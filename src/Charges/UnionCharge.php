<?php

namespace iBrand\Component\Pay\Charges;

use iBrand\Component\Pay\Contracts\PayChargeContract;
use iBrand\Component\Pay\Exceptions\GatewayException;
use iBrand\Component\Pay\Models\Charge;
use GuzzleHttp\Client;

class UnionCharge extends BaseCharge implements PayChargeContract
{

	const MINI_PROGRAM_CHARGE_URL = 'https://qr.chinaums.com/netpay-route-server/api/';

	const WEB_PAY_CHARGE_URL = 'https://qr.chinaums.com/netpay-portal/webpay/pay.do';
	//const WEB_PAY_CHARGE_URL = 'https://qr-test2.chinaums.com/netpay-portal/webpay/pay.do';

	/**
	 * 创建支付请求
	 *
	 * @param array                                    $data 支付数据
	 * @param string                                   $type 业务类型
	 * @param string                                   $app  支付参数APP，config payments 数组中的配置项名称
	 * @param \iBrand\Component\Pay\Models\Charge|null $charge
	 *
	 * @return \iBrand\Component\Pay\Models\Charge
	 * @throws \iBrand\Component\Pay\Exceptions\GatewayException
	 * @throws \Exception
	 */
	public function create(array $data, $type = 'default', $app = 'default', Charge $charge = null)
	{
		$this->validateParams($data);

		$support = config('ibrand.pay.union.support');
		if (!in_array($data['channel'], $support)) {
			throw new \InvalidArgumentException("Unsupported channel [{$data['channel']}]");
		}

		if (is_null($charge)) {
			$charge = Charge::where('order_no', $data['order_no'])->where('paid', true)->first();

			if ($charge) {
				throw new GatewayException('订单：' . $data['order_no'] . '已支付');
			}

			$modelData = array_merge(['app' => $app, 'type' => $type], array_only($data, ['channel', 'order_no', 'client_ip', 'subject', 'amount', 'body', 'extra', 'time_expire', 'metadata', 'description']));
			$payModel  = Charge::create($modelData);
		} else {
			$payModel = $charge;
			$data     = $charge->toArray();
		}

		try {

			$config              = config('ibrand.pay.union.' . $data['channel']);
			$config['notifyUrl'] = route('pay.union.notify', ['channel' => $data['channel']]);
			$credential          = null;
			switch ($data['channel']) {
				case 'wx_lite':
					$credential = $this->createMiniProgramCharge($data, $type, $app, $config);
					break;
				case 'wx_pub':
					$config['msgType'] = 'WXPay.jsPay';
					$credential        = $this->createWapCharge($data, $type, $app, $config);
					break;
				case 'alipay_wap':
					$config['msgType'] = 'trade.jsPay';
					$credential        = $this->createWapCharge($data, $type, $app, $config);
					break;
			}

			$payModel->credential = $credential;
			$payModel->save();

			return $credential;
		} catch (\Exception $exception) {
			throw  new \Exception($exception->getMessage());
		}
	}

	/**
	 * @param $data
	 * @param $type
	 * @param $app
	 * @param $config
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function createMiniProgramCharge($data, $type, $app, $config)
	{
		$chargeData = [
			'attachedData'     => [
				'type'    => $type,
				'channel' => $data['channel'],
			],
			'msgSrc'           => $config['msgSrc'],
			'msgType'          => 'wx.unifiedOrder',
			'requestTimestamp' => date('Y-m-d H:i:s'),
			'merOrderId'       => $data['order_no'],
			'mid'              => $config['mid'],
			'tid'              => $config['tid'],
			'instMid'          => $config['instMid'],
			'totalAmount'      => $data['amount'],
			'notifyUrl'        => $config['notifyUrl'],
			'signType'         => 'MD5',
			'subAppId'         => config('ibrand.wechat.mini_program.' . $app . '.app_id'),
			'subOpenId'        => $data['openid'],
			'tradeType'        => 'MINI',
		];

		if (isset($data['divisionFlag']) && $data['divisionFlag']) {
			$division   = [
				'divisionFlag'   => true,
				'platformAmount' => $data['platformAmount'],
				'subOrders'      => $data['subOrders'],
			];
			$chargeData = array_merge($chargeData, $division);
		}

		$chargeData['sign'] = $this->makeSign($chargeData, $config['signKey']);
		$options            = [
			'body'    => json_encode($chargeData),
			'headers' => ['content-type' => 'application/json'],
		];

		$cli      = new Client();
		$response = $cli->post(self::MINI_PROGRAM_CHARGE_URL, $options);
		$contents = $response->getBody()->getContents();
		$result   = json_decode($contents, true);
		if (empty($result) || !isset($result['miniPayRequest']) || $result['errCode'] != 'SUCCESS') {

			throw new \Exception(isset($result['errMsg']) ? $result['errMsg'] : '请求支付失败');
		}

		return $result;
	}

	public function createWapCharge($data, $type, $app, $config)
	{
		$params = [
			'attachedData'     => [
				'type'    => $type,
				'channel' => $data['channel'],
			],
			'msgSrc'           => $config['msgSrc'],
			'msgType'          => $config['msgType'],
			'requestTimestamp' => date('Y-m-d H:i:s'),
			'merOrderId'       => $data['order_no'],
			'mid'              => $config['mid'],
			'tid'              => $config['tid'],
			'instMid'          => $config['instMid'],
			'totalAmount'      => $data['amount'],
			'notifyUrl'        => $config['notifyUrl'],
			'returnUrl'        => $data['returnUrl'],
			'signType'         => 'MD5',
		];

		if ($config['msgType'] == 'WXPay.jsPay') {
			$params['subOpenId'] = $data['openid'];
			$params['subAppId']  = config('ibrand.wechat.official_account.' . $app . '.app_id');
		}

		$params['sign'] = $this->makeSign($params, $config['signKey']);
		$buff           = "";
		foreach ($params as $k => $v) {
			if ($v !== "" && !is_array($v) && !in_array($k, ['requestTimestamp', 'notifyUrl', 'returnUrl', 'divisionFlag'])) {
				$buff .= $k . "=" . $v . "&";
			} elseif ($k == "divisionFlag") {
				$buff .= $k . "=true&";
			} elseif ($k == "requestTimestamp" || $k == "notifyUrl" || $k == "returnUrl") {
				$buff .= $k . "=" . urlencode($v) . "&";
			} elseif ($v !== "" && is_array($v) && !empty($v) && !in_array($k, ['requestTimestamp', 'notifyUrl', 'returnUrl', 'divisionFlag'])) {
				$buff .= $k . "=" . urlencode(json_encode($v)) . "&";
			} else {
				continue;
			}
		}

		$buff = trim($buff, "&");

		return ['url' => self::WEB_PAY_CHARGE_URL . '?' . $buff];
	}

	/**
	 * 生成签名
	 *
	 * @param array  $config
	 * @param        $signKey
	 * @param string $type
	 *
	 * @return bool|string
	 */
	public function makeSign(array $config, $signKey, $type = 'MD5')
	{
		//签名步骤一：按字典序排序参数
		ksort($config);
		$string = $this->toUrlParams($config);
		//签名步骤二：在string后加入KEY
		$string = $string . $signKey;
		//签名步骤三：MD5加密或者HMAC-SHA256
		if ($type == "MD5") {
			$string = md5($string);
		} elseif ($type == "SHA256") {
			$string = hash_hmac("sha256", $string, $signKey);
		} else {
			return false;
		}

		//签名步骤四：所有字符转为大写
		$result = strtoupper($string);

		return $result;
	}

	/**
	 * 格式化参数格式化成url参数
	 *
	 * @param array $config
	 *
	 * @return string
	 */
	public function toUrlParams(array $config)
	{
		$buff = "";
		foreach ($config as $k => $v) {
			if ($k != "sign" && $k != "divisionFlag" && $v !== "" && !is_array($v)) {
				$buff .= $k . "=" . $v . "&";
			} elseif ($k == "divisionFlag") {
				$buff .= $k . "=true&";
			} elseif ($k != "sign" && $k != "divisionFlag" && $v !== "" && is_array($v) && !empty($v)) {
				$buff .= $k . "=" . json_encode($v) . "&";
			} else {
				continue;
			}
		}

		$buff = trim($buff, "&");

		return $buff;
	}

	public function find($order_no)
	{
		/*$config = settings('shitang_miniProgram_pay_config');

		$queryData = [
			'msgSrc'           => $config['msgSrc'],
			'msgType'          => 'query',
			'requestTimestamp' => date('Y-m-d H:i:s'),
			'mid'              => $config['mid'],
			'tid'              => $config['tid'],
			'instMid'          => $config['instMid'],
			'merOrderId'       => $order_no,
		];

		$result = UnionPayService::orderQuery($queryData, $config);
		if ($result) {
			$result['attachedData'] = json_decode($result['attachedData'], true);

			return $result;
		}

		return false;*/
	}
}
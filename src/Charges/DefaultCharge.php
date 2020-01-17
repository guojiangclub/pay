<?php

/*
 * This file is part of ibrand/pay.
 *
 * (c) 果酱社区 <https://guojiang.club>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace iBrand\Component\Pay\Charges;

use Carbon\Carbon;
use iBrand\Component\Pay\Contracts\PayChargeContract;
use iBrand\Component\Pay\Exceptions\GatewayException;
use iBrand\Component\Pay\Models\Charge;
use Yansongda\Pay\Exceptions\GatewayException as PayException;
use Yansongda\Pay\Pay;

class DefaultCharge extends BaseCharge implements PayChargeContract
{
	/**
	 * 创建支付请求
	 *
	 * @param array  $data   支付数据
	 * @param string $type   业务类型
	 * @param string $app    支付参数APP，config payments 数组中的配置项名称
	 * @param Charge $charge model.
	 *
	 * @return mixed
	 *
	 * @throws GatewayException
	 */
	public function create(array $data, $type = 'default', $app = 'default', Charge $charge = null)
	{
		$this->validateParams($data);

		if (is_null($charge) && !in_array($data['channel'], ['wx_pub', 'wx_pub_qr', 'wx_lite', 'alipay_wap', 'alipay_pc_direct', 'wx_app'])) {
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
			$credential   = null;
			$out_trade_no = null;
			switch ($data['channel']) {
				case 'wx_pub':
				case 'wx_pub_qr':
				case 'wx_lite':
				case 'wx_app':
					$config               = config('ibrand.pay.default.wechat.' . $app);
					$config['notify_url'] = route('pay.wechat.notify', ['app' => $app]);
					$credential           = $this->createWechatCharge($data, $config, $out_trade_no);
					break;
				case 'alipay_wap':
				case 'alipay_pc_direct':
					$config               = config('ibrand.pay.default.alipay.' . $app);
					$config['notify_url'] = route('pay.alipay.notify', ['app' => $app]);
					$credential           = $this->createAlipayCharge($data, $config, $out_trade_no);
			}

			$payModel->credential   = $credential;
			$payModel->out_trade_no = $out_trade_no;
			$payModel->save();

			return $payModel;
		} catch (\Yansongda\Pay\Exceptions\Exception $exception) {
			throw  new GatewayException('支付通道错误');
		}
	}

	/**
	 * @param $data
	 * @param $config
	 *
	 * @return array|null
	 */
	protected function createWechatCharge($data, $config, &$out_trade_no)
	{
		$out_trade_no = $this->getOutTradeNo($data['order_no'], $data['channel']);

		$chargeData = [
			'body'             => mb_strcut($data['body'], 0, 32, 'UTF-8'),
			'out_trade_no'     => $out_trade_no,
			'total_fee'        => abs($data['amount']),
			'spbill_create_ip' => $data['client_ip'],
		];

		if (isset($data['time_expire'])) {
			$chargeData['time_expire'] = $data['time_expire'];
		}

		if (isset($data['metadata'])) {
			$chargeData['attach'] = json_encode($data['metadata']);
		}

		switch ($data['channel']) {
			case 'wx_pub_qr':
				$pay = Pay::wechat($config)->scan($chargeData);

				return ['wechat' => $pay];
			case 'wx_pub':
				$chargeData['openid'] = $data['extra']['openid'];
				$pay                  = Pay::wechat($config)->mp($chargeData);

				return ['wechat' => $pay];

			case 'wx_lite':
				$chargeData['openid'] = $data['extra']['openid'];
				$pay                  = Pay::wechat($config)->miniapp($chargeData);

				return ['wechat' => $pay];
			case 'wx_app':
				$pay = Pay::wechat($config)->app($chargeData);

				return ['wechat' => $pay->getContent()];
			default:
				return null;
		}
	}

	public function createAlipayCharge($data, $config, &$out_trade_no)
	{
		$out_trade_no = $this->getOutTradeNo($data['order_no'], $data['channel']);

		$chargeData = [
			'body'         => mb_strcut($data['body'], 0, 32, 'UTF-8'),
			'out_trade_no' => $out_trade_no,
			'total_amount' => number_format($data['amount'] / 100, 2, '.', ''),
			'subject'      => mb_strcut($data['subject'], 0, 32, 'UTF-8'),
			'client_ip'    => $data['client_ip'],
		];

		if (isset($data['time_expire']) && ($gap = strtotime($data['time_expire']) - Carbon::now()->timestamp) > 0) {
			$chargeData['timeout_express'] = floor($gap / 60) . 'm';
		}

		if (isset($data['metadata'])) {
			$chargeData['passback_params'] = json_encode($data['metadata']);
		}

		if (isset($data['extra']['failUrl'])) {
			$chargeData['quit_url'] = $data['extra']['failUrl'];
		}

		if (isset($data['extra']['successUrl'])) {
			$chargeData['success_url'] = $data['extra']['successUrl'];
			$config['return_url']      = $data['extra']['successUrl'];
		}

		if ('alipay_pc_direct' == $data['channel']) {
			$ali_pay = Pay::alipay($config)->web($chargeData);

			return [
				'alipay' => html_entity_decode($ali_pay),
			];
		}

		if ('alipay_wap' == $data['channel']) {
			$ali_pay = Pay::alipay($config)->wap($chargeData);

			return [
				'alipay' => html_entity_decode($ali_pay),
			];
		}
	}

	public function find($charge_id)
	{
		$charge = Charge::where('charge_id', $charge_id)->first();

		$config = config('ibrand.pay.default.wechat.' . $charge->app);

		$order = [
			'out_trade_no' => $charge->out_trade_no,
		];

		if ('wx_lite' == $charge->channel) {
			$order['type'] = 'miniapp';
		}

		if ('alipay_pc_direct' == $charge->channel || 'alipay_wap' == $charge->channel) {
			$config = config('ibrand.pay.default.alipay.' . $charge->app);

			$result = Pay::alipay($config)->find($order);

			if ('TRADE_SUCCESS' == $result['trade_status'] || 'TRADE_FINISHED' == $result['trade_status']) {
				$charge->transaction_meta = json_encode($result);
				$charge->transaction_no   = $result['trade_no'];
				$charge->time_paid        = Carbon::now();
				$charge->paid             = 1;
				$charge->save();
			} else {
				$charge->transaction_meta = json_encode($result);
				$charge->save();
			}

			return $charge;
		}

		try {
			$result                   = Pay::wechat($config)->find($order);
			$charge->transaction_meta = json_encode($result);
			$charge->transaction_no   = $result['transaction_id'];
			$charge->time_paid        = Carbon::createFromTimestamp(strtotime($result['time_end']));
			$charge->paid             = 1;
			$charge->save();

			return $charge;
		} catch (PayException $exception) {
			$result = $exception->raw;
			if ('FAIL' == $result['return_code']) {
				$charge->failure_code = $result['return_code'];
				$charge->failure_msg  = $result['return_msg'];
				$charge->save();

				return $charge;
			}

			if ('FAIL' == $result['result_code'] || 'SUCCESS' != $result['trade_state']) {
				$charge->failure_code = $result['err_code'];
				$charge->failure_msg  = $result['err_code_des'];
				$charge->save();
			}
		}
	}
}

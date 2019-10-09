<?php

/*
 * This file is part of ibrand/pay.
 *
 * (c) 果酱社区 <https://guojiang.club>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace iBrand\Component\Pay\Controllers;

use Carbon\Carbon;
use iBrand\Component\Pay\Facades\PayNotify;
use Illuminate\Routing\Controller;
use Yansongda\Pay\Pay;

class AlipayNotifyController extends Controller
{
    public function notify($app)
    {
        $config = config('ibrand.pay.default.alipay.'.$app);

        $pay = Pay::alipay($config);

        $data = $pay->verify();

        if ('TRADE_SUCCESS' == $data['trade_status'] || 'TRADE_FINISHED' == $data['trade_status']) {
            $charge = \iBrand\Component\Pay\Models\Charge::ofOutTradeNo($data['out_trade_no'])->first();

            if (!$charge) {
                return response('支付失败', 500);
            }

            $charge->transaction_meta = json_encode($data);
            $charge->transaction_no = $data['trade_no'];
            $charge->time_paid = Carbon::createFromTimestamp(strtotime($data['gmt_payment']));
            $charge->paid = 1;
            $charge->save();

            if ($charge->amount !== intval($data['total_amount'] * 100)) {
                return response('支付失败', 500);
            }

            PayNotify::success($charge->type, $charge);

            return $pay->success();
        }

        return response('alipay notify fail.', 500);
    }
}

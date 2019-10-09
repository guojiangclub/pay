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

use Faker\Factory;
use iBrand\Component\Pay\Models\Charge as ChargeModel;
use Illuminate\Routing\Controller;

class AlipayTestController extends Controller
{
    public function wap()
    {
        $charge = ChargeModel::create([
            'app' => 'default', 'type' => 'default', 'channel' => 'alipay_wap', 'order_no' => build_order_no(), 'client_ip' => request()->getClientIp(), 'amount' => 1, 'subject' => Factory::create()->word, 'body' => Factory::create()->word, 'extra' => ['successUrl' => 'https://www.baidu.com', 'failUrl' => 'https://www.baidu.com'],
        ]);

        return redirect(route('payment.alipay.pay', ['charge_id' => $charge->charge_id]));
    }
}

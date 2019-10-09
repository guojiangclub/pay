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

class OfficialAccountTestController extends Controller
{
    public function mock()
    {
        $charge = ChargeModel::create([
            'app' => 'default', 'type' => 'default', 'channel' => 'wx_pub', 'order_no' => build_order_no(), 'client_ip' => request()->getClientIp(), 'amount' => 1, 'subject' => Factory::create()->word, 'body' => Factory::create()->word, 'extra' => ['successUrl' => 'https://www.baidu.com', 'failUrl' => 'https://www.baidu.com', 'failUcenter' => 'https://www.baidu.com'],
        ]);

        return redirect(route('payment.wechat.getCode', ['charge_id' => $charge->charge_id]));
    }
}

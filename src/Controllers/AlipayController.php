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

use iBrand\Component\Pay\Facades\Charge;
use iBrand\Component\Pay\Models\Charge as ChargeModel;
use Illuminate\Routing\Controller;

class AlipayController extends Controller
{
    public function pay()
    {
        $chargeId = request('charge_id');
        $chargeModel = ChargeModel::ofChargeId($chargeId)->first();

        $charge = Charge::create([], $chargeModel->type, $chargeModel->app, $chargeModel);

        return view('ibrand.pay::defaultAliPay', compact('charge'));
    }
}

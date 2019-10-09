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

use iBrand\Common\Wechat\Factory;
use iBrand\Component\Pay\Facades\Charge;
use iBrand\Component\Pay\Models\Charge as ChargeModel;
use Illuminate\Routing\Controller;

class OfficialAccountController extends Controller
{
    public function getCode()
    {
        $chargeId = request('charge_id');

        $chargeModel = ChargeModel::ofChargeId($chargeId)->first();

        $url = route('payment.wechat.wxPay', ['charge_id' => $chargeId]);

        $officialAccount = Factory::officialAccount($chargeModel->app);

        return $officialAccount->oauth->scopes(['snsapi_base'])->setRedirectUrl($url)->redirect();
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function wxPay()
    {
        $chargeId = request('charge_id');
        $chargeModel = ChargeModel::ofChargeId($chargeId)->first();

        $officialAccount = Factory::officialAccount($chargeModel->app);
        $user = $officialAccount->oauth->user();

        $openid = $user->getId();
        $chargeModel->extra = array_merge($chargeModel->extra, ['openid' => $openid]);

        $charge = Charge::create([], $chargeModel->type, $chargeModel->app, $chargeModel);

        $successUrl = $chargeModel->extra['successUrl'];
        $failUrl = $chargeModel->extra['failUrl'];

        return view('ibrand.pay::defaultWxPay', compact('charge', 'successUrl', 'failUrl'));
    }
}

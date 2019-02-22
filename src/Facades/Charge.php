<?php

/*
 * This file is part of ibrand/pay.
 *
 * (c) iBrand <https://www.ibrand.cc>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace iBrand\Component\Pay\Facades;

use iBrand\Component\Pay\Contracts\PayChargeContract;
use Illuminate\Support\Facades\Facade;

/**
 * Charge Facade.
 *
 * @method PayChargeContract create(array $data, $type = 'default', $app = 'default', Charge $charge = null)
 * @method PayChargeContract find($charge_id)
 */
class Charge extends Facade
{
    /**
     * Return the facade accessor.
     *
     * @return string
     */
    public static function getFacadeAccessor()
    {
        return 'ibrand.pay.charge';
    }
}

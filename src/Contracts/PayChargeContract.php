<?php

/*
 * This file is part of ibrand/pay.
 *
 * (c) 果酱社区 <https://guojiang.club>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace iBrand\Component\Pay\Contracts;

use iBrand\Component\Pay\Models\Charge;

interface PayChargeContract
{
    public function create(array $data, $type = 'default', $app = 'default', Charge $charge = null);

    public function find($charge_id);
}

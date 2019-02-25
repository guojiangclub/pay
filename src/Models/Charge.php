<?php

/*
 * This file is part of ibrand/pay.
 *
 * (c) iBrand <https://www.ibrand.cc>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace iBrand\Component\Pay\Models;

use Hidehalo\Nanoid\Client;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Charge.
 */
class Charge extends Model
{
    /**
     * @var string
     */
    protected $table = 'ibrand_pay_charge';

    /**
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Charge constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $client = new Client();

        $this->charge_id = 'ch_'.$client->generateId($size = 24);
    }

    /**
     * @param $value
     */
    public function setMetadataAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['metadata'] = json_encode($value);
        }
    }

    /**
     * @param $value
     */
    public function setExtraAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['extra'] = json_encode($value);
        }
    }

    /**
     * @param $value
     *
     * @return mixed
     */
    public function getMetadataAttribute($value)
    {
        if (!empty($value)) {
            $value = json_decode($value, true);
        }

        return $value;
    }

    /**
     * @param $value
     *
     * @return mixed
     */
    public function getExtraAttribute($value)
    {
        if (!empty($value)) {
            $value = json_decode($value, true);
        }

        return $value;
    }

    /**
     * @param $value
     */
    public function setCredentialAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['credential'] = json_encode($value);
        }
    }

    /**
     * @param $value
     *
     * @return mixed
     */
    public function getCredentialAttribute($value)
    {
        if (!empty($value)) {
            $value = json_decode($value, true);
        }

        return $value;
    }

    /**
     * @param $query
     * @param $chargeId
     *
     * @return mixed
     */
    public function scopeOfChargeId($query, $chargeId)
    {
        return $query->where('charge_id', $chargeId);
    }

    /**
     * @param $query
     * @param $tradeNo
     * @return mixed
     */
    public function scopeOfOutTradeNo($query, $tradeNo)
    {
        return $query->where('out_trade_no', $tradeNo);
    }
}

<?php

namespace iBrand\Component\Pay\Http\Controllers;

use Illuminate\Routing\Controller;

class UnionPayNotifyController extends Controller
{
	public function notify($channel)
	{
		$config = config('ibrand.pay.default.union.' . $channel);
	}
}
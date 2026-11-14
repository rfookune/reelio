<?php

namespace App\Http\Support\Visitor;

use Illuminate\Http\Request;

/**
 * Who a rate limit counts against: the signed-in user, wherever they connect from, or a guest's IP.
 */
class RateLimitKey
{
    public static function for(Request $request): string
    {
        return $request->user() !== null ? 'user:'.$request->user()->id : 'ip:'.$request->ip();
    }
}

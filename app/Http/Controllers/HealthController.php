<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): Response
    {
        try {
            DB::select('select 1');
            Cache::get('health');
        } catch (Throwable) {
            return response('', 503)->header('Cache-Control', 'no-store');
        }

        return response('', 200)->header('Cache-Control', 'no-store');
    }
}

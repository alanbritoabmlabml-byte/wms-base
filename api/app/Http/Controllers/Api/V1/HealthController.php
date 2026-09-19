<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $db = 'ok';
        } catch (Throwable) {
            $db = 'down';
        }

        return response()->json([
            'status' => $db === 'ok' ? 'ok' : 'degraded',
            'version' => config('app.wms_version'),
            'db' => $db,
        ], $db === 'ok' ? 200 : 503);
    }
}

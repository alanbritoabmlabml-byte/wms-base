<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScanResolveRequest;
use App\Services\ScanResolver;
use Illuminate\Http\JsonResponse;

class ScanController extends Controller
{
    public function __construct(private readonly ScanResolver $resolver) {}

    public function resolve(ScanResolveRequest $request): JsonResponse
    {
        $payload = $this->resolver->resolve(
            $request->string('code')->toString(),
            $request->integer('warehouse_id'),
        );

        return response()->json($payload);
    }
}

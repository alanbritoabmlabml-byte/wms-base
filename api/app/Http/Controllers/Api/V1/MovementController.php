<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovementRequest;
use App\Services\MovementService;
use Illuminate\Http\JsonResponse;

class MovementController extends Controller
{
    public function __construct(private readonly MovementService $movements) {}

    public function store(MovementRequest $request): JsonResponse
    {
        $result = $this->movements->post($request->validated(), $request->user());

        return response()->json($result['body'], $result['status']);
    }
}

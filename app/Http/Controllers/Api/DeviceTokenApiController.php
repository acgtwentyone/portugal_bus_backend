<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;

class DeviceTokenApiController extends ApiController
{
    /**
     * Register (or refresh) an FCM device token. No PII, not tied to any account.
     */
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        DeviceToken::updateOrCreate(
            ['token' => $request->validated('token')],
        );

        return response()->json(['status' => 'ok'], 201);
    }
}

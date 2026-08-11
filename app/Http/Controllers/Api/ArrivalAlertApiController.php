<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreArrivalAlertRequest;
use App\Models\ArrivalAlert;
use Illuminate\Http\JsonResponse;

class ArrivalAlertApiController extends ApiController
{
    /**
     * Activate a "bus is arriving" alert for a stop/route/direction.
     */
    public function store(StoreArrivalAlertRequest $request): JsonResponse
    {
        $alert = ArrivalAlert::create([
            ...$request->validated(),
            'threshold_minutes' => $request->validated('threshold_minutes') ?? config('app.arrival_alert_threshold_minutes'),
            'locale' => $request->validated('locale') ?? config('app.locale'),
        ]);

        return response()->json(['id' => $alert->id], 201);
    }

    /**
     * Cancel an active alert before it fires.
     */
    public function destroy(ArrivalAlert $alert): JsonResponse
    {
        $alert->delete();

        return response()->json(['status' => 'ok']);
    }
}

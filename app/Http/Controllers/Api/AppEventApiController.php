<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreAppEventRequest;
use App\Models\AppEvent;
use Illuminate\Http\JsonResponse;

class AppEventApiController extends ApiController
{
    /**
     * Record a review-prompt funnel event from the mobile app.
     */
    public function store(StoreAppEventRequest $request): JsonResponse
    {
        AppEvent::create($request->validated());

        return response()->json(['status' => 'ok'], 201);
    }

    /**
     * Aggregate event counts per type.
     *
     * Equivalent raw SQL: SELECT event_type, COUNT(*) AS total FROM app_events GROUP BY event_type;
     */
    public function summary(): JsonResponse
    {
        $counts = AppEvent::query()
            ->selectRaw('event_type, count(*) as total')
            ->groupBy('event_type')
            ->pluck('total', 'event_type');

        return response()->json($counts);
    }
}

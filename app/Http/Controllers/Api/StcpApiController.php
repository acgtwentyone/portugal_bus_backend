<?php

namespace App\Http\Controllers\Api;

use App\CacheKeysEnum;
use App\Models\BusLine;
use App\Services\StcpService;
use Illuminate\Http\Request;
use App\Http\Resources\BusLineResource;
use App\Http\Resources\BusStopResource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StcpApiController extends ApiController
{
    public function __construct(private StcpService $stcpService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function lines(Request $request)
    {
        $data = $request->validate([
            'search' => 'sometimes|nullable|string',
        ]);

        if (!empty($data['search'])) {
            $searchTerm = $data['search'];

            // Query database if user searches
            return BusLine::query()
                ->where(function ($q) use ($searchTerm) {
                    $q->where('code', 'like', "%{$searchTerm}%")
                        ->orWhere('name', 'like', "%{$searchTerm}%");
                })
                ->orderBy('network', 'asc')
                ->orderByRaw('code REGEXP "^[0-9]" DESC')
                ->orderByRaw('LENGTH(code) ASC')
                ->orderBy('code', 'asc')
                ->get();
        }

        $lines = Cache::remember(CacheKeysEnum::STCP_LINES_ALL, now()->addHours(24), function () {
            return BusLine::query()
                ->orderBy('network', 'asc')
                ->orderByRaw('code REGEXP "^[0-9]" DESC')
                ->orderByRaw('LENGTH(code) ASC')
                ->orderBy('code', 'asc')
                ->get();
        });

        return BusLineResource::collection($lines);
    }

    /**
     * Get bus tops by code
     */
    public function stops(string $code)
    {
        $stops = Cache::remember(CacheKeysEnum::STCP_STOPS_BY_CODE . '_' . $code, now()->addHours(24), function () use ($code) {
            return DB::table('bus_stops')
                ->join('bus_lines', 'bus_stops.bus_line_id', '=', 'bus_lines.id')
                ->where('bus_lines.code', $code)
                ->select(
                    'bus_stops.id',
                    'bus_lines.id as bus_id',
                    'bus_lines.code as bus_code',
                    'bus_lines.name as bus_name',
                    'bus_lines.network as bus_network',
                    'bus_stops.directions_0',
                    'bus_stops.directions_1'
                )
                ->first();
        });

        return new BusStopResource($stops);
    }

    /**
     * Get today's scheduled departure times for a stop, grouped by line/direction.
     */
    public function scheduleCards(string $stopId)
    {
        $today = now('Europe/Lisbon')->format('Y-m-d');
        $cacheKey = CacheKeysEnum::STCP_SCHEDULE_CARDS . ':' . $stopId . ':' . $today;

        $data = Cache::remember($cacheKey, now('Europe/Lisbon')->endOfDay(), function () use ($stopId, $today) {
            return $this->stcpService->buildScheduleCards($stopId, $today);
        });

        return response()->json($data);
    }
}

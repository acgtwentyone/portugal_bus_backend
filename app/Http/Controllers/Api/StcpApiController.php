<?php

namespace App\Http\Controllers\Api;

use App\CacheKeysEnum;
use App\Models\BusLine;
use Illuminate\Http\Request;
use App\Http\Resources\BusLineResource;
use App\Http\Resources\BusStopResource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StcpApiController extends ApiController
{
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
            return $this->buildScheduleCards($stopId, $today);
        });

        return response()->json($data);
    }

    /**
     * Find every (route_id, direction_id) combination whose stop list contains $stopId.
     */
    private function findLineDirectionsForStop(string $stopId): array
    {
        $rows = DB::table('bus_stops')
            ->join('bus_lines', 'bus_stops.bus_line_id', '=', 'bus_lines.id')
            ->where(function ($query) use ($stopId) {
                $query->whereRaw('JSON_CONTAINS(bus_stops.directions_0, JSON_OBJECT("stop_id", ?))', [$stopId])
                    ->orWhereRaw('JSON_CONTAINS(bus_stops.directions_1, JSON_OBJECT("stop_id", ?))', [$stopId]);
            })
            ->select(
                'bus_lines.code as route_id',
                'bus_lines.name as route_name',
                'bus_stops.directions_0',
                'bus_stops.directions_1'
            )
            ->get();

        $lineDirections = [];

        foreach ($rows as $row) {
            foreach ([0 => $row->directions_0, 1 => $row->directions_1] as $directionId => $json) {
                $stops = json_decode($json, true) ?? [];

                $servesStop = collect($stops)->contains(fn ($stop) => ($stop['stop_id'] ?? null) === $stopId);

                if ($servesStop) {
                    $lineDirections[] = [
                        'route_id' => $row->route_id,
                        'route_name' => $row->route_name,
                        'direction_id' => $directionId,
                    ];
                }
            }
        }

        return $lineDirections;
    }

    /**
     * Fetch schedules from stcp.pt for every line/direction serving this stop and aggregate them.
     */
    private function buildScheduleCards(string $stopId, string $date): array
    {
        $lineDirections = $this->findLineDirectionsForStop($stopId);

        if (empty($lineDirections)) {
            abort(404, "No lines found serving stop {$stopId}");
        }

        $activeServiceId = null;

        try {
            $servicesResponse = Http::timeout(15)->get("https://stcp.pt/api/stops/{$stopId}/services", [
                'date' => $date,
            ]);

            if ($servicesResponse->successful()) {
                $activeServiceId = $servicesResponse->json('active_service_id');
            }
        } catch (\Exception $e) {
            Log::error("STCP services fetch failed for stop {$stopId}: " . $e->getMessage());
        }

        $lines = [];
        $partial = false;

        foreach ($lineDirections as $lineDirection) {
            if (!$activeServiceId) {
                $lines[] = [
                    'route_id' => $lineDirection['route_id'],
                    'route_name' => $lineDirection['route_name'],
                    'direction_id' => $lineDirection['direction_id'],
                    'times' => [],
                    'error' => 'Could not determine active schedule for this date',
                ];
                $partial = true;
                continue;
            }

            try {
                $schedule = $this->fetchSchedule($stopId, $lineDirection['route_id'], $activeServiceId, $lineDirection['direction_id']);

                $lines[] = [
                    'route_id' => $lineDirection['route_id'],
                    'route_name' => $lineDirection['route_name'],
                    'direction_id' => $lineDirection['direction_id'],
                    'headsign' => $schedule['headsign'],
                    'times' => $schedule['times'],
                ];
            } catch (\Exception $e) {
                Log::error("STCP schedule fetch failed for stop {$stopId}, route {$lineDirection['route_id']}, direction {$lineDirection['direction_id']}: " . $e->getMessage());

                $lines[] = [
                    'route_id' => $lineDirection['route_id'],
                    'route_name' => $lineDirection['route_name'],
                    'direction_id' => $lineDirection['direction_id'],
                    'times' => [],
                    'error' => 'Failed to fetch schedule',
                ];
                $partial = true;
            }
        }

        return [
            'stop_id' => $stopId,
            'lines' => $lines,
            'partial' => $partial,
        ];
    }

    /**
     * Call the stcp.pt schedule endpoint, flatten the hour-keyed response into a sorted time list,
     * and determine the real destination shown on the bus for this stop/direction.
     *
     * The line's route_name (e.g. "Campanhã - Castelo Do Queijo") is a static official designation
     * that stays the same for both directions, so it can't be used to tell riders which way the bus
     * is actually going. The stcp.pt schedule instead carries a per-trip "headsign" (the destination
     * shown on the bus), which we use here — picking the most frequent one for short-working trips
     * that don't run the full route.
     */
    private function fetchSchedule(string $stopId, string $routeId, string $serviceId, int $directionId): array
    {
        $response = Http::timeout(15)->get("https://stcp.pt/api/stops/{$stopId}/schedule", [
            'route_id' => $routeId,
            'service_id' => $serviceId,
            'direction_id' => $directionId,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException("STCP schedule endpoint returned status {$response->status()}");
        }

        $schedule = $response->json('schedule') ?? [];

        $hours = array_keys($schedule);
        sort($hours, SORT_NUMERIC);

        $times = [];
        $headsignCounts = [];
        foreach ($hours as $hour) {
            foreach ($schedule[$hour] as $entry) {
                if (!empty($entry['departure_time'])) {
                    $times[] = substr($entry['departure_time'], 0, 5);
                }

                if (!empty($entry['headsign'])) {
                    $headsignCounts[$entry['headsign']] = ($headsignCounts[$entry['headsign']] ?? 0) + 1;
                }
            }
        }

        arsort($headsignCounts);
        $headsign = array_key_first($headsignCounts);

        return [
            'times' => $times,
            'headsign' => $headsign,
        ];
    }
}

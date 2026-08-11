<?php

namespace App\Services;

use App\CacheKeysEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to stcp.pt: schedules (day-cached, powers scheduleCards) and the live
 * realtime feed (uncached, powers the arrival-alert polling job).
 */
class StcpService
{
    /**
     * Live arrivals for a stop, straight from stcp.pt's own realtime feed (the same
     * one the app calls directly for the real-time / pull-to-refresh screen).
     *
     * @return array<int, array{route_short_name: string, trip_headsign?: string, estimated_arrival_time?: string, arrival_minutes: int, status?: string}>
     */
    public function getArrivals(string $stopId): array
    {
        try {
            $response = Http::timeout(15)->get(config('app.stcp_api_base_url') . "/stops/{$stopId}/realtime");

            if (!$response->successful()) {
                return [];
            }

            return $response->json('arrivals') ?? [];
        } catch (\Exception $e) {
            Log::error("STCP realtime fetch failed for stop {$stopId}: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Find every (route_id, direction_id) combination whose stop list contains $stopId.
     */
    public function findLineDirectionsForStop(string $stopId): array
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
    public function buildScheduleCards(string $stopId, string $date): array
    {
        $lineDirections = $this->findLineDirectionsForStop($stopId);

        if (empty($lineDirections)) {
            abort(404, "No lines found serving stop {$stopId}");
        }

        $activeServiceId = $this->getActiveServiceId($stopId, $date);

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
                $schedule = $this->fetchSchedule($stopId, $lineDirection['route_id'], $activeServiceId, $lineDirection['direction_id'], $date);

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
     * Cached for the day: the active stcp.pt schedule id for a stop, used by every
     * route/direction fetch for that stop.
     */
    private function getActiveServiceId(string $stopId, string $date): ?string
    {
        $cacheKey = CacheKeysEnum::STCP_ACTIVE_SERVICE_ID . ':' . $stopId . ':' . $date;

        return Cache::remember($cacheKey, now('Europe/Lisbon')->endOfDay(), function () use ($stopId, $date) {
            try {
                $response = Http::timeout(15)->get(config('app.stcp_api_base_url') . "/stops/{$stopId}/services", [
                    'date' => $date,
                ]);

                if ($response->successful()) {
                    return $response->json('active_service_id');
                }
            } catch (\Exception $e) {
                Log::error("STCP services fetch failed for stop {$stopId}: " . $e->getMessage());
            }

            return null;
        });
    }

    /**
     * Cached for the day: call the stcp.pt schedule endpoint, flatten the hour-keyed response
     * into a sorted time list, and determine the real destination shown on the bus for this
     * stop/direction.
     *
     * The line's route_name (e.g. "Campanhã - Castelo Do Queijo") is a static official designation
     * that stays the same for both directions, so it can't be used to tell riders which way the bus
     * is actually going. The stcp.pt schedule instead carries a per-trip "headsign" (the destination
     * shown on the bus), which we use here — picking the most frequent one for short-working trips
     * that don't run the full route.
     */
    private function fetchSchedule(string $stopId, string $routeId, string $serviceId, int $directionId, string $date): array
    {
        $cacheKey = CacheKeysEnum::STCP_ROUTE_SCHEDULE . ':' . $stopId . ':' . $routeId . ':' . $directionId . ':' . $serviceId . ':' . $date;

        return Cache::remember($cacheKey, now('Europe/Lisbon')->endOfDay(), function () use ($stopId, $routeId, $serviceId, $directionId) {
            $response = Http::timeout(15)->get(config('app.stcp_api_base_url') . "/stops/{$stopId}/schedule", [
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
        });
    }
}

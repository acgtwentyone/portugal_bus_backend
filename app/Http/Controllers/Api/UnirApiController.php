<?php

namespace App\Http\Controllers\Api;

use App\Models\UnirBusLine;
use Illuminate\Http\Request;
use App\Http\Resources\UnirBusLineResource;
use App\Http\Resources\UnirBusStopResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UnirApiController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function lines(Request $request)
    {
        $data = $request->validate([
            'search' => 'sometimes|nullable|string',
        ]);

        $query = UnirBusLine::query();

        if (!empty($data['search'])) {
            $searchTerm = $data['search'];

            $query->where(function ($q) use ($searchTerm) {
                $q->where('code', 'like', "%{$searchTerm}%")
                    ->orWhere('name', 'like', "%{$searchTerm}%");
            });
        }

        $lines = $query
            ->orderBy('network', 'asc')
            ->orderByRaw('code REGEXP "^[0-9]" DESC')
            ->orderByRaw('LENGTH(code) ASC')
            ->orderBy('code', 'asc')
            ->get();

        return UnirBusLineResource::collection($lines);
    }

    /**
     * Get bus tops by code
     */
    public function stops($code)
    {
        $stops = DB::table('unir_bus_stops')
            ->join('unir_bus_lines', 'unir_bus_stops.unir_bus_line_id', '=', 'unir_bus_lines.id')
            ->where('unir_bus_lines.code', $code)
            ->select(
                'unir_bus_stops.id',
                'unir_bus_lines.id as bus_id',
                'unir_bus_lines.code as bus_code',
                'unir_bus_lines.name as bus_name',
                'unir_bus_lines.network as bus_network',
                'unir_bus_stops.directions_0',
                'unir_bus_stops.directions_1'
            )
            ->first();

        if (!$stops) {
            abort(404, 'Line not found');
        }

        return new UnirBusStopResource($stops);
    }

    /**
     * Get real-time arrivals for a specific Unir stop
     */
    public function realtime($stopCode)
    {
        try {
            $today = now()->format('Y-m-d');
            $url = config('app.unir_realtime_endpoint') . "?dia={$today}&id={$stopCode}";

            $response = Http::get($url);

            if (!$response->successful()) {
                return response()->json(['arrivals' => []]);
            }

            $body = $response->body();

            // Tentar descodificar diretamente primeiro
            $data = json_decode($body, true);

            // Se falhar (porque está como string escapada), descodificar a string resultante
            if (is_string($data)) {
                $data = json_decode($data, true);
            }

            if (!$data || !isset($data['horarios'])) {
                return response()->json(['arrivals' => []]);
            }

            $horarios = $data['horarios'];
            $arrivals = [];
            $now = now('Europe/Lisbon');

            foreach ($horarios as $item) {
                $line = $item['linha'] ?? '';
                $destination = $item['destino'] ?? '';
                $scheduledTime = $item['chegada'] ?? '';
                $tripId = $item['trip_id'] ?? null;

                if (empty($scheduledTime))
                    continue;

                $parts = explode(':', $scheduledTime);
                $hours = (int) $parts[0];
                $minutes = (int) $parts[1];

                $scheduled = now('Europe/Lisbon')->setHour($hours % 24)->setMinute($minutes)->setSecond(0);

                if ($hours >= 24) {
                    $scheduled->addDay();
                } else {
                    if ($scheduled->isPast() && $scheduled->diffInHours($now) > 12) {
                        $scheduled->addDay();
                    }
                }

                $arrivalMinutes = (int) $now->diffInMinutes($scheduled, false);

                // Mostrar apenas o que ainda não passou (margem de 2 min)
                // e limitar aos próximos 120 minutos (2 horas) para não encher a lista com o dia seguinte
                if ($arrivalMinutes < -2 || $arrivalMinutes > 120)
                    continue;

                $arrivals[] = [
                    'route_short_name' => $line,
                    'trip_headsign' => $destination,
                    'arrival_minutes' => max(0, $arrivalMinutes),
                    'estimated_arrival_time' => $scheduled->toIso8601String(),
                    'scheduled_arrival_time' => $scheduled->toIso8601String(),
                    'status' => 'ON_TIME',
                    'delay_minutes' => 0,
                    'trip_id' => $tripId,
                    'vehicle_id' => null,
                ];
            }

            usort($arrivals, function ($a, $b) {
                return $a['arrival_minutes'] <=> $b['arrival_minutes'];
            });

            return response()->json([
                'stop_id' => $stopCode,
                'stop_name' => $data['designa'] ?? 'Paragem Unir',
                'arrivals' => $arrivals,
                'last_updated' => now('Europe/Lisbon')->toIso8601String(),
                'data_source' => 'UNIR'
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching Unir realtime JSON for $stopCode: " . $e->getMessage());
            return response()->json(['arrivals' => [], 'error' => 'Failed to fetch realtime data'], 500);
        }
    }
}

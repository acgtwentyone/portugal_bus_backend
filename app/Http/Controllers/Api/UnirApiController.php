<?php

namespace App\Http\Controllers\Api;

use App\Models\UnirBusLine;
use Illuminate\Http\Request;
use App\Http\Resources\UnirBusLineResource;
use App\Http\Resources\UnirBusStopResource;
use App\Services\UnirService;
use App\CacheKeysEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
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
     * Pesquisa de paragens UNIR por nome ou código, no mesmo formato do /stops da STCP.
     */
    public function searchStops(Request $request)
    {
        $data = $request->validate([
            'search' => 'sometimes|nullable|string',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $term = Str::lower(trim($data['search'] ?? ''));
        $limit = $data['limit'] ?? 25;

        if ($term === '') {
            return response()->json(['data' => []]);
        }

        // Lista única de paragens (uma paragem serve várias linhas). Invalida no próximo sync.
        $stops = Cache::remember(CacheKeysEnum::UNIR_STOPS_ALL_FLAT, now()->addHours(24), function () {
            $byCode = [];

            foreach (DB::table('unir_bus_stops')->select('directions_0', 'directions_1')->get() as $row) {
                foreach ([$row->directions_0, $row->directions_1] as $json) {
                    foreach (json_decode($json ?? '[]', true) ?? [] as $stop) {
                        if (!empty($stop['stop_code'])) {
                            $byCode[$stop['stop_code']] = $stop;
                        }
                    }
                }
            }

            return array_values($byCode);
        });

        $results = collect($stops)
            ->filter(fn ($stop) => Str::contains(Str::lower($stop['stop_name'] ?? ''), $term)
                || Str::contains(Str::lower($stop['stop_code'] ?? ''), $term))
            ->values()
            ->take($limit);

        return response()->json(['data' => $results]);
    }

    /**
     * Próximas passagens numa paragem UNIR.
     *
     * A UNIR só publica horários programados (sem tempo real), por isso a resposta
     * leva is_realtime=false e cada chegada status=SCHEDULED. Se a fonte falhar,
     * devolve 502 em vez de uma lista vazia, para a app não confundir
     * "a fonte falhou" com "não há autocarros".
     */
    public function realtime(string $stopCode, UnirService $unir)
    {
        try {
            $now = now('Europe/Lisbon');
            $schedule = $unir->getDaySchedule($stopCode, $now->format('Y-m-d'));
        } catch (\Throwable $e) {
            Log::error("UNIR: falha ao obter horários da paragem {$stopCode}: " . $e->getMessage());

            return response()->json([
                'stop_id' => $stopCode,
                'arrivals' => [],
                'error' => 'Failed to fetch UNIR schedule',
            ], 502);
        }

        return response()->json([
            'stop_id' => $stopCode,
            'stop_name' => $schedule['stop_name'] ?? 'Paragem Unir',
            'arrivals' => $unir->buildArrivals($schedule, $now),
            'last_updated' => $now->toIso8601String(),
            'data_source' => 'UNIR',
            'is_realtime' => false,
        ]);
    }

    /**
     * Horário completo do dia por linha, no mesmo formato do schedule-cards da STCP.
     */
    public function scheduleCards(string $stopCode, UnirService $unir)
    {
        try {
            $schedule = $unir->getDaySchedule($stopCode, now('Europe/Lisbon')->format('Y-m-d'));
        } catch (\Throwable $e) {
            Log::error("UNIR: falha ao obter schedule-cards da paragem {$stopCode}: " . $e->getMessage());

            return response()->json([
                'stop_id' => $stopCode,
                'lines' => [],
                'partial' => true,
                'error' => 'Failed to fetch UNIR schedule',
            ], 502);
        }

        return response()->json($unir->buildScheduleCards($stopCode, $schedule));
    }
}

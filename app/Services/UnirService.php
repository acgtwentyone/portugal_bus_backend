<?php

namespace App\Services;

use App\CacheKeysEnum;
use App\Models\UnirBusLine;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Acesso aos horários da UNIR (paragens.amp.pt).
 *
 * A UNIR não disponibiliza tempo real: o endpoint devolve os horários
 * programados do dia para uma paragem. Por isso a resposta é a mesma ao longo
 * do dia e fica em cache até à meia-noite (Europe/Lisbon).
 */
class UnirService
{
    /** Janela mostrada no ecrã de chegadas, igual à da STCP. */
    public const ARRIVALS_WINDOW_MINUTES = 120;

    /**
     * Horários do dia para uma paragem.
     *
     * @return array{stop_name: ?string, entries: array<int, array{line: string, destination: string, time: string, trip_id: ?string, direction: int}>}
     *
     * @throws RuntimeException quando a fonte falha ou devolve algo que não se consegue ler
     */
    public function getDaySchedule(string $stopCode, string $date): array
    {
        $cacheKey = CacheKeysEnum::UNIR_DAY_SCHEDULE . ':' . $stopCode . ':' . $date;

        // Cache::remember só guarda quando não há exceção: uma falha da fonte não fica em cache.
        return Cache::remember($cacheKey, now('Europe/Lisbon')->endOfDay(), fn () => $this->fetchDaySchedule($stopCode, $date));
    }

    /**
     * Chegadas nos próximos ARRIVALS_WINDOW_MINUTES, no formato que a app já usa para a STCP.
     */
    public function buildArrivals(array $schedule, CarbonInterface $now): array
    {
        $arrivals = [];

        foreach ($schedule['entries'] as $entry) {
            $scheduled = $this->resolveTime($entry['time'], $now);
            if (!$scheduled) {
                continue;
            }

            $minutes = (int) $now->diffInMinutes($scheduled, false);

            // Margem de 2 min para o que acabou de passar, como antes.
            if ($minutes < -2 || $minutes > self::ARRIVALS_WINDOW_MINUTES) {
                continue;
            }

            $iso = $scheduled->toIso8601String();

            $arrivals[] = [
                'route_short_name' => $entry['line'],
                'trip_headsign' => $entry['destination'],
                'arrival_minutes' => max(0, $minutes),
                // Sem tempo real: a hora "estimada" é a programada. O status deixa isso explícito
                // em vez de afirmar ON_TIME/atraso 0, que a fonte não sabe.
                'estimated_arrival_time' => $iso,
                'scheduled_arrival_time' => $iso,
                'status' => 'SCHEDULED',
                'delay_minutes' => 0,
                'trip_id' => $entry['trip_id'],
                'vehicle_id' => null,
            ];
        }

        usort($arrivals, fn ($a, $b) => $a['arrival_minutes'] <=> $b['arrival_minutes']);

        return $arrivals;
    }

    /**
     * Cartões de horário por linha, no mesmo formato do /stops/{id}/schedule-cards da STCP.
     */
    public function buildScheduleCards(string $stopCode, array $schedule): array
    {
        $groups = [];

        foreach ($schedule['entries'] as $entry) {
            $direction = $entry['direction'] ?? 0;
            $key = $entry['line'] . '|' . $direction . '|' . $entry['destination'];
            $groups[$key] ??= [
                'line' => $entry['line'],
                'direction' => $direction,
                'destination' => $entry['destination'],
                'times' => [],
            ];
            $groups[$key]['times'][] = $entry['time'];
        }

        $lineNames = UnirBusLine::query()
            ->whereIn('code', array_unique(array_column($groups, 'line')))
            ->pluck('name', 'code');

        $lines = [];
        foreach ($groups as $group) {
            $times = array_values(array_unique($group['times']));
            // "HH:MM" com HH a poder passar de 24 (convenção dos operadores): ordenar numericamente.
            usort($times, fn ($a, $b) => $this->toMinutes($a) <=> $this->toMinutes($b));

            $lines[] = [
                'route_id' => $group['line'],
                'route_name' => $lineNames[$group['line']] ?? $group['destination'],
                // "sentido" da UNIR (0/1), equivalente ao direction_id da STCP.
                'direction_id' => $group['direction'],
                'headsign' => $group['destination'],
                'times' => $times,
            ];
        }

        usort($lines, fn ($a, $b) => [$a['route_id'], $a['direction_id'], $a['headsign']] <=> [$b['route_id'], $b['direction_id'], $b['headsign']]);

        return [
            'stop_id' => $stopCode,
            'lines' => $lines,
            'partial' => false,
        ];
    }

    private function fetchDaySchedule(string $stopCode, string $date): array
    {
        $response = Http::timeout(10)->get(config('app.unir_realtime_endpoint'), [
            'dia' => $date,
            'id' => $stopCode,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException("UNIR respondeu HTTP {$response->status()} para a paragem {$stopCode}");
        }

        $data = json_decode($response->body(), true);

        // A fonte às vezes devolve o JSON como string dentro de JSON.
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (!is_array($data) || !array_key_exists('horarios', $data)) {
            throw new RuntimeException("Resposta da UNIR ilegível para a paragem {$stopCode}");
        }

        $entries = [];
        foreach ((array) $data['horarios'] as $item) {
            $time = $item['chegada'] ?? '';
            if (!preg_match('/^\d{1,2}:\d{2}/', $time)) {
                continue;
            }

            $entries[] = [
                'line' => (string) ($item['linha'] ?? ''),
                'destination' => (string) ($item['destino'] ?? ''),
                'time' => substr($time, 0, strpos($time, ':') + 3),
                'trip_id' => isset($item['trip_id']) ? (string) $item['trip_id'] : null,
                'direction' => isset($item['sentido']) ? (int) $item['sentido'] : 0,
            ];
        }

        return [
            'stop_name' => $data['designa'] ?? null,
            'entries' => $entries,
        ];
    }

    /**
     * Converte "HH:MM" (HH pode ser >= 24) numa data a partir do dia de $now.
     */
    private function resolveTime(string $time, CarbonInterface $now): ?CarbonInterface
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            return null;
        }

        $hours = (int) $m[1];

        return $now->copy()
            ->startOfDay()
            ->addDays(intdiv($hours, 24))
            ->setTime($hours % 24, (int) $m[2]);
    }

    private function toMinutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time) + [0, 0]);

        return $h * 60 + $m;
    }
}

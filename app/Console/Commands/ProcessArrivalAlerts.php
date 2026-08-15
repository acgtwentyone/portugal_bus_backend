<?php

namespace App\Console\Commands;

use App\Models\ArrivalAlert;
use App\Services\FcmPushService;
use App\Services\StcpService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;

class ProcessArrivalAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-arrival-alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll STCP for active "bus is arriving" alerts and push via FCM when the bus is close, expiring stale alerts';

    private StcpService $stcpService;

    private FcmPushService $pushService;

    /**
     * Execute the console command.
     *
     * StcpService/FcmPushService are resolved here rather than via constructor injection:
     * Laravel's Schedule::command(ProcessArrivalAlerts::class) instantiates this class on
     * every artisan bootstrap just to read its signature, and constructor injection would
     * eagerly build the Firebase Messaging client (and fail) at that point.
     */
    public function handle(StcpService $stcpService, FcmPushService $pushService): void
    {
        $this->stcpService = $stcpService;
        $this->pushService = $pushService;

        $alerts = ArrivalAlert::all();

        if ($alerts->isEmpty()) {
            return;
        }

        $graceMinutes = (int) config('app.arrival_alert_expiry_grace_minutes');

        // stcp.pt's realtime feed is scoped to a stop and already returns every route
        // passing through it, so one fetch per stop_id covers every alert on that stop
        // regardless of how many different routes/directions they're watching.
        $byStop = $alerts->groupBy('stop_id');

        foreach ($byStop as $stopId => $stopAlerts) {
            $arrivals = $this->stcpService->getArrivals($stopId);

            // Group by the exact trip so alerts on the same specific bus (not just the
            // same route) are batched together.
            $byTrip = $stopAlerts->groupBy('trip_id');

            foreach ($byTrip as $groupAlerts) {
                $this->processGroup($groupAlerts, $arrivals, $graceMinutes);
            }
        }
    }

    /**
     * @param Collection<int, ArrivalAlert> $groupAlerts
     * @param array<int, array<string, mixed>> $arrivals
     */
    private function processGroup(Collection $groupAlerts, array $arrivals, int $graceMinutes): void
    {
        /** @var ArrivalAlert $first */
        $first = $groupAlerts->first();

        // Match the exact trip the alert was created for, not just "next of this route",
        // so we track the specific bus the user picked.
        $match = collect($arrivals)->firstWhere('trip_id', $first->trip_id);

        if (!$match) {
            $this->expireStaleAlerts($groupAlerts, $graceMinutes);

            return;
        }

        $minutesAway = (int) ($match['arrival_minutes'] ?? PHP_INT_MAX);

        // Every alert in the group is watching the same trip but can have its own
        // "notify me X minutes before" threshold, so the single fetched arrival can
        // cross some alerts' thresholds while others are still waiting.
        [$toFire, $notYet] = $groupAlerts->partition(fn (ArrivalAlert $alert) => $minutesAway <= $alert->threshold_minutes);

        if ($toFire->isNotEmpty()) {
            $this->fireGroup($toFire, $match, $minutesAway);
        }

        if ($notYet->isNotEmpty()) {
            $this->expireStaleAlerts($notYet, $graceMinutes);
        }
    }

    /**
     * @param Collection<int, ArrivalAlert> $groupAlerts
     * @param array<string, mixed> $match
     */
    private function fireGroup(Collection $groupAlerts, array $match, int $minutesAway): void
    {
        $minutesAway = max(0, $minutesAway);
        $headsign = $match['trip_headsign'] ?? null;

        foreach ($groupAlerts as $alert) {
            $locale = $alert->locale ?: config('app.locale');

            $title = Lang::get('push.arrival_alert_title', [], $locale);
            $body = $headsign
                ? Lang::get('push.arrival_alert_body', ['route' => $alert->route_id, 'headsign' => $headsign, 'minutes' => $minutesAway], $locale)
                : Lang::get('push.arrival_alert_body_no_headsign', ['route' => $alert->route_id, 'minutes' => $minutesAway], $locale);

            // Matches the value the app already has cached in MMKV for this alert (the ETA
            // it sent when activating it), not the live one, so the app can find/clear it.
            $data = [
                'alert_id' => $alert->id,
                'stop_id' => $alert->stop_id,
                'route_id' => $alert->route_id,
                'direction_id' => (string) $alert->direction_id,
                'trip_id' => $alert->trip_id,
                'estimated_arrival_time' => $alert->estimated_arrival_time->toIso8601String(),
            ];

            $this->pushService->send($alert->device_token, $title, $body, $data);

            $alert->delete();
        }
    }

    /**
     * @param Collection<int, ArrivalAlert> $groupAlerts
     */
    private function expireStaleAlerts(Collection $groupAlerts, int $graceMinutes): void
    {
        $expired = $groupAlerts
            ->filter(fn (ArrivalAlert $alert) => now('Europe/Lisbon')->isAfter($alert->estimated_arrival_time->clone()->addMinutes($graceMinutes)));

        foreach ($expired as $alert) {
            Log::info("Expiring stale arrival alert without firing: {$alert->id}");

            $alert->delete();
        }
    }
}

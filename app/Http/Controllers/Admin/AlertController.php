<?php

namespace App\Http\Controllers\Admin;

use App\AdminActionEnum;
use App\Http\Controllers\Controller;
use App\Models\ArrivalAlert;
use App\Services\AdminAuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(): View
    {
        return view('admin.alerts.index', [
            'alerts' => ArrivalAlert::orderBy('estimated_arrival_time')->paginate(50),
            'expiredCount' => ArrivalAlert::expired()->count(),
        ]);
    }

    /**
     * Cancel a single alert. The device gets no push, same as when the scheduler expires one.
     */
    public function destroy(ArrivalAlert $alert, AdminAuditLog $log): RedirectResponse
    {
        DB::transaction(function () use ($alert, $log) {
            $alert->delete();

            $log->record(AdminActionEnum::AlertCancelled, $alert->id, $alert->only([
                'stop_id', 'route_id', 'direction_id', 'trip_id', 'estimated_arrival_time',
            ]));
        });

        return redirect()->route('admin.alerts.index')->with('success', "Alerta #{$alert->id} cancelado.");
    }

    /**
     * Delete every expired alert. Alerts are deleted one by one so the log only lists the ones
     * this request actually removed, even if ProcessArrivalAlerts expires some concurrently.
     */
    public function purgeExpired(AdminAuditLog $log): RedirectResponse
    {
        $deletedIds = DB::transaction(function () use ($log) {
            $deletedIds = ArrivalAlert::expired()->pluck('id')
                ->filter(fn (int $id) => ArrivalAlert::whereKey($id)->delete() > 0)
                ->values()
                ->all();

            if ($deletedIds) {
                $log->record(AdminActionEnum::AlertsPurged, null, ['count' => count($deletedIds), 'ids' => $deletedIds]);
            }

            return $deletedIds;
        });

        $message = $deletedIds ? count($deletedIds).' alertas expirados removidos.' : 'Não havia alertas expirados.';

        return redirect()->route('admin.alerts.index')->with('success', $message);
    }
}

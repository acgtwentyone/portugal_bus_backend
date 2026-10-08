<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArrivalAlert;
use App\Models\DeviceToken;
use App\Services\ScheduledTaskStatus;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(ScheduledTaskStatus $status): View
    {
        return view('admin.dashboard', [
            'tasks' => collect($status->events())->map(fn ($event) => $status->forEvent($event)),
            'activeAlerts' => ArrivalAlert::count(),
            'expiredAlerts' => ArrivalAlert::expired()->count(),
            'devices' => DeviceToken::count(),
        ]);
    }
}

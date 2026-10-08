<?php

namespace Tests\Feature\Admin;

use App\Models\ArrivalAlert;
use App\Models\DeviceToken;
use App\Models\ScheduledTaskRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCloudflareAccess;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use InteractsWithCloudflareAccess;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCloudflareAccess();
    }

    public function test_dashboard_shows_a_card_per_scheduled_task(): void
    {
        $this->asAdmin()->get(self::ADMIN_HOST.'/')
            ->assertOk()
            ->assertSee('app:sync-stcp-lines')
            ->assertSee('app:process-arrival-alerts')
            ->assertSee('Sem execuções registadas');
    }

    public function test_dashboard_shows_counters(): void
    {
        config(['app.arrival_alert_expiry_grace_minutes' => 15]);
        ArrivalAlert::factory()->create();
        ArrivalAlert::factory()->create(['estimated_arrival_time' => now()->subHour()]);
        foreach (['t1', 't2', 't3'] as $token) {
            DeviceToken::create(['token' => $token]);
        }

        $this->asAdmin()->get(self::ADMIN_HOST.'/')
            ->assertSeeInOrder(['Alertas ativos', '2', 'Alertas expirados', '1', 'Dispositivos registados', '3'])
            ->assertSee(route('admin.alerts.index'))
            ->assertSee(route('admin.push.create'));
    }

    public function test_dashboard_shows_late_warning_and_last_error(): void
    {
        ScheduledTaskRun::create([
            'command' => 'app:process-arrival-alerts',
            'status' => ScheduledTaskRun::STATUS_FAILED,
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(10),
            'error' => 'boom',
        ]);

        $this->asAdmin()->get(self::ADMIN_HOST.'/')
            ->assertSee('Atrasado')
            ->assertSee('boom');
    }
}

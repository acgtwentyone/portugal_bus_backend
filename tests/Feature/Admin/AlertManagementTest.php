<?php

namespace Tests\Feature\Admin;

use App\Models\AdminAction;
use App\Models\ArrivalAlert;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithCloudflareAccess;
use Tests\TestCase;

class AlertManagementTest extends TestCase
{
    use InteractsWithCloudflareAccess;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCloudflareAccess();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        config(['app.arrival_alert_expiry_grace_minutes' => 15]);
        $this->travelTo('2026-10-08 10:00:00');
    }

    public function test_index_lists_alerts_ordered_by_eta_with_expired_badge(): void
    {
        ArrivalAlert::factory()->create(['trip_id' => 'TRIP-LATER', 'estimated_arrival_time' => now()->addMinutes(20)]);
        ArrivalAlert::factory()->create(['trip_id' => 'TRIP-OLD', 'estimated_arrival_time' => now()->subMinutes(30)]);
        ArrivalAlert::factory()->create(['trip_id' => 'TRIP-SOON', 'estimated_arrival_time' => now()->addMinutes(5)]);

        $this->asAdmin()->get(self::ADMIN_HOST.'/alerts')
            ->assertOk()
            ->assertSeeInOrder(['TRIP-OLD', 'Expirado', 'TRIP-SOON', 'TRIP-LATER'])
            ->assertSee('Limpar 1 expirados');
    }

    public function test_index_truncates_device_token(): void
    {
        ArrivalAlert::factory()->create(['device_token' => 'abcdefgh12345']);

        $this->asAdmin()->get(self::ADMIN_HOST.'/alerts')
            ->assertSee('abcdefgh…')
            ->assertDontSee('abcdefgh12345');
    }

    public function test_cancel_deletes_alert_and_logs(): void
    {
        $alert = ArrivalAlert::factory()->create(['trip_id' => 'T9']);

        $this->asAdmin()->delete(self::ADMIN_HOST."/alerts/{$alert->id}")
            ->assertRedirect(route('admin.alerts.index'))
            ->assertSessionHas('success');

        $this->assertModelMissing($alert);
        $action = AdminAction::sole();
        $this->assertSame('alert.cancelled', $action->action->value);
        $this->assertSame($alert->id, $action->subject_id);
        $this->assertSame('T9', $action->payload['trip_id']);
    }

    public function test_cancel_missing_alert_shows_message(): void
    {
        $this->asAdmin()->delete(self::ADMIN_HOST.'/alerts/999')
            ->assertRedirect(route('admin.alerts.index'))
            ->assertSessionHas('error', 'Alerta já não existe');

        $this->assertSame(0, AdminAction::count());
    }

    public function test_purge_deletes_only_expired_and_logs_ids(): void
    {
        $expired = ArrivalAlert::factory()->count(2)->create(['estimated_arrival_time' => now()->subMinutes(30)]);
        $active = ArrivalAlert::factory()->create();

        $this->asAdmin()->post(self::ADMIN_HOST.'/alerts/purge')
            ->assertRedirect(route('admin.alerts.index'));

        $this->assertSame([$active->id], ArrivalAlert::pluck('id')->all());
        $action = AdminAction::sole();
        $this->assertSame('alerts.purged', $action->action->value);
        $this->assertSame(2, $action->payload['count']);
        $this->assertEqualsCanonicalizing($expired->pluck('id')->all(), $action->payload['ids']);
    }

    public function test_purge_with_nothing_expired_logs_nothing(): void
    {
        ArrivalAlert::factory()->create();

        $this->asAdmin()->post(self::ADMIN_HOST.'/alerts/purge')
            ->assertRedirect(route('admin.alerts.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, ArrivalAlert::count());
        $this->assertSame(0, AdminAction::count());
    }

    public function test_purge_logs_only_ids_actually_deleted(): void
    {
        [$first, $second] = ArrivalAlert::factory()->count(2)->create(['estimated_arrival_time' => now()->subMinutes(30)]);

        // Simulate ProcessArrivalAlerts expiring $first right after the panel read the expired ids.
        $raced = false;
        DB::listen(function (QueryExecuted $query) use (&$raced, $first) {
            if (! $raced && str_starts_with($query->sql, 'select') && str_contains($query->sql, 'arrival_alerts')) {
                $raced = true;
                DB::table('arrival_alerts')->where('id', $first->id)->delete();
            }
        });

        $this->asAdmin()->post(self::ADMIN_HOST.'/alerts/purge');

        $action = AdminAction::sole();
        $this->assertSame(1, $action->payload['count']);
        $this->assertSame([$second->id], $action->payload['ids']);
    }
}

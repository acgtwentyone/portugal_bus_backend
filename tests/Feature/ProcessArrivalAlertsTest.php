<?php

namespace Tests\Feature;

use App\Models\ArrivalAlert;
use App\Services\FcmPushService;
use App\Services\StcpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessArrivalAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.arrival_alert_expiry_grace_minutes' => 15]);
        $this->travelTo('2026-10-08 10:00:00');
    }

    public function test_alert_within_grace_is_kept_when_trip_missing(): void
    {
        $alert = ArrivalAlert::factory()->create(['estimated_arrival_time' => now()->subMinutes(14)]);

        $this->mock(StcpService::class)->shouldReceive('getArrivals')->andReturn([]);
        $this->mock(FcmPushService::class)->shouldNotReceive('send');

        $this->artisan('app:process-arrival-alerts')->assertSuccessful();

        $this->assertModelExists($alert);
    }

    public function test_alert_past_grace_is_deleted_without_push(): void
    {
        $alert = ArrivalAlert::factory()->create(['estimated_arrival_time' => now()->subMinutes(16)]);

        $this->mock(StcpService::class)->shouldReceive('getArrivals')->andReturn([]);
        $this->mock(FcmPushService::class)->shouldNotReceive('send');

        $this->artisan('app:process-arrival-alerts')->assertSuccessful();

        $this->assertModelMissing($alert);
    }

    public function test_alert_fires_and_is_deleted_when_threshold_reached(): void
    {
        $alert = ArrivalAlert::factory()->create(['trip_id' => 'T1', 'threshold_minutes' => 5]);

        $this->mock(StcpService::class)->shouldReceive('getArrivals')->with('BLM1')->andReturn([
            ['trip_id' => 'T1', 'arrival_minutes' => 3, 'trip_headsign' => 'Bolhão'],
        ]);
        $this->mock(FcmPushService::class)->shouldReceive('send')->once()->andReturnTrue();

        $this->artisan('app:process-arrival-alerts')->assertSuccessful();

        $this->assertModelMissing($alert);
    }
}

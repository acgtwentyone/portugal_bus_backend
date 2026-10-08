<?php

namespace Tests\Unit;

use App\Models\ArrivalAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArrivalAlertExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.arrival_alert_expiry_grace_minutes' => 15]);
        $this->travelTo('2026-10-08 10:00:00');
    }

    public function test_is_not_expired_exactly_at_grace_boundary(): void
    {
        $alert = ArrivalAlert::factory()->make(['estimated_arrival_time' => '2026-10-08 09:45:00']);

        $this->assertFalse($alert->isExpired());
    }

    public function test_is_expired_one_second_after_grace(): void
    {
        $alert = ArrivalAlert::factory()->make(['estimated_arrival_time' => '2026-10-08 09:44:59']);

        $this->assertTrue($alert->isExpired());
    }

    public function test_expired_scope_matches_is_expired(): void
    {
        $expired = ArrivalAlert::factory()->create(['estimated_arrival_time' => '2026-10-08 09:30:00']);
        ArrivalAlert::factory()->create(['estimated_arrival_time' => '2026-10-08 09:45:00']);
        ArrivalAlert::factory()->create(['estimated_arrival_time' => '2026-10-08 10:10:00']);

        $this->assertSame([$expired->id], ArrivalAlert::expired()->pluck('id')->all());
    }
}

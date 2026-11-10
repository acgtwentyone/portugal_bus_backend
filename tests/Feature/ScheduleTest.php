<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_unir_sync_runs_weekly_on_sunday_at_4am_lisbon(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'app:sync-unir-lines'));

        $this->assertNotNull($event, 'app:sync-unir-lines não está agendado');
        $this->assertSame('0 4 * * 0', $event->expression);
        $this->assertSame('Europe/Lisbon', $event->timezone);
    }
}

<?php

namespace Tests\Unit;

use App\Models\ScheduledTaskRun;
use App\Services\ScheduledTaskStatus;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledTaskStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_minute_task_is_late_after_three_minutes(): void
    {
        $this->travelTo('2026-10-08 10:05:30');
        $this->recordRun('app:process-arrival-alerts', '2026-10-08 10:02:00');

        $this->assertTrue($this->statusFor($this->everyMinute())['late']);
    }

    public function test_every_minute_task_is_not_late_with_recent_run(): void
    {
        $this->travelTo('2026-10-08 10:05:30');
        $this->recordRun('app:process-arrival-alerts', '2026-10-08 10:04:00');

        $this->assertFalse($this->statusFor($this->everyMinute())['late']);
    }

    public function test_task_without_runs_is_not_late(): void
    {
        $this->travelTo('2026-10-08 10:05:30');

        $status = $this->statusFor($this->everyMinute());

        $this->assertFalse($status['late']);
        $this->assertNull($status['last_run']);
    }

    public function test_weekend_task_is_late_when_saturday_run_missing(): void
    {
        $this->travelTo('2026-10-11 11:00:00'); // Sunday 12:00 Lisbon (UTC+1)
        $this->recordRun('app:sync-stcp-lines', '2026-10-09 02:00:00');

        $this->assertTrue($this->statusFor($this->weekends())['late']);
    }

    public function test_weekend_task_is_not_late_after_saturday_run(): void
    {
        $this->travelTo('2026-10-11 11:00:00'); // Sunday 12:00 Lisbon (UTC+1)
        $this->recordRun('app:sync-stcp-lines', '2026-10-11 02:00:05'); // Sunday 03:00:05 Lisbon

        $this->assertFalse($this->statusFor($this->weekends())['late']);
    }

    public function test_frequency_texts(): void
    {
        $this->assertSame('todos os minutos', $this->statusFor($this->everyMinute())['frequency']);
        $this->assertSame('sáb e dom às 03:00 (Europe/Lisbon)', $this->statusFor($this->weekends())['frequency']);
        $this->assertSame('todos os dias às 00:00', $this->statusFor(app(Schedule::class)->command('model:prune')->daily())['frequency']);
        $this->assertSame('*/5 * * * *', $this->statusFor(app(Schedule::class)->command('x')->everyFiveMinutes())['frequency']);
    }

    public function test_recent_lists_last_ten_runs_newest_first(): void
    {
        foreach (range(1, 12) as $i) {
            $this->recordRun('app:process-arrival-alerts', now()->subMinutes($i));
        }

        $recent = $this->statusFor($this->everyMinute())['recent'];

        $this->assertCount(10, $recent);
        $this->assertTrue($recent->first()->started_at->gt($recent->last()->started_at));
    }

    private function everyMinute()
    {
        return app(Schedule::class)->command('app:process-arrival-alerts')->everyMinute();
    }

    private function weekends()
    {
        return app(Schedule::class)->command('app:sync-stcp-lines')->weekends()->at('03:00')->timezone('Europe/Lisbon');
    }

    private function statusFor($event): array
    {
        return app(ScheduledTaskStatus::class)->forEvent($event);
    }

    private function recordRun(string $command, $startedAt): void
    {
        ScheduledTaskRun::create(['command' => $command, 'status' => 'success', 'started_at' => $startedAt]);
    }
}

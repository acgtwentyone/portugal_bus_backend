<?php

namespace Tests\Feature;

use App\Models\ScheduledTaskRun;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordScheduledTaskRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_then_finished_with_zero_exit_records_success(): void
    {
        $task = $this->task();

        event(new ScheduledTaskStarting($task));
        $this->assertSame(ScheduledTaskRun::STATUS_RUNNING, ScheduledTaskRun::sole()->status);

        $task->exitCode = 0;
        event(new ScheduledTaskFinished($task, 1.25));

        $run = ScheduledTaskRun::sole();
        $this->assertSame(ScheduledTaskRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(1250, $run->duration_ms);
        $this->assertSame(0, $run->exit_code);
        $this->assertNotNull($run->finished_at);
    }

    public function test_finished_with_nonzero_exit_records_failed(): void
    {
        $task = $this->task();

        event(new ScheduledTaskStarting($task));
        $task->exitCode = 1;
        event(new ScheduledTaskFinished($task, 0.5));

        $run = ScheduledTaskRun::sole();
        $this->assertSame(ScheduledTaskRun::STATUS_FAILED, $run->status);
        $this->assertSame(1, $run->exit_code);
    }

    public function test_finished_with_null_exit_code_records_skipped(): void
    {
        // withoutOverlapping() bails out of Event::run() before setting exitCode and the
        // scheduler still dispatches ScheduledTaskFinished, not ScheduledTaskSkipped.
        $task = $this->task();

        event(new ScheduledTaskStarting($task));
        event(new ScheduledTaskFinished($task, 0.0));

        $this->assertSame(ScheduledTaskRun::STATUS_SKIPPED, ScheduledTaskRun::sole()->status);
    }

    public function test_failed_event_records_error_message(): void
    {
        $task = $this->task();

        event(new ScheduledTaskStarting($task));
        event(new ScheduledTaskFailed($task, new \RuntimeException('boom')));

        $run = ScheduledTaskRun::sole();
        $this->assertSame(ScheduledTaskRun::STATUS_FAILED, $run->status);
        $this->assertSame('boom', $run->error);
        $this->assertNotNull($run->finished_at);
    }

    public function test_skipped_event_creates_skipped_row(): void
    {
        event(new ScheduledTaskSkipped($this->task()));

        $run = ScheduledTaskRun::sole();
        $this->assertSame(ScheduledTaskRun::STATUS_SKIPPED, $run->status);
        $this->assertSame('app:process-arrival-alerts', $run->command);
    }

    public function test_command_name_is_extracted(): void
    {
        $this->assertSame('app:process-arrival-alerts', ScheduledTaskRun::commandName($this->task()));
    }

    public function test_prune_removes_runs_older_than_7_days(): void
    {
        $old = ScheduledTaskRun::create(['command' => 'x', 'status' => 'success', 'started_at' => now()->subDays(8)]);
        $recent = ScheduledTaskRun::create(['command' => 'x', 'status' => 'success', 'started_at' => now()->subDays(6)]);

        $this->artisan('model:prune', ['--model' => ScheduledTaskRun::class])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }

    private function task(): Event
    {
        return app(Schedule::class)->command('app:process-arrival-alerts');
    }
}

<?php

namespace App\Listeners;

use App\Models\ScheduledTaskRun;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;

/**
 * Records every scheduled task execution so the admin dashboard can show last runs and
 * spot a stalled cron. Registered as a singleton: schedule:run runs tasks in-process, so the
 * Starting and Finished/Failed events for one run share the same Event object.
 */
class RecordScheduledTaskRun
{
    /** @var array<int, int> spl_object_id(Event) => scheduled_task_runs.id */
    private array $runIds = [];

    public function handleStarting(ScheduledTaskStarting $event): void
    {
        $run = ScheduledTaskRun::create([
            'command' => ScheduledTaskRun::commandName($event->task),
            'status' => ScheduledTaskRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $this->runIds[spl_object_id($event->task)] = $run->id;
    }

    public function handleFinished(ScheduledTaskFinished $event): void
    {
        // withoutOverlapping() returns from Event::run() without setting an exit code, and the
        // scheduler still fires Finished, so a null exit code means the run was skipped.
        $status = match (true) {
            $event->task->exitCode === null => ScheduledTaskRun::STATUS_SKIPPED,
            $event->task->exitCode === 0 => ScheduledTaskRun::STATUS_SUCCESS,
            default => ScheduledTaskRun::STATUS_FAILED,
        };

        $this->finish($event->task, [
            'status' => $status,
            'duration_ms' => (int) round($event->runtime * 1000),
            'exit_code' => $event->task->exitCode,
        ]);
    }

    public function handleFailed(ScheduledTaskFailed $event): void
    {
        $this->finish($event->task, [
            'status' => ScheduledTaskRun::STATUS_FAILED,
            'error' => $event->exception->getMessage(),
        ]);
    }

    public function handleSkipped(ScheduledTaskSkipped $event): void
    {
        ScheduledTaskRun::create([
            'command' => ScheduledTaskRun::commandName($event->task),
            'status' => ScheduledTaskRun::STATUS_SKIPPED,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    private function finish(object $task, array $attributes): void
    {
        $runId = $this->runIds[spl_object_id($task)] ?? null;

        if ($runId === null) {
            return;
        }

        unset($this->runIds[spl_object_id($task)]);

        ScheduledTaskRun::whereKey($runId)->update([...$attributes, 'finished_at' => now()]);
    }
}

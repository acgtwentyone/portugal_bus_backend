<?php

namespace App\Services;

use App\Models\ScheduledTaskRun;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;

class ScheduledTaskStatus
{
    /** Slack before a missing run counts as late, so a run that's just starting isn't flagged. */
    private const LATE_TOLERANCE_MINUTES = 2;

    private const WEEKDAYS = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

    /**
     * Every task defined in routes/console.php. Under the HTTP kernel that file is never
     * loaded, so the console kernel is bootstrapped first or the schedule would be empty.
     *
     * @return list<Event>
     */
    public function events(): array
    {
        app(ConsoleKernel::class)->bootstrap();

        return app(Schedule::class)->events();
    }

    /**
     * @return array{command: string, frequency: string, next_run: \Illuminate\Support\Carbon, last_run: ?ScheduledTaskRun, recent: \Illuminate\Support\Collection<int, ScheduledTaskRun>, late: bool}
     */
    public function forEvent(Event $event): array
    {
        $command = ScheduledTaskRun::commandName($event);

        $recent = ScheduledTaskRun::where('command', $command)->latest('started_at')->limit(10)->get();
        $lastRun = $recent->first();

        return [
            'command' => $command,
            'frequency' => $this->describe($event),
            'next_run' => $event->nextRunDate(),
            'last_run' => $lastRun,
            'recent' => $recent,
            'late' => $lastRun !== null && $lastRun->started_at->lt($this->lastDueDate($event)),
        ];
    }

    /**
     * The most recent time the task should have started, ignoring the last few minutes.
     * With no recorded runs at all the task is never "late" (e.g. right after a deploy).
     */
    private function lastDueDate(Event $event): \DateTimeInterface
    {
        $timezone = $event->timezone ?? config('app.timezone');

        return (new CronExpression($event->expression))
            ->getPreviousRunDate(now($timezone)->subMinutes(self::LATE_TOLERANCE_MINUTES), 0, true);
    }

    private function describe(Event $event): string
    {
        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = explode(' ', $event->expression);

        if ($event->expression === '* * * * *') {
            return 'todos os minutos';
        }

        if (! ctype_digit($minute) || ! ctype_digit($hour) || $dayOfMonth !== '*' || $month !== '*') {
            return $event->expression;
        }

        $time = sprintf('às %02d:%02d', $hour, $minute);

        if ($dayOfWeek === '*') {
            $text = "todos os dias {$time}";
        } elseif (preg_match('/^[0-6](,[0-6])*$/', $dayOfWeek)) {
            $text = collect(explode(',', $dayOfWeek))
                ->sortBy(fn (string $day) => ((int) $day + 6) % 7) // week starts on Monday
                ->map(fn (string $day) => self::WEEKDAYS[(int) $day])
                ->join(', ', ' e ')." {$time}";
        } else {
            return $event->expression;
        }

        $timezone = $event->timezone ?? config('app.timezone');

        return $timezone === 'UTC' ? $text : "{$text} ({$timezone})";
    }
}

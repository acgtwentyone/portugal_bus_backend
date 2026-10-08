<?php

namespace App\Models;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Str;

class ScheduledTaskRun extends Model
{
    use Prunable;

    const STATUS_RUNNING = 'running';

    const STATUS_SUCCESS = 'success';

    const STATUS_FAILED = 'failed';

    const STATUS_SKIPPED = 'skipped';

    public $timestamps = false;

    protected $fillable = [
        'command',
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
        'exit_code',
        'error',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_ms' => 'integer',
        'exit_code' => 'integer',
    ];

    /**
     * The artisan command name (e.g. "app:process-arrival-alerts") for a scheduled event,
     * or the scheduler's display summary for non-artisan tasks.
     */
    public static function commandName(Event $event): string
    {
        if ($event->command && str_contains($event->command, "'artisan' ")) {
            return Str::after($event->command, "'artisan' ");
        }

        return $event->getSummaryForDisplay();
    }

    public function prunable(): Builder
    {
        return static::where('started_at', '<', now()->subDays(7));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArrivalAlert extends Model
{
    /** @use HasFactory<\Database\Factories\ArrivalAlertFactory> */
    use HasFactory;

    protected $fillable = [
        'stop_id',
        'route_id',
        'direction_id',
        'trip_id',
        'estimated_arrival_time',
        'threshold_minutes',
        'device_token',
        'locale',
    ];

    protected $casts = [
        'direction_id' => 'integer',
        'estimated_arrival_time' => 'datetime',
        'threshold_minutes' => 'integer',
    ];

    /**
     * An alert is expired once its ETA plus the grace window has passed without it firing.
     */
    public function isExpired(): bool
    {
        return now()->isAfter($this->estimated_arrival_time->clone()->addMinutes(self::expiryGraceMinutes()));
    }

    /**
     * Same rule as isExpired(), in SQL. now() is in the app timezone, which is how the
     * datetime cast reads the stored values back.
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where('estimated_arrival_time', '<', now()->subMinutes(self::expiryGraceMinutes()));
    }

    private static function expiryGraceMinutes(): int
    {
        return (int) config('app.arrival_alert_expiry_grace_minutes');
    }
}

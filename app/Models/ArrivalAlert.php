<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArrivalAlert extends Model
{
    protected $fillable = [
        'stop_id',
        'route_id',
        'direction_id',
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
}

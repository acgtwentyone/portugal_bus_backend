<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppEvent extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'event_type',
        'device_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}

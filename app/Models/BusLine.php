<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusLine extends Model
{
    /** @use HasFactory<\Database\Factories\BusLineFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'network',
        'slug',
        'last_sync',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusStop extends Model
{
    protected $fillable = [
        'bus_line_id', 
        'directions_0', 
        'directions_1',
        'created-at',
        'updated_at',
    ];

    protected $casts = [
        'directions_0' => 'array',
        'directions_1' => 'array',
    ];

    public function busLine()
    {
        return $this->belongsTo(BusLine::class);
    }
}

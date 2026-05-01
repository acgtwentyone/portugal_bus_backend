<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnirBusStop extends Model
{
    use HasFactory;

    protected $fillable = [
        'unir_bus_line_id', 
        'directions_0', 
        'directions_1',
    ];

    protected $casts = [
        'directions_0' => 'array',
        'directions_1' => 'array',
    ];

    public function busLine()
    {
        return $this->belongsTo(UnirBusLine::class, 'unir_bus_line_id');
    }
}

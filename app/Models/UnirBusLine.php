<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnirBusLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'network',
        'slug',
        'last_sync',
    ];

    public function busStop()
    {
        return $this->hasOne(UnirBusStop::class, 'unir_bus_line_id');
    }
}

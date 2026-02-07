<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreBusLineRequest;
use App\Http\Requests\UpdateBusLineRequest;
use App\Models\BusLine;
use Illuminate\Support\Facades\Artisan;

class BusLineAPIController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $busLines = BusLine::orderBy('code')->get();

        return BusLineResource::collection($busLines);
    }
}

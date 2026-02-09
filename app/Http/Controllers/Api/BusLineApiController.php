<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreBusLineRequest;
use App\Http\Requests\UpdateBusLineRequest;
use App\Models\BusLine;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use App\Http\Resources\BusLineResource;

class BusLineApiController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = BusLine::query();

        if ($request->filled('search')) {
            $termo = $request->input('search');

            $query->where(function($q) use ($termo) {
                $q->where('code', 'like', "%{$termo}%")
                  ->orWhere('name', 'like', "%{$termo}%");
            });
        }

        $lines = $query->orderBy('code', 'asc')->get();

        return BusLineResource::collection($lines);
    }
}

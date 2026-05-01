<?php

namespace App\Http\Controllers\Api;

use App\Models\BusLine;
use Illuminate\Http\Request;
use App\Http\Resources\BusLineResource;
use App\Http\Resources\BusStopResource;
use Illuminate\Support\Facades\DB;

class StcpApiController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function lines(Request $request)
    {
        $data = $request->validate([
            'search' => 'sometimes|nullable|string',
        ]);

        $query = BusLine::query();

        if (!empty($data['search'])) {
            $searchTerm = $data['search'];

            $query->where(function ($q) use ($searchTerm) {
                $q->where('code', 'like', "%{$searchTerm}%")
                    ->orWhere('name', 'like', "%{$searchTerm}%");
            });
        }

        $lines = $query
            ->orderBy('network', 'asc')
            ->orderByRaw('code REGEXP "^[0-9]" DESC')
            ->orderByRaw('LENGTH(code) ASC')
            ->orderBy('code', 'asc')
            ->get();

        return BusLineResource::collection($lines);
    }

    /**
     * Get bus tops by code
     */
    public function stops($code)
    {
        $stops = DB::table('bus_stops')
            ->join('bus_lines', 'bus_stops.bus_line_id', '=', 'bus_lines.id')
            ->where('bus_lines.code', $code)
            ->select(
                'bus_stops.id',
                'bus_lines.id as bus_id',
                'bus_lines.code as bus_code',
                'bus_lines.name as bus_name',
                'bus_lines.network as bus_network',
                'bus_stops.directions_0',
                'bus_stops.directions_1'
            )
            ->first();

        return new BusStopResource($stops);
    }
}

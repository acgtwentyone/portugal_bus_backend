<?php

namespace App\Http\Controllers\Api;

use App\Models\UnirBusLine;
use Illuminate\Http\Request;
use App\Http\Resources\UnirBusLineResource;
use App\Http\Resources\UnirBusStopResource;
use Illuminate\Support\Facades\DB;

class UnirApiController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function lines(Request $request)
    {
        $data = $request->validate([
            'search' => 'sometimes|nullable|string',
        ]);

        $query = UnirBusLine::query();

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

        return UnirBusLineResource::collection($lines);
    }

    /**
     * Get bus tops by code
     */
    public function stops($code)
    {
        $stops = DB::table('unir_bus_stops')
            ->join('unir_bus_lines', 'unir_bus_stops.unir_bus_line_id', '=', 'unir_bus_lines.id')
            ->where('unir_bus_lines.code', $code)
            ->select(
                'unir_bus_stops.id',
                'unir_bus_lines.id as bus_id',
                'unir_bus_lines.code as bus_code',
                'unir_bus_lines.name as bus_name',
                'unir_bus_lines.network as bus_network',
                'unir_bus_stops.directions_0',
                'unir_bus_stops.directions_1'
            )
            ->first();

        if (!$stops) {
            abort(404, 'Line not found');
        }

        return new UnirBusStopResource($stops);
    }
}

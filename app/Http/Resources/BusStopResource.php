<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusStopResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'line' => new BusLineResource((object)[
                'id' => $this->bus_id,
                'code' => $this->bus_code,
                'name' => $this->bus_name,
                'network' => $this->bus_network,
            ]),
            'directions' => [
                'dir_0' => $this->parseJson($this->directions_0),
                'dir_1' => $this->parseJson($this->directions_1),
            ]
        ];
    }

    /**
     * Helper para garantir que o JSON é sempre uma array
     */
    private function parseJson($data): array
    {
        if (is_array($data)) return $data;
        
        $decoded = json_decode($data, true);
        return is_array($decoded) ? $decoded : [];
    }
}

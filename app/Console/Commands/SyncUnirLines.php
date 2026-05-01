<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UnirBusLine;
use App\Models\UnirBusStop;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Cookie\CookieJar;

class SyncUnirLines extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-unir-lines';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync bus lines from UNIR to the database';

    /**
     * Municípios listados no site da UNIR
     */
    protected $municipalities = [
        'aro', 'esp', 'gon', 'mai', 'mat', 'oaz', 'prd', 'prt', 
        'pov', 'smf', 'str', 'sjm', 'trf', 'vcm', 'vlg', 'vcd', 'vng'
    ];

    /**
     * Cookies da sessão
     */
    protected $cookieJar;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Initiating synchronization of UNIR lines...');

        $this->cookieJar = new CookieJar();

        // 1. Obter cookie de sessão inicializando a página
        try {
            $response = Http::withOptions(['cookies' => $this->cookieJar])->get('https://paragens.amp.pt/unirmap/');
            if (!$response->successful()) {
                $this->error('Failed to fetch UNIR website index to get cookies.');
                return;
            }
        } catch (\Exception $e) {
            $this->error('Failed to fetch UNIR website: ' . $e->getMessage());
            return;
        }

        foreach ($this->municipalities as $mun) {
            $this->info("Fetching lines for municipality: $mun");
            
            $lines = $this->fetchUnirApi("/getmun?idop={$mun}");

            if (!$lines || !is_array($lines)) {
                $this->error("Failed to parse lines for $mun");
                continue;
            }

            foreach ($lines as $lineData) {
                $code = $lineData['codamp'] ?? null;
                $name = $lineData['designa'] ?? null;
                
                if (!$code || !$name) continue;

                $this->comment("Processing line: $code - $name...");

                $gid_ida = $lineData['gid_ida'] ?? null;
                $gid_volta = $lineData['gid_volta'] ?? null;

                $stops_ida = $gid_ida ? $this->fetchUnirStops($gid_ida) : [];
                sleep(1);
                $stops_volta = $gid_volta ? $this->fetchUnirStops($gid_volta) : [];
                sleep(1);

                DB::transaction(function () use ($code, $name, $stops_ida, $stops_volta) {
                    $busLine = UnirBusLine::updateOrCreate(
                        ['code' => $code],
                        [
                            'name' => $name,
                            'network' => 'U',
                            'slug' => Str::slug($code . '-' . $name),
                            'last_sync' => now(),
                        ]
                    );

                    UnirBusStop::updateOrCreate(
                        ['unir_bus_line_id' => $busLine->id],
                        [
                            'directions_0' => $stops_ida,
                            'directions_1' => $stops_volta,
                        ]
                    );
                });

                $this->info("✔ Line $code and stops synced.");
            }
        }

        $this->info('UNIR lines synchronization completed successfully.');
    }

    /**
     * Helper para fazer o fetch e decode da API da UNIR
     */
    private function fetchUnirApi($path)
    {
        try {
            $response = Http::withOptions(['cookies' => $this->cookieJar])
                ->withHeaders([
                    'X-Requested-With' => 'XMLHttpRequest',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
                ])
                ->timeout(30)
                ->get("https://paragens.amp.pt/unirmap" . $path);
            
            if ($response->successful()) {
                $data = $response->json();
                if (is_string($data)) {
                    $data = json_decode($data, true);
                }
                return $data;
            }
            return null;
        } catch (\Exception $e) {
            $this->error("Error fetching UNIR API for $path: " . $e->getMessage());
            return null;
        }
    }

    private function fetchUnirStops($gid)
    {
        try {
            $response = Http::withOptions(['cookies' => $this->cookieJar])
                ->withHeaders([
                    'X-Requested-With' => 'XMLHttpRequest',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
                ])
                ->timeout(30)
                ->get("https://paragens.amp.pt/geoserver/plim/wfs", [
                    'service' => 'WFS',
                    'version' => '1.0.0',
                    'request' => 'GetFeature',
                    'typeName' => 'plim:paragens_linha_amp',
                    'cql_filter' => "gid_linha={$gid}",
                    'outputFormat' => 'application/json',
                    'srsName' => 'EPSG:4326'
                ]);

            if (!$response->successful()) {
                return [];
            }

            $data = $response->json();
            $features = $data['features'] ?? [];
            
            // Ordenar por ordem da paragem
            usort($features, function($a, $b) {
                return ($a['properties']['ordem'] ?? 0) <=> ($b['properties']['ordem'] ?? 0);
            });

            $stops = [];

            foreach ($features as $feature) {
                if (!isset($feature['properties']) || !isset($feature['geometry'])) continue;

                $props = $feature['properties'];
                $geom = $feature['geometry'];
                
                $stops[] = [
                    'stop_id' => $props['cod_paragem'] ?? null,
                    'stop_name' => $props['desig_paragem'] ?? $props['localizacao'] ?? null,
                    'stop_code' => $props['cod_paragem'] ?? null,
                    'zone_id' => $props['zona_andante'] ?? null,
                    'stop_lat' => $geom['coordinates'][1] ?? null, // latitude
                    'stop_lon' => $geom['coordinates'][0] ?? null, // longitude
                    'stop_sequence' => $props['ordem'] ?? null,
                    'description' => $props['localizacao'] ?? null
                ];
            }

            return $stops;
        } catch (\Exception $e) {
            $this->error("Error fetching stops for GID {$gid}: " . $e->getMessage());
            return [];
        }
    }
}

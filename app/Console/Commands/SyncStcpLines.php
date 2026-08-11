<?php

namespace App\Console\Commands;

use App\CacheKeysEnum;
use Illuminate\Console\Command;
use App\Models\BusLine;
use App\Models\BusStop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class SyncStcpLines extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-stcp-lines {--force : Force synchronization even if data is recent}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync bus lines from STCP to the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $totalLinesProcessed = 0;

        $this->info('Initiating synchronization of STCP lines...');

        try {
            $linesHtml = Http::timeout(60)->get('https://stcp.pt/pt/linhas')->body();
            $crawler = new Crawler($linesHtml);
        } catch (\Exception $e) {
            $this->error('Failed to fetch STCP website: ' . $e->getMessage());
            return;
        }

        $crawler->filter('.lines-list .col')->each(function (Crawler $node) use (&$totalLinesProcessed) {
            $code = trim($node->filter('.line-number')->text());
            $name = $this->toCamelCaseName(trim($node->filter('.line-name')->text()));

            $this->comment("Processing line: $code...");

            $busDirection0 = $this->fetchStcpApi($code, 0);
            
            sleep(2); 
            
            $busDirection1 = $this->fetchStcpApi($code, 1);

            $stopsSynced = false;

            DB::transaction(function () use ($code, $name, $busDirection0, $busDirection1, &$stopsSynced) {

                $network = str_ends_with($code, 'M') ? 'M' : 'D';

                $busLine = BusLine::updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'network' => $network,
                        'slug' => Str::slug($code . '-' . $name),
                        'last_sync' => now(),
                    ]
                );

                if ($busDirection0 && $busDirection1) {
                    if (($busDirection0['success'] ?? false) && ($busDirection1['success'] ?? false)) {

                        BusStop::updateOrCreate(
                            ['bus_line_id' => $busLine->id],
                            [
                                'directions_0' => $busDirection0['stops'] ?? [],
                                'directions_1' => $busDirection1['stops'] ?? [],
                            ]
                        );

                        $this->info("✔ Line $code and stops synced.");

                        $stopsSynced = true;
                    }
                }
            });

            if ($stopsSynced) {
                Cache::forget(CacheKeysEnum::STCP_STOPS_BY_CODE . '_' . $code);
            }

            $totalLinesProcessed++;

            sleep(1);
        });

        Cache::forget(CacheKeysEnum::STCP_LINES_ALL);

        $this->info("STCP lines synchronization completed successfully. Total lines processed: $totalLinesProcessed.");
    }

    /**
     * Converte o nome da linha / paragem para Title Case, mantendo os separadores -, ., (, ) e ' com espaçamento legível.
     */
    private function toCamelCaseName(string $name): string
    {
        $delimiters = [' ', '-', '.', '(', ')', "'"];

        $tokens = preg_split("/([ \-\.\(\)'])/u", $name, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $result = '';
        foreach ($tokens as $token) {
            if ($token === ' ') {
                $result .= ' ';
            } elseif ($token === '-') {
                $result .= ' - ';
            } elseif ($token === '.') {
                $result .= '. ';
            } elseif (in_array($token, $delimiters, true)) {
                $result .= $token;
            } else {
                $result .= Str::ucfirst(Str::lower($token));
            }
        }

        $result = preg_replace('/\s+/', ' ', $result);
        $result = str_replace(['( ', ' )'], ['(', ')'], $result);

        return trim($result);
    }

    /**
     * Helper para fazer o fetch e decode da API da STCP
     */
    private function fetchStcpApi($code, $direction)
    {
        try {
            $response = Http::timeout(30)->get(config('app.stcp_api_base_url') . "/route/$code/stops/direction", [
                'direction_id' => $direction
            ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            if (! empty($data['stops'])) {
                foreach ($data['stops'] as &$stop) {
                    $stop['stop_name'] = $this->toCamelCaseName($stop['stop_name']);
                }
                unset($stop);
            }

            return $data;
        } catch (\Exception $e) {
            $this->error("Error fetching API for $code Dir $direction: " . $e->getMessage());
            return null;
        }
    }
}
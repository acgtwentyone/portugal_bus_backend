<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BusLine;
use App\Models\BusStop;
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
        $this->info('Initiating synchronization of STCP lines...');

        try {
            $linesHtml = Http::timeout(60)->get('https://stcp.pt/pt/linhas')->body();
            $crawler = new Crawler($linesHtml);
        } catch (\Exception $e) {
            $this->error('Failed to fetch STCP website: ' . $e->getMessage());
            return;
        }

        $crawler->filter('.lines-list .col')->each(function (Crawler $node) {
            $code = trim($node->filter('.line-number')->text());
            $name = trim($node->filter('.line-name')->text());

            $this->comment("Processing line: $code...");

            $busDirection0 = $this->fetchStcpApi($code, 0);
            
            sleep(2); 
            
            $busDirection1 = $this->fetchStcpApi($code, 1);

            DB::transaction(function () use ($code, $name, $busDirection0, $busDirection1) {
                
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
                    }
                }
            });

            sleep(1);
        });

        $this->info('STCP lines synchronization completed successfully.');
    }

    /**
     * Helper para fazer o fetch e decode da API da STCP
     */
    private function fetchStcpApi($code, $direction)
    {
        try {
            $response = Http::timeout(30)->get("https://stcp.pt/api/route/$code/stops/direction", [
                'direction_id' => $direction
            ]);
            
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            $this->error("Error fetching API for $code Dir $direction: " . $e->getMessage());
            return null;
        }
    }
}
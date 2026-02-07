<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BusLine;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Http;

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
        $force = $this->option('force');

        if ($force) {
            $this->warn('Attention: you are forcing syncronization!');
        }

        $this->info('Initiating synchronization of STCP lines...');

        $html = $this->fetchStcpLinesHtml();

        $crawler = new Crawler($html);

        // Select each line element and extract code and name
        $crawler->filter('.lines-list .col')->each(function (Crawler $node) {
            $code = $node->filter('.line-number')->text();
            $name = $node->filter('.line-name')->text();
            
            // check if code ends with "M" to determine the network type
            $network = str_ends_with($code, 'M') ? 'M' : 'D';

            BusLine::updateOrCreate(
                ['code' => $code], // Unique code identifier for the line
                [
                    'name' => $name,
                    'network' => $network,
                    'slug' => Str::slug($code . '-' . $name),
                    'last_sync' => now(),
                ]
            );
        });

        $this->info('STCP lines synchronization completed successfully.');
    }

    /**
     * Fetch the HTML content of the STCP lines page.
     *
     * @return string
     */
    private function fetchStcpLinesHtml(): string
    {
        return Http::timeout(60)->get('https://stcp.pt/pt/linhas')->body();
    }
}

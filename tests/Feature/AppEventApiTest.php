<?php

namespace Tests\Feature;

use App\Models\AppEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppEventApiTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = ['X-App-Name' => 'PortugalBus'];

    private function url(string $path): string
    {
        return 'http://' . config('app.api_domain') . '/api/v1' . $path;
    }

    public function test_provider_selected_event_is_stored_with_metadata(): void
    {
        $this->postJson($this->url('/events'), [
            'event_type' => 'provider_selected',
            'metadata' => ['from' => 'stcp', 'to' => 'unir', 'source' => 'lines'],
        ], self::HEADERS)->assertCreated();

        $event = AppEvent::query()->firstOrFail();
        $this->assertSame('provider_selected', $event->event_type instanceof \BackedEnum ? $event->event_type->value : $event->event_type);
        $this->assertEquals(['from' => 'stcp', 'to' => 'unir', 'source' => 'lines'], is_array($event->metadata) ? $event->metadata : json_decode($event->metadata, true));
    }

    public function test_unknown_event_types_are_rejected(): void
    {
        $this->postJson($this->url('/events'), ['event_type' => 'xpto'], self::HEADERS)
            ->assertStatus(422);
    }

    public function test_summary_counts_provider_selected(): void
    {
        foreach (['unir', 'stcp'] as $to) {
            $this->postJson($this->url('/events'), ['event_type' => 'provider_selected', 'metadata' => ['to' => $to]], self::HEADERS)->assertCreated();
        }

        $this->getJson($this->url('/events/summary'), self::HEADERS)
            ->assertOk()
            ->assertJsonPath('provider_selected', 2);
    }
}

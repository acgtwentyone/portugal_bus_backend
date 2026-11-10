<?php

namespace Tests\Feature;

use App\Models\UnirBusLine;
use App\Models\UnirBusStop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UnirApiTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = ['X-App-Name' => 'PortugalBus'];

    protected function setUp(): void
    {
        parent::setUp();

        // 10:00 em Lisboa
        $this->travelTo(Carbon::parse('2026-11-10 10:00:00', 'Europe/Lisbon'));
    }

    private function url(string $path): string
    {
        return 'http://' . config('app.api_domain') . '/api/v1' . $path;
    }

    private function fakeUnir(array $horarios, string $designa = 'Paragem Teste', bool $doubleEncoded = false): void
    {
        $payload = json_encode(['designa' => $designa, 'horarios' => $horarios]);

        Http::fake([
            '*get_horarios_prg*' => Http::response($doubleEncoded ? json_encode($payload) : $payload, 200),
        ]);
    }

    public function test_realtime_returns_upcoming_scheduled_arrivals_flagged_as_not_realtime(): void
    {
        $this->fakeUnir([
            ['linha' => '2001', 'destino' => 'Maia', 'chegada' => '09:30', 'trip_id' => 'past'],
            ['linha' => '2001', 'destino' => 'Maia', 'chegada' => '10:15', 'trip_id' => 't1'],
            ['linha' => '2002', 'destino' => 'Porto', 'chegada' => '10:05:00', 'trip_id' => 't2'],
            ['linha' => '2002', 'destino' => 'Porto', 'chegada' => '13:00', 'trip_id' => 'too-late'],
        ]);

        $response = $this->getJson($this->url('/unir/stops/MAI1/realtime'), self::HEADERS);

        $response->assertOk()
            ->assertJsonPath('stop_id', 'MAI1')
            ->assertJsonPath('stop_name', 'Paragem Teste')
            ->assertJsonPath('data_source', 'UNIR')
            ->assertJsonPath('is_realtime', false)
            ->assertJsonCount(2, 'arrivals')
            // ordenadas pela mais próxima
            ->assertJsonPath('arrivals.0.route_short_name', '2002')
            ->assertJsonPath('arrivals.0.arrival_minutes', 5)
            ->assertJsonPath('arrivals.0.status', 'SCHEDULED')
            ->assertJsonPath('arrivals.1.trip_id', 't1')
            ->assertJsonPath('arrivals.1.arrival_minutes', 15);
    }

    public function test_realtime_handles_double_encoded_json_and_after_midnight_times(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 23:50:00', 'Europe/Lisbon'));

        $this->fakeUnir([
            ['linha' => '2001', 'destino' => 'Maia', 'chegada' => '24:10', 'trip_id' => 'night'],
        ], doubleEncoded: true);

        $this->getJson($this->url('/unir/stops/MAI1/realtime'), self::HEADERS)
            ->assertOk()
            ->assertJsonCount(1, 'arrivals')
            ->assertJsonPath('arrivals.0.arrival_minutes', 20);
    }

    public function test_parses_real_source_format_with_seconds_and_direction(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 21:05:00', 'Europe/Lisbon'));

        // Formato real devolvido por /acarto2/get_horarios_prg?id=mai:129 (JSON dentro de string).
        $real = json_encode([
            'prg' => 'mai:129', 'designa' => 'Cutamas', 'zona' => 'MAI4',
            'horarios' => [
                ['trip_id' => 'A-U:6005:0:1:2115', 'chegada' => '21:51:00', 'linha' => '6005', 'sentido' => '1', 'destino' => 'Hospital São João'],
                ['trip_id' => 'A-U:6005:0:1:2215', 'chegada' => '22:51:00', 'linha' => '6005', 'sentido' => '1', 'destino' => 'Hospital São João'],
                ['trip_id' => 'A-U:6005:0:1:0615', 'chegada' => '06:50:30', 'linha' => '6005', 'sentido' => '1', 'destino' => 'Hospital São João'],
            ],
        ]);
        Http::fake(['*get_horarios_prg*' => Http::response(json_encode($real), 200)]);

        $this->getJson($this->url('/unir/stops/mai:129/realtime'), self::HEADERS)
            ->assertOk()
            ->assertJsonPath('stop_name', 'Cutamas')
            // 21:51 (+46) e 22:51 (+106) estão na janela de 120 min; 06:50 não
            ->assertJsonCount(2, 'arrivals')
            ->assertJsonPath('arrivals.0.arrival_minutes', 46)
            ->assertJsonPath('arrivals.1.arrival_minutes', 106)
            ->assertJsonPath('arrivals.0.trip_headsign', 'Hospital São João');

        $this->getJson($this->url('/unir/stops/mai:129/schedule-cards'), self::HEADERS)
            ->assertOk()
            ->assertJsonPath('lines.0.direction_id', 1)
            ->assertJsonPath('lines.0.times', ['06:50', '21:51', '22:51']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'id=mai%3A129'));
    }

    public function test_realtime_returns_502_when_source_fails_instead_of_empty_list(): void
    {
        Http::fake(['*get_horarios_prg*' => Http::response('boom', 500)]);

        $this->getJson($this->url('/unir/stops/MAI1/realtime'), self::HEADERS)
            ->assertStatus(502)
            ->assertJsonPath('arrivals', []);
    }

    public function test_realtime_returns_502_when_response_is_unreadable(): void
    {
        Http::fake(['*get_horarios_prg*' => Http::response('<html>manutenção</html>', 200)]);

        $this->getJson($this->url('/unir/stops/MAI1/realtime'), self::HEADERS)
            ->assertStatus(502);
    }

    public function test_empty_schedule_is_ok_not_an_error(): void
    {
        $this->fakeUnir([]);

        $this->getJson($this->url('/unir/stops/MAI1/realtime'), self::HEADERS)
            ->assertOk()
            ->assertJsonCount(0, 'arrivals');
    }

    public function test_schedule_cards_group_by_line_and_destination_with_sorted_times(): void
    {
        UnirBusLine::create(['code' => '2001', 'name' => 'Maia - Porto', 'network' => 'U', 'slug' => '2001-maia-porto']);

        $this->fakeUnir([
            ['linha' => '2001', 'destino' => 'Maia', 'chegada' => '24:15'],
            ['linha' => '2001', 'destino' => 'Maia', 'chegada' => '08:00'],
            ['linha' => '2001', 'destino' => 'Porto', 'chegada' => '09:00'],
            ['linha' => '2001', 'destino' => 'Maia', 'chegada' => '23:45'],
            ['linha' => '9999', 'destino' => 'Ermesinde', 'chegada' => '07:10'],
        ]);

        $response = $this->getJson($this->url('/unir/stops/MAI1/schedule-cards'), self::HEADERS);

        $response->assertOk()
            ->assertJsonPath('stop_id', 'MAI1')
            ->assertJsonPath('partial', false)
            ->assertJsonCount(3, 'lines')
            ->assertJsonPath('lines.0.route_id', '2001')
            ->assertJsonPath('lines.0.headsign', 'Maia')
            ->assertJsonPath('lines.0.route_name', 'Maia - Porto')
            ->assertJsonPath('lines.0.times', ['08:00', '23:45', '24:15'])
            ->assertJsonPath('lines.1.headsign', 'Porto')
            // linha que não está na BD: usa o destino como nome
            ->assertJsonPath('lines.2.route_name', 'Ermesinde');
    }

    public function test_day_schedule_is_cached_so_realtime_and_cards_hit_the_source_once(): void
    {
        $this->fakeUnir([['linha' => '2001', 'destino' => 'Maia', 'chegada' => '10:30']]);

        $this->getJson($this->url('/unir/stops/MAI1/realtime'), self::HEADERS)->assertOk();
        $this->getJson($this->url('/unir/stops/MAI1/schedule-cards'), self::HEADERS)->assertOk();

        Http::assertSentCount(1);
    }

    public function test_source_failures_are_not_cached(): void
    {
        Http::fakeSequence('*get_horarios_prg*')
            ->push('boom', 500)
            ->push(json_encode(['horarios' => [['linha' => '2001', 'destino' => 'Maia', 'chegada' => '10:30']]]), 200);

        $this->getJson($this->url('/unir/stops/MAI1/realtime'), self::HEADERS)->assertStatus(502);
        $this->getJson($this->url('/unir/stops/MAI1/realtime'), self::HEADERS)
            ->assertOk()
            ->assertJsonCount(1, 'arrivals');
    }

    public function test_requests_without_app_header_are_rejected(): void
    {
        $this->getJson($this->url('/unir/stops/MAI1/schedule-cards'))->assertForbidden();
    }

    public function test_search_stops_by_name_or_code_without_duplicates(): void
    {
        $stop = fn ($code, $name) => ['stop_id' => $code, 'stop_code' => $code, 'stop_name' => $name, 'zone_id' => 'MAI4'];

        $a = UnirBusLine::create(['code' => '6005', 'name' => 'A', 'network' => 'U', 'slug' => '6005-a']);
        $b = UnirBusLine::create(['code' => '6006', 'name' => 'B', 'network' => 'U', 'slug' => '6006-b']);
        UnirBusStop::create(['unir_bus_line_id' => $a->id, 'directions_0' => [$stop('mai:129', 'Cutamas'), $stop('mai:130', 'Maia Centro')], 'directions_1' => []]);
        // a mesma paragem servida por outra linha não aparece duas vezes
        UnirBusStop::create(['unir_bus_line_id' => $b->id, 'directions_0' => [$stop('mai:129', 'Cutamas')], 'directions_1' => null]);

        $this->getJson($this->url('/unir/stops?search=cutam'), self::HEADERS)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.stop_code', 'mai:129');

        $this->getJson($this->url('/unir/stops?search=MAI:13'), self::HEADERS)
            ->assertOk()
            ->assertJsonPath('data.0.stop_name', 'Maia Centro');

        $this->getJson($this->url('/unir/stops?search='), self::HEADERS)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}

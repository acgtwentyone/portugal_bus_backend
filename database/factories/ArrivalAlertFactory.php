<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ArrivalAlert>
 */
class ArrivalAlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stop_id' => 'BLM1',
            'route_id' => '205',
            'direction_id' => 0,
            'trip_id' => 'T1',
            'estimated_arrival_time' => now()->addMinutes(10),
            'threshold_minutes' => 5,
            'device_token' => fake()->sha1(),
            'locale' => 'pt',
        ];
    }
}

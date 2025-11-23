<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Device>
 */
class DeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['switch', 'dimmer']);

        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'location' => fake()->optional()->city(),
            'location_id' => null,
            'type' => $type,
            'status' => fake()->randomElement(['on', 'off']),
            'brightness' => $type === 'dimmer'
                ? fake()->numberBetween(0, 100)
                : 100,
        ];
    }
}

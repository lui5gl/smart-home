<?php

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can view their devices on the dashboard', function () {
    $user = User::factory()->create();
    $userDevices = Device::factory()
        ->count(2)
        ->for($user)
        ->sequence(
            ['name' => 'Sensor de temperatura', 'type' => 'dimmer'],
            ['name' => 'Foco principal', 'type' => 'switch']
        )
        ->create();

    Device::factory()->create(); // other user's device

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('devices', function (Collection $devices) use ($userDevices) {
                return $devices->pluck('id')->diff($userDevices->pluck('id'))->isEmpty()
                    && $devices->count() === $userDevices->count()
                    && $devices->every(fn (array $device) => array_key_exists('name', $device)
                        && array_key_exists('location', $device)
                        && array_key_exists('type', $device)
                        && array_key_exists('created_at', $device));
            })
        );
});

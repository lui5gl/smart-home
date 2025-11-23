<?php

use App\Models\Device;
use App\Models\Location;
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
            ['name' => 'Sensor de temperatura', 'type' => 'dimmer', 'status' => 'on', 'brightness' => 70],
            ['name' => 'Foco principal', 'type' => 'switch', 'status' => 'off', 'brightness' => 100]
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
                        && array_key_exists('status', $device)
                        && array_key_exists('brightness', $device)
                        && array_key_exists('created_at', $device)
                        && array_key_exists('updated_at', $device));
            })
        );
});

test('users can filter devices by location on the dashboard', function () {
    $user = User::factory()->create();
    $locationA = Location::factory()->for($user)->create(['name' => 'Oficina']);
    $locationB = Location::factory()->for($user)->create(['name' => 'Habitación']);

    $deviceInA = Device::factory()->for($user)->create([
        'location_id' => $locationA->id,
        'location' => $locationA->name,
    ]);
    Device::factory()->for($user)->create([
        'location_id' => $locationB->id,
        'location' => $locationB->name,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', ['location' => $locationA->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('devices', fn (Collection $devices) => $devices->count() === 1
                && $devices->first()['id'] === $deviceInA->id)
        );
});

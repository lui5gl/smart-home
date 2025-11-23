<?php

use App\Models\Area;
use App\Models\Device;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can view their devices grouped by locations and areas', function () {
    $user = User::factory()->create();
    $location = Location::factory()->for($user)->create(['name' => 'Casa']);
    $areaOne = Area::factory()->for($user)->for($location)->create(['name' => 'Sala']);
    $areaTwo = Area::factory()->for($user)->for($location)->create(['name' => 'Habitación']);

    $deviceInSala = Device::factory()->for($user)->create([
        'name' => 'Sensor de temperatura',
        'location' => $areaOne->name,
        'location_id' => $location->id,
        'area_id' => $areaOne->id,
    ]);
    $deviceInHabitacion = Device::factory()->for($user)->create([
        'name' => 'Foco principal',
        'location' => $areaTwo->name,
        'location_id' => $location->id,
        'area_id' => $areaTwo->id,
    ]);
    Device::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('devices', function (Collection $devices) use ($deviceInSala, $deviceInHabitacion) {
                return $devices->pluck('id')->diff(collect([$deviceInSala->id, $deviceInHabitacion->id]))->isEmpty();
            })
            ->has('locations', fn (Assert $locations) => $locations
                ->where('0.name', 'Casa')
                ->where('0.areas.0.name', 'Sala')
                ->where('0.areas.1.name', 'Habitación')
            )
        );
});

test('users can filter devices by location and area', function () {
    $user = User::factory()->create();
    $location = Location::factory()->for($user)->create(['name' => 'Departamento']);
    $sala = Area::factory()->for($user)->for($location)->create(['name' => 'Sala']);
    $cocina = Area::factory()->for($user)->for($location)->create(['name' => 'Cocina']);

    $deviceInSala = Device::factory()->for($user)->create([
        'location' => $sala->name,
        'location_id' => $location->id,
        'area_id' => $sala->id,
    ]);
    Device::factory()->for($user)->create([
        'location' => $cocina->name,
        'location_id' => $location->id,
        'area_id' => $cocina->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', ['location' => $location->id, 'area' => $sala->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('devices', fn (Collection $devices) => $devices->count() === 1
                && $devices->first()['id'] === $deviceInSala->id)
            ->where('filters.location', $location->id)
            ->where('filters.area', $sala->id)
        );
});

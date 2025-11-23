<?php

use App\Models\Area;
use App\Models\Device;
use App\Models\Location;
use App\Models\User;

test('authenticated users can create devices', function () {
    $user = User::factory()->create();
    $location = Location::factory()->for($user)->create(['name' => 'Casa principal']);
    $area = Area::factory()->for($user)->for($location)->create(['name' => 'Sala']);

    $this->actingAs($user);

    $response = $this->post(route('devices.store'), [
        'name' => 'Sensor de temperatura',
        'area_id' => $area->id,
        'type' => 'dimmer',
        'status' => 'on',
        'brightness' => 80,
    ]);

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('success', 'Dispositivo agregado correctamente.');

    $this->assertDatabaseHas('devices', [
        'user_id' => $user->id,
        'area_id' => $area->id,
        'location_id' => $location->id,
        'name' => 'Sensor de temperatura',
        'location' => $area->name,
        'type' => 'dimmer',
        'status' => 'on',
        'brightness' => 80,
    ]);
});

test('authenticated users can update their devices', function () {
    $user = User::factory()->create();
    $location = Location::factory()->for($user)->create(['name' => 'Casa']);
    $area = Area::factory()->for($user)->for($location)->create(['name' => 'Sala']);
    $newArea = Area::factory()->for($user)->for($location)->create(['name' => 'Terraza']);
    $device = Device::factory()->for($user)->create([
        'name' => 'Sensor de temperatura',
        'location' => $area->name,
        'location_id' => $location->id,
        'area_id' => $area->id,
        'type' => 'dimmer',
        'status' => 'off',
        'brightness' => 60,
    ]);

    $this->actingAs($user);

    $response = $this->patch(route('devices.update', $device), [
        'name' => 'Sensor exterior',
        'area_id' => $newArea->id,
        'type' => 'switch',
        'status' => 'on',
        'brightness' => 100,
    ]);

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('success', 'Dispositivo actualizado correctamente.');

    $this->assertDatabaseHas('devices', [
        'id' => $device->id,
        'user_id' => $user->id,
        'area_id' => $newArea->id,
        'location_id' => $location->id,
        'name' => 'Sensor exterior',
        'location' => $newArea->name,
        'type' => 'switch',
        'status' => 'on',
        'brightness' => 100,
    ]);
});

test('users cannot update devices that are not theirs', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $location = Location::factory()->for($owner)->create();
    $area = Area::factory()->for($owner)->for($location)->create();
    $device = Device::factory()->for($owner)->create([
        'name' => 'Sensor interior',
        'location' => $area->name,
        'location_id' => $location->id,
        'area_id' => $area->id,
        'type' => 'switch',
        'status' => 'off',
        'brightness' => 100,
    ]);

    $this->actingAs($otherUser);

    $response = $this->patch(route('devices.update', $device), [
        'name' => 'Sensor actualizado',
        'area_id' => $area->id,
        'type' => 'dimmer',
        'status' => 'on',
        'brightness' => 70,
    ]);

    $response->assertForbidden();
});

test('guests cannot create devices', function () {
    $response = $this->post(route('devices.store'), [
        'name' => 'Sensor de movimiento',
        'area_id' => 1,
        'type' => 'switch',
        'status' => 'on',
        'brightness' => 100,
    ]);

    $response->assertRedirect(route('login'));
    $this->assertDatabaseCount('devices', 0);
});

test('users can assign devices to saved areas', function () {
    $user = User::factory()->create();
    $location = Location::factory()->for($user)->create(['name' => 'Oficina']);
    $area = Area::factory()->for($user)->for($location)->create(['name' => 'Recepción']);

    $this->actingAs($user);

    $response = $this->post(route('devices.store'), [
        'name' => 'Sensor de presencia',
        'area_id' => $area->id,
        'type' => 'switch',
        'status' => 'off',
        'brightness' => 100,
    ]);

    $response->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('devices', [
        'user_id' => $user->id,
        'area_id' => $area->id,
        'location_id' => $location->id,
        'location' => $area->name,
    ]);
});

test('users cannot assign devices to areas they do not own', function () {
    $user = User::factory()->create();
    $otherArea = Area::factory()->create();

    $this->actingAs($user);

    $response = $this->from(route('dashboard'))->post(route('devices.store'), [
        'name' => 'Sensor de puerta',
        'area_id' => $otherArea->id,
        'type' => 'switch',
        'status' => 'on',
        'brightness' => 100,
    ]);

    $response->assertSessionHasErrors('area_id');
});

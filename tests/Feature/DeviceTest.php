<?php

use App\Models\Device;
use App\Models\Location;
use App\Models\User;

test('authenticated users can create devices', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post(route('devices.store'), [
        'name' => 'Sensor de temperatura',
        'location' => '  Sala principal ',
        'type' => 'dimmer',
        'status' => 'on',
        'brightness' => 80,
    ]);

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('success', 'Dispositivo agregado correctamente.');

    $this->assertDatabaseHas('devices', [
        'user_id' => $user->id,
        'name' => 'Sensor de temperatura',
        'location' => 'Sala principal',
        'type' => 'dimmer',
        'status' => 'on',
        'brightness' => 80,
    ]);
});

test('location is optional when creating devices', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post(route('devices.store'), [
        'name' => 'Foco principal',
        'location' => '',
        'type' => 'switch',
        'status' => 'off',
        'brightness' => 100,
    ]);

    $response->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('devices', [
        'user_id' => $user->id,
        'name' => 'Foco principal',
        'location' => null,
        'type' => 'switch',
        'status' => 'off',
        'brightness' => 100,
    ]);
});

test('authenticated users can update their devices', function () {
    $user = User::factory()->create();
    $device = Device::factory()->for($user)->create([
        'name' => 'Sensor de temperatura',
        'location' => 'Sala',
        'type' => 'dimmer',
        'status' => 'off',
        'brightness' => 60,
    ]);

    $this->actingAs($user);

    $response = $this->patch(route('devices.update', $device), [
        'name' => 'Sensor exterior',
        'location' => '  Terraza ',
        'type' => 'switch',
        'status' => 'on',
        'brightness' => 100,
    ]);

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('success', 'Dispositivo actualizado correctamente.');

    $this->assertDatabaseHas('devices', [
        'id' => $device->id,
        'user_id' => $user->id,
        'name' => 'Sensor exterior',
        'location' => 'Terraza',
        'type' => 'switch',
        'status' => 'on',
        'brightness' => 100,
    ]);
});

test('users cannot update devices that are not theirs', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $device = Device::factory()->for($owner)->create([
        'name' => 'Sensor interior',
        'location' => 'Pasillo',
        'type' => 'switch',
        'status' => 'off',
        'brightness' => 100,
    ]);

    $this->actingAs($otherUser);

    $response = $this->patch(route('devices.update', $device), [
        'name' => 'Sensor actualizado',
        'location' => 'Habitación',
        'type' => 'dimmer',
        'status' => 'on',
        'brightness' => 70,
    ]);

    $response->assertForbidden();

    $this->assertDatabaseHas('devices', [
        'id' => $device->id,
        'name' => 'Sensor interior',
        'location' => 'Pasillo',
        'type' => 'switch',
        'status' => 'off',
        'brightness' => 100,
    ]);
});

test('guests cannot create devices', function () {
    $response = $this->post(route('devices.store'), [
        'name' => 'Sensor de movimiento',
        'location' => 'Entrada',
        'type' => 'switch',
        'status' => 'on',
        'brightness' => 100,
    ]);

    $response->assertRedirect(route('login'));
    $this->assertDatabaseCount('devices', 0);
});

test('users can assign devices to saved locations', function () {
    $user = User::factory()->create();
    $location = Location::factory()->for($user)->create(['name' => 'Oficina']);

    $this->actingAs($user);

    $response = $this->post(route('devices.store'), [
        'name' => 'Sensor de presencia',
        'location' => '',
        'location_id' => $location->id,
        'type' => 'switch',
        'status' => 'off',
        'brightness' => 100,
    ]);

    $response->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('devices', [
        'user_id' => $user->id,
        'location_id' => $location->id,
        'location' => $location->name,
    ]);
});

test('users cannot assign devices to locations they do not own', function () {
    $user = User::factory()->create();
    $otherLocation = Location::factory()->create(['name' => 'Inválido']);

    $this->actingAs($user);

    $response = $this->from(route('dashboard'))->post(route('devices.store'), [
        'name' => 'Sensor de puerta',
        'location' => 'Pasillo',
        'location_id' => $otherLocation->id,
        'type' => 'switch',
        'status' => 'on',
        'brightness' => 100,
    ]);

    $response->assertSessionHasErrors('location_id');
});

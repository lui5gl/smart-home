<?php

use App\Models\User;

test('authenticated users can create devices', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post(route('devices.store'), [
        'name' => 'Sensor de temperatura',
        'location' => '  Sala principal ',
        'type' => 'dimmer',
    ]);

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('success', 'Dispositivo agregado correctamente.');

    $this->assertDatabaseHas('devices', [
        'user_id' => $user->id,
        'name' => 'Sensor de temperatura',
        'location' => 'Sala principal',
        'type' => 'dimmer',
    ]);
});

test('location is optional when creating devices', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post(route('devices.store'), [
        'name' => 'Foco principal',
        'location' => '',
        'type' => 'switch',
    ]);

    $response->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('devices', [
        'user_id' => $user->id,
        'name' => 'Foco principal',
        'location' => null,
        'type' => 'switch',
    ]);
});

test('guests cannot create devices', function () {
    $response = $this->post(route('devices.store'), [
        'name' => 'Sensor de movimiento',
        'location' => 'Entrada',
        'type' => 'switch',
    ]);

    $response->assertRedirect(route('login'));
    $this->assertDatabaseCount('devices', 0);
});

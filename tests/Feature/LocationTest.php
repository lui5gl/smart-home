<?php

use App\Models\Location;
use App\Models\User;

test('authenticated users can create locations', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post(route('locations.store'), [
        'name' => 'Sala principal',
    ]);

    $response->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('locations', [
        'user_id' => $user->id,
        'name' => 'Sala principal',
    ]);
});

test('users cannot create duplicated location names', function () {
    $user = User::factory()->create();
    Location::factory()->for($user)->create(['name' => 'Cocina']);

    $this->actingAs($user);

    $response = $this->from(route('dashboard'))->post(route('locations.store'), [
        'name' => 'Cocina',
    ]);

    $response->assertSessionHasErrors('name');
});

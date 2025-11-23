<?php

use App\Models\Location;
use App\Models\User;

test('authenticated users can create areas within a location', function () {
    $user = User::factory()->create();
    $location = Location::factory()->for($user)->create();

    $this->actingAs($user);

    $response = $this->post(route('areas.store'), [
        'location_id' => $location->id,
        'name' => 'Sala principal',
    ]);

    $response->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('areas', [
        'location_id' => $location->id,
        'user_id' => $user->id,
        'name' => 'Sala principal',
    ]);
});

test('users cannot duplicate area names within the same location', function () {
    $user = User::factory()->create();
    $location = Location::factory()->for($user)->create();
    \App\Models\Area::factory()->for($user)->for($location)->create(['name' => 'Sala principal']);

    $this->actingAs($user);

    $response = $this->from(route('dashboard'))->post(route('areas.store'), [
        'location_id' => $location->id,
        'name' => 'Sala principal',
    ]);

    $response->assertSessionHasErrors('name');
});

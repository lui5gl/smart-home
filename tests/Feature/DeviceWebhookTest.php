<?php

use App\Models\Device;
use App\Models\User;

it('returns device status through the webhook', function () {
    /** @var User $user */
    $user = User::factory()->create();

    /** @var Device $device */
    $device = Device::factory()->for($user)->create([
        'type' => 'dimmer',
        'status' => 'on',
        'brightness' => 65,
    ]);

    $response = $this->getJson(route('devices.webhook', ['token' => $device->webhook_token]));

    $response->assertSuccessful();
    $response->assertJsonPath('id', $device->id);
    $response->assertJsonPath('status', 'on');
    $response->assertJsonPath('brightness', 65);
});

it('returns not found for hidden devices', function () {
    /** @var Device $device */
    $device = Device::factory()->create([
        'hidden' => true,
    ]);

    $response = $this->getJson(route('devices.webhook', ['token' => $device->webhook_token]));

    $response->assertNotFound();
});

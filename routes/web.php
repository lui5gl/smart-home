<?php

use App\Http\Controllers\DeviceController;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function (Request $request) {
    $devices = $request->user()
        ->devices()
        ->latest()
        ->get()
        ->map(fn (Device $device) => [
            'id' => $device->id,
            'name' => $device->name,
            'location' => $device->location,
            'type' => $device->type,
            'created_at' => $device->created_at?->toIso8601String(),
        ]);

    return Inertia::render('Dashboard', [
        'devices' => $devices,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::post('devices', [DeviceController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('devices.store');

require __DIR__.'/settings.php';

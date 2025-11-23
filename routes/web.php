<?php

use App\Http\Controllers\DeviceController;
use App\Http\Controllers\LocationController;
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
    $user = $request->user();
    $locations = $user->locations()->orderBy('name')->get(['id', 'name']);
    $locationFilter = $request->query('location');
    $locationId = is_numeric($locationFilter) ? (int) $locationFilter : null;
    $showUnassigned = $locationFilter === 'none';

    $devicesQuery = $user->devices()->with('location')->latest();

    if ($showUnassigned) {
        $devicesQuery->whereNull('location_id');
    } elseif ($locationId) {
        $devicesQuery->where('location_id', $locationId);
    }

    $devices = $devicesQuery
        ->get()
        ->map(fn (Device $device) => [
            'id' => $device->id,
            'name' => $device->name,
            'location' => $device->location?->name ?? $device->location,
            'location_id' => $device->location_id,
            'type' => $device->type,
            'status' => $device->status,
            'brightness' => $device->brightness,
            'created_at' => $device->created_at?->toIso8601String(),
            'updated_at' => $device->updated_at?->toIso8601String(),
        ]);

    return Inertia::render('Dashboard', [
        'devices' => $devices,
        'locations' => $locations,
        'filters' => [
            'location' => $showUnassigned ? 'none' : $locationId,
        ],
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::post('devices', [DeviceController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('devices.store');

Route::patch('devices/{device}', [DeviceController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('devices.update');

Route::post('locations', [LocationController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('locations.store');

require __DIR__.'/settings.php';

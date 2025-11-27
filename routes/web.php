<?php

use App\Http\Controllers\AreaController;
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
    $locationFilter = $request->query('location');
    $areaFilter = $request->query('area');
    $locationId = is_numeric($locationFilter) ? (int) $locationFilter : null;
    $areaId = is_numeric($areaFilter) ? (int) $areaFilter : null;

    $locations = $user->locations()
        ->with(['areas' => fn ($query) => $query->orderBy('name')])
        ->orderBy('name')
        ->get()
        ->map(fn ($location) => [
            'id' => $location->id,
            'name' => $location->name,
            'areas' => $location->areas->map(fn ($area) => [
                'id' => $area->id,
                'name' => $area->name,
            ]),
        ]);

    $devicesQuery = $user->devices()->visible()->with(['location', 'area'])->latest();
    $filters = [
        'location' => null,
        'area' => null,
    ];

    if ($areaId) {
        $area = $user->areas()->whereKey($areaId)->first();

        if ($area) {
            $devicesQuery->where('area_id', $area->id);
            $filters['area'] = $area->id;
            $filters['location'] = $area->location_id;
        }
    } elseif ($locationId) {
        $userLocation = $user->locations()->whereKey($locationId)->first();

        if ($userLocation) {
            $devicesQuery->where('location_id', $userLocation->id);
            $filters['location'] = $userLocation->id;
        }
    }

    $devices = $devicesQuery
        ->get()
        ->map(fn (Device $device) => [
            'id' => $device->id,
            'name' => $device->name,
            'location' => $device->area?->name ?? $device->location?->name ?? $device->location,
            'location_id' => $device->location_id,
            'area_id' => $device->area_id,
            'type' => $device->type,
            'status' => $device->status,
            'brightness' => $device->brightness,
            'created_at' => $device->created_at?->toIso8601String(),
            'updated_at' => $device->updated_at?->toIso8601String(),
            'webhook_url' => route('devices.webhook', ['token' => $device->webhook_token]),
        ]);

    return Inertia::render('Dashboard', [
        'devices' => $devices,
        'locations' => $locations,
        'filters' => $filters,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::post('devices', [DeviceController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('devices.store');

Route::patch('devices/{device}', [DeviceController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('devices.update');

Route::delete('devices/{device}', [DeviceController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->name('devices.destroy');

Route::get('webhooks/devices/{token}', [DeviceController::class, 'webhook'])
    ->name('devices.webhook');

Route::post('areas', [AreaController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('areas.store');

Route::post('locations', [LocationController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('locations.store');

Route::post('voice/session', [\App\Http\Controllers\VoiceCommandController::class, 'createSession'])
    ->middleware(['auth', 'verified'])
    ->name('voice.session');

Route::post('voice/tool', [\App\Http\Controllers\VoiceCommandController::class, 'executeTool'])
    ->middleware(['auth', 'verified'])
    ->name('voice.tool');

// Route::post('voice-command', [\App\Http\Controllers\VoiceCommandController::class, 'handle'])
//     ->middleware(['auth', 'verified'])
//     ->name('voice.command');

require __DIR__.'/settings.php';

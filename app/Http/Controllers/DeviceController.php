<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    public function store(StoreDeviceRequest $request): RedirectResponse
    {
        $request->user()
            ->devices()
            ->create([
                ...$request->validated(),
                'webhook_token' => (string) Str::uuid(),
            ]);

        return redirect()->route('dashboard')->with('success', 'Dispositivo agregado correctamente.');
    }

    public function update(UpdateDeviceRequest $request, Device $device): RedirectResponse
    {
        $device->update($request->validated());

        return redirect()->route('dashboard')->with('success', 'Dispositivo actualizado correctamente.');
    }

    public function destroy(Request $request, Device $device): RedirectResponse
    {
        abort_unless($request->user()?->is($device->user), 403);

        $device->update(['hidden' => true]);

        return redirect()->route('dashboard')->with('success', 'Dispositivo eliminado correctamente.');
    }

    public function webhook(string $token): JsonResponse
    {
        $device = Device::query()
            ->where('webhook_token', $token)
            ->where('hidden', false)
            ->firstOrFail();

        return response()->json([
            'id' => $device->id,
            'name' => $device->name,
            'type' => $device->type,
            'status' => $device->status,
            'brightness' => $device->brightness,
            'location_id' => $device->location_id,
            'area_id' => $device->area_id,
            'updated_at' => optional($device->updated_at)->toIso8601String(),
        ]);
    }
}

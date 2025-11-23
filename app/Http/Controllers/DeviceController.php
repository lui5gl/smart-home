<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function store(StoreDeviceRequest $request): RedirectResponse
    {
        $request->user()
            ->devices()
            ->create($request->validated());

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
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceRequest;
use Illuminate\Http\RedirectResponse;

class DeviceController extends Controller
{
    public function store(StoreDeviceRequest $request): RedirectResponse
    {
        $request->user()
            ->devices()
            ->create($request->validated());

        return redirect()->route('dashboard')->with('success', 'Dispositivo agregado correctamente.');
    }
}

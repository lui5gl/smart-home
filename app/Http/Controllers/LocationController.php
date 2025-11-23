<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLocationRequest;
use Illuminate\Http\RedirectResponse;

class LocationController extends Controller
{
    public function store(StoreLocationRequest $request): RedirectResponse
    {
        $request->user()
            ->locations()
            ->create($request->validated());

        return redirect()->route('dashboard')->with('success', 'Ubicación creada correctamente.');
    }
}

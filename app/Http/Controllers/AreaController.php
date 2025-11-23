<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAreaRequest;
use Illuminate\Http\RedirectResponse;

class AreaController extends Controller
{
    public function store(StoreAreaRequest $request): RedirectResponse
    {
        $request->user()
            ->areas()
            ->create($request->validated());

        return redirect()->route('dashboard')->with('success', 'Área creada correctamente.');
    }
}

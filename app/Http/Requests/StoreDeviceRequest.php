<?php

namespace App\Http\Requests;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $location = $this->input('location');
        $locationId = $this->input('location_id');
        $brightness = $this->input('brightness');
        $type = $this->input('type');
        $user = $this->user();
        $resolvedLocation = is_string($location) ? trim($location) : $location;
        $resolvedLocationId = filled($locationId) ? (int) $locationId : null;

        if ($user !== null && filled($locationId)) {
            /** @var Location|null $userLocation */
            $userLocation = $user->locations()->whereKey($locationId)->first();

            if ($userLocation !== null) {
                $resolvedLocation = $userLocation->name;
                $resolvedLocationId = $userLocation->id;
            }
        }

        $this->merge([
            'name' => is_string($name) ? trim($name) : $name,
            'location' => filled($resolvedLocation) ? $resolvedLocation : null,
            'location_id' => $resolvedLocationId,
            'brightness' => $type === 'dimmer'
                ? (int) ($brightness ?? 50)
                : 100,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where('user_id', $this->user()?->id ?? 0),
            ],
            'type' => ['required', Rule::in(['switch', 'dimmer'])],
            'status' => ['required', Rule::in(['on', 'off'])],
            'brightness' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }
}

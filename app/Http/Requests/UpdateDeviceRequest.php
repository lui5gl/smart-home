<?php

namespace App\Http\Requests;

use App\Models\Area;
use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Device|null $device */
        $device = $this->route('device');

        return $device !== null && $this->user()?->is($device->user);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $areaId = $this->input('area_id');
        $brightness = $this->input('brightness');
        $type = $this->input('type');
        $user = $this->user();
        $resolvedLocation = null;
        $resolvedLocationId = null;
        $resolvedAreaId = null;

        if ($user !== null && filled($areaId)) {
            /** @var Area|null $area */
            $area = $user->areas()->with('location')->whereKey($areaId)->first();

            if ($area !== null) {
                $resolvedAreaId = $area->id;
                $resolvedLocationId = $area->location_id;
                $resolvedLocation = $area->name;
            }
        }

        $this->merge([
            'name' => is_string($name) ? trim($name) : $name,
            'location' => $resolvedLocation,
            'location_id' => $resolvedLocationId,
            'area_id' => $resolvedAreaId,
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
            'location_id' => ['nullable', 'integer'],
            'area_id' => [
                'required',
                'integer',
                Rule::exists('areas', 'id')->where('user_id', $this->user()?->id ?? 0),
            ],
            'type' => ['required', Rule::in(['switch', 'dimmer'])],
            'status' => ['required', Rule::in(['on', 'off'])],
            'brightness' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }
}

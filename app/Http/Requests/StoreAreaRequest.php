<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAreaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('user_id', $this->user()?->id ?? 0),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('areas', 'name')->where(fn ($query) => $query
                    ->where('user_id', $this->user()?->id ?? 0)
                    ->where('location_id', $this->input('location_id'))),
            ],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreArrivalAlertRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'stop_id' => ['required', 'string', 'max:255'],
            'route_id' => ['required', 'string', 'max:255'],
            'direction_id' => ['required', 'integer', 'in:0,1'],
            'trip_id' => ['required', 'string', 'max:255'],
            'estimated_arrival_time' => ['required', 'date'],
            'threshold_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:30'],
            'device_token' => ['required', 'string', 'max:255'],
            'locale' => ['sometimes', 'nullable', 'string', 'in:en,pt,es'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDriverApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $documentRules = ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];
        $photoRules = ['file', 'mimes:jpg,jpeg,png', 'max:5120'];

        return [
            'full_name' => ['required', 'string', 'max:150'],
            'mobile_number' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9\s-]{7,15}$/'],
            'whatsapp_number' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s-]{7,15}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'city' => ['required', 'string', 'in:'.implode(',', array_keys(trans('driver_application.cities')))],
            'service_type' => ['required', 'in:taxi,delivery'],

            'national_id_number' => ['required', 'string', 'max:60'],
            'driving_license_number' => ['required', 'string', 'max:60'],
            'vehicle_type' => ['required', 'string', 'in:'.implode(',', array_keys(trans('driver_application.vehicle_types')))],
            'vehicle_brand' => ['required', 'string', 'max:60'],
            'vehicle_model' => ['required', 'string', 'max:60'],
            'vehicle_year' => ['required', 'integer', 'min:1980', 'max:'.(now()->year + 1)],
            'plate_number' => ['required', 'string', 'max:30'],

            'national_id_front' => array_merge(['required'], $documentRules),
            'driving_license_file' => array_merge(['required'], $documentRules),
            'vehicle_registration_file' => array_merge(['required'], $documentRules),
            'personal_photo' => array_merge(['nullable'], $photoRules),
            'vehicle_photo' => array_merge(['nullable'], $photoRules),

            'confirmed_information_correct' => ['required', 'accepted'],
            'agreed_terms' => ['required', 'accepted'],
        ];
    }
}

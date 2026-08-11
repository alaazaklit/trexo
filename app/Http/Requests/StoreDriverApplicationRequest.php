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
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'mobile_number' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9\s-]{7,15}$/'],
            'whatsapp_number' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s-]{7,15}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'city' => ['required', 'string', 'in:'.implode(',', array_keys(trans('driver_application.cities')))],
            // Requires the plate symbol (1-3 letters) alongside the number,
            // e.g. "A 123456" — matches the field's placeholder example.
            'plate_number' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z]{1,3}[\s\-]?\d{3,6}$/'],

            // Hidden on the public form for now — not needed yet.
            // 'service_type' => ['required', 'in:taxi,delivery,bus'],

            // 'national_id_number' => ['required', 'string', 'max:60'],
            // 'driving_license_number' => ['required', 'string', 'max:60'],
            // 'vehicle_type' => ['required', 'string', 'in:'.implode(',', array_keys(trans('driver_application.vehicle_types')))],
            // 'vehicle_brand' => ['required', 'string', 'max:60'],
            // 'vehicle_model' => ['required', 'string', 'max:60'],
            // 'vehicle_year' => ['required', 'integer', 'min:1980', 'max:'.(now()->year + 1)],

            // Document uploads dropped from the public form on purpose — a
            // first-launch driver asked to hand over ID/license/registration
            // photos before they've even seen the app work tends to bail.
            // Collected later (in-app, or by a human follow-up call) instead
            // of gating the initial signup on them.
            // 'national_id_front' => array_merge(['required'], $documentRules),
            // 'driving_license_file' => array_merge(['required'], $documentRules),
            // 'vehicle_registration_file' => array_merge(['required'], $documentRules),
            // 'personal_photo' => array_merge(['nullable'], $photoRules),
            // 'vehicle_photo' => array_merge(['nullable'], $photoRules),

            // 'confirmed_information_correct' => ['required', 'accepted'],
            // Checkbox removed from the public form too — consent is implied
            // by submitting; the column is left at its default (false).
            // 'agreed_terms' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'plate_number.regex' => trans('driver_application.fields.plate_number_format_error'),
        ];
    }
}

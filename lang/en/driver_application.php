<?php

return [
    'title' => 'Become a Driver',
    'subtitle' => 'A quick, simple application — most drivers finish in under 2 minutes.',

    'progress' => [
        'step' => 'Step :current of :total',
    ],

    'steps' => [
        1 => [
            'title' => 'Personal Information',
            'description' => 'Tell us a bit about yourself.',
        ],
        2 => [
            'title' => 'Driver Information',
            'description' => 'Your license and vehicle details.',
        ],
        3 => [
            'title' => 'Upload Documents',
            'description' => 'Just the essentials — you can add more later if needed.',
        ],
    ],

    'fields' => [
        'full_name' => 'Full Name',
        'mobile_number' => 'Mobile Number',
        'whatsapp_number' => 'WhatsApp Number',
        'email' => 'Email',
        'city' => 'City',
        'service_type' => 'Service Type',
        'national_id_number' => 'National ID Number',
        'driving_license_number' => 'Driving License Number',
        'vehicle_type' => 'Vehicle Type',
        'vehicle_brand' => 'Vehicle Brand',
        'vehicle_model' => 'Vehicle Model',
        'vehicle_year' => 'Vehicle Year',
        'plate_number' => 'Plate Number',
        'national_id_front' => 'National ID (Front)',
        'driving_license_file' => 'Driving License',
        'vehicle_registration_file' => 'Vehicle Registration',
        'personal_photo' => 'Personal Photo',
        'vehicle_photo' => 'Vehicle Photo',
    ],

    'optional' => 'optional',

    'service_types' => [
        'taxi' => 'Taxi',
        'delivery' => 'Delivery',
    ],

    'vehicle_types' => [
        'car' => 'Car',
        'van' => 'Van',
        'motorcycle' => 'Motorcycle',
        'tuk_tuk' => 'Tuk-Tuk',
        'truck' => 'Truck',
    ],

    'cities' => [
        'beirut' => 'Beirut',
        'tripoli' => 'Tripoli',
        'sidon' => 'Sidon (Saida)',
        'tyre' => 'Tyre (Sour)',
        'jounieh' => 'Jounieh',
        'zahle' => 'Zahle',
        'baalbek' => 'Baalbek',
        'byblos' => 'Byblos (Jbeil)',
        'nabatieh' => 'Nabatieh',
        'aley' => 'Aley',
        'batroun' => 'Batroun',
        'zgharta' => 'Zgharta',
        'chouf' => 'Chouf',
        'keserwan' => 'Keserwan',
        'metn' => 'Metn',
        'akkar' => 'Akkar',
        'bekaa' => 'Bekaa',
    ],

    'upload' => [
        'hint' => 'JPG, PNG, or PDF — up to 5MB',
        'choose_file' => 'Choose File',
        'no_file' => 'No file chosen',
    ],

    'agreements' => [
        'information_correct' => 'I confirm that the information provided is correct.',
        'terms_and_privacy' => 'I agree to the :terms and :privacy.',
        'terms_link' => 'Terms & Conditions',
        'privacy_link' => 'Privacy Policy',
    ],

    'buttons' => [
        'next' => 'Next',
        'back' => 'Back',
        'submit' => 'Submit Application',
    ],

    'success' => [
        'title' => 'Application Received!',
        'message' => 'Your application has been received successfully. Our team will review it and contact you soon.',
        'back_home' => 'Back to Home',
    ],
];

<?php

return [
    'title' => 'انضم كسائق',
    'subtitle' => 'طلب سريع وبسيط — معظم السائقين ينهونه في أقل من دقيقتين.',

    'progress' => [
        'step' => 'الخطوة :current من :total',
    ],

    'steps' => [
        1 => [
            'title' => 'المعلومات الشخصية',
            'description' => 'أخبرنا القليل عن نفسك.',
        ],
        2 => [
            'title' => 'معلومات السائق',
            'description' => 'تفاصيل رخصتك ومركبتك.',
        ],
        3 => [
            'title' => 'رفع الوثائق',
            'description' => 'فقط الأساسيات — يمكنك إضافة المزيد لاحقاً عند الحاجة.',
        ],
    ],

    'fields' => [
        'full_name' => 'الاسم الكامل',
        'mobile_number' => 'رقم الهاتف',
        'whatsapp_number' => 'رقم الواتساب',
        'email' => 'البريد الإلكتروني',
        'city' => 'المدينة',
        'service_type' => 'نوع الخدمة',
        'national_id_number' => 'رقم الهوية الوطنية',
        'driving_license_number' => 'رقم رخصة القيادة',
        'vehicle_type' => 'نوع المركبة',
        'vehicle_brand' => 'ماركة المركبة',
        'vehicle_model' => 'موديل المركبة',
        'vehicle_year' => 'سنة الصنع',
        'plate_number' => 'رقم اللوحة',
        'national_id_front' => 'الهوية الوطنية (الوجه الأمامي)',
        'driving_license_file' => 'رخصة القيادة',
        'vehicle_registration_file' => 'دفتر سيارة المركبة',
        'personal_photo' => 'صورة شخصية',
        'vehicle_photo' => 'صورة المركبة',
    ],

    'optional' => 'اختياري',

    'service_types' => [
        'taxi' => 'تكسي',
        'delivery' => 'توصيل',
    ],

    'vehicle_types' => [
        'car' => 'سيارة',
        'van' => 'فان',
        'motorcycle' => 'دراجة نارية',
        'tuk_tuk' => 'توكتوك',
        'truck' => 'شاحنة',
    ],

    'cities' => [
        'beirut' => 'بيروت',
        'tripoli' => 'طرابلس',
        'sidon' => 'صيدا',
        'tyre' => 'صور',
        'jounieh' => 'جونية',
        'zahle' => 'زحلة',
        'baalbek' => 'بعلبك',
        'byblos' => 'جبيل',
        'nabatieh' => 'النبطية',
        'aley' => 'عاليه',
        'batroun' => 'البترون',
        'zgharta' => 'زغرتا',
        'chouf' => 'الشوف',
        'keserwan' => 'كسروان',
        'metn' => 'المتن',
        'akkar' => 'عكار',
        'bekaa' => 'البقاع',
    ],

    'upload' => [
        'hint' => 'JPG أو PNG أو PDF — حتى 5 ميغابايت',
        'choose_file' => 'اختر ملفاً',
        'no_file' => 'لم يتم اختيار ملف',
    ],

    'agreements' => [
        'information_correct' => 'أؤكد أن المعلومات المقدمة صحيحة.',
        'terms_and_privacy' => 'أوافق على :terms و :privacy.',
        'terms_link' => 'الشروط والأحكام',
        'privacy_link' => 'سياسة الخصوصية',
    ],

    'buttons' => [
        'next' => 'التالي',
        'back' => 'رجوع',
        'submit' => 'إرسال الطلب',
    ],

    'success' => [
        'title' => 'تم استلام طلبك!',
        'message' => 'تم استلام طلبك بنجاح. سيقوم فريقنا بمراجعته والتواصل معك قريباً.',
        'back_home' => 'العودة إلى الرئيسية',
    ],
];

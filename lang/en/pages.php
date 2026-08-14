<?php

return [
    'privacy' => [
        'title' => 'Privacy Policy',
        'updated_at' => 'Last updated: :date',
        'sections' => [
            [
                'heading' => 'Introduction',
                'body' => 'This Privacy Policy explains how Trexo ("we", "us") collects, uses, and protects the personal data of riders, senders, and drivers who use the Trexo mobile apps and website. By creating an account or using our services, you agree to the practices described below.',
            ],
            [
                'heading' => 'Information We Collect',
                'body' => 'Account information you provide, such as your name, phone number, email address, and profile photo. Trip and delivery information, including pickup and drop-off locations, fare amounts, and payment method type (we do not store your card or bank details). For drivers, verification information such as national ID, driver\'s license, vehicle registration, insurance documents, and vehicle details. Device and usage information, such as device model, operating system, IP address, app version, and push-notification token. Communications you send us, such as support requests.',
            ],
            [
                'heading' => 'Location Data',
                'body' => 'Trexo collects precise location data while the app is in use to match riders and senders with nearby drivers, calculate routes and fares, and show live trip tracking. Drivers who are online may also share location in the background so nearby requests can reach them. You can disable location access from your device settings, but this will limit or prevent use of the app.',
            ],
            [
                'heading' => 'How We Use Your Information',
                'body' => 'We use your information to provide and improve our taxi and delivery services, connect you with drivers, process payments, verify driver identity and documents, prevent fraud, send service and account notifications, and communicate with you about your account and trips.',
            ],
            [
                'heading' => 'Third-Party Services',
                'body' => 'We rely on trusted third-party providers to operate Trexo, including Google Firebase for push notifications and app performance, Google Maps for mapping and navigation, and Twilio and WhatsApp Business for phone verification (OTP). These providers process data on our behalf under their own privacy and security terms.',
            ],
            [
                'heading' => 'Sharing Your Information',
                'body' => 'We share only the information necessary with drivers or riders to complete a trip or delivery, such as name, phone number, and location. We may also share information with service providers who support our operations (Third-Party Services above), or when required by law. We do not sell your personal information to third parties.',
            ],
            [
                'heading' => 'Data Retention',
                'body' => 'We retain your personal data for as long as your account is active or as needed to provide our services. Driver verification documents and trip records may be kept for longer where required for legal, tax, safety, or fraud-prevention purposes, after which they are deleted or anonymized.',
            ],
            [
                'heading' => 'Data Security',
                'body' => 'We use reasonable technical and organizational measures, including encrypted connections, to protect your information from unauthorized access, loss, or misuse.',
            ],
            [
                'heading' => 'Account and Data Deletion',
                'body' => 'You can request deletion of your account and personal data at any time from within the app (Account Settings > Delete Account) or by emailing support@trexo.com. Once processed, your personal data is deleted or anonymized, except where we must retain certain records to comply with legal, tax, or safety obligations.',
            ],
            [
                'heading' => 'Children\'s Privacy',
                'body' => 'Trexo is not directed at children, and we do not knowingly collect personal data from anyone under 18 years of age. If you believe a child has provided us with personal data, please contact us so we can remove it.',
            ],
            [
                'heading' => 'Your Rights',
                'body' => 'You may request access to, correction of, or deletion of your personal data at any time by contacting our support team.',
            ],
            [
                'heading' => 'Changes to This Policy',
                'body' => 'We may update this Privacy Policy from time to time. Continued use of the app after changes means you accept the updated policy.',
            ],
            [
                'heading' => 'Contact Us',
                'body' => 'If you have questions about this Privacy Policy or wish to exercise your data rights, please reach out through our Contact page or email support@trexo.com.',
            ],
        ],
    ],

    'delete_account' => [
        'title' => 'Delete Your Account',
        'intro' => 'Enter the phone number on your Trexo account. We\'ll send a verification code via WhatsApp to confirm it\'s you before deleting your account and personal data.',
        'phone_label' => 'Phone number',
        'phone_placeholder' => 'e.g. 71234567',
        'send_code' => 'Send verification code',
        'otp_sent' => 'A verification code was sent via WhatsApp.',
        'otp_label' => 'Verification code',
        'otp_placeholder' => '6-digit code',
        'otp_hint' => 'A code was sent via WhatsApp to :phone. It expires in 10 minutes.',
        'confirm_button' => 'Permanently delete my account',
        'resend' => 'Didn\'t get it? Send again',
        'warning' => 'This cannot be undone. Your name, email, phone number, and profile photo will be permanently removed. Trip records are kept in anonymized form only where required by law.',
        'deleted_title' => 'Your account has been deleted',
        'deleted_body' => 'Your account and personal data have been removed. If you have questions, contact support@trexo.com.',
        'errors' => [
            'not_found' => 'No Trexo account is registered with this phone number.',
            'wait' => 'Please wait :seconds seconds before requesting another code.',
            'invalid_otp' => 'That code is incorrect or has expired.',
        ],
    ],

    'terms' => [
        'title' => 'Terms & Conditions',
        'updated_at' => 'Last updated: :date',
        'sections' => [
            [
                'heading' => 'Acceptance of Terms',
                'body' => 'By using Trexo, you agree to these Terms & Conditions. If you do not agree, please do not use our services.',
            ],
            [
                'heading' => 'Using Our Services',
                'body' => 'You must provide accurate information when booking a ride or delivery and treat drivers and other users respectfully.',
            ],
            [
                'heading' => 'Driver Applications',
                'body' => 'Submitting a driver application does not guarantee approval. Trexo reserves the right to request additional documents or verification before or after approval.',
            ],
            [
                'heading' => 'Payments',
                'body' => 'Fares and delivery fees are calculated based on distance, time, and service type, and are shown before you confirm a booking.',
            ],
            [
                'heading' => 'Limitation of Liability',
                'body' => 'Trexo connects riders, senders, and drivers, and is not liable for the conduct of independent drivers beyond what is required by applicable Lebanese law.',
            ],
            [
                'heading' => 'Changes to These Terms',
                'body' => 'We may update these Terms from time to time. Continued use of the app after changes means you accept the updated Terms.',
            ],
        ],
    ],
];

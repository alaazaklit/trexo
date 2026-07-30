<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client();
    }

    /**
     * Send a one-time verification code to a phone number via the Meta
     * WhatsApp Cloud API, using a pre-approved "Authentication" template
     * that accepts a single body variable (the code).
     *
     * @param string $internationalPhone Phone number with country code, digits only (no leading "+").
     */
    public function sendOtp(string $internationalPhone, string $code): bool
    {
        $token = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');

        if (empty($token) || empty($phoneNumberId)) {
            Log::warning('WhatsApp OTP not sent: WHATSAPP_ACCESS_TOKEN / WHATSAPP_PHONE_NUMBER_ID not configured.');
            return false;
        }

        try {
            $response = $this->client->post("https://graph.facebook.com/v20.0/{$phoneNumberId}/messages", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => $internationalPhone,
                    'type' => 'template',
                    'template' => [
                        'name' => config('services.whatsapp.otp_template'),
                        'language' => [
                            'code' => config('services.whatsapp.otp_template_lang'),
                        ],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $code],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (GuzzleException $e) {
            Log::error('Failed to send WhatsApp OTP', [
                'phone' => $internationalPhone,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

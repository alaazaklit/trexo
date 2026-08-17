<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UnlimitedMessagingService
{
    /**
     * Send a WhatsApp text message via the UnlimitedMessaging REST API
     * (POST /message, Bearer auth, recipient in E.164 format).
     *
     * @param string $phone Phone number in any format accepted by User::normalizePhone()
     *                       (local Lebanese digits, with/without +961, etc).
     */
    public function sendWhatsAppMessage(string $phone, string $message): bool
    {
        $apiUrl = config('services.unlimited_messaging.api_url');
        $token = config('services.unlimited_messaging.api_token');

        if (empty($apiUrl) || empty($token)) {
            Log::warning('WhatsApp message not sent: UnlimitedMessaging is not configured.');
            return false;
        }

        $recipient = '+961' . User::normalizePhone($phone);

        if (!preg_match('/^\+?\d{6,15}$/', $recipient)) {
            Log::warning('WhatsApp message not sent: invalid recipient phone number.');
            return false;
        }

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->post(rtrim($apiUrl, '/') . '/message', [
                    'recipient' => $recipient,
                    'text' => $message,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('UnlimitedMessaging request failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            return false;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('UnlimitedMessaging connection/timeout error', [
                'message' => $e->getMessage(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('UnlimitedMessaging unexpected error', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

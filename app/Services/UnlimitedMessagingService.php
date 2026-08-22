<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use TCG\Voyager\Models\Setting;

class UnlimitedMessagingService
{
    /**
     * Send a WhatsApp text message via the UnlimitedMessaging REST API
     * (POST /message, Bearer auth, recipient in E.164 format).
     *
     * Which number it sends from (the API's 'simId') is admin-editable —
     * see the 'messaging.whatsapp_sim_id'/'messaging.whatsapp_backup_sim_id'
     * Settings, edited at /admin/settings (group "Messaging") — instead of
     * only ever configurable via UNLIMITED_MESSAGING_SIM_ID in .env. If the
     * primary sim's send fails (e.g. that number got blocked/banned), this
     * automatically retries once on the backup sim before giving up, so a
     * blocked number doesn't stop OTPs from arriving entirely while someone
     * notices and fixes the primary Setting.
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

        $primarySimId = $this->primarySimId();

        if ($this->attemptSend($apiUrl, $token, $recipient, $message, $primarySimId)) {
            return true;
        }

        $backupSimId = $this->backupSimId();
        if (empty($backupSimId) || $backupSimId === $primarySimId) {
            return false;
        }

        Log::warning('UnlimitedMessaging: primary sim send failed, retrying on backup sim.');

        return $this->attemptSend($apiUrl, $token, $recipient, $message, $backupSimId);
    }

    private function primarySimId(): ?string
    {
        $setting = Setting::where('key', 'messaging.whatsapp_sim_id')->value('value');

        return !empty($setting) ? $setting : config('services.unlimited_messaging.sim_id');
    }

    private function backupSimId(): ?string
    {
        return Setting::where('key', 'messaging.whatsapp_backup_sim_id')->value('value') ?: null;
    }

    private function attemptSend(string $apiUrl, string $token, string $recipient, string $message, ?string $simId): bool
    {
        try {
            $payload = [
                'recipient' => $recipient,
                'text' => $message,
            ];
            // Required once more than one WhatsApp number is registered on
            // the account — otherwise the API rejects the request with
            // "Multiple SIMs available, please specify a simId".
            if (!empty($simId)) {
                $payload['simId'] = $simId;
            }

            $response = Http::withToken($token)
                ->timeout(10)
                ->post(rtrim($apiUrl, '/') . '/message', $payload);

            if ($response->successful()) {
                return true;
            }

            Log::error('UnlimitedMessaging request failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
                'simId' => $simId,
            ]);

            return false;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('UnlimitedMessaging connection/timeout error', [
                'message' => $e->getMessage(),
                'simId' => $simId,
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('UnlimitedMessaging unexpected error', [
                'message' => $e->getMessage(),
                'simId' => $simId,
            ]);

            return false;
        }
    }
}

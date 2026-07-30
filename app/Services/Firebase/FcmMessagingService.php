<?php
namespace App\Services\Firebase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;

class FcmMessagingService
{
    protected $client;
    protected $accessToken;
    protected $projectId;

    public function __construct()
    {
        // Path to your Firebase service account key
        $serviceAccountFile = storage_path('firebase/allo-delivery-4bcb0-firebase-adminsdk-fbsvc-f0c988e59e.json');

        // Firebase Project ID
        $this->projectId = "allo-delivery-4bcb0";

        // Authenticate and set access token
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];

        $credentials = new ServiceAccountCredentials($scopes, $serviceAccountFile);

        $this->accessToken = $credentials->fetchAuthToken()['access_token'];


        // Initialize Guzzle Client
        $this->client = new Client();
    }




    // How long an undelivered push is worth holding onto before FCM just
    // drops it. Without this, messages sent while a device is offline (or
    // the app is uninstalled) queue for FCM's own default lifetime — up to
    // 4 weeks — and all land at once the moment the device reconnects
    // (e.g. after a reinstall), flooding the user with a burst of stale
    // notifications. A day is plenty for these (order/reservation status
    // updates), and short enough that a reinstall doesn't replay weeks of
    // history.
    private const MESSAGE_TTL_SECONDS = 86400;

    public function sendNotification($tokens,$title,$body,$section = 'orders')
    {
        $results = [];

        foreach($tokens as $deviceToken){

            if (empty($deviceToken['fcm_token'])) {
                Log::warning('FCM send skipped: empty fcm_token', ['user_id' => $deviceToken['user_id'] ?? null]);
                $results[] = ['success' => false, 'user_id' => $deviceToken['user_id'] ?? null, 'error' => 'empty fcm_token'];
                continue;
            }

            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

            // How many notifications this recipient now has unread, so the
            // client can set its bell badge / home-screen icon badge from
            // this single push without a separate round trip.
            $recipientUserId = $deviceToken['user_id'] ?? null;
            $unreadBadgeCount = $recipientUserId
                ? DB::table('notifications')->where('user_id', $recipientUserId)->where('is_read', 0)->count()
                : 0;

            // Collapsing on section+ref_id means if several updates about
            // the same order/reservation are still queued for a device that
            // was offline, only the latest one is actually delivered instead
            // of replaying the whole history once it reconnects.
            $collapseKey = $section . '_' . ($deviceToken['ref_id'] ?? '0');

            $payload = [
                'message' => [
                    'token' => $deviceToken['fcm_token'],
                    // Data-only — no `notification` block. With one, the OS
                    // auto-displays and auto-owns the tray entry whenever the
                    // app is backgrounded/killed, leaving the client no way
                    // to dismiss it once read in-app or to keep the icon
                    // badge in sync. Showing it and clearing it is now
                    // entirely the client's job (see firebase_notifications.dart).
                    'data' => [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'title' => (string) $title,
                        'body' => (string) $body,
                        'status' => (string) ($deviceToken['status'] ?? 'done'),
                        'ref_id' =>(string) $deviceToken['ref_id'],
                        'user_id' =>(string) $deviceToken['user_id'],
                        'section' => $section,
                        'notification_id' => (string) ($deviceToken['notification_id'] ?? ''),
                        'badge' => (string) $unreadBadgeCount,
                    ],
                    'android' => [
                        'priority' => 'high',
                        'ttl' => self::MESSAGE_TTL_SECONDS . 's',
                        'collapse_key' => $collapseKey,
                    ],
                    'apns' => [
                        'headers' => [
                            'apns-priority' => '10',
                            'apns-expiration' => (string) (time() + self::MESSAGE_TTL_SECONDS),
                            'apns-collapse-id' => $collapseKey,
                        ],
                        'payload' => ['aps' => ['content-available' => 1]],
                    ],
                ]
            ];


            try {
                $response = $this->client->post($url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $payload,
                ]);

                Log::info('FCM send succeeded', [
                    'user_id' => $deviceToken['user_id'] ?? null,
                    'title' => $title,
                    'response' => json_decode($response->getBody()->getContents(), true),
                ]);
                $results[] = ['success' => true, 'user_id' => $deviceToken['user_id'] ?? null];
            } catch (\Exception $e) {
                Log::error('FCM send failed', [
                    'user_id' => $deviceToken['user_id'] ?? null,
                    'title' => $title,
                    'fcm_token_prefix' => substr($deviceToken['fcm_token'], 0, 12),
                    'error' => $e->getMessage(),
                ]);
                $results[] = ['success' => false, 'user_id' => $deviceToken['user_id'] ?? null, 'error' => $e->getMessage()];
            }
        }

        return response()->json(['results' => $results]);
    }

    // Sends a data-only message — no `notification` block, so the OS won't
    // auto-display anything and the client's local-notification banner
    // won't fire either. Used for things the app wants to react to with its
    // own custom UI instead of a generic banner, e.g. an incoming-call
    // screen instead of a passive "you have a notification" toast.
    public function sendDataMessage(string $fcmToken, array $data): bool
    {
        if (empty($fcmToken)) {
            Log::warning('FCM data message skipped: empty fcm_token');
            return false;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $stringData = array_map(static fn ($value) => (string) $value, $data);

        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'message' => [
                        'token' => $fcmToken,
                        'data' => $stringData,
                        'android' => [
                            'priority' => 'high',
                        ],
                        'apns' => [
                            'headers' => ['apns-priority' => '10'],
                            'payload' => ['aps' => ['content-available' => 1]],
                        ],
                    ],
                ],
            ]);

            Log::info('FCM data message succeeded', [
                'section' => $data['section'] ?? null,
                'response' => json_decode($response->getBody()->getContents(), true),
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('FCM data message failed', [
                'section' => $data['section'] ?? null,
                'fcm_token_prefix' => substr($fcmToken, 0, 12),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function insertNoitifcations($arr,$section,$title,$message){
        $notifications = [];

    foreach ($arr as $ar) {
       $notifications[] = [
        'user_id' => $ar['user_id'],
        'ref_id' => $ar['ref_id'],
        'section' => $section,
        'title' => $title,
        'message' => $message,
        'data' => json_encode($ar),
        'is_read' => 0,
        'created_at' => now()
    ];
}

if(!empty($notifications)){DB::table('notifications')->insert($notifications);}
// Insert all at once

    }
}

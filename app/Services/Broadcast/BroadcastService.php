<?php

namespace App\Services\Broadcast;

use App\Models\Broadcast;
use App\Models\Driver;
use App\Models\User;
use App\Services\Firebase\FcmMessagingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BroadcastService
{
    public function __construct(private readonly FcmMessagingService $fcm)
    {
    }

    public function send(string $title, string $message, ?string $accountType, ?string $serviceType, ?int $sentBy): Broadcast
    {
        $recipients = $this->resolveRecipients($accountType, $serviceType);

        if ($recipients->isNotEmpty()) {
            $notifications = $recipients->map(fn (User $user) => [
                'user_id' => $user->id,
                'ref_id' => 0,
                'section' => 'broadcast',
                'title' => $title,
                'message' => $message,
                'data' => json_encode(['section' => 'broadcast']),
                'is_read' => 0,
                'created_at' => now(),
            ])->all();
            DB::table('notifications')->insert($notifications);

            $tokens = $recipients
                ->filter(fn (User $user) => !empty($user->fcm_token))
                ->map(fn (User $user) => [
                    'fcm_token' => $user->fcm_token,
                    'user_id' => $user->id,
                    'ref_id' => 0,
                ])
                ->values()
                ->all();

            if (!empty($tokens)) {
                $this->fcm->sendNotification($tokens, $title, $message, 'broadcast');
            }
        }

        return Broadcast::create([
            'title' => $title,
            'message' => $message,
            'account_type' => $accountType,
            'service_type' => $serviceType,
            'recipient_count' => $recipients->count(),
            'sent_by' => $sentBy,
        ]);
    }

    /** @return Collection<int, User> */
    public function resolveRecipients(?string $accountType, ?string $serviceType): Collection
    {
        // Service type (taxi/delivery/bus) only exists as a driver attribute
        // (vehicle category capability, or school-bus opt-in status) —
        // sellers have no such attribute, so selecting one always scopes
        // the audience to drivers regardless of the account-type filter.
        if ($serviceType !== null) {
            if ($accountType === 'seller') {
                return collect();
            }

            $query = Driver::query()->with('user');

            match ($serviceType) {
                'taxi' => $query->whereHas('vehicleCategory', fn ($q) => $q->where('supports_taxi', true)),
                'delivery' => $query->whereHas('vehicleCategory', fn ($q) => $q->where('supports_delivery', true)),
                'bus' => $query->where('school_bus_status', 'approved'),
                default => null,
            };

            return $query->get()
                ->pluck('user')
                ->filter(fn (?User $user) => $user !== null && $user->account_status === 'active');
        }

        // whereNotNull('type') keeps this scoped to actual app users
        // (drivers/sellers) even when no filter is picked — admin/staff
        // Voyager accounts share this same `users` table but have no `type`.
        $query = User::query()->where('account_status', 'active')->whereNotNull('type');
        if ($accountType !== null) {
            $query->where('type', $accountType);
        }

        return $query->get();
    }
}

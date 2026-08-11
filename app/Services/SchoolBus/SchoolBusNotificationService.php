<?php

namespace App\Services\SchoolBus;

use App\Models\Driver;
use App\Models\SchoolBusSubscription;
use App\Models\User;
use App\Services\Firebase\FcmMessagingService;
use Illuminate\Support\Facades\DB;

class SchoolBusNotificationService
{
    // Titles/messages for the driver's quick-action buttons (keys are what
    // the Flutter app sends as `event` on POST .../subscriptions/{id}/event),
    // plus `proximity_alert`, sent automatically by the
    // school-bus:check-proximity command instead of a driver tap.
    public const EVENTS = [
        'on_the_way' => ['title' => 'Bus is on the way', 'message' => 'The school bus is on its way to the pickup area.'],
        'arrived' => ['title' => 'Bus has arrived', 'message' => 'The school bus has arrived at the pickup area.'],
        'boarded' => ['title' => 'Student boarded the bus', 'message' => 'Your child has boarded the bus.'],
        'arrived_at_school' => ['title' => 'Student arrived at school', 'message' => 'Your child has arrived at school.'],
        'left_school' => ['title' => 'Student left school', 'message' => 'Your child has left school and is on the bus home.'],
        'arrived_home' => ['title' => 'Student arrived home', 'message' => 'Your child has arrived home.'],
        'proximity_alert' => ['title' => 'Bus arriving soon', 'message' => 'The school bus is about 2 minutes away. Please have your child ready.'],
    ];

    public function __construct(private readonly FcmMessagingService $fcm)
    {
    }

    public function sendEvent(SchoolBusSubscription $subscription, string $event): void
    {
        if (!array_key_exists($event, self::EVENTS)) {
            throw new \InvalidArgumentException("Invalid school bus event: {$event}");
        }

        $copy = self::EVENTS[$event];
        $this->notifyParent($subscription, $event, $copy['title'], $copy['message']);
    }

    public function notifyParent(SchoolBusSubscription $subscription, string $status, string $title, string $message): void
    {
        $recipient = $subscription->relationLoaded('parentUser')
            ? $subscription->parentUser
            : User::find($subscription->parent_user_id);

        if (!$recipient) {
            return;
        }

        $notificationId = DB::table('notifications')->insertGetId([
            'user_id' => $recipient->id,
            'ref_id' => $subscription->id,
            'section' => 'school_bus',
            'title' => $title,
            'message' => $message,
            'data' => json_encode([
                'subscription_id' => $subscription->id,
                'status' => $status,
            ]),
            'is_read' => 0,
            'created_at' => now(),
        ]);

        if (!empty($recipient->fcm_token)) {
            $this->fcm->sendNotification([
                [
                    'fcm_token' => $recipient->fcm_token,
                    'user_id' => $recipient->id,
                    'ref_id' => $subscription->id,
                    'status' => $status,
                    'notification_id' => $notificationId,
                ],
            ], $title, $message, 'school_bus');
        }
    }

    // Mirrors notifyParent() above, but resolves the recipient off the
    // subscription's driver instead — used when a new request comes in, so
    // the driver can confirm/reject it before it goes anywhere near the
    // parent-facing statuses in EVENTS.
    public function notifyDriver(SchoolBusSubscription $subscription, string $status, string $title, string $message): void
    {
        $driver = $subscription->relationLoaded('driver')
            ? $subscription->driver
            : Driver::find($subscription->driver_id);
        $recipient = $driver?->user;

        if (!$recipient) {
            return;
        }

        $notificationId = DB::table('notifications')->insertGetId([
            'user_id' => $recipient->id,
            'ref_id' => $subscription->id,
            'section' => 'school_bus',
            'title' => $title,
            'message' => $message,
            'data' => json_encode([
                'subscription_id' => $subscription->id,
                'status' => $status,
            ]),
            'is_read' => 0,
            'created_at' => now(),
        ]);

        if (!empty($recipient->fcm_token)) {
            $this->fcm->sendNotification([
                [
                    'fcm_token' => $recipient->fcm_token,
                    'user_id' => $recipient->id,
                    'ref_id' => $subscription->id,
                    'status' => $status,
                    'notification_id' => $notificationId,
                ],
            ], $title, $message, 'school_bus');
        }
    }

    // "New request" is the one driver-facing notification whose copy needs
    // localizing — everyone else in EVENTS is a fixed, translation-free
    // constant, but this one embeds the parent/student/pickup-area names at
    // send time. users.language is synced opportunistically from a couple
    // of driver-facing endpoints (see SchoolBusRouteController::
    // syncLanguage()) since the backend has no other way to know the
    // driver's app language when this fires.
    public function notifyDriverNewRequest(SchoolBusSubscription $subscription, string $parentName, string $studentName, string $pickupArea): void
    {
        $driver = $subscription->relationLoaded('driver')
            ? $subscription->driver
            : Driver::find($subscription->driver_id);
        $isArabic = $driver?->user?->language === 'ar';

        if ($isArabic) {
            $title = 'طلب باص مدرسي جديد';
            $message = "{$parentName} طلب مقعدًا لـ {$studentName} - نقطة الانطلاق: {$pickupArea}.";
        } else {
            $title = 'New School Bus Request';
            $message = "{$parentName} requested a seat for {$studentName} — pickup at {$pickupArea}.";
        }

        $this->notifyDriver($subscription, 'new_request', $title, $message);
    }

    // "Accepted" also needs localizing (embeds the driver's name, and
    // doubles as a Premium upsell) — users.language is synced
    // opportunistically from mine()/counts()/status() since the backend has
    // no other way to know a user's app language when this fires.
    public function notifyParentAccepted(SchoolBusSubscription $subscription, string $driverName): void
    {
        $recipient = $subscription->relationLoaded('parentUser')
            ? $subscription->parentUser
            : User::find($subscription->parent_user_id);
        $isArabic = $recipient?->language === 'ar';

        if ($isArabic) {
            $title = 'تم قبول طلب الاشتراك';
            $message = "الباص المدرسي: {$driverName} وافق على طلب الاشتراك. يمكنك الآن تفعيل خدمة التنبيه المسبق قبل وصول سائق الباص المدرسي.";
        } else {
            $title = 'Subscription Accepted';
            $message = "School bus driver {$driverName} accepted your subscription. You can now activate the early notification service before your driver arrives.";
        }

        $this->notifyParent($subscription, 'active', $title, $message);
    }
}

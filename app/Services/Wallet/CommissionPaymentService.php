<?php

namespace App\Services\Wallet;

use App\Models\CommissionPayment;
use App\Models\Driver;
use App\Models\Wallet;
use App\Services\Firebase\FcmMessagingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// The driver already holds the cash (this app is cash-to-driver) — nothing
// is held or deducted when they submit a payment report, since there's
// nothing of theirs in the wallet to hold. Only an admin *approving* the
// receipt actually reduces what they owe; rejecting leaves the owed amount
// untouched (there's nothing to refund — no money ever moved on submit).
class CommissionPaymentService
{
    public function __construct(private readonly FcmMessagingService $fcm)
    {
    }

    public function submit(Driver $driver, float $amount, UploadedFile $receipt): CommissionPayment
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Invalid payment amount.');
        }

        $fileName = time().'_'.Str::uuid().'.'.$receipt->getClientOriginalExtension();
        Storage::disk('public')->putFileAs('commission_payment_receipts', $receipt, $fileName);

        return CommissionPayment::create([
            'driver_id' => $driver->id,
            'amount' => $amount,
            'status' => 'pending',
            'payment_method' => 'wish_money',
            'receipt_path' => 'commission_payment_receipts/'.$fileName,
        ]);
    }

    public function approve(CommissionPayment $payment): void
    {
        if ($payment->status !== 'pending') {
            throw new \InvalidArgumentException('Only a pending commission payment can be approved.');
        }

        DB::transaction(function () use ($payment) {
            $wallet = Wallet::where('driver_id', $payment->driver_id)->lockForUpdate()->first();
            if ($wallet !== null) {
                // Clamped at 0 rather than allowed to go negative — a driver
                // slightly overpaying (rounding, or paying ahead) shouldn't
                // leave them with a wallet that reads as "Trexo owes them."
                $wallet->update(['commission_owed' => max(0, (float) $wallet->commission_owed - (float) $payment->amount)]);
            }

            $payment->update([
                'status' => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);
        });
    }

    public function reject(CommissionPayment $payment, ?string $reason): void
    {
        if ($payment->status !== 'pending') {
            throw new \InvalidArgumentException('Only a pending commission payment can be rejected.');
        }

        $payment->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);
    }

    // Manual, on-demand — the admin decides when the driver needs a nudge
    // about this specific report, rather than this firing automatically on
    // every status change (approve()/reject() intentionally don't call this
    // themselves, so an admin can silently correct a mistake before telling
    // the driver anything).
    public function notify(CommissionPayment $payment): void
    {
        $payment->loadMissing('driver.user');
        $user = $payment->driver?->user;
        if ($user === null) {
            throw new \InvalidArgumentException('This payment has no linked driver account to notify.');
        }

        // Arabic, matching every other push notification in the app
        // (OrderController etc.) — there's no per-user language preference
        // stored anywhere, so this mirrors the existing app-wide convention
        // rather than introducing a one-off English notification.
        $amount = number_format((float) $payment->amount, 2);
        $title = 'تحديث حالة دفعة العمولة';
        $message = match ($payment->status) {
            'approved' => "تم قبول تقرير دفع العمولة بقيمة \${$amount}. تم تحديث المبلغ المستحق عليك.",
            'rejected' => $payment->rejection_reason
                ? "تم رفض تقرير دفع العمولة بقيمة \${$amount}: {$payment->rejection_reason}"
                : "تم رفض تقرير دفع العمولة بقيمة \${$amount}. يرجى مراجعة الإيصال والمحاولة مرة أخرى.",
            default => "تقرير دفع العمولة بقيمة \${$amount} لا يزال قيد المراجعة.",
        };

        DB::table('notifications')->insert([
            'user_id' => $user->id,
            'ref_id' => $payment->id,
            'section' => 'commission_payment',
            'title' => $title,
            'message' => $message,
            'data' => json_encode(['section' => 'commission_payment', 'status' => $payment->status]),
            'is_read' => 0,
            'created_at' => now(),
        ]);

        if (!empty($user->fcm_token)) {
            $this->fcm->sendNotification([[
                'fcm_token' => $user->fcm_token,
                'user_id' => $user->id,
                'ref_id' => $payment->id,
                'status' => $payment->status,
            ]], $title, $message, 'commission_payment');
        }
    }
}

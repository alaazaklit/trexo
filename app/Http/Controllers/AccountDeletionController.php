<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use App\VerificationCode;
use App\Services\UnlimitedMessagingService;
use App\Services\OtpService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public, no-login-required page for Google Play's "Account Deletion"
 * Data Safety requirement: a web URL where a user can request deletion
 * of their account without the app installed. Verifies phone ownership
 * via the same WhatsApp OTP used for login, then runs the identical
 * anonymize-and-soft-delete path as the in-app API
 * (Api\UsersController::deleteAccount via User::anonymize()).
 */
class AccountDeletionController extends Controller
{
    public function __construct(
        private readonly UnlimitedMessagingService $whatsAppService,
        private readonly OtpService $otpService,
        private readonly RefreshTokenService $refreshTokenService,
    ) {
    }

    public function show(): View
    {
        return view('pages.delete-account');
    }

    public function requestOtp(Request $request): RedirectResponse
    {
        $request->validate(['phone' => 'required']);

        $user = $this->findUserByPhone($request->input('phone'));

        if (!$user) {
            return back()->withInput()->withErrors(['phone' => __('pages.delete_account.errors.not_found')]);
        }

        $normalizedPhone = User::normalizePhone($request->input('phone'));

        $outcome = $this->otpService->requestOtp($user, $normalizedPhone, 'account_deletion', $request, function (string $code) {
            return "Your Trexo verification code is: {$code}";
        });

        if ($outcome['http_status'] !== 200) {
            $errorKey = match ($outcome['reason']) {
                OtpService::REASON_COOLDOWN => __('pages.delete_account.errors.wait', ['seconds' => $outcome['wait_seconds'] ?? 60]),
                OtpService::REASON_LIMIT_DAILY => __('pages.delete_account.errors.too_many_today'),
                OtpService::REASON_GLOBAL_BUSY => __('pages.delete_account.errors.busy'),
                OtpService::REASON_SEND_FAILED => __('pages.delete_account.errors.send_failed'),
                default => __('pages.delete_account.errors.too_many'),
            };

            return back()->withInput()->with('otp_sent_phone', $request->input('phone'))->withErrors(['otp' => $errorKey]);
        }

        return back()
            ->with('otp_sent_phone', $request->input('phone'))
            ->with('status', __('pages.delete_account.otp_sent'));
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate([
            'phone' => 'required',
            'otp' => 'required|numeric|digits:6',
        ]);

        $user = $this->findUserByPhone($request->input('phone'));

        if (!$user) {
            return back()->withInput()->withErrors(['phone' => __('pages.delete_account.errors.not_found')]);
        }

        $verificationCode = VerificationCode::where('user_id', $user->id)
            ->where('code', $request->input('otp'))
            ->where('type', 'account_deletion')
            ->where('used', false)
            ->where('expires_at', '>=', Carbon::now())
            ->orderByDesc('id')
            ->first();

        if (!$verificationCode) {
            return back()
                ->withInput()
                ->with('otp_sent_phone', $request->input('phone'))
                ->withErrors(['otp' => __('pages.delete_account.errors.invalid_otp')]);
        }

        $verificationCode->update(['used' => true]);

        $user->anonymize();
        $this->refreshTokenService->revokeAllForUser($user);
        $user->delete();

        return redirect(localized_route('pages.delete-account'))->with('deleted', true);
    }

    /**
     * Excludes the seeded Google Play review demo account (see
     * Api\UsersController::isDemoPhone) — it must survive a reviewer
     * exercising this page, or every future submission loses its
     * working test login.
     */
    private function findUserByPhone(?string $phone): ?User
    {
        $normalized = User::normalizePhone($phone);

        $candidates = array_values(array_unique(array_filter([
            $normalized,
            '961' . $normalized,
            '+961' . $normalized,
        ])));

        $user = User::whereIn('phone', $candidates)->first();

        return ($user && $user->is_demo_account) ? null : $user;
    }
}

<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\VerificationCode;
use App\Services\UnlimitedMessagingService;
use App\Services\OtpService;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;



class UsersController extends Controller
{
    private UnlimitedMessagingService $whatsAppService;
    private OtpService $otpService;
    private RefreshTokenService $refreshTokenService;

    public function __construct(UnlimitedMessagingService $whatsAppService, OtpService $otpService, RefreshTokenService $refreshTokenService)
    {
        $this->whatsAppService = $whatsAppService;
        $this->otpService = $otpService;
        $this->refreshTokenService = $refreshTokenService;
    }

    /**
     * Issues a fresh short-lived access token plus a long-lived refresh
     * token for the given user. Used by both verifyOtp (initial login) and
     * refresh() (silent renewal), so both paths hand back an identically
     * shaped token pair.
     */
    private function issueTokenPair(User $user): array
    {
        return [
            'access_token' => JWTAuth::fromUser($user),
            'refresh_token' => $this->refreshTokenService->issue($user),
            'token_type' => 'bearer',
            'expires_in' => (int) config('jwt.ttl') * 60,
        ];
    }

    private function normalizePhoneNumber(?string $phone): string
    {
        $digitsOnly = preg_replace('/\D+/', '', (string) $phone);

        if (str_starts_with($digitsOnly, '961')) {
            $digitsOnly = substr($digitsOnly, 3);
        }

        return $digitsOnly;
    }

    private function phoneCandidates(?string $phone): array
    {
        $normalized = $this->normalizePhoneNumber($phone);
        $candidates = array_values(array_unique(array_filter([
            $normalized,
            '961' . $normalized,
            '+961' . $normalized,
        ])));

        return $candidates;
    }

    private function formatUserResponse($user, ?Request $request = null)
    {
        if (!$user) {
            return null;
        }

        $data = $user->toArray();
        $avatarPath = trim((string) ($user->avatar ?? ''));

        if ($avatarPath === '') {
            $data['avatar_url'] = '';
        } elseif (str_starts_with($avatarPath, 'http://') || str_starts_with($avatarPath, 'https://')) {
            $data['avatar_url'] = $avatarPath;
        } else {
            // Storage::disk('public')->url() builds from APP_URL (config/filesystems.php),
            // which already accounts for the app living in a subdirectory
            // (https://ramin7.sg-host.com/trexo/public) on the live host. Building
            // this from just the incoming request's scheme+host instead silently
            // dropped that subdirectory, producing an avatar_url that 404'd even
            // though the file was saved correctly.
            $data['avatar_url'] = Storage::disk('public')->url($avatarPath);
        }

        // Carries the grace-period banner/lock state to every response that
        // returns a user (login, refresh, validateToken) so the app never
        // has to make a separate call just to know whether to show it.
        if ($user->type === 'driver') {
            $driver = \App\Models\Driver::where('user_id', $user->id)->first();
            $data['driver_verification'] = $driver?->verificationStatus();
        }

        return $data;
    }

    /**
     * True only when $normalizedPhone exactly matches the configured
     * DEMO_ACCOUNT_PHONE — the single gate every demo-account code path
     * below is guarded by. Returns false (never matches) when the env var
     * is unset, which is how the whole feature is disabled.
     */
    private function isDemoPhone(string $normalizedPhone): bool
    {
        $demoPhone = User::normalizePhone(config('services.demo_account.phone'));

        return $demoPhone !== '' && $normalizedPhone === $demoPhone;
    }

    /**
     * Google Play review / demo account login — entirely separate from the
     * real WhatsApp-OTP flow below. No VerificationCode row is ever created
     * for this phone, no real WhatsApp message is sent, and the fixed OTP
     * is checked directly against config in verifyOtp(). See
     * docs/demo-account.md.
     */
    private function requestDemoOtp(Request $request, string $phone)
    {
        $user = User::firstOrCreate(
            ['phone' => $phone],
            [
                'type' => 'seller',
                'name' => config('services.demo_account.name'),
                'is_demo_account' => true,
                'is_verified' => true,
                'account_status' => 'active',
            ]
        );

        if (!$user->is_demo_account) {
            $user->is_demo_account = true;
            $user->save();
        }

        Log::channel('demo_account')->info('Demo OTP requested', [
            'phone' => $phone,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'result' => true,
            'message' => 'تم إرسال رمز التحقق عبر واتساب',
        ], 200);
    }

    public function requestOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => false, 'message' => 'يرجى إدخال رقم الهاتف'], 422);
        }

        $phone = $this->normalizePhoneNumber($request->input('phone'));

        if ($this->isDemoPhone($phone)) {
            return $this->requestDemoOtp($request, $phone);
        }

        $user = User::firstOrCreate(
            ['phone' => $phone],
            ['fcm_token' => $request->input('fcm_token', ''), 'type' => 'seller']
        );

        if ($request->filled('fcm_token') && $user->fcm_token !== $request->input('fcm_token')) {
            $user->fcm_token = $request->input('fcm_token');
            $user->save();
        }

        // The code sits alone on its own line (not inline with surrounding
        // text) so a long-press-to-copy on the WhatsApp message grabs just
        // the digits — mixed into a sentence, that gesture over-selects
        // into the neighboring words instead.
        $outcome = $this->otpService->requestOtp($user, $phone, 'whatsapp_otp', $request, function (string $code) {
            return "Your Trexo verification code is:\n\n{$code}\n\nDon't share this code with anyone.";
        });

        if ($outcome['http_status'] !== 200) {
            return response()->json([
                'result' => false,
                'message' => $outcome['message'],
            ], $outcome['http_status']);
        }

        $response = [
            'result' => true,
            'message' => $outcome['message'],
        ];

        if (config('app.debug')) {
            $response['debug_otp'] = $outcome['code']->code;
        }

        return response()->json($response, 200);
    }

    public function updateProfile(Request $request)
{
    // Add the 'auth:api' middleware to require authentication
   // $this->middleware('auth:api');

   $user = Auth::user();
    if ($user) {
    $postVars = $request->input();

    $validator = \Validator::make($request->all(), [ 
        'name' => 'required',
        'email' => 'nullable|email|unique:users,email,' . $user->id, // Allow email update while excluding the current user's email
        'phone' => 'nullable|string|max:255',
        'password' => 'nullable|string',
        'gender' => 'nullable|in:male,female',
        'fcm_token' => 'nullable|string',
        'latitude' => 'nullable|numeric',
        'longitude' => 'nullable|numeric',
        'heading' => 'nullable|numeric|min:0|max:360',
        'speed_kmh' => 'nullable|numeric|min:0|max:180',
        'last_seen_at' => 'nullable|date',
        'profile_image' => 'nullable|file|mimetypes:image/jpeg,image/png,image/jpg,image/gif,image/webp,image/heic,image/heif,image/heic-sequence,image/heif-sequence|max:20480',
    ]);

    if ($validator->fails()) {
        // Validation failed
        $errors = $validator->messages();
        $msg = '';
        foreach ($errors->all() as $message) {
            $msg .= $message;
        }

        // Return a JSON response with validation errors
        return response()->json(['result' => false, 'message' => $msg, 'errors' => $errors], 422);
    } else {
        // Validation passed, update the user's profile
        $user = auth()->user(); // Get the authenticated user

        if ($request->filled('email')) {
            $user->email = $request->input('email');
        }

        if ($request->filled('phone')) {
            $user->phone = $request->input('phone');
        }

        if ($request->filled('gender')) {
            $user->gender = $request->input('gender');
        }

        $user->name = $request->input('name');
       // $user->password = Hash::make($request->input('password'));
        $user->fcm_token = $request->input('fcm_token', $user->fcm_token ?? '');
        if ($request->has('latitude')) {
            $user->latitude = $request->input('latitude');
        }

        if ($request->has('longitude')) {
            $user->longitude = $request->input('longitude');
        }

        if ($request->has('heading')) {
            $user->heading = $request->input('heading');
        }

        if ($request->has('speed_kmh')) {
            $user->speed_kmh = $request->input('speed_kmh');
        }

        if ($request->filled('last_seen_at')) {
            $user->last_seen_at = Carbon::parse($request->input('last_seen_at'));
        } elseif ($request->hasAny(['latitude', 'longitude', 'heading', 'speed_kmh'])) {
            $user->last_seen_at = now();
        }

        // Handle the profile image upload
        if ($request->hasFile('profile_image')) {
            $image = $request->file('profile_image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $storedPath = Storage::disk('public')->putFileAs('users', $image, $imageName);

            if ($storedPath === false) {
                // putFileAs() returns false rather than throwing on failure
                // (disk full, permissions, a transient hosting hiccup) — this
                // used to fall through silently and still point $user->avatar
                // at a file that was never written, leaving the client with a
                // "successful" response and a permanently-404ing avatar URL.
                Log::error('Profile image upload failed to write to storage', [
                    'user_id' => $user->id ?? null,
                    'original_name' => $image->getClientOriginalName(),
                ]);

                return response()->json([
                    'result' => false,
                    'message' => 'Failed to save the profile image, please try again.',
                ], 500);
            }

            $user->avatar = 'users/' . $imageName;

            Log::info('Profile image uploaded', [
                'user_id' => $user->id ?? null,
                'original_name' => $image->getClientOriginalName(),
                'extension' => $image->getClientOriginalExtension(),
                'mime_type' => $image->getClientMimeType(),
                'stored_avatar' => $user->avatar,
                'public_url' => Storage::disk('public')->url($user->avatar),
            ]);
        }

        $user->save();

        // Return a JSON response for a successful profile update
        return response()->json([
            'result' => true,
            'message' => 'Profile updated successfully',
            'user' => $this->formatUserResponse($user, $request),
        ], 200);
    }}else{
        return response()->json(['error' => 'Unauthorized'], 201);
    }
}


public function updateAvailability(Request $request)
{
    $user = Auth::user();

    if (!$user) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $validator = Validator::make($request->all(), [
        'is_available' => 'required|boolean',
    ]);

    if ($validator->fails()) {
        $errors = $validator->messages();
        $msg = '';
        foreach ($errors->all() as $message) {
            $msg .= $message;
        }

        return response()->json(['result' => false, 'message' => $msg, 'errors' => $errors], 422);
    }

    $goingOnline = $request->boolean('is_available');

    if ($goingOnline) {
        $driverRow = DB::table('drivers')->where('user_id', $user->id)->first();

        if ($driverRow !== null && $driverRow->approval_status !== null && $driverRow->approval_status !== 'approved') {
            // A driver still inside their 7-day document-upload grace period
            // may go online same as an approved one — only an expired grace
            // period (documents_required) or an explicit
            // pending/suspended/rejected status blocks this.
            $isWithinGracePeriod = $driverRow->approval_status === 'grace_period'
                && $driverRow->grace_period_ends_at !== null
                && Carbon::parse($driverRow->grace_period_ends_at)->isFuture();

            if (!$isWithinGracePeriod) {
                return response()->json([
                    'result' => false,
                    'message' => 'حسابك كسائق قيد المراجعة أو موقوف، لا يمكنك الاتصال بالإنترنت الآن',
                    'driver_status' => $driverRow->approval_status,
                ], 403);
            }
        }
    }

    $user->is_available = $goingOnline;
    $user->save();

    // Keep the driver-simulator's own state in lockstep: if this account
    // also has a `drivers` row (a real driver being used as a simulator
    // test subject), its background tick loop resyncs users.is_available
    // from drivers.status on every tick — without this, that tick would
    // silently overwrite the manual toggle made here a few seconds later.
    DB::table('drivers')->where('user_id', $user->id)->update([
        'is_online' => $user->is_available,
        'status' => $user->is_available ? 'available' : 'offline',
        'workflow_state' => $user->is_available ? 'available' : 'offline',
        'updated_at' => now(),
    ]);

    return response()->json([
        'result' => true,
        'message' => 'Availability updated successfully',
        'user' => $this->formatUserResponse($user, $request),
    ], 200);
}
public function updateBusinessType(Request $request)
{
   $user = Auth::user();
    if ($user) {
    $postVars = $request->input();

    $validator = \Validator::make($request->all(), [ 
        'type' => 'required',
       
    ]);

    if ($validator->fails()) {
        // Validation failed
        $errors = $validator->messages();
        $msg = '';
        foreach ($errors->all() as $message) {
            $msg .= $message;
        }

        // Return a JSON response with validation errors
        return response()->json(['result' => false, 'message' => $msg, 'errors' => $errors], 422);
    } else {
        // Validation passed, update the user's profile
        $user = auth()->user(); // Get the authenticated user

        $user->type = $request->input('type');

        $user->save();
      
        // Return a JSON response for a successful profile update
        return response()->json(['result' => true, 'message' => 'Updated successfully','user'=> $this->formatUserResponse($user, $request)], 201);
    }}else{
        return response()->json(['error' => 'Unauthorized'], 201);
    }
}


public function profile(Request $request)
{

    $user = Auth::user();
    if ($user) {
        return response()->json(['user' => $this->formatUserResponse($user, $request)], 200);
    }
    return response()->json(['error' => 'Unauthorized'], 201);
}

public function validateToken(Request $request)
{
    $user = Auth::user();

    if ($user) {
        return response()->json([
            'message' => 'Authenticated',
            'user' => $this->formatUserResponse($user, $request),
        ], 200);
    }

    return response()->json([
        'message' => 'Not authenticated',
        'arr' => $request->all(),
    ], 401);
}

public function refresh(Request $request)
{
    $validator = Validator::make($request->all(), [
        'refresh_token' => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'result' => false,
            'message' => 'Missing refresh token',
        ], 401);
    }

    try {
        $record = $this->refreshTokenService->resolve($request->input('refresh_token'));

        if (!$record) {
            return response()->json([
                'result' => false,
                'message' => 'Refresh token is invalid, expired, or revoked',
            ], 401);
        }

        $user = $record->user;

        if (!$user) {
            return response()->json([
                'result' => false,
                'message' => 'Unable to refresh token',
            ], 401);
        }

        if ($user->isBlocked()) {
            return response()->json([
                'result' => false,
                'message' => 'هذا الحساب موقوف، يرجى التواصل مع الدعم',
                'account_status' => $user->account_status,
            ], 403);
        }

        // Rotate: this refresh token is single-use — revoke it the moment
        // it's redeemed and hand back a brand new one. A stolen-in-transit
        // refresh token that's already been used by the legitimate client
        // is dead on arrival for whoever else has it.
        $this->refreshTokenService->revoke($record);
        $tokens = $this->issueTokenPair($user);

        return response()->json(array_merge([
            'result' => true,
            'message' => 'Token refreshed successfully',
            'token' => $tokens['access_token'],
            'user' => $this->formatUserResponse($user, $request),
        ], $tokens), 200);
    } catch (\Throwable $e) {
        Log::warning('Token refresh failed', [
            'message' => $e->getMessage(),
        ]);

        return response()->json([
            'result' => false,
            'message' => 'Unable to refresh token',
        ], 401);
    }
}

public function logout(Request $request)
{
    $refreshTokenValue = $request->input('refresh_token');
    if ($refreshTokenValue) {
        $record = $this->refreshTokenService->resolve($refreshTokenValue);
        if ($record) {
            $this->refreshTokenService->revoke($record);
        }
    }

    try {
        JWTAuth::parseToken()->invalidate();
    } catch (\Throwable $e) {
        // No/invalid access token on the request — nothing left to
        // invalidate, and logging out should succeed regardless.
    }

    return response()->json(['result' => true, 'message' => 'Logged out'], 200);
}


/**
 * Verifies the fixed demo OTP against config and, on success, issues a
 * real token pair for the isolated demo user — otherwise rejects with the
 * same generic message a real wrong/expired code gets, so a wrong guess
 * for this phone number reveals nothing about it being special. No
 * VerificationCode row is ever consulted for this phone.
 */
private function verifyDemoOtp(Request $request, string $phone)
{
    $demoOtp = (string) config('services.demo_account.otp');

    if ($demoOtp === '' || $request->input('otp') !== $demoOtp) {
        Log::channel('demo_account')->warning('Demo OTP verification failed', [
            'phone' => $phone,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'result' => false,
            'error' => 'رمز التحقق غير صحيح أو منتهي الصلاحية',
        ], 400);
    }

    $user = User::firstOrCreate(
        ['phone' => $phone],
        [
            'type' => 'seller',
            'name' => config('services.demo_account.name'),
            'is_demo_account' => true,
            'is_verified' => true,
            'account_status' => 'active',
        ]
    );

    if (!$user->is_demo_account) {
        $user->is_demo_account = true;
        $user->save();
    }

    if ($user->isBlocked()) {
        return response()->json([
            'result' => false,
            'error' => 'هذا الحساب موقوف، يرجى التواصل مع الدعم',
            'account_status' => $user->account_status,
        ], 403);
    }

    $user->is_verified = 1;
    $user->save();

    $tokens = $this->issueTokenPair($user);

    Log::channel('demo_account')->info('Demo account login succeeded', [
        'user_id' => $user->id,
        'phone' => $phone,
        'ip' => $request->ip(),
    ]);

    return response()->json(array_merge([
        'result' => true,
        'message' => 'تم التحقق بنجاح',
        'token' => $tokens['access_token'],
        'needs_profile' => empty($user->name),
        'user' => $this->formatUserResponse($user, $request),
    ], $tokens), 200);
}

public function verifyOtp(Request $request)
{
    $validator = Validator::make($request->all(), [
        'phone' => 'required',
        'otp' => 'required|numeric|digits:6',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'result' => false,
            'error' => 'Invalid input data',
            'details' => $validator->errors(),
        ], 400);
    }

    $normalizedPhone = $this->normalizePhoneNumber($request->input('phone'));

    if ($this->isDemoPhone($normalizedPhone)) {
        return $this->verifyDemoOtp($request, $normalizedPhone);
    }

    $phoneCandidates = $this->phoneCandidates($request->input('phone'));
    $user = User::whereIn('phone', $phoneCandidates)->first();

    if (!$user) {
        return response()->json([
            'result' => false,
            'error' => 'User not found',
        ], 404);
    }

    $verificationCode = VerificationCode::where('user_id', $user->id)
        ->where('code', $request->input('otp'))
        ->where('used', false)
        ->where('expires_at', '>=', Carbon::now())
        ->orderByDesc('id')
        ->first();

    if (!$verificationCode) {
        return response()->json([
            'result' => false,
            'error' => 'رمز التحقق غير صحيح أو منتهي الصلاحية',
        ], 400);
    }

    if ($user->isBlocked()) {
        return response()->json([
            'result' => false,
            'error' => 'هذا الحساب موقوف، يرجى التواصل مع الدعم',
            'account_status' => $user->account_status,
        ], 403);
    }

    $verificationCode->used = true;
    $verificationCode->save();

    $user->is_verified = 1;
    $user->save();
    $this->otpService->markVerified($user);

    $tokens = $this->issueTokenPair($user);

    return response()->json(array_merge([
        'result' => true,
        'message' => 'تم التحقق بنجاح',
        'token' => $tokens['access_token'],
        'needs_profile' => empty($user->name),
        'user' => $this->formatUserResponse($user, $request),
    ], $tokens), 200);
}


/////////////////////////PASSWORD RESET/////////////////////////

public function forgotPassword(Request $request)
{
    $validator = Validator::make($request->all(), [
        'phone' => 'required',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'result' => false,
            'error' => 'يرجى إدخال رقم الهاتف',
        ], 400);
    }

    $phoneCandidates = $this->phoneCandidates($request->input('phone'));
    $user = User::whereIn('phone', $phoneCandidates)->first();

    if (!$user) {
        return response()->json([
            'result' => false,
            'error' => 'رقم الهاتف غير مسجل',
        ], 404);
    }

    $normalizedPhone = $this->normalizePhoneNumber($request->input('phone'));

    $outcome = $this->otpService->requestOtp($user, $normalizedPhone, 'password_reset', $request, function (string $code) {
        return "Your Trexo password reset code is:\n\n{$code}\n\nDon't share this code with anyone.";
    });

    if ($outcome['http_status'] !== 200) {
        return response()->json([
            'result' => false,
            'error' => $outcome['message'],
        ], $outcome['http_status']);
    }

    $response = [
        'result' => true,
        'message' => $outcome['message'],
        'user_id' => $user->id,
        'phone' => $user->phone,
    ];

    if (config('app.debug')) {
        $response['debug_otp'] = $outcome['code']->code;
    }

    return response()->json($response, 200);
}

public function resetPassword(Request $request)
{
    $validator = Validator::make($request->all(), [
        'user_id' => 'required',
        'otp' => 'required|numeric|digits:6',
        'password' => 'required|min:6',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'result' => false,
            'error' => 'يرجى التحقق من البيانات المدخلة',
            'details' => $validator->errors(),
        ], 400);
    }

    $verificationCode = VerificationCode::where('user_id', $request->input('user_id'))
        ->where('code', $request->input('otp'))
        ->where('used', 0)
        ->orderByDesc('id')
        ->first();

    if (!$verificationCode) {
        return response()->json([
            'result' => false,
            'error' => 'رمز التحقق غير صحيح أو منتهي الصلاحية',
        ], 400);
    }

    $user = User::find($request->input('user_id'));
    if (!$user) {
        return response()->json([
            'result' => false,
            'error' => 'المستخدم غير موجود',
        ], 404);
    }

    if ($user->isBlocked()) {
        return response()->json([
            'result' => false,
            'error' => 'هذا الحساب موقوف، يرجى التواصل مع الدعم',
            'account_status' => $user->account_status,
        ], 403);
    }

    $verificationCode->used = true;
    $verificationCode->save();

    $user->password = Hash::make($request->input('password'));
    $user->save();
    $this->otpService->markVerified($user);

    // A password reset is a signal the account may have been compromised
    // (or the old password simply forgotten) — either way, every device
    // that was already signed in should be forced to re-authenticate
    // rather than silently keep refreshing on the old password's session.
    // Revoking here (before issuing this request's own pair) only kills
    // *other* sessions; this device gets a fresh one right after.
    $this->refreshTokenService->revokeAllForUser($user);
    $tokens = $this->issueTokenPair($user);

    return response()->json(array_merge([
        'result' => true,
        'message' => 'تم تحديث كلمة المرور بنجاح',
        'token' => $tokens['access_token'],
        'user' => $this->formatUserResponse($user, $request),
    ], $tokens), 200);
}

/////////////////////////NOTIFICATIONS/////////////////////////

public function saveFcmToken(Request $request)
{    $user = JWTAuth::parseToken()->authenticate();
    $request->validate(['fcm_token' => 'required|string']);

    $user->fcm_token = $request->fcm_token;
    $user->save();

    return response()->json(['message' => 'FCM token saved']);
}

/////////////////////////ACCOUNT DELETION/////////////////////////

/**
 * Permanently disables the account: personal data (name, phone, email,
 * avatar, password, fcm token) is scrubbed and the row is soft-deleted,
 * so the user can no longer authenticate. Historical order/reservation
 * rows keep their user_id for the business's own record-keeping, but no
 * longer resolve to any identifying user data once this ran.
 */
public function deleteAccount(Request $request)
{
    $user = Auth::user();

    if (!$user) {
        return response()->json(['result' => false, 'message' => 'Unauthorized'], 401);
    }

    $user->anonymize();
    $this->refreshTokenService->revokeAllForUser($user);
    $user->delete();

    return response()->json([
        'result' => true,
        'message' => 'Account deleted successfully',
    ], 200);
}

}






?>



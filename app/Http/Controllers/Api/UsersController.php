<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\VerificationCode;
use App\Services\WhatsAppService;
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
    private WhatsAppService $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
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
            return $data;
        }

        if (str_starts_with($avatarPath, 'http://') || str_starts_with($avatarPath, 'https://')) {
            $data['avatar_url'] = $avatarPath;
            return $data;
        }

        $baseUrl = rtrim($request?->getSchemeAndHttpHost() ?? url('/'), '/');
        $data['avatar_url'] = $baseUrl . '/storage/' . ltrim($avatarPath, '/');
        return $data;
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

        $user = User::firstOrCreate(
            ['phone' => $phone],
            ['fcm_token' => $request->input('fcm_token', ''), 'type' => 'seller']
        );

        if ($request->filled('fcm_token') && $user->fcm_token !== $request->input('fcm_token')) {
            $user->fcm_token = $request->input('fcm_token');
            $user->save();
        }

        $recentCode = VerificationCode::where('user_id', $user->id)
            ->where('used', false)
            ->orderByDesc('id')
            ->first();

        if ($recentCode && $recentCode->created_at && $recentCode->created_at->gt(Carbon::now()->subSeconds(60))) {
            $waitSeconds = max(1, 60 - Carbon::now()->diffInSeconds($recentCode->created_at));
            return response()->json([
                'result' => false,
                'message' => "يرجى الانتظار {$waitSeconds} ثانية قبل إعادة الإرسال",
            ], 429);
        }

        $verificationCode = VerificationCode::create([
            'user_id' => $user->id,
            'code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'expires_at' => Carbon::now()->addMinutes(10),
            'type' => 'whatsapp_otp',
            'used' => 0,
        ]);

        $this->whatsAppService->sendOtp('961' . $phone, $verificationCode->code);

        $response = [
            'result' => true,
            'message' => 'تم إرسال رمز التحقق عبر واتساب',
        ];

        if (config('app.debug')) {
            $response['debug_otp'] = $verificationCode->code;
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
            Storage::disk('public')->putFileAs('users', $image, $imageName);
            $user->avatar = 'users/' . $imageName;

            Log::info('Profile image uploaded', [
                'user_id' => $user->id ?? null,
                'original_name' => $image->getClientOriginalName(),
                'extension' => $image->getClientOriginalExtension(),
                'mime_type' => $image->getClientMimeType(),
                'stored_avatar' => $user->avatar,
                'public_url' => url('storage/' . $user->avatar),
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
        $driverApprovalStatus = DB::table('drivers')->where('user_id', $user->id)->value('approval_status');

        if ($driverApprovalStatus !== null && $driverApprovalStatus !== 'approved') {
            return response()->json([
                'result' => false,
                'message' => 'حسابك كسائق قيد المراجعة أو موقوف، لا يمكنك الاتصال بالإنترنت الآن',
            ], 403);
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

public function refreshToken(Request $request)
{
    try {
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            return response()->json([
                'result' => false,
                'message' => 'Missing access token',
            ], 401);
        }

        $newToken = JWTAuth::setToken($bearerToken)->refresh();
        $user = JWTAuth::setToken($newToken)->toUser();

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

        return response()->json([
            'result' => true,
            'message' => 'Token refreshed successfully',
            'token' => $newToken,
            'user' => $this->formatUserResponse($user, $request),
        ], 200);
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

    $token = JWTAuth::fromUser($user);

    return response()->json([
        'result' => true,
        'message' => 'تم التحقق بنجاح',
        'token' => $token,
        'needs_profile' => empty($user->name),
        'user' => $this->formatUserResponse($user, $request),
    ], 200);
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

    $verificationCode = VerificationCode::create([
        'user_id' => $user->id,
        'code' => rand(100000, 999999),
        'expires_at' => Carbon::now()->addMinutes(10),
        'type' => 'password_reset',
        'used' => 0,
    ]);

    $response = [
        'result' => true,
        'message' => 'تم إرسال رمز التحقق',
        'user_id' => $user->id,
        'phone' => $user->phone,
    ];

    if (config('app.debug')) {
        $response['debug_otp'] = $verificationCode->code;
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

    $token = JWTAuth::fromUser($user);

    return response()->json([
        'result' => true,
        'message' => 'تم تحديث كلمة المرور بنجاح',
        'token' => $token,
        'user' => $this->formatUserResponse($user, $request),
    ], 200);
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

    $userId = $user->id;

    if ($user->avatar) {
        Storage::disk('public')->delete($user->avatar);
    }

    $user->name = 'Deleted user';
    $user->email = "deleted_{$userId}_" . time() . '@deleted.local';
    $user->phone = "deleted_{$userId}";
    $user->password = Hash::make(Str::random(40));
    $user->fcm_token = null;
    $user->api_token = null;
    $user->avatar = null;
    $user->is_available = false;
    $user->save();

    $user->delete();

    return response()->json([
        'result' => true,
        'message' => 'Account deleted successfully',
    ], 200);
}

}






?>



<?php

/**
 * Standalone worker spawned as a genuinely separate OS process by
 * tests\Feature\OtpRateLimitingTest::test_true_concurrent_processes_cannot_bypass_the_cooldown().
 * Boots Laravel in the testing environment (so OtpService's provider-mode
 * hard-lock keeps this mocked — no real WhatsApp send is possible from
 * here either) against the same testing database, calls
 * OtpService::requestOtp() once for the phone given as argv[1], and prints
 * only the result's 'reason' to stdout. Kept deliberately minimal since
 * its whole purpose is to race identically-shaped sibling processes
 * against the same phone number.
 */

require __DIR__ . '/../../vendor/autoload.php';

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';
putenv('DB_DATABASE=allow_delivery_testing');
$_ENV['DB_DATABASE'] = 'allow_delivery_testing';
$_SERVER['DB_DATABASE'] = 'allow_delivery_testing';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$phone = $argv[1];

$user = App\Models\User::firstOrCreate(
    ['phone' => $phone],
    ['type' => 'seller', 'name' => 'Concurrency Runner']
);

$service = $app->make(App\Services\OtpService::class);
$request = Illuminate\Http\Request::create('/api/requestOtp', 'POST', ['phone' => $phone]);
$request->server->set('REMOTE_ADDR', '198.51.100.77');

$result = $service->requestOtp($user, $phone, 'whatsapp_otp', $request, fn ($code) => "concurrency-test {$code}");

echo $result['reason'];

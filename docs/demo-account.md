# Google Play Review / Demo Account

A single, dedicated phone number that logs in with a fixed OTP instead of a
real WhatsApp code, so Google Play reviewers (and anyone else who needs a
stable login) are never blocked on a code that expires or changes.

## How it's identified

- The account is a normal row in `users` (`type = seller`), flagged with
  `is_demo_account = true`.
- It owns none of the data other users create — every API endpoint scopes
  orders/wallet/etc. by the authenticated user's own id, so a fresh demo
  account starts empty and can never see real customers' orders, payments,
  or personal data. Nothing extra was added to enforce this; it falls out
  of the existing per-user scoping.
- It has normal `seller` permissions, so a reviewer can exercise the app's
  main flows (place/track orders, etc.) with real functionality, on data
  that belongs only to that account.

## How the bypass works

Two config values control the whole feature:

| Env var | Meaning |
|---|---|
| `DEMO_ACCOUNT_PHONE` | The dedicated demo phone number (8 digits, no country code — same format the app sends) |
| `DEMO_ACCOUNT_OTP` | The fixed 6-digit code accepted **only** for that phone number |
| `DEMO_ACCOUNT_NAME` | Display name given to the account when it's created (optional, defaults to "Google Play Reviewer") |

In `App\Http\Controllers\Api\UsersController`:

- `isDemoPhone($phone)` — the single gate. Returns true only when the
  normalized incoming phone exactly equals `DEMO_ACCOUNT_PHONE`. If that
  env var is empty, it can never return true, which is how the feature is
  disabled.
- `requestOtp()` — checks `isDemoPhone()` first. If true, delegates to
  `requestDemoOtp()`, which creates/reuses the demo user and returns
  success **without** creating a `VerificationCode` row and **without**
  sending a real WhatsApp message. Every other phone number falls through
  to the original, completely unmodified flow.
- `verifyOtp()` — same pattern: `isDemoPhone()` first, then
  `verifyDemoOtp()`, which compares the submitted OTP directly against
  `DEMO_ACCOUNT_OTP` (never touching the `VerificationCode` table) and
  issues a real JWT + refresh token pair on match. A wrong code gets the
  exact same generic "invalid or expired" message a real wrong/expired
  code would — nothing about the response reveals this phone number is
  special. Every other phone number falls through to the original,
  completely unmodified flow, which still requires a real `VerificationCode`
  row — so the fixed OTP has no effect on any other account, and normal
  users' security is unchanged.

This is not a global bypass and there's no `if phone == X then OTP = 123456`
shortcut sitting in the normal flow — the demo phone/OTP pair is checked in
its own dedicated method, gated on both the phone matching **and** the
otp matching, before the normal code even runs.

## Logging

Every demo-account request/verification (success or failure) is logged to
its own file, `storage/logs/demo-account-YYYY-MM-DD.log` (channel
`demo_account` in `config/logging.php`, 90-day retention), with phone,
IP, and (on success) the resulting user id — kept separate from
`laravel.log` so usage is trivial to audit without grep-ing the whole app
log.

## Setting it up

1. Set the three env vars on the server (`.env`):
   ```
   DEMO_ACCOUNT_PHONE=70999999
   DEMO_ACCOUNT_OTP=482913
   DEMO_ACCOUNT_NAME="Google Play Reviewer"
   ```
   Pick your own 8-digit phone and 6-digit code — these are just the
   values used during development/testing of this feature. Avoid an
   obviously-guessable code like `123456`.

2. Clear the config cache so the new values take effect:
   ```
   php artisan config:clear
   ```

3. (Optional, but recommended) Pre-create the account so it exists even
   before the first login attempt:
   ```
   php artisan db:seed --class=DemoAccountSeeder
   ```
   Safe to re-run any time — it's an `updateOrCreate`, so it never
   duplicates the account.

4. If the app has paid/premium content the demo account needs access to
   for review (currently: School Bus Premium), run:
   ```
   php artisan db:seed --class=DemoSchoolBusPremiumSeeder
   ```
   This grants the demo account a genuinely active Premium subscription
   (5-year expiry) without a real payment — there's no payment gateway in
   this app; premium is normally unlocked by a parent uploading a receipt
   and an admin approving it. To do this it also creates a synthetic
   driver, school, and route, all of which are `is_active = false` /
   never-approved so they can never appear in any real user's
   school-browsing or driver-listing screens — verified via
   `GET /schools` and `GET /schools/{id}/drivers` before shipping this.
   The demo parent's own "my subscriptions" screen still sees it, since
   that view fetches by the parent's own user id regardless of those
   flags. Safe to re-run any time.

No Flutter/app changes are required — the app already just POSTs whatever
phone + OTP the user types to the existing `/api/requestOtp` and
`/api/verifyOtp` endpoints; the backend decides which path to take.

## Entering it in Google Play Console

In **Play Console → App content → App access → "Not all functionality is
available"** (or the "Add sign-in details" form on newer Console
versions):

1. Instructions field: explain it's phone + OTP login, and that the OTP
   is fixed for this specific test account (reviewers won't receive a
   real WhatsApp message).
2. Username / phone field: the value of `DEMO_ACCOUNT_PHONE` (e.g.
   `70999999`).
3. Password / OTP field: the value of `DEMO_ACCOUNT_OTP` (e.g. `482913`).
4. "Full access to all features including premium/paid content" checkbox:
   only check this once `DemoSchoolBusPremiumSeeder` (or the equivalent
   for whatever other paid feature exists at the time) has actually been
   run — otherwise the demo account genuinely won't have that access and
   the box would be false.

## Disabling or rotating the demo account

- **Disable entirely**: clear `DEMO_ACCOUNT_PHONE` and `DEMO_ACCOUNT_OTP`
  in `.env`, then `php artisan config:clear`. The phone number
  immediately falls back to being treated like any other real user
  number — a real OTP would be generated and sent via WhatsApp for it,
  and the old fixed code stops working.
- **Rotate the code or phone number**: change the env vars, run
  `php artisan config:clear`, then re-run the seeder if you also changed
  the phone number (so the new number has an account ready). The old
  demo user row is left in `users` (harmless, just an ordinary
  never-logged-into account afterward) unless you delete it manually.
- **Delete the demo account's data**: it's a normal user row —
  `App\Models\User::where('is_demo_account', true)->first()->delete()`
  (or via the admin panel) removes it and its own orders like any other
  account.

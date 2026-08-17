<?php

namespace App\Services\Broadcast;

use App\Models\Broadcast;
use App\Models\Driver;
use App\Models\User;
use App\Services\Firebase\FcmMessagingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Shuchkin\SimpleXLS;
use Shuchkin\SimpleXLSX;

class BroadcastService
{
    // Hard ceiling on data rows read from an uploaded sheet — well above any
    // realistic recipient list, just there so a malformed/huge file can't
    // exhaust memory parsing it.
    private const EXCEL_MAX_ROWS = 20000;

    // Every local Lebanese mobile number in PhoneNormalizationTest normalizes
    // to 7-8 digits; nothing elsewhere in the app enforces a stricter format
    // than that, so this is the same bar "valid phone" is held to everywhere
    // else (registration, OTP, etc.) rather than a new, stricter one invented
    // just for Excel imports.
    private const PHONE_PATTERN = '/^\d{7,8}$/';

    public function __construct(private readonly FcmMessagingService $fcm) {}

    // Filtration entry point — unchanged behavior/signature. Resolves the
    // audience from the filters, then hands off to the same sendToRecipients()
    // the Excel path uses, so there's exactly one place that actually pushes
    // notifications and records a Broadcast row.
    public function send(string $title, string $message, ?string $accountType, ?string $serviceType, ?int $sentBy): Broadcast
    {
        $recipients = $this->resolveRecipients($accountType, $serviceType);

        return $this->sendToRecipients($title, $message, $recipients, $accountType, $serviceType, $sentBy);
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    public function sendToRecipients(
        string $title,
        string $message,
        Collection $recipients,
        ?string $accountType,
        ?string $serviceType,
        ?int $sentBy,
        string $source = Broadcast::SOURCE_FILTRATION,
        ?string $sourceFileName = null,
    ): Broadcast {
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
                ->filter(fn (User $user) => ! empty($user->fcm_token))
                ->map(fn (User $user) => [
                    'fcm_token' => $user->fcm_token,
                    'user_id' => $user->id,
                    'ref_id' => 0,
                ])
                ->values()
                ->all();

            if (! empty($tokens)) {
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
            'source' => $source,
            'source_file_name' => $sourceFileName,
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

    /**
     * Parses an uploaded Excel file into a recipient preview: matches each
     * valid, deduplicated phone number against an existing active user
     * (mirroring resolveRecipients()'s "any" eligibility — active account,
     * a real driver/seller `type`), same as a filtration-selected audience
     * would be. Throws \InvalidArgumentException with a message safe to show
     * the admin directly for anything wrong with the file itself.
     *
     * @return array{
     *     total_rows: int,
     *     empty_rows_ignored: int,
     *     invalid_count: int,
     *     invalid_samples: array<int, string>,
     *     duplicate_count: int,
     *     not_found_count: int,
     *     not_found_numbers: array<int, string>,
     *     recipients: Collection<int, User>,
     *     final_count: int,
     * }
     */
    public function parseExcelRecipients(UploadedFile $file): array
    {
        // Confirms this is actually a spreadsheet by inspecting its real
        // content — the .xlsx magic bytes (it's a zip archive) or the legacy
        // .xls magic bytes (an OLE2 compound file) — not by trusting the
        // client-supplied extension/mime type, which a renamed file can fake.
        $format = $this->detectSpreadsheetFormat($file->getRealPath());

        try {
            $parsed = $format === 'xlsx'
                ? SimpleXLSX::parse($file->getRealPath())
                : SimpleXLS::parse($file->getRealPath());
        } catch (\Throwable $e) {
            $parsed = false;
        }

        if ($parsed === false) {
            $error = $format === 'xlsx' ? SimpleXLSX::parseError() : SimpleXLS::parseError();
            throw new \InvalidArgumentException('Could not read the uploaded file — it may be corrupted: '.$error);
        }

        $rows = $parsed->rows();

        if (empty($rows)) {
            throw new \InvalidArgumentException('The uploaded file is empty.');
        }

        $header = array_map(fn ($cell) => strtolower(trim((string) $cell)), array_shift($rows));
        $phoneColumnIndex = array_search('phone', $header, true);

        if ($phoneColumnIndex === false) {
            throw new \InvalidArgumentException('The uploaded file must have a "phone" column.');
        }

        if (count($rows) > self::EXCEL_MAX_ROWS) {
            throw new \InvalidArgumentException('The uploaded file has too many rows (max '.self::EXCEL_MAX_ROWS.').');
        }

        $totalRows = count($rows);
        $emptyRowsIgnored = 0;
        $invalidCount = 0;
        $invalidSamples = [];
        $duplicateCount = 0;
        $seenPhones = [];
        $validUniquePhones = [];

        foreach ($rows as $row) {
            $raw = $row[$phoneColumnIndex] ?? null;
            $raw = is_string($raw) ? trim($raw) : $raw;

            if ($raw === null || $raw === '') {
                $emptyRowsIgnored++;

                continue;
            }

            $normalized = User::normalizePhone((string) $raw);

            if ($normalized === '' || ! preg_match(self::PHONE_PATTERN, $normalized)) {
                $invalidCount++;
                if (count($invalidSamples) < 10) {
                    $invalidSamples[] = (string) $raw;
                }

                continue;
            }

            if (isset($seenPhones[$normalized])) {
                $duplicateCount++;

                continue;
            }

            $seenPhones[$normalized] = true;
            $validUniquePhones[] = $normalized;
        }

        // Mirrors UsersController::phoneCandidates() — existing user rows
        // aren't guaranteed to have their phone stored in one single
        // normalized shape (some predate that convention), so match against
        // every shape a normalized number could legitimately be stored as
        // instead of reporting a real user as "not found".
        $allCandidates = [];
        foreach ($validUniquePhones as $normalized) {
            array_push($allCandidates, ...$this->phoneCandidates($normalized));
        }

        $usersByPhone = User::query()
            ->where('account_status', 'active')
            ->whereNotNull('type')
            ->whereIn('phone', $allCandidates)
            ->get()
            ->keyBy('phone');

        $recipients = collect();
        $notFoundNumbers = [];

        foreach ($validUniquePhones as $normalized) {
            $match = null;
            foreach ($this->phoneCandidates($normalized) as $candidate) {
                if ($usersByPhone->has($candidate)) {
                    $match = $usersByPhone->get($candidate);
                    break;
                }
            }

            if ($match !== null) {
                $recipients->push($match);
            } else {
                $notFoundNumbers[] = $normalized;
            }
        }

        $recipients = $recipients->unique('id')->values();

        return [
            'total_rows' => $totalRows,
            'empty_rows_ignored' => $emptyRowsIgnored,
            'invalid_count' => $invalidCount,
            'invalid_samples' => $invalidSamples,
            'duplicate_count' => $duplicateCount,
            'not_found_count' => count($notFoundNumbers),
            'not_found_numbers' => $notFoundNumbers,
            'recipients' => $recipients,
            'final_count' => $recipients->count(),
        ];
    }

    /** @return array<int, string> */
    private function phoneCandidates(string $normalized): array
    {
        return array_values(array_unique(array_filter([
            $normalized,
            '961'.$normalized,
            '+961'.$normalized,
        ])));
    }

    /**
     * Sniffs the file's actual byte signature instead of trusting its
     * extension: .xlsx is a zip archive (starts "PK\x03\x04", or the empty-
     * archive variants), legacy .xls is an OLE2 compound file (a fixed
     * 8-byte magic number).
     */
    private function detectSpreadsheetFormat(string $path): string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new \InvalidArgumentException('The uploaded file could not be read.');
        }
        $header = fread($handle, 8);
        fclose($handle);

        if ($header === false || strlen($header) < 4) {
            throw new \InvalidArgumentException('The uploaded file is not a valid Excel file.');
        }

        if (in_array(substr($header, 0, 4), ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true)) {
            return 'xlsx';
        }

        if ($header === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            return 'xls';
        }

        throw new \InvalidArgumentException('The uploaded file must be a .xlsx or .xls Excel file.');
    }
}

<?php

namespace App\Services\Driver;

use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\User;
use App\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DriverManagementService
{
    public const APPROVAL_STATUSES = ['pending', 'approved', 'suspended', 'rejected'];
    public const DOCUMENT_TYPES = ['license', 'id_card', 'vehicle_registration', 'insurance'];

    /**
     * @param array{search?: string, approval_status?: string, expiring_soon?: string} $filters
     */
    public function filtered(array $filters): LengthAwarePaginator
    {
        $query = Driver::query()->with('user')->latest('id');

        if (!empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->whereHas('user', function ($q) use ($term) {
                $q->where('name', 'like', $term)->orWhere('phone', 'like', $term);
            });
        }

        if (!empty($filters['approval_status'])) {
            $query->where('approval_status', $filters['approval_status']);
        }

        if (!empty($filters['expiring_soon'])) {
            $query->whereHas('documents', function ($q) {
                $q->whereNotNull('expires_at')->where('expires_at', '<=', now()->addDays(30));
            });
        }

        return $query->paginate(25)->withQueryString();
    }

    public function tripHistory(Driver $driver): \Illuminate\Support\Collection
    {
        return Order::where('driver_id', $driver->user_id)->latest()->limit(20)->get();
    }

    /**
     * Normalizes a phone the same way Api\UsersController does for OTP
     * login, so admin-entered numbers match however the number is already
     * stored (with or without the 961 country code).
     */
    private function normalizePhone(string $phone): string
    {
        $digitsOnly = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digitsOnly, '961')) {
            $digitsOnly = substr($digitsOnly, 3);
        }

        return $digitsOnly;
    }

    private function phoneCandidates(string $phone): array
    {
        $normalized = $this->normalizePhone($phone);

        return array_values(array_unique(array_filter([
            $normalized,
            '961'.$normalized,
            '+961'.$normalized,
        ])));
    }

    /**
     * Finds an existing user by phone (in whatever format it's stored) or
     * creates a new one. Either way, promotes them to type='driver' — this
     * app's `users.type` is a single value, not a role list, so linking an
     * existing customer's phone to a driver record does overwrite their
     * type, per the confirmed decision.
     */
    public function findOrCreateUserByPhone(string $phone): User
    {
        $candidates = $this->phoneCandidates($phone);
        $user = User::whereIn('phone', $candidates)->first();

        if (!$user) {
            $user = User::create(['phone' => $this->normalizePhone($phone)]);
        }

        $user->type = 'driver';
        $user->save();

        return $user;
    }

    /**
     * @throws \InvalidArgumentException if this user already has a driver record.
     */
    public function createDriver(User $user, array $data): Driver
    {
        if (Driver::where('user_id', $user->id)->exists()) {
            throw new \InvalidArgumentException('This user already has a driver record.');
        }

        return Driver::create(array_merge($data, [
            'user_id' => $user->id,
            'approval_status' => 'pending',
        ]));
    }

    public function updateApprovalStatus(Driver $driver, string $status): void
    {
        if (!in_array($status, self::APPROVAL_STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid approval status: {$status}");
        }

        $driver->approval_status = $status;
        $driver->save();
    }

    public function uploadDocument(Driver $driver, string $documentType, UploadedFile $file, ?string $expiresAt): DriverDocument
    {
        if (!in_array($documentType, self::DOCUMENT_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid document type: {$documentType}");
        }

        $fileName = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
        Storage::disk('public')->putFileAs('driver_documents', $file, $fileName);

        return DriverDocument::create([
            'driver_id' => $driver->id,
            'document_type' => $documentType,
            'file_path' => 'driver_documents/'.$fileName,
            'expires_at' => $expiresAt,
        ]);
    }

    public function reviewDocument(DriverDocument $document, string $status, ?string $rejectionReason): void
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException("Invalid document review status: {$status}");
        }

        $document->status = $status;
        $document->rejection_reason = $status === 'rejected' ? $rejectionReason : null;
        $document->reviewed_by = Auth::id();
        $document->reviewed_at = now();
        $document->save();
    }

    public function deleteDocument(DriverDocument $document): void
    {
        Storage::disk('public')->delete($document->file_path);
        $document->delete();
    }

    /**
     * Removes a driver record entirely (e.g. one created against the wrong
     * phone number by mistake). Uploaded document files are deleted first —
     * the `driver_documents` rows themselves cascade-delete at the DB level
     * — and the linked user's `type` is reverted, since creating a driver
     * always overwrites it and there's nothing else to undo that.
     */
    public function deleteDriver(Driver $driver, ?string $revertType): void
    {
        foreach ($driver->documents as $document) {
            Storage::disk('public')->delete($document->file_path);
        }

        $user = $driver->user;
        $driver->delete();

        if ($revertType !== null && $user) {
            $user->type = $revertType;
            $user->save();
        }
    }
}

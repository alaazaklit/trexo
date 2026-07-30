<?php

namespace App\Services\Customer;

use App\Models\User;
use App\TripRating;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class CustomerService
{
    public const STATUSES = ['active', 'suspended', 'banned'];

    /**
     * @param array{search?: string, account_status?: string} $filters
     */
    public function filtered(array $filters): LengthAwarePaginator
    {
        $query = User::query()->where('type', 'seller')->latest('id');

        if (!empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        if (!empty($filters['account_status'])) {
            $query->where('account_status', $filters['account_status']);
        }

        return $query->paginate(25)->withQueryString();
    }

    public function profile(User $customer): array
    {
        return [
            'orders' => $customer->orders()->latest()->limit(20)->get(),
            'rating_given_avg' => TripRating::where('rater_user_id', $customer->id)->avg('rating'),
            'rating_received_avg' => TripRating::where('rated_user_id', $customer->id)->avg('rating'),
        ];
    }

    public function updateStatus(User $customer, string $status, ?string $reason): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid account status: {$status}");
        }

        $customer->account_status = $status;
        $customer->status_reason = $reason;
        $customer->status_changed_at = now();
        $customer->status_changed_by = Auth::id();
        $customer->save();
    }

    public function setVerified(User $customer, bool $verified): void
    {
        $customer->is_verified = $verified;
        $customer->save();
    }
}

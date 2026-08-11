<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;
use TCG\Voyager\Models\Setting;

class WalletController extends Controller
{
    private function debtLimit(): float
    {
        $value = Setting::where('key', 'wallet.debt_limit')->value('value');

        return $value === null || $value === '' ? 50.0 : (float) $value;
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['driver_id', 'service_type', 'date_from', 'date_to']);

        $query = Transaction::query()->with(['driver.user', 'customer'])->latest('id');

        if (!empty($filters['driver_id'])) {
            $query->where('driver_id', $filters['driver_id']);
        }
        if (!empty($filters['service_type'])) {
            $query->where('service_type', $filters['service_type']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $debtLimit = $this->debtLimit();

        return view('admin.wallet.index', [
            'pageTitle' => 'Wallet Ledger',
            'transactions' => $query->paginate(25)->withQueryString(),
            'filters' => $filters,
            'drivers' => Driver::with('user')->get(),
            'debtLimit' => $debtLimit,
            'overLimitDrivers' => Driver::with(['user', 'wallet'])
                ->whereHas('wallet', fn ($q) => $q->where('commission_owed', '>', $debtLimit))
                ->get()
                ->sortByDesc(fn (Driver $driver) => (float) $driver->wallet->commission_owed)
                ->values(),
        ]);
    }

    public function show(Driver $driver): View
    {
        $driver->load(['user', 'wallet']);

        return view('admin.wallet.show', [
            'pageTitle' => 'Wallet: '.($driver->user->name ?: $driver->user->phone),
            'driver' => $driver,
            'transactions' => $driver->transactions()->latest('id')->paginate(25),
            'debtLimit' => $this->debtLimit(),
        ]);
    }
}

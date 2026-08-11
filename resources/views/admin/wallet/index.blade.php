@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Wallet Ledger')

@section('content')
    <div class="page-content browse container-fluid">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Over Debt Limit (${{ number_format($debtLimit, 2) }})</h3></div>
            <div class="panel-body">
                @if ($overLimitDrivers->isEmpty())
                    <p class="text-muted">No drivers currently over the limit.</p>
                @else
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Driver</th>
                                <th>Phone</th>
                                <th>Owes Trexo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($overLimitDrivers as $driver)
                                <tr class="danger">
                                    <td>{{ $driver->user->name ?: '—' }}</td>
                                    <td>{{ $driver->user->phone }}</td>
                                    <td>
                                        ${{ number_format($driver->wallet->commission_owed, 2) }}
                                        <span class="label label-danger">Over Limit</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.wallet.show', $driver) }}" class="btn btn-xs btn-default">View Wallet</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Filter</h3></div>
            <div class="panel-body">
                <form method="GET" action="{{ route('admin.wallet.index') }}" class="form-inline">
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="driver_id" class="form-control">
                            <option value="">All drivers</option>
                            @foreach ($drivers as $driver)
                                <option value="{{ $driver->id }}" @selected(($filters['driver_id'] ?? null) == $driver->id)>{{ $driver->user->name ?: $driver->user->phone }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="service_type" class="form-control">
                            <option value="">All services</option>
                            <option value="taxi" @selected(($filters['service_type'] ?? null) === 'taxi')>Taxi</option>
                            <option value="delivery" @selected(($filters['service_type'] ?? null) === 'delivery')>Delivery</option>
                            <option value="bus" @selected(($filters['service_type'] ?? null) === 'bus')>Bus</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.wallet.index') }}" class="btn btn-default">Reset</a>
                </form>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Driver</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Total Amount</th>
                            <th>Commission</th>
                            <th>Driver Earnings</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $transaction)
                            <tr>
                                <td>{{ $transaction->created_at?->format('Y-m-d H:i') }}</td>
                                <td>
                                    @if ($transaction->driver)
                                        <a href="{{ route('admin.wallet.show', $transaction->driver) }}">{{ $transaction->driver->user->name ?: $transaction->driver->user->phone }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $transaction->customer->name ?? '—' }}</td>
                                <td>{{ ucfirst($transaction->service_type) }}</td>
                                <td>${{ number_format($transaction->total_amount, 2) }}</td>
                                <td>{{ $transaction->commission_percentage }}% (${{ number_format($transaction->commission_amount, 2) }})</td>
                                <td>${{ number_format($transaction->driver_earnings, 2) }}</td>
                                <td>
                                    <span class="label label-{{ match($transaction->status) {
                                        'completed' => 'success',
                                        'failed' => 'danger',
                                        default => 'default',
                                    } }}">
                                        {{ ucfirst($transaction->status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center">No transactions match these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{ $transactions->links() }}
            </div>
        </div>
    </div>
@endsection

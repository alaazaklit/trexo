@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Wallet')

@section('content')
    <div class="page-content container-fluid">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Driver</h3></div>
            <div class="panel-body">
                <p><strong>Name:</strong> {{ $driver->user->name ?: '—' }}</p>
                <p><strong>Phone:</strong> {{ $driver->user->phone }}</p>
                <p>
                    <a href="{{ route('admin.drivers.show', $driver) }}" class="btn btn-sm btn-default">View Driver</a>
                </p>
                <h2>
                    Owes Trexo: ${{ number_format($driver->wallet->commission_owed ?? 0, 2) }}
                    @if (($driver->wallet->commission_owed ?? 0) > $debtLimit)
                        <span class="label label-danger" style="font-size: 13px; vertical-align: middle;">Over Limit (${{ number_format($debtLimit, 2) }})</span>
                    @endif
                </h2>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Transactions</h3></div>
            <div class="panel-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
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
                                <td colspan="7" class="text-center">No transactions yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{ $transactions->links() }}
            </div>
        </div>
    </div>
@endsection

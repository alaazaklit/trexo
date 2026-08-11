@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Financial Report')

@section('content')
    <div class="page-content browse container-fluid">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Filter</h3></div>
            <div class="panel-body">
                <form method="GET" action="{{ route('admin.financial-reports.index') }}" class="form-inline">
                    <div class="form-group" style="margin-right: 10px;">
                        <label style="margin-right: 5px;">From</label>
                        <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <label style="margin-right: 5px;">To</label>
                        <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.financial-reports.index') }}" class="btn btn-default">Reset</a>
                </form>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Totals</h3></div>
            <div class="panel-body">
                <p><strong>Orders Count:</strong> {{ number_format($totals['orders_count'] ?? 0) }}</p>
                <p><strong>Total Order Value:</strong> ${{ number_format($totals['total_value'] ?? 0, 2) }}</p>
                <p><strong>Trexo Profit:</strong> ${{ number_format($totals['trexo_profit'] ?? 0, 2) }}</p>
                <p><strong>Driver Profit:</strong> ${{ number_format($totals['driver_profit'] ?? 0, 2) }}</p>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Driver Name</th>
                            <th>Orders Count</th>
                            <th>Total Order Value</th>
                            <th>Trexo Profit</th>
                            <th>Driver Profit</th>
                            <th>Current Subscription</th>
                            <th>Subscription Expiry</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row->driver->user->name ?? ($row->driver->user->phone ?? '—') }}</td>
                                <td>{{ number_format($row->orders_count) }}</td>
                                <td>${{ number_format($row->total_value, 2) }}</td>
                                <td>${{ number_format($row->trexo_profit, 2) }}</td>
                                <td>${{ number_format($row->driver_profit, 2) }}</td>
                                <td>{{ $row->current_plan }}</td>
                                <td>{{ $row->subscription_expiry ? \Illuminate\Support\Carbon::parse($row->subscription_expiry)->format('Y-m-d') : 'Never' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">No transactions in this date range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th>{{ number_format($totals['orders_count'] ?? 0) }}</th>
                                <th>${{ number_format($totals['total_value'] ?? 0, 2) }}</th>
                                <th>${{ number_format($totals['trexo_profit'] ?? 0, 2) }}</th>
                                <th>${{ number_format($totals['driver_profit'] ?? 0, 2) }}</th>
                                <th></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
@endsection

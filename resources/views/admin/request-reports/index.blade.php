@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Requests Report')

@section('content')
    <div class="page-content browse container-fluid">
        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Filter</h3></div>
            <div class="panel-body">
                <form method="GET" action="{{ route('admin.request-reports.index') }}" class="form-inline">
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="seller_id" class="form-control">
                            <option value="">— Any seller —</option>
                            @foreach ($sellers as $seller)
                                <option value="{{ $seller->id }}" @selected(($filters['seller_id'] ?? null) == $seller->id)>{{ $seller->name ?: $seller->phone }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="driver_id" class="form-control">
                            <option value="">— Any driver —</option>
                            @foreach ($drivers as $driver)
                                <option value="{{ $driver->id }}" @selected(($filters['driver_id'] ?? null) == $driver->id)>{{ $driver->name ?: $driver->phone }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="request_type" class="form-control">
                            <option value="">— Any type —</option>
                            <option value="order" @selected(($filters['request_type'] ?? null) === 'order')>Order</option>
                            <option value="reservation" @selected(($filters['request_type'] ?? null) === 'reservation')>Reservation</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="outcome" class="form-control">
                            <option value="">— Any outcome —</option>
                            @foreach (['pending', 'accepted', 'rejected', 'expired', 'canceled'] as $outcome)
                                <option value="{{ $outcome }}" @selected(($filters['outcome'] ?? null) === $outcome)>{{ ucfirst($outcome) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <label style="margin-right: 5px;">From</label>
                        <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <label style="margin-right: 5px;">To</label>
                        <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.request-reports.index') }}" class="btn btn-default">Reset</a>
                </form>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Totals</h3></div>
            <div class="panel-body">
                <p>
                    <strong>Accepted:</strong> {{ number_format($totals['accepted'] ?? 0) }} &nbsp;|&nbsp;
                    <strong>Rejected:</strong> {{ number_format($totals['rejected'] ?? 0) }} &nbsp;|&nbsp;
                    <strong>No Response (Expired):</strong> {{ number_format($totals['expired'] ?? 0) }} &nbsp;|&nbsp;
                    <strong>Canceled:</strong> {{ number_format($totals['canceled'] ?? 0) }} &nbsp;|&nbsp;
                    <strong>Pending:</strong> {{ number_format($totals['pending'] ?? 0) }}
                </p>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Ref</th>
                            <th>Seller</th>
                            <th>Driver</th>
                            <th>Kind</th>
                            <th>Price</th>
                            <th>Sent At</th>
                            <th>Responded At</th>
                            <th>Response Time</th>
                            <th>Outcome</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td>{{ ucfirst($log->request_type) }}</td>
                                <td>
                                    @if ($log->order)
                                        <a href="{{ route('voyager.orders.edit', $log->order_id) }}">#{{ $log->order_id }}</a>
                                    @elseif ($log->reservation)
                                        {{ $log->reservation->tracking_id ?? ('#'.$log->reservation_id) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $log->seller->name && $log->seller->phone ? $log->seller->name.'('.$log->seller->phone.')' : ($log->seller->name ?: ($log->seller->phone ?: '—')) }}</td>
                                <td>{{ $log->driver->name && $log->driver->phone ? $log->driver->name.'('.$log->driver->phone.')' : ($log->driver->name ?: ($log->driver->phone ?: '—')) }}</td>
                                <td>{{ $log->order_kind ?? '—' }}</td>
                                <td>{{ $log->price !== null ? number_format($log->price, 2) : '—' }}</td>
                                <td>{{ $log->sent_at?->format('Y-m-d H:i:s') }}</td>
                                <td>{{ $log->responded_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                                <td>{{ $log->responded_at ? $log->sent_at->diffForHumans($log->responded_at, true) : '—' }}</td>
                                <td>
                                    <span class="label label-{{ match($log->outcome) {
                                        'accepted' => 'success',
                                        'rejected' => 'danger',
                                        'expired' => 'warning',
                                        'canceled' => 'default',
                                        default => 'info',
                                    } }}">
                                        {{ $log->outcome === 'expired' ? 'No response' : ucfirst($log->outcome) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center">No requests found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $logs->links() }}
            </div>
        </div>
    </div>
@endsection

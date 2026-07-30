@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Reservations')

@section('content')
    <div class="page-content container-fluid">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Filter</h3></div>
            <div class="panel-body">
                <form method="GET" action="{{ route('admin.reservations.index') }}" class="form-inline">
                    <div class="form-group" style="margin-right:10px;">
                        <select name="status" class="form-control">
                            <option value="">All statuses</option>
                            @foreach (['pending', 'accepted', 'rejected', 'completed', 'cancelled'] as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.reservations.index') }}" class="btn btn-default">Reset</a>
                </form>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th><th>Seller</th><th>Driver</th><th>Kind</th><th>Status</th><th>When</th><th>Reassign</th><th>Cancel</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reservations as $reservation)
                            <tr>
                                <td>{{ $reservation->id }}</td>
                                <td>{{ $reservation->seller->name ?? '—' }}</td>
                                <td>{{ $reservation->driver->name ?? 'Unassigned' }}</td>
                                <td>{{ $reservation->order_kind }}</td>
                                <td>{{ $reservation->status }}</td>
                                <td>{{ $reservation->start_date_time?->format('Y-m-d H:i') }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.reservations.reassign', $reservation) }}" class="form-inline">
                                        @csrf
                                        <select name="driver_id" class="form-control input-sm">
                                            @foreach ($drivers as $driver)
                                                <option value="{{ $driver->id }}">{{ $driver->name }} ({{ $driver->phone }})</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-xs btn-default">Assign</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.reservations.cancel', $reservation) }}" class="form-inline" onsubmit="return confirm('Cancel this reservation?');">
                                        @csrf
                                        <input type="text" name="reason" class="form-control input-sm" placeholder="Reason" required>
                                        <button type="submit" class="btn btn-xs btn-danger">Cancel</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center">No reservations match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                {{ $reservations->links() }}
            </div>
        </div>
    </div>
@endsection

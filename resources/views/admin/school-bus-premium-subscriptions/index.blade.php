@extends('voyager::master')

@section('page_title', $pageTitle ?? 'School Bus Premium Subscriptions')

@section('content')
    <div class="page-content browse container-fluid">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Filter</h3></div>
            <div class="panel-body">
                <form method="GET" action="{{ route('admin.school-bus-premium-subscriptions.index') }}" class="form-inline">
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="status" class="form-control">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.school-bus-premium-subscriptions.index') }}" class="btn btn-default">Reset</a>
                </form>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Parent</th>
                            <th>Driver</th>
                            <th>Plan</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($premiumSubscriptions as $premium)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.school-bus-premium-subscriptions.show', $premium) }}">
                                        {{ $premium->subscription->student_name ?? '—' }}
                                    </a>
                                </td>
                                <td>{{ $premium->parentUser->name ?? '—' }}</td>
                                <td>{{ $premium->subscription->driver->user->name ?? '—' }}</td>
                                <td>{{ ucfirst($premium->plan) }}</td>
                                <td>${{ number_format((float) $premium->price, 2) }}</td>
                                <td>
                                    <span class="label label-{{ match($premium->status) {
                                        'active' => 'success',
                                        'rejected' => 'danger',
                                        'expired', 'cancelled' => 'default',
                                        default => 'warning',
                                    } }}">
                                        {{ ucfirst($premium->status) }}
                                    </span>
                                </td>
                                <td>{{ $premium->created_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">No Premium requests match these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{ $premiumSubscriptions->links() }}
            </div>
        </div>
    </div>
@endsection

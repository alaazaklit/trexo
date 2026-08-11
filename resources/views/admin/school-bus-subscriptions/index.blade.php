@extends('voyager::master')

@section('page_title', $pageTitle ?? 'School Bus Subscriptions')

@section('content')
    <div class="page-content browse container-fluid">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Filter</h3></div>
            <div class="panel-body">
                <form method="GET" action="{{ route('admin.school-bus-subscriptions.index') }}" class="form-inline">
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="status" class="form-control">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.school-bus-subscriptions.index') }}" class="btn btn-default">Reset</a>
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
                            <th>School</th>
                            <th>Pickup Area</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subscriptions as $subscription)
                            <tr>
                                <td><a href="{{ route('admin.school-bus-subscriptions.show', $subscription) }}">{{ $subscription->student_name }}</a></td>
                                <td>{{ $subscription->parent_name }}</td>
                                <td>{{ $subscription->driver->user->name ?: '—' }}</td>
                                <td>{{ $subscription->route->school->name ?? '—' }}</td>
                                <td>{{ $subscription->route->pickup_area ?? '—' }}</td>
                                <td>
                                    <span class="label label-{{ match($subscription->status) {
                                        'active' => 'success',
                                        'rejected' => 'danger',
                                        default => 'default',
                                    } }}">
                                        {{ ucfirst($subscription->status) }}
                                    </span>
                                </td>
                                <td>{{ $subscription->created_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">No subscriptions match these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{ $subscriptions->links() }}
            </div>
        </div>
    </div>
@endsection

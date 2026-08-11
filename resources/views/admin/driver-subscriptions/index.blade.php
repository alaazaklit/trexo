@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Driver Subscriptions')

@section('content')
    <div class="page-content browse container-fluid">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Filter</h3></div>
            <div class="panel-body">
                <form method="GET" action="{{ route('admin.driver-subscriptions.index') }}" class="form-inline">
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="payment_status" class="form-control">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(($filters['payment_status'] ?? null) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="plan_id" class="form-control">
                            <option value="">All plans</option>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}" @selected(($filters['plan_id'] ?? null) == $plan->id)>{{ $plan->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.driver-subscriptions.index') }}" class="btn btn-default">Reset</a>
                </form>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Driver</th>
                            <th>Phone</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subscriptions as $subscription)
                            <tr>
                                <td><a href="{{ route('admin.driver-subscriptions.show', $subscription) }}">{{ $subscription->driver->user->name ?: '—' }}</a></td>
                                <td>{{ $subscription->driver->user->phone }}</td>
                                <td>{{ $subscription->plan->name ?? '—' }}</td>
                                <td>
                                    <span class="label label-{{ match($subscription->payment_status) {
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        default => 'default',
                                    } }}">
                                        {{ ucfirst($subscription->payment_status) }}
                                    </span>
                                </td>
                                <td>{{ $subscription->start_date?->format('Y-m-d') ?: '—' }}</td>
                                <td>{{ $subscription->end_date?->format('Y-m-d') ?: '—' }}</td>
                                <td>{{ $subscription->created_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">No subscription requests match these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{ $subscriptions->links() }}
            </div>
        </div>
    </div>
@endsection

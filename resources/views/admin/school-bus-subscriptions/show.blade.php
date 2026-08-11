@extends('voyager::master')

@section('page_title', $pageTitle ?? 'School Bus Subscription')

@section('content')
    <div class="page-content container-fluid">
        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Student &amp; Parent</h3></div>
                    <div class="panel-body">
                        <p><strong>Student:</strong> {{ $subscription->student_name }}</p>
                        <p><strong>Parent:</strong> {{ $subscription->parent_name }}</p>
                        <p><strong>Phone:</strong> {{ $subscription->phone }}</p>
                        <p><strong>Address:</strong> {{ $subscription->address }}</p>
                        <p><strong>Notes:</strong> {{ $subscription->notes ?: '—' }}</p>
                        <p><strong>Parent account:</strong> {{ $subscription->parentUser->name ?: $subscription->parentUser->phone }}</p>
                    </div>
                </div>

                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Route</h3></div>
                    <div class="panel-body">
                        <p><strong>Driver:</strong> {{ $subscription->driver->user->name ?: '—' }} ({{ $subscription->driver->user->phone }})</p>
                        <p><strong>School:</strong> {{ $subscription->route->school->name ?? '—' }}</p>
                        <p><strong>Pickup Area:</strong> {{ $subscription->route->pickup_area ?? '—' }}</p>
                        <p><strong>Monthly Price:</strong> {{ $subscription->route->monthly_price ?? '—' }}</p>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Status</h3></div>
                    <div class="panel-body">
                        <p>
                            <span class="label label-{{ match($subscription->status) {
                                'active' => 'success',
                                'rejected' => 'danger',
                                default => 'default',
                            } }}">
                                {{ ucfirst($subscription->status) }}
                            </span>
                        </p>
                        <p><strong>Submitted:</strong> {{ $subscription->created_at?->format('Y-m-d H:i') }}</p>
                        @if ($subscription->accepted_at)
                            <p><strong>Accepted:</strong> {{ $subscription->accepted_at->format('Y-m-d H:i') }}</p>
                        @endif
                        @if ($subscription->status === 'rejected' && $subscription->rejection_reason)
                            <p><strong>Rejection Reason:</strong> {{ $subscription->rejection_reason }}</p>
                        @endif
                        <p class="text-muted">Accept/reject is handled by the driver in the app.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

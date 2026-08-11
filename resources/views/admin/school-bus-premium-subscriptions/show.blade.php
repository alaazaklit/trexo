@extends('voyager::master')

@section('page_title', $pageTitle ?? 'School Bus Premium Request')

@section('content')
    <div class="page-content container-fluid">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>School Bus Request</h3></div>
                    <div class="panel-body">
                        <p><strong>Student:</strong> {{ $premium->subscription->student_name ?? '—' }}</p>
                        <p><strong>School:</strong> {{ $premium->subscription->route->school->name ?? '—' }}</p>
                        <p><strong>Pickup Area:</strong> {{ $premium->subscription->route->pickup_area ?? '—' }}</p>
                        <p><strong>Driver:</strong> {{ $premium->subscription->driver->user->name ?? '—' }}</p>
                    </div>
                </div>

                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Parent</h3></div>
                    <div class="panel-body">
                        <p><strong>Name:</strong> {{ $premium->parentUser->name ?? '—' }}</p>
                        <p><strong>Phone:</strong> {{ $premium->parentUser->phone ?? $premium->subscription->phone ?? '—' }}</p>
                    </div>
                </div>

                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Premium Details</h3></div>
                    <div class="panel-body">
                        <p><strong>Plan:</strong> {{ ucfirst($premium->plan) }}</p>
                        <p><strong>Price:</strong> ${{ number_format((float) $premium->price, 2) }}</p>
                        <p>
                            <strong>Status:</strong>
                            <span class="label label-{{ match($premium->status) {
                                'active' => 'success',
                                'rejected' => 'danger',
                                'expired', 'cancelled' => 'default',
                                default => 'warning',
                            } }}">
                                {{ ucfirst($premium->status) }}
                            </span>
                        </p>
                        <p><strong>Started:</strong> {{ $premium->started_at?->format('Y-m-d') ?: '—' }}</p>
                        <p><strong>Expires:</strong> {{ $premium->expires_at?->format('Y-m-d') ?: '—' }}</p>
                        <p><strong>Submitted:</strong> {{ $premium->created_at?->format('Y-m-d H:i') }}</p>
                        @if ($premium->reviewed_at)
                            <p class="text-muted">Reviewed: {{ $premium->reviewed_at->format('Y-m-d H:i') }} by {{ $premium->reviewedBy->name ?? '—' }}</p>
                        @endif
                        @if ($premium->status === 'rejected' && $premium->rejection_reason)
                            <p><strong>Rejection Reason:</strong> {{ $premium->rejection_reason }}</p>
                        @endif
                    </div>
                </div>

                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Receipt</h3></div>
                    <div class="panel-body">
                        @if ($premium->receipt_path)
                            @if (in_array(strtolower(pathinfo($premium->receipt_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png']))
                                <a href="{{ asset('storage/'.$premium->receipt_path) }}" target="_blank">
                                    <img src="{{ asset('storage/'.$premium->receipt_path) }}" alt="Receipt" style="max-width: 100%; border: 1px solid #ddd;">
                                </a>
                            @else
                                <a href="{{ asset('storage/'.$premium->receipt_path) }}" target="_blank" class="btn btn-default">View Receipt</a>
                            @endif
                        @else
                            <p class="text-muted">No receipt uploaded.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                @if ($premium->status === 'pending')
                    <div class="panel panel-bordered">
                        <div class="panel-heading"><h3>Review</h3></div>
                        <div class="panel-body">
                            <form method="POST" action="{{ route('admin.school-bus-premium-subscriptions.approve', $premium) }}" onsubmit="return confirm('Approve this Premium request?');">
                                @csrf
                                <button type="submit" class="btn btn-success">Approve</button>
                            </form>

                            <hr>

                            <form method="POST" action="{{ route('admin.school-bus-premium-subscriptions.reject', $premium) }}" onsubmit="return confirm('Reject this Premium request?');">
                                @csrf
                                <div class="form-group">
                                    <label>Rejection Reason</label>
                                    <textarea name="rejection_reason" class="form-control" rows="3" placeholder="Optional reason…"></textarea>
                                </div>
                                <button type="submit" class="btn btn-danger">Reject</button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

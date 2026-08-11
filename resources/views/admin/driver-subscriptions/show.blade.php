@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Subscription Request')

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
                    <div class="panel-heading"><h3>Driver</h3></div>
                    <div class="panel-body">
                        <p><strong>Name:</strong> {{ $subscription->driver->user->name ?: '—' }}</p>
                        <p><strong>Phone:</strong> {{ $subscription->driver->user->phone }}</p>
                        <p>
                            <a href="{{ route('admin.drivers.show', $subscription->driver) }}" class="btn btn-sm btn-default">View Driver</a>
                        </p>
                    </div>
                </div>

                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Subscription Details</h3></div>
                    <div class="panel-body">
                        <p><strong>Plan:</strong> {{ $subscription->plan->name ?? '—' }}</p>
                        <p><strong>Commission % (snapshot):</strong> {{ $subscription->commission_percentage_snapshot }}%</p>
                        <p><strong>Payment Method:</strong> {{ $subscription->payment_method ?: '—' }}</p>
                        <p>
                            <strong>Status:</strong>
                            <span class="label label-{{ match($subscription->payment_status) {
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default => 'default',
                            } }}">
                                {{ ucfirst($subscription->payment_status) }}
                            </span>
                        </p>
                        <p><strong>Start Date:</strong> {{ $subscription->start_date?->format('Y-m-d') ?: '—' }}</p>
                        <p><strong>End Date:</strong> {{ $subscription->end_date?->format('Y-m-d') ?: '—' }}</p>
                        <p><strong>Renewal Date:</strong> {{ $subscription->renewal_date?->format('Y-m-d') ?: '—' }}</p>
                        <p><strong>Submitted:</strong> {{ $subscription->created_at?->format('Y-m-d H:i') }}</p>
                        @if ($subscription->reviewed_at)
                            <p class="text-muted">Reviewed: {{ $subscription->reviewed_at->format('Y-m-d H:i') }}</p>
                        @endif
                        @if ($subscription->payment_status === 'rejected' && $subscription->rejection_reason)
                            <p><strong>Rejection Reason:</strong> {{ $subscription->rejection_reason }}</p>
                        @endif
                    </div>
                </div>

                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Receipt</h3></div>
                    <div class="panel-body">
                        @if ($subscription->receipt_path)
                            @if (in_array(strtolower(pathinfo($subscription->receipt_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png']))
                                <a href="{{ asset('storage/'.$subscription->receipt_path) }}" target="_blank">
                                    <img src="{{ asset('storage/'.$subscription->receipt_path) }}" alt="Receipt" style="max-width: 100%; border: 1px solid #ddd;">
                                </a>
                            @else
                                <a href="{{ asset('storage/'.$subscription->receipt_path) }}" target="_blank" class="btn btn-default">View Receipt</a>
                            @endif
                        @else
                            <p class="text-muted">No receipt uploaded.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                @if ($subscription->payment_status === 'pending')
                    <div class="panel panel-bordered">
                        <div class="panel-heading"><h3>Review</h3></div>
                        <div class="panel-body">
                            <form method="POST" action="{{ route('admin.driver-subscriptions.approve', $subscription) }}" onsubmit="return confirm('Approve this subscription request?');">
                                @csrf
                                <button type="submit" class="btn btn-success">Approve</button>
                            </form>

                            <hr>

                            <form method="POST" action="{{ route('admin.driver-subscriptions.reject', $subscription) }}" onsubmit="return confirm('Reject this subscription request?');">
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

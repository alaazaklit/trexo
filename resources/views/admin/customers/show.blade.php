@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Customer')

@section('content')
    <div class="page-content container-fluid">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Profile</h3></div>
                    <div class="panel-body">
                        <table class="table table-striped">
                            <tbody>
                                <tr><td><strong>Name</strong></td><td>{{ $customer->name ?: '—' }}</td></tr>
                                <tr><td><strong>Phone</strong></td><td>{{ $customer->phone }}</td></tr>
                                <tr><td><strong>Email</strong></td><td>{{ $customer->email ?: '—' }}</td></tr>
                                <tr><td><strong>Verified</strong></td><td>{{ $customer->is_verified ? 'Yes' : 'No' }}</td></tr>
                                <tr><td><strong>Account status</strong></td><td>{{ ucfirst($customer->account_status) }}</td></tr>
                                <tr><td><strong>Status reason</strong></td><td>{{ $customer->status_reason ?: '—' }}</td></tr>
                                <tr><td><strong>Rating given (avg)</strong></td><td>{{ $profile['rating_given_avg'] ? round($profile['rating_given_avg'], 2) : '—' }}</td></tr>
                                <tr><td><strong>Rating received (avg)</strong></td><td>{{ $profile['rating_received_avg'] ? round($profile['rating_received_avg'], 2) : '—' }}</td></tr>
                            </tbody>
                        </table>

                        <form method="POST" action="{{ route('admin.customers.toggle-verified', $customer) }}" style="display:inline-block; margin-right: 8px;">
                            @csrf
                            <button type="submit" class="btn btn-default">{{ $customer->is_verified ? 'Unverify' : 'Verify' }}</button>
                        </form>
                    </div>
                </div>

                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Account Status</h3></div>
                    <div class="panel-body">
                        <form method="POST" action="{{ route('admin.customers.update-status', $customer) }}">
                            @csrf
                            <div class="form-group">
                                <select name="account_status" class="form-control">
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status }}" @selected($customer->account_status === $status)>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <textarea name="reason" class="form-control" placeholder="Reason (required for suspend/ban)">{{ $customer->status_reason }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Status</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Recent Orders</h3></div>
                    <div class="panel-body">
                        <table class="table table-hover">
                            <thead>
                                <tr><th>ID</th><th>Kind</th><th>Status</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($profile['orders'] as $order)
                                    <tr>
                                        <td><a href="{{ route('voyager.orders.edit', $order->id) }}">#{{ $order->id }}</a></td>
                                        <td>{{ $order->order_kind }}</td>
                                        <td>{{ $order->status }}</td>
                                        <td>{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center">No orders yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Commission Payments')

@section('content')
    <div class="page-content browse container-fluid">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Filter</h3></div>
            <div class="panel-body">
                <form method="GET" action="{{ route('admin.commission-payments.index') }}" class="form-inline">
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="status" class="form-control">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.commission-payments.index') }}" class="btn btn-default">Reset</a>
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
                            <th>Amount Paid</th>
                            <th>Receipt</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Reviewed</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr>
                                <td>{{ $payment->driver->user->name ?: '—' }}</td>
                                <td>{{ $payment->driver->user->phone }}</td>
                                <td>${{ number_format($payment->amount, 2) }}</td>
                                <td>
                                    @if ($payment->receipt_path)
                                        @if (in_array(strtolower(pathinfo($payment->receipt_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png']))
                                            <a href="{{ asset('storage/'.$payment->receipt_path) }}" target="_blank">
                                                <img src="{{ asset('storage/'.$payment->receipt_path) }}" alt="Receipt" style="max-height: 60px;">
                                            </a>
                                        @else
                                            <a href="{{ asset('storage/'.$payment->receipt_path) }}" target="_blank" class="btn btn-xs btn-default">View</a>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="label label-{{ match($payment->status) {
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        default => 'default',
                                    } }}">
                                        {{ ucfirst($payment->status) }}
                                    </span>
                                    @if ($payment->status === 'rejected' && $payment->rejection_reason)
                                        <div class="text-muted" style="font-size: 11px;">{{ $payment->rejection_reason }}</div>
                                    @endif
                                </td>
                                <td>{{ $payment->created_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ $payment->reviewed_at?->format('Y-m-d H:i') ?: '—' }}</td>
                                <td>
                                    @if ($payment->status === 'pending')
                                        <form method="POST" action="{{ route('admin.commission-payments.approve', $payment) }}" style="display:inline-block;" onsubmit="return confirm('Confirm you verified this Wish Money receipt and the driver\'s owed balance should be reduced?');">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-success">Approve</button>
                                        </form>

                                        <button type="button" class="btn btn-xs btn-danger" data-toggle="collapse" data-target="#reject-{{ $payment->id }}">Reject</button>

                                        <div id="reject-{{ $payment->id }}" class="collapse" style="margin-top: 8px;">
                                            <form method="POST" action="{{ route('admin.commission-payments.reject', $payment) }}" onsubmit="return confirm('Reject this payment report?');">
                                                @csrf
                                                <div class="form-group">
                                                    <textarea name="rejection_reason" class="form-control" rows="2" placeholder="Optional reason…"></textarea>
                                                </div>
                                                <button type="submit" class="btn btn-xs btn-danger">Confirm Reject</button>
                                            </form>
                                        </div>
                                    @endif

                                    <form method="POST" action="{{ route('admin.commission-payments.notify', $payment) }}" style="display:inline-block; margin-top: 4px;" onsubmit="return confirm('Send this driver a notification with the current status of this payment?');">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-default"><i class="voyager-bell"></i> Send Notification</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center">No commission payments match these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{ $payments->links() }}
            </div>
        </div>
    </div>
@endsection

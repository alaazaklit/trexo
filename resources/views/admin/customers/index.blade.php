@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Customers')

@section('content')
    <div class="page-content browse container-fluid">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3>Filter</h3>
            </div>
            <div class="panel-body">
                <form method="GET" action="{{ route('admin.customers.index') }}" class="form-inline">
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="text" name="search" class="form-control" placeholder="Name, phone, or email" value="{{ $filters['search'] ?? '' }}">
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="account_status" class="form-control">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(($filters['account_status'] ?? null) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.customers.index') }}" class="btn btn-default">Reset</a>
                </form>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Verified</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr>
                                <td>{{ $customer->name ?: '—' }}</td>
                                <td>{{ $customer->phone }}</td>
                                <td>{{ $customer->email ?: '—' }}</td>
                                <td>{{ $customer->is_verified ? 'Yes' : 'No' }}</td>
                                <td>
                                    <span class="label label-{{ $customer->account_status === 'active' ? 'success' : ($customer->account_status === 'suspended' ? 'warning' : 'danger') }}">
                                        {{ ucfirst($customer->account_status) }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-sm btn-default">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">No customers match these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{ $customers->links() }}
            </div>
        </div>
    </div>
@endsection

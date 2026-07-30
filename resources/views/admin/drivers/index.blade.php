@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Drivers')

@section('content')
    <div class="page-content browse container-fluid">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Create Driver</h3></div>
            <div class="panel-body">
                <p class="text-muted">
                    Enter a phone number. If a user account with that phone already exists (customer or otherwise),
                    it will be linked and switched to a driver account; if not, a new account is created.
                </p>
                <form method="POST" action="{{ route('admin.drivers.store') }}" class="form-inline">
                    @csrf
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="text" name="phone" class="form-control" placeholder="Phone number" required>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="text" name="license_number" class="form-control" placeholder="License # (optional)">
                    </div>
                    <button type="submit" class="btn btn-primary">Create Driver</button>
                </form>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Filter</h3></div>
            <div class="panel-body">
                <form method="GET" action="{{ route('admin.drivers.index') }}" class="form-inline">
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="text" name="search" class="form-control" placeholder="Name or phone" value="{{ $filters['search'] ?? '' }}">
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="approval_status" class="form-control">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(($filters['approval_status'] ?? null) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <label><input type="checkbox" name="expiring_soon" value="1" @checked(!empty($filters['expiring_soon']))> Documents expiring within 30 days</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.drivers.index') }}" class="btn btn-default">Reset</a>
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
                            <th>Online</th>
                            <th>Approval</th>
                            <th>Rating</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($drivers as $driver)
                            <tr>
                                <td>{{ $driver->user->name ?: '—' }}</td>
                                <td>{{ $driver->user->phone }}</td>
                                <td>{{ $driver->is_online ? 'Online' : 'Offline' }}</td>
                                <td>
                                    <span class="label label-{{ $driver->approval_status === 'approved' ? 'success' : ($driver->approval_status === 'pending' ? 'default' : 'danger') }}">
                                        {{ ucfirst($driver->approval_status) }}
                                    </span>
                                </td>
                                <td>{{ $driver->rating }}</td>
                                <td>
                                    <a href="{{ route('admin.drivers.show', $driver) }}" class="btn btn-sm btn-default">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">No drivers match these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{ $drivers->links() }}
            </div>
        </div>
    </div>
@endsection

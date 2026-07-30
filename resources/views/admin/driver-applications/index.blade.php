@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Driver Applications')

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
                <form method="GET" action="{{ route('admin.driver-applications.index') }}" class="form-inline">
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="text" name="search" class="form-control" placeholder="Name, mobile, national ID, or plate" value="{{ $filters['search'] ?? '' }}">
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="status" class="form-control">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="service_type" class="form-control">
                            <option value="">All services</option>
                            <option value="taxi" @selected(($filters['service_type'] ?? null) === 'taxi')>Taxi</option>
                            <option value="delivery" @selected(($filters['service_type'] ?? null) === 'delivery')>Delivery</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.driver-applications.index') }}" class="btn btn-default">Reset</a>
                </form>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>City</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($applications as $application)
                            <tr>
                                <td>{{ $application->full_name }}</td>
                                <td>{{ $application->mobile_number }}</td>
                                <td>{{ $application->city }}</td>
                                <td>{{ ucfirst($application->service_type) }}</td>
                                <td>
                                    <span class="label label-{{ match($application->status) {
                                        'approved' => 'success',
                                        'under_review' => 'info',
                                        'rejected' => 'danger',
                                        default => 'default',
                                    } }}">
                                        {{ ucfirst(str_replace('_', ' ', $application->status)) }}
                                    </span>
                                </td>
                                <td>{{ $application->created_at?->format('Y-m-d H:i') }}</td>
                                <td>
                                    <a href="{{ route('admin.driver-applications.show', $application) }}" class="btn btn-sm btn-default">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">No driver applications match these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{ $applications->links() }}
            </div>
        </div>
    </div>
@endsection

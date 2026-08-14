@extends('voyager::master')

@section('page_title', $pageTitle ?? 'School Bus Routes')

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
                <form method="GET" class="form-inline">
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="driver_id" class="form-control">
                            <option value="">— Any driver —</option>
                            @foreach ($drivers as $driver)
                                <option value="{{ $driver->id }}" @selected(($filters['driver_id'] ?? null) == $driver->id)>{{ $driver->user->name ?: $driver->user->phone }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="school_id" class="form-control">
                            <option value="">— Any school —</option>
                            @foreach ($schools as $school)
                                <option value="{{ $school->id }}" @selected(($filters['school_id'] ?? null) == $school->id)>{{ $school->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="is_active" class="form-control">
                            <option value="">— Any status —</option>
                            <option value="1" @selected(($filters['is_active'] ?? null) === '1')>Active</option>
                            <option value="0" @selected(($filters['is_active'] ?? null) === '0')>Inactive</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-default">Filter</button>
                </form>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Add Route</h3></div>
            <div class="panel-body">
                <form method="POST" action="{{ route('admin.school-bus-routes.store') }}" class="form-inline">
                    @csrf
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="driver_id" class="form-control" required>
                            <option value="">— Driver —</option>
                            @foreach ($drivers as $driver)
                                <option value="{{ $driver->id }}">{{ $driver->user->name ?: $driver->user->phone }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="school_id" class="form-control" required>
                            <option value="">— School —</option>
                            @foreach ($schools as $school)
                                <option value="{{ $school->id }}">{{ $school->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="text" name="pickup_area" class="form-control" placeholder="Pickup area" required>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="number" step="0.01" min="0" name="monthly_price" class="form-control" placeholder="Monthly price" required>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <label><input type="checkbox" name="is_active" value="1" checked> Active</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Route</button>
                </form>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Routes</h3></div>
            <div class="panel-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Driver</th>
                            <th>School</th>
                            <th>Pickup Area</th>
                            <th>Monthly Price</th>
                            <th>Active</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($routes as $route)
                            <tr>
                                <td colspan="6" style="padding: 0;">
                                    <form method="POST" action="{{ route('admin.school-bus-routes.update', $route) }}" class="form-inline" style="width: 100%;">
                                        @csrf
                                        @method('PUT')
                                        <table class="table" style="margin-bottom: 0;">
                                            <tr>
                                                <td>{{ $route->driver->user->name ?: $route->driver->user->phone }}</td>
                                                <td>{{ $route->school->name ?? '—' }}</td>
                                                <td><input type="text" name="pickup_area" class="form-control" value="{{ $route->pickup_area }}" required></td>
                                                <td><input type="number" step="0.01" min="0" name="monthly_price" class="form-control" value="{{ $route->monthly_price }}" required></td>
                                                <td><input type="checkbox" name="is_active" value="1" @checked($route->is_active)></td>
                                                <td>
                                                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                                </td>
                                            </tr>
                                        </table>
                                    </form>
                                    <form method="POST" action="{{ route('admin.school-bus-routes.destroy', $route) }}" onsubmit="return confirm('Delete this route?');" style="padding: 0 15px 10px;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-link text-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">No routes yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Audit Logs')

@section('content')
    <div class="page-content browse container-fluid">
        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3>Filter</h3>
            </div>
            <div class="panel-body">
                <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="form-inline">
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="user_id" class="form-control">
                            <option value="">All users</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected(($filters['user_id'] ?? null) == $user->id)>
                                    {{ $user->name ?: $user->phone }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="action" class="form-control">
                            <option value="">All actions</option>
                            @foreach (['created', 'updated', 'deleted'] as $action)
                                <option value="{{ $action }}" @selected(($filters['action'] ?? null) === $action)>
                                    {{ ucfirst($action) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <select name="auditable_type" class="form-control">
                            <option value="">All models</option>
                            @foreach ($auditableTypes as $type)
                                <option value="{{ $type }}" @selected(($filters['auditable_type'] ?? null) === $type)>
                                    {{ class_basename($type) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="date" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}" placeholder="From">
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="date" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}" placeholder="To">
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-default">Reset</a>
                </form>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Model</th>
                            <th>Changes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td>{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                                <td>{{ $log->user->name ?? $log->user->phone ?? '—' }}</td>
                                <td><span class="label label-default">{{ $log->action }}</span></td>
                                <td>{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</td>
                                <td>
                                    <a href="#" onclick="event.preventDefault(); this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none';">
                                        View diff
                                    </a>
                                    <pre style="display: none; white-space: pre-wrap;">Old: {{ json_encode($log->old_values, JSON_PRETTY_PRINT) }}

New: {{ json_encode($log->new_values, JSON_PRETTY_PRINT) }}</pre>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center">No audit log entries match these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{ $logs->links() }}
            </div>
        </div>
    </div>
@endsection

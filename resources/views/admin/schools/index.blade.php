@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Schools')

@section('content')
    <div class="page-content browse container-fluid">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Add School</h3></div>
            <div class="panel-body">
                <form method="POST" action="{{ route('admin.schools.store') }}" class="form-inline">
                    @csrf
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="text" name="name" class="form-control" placeholder="Name" required>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="text" name="area" class="form-control" placeholder="Area (optional)">
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <label><input type="checkbox" name="is_active" value="1" checked> Active</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Add School</button>
                </form>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Schools</h3></div>
            <div class="panel-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Area</th>
                            <th>Active</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($schools as $school)
                            <tr>
                                <td colspan="4" style="padding: 0;">
                                    <form method="POST" action="{{ route('admin.schools.update', $school) }}" class="form-inline" style="width: 100%;">
                                        @csrf
                                        @method('PUT')
                                        <table class="table" style="margin-bottom: 0;">
                                            <tr>
                                                <td><input type="text" name="name" class="form-control" value="{{ $school->name }}" required></td>
                                                <td><input type="text" name="area" class="form-control" value="{{ $school->area }}"></td>
                                                <td><input type="checkbox" name="is_active" value="1" @checked($school->is_active)></td>
                                                <td>
                                                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                                </td>
                                            </tr>
                                        </table>
                                    </form>
                                    <form method="POST" action="{{ route('admin.schools.destroy', $school) }}" onsubmit="return confirm('Delete this school? Routes linked to it will also be removed.');" style="padding: 0 15px 10px;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-link text-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center">No schools yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

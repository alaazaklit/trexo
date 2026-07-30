@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Vehicle Categories')

@section('content')
    <div class="page-content container-fluid">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Add Category</h3></div>
            <div class="panel-body">
                <form method="POST" action="{{ route('admin.vehicle-categories.store') }}" class="form-inline">
                    @csrf
                    <input type="text" name="name" class="form-control" placeholder="Name" required style="margin-right:8px;">
                    <input type="number" step="0.01" name="price_multiplier" class="form-control" placeholder="Multiplier" value="1.00" style="margin-right:8px; width:110px;">
                    <input type="number" name="capacity" class="form-control" placeholder="Capacity" value="4" style="margin-right:8px; width:100px;">
                    <input type="text" name="icon" class="form-control" placeholder="Icon class" style="margin-right:8px;">
                    <label style="margin-right:8px;"><input type="checkbox" name="supports_taxi" value="1" checked> Taxi</label>
                    <label style="margin-right:8px;"><input type="checkbox" name="supports_delivery" value="1" checked> Delivery</label>
                    <label style="margin-right:8px;"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                    <button type="submit" class="btn btn-primary">Add</button>
                </form>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th><th>Multiplier</th><th>Capacity</th><th>Taxi</th><th>Delivery</th><th>Active</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <form method="POST" action="{{ route('admin.vehicle-categories.update', $category) }}">
                                    @csrf
                                    @method('PUT')
                                    <td><input type="text" name="name" class="form-control" value="{{ $category->name }}"></td>
                                    <td><input type="number" step="0.01" name="price_multiplier" class="form-control" value="{{ $category->price_multiplier }}" style="width:90px;"></td>
                                    <td><input type="number" name="capacity" class="form-control" value="{{ $category->capacity }}" style="width:80px;"></td>
                                    <td><input type="checkbox" name="supports_taxi" value="1" @checked($category->supports_taxi)></td>
                                    <td><input type="checkbox" name="supports_delivery" value="1" @checked($category->supports_delivery)></td>
                                    <td><input type="checkbox" name="is_active" value="1" @checked($category->is_active)></td>
                                    <td>
                                        <button type="submit" class="btn btn-xs btn-primary">Save</button>
                                </form>
                                        <form method="POST" action="{{ route('admin.vehicle-categories.destroy', $category) }}" style="display:inline-block;" onsubmit="return confirm('Delete this category?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-danger">Delete</button>
                                        </form>
                                    </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center">No vehicle categories yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

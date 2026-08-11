@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Subscription Plans')

@section('content')
    <div class="page-content browse container-fluid">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Create New Plan</h3></div>
            <div class="panel-body">
                <form method="POST" action="{{ route('admin.subscription-plans.store') }}" class="form-inline">
                    @csrf
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="text" name="name" class="form-control" placeholder="Name" required>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="text" name="slug" class="form-control" placeholder="Slug" required>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="number" step="0.01" min="0" name="monthly_price" class="form-control" placeholder="Monthly price" required>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="number" step="0.01" min="0" max="100" name="commission_percentage" class="form-control" placeholder="Commission %" required>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <label><input type="checkbox" name="is_active" value="1" checked> Active</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Plan</button>
                </form>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Plans</h3></div>
            <div class="panel-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Monthly Price</th>
                            <th>Commission %</th>
                            <th>Active</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($plans as $plan)
                            <tr>
                                <td colspan="6" style="padding: 0;">
                                    <form method="POST" action="{{ route('admin.subscription-plans.update', $plan) }}" class="form-inline" style="width: 100%;">
                                        @csrf
                                        @method('PUT')
                                        <table class="table" style="margin-bottom: 0;">
                                            <tr>
                                                <td><input type="text" name="name" class="form-control" value="{{ $plan->name }}" required></td>
                                                <td>{{ $plan->slug }}</td>
                                                <td><input type="number" step="0.01" min="0" name="monthly_price" class="form-control" value="{{ $plan->monthly_price }}" required></td>
                                                <td><input type="number" step="0.01" min="0" max="100" name="commission_percentage" class="form-control" value="{{ $plan->commission_percentage }}" required></td>
                                                <td><input type="checkbox" name="is_active" value="1" @checked($plan->is_active)></td>
                                                <td><button type="submit" class="btn btn-sm btn-primary">Save</button></td>
                                            </tr>
                                        </table>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">No subscription plans yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

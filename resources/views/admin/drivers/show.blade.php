@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Driver')

@section('content')
    <div class="page-content container-fluid">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Approval</h3></div>
                    <div class="panel-body">
                        <p><strong>Name:</strong> {{ $driver->user->name ?: '—' }}</p>
                        <p><strong>Phone:</strong> {{ $driver->user->phone }}</p>
                        <p><strong>License #:</strong> {{ $driver->license_number ?: '—' }}</p>
                        <p>
                            <a href="{{ route('voyager.users.edit', $driver->user_id) }}" class="btn btn-sm btn-default">
                                Manage user account (phone, password, email…)
                            </a>
                        </p>
                        <form method="POST" action="{{ route('admin.drivers.update-approval', $driver) }}">
                            @csrf
                            <div class="form-group">
                                <select name="approval_status" class="form-control">
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status }}" @selected($driver->approval_status === $status)>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Approval</button>
                        </form>
                    </div>
                </div>

                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>School Bus Status</h3></div>
                    <div class="panel-body">
                        <form method="POST" action="{{ route('admin.drivers.update-school-bus-status', $driver) }}">
                            @csrf
                            <div class="form-group">
                                <select name="school_bus_status" class="form-control">
                                    <option value="" @selected($driver->school_bus_status === null)>— Not enrolled —</option>
                                    @foreach ($schoolBusStatuses as $status)
                                        <option value="{{ $status }}" @selected($driver->school_bus_status === $status)>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Update School Bus Status</button>
                        </form>
                    </div>
                </div>

                <div class="panel panel-bordered panel-danger">
                    <div class="panel-heading"><h3>Remove Driver Record</h3></div>
                    <div class="panel-body">
                        <p class="text-muted">
                            Deletes this driver record (and its uploaded documents). Use this if this driver was created
                            against the wrong phone number by mistake.
                        </p>
                        <form method="POST" action="{{ route('admin.drivers.destroy', $driver) }}" onsubmit="return confirm('Remove this driver record? This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <div class="form-group">
                                <label>After removal, set this account's type to:</label>
                                <select name="revert_type" class="form-control">
                                    <option value="seller">Seller (customer)</option>
                                    <option value="driver">Leave as driver</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-danger">Remove Driver Record</button>
                        </form>
                    </div>
                </div>

                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Vehicle</h3></div>
                    <div class="panel-body">
                        <form method="POST" action="{{ route('admin.drivers.update-vehicle', $driver) }}">
                            @csrf
                            <div class="form-group"><label>License #</label><input type="text" name="license_number" class="form-control" value="{{ $driver->license_number }}"></div>
                            <div class="form-group">
                                <label>Category</label>
                                <select name="vehicle_category_id" class="form-control">
                                    <option value="">— None —</option>
                                    @foreach ($vehicleCategories as $category)
                                        <option value="{{ $category->id }}" @selected($driver->vehicle_category_id === $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group"><label>Make</label><input type="text" name="vehicle_make" class="form-control" value="{{ $driver->vehicle_make }}"></div>
                            <div class="form-group"><label>Model</label><input type="text" name="vehicle_model" class="form-control" value="{{ $driver->vehicle_model }}"></div>
                            <div class="form-group"><label>Color</label><input type="text" name="vehicle_color" class="form-control" value="{{ $driver->vehicle_color }}"></div>
                            <div class="form-group"><label>Plate</label><input type="text" name="vehicle_plate" class="form-control" value="{{ $driver->vehicle_plate }}"></div>
                            <div class="form-group"><label>Transmission</label><input type="text" name="transmission" class="form-control" value="{{ $driver->transmission }}"></div>
                            <button type="submit" class="btn btn-primary">Save Vehicle</button>
                        </form>
                    </div>
                </div>

                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Trip History</h3></div>
                    <div class="panel-body">
                        <table class="table table-hover">
                            <thead><tr><th>ID</th><th>Kind</th><th>Status</th><th>Date</th></tr></thead>
                            <tbody>
                                @forelse ($trips as $trip)
                                    <tr>
                                        <td><a href="{{ route('voyager.orders.edit', $trip->id) }}">#{{ $trip->id }}</a></td>
                                        <td>{{ $trip->order_kind }}</td>
                                        <td>{{ $trip->status }}</td>
                                        <td>{{ $trip->created_at?->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center">No trips yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Upload Document</h3></div>
                    <div class="panel-body">
                        <form method="POST" action="{{ route('admin.drivers.upload-document', $driver) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group">
                                <label>Type</label>
                                <select name="document_type" class="form-control">
                                    @foreach ($documentTypes as $type)
                                        <option value="{{ $type }}">{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label>File</label>
                                <input type="file" name="file" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Expires at</label>
                                <input type="date" name="expires_at" class="form-control">
                            </div>
                            <button type="submit" class="btn btn-primary">Upload</button>
                        </form>
                    </div>
                </div>

                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Documents</h3></div>
                    <div class="panel-body">
                        <table class="table table-hover">
                            <thead><tr><th>Type</th><th>Status</th><th>Expires</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($driver->documents as $document)
                                    <tr>
                                        <td>{{ ucfirst(str_replace('_', ' ', $document->document_type)) }}</td>
                                        <td>
                                            <span class="label label-{{ $document->status === 'approved' ? 'success' : ($document->status === 'pending' ? 'default' : 'danger') }}">
                                                {{ ucfirst($document->status) }}
                                            </span>
                                        </td>
                                        <td>{{ $document->expires_at?->format('Y-m-d') ?: '—' }}</td>
                                        <td>
                                            <a href="{{ asset('storage/'.$document->file_path) }}" target="_blank" class="btn btn-xs btn-default">View</a>
                                            <form method="POST" action="{{ route('admin.drivers.documents.review', $document) }}" style="display:inline-block;">
                                                @csrf
                                                <input type="hidden" name="status" value="approved">
                                                <button type="submit" class="btn btn-xs btn-success">Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.drivers.documents.review', $document) }}" style="display:inline-block;">
                                                @csrf
                                                <input type="hidden" name="status" value="rejected">
                                                <button type="submit" class="btn btn-xs btn-danger">Reject</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.drivers.documents.destroy', $document) }}" style="display:inline-block;" onsubmit="return confirm('Delete this document?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-xs btn-link">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center">No documents uploaded.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

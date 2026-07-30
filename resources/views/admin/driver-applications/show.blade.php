@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Driver Application')

@section('content')
    <div class="page-content container-fluid">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @error('convert')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror

        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Personal Information</h3></div>
                    <div class="panel-body">
                        <p><strong>Full Name:</strong> {{ $application->full_name }}</p>
                        <p><strong>Mobile:</strong> {{ $application->mobile_number }}</p>
                        <p><strong>WhatsApp:</strong> {{ $application->whatsapp_number ?: '—' }}</p>
                        <p><strong>Email:</strong> {{ $application->email ?: '—' }}</p>
                        <p><strong>City:</strong> {{ $application->city }}</p>
                        <p><strong>Service Type:</strong> {{ ucfirst($application->service_type) }}</p>
                    </div>
                </div>

                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Driver &amp; Vehicle Information</h3></div>
                    <div class="panel-body">
                        <p><strong>National ID #:</strong> {{ $application->national_id_number }}</p>
                        <p><strong>Driving License #:</strong> {{ $application->driving_license_number }}</p>
                        <p><strong>Vehicle Type:</strong> {{ $application->vehicle_type }}</p>
                        <p><strong>Vehicle Brand:</strong> {{ $application->vehicle_brand }}</p>
                        <p><strong>Vehicle Model:</strong> {{ $application->vehicle_model }}</p>
                        <p><strong>Vehicle Year:</strong> {{ $application->vehicle_year }}</p>
                        <p><strong>Plate Number:</strong> {{ $application->plate_number }}</p>
                    </div>
                </div>

                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Documents</h3></div>
                    <div class="panel-body">
                        <table class="table table-hover">
                            <thead><tr><th>Document</th><th></th></tr></thead>
                            <tbody>
                                <tr>
                                    <td>National ID (Front)</td>
                                    <td><a href="{{ asset('storage/'.$application->national_id_front_path) }}" target="_blank" class="btn btn-xs btn-default">View</a></td>
                                </tr>
                                <tr>
                                    <td>Driving License</td>
                                    <td><a href="{{ asset('storage/'.$application->driving_license_path) }}" target="_blank" class="btn btn-xs btn-default">View</a></td>
                                </tr>
                                <tr>
                                    <td>Vehicle Registration</td>
                                    <td><a href="{{ asset('storage/'.$application->vehicle_registration_path) }}" target="_blank" class="btn btn-xs btn-default">View</a></td>
                                </tr>
                                @if ($application->personal_photo_path)
                                    <tr>
                                        <td>Personal Photo</td>
                                        <td><a href="{{ asset('storage/'.$application->personal_photo_path) }}" target="_blank" class="btn btn-xs btn-default">View</a></td>
                                    </tr>
                                @endif
                                @if ($application->vehicle_photo_path)
                                    <tr>
                                        <td>Vehicle Photo</td>
                                        <td><a href="{{ asset('storage/'.$application->vehicle_photo_path) }}" target="_blank" class="btn btn-xs btn-default">View</a></td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                        <p class="text-muted">
                            Agreements confirmed: information correct — {{ $application->confirmed_information_correct ? 'Yes' : 'No' }};
                            terms &amp; privacy — {{ $application->agreed_terms ? 'Yes' : 'No' }}.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Review Status</h3></div>
                    <div class="panel-body">
                        <p>
                            Current status:
                            <span class="label label-{{ match($application->status) {
                                'approved' => 'success',
                                'under_review' => 'info',
                                'rejected' => 'danger',
                                default => 'default',
                            } }}">
                                {{ ucfirst(str_replace('_', ' ', $application->status)) }}
                            </span>
                        </p>
                        @if ($application->reviewed_at)
                            <p class="text-muted">Last reviewed: {{ $application->reviewed_at->format('Y-m-d H:i') }}</p>
                        @endif
                        <form method="POST" action="{{ route('admin.driver-applications.update-status', $application) }}">
                            @csrf
                            <div class="form-group">
                                <select name="status" class="form-control">
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status }}" @selected($application->status === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Status</button>
                        </form>

                        <hr>

                        @if ($application->driver_id)
                            <p class="text-success">
                                <strong>Converted to driver.</strong>
                            </p>
                            <a href="{{ route('admin.drivers.show', $application->driver_id) }}" class="btn btn-default">
                                View Driver
                            </a>
                        @else
                            <p class="text-muted">
                                Creates a driver account for this applicant's phone number, copies over the
                                license/vehicle info, and carries the national ID, license, and vehicle
                                registration documents over as already-approved.
                            </p>
                            <form method="POST" action="{{ route('admin.driver-applications.convert-to-driver', $application) }}" onsubmit="return confirm('Create a driver account from this application? This cannot be undone.');">
                                @csrf
                                <button type="submit" class="btn btn-success">Approve &amp; Create Driver</button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="panel panel-bordered">
                    <div class="panel-heading"><h3>Internal Notes</h3></div>
                    <div class="panel-body">
                        <form method="POST" action="{{ route('admin.driver-applications.notes.store', $application) }}" class="mb-3">
                            @csrf
                            <div class="form-group">
                                <textarea name="note" class="form-control" rows="3" placeholder="Add an internal note…" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-default">Add Note</button>
                        </form>

                        @forelse ($application->notes as $note)
                            <div class="well well-sm">
                                <p class="mb-1">{{ $note->note }}</p>
                                <small class="text-muted">
                                    {{ $note->author->name ?? 'Staff' }} — {{ $note->created_at->format('Y-m-d H:i') }}
                                </small>
                            </div>
                        @empty
                            <p class="text-muted">No notes yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Broadcast')

@section('content')
    <div class="page-content browse container-fluid">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>New Broadcast</h3></div>
            <div class="panel-body">
                <form method="POST" action="{{ route('admin.broadcasts.store') }}">
                    @csrf
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" name="title" id="title" class="form-control" maxlength="120" value="{{ old('title') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea name="message" id="message" class="form-control" rows="3" maxlength="1000" required>{{ old('message') }}</textarea>
                        <p class="help-block">Write the Title and Message in Arabic — the app has no per-user language setting, so every notification (this one included) is sent to drivers/sellers in Arabic only.</p>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="account_type">Account Type</label>
                                <select name="account_type" id="account_type" class="form-control">
                                    <option value="">Any</option>
                                    @foreach ($accountTypes as $type)
                                        <option value="{{ $type }}" @selected(old('account_type') === $type)>{{ ucfirst($type) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="service_type">Service Type</label>
                                <select name="service_type" id="service_type" class="form-control">
                                    <option value="">Any</option>
                                    @foreach ($serviceTypes as $type)
                                        <option value="{{ $type }}" @selected(old('service_type') === $type)>{{ ucfirst($type) }}</option>
                                    @endforeach
                                </select>
                                <p class="help-block">Only drivers have a service type — picking one always targets drivers, regardless of Account Type.</p>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Send this notification to everyone matching the selected filters?');">
                        <i class="voyager-bell"></i> Send Broadcast
                    </button>
                </form>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3>Sent Broadcasts</h3></div>
            <div class="panel-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Message</th>
                            <th>Account Type</th>
                            <th>Service Type</th>
                            <th>Recipients</th>
                            <th>Sent By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($broadcasts as $broadcast)
                            <tr>
                                <td>{{ $broadcast->created_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ $broadcast->title }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($broadcast->message, 60) }}</td>
                                <td>{{ $broadcast->account_type ? ucfirst($broadcast->account_type) : 'Any' }}</td>
                                <td>{{ $broadcast->service_type ? ucfirst($broadcast->service_type) : 'Any' }}</td>
                                <td>{{ $broadcast->recipient_count }}</td>
                                <td>{{ $broadcast->sentBy->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">No broadcasts sent yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{ $broadcasts->links() }}
            </div>
        </div>
    </div>
@endsection

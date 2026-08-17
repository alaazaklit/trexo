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
                <div class="form-group">
                    <label>Send To</label><br>
                    <label class="radio-inline">
                        <input type="radio" name="send_to_toggle" value="filtration" id="send_to_filtration" @checked(!$excelPending)>
                        By Filtration
                    </label>
                    <label class="radio-inline">
                        <input type="radio" name="send_to_toggle" value="excel" id="send_to_excel" @checked($excelPending)>
                        By Excel
                    </label>
                </div>

                <div id="send-to-filtration-section">
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

                <div id="send-to-excel-section">
                    @if ($excelPending)
                        <div class="alert alert-info">
                            <strong>Preview — nothing has been sent yet.</strong> Review the recipients below, then confirm.
                        </div>
                        <p><strong>Title:</strong> {{ $excelPending['title'] }}</p>
                        <p><strong>Message:</strong> {{ $excelPending['message'] }}</p>
                        <p><strong>File:</strong> {{ $excelPending['source_file_name'] }}</p>

                        <table class="table table-condensed" style="max-width:420px;">
                            <tr><td>Excel rows</td><td><strong>{{ $excelPending['summary']['total_rows'] }}</strong></td></tr>
                            <tr><td>Empty rows ignored</td><td>{{ $excelPending['summary']['empty_rows_ignored'] }}</td></tr>
                            <tr><td>Invalid numbers</td><td>{{ $excelPending['summary']['invalid_count'] }}</td></tr>
                            <tr><td>Duplicates removed</td><td>{{ $excelPending['summary']['duplicate_count'] }}</td></tr>
                            <tr><td>Users not found</td><td>{{ $excelPending['summary']['not_found_count'] }}</td></tr>
                            <tr class="success"><td><strong>Final recipients</strong></td><td><strong>{{ $excelPending['summary']['final_count'] }}</strong></td></tr>
                        </table>

                        @if (!empty($excelPending['summary']['invalid_samples']))
                            <p class="text-muted">Sample invalid values: {{ implode(', ', $excelPending['summary']['invalid_samples']) }}</p>
                        @endif
                        @if (!empty($excelPending['summary']['not_found_numbers']))
                            <p class="text-muted">Not found: {{ implode(', ', array_slice($excelPending['summary']['not_found_numbers'], 0, 20)) }}{{ count($excelPending['summary']['not_found_numbers']) > 20 ? '…' : '' }}</p>
                        @endif

                        <div class="panel panel-default">
                            <div class="panel-heading">Recipients ({{ count($excelPending['recipients']) }})</div>
                            <div class="panel-body" style="max-height:240px; overflow-y:auto;">
                                <table class="table table-condensed">
                                    <thead><tr><th>Name</th><th>Phone</th></tr></thead>
                                    <tbody>
                                        @foreach ($excelPending['recipients'] as $recipient)
                                            <tr>
                                                <td>{{ $recipient['name'] ?: '—' }}</td>
                                                <td>{{ $recipient['phone'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.broadcasts.excel.send') }}" style="display:inline-block;">
                            @csrf
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Send this broadcast to {{ $excelPending['summary']['final_count'] }} recipient(s)?');">
                                <i class="voyager-bell"></i> Confirm &amp; Send Broadcast
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.broadcasts.excel.cancel') }}" style="display:inline-block;">
                            @csrf
                            <button type="submit" class="btn btn-default">Cancel</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.broadcasts.excel.preview') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group">
                                <label for="excel_title">Title</label>
                                <input type="text" name="title" id="excel_title" class="form-control" maxlength="120" value="{{ old('title') }}" required>
                            </div>
                            <div class="form-group">
                                <label for="excel_message">Message</label>
                                <textarea name="message" id="excel_message" class="form-control" rows="3" maxlength="1000" required>{{ old('message') }}</textarea>
                                <p class="help-block">Same Arabic-only note as above — this is sent as-is to whoever matches the uploaded file.</p>
                            </div>
                            <div class="form-group">
                                <label for="file">Recipients file (.xlsx or .xls, max 5MB)</label>
                                <input type="file" name="file" id="file" class="form-control" accept=".xlsx,.xls" required>
                                <p class="help-block">Must have a <code>phone</code> column header. One phone number per row, e.g. 96171123456.</p>
                            </div>
                            <button type="submit" class="btn btn-default">
                                <i class="voyager-upload"></i> Upload &amp; Preview
                            </button>
                        </form>
                    @endif
                </div>
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
                            <th>Source</th>
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
                                <td>
                                    {{ $broadcast->source === \App\Models\Broadcast::SOURCE_EXCEL ? 'Excel' : 'Filtration' }}
                                    @if ($broadcast->source === \App\Models\Broadcast::SOURCE_EXCEL && $broadcast->source_file_name)
                                        <br><span class="text-muted small">{{ $broadcast->source_file_name }}</span>
                                    @endif
                                </td>
                                <td>{{ $broadcast->account_type ? ucfirst($broadcast->account_type) : ($broadcast->source === \App\Models\Broadcast::SOURCE_EXCEL ? '—' : 'Any') }}</td>
                                <td>{{ $broadcast->service_type ? ucfirst($broadcast->service_type) : ($broadcast->source === \App\Models\Broadcast::SOURCE_EXCEL ? '—' : 'Any') }}</td>
                                <td>{{ $broadcast->recipient_count }}</td>
                                <td>{{ $broadcast->sentBy->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center">No broadcasts sent yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{ $broadcasts->links() }}
            </div>
        </div>
    </div>
@endsection

@push('javascript')
    <script>
        (function () {
            var filtrationRadio = document.getElementById('send_to_filtration');
            var excelRadio = document.getElementById('send_to_excel');
            var filtrationSection = document.getElementById('send-to-filtration-section');
            var excelSection = document.getElementById('send-to-excel-section');

            function applyToggle() {
                var showExcel = excelRadio.checked;
                filtrationSection.style.display = showExcel ? 'none' : 'block';
                excelSection.style.display = showExcel ? 'block' : 'none';
            }

            filtrationRadio.addEventListener('change', applyToggle);
            excelRadio.addEventListener('change', applyToggle);
            applyToggle();
        })();
    </script>
@endpush

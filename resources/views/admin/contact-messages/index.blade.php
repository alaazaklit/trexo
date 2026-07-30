@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Contact Messages')

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
                <form method="GET" action="{{ route('admin.contact-messages.index') }}" class="form-inline">
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="text" name="search" class="form-control" placeholder="Name, email, or message" value="{{ $search }}">
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.contact-messages.index') }}" class="btn btn-default">Reset</a>
                </form>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Message</th>
                            <th>Received</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($messages as $message)
                            <tr>
                                <td>{{ $message->name }}</td>
                                <td>{{ $message->email }}</td>
                                <td>{{ $message->phone ?: '—' }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($message->message, 80) }}</td>
                                <td>{{ $message->created_at?->format('Y-m-d H:i') }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.contact-messages.destroy', $message) }}" onsubmit="return confirm('Delete this message?');" style="display:inline-block;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">No contact messages yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{ $messages->links() }}
            </div>
        </div>
    </div>
@endsection

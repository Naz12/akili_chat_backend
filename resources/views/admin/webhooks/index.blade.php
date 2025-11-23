@extends('layouts.admin')
@section('title', 'Webhook Logs')

@section('content')
    <h2 class="mb-3">Webhook Logs</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Provider</th>
                <th>Event</th>
                <th>Received</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($logs as $log)
                <tr>
                    <td>{{ $log->id }}</td>
                    <td>{{ ucfirst($log->provider) }}</td>
                    <td>{{ $log->event_type }}</td>
                    <td>{{ $log->created_at->diffForHumans() }}</td>
                    <td><a href="{{ route('admin.webhooks.show', $log->id) }}" class="btn btn-sm btn-info">View</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $logs->links() }}
@endsection

@extends('layouts.admin')
@section('title', 'Webhook Details')

@section('content')
    <h2>Webhook #{{ $log->id }}</h2>
    <ul class="list-group mb-3">
        <li class="list-group-item"><strong>Provider:</strong> {{ $log->provider }}</li>
        <li class="list-group-item"><strong>Event Type:</strong> {{ $log->event_type }}</li>
        <li class="list-group-item"><strong>Signature:</strong> <code>{{ $log->signature }}</code></li>
        <li class="list-group-item"><strong>Received At:</strong> {{ $log->created_at }}</li>
    </ul>

    <h4>Payload:</h4>
    <pre style="background:#f8f9fa; padding:1rem;">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
@endsection

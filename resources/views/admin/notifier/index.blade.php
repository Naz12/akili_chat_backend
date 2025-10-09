@extends('layouts.admin')

@section('title', 'Send Notifications')

@section('content')
    <div class="container">
        <h2 class="mb-4">Send Notification (Multi-User, Multi-Channel)</h2>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @elseif (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.notifier.broadcast') }}">
            @csrf

            <div class="mb-3">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="sendToAll" name="send_to_all" value="1">
                    <label class="form-check-label" for="sendToAll">Send to All Users</label>
                </div>

                <label class="form-label">Select Users</label>
                <select name="user_ids[]" class="form-select" id="userSelect" multiple required>
                    @foreach (\App\Models\User::with('preference')->get() as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
                <small class="text-muted">Hold Ctrl/Cmd to select multiple users.</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Channels</label><br>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="channels[]" value="email" checked>
                    <label class="form-check-label">Email</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="channels[]" value="push">
                    <label class="form-check-label">Push</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="channels[]" value="sms">
                    <label class="form-check-label">SMS</label>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Subject / Title</label>
                <input type="text" name="subject" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Message</label>
                <textarea name="message" class="form-control" rows="4" required></textarea>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-bullhorn me-1"></i> Broadcast
            </button>
        </form>
    </div>

    <script>
        document.getElementById('sendToAll').addEventListener('change', function() {
            document.getElementById('userSelect').disabled = this.checked;
        });
    </script>
@endsection

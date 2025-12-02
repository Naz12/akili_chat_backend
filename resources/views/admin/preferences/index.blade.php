@extends('layouts.admin')

@section('title', 'User Preferences')

@section('content')
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">User Notification Preferences</h2>
            <x-export-button 
                route="{{ route('admin.preferences.export') }}"
                title="Export Preferences"
                :currentCount="$preferences->count()"
                :totalCount="\App\Models\Preference::count()"
                :hasFilters="false"
            />
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Marketing Email</th>
                        <th>Push Notification</th>
                        <th>SMS</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($preferences as $pref)
                        <tr>
                            <form method="POST" action="{{ route('admin.preferences.update', $pref->id) }}" class="d-flex">
                                @csrf
                                @method('PUT')

                                <td class="align-middle">{{ $pref->user->name }}</td>
                                <td class="align-middle">{{ $pref->user->email }}</td>
                                <td class="align-middle text-center">
                                    <input type="checkbox" name="allow_marketing_email" class="form-check-input"
                                        {{ $pref->allow_marketing_email ? 'checked' : '' }}>
                                </td>
                                <td class="align-middle text-center">
                                    <input type="checkbox" name="allow_push_notifications" class="form-check-input"
                                        {{ $pref->allow_push_notifications ? 'checked' : '' }}>
                                </td>
                                <td class="align-middle text-center">
                                    <input type="checkbox" name="allow_sms" class="form-check-input"
                                        {{ $pref->allow_sms ? 'checked' : '' }}>
                                </td>
                                <td class="align-middle">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fas fa-save me-1"></i> Save
                                    </button>
                                </td>
                            </form>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">No preferences found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $preferences->links() }}
        </div>
    </div>
@endsection

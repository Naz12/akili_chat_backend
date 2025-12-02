@extends('layouts.admin')
@section('title', 'Webhook Logs')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary"><i class="fas fa-webhook me-2"></i>Webhook Logs</h2>
        <x-export-button 
            route="{{ route('admin.webhooks.export') }}"
            title="Export Webhook Logs"
            :currentCount="$logs->count()"
            :totalCount="\App\Models\Webhook::count()"
            :hasFilters="false"
        />
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Webhook Logs Table -->
    @if ($logs->count())
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
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
                                    <td>
                                        <span class="badge bg-info">{{ ucfirst($log->provider) }}</span>
                                    </td>
                                    <td>
                                        <code class="text-primary">{{ $log->event_type }}</code>
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ $log->created_at->diffForHumans() }}</small>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.webhooks.show', $log->id) }}" 
                                           class="btn btn-sm btn-outline-primary" 
                                           title="View Details">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="mt-4 p-3 bg-light rounded">
            {{ $logs->links('pagination::bootstrap-5') }}
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-webhook fa-3x text-muted mb-3"></i>
                <p class="text-muted">No webhook logs found.</p>
            </div>
        </div>
    @endif
@endsection

@extends('layouts.admin')
@section('title', 'Audit Log Details')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1" style="color: #1e293b;">
                <i class="fas fa-file-alt me-2" style="color: #6366f1;"></i>Audit Log Details
            </h1>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Detailed information about this operation</p>
        </div>
        <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to List
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Operation Details</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="30%">Admin:</th>
                            <td>
                                <strong>{{ $log->admin->name }}</strong><br>
                                <small class="text-muted">{{ $log->admin->email }}</small>
                            </td>
                        </tr>
                        <tr>
                            <th>Action:</th>
                            <td><span class="badge bg-primary">{{ $log->action }}</span></td>
                        </tr>
                        <tr>
                            <th>Model:</th>
                            <td>
                                @if($log->model_type)
                                    <code>{{ $log->model_type }}</code>
                                    @if($log->model_id)
                                        <br><small class="text-muted">ID: {{ $log->model_id }}</small>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Description:</th>
                            <td>{{ $log->description ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>IP Address:</th>
                            <td>{{ $log->ip_address ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>User Agent:</th>
                            <td><small>{{ $log->user_agent ?? '-' }}</small></td>
                        </tr>
                        <tr>
                            <th>Timestamp:</th>
                            <td>
                                {{ $log->created_at->format('M d, Y H:i:s') }}<br>
                                <small class="text-muted">{{ $log->created_at->diffForHumans() }}</small>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Changes -->
            @if($log->old_values || $log->new_values)
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Changes</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @if($log->old_values)
                        <div class="col-md-6">
                            <h6 class="text-danger">Before</h6>
                            <pre class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto;"><code>{{ json_encode($log->old_values, JSON_PRETTY_PRINT) }}</code></pre>
                        </div>
                        @endif
                        @if($log->new_values)
                        <div class="col-md-6">
                            <h6 class="text-success">After</h6>
                            <pre class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto;"><code>{{ json_encode($log->new_values, JSON_PRETTY_PRINT) }}</code></pre>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection


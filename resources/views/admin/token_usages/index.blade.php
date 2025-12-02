@extends('layouts.admin')
@section('title', 'Usage Logs')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1e293b;">
                <i class="fas fa-list-ol me-2" style="color: #6366f1;"></i>Token Usage Logs
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Track AI token consumption and costs across users
            </p>
        </div>
        <x-export-button 
            route="{{ route('admin.usage.logs.export') }}"
            title="Export Token Usage"
            :currentCount="$usages->count()"
            :totalCount="\App\Models\TokenUsage::count()"
            :hasFilters="request()->hasAny(['user_id', 'engine_id'])"
        />
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.usage.logs') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold" style="color: #475569;">Filter by User ID</label>
                    <input type="number" name="user_id" class="form-control" 
                           placeholder="Enter user ID" value="{{ request('user_id') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold" style="color: #475569;">Filter by Engine ID</label>
                    <input type="number" name="engine_id" class="form-control" 
                           placeholder="Enter engine ID" value="{{ request('engine_id') }}">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter me-1"></i> Apply Filters
                    </button>
                    <a href="{{ route('admin.usage.logs') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Usage Logs Table -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Engine</th>
                            <th>Tokens</th>
                            <th>Cost</th>
                            <th>Prompt</th>
                            <th>Response</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($usages as $usage)
                            <tr>
                                <td>
                                    <div>
                                        <span class="fw-semibold d-block" style="color: #1e293b;">
                                            {{ $usage->user->name ?? 'N/A' }}
                                        </span>
                                        <small class="text-muted">ID: {{ $usage->user_id }}</small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <span class="fw-semibold d-block" style="color: #1e293b;">
                                            {{ $usage->engine->name ?? 'Unknown' }}
                                        </span>
                                        <small class="text-muted">{{ $usage->engine->provider ?? 'N/A' }}</small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" style="background-color: #dbeafe; color: #1e40af; font-size: 0.875rem;">
                                        <i class="fas fa-coins me-1" style="font-size: 0.75rem;"></i>
                                        {{ number_format($usage->tokens_used) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold" style="color: #10b981;">
                                        ${{ number_format($usage->cost, 4) }}
                                    </span>
                                </td>
                                <td style="max-width: 250px;">
                                    <div class="text-truncate" title="{{ $usage->prompt }}">
                                        <i class="fas fa-comment-dots me-1" style="color: #6366f1; font-size: 0.75rem;"></i>
                                        <span style="font-size: 0.875rem; color: #475569;">{{ Str::limit($usage->prompt, 60) }}</span>
                                    </div>
                                </td>
                                <td style="max-width: 250px;">
                                    <div class="text-truncate" title="{{ $usage->response }}">
                                        <i class="fas fa-robot me-1" style="color: #8b5cf6; font-size: 0.75rem;"></i>
                                        <span style="font-size: 0.875rem; color: #475569;">{{ Str::limit($usage->response, 60) }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <span class="d-block" style="font-size: 0.875rem; color: #1e293b;">
                                            {{ $usage->created_at->format('M d, Y') }}
                                        </span>
                                        <small class="text-muted">{{ $usage->created_at->format('h:i A') }}</small>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="fas fa-inbox" style="font-size: 3rem; color: #cbd5e1;"></i>
                                    <p class="text-muted mt-3 mb-0">No usage logs found.</p>
                                    @if(request('user_id') || request('engine_id'))
                                        <small class="text-muted">Try adjusting your filters</small>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        
        @if($usages->hasPages())
            <div class="card-footer bg-white border-top" style="padding: 1.25rem;">
                {{ $usages->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection

@extends('layouts.admin')
@section('title', 'AI Engines')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1e293b;">
                <i class="fas fa-brain me-2" style="color: #6366f1;"></i>AI Engines
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Manage and monitor AI engine configurations</p>
        </div>
        <div class="d-flex gap-2">
            <x-export-button 
                route="{{ route('admin.ai-engines.export') }}"
                title="Export AI Engines"
                :currentCount="$engines->count()"
                :totalCount="$engines->count()"
                :hasFilters="false"
            />
            <a href="{{ route('admin.ai-engines.create') }}" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i> Add New Engine
            </a>
        </div>
    </div>

    {{-- Engine Status Metrics --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light fw-bold">
            <i class="fas fa-server me-1 text-success"></i> Engine Status & Metrics
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Engine</th>
                            <th>Vision</th>
                            <th>Status</th>
                            <th>Last Test</th>
                            <th>Avg Cost</th>
                            <th>Fails</th>
                            <th>Action</th> {{-- ✅ Action column --}}
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($engineStatuses as $engine)
                            <tr>

                                <td>
                                    <strong>{{ $engine['name'] }}</strong><br>
                                    <small class="text-muted">{{ $engine['provider'] }}</small>
                                </td>
                                <td>
                                    @if($engine['is_vision_support'])
                                        <span class="badge bg-info">Vision</span>
                                    @else
                                        <span class="badge bg-danger">No</span>
                                    @endif
                                </td>

                                <td>
                                    <span class="badge bg-{{ $engine['online'] ? 'success' : 'danger' }}">
                                        {{ $engine['online'] ? 'Online' : 'Offline' }}
                                    </span>
                                </td>
                                <td>
                                    {{ $engine['last_test_success']
                                        ? \Carbon\Carbon::parse($engine['last_test_success'])->diffForHumans()
                                        : 'Never' }}
                                </td>
                                <td>
                                    {{ $engine['avg_cost_per_request'] ? '$' . number_format($engine['avg_cost_per_request'], 5) : 'N/A' }}
                                </td>
                                <td>
                                    <span class="badge bg-{{ $engine['failure_count'] > 0 ? 'danger' : 'secondary' }}">
                                        {{ $engine['failure_count'] }}
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary test-engine-btn"
                                        data-id="{{ $engine['id'] }}"
                                        data-url="{{ url('admin/ai-engines/' . $engine['id'] . '/test') }}">
                                        Test
                                    </button>
                                    <span class="text-muted ms-2" id="engine-status-{{ $engine['id'] }}"></span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    {{-- Editable Engine Table --}}
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Provider</th>
                            <th>Max Tokens</th>
                            <th>Price / 1K</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($engines as $engine)
                            <tr>
                                <td><strong>{{ $engine->name }}</strong> <a
                                        href="{{ route('admin.plans.index', ['engine_id' => $engine->id]) }}"
                                        class="badge bg-info text-dark"
                                        title="{{ $engine->plans->pluck('name')->join(', ') }}">
                                        {{ $engine->plans->count() }} plan{{ $engine->plans->count() !== 1 ? 's' : '' }}
                                    </a>
                                </td>
                                <td>{{ ucfirst($engine->provider) }}</td>
                                <td>{{ number_format($engine->max_tokens) }}</td>
                                <td>${{ number_format($engine->price_per_1k, 4) }}</td>
                                <td>
                                    <span class="badge bg-{{ $engine->is_active ? 'success' : 'secondary' }}">
                                        {{ $engine->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>

                                <td class="text-center">
                                    <a href="{{ route('admin.ai-engines.edit', $engine->id) }}"
                                        class="btn btn-sm btn-warning me-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('admin.ai-engines.destroy', $engine->id) }}" method="POST"
                                        class="d-inline"
                                        onsubmit="return confirm('Are you sure you want to delete this engine?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-danger" type="submit">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-circle-info me-1"></i> No AI engines found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection


@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const testButtons = document.querySelectorAll('.test-engine-btn');

            testButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const engineId = this.dataset.id;
                    const testUrl = this.dataset.url;
                    const statusSpan = document.getElementById(`engine-status-${engineId}`);

                    statusSpan.innerHTML = '<span class="text-info">⏳ Testing...</span>';

                    fetch(testUrl, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.message) {
                                statusSpan.innerHTML =
                                    '<span class="text-success">✅ Online</span>';
                            } else if (data.error) {
                                statusSpan.innerHTML = '<span class="text-danger">❌ ' + data
                                    .error + '</span>';
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            statusSpan.innerHTML = '<span class="text-danger">❌ Error</span>';
                        });
                });
            });
        });
    </script>
@endpush

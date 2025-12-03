@extends('layouts.admin')

@section('title', 'Payment Methods')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1e293b;">
                <i class="fas fa-credit-card me-2" style="color: #6366f1;"></i>Payment Methods
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Manage payment methods for local and international users</p>
        </div>
        <div class="d-flex gap-2">
            <x-export-button 
                route="{{ route('admin.payment-methods.export') }}"
                title="Export Payment Methods"
                :currentCount="$methods->count()"
                :totalCount="$methods->count()"
                :hasFilters="false"
            />
            <a href="{{ route('admin.payment-methods.create') }}" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i> Add New Payment Method
            </a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Key</th>
                            <th>Status</th>
                            <th>Regions</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($methods as $method)
                            <tr>
                                <td><strong>{{ $method->name }}</strong></td>
                                <td><code>{{ $method->key }}</code></td>
                                <td>
                                    <span class="badge bg-{{ $method->is_enabled ? 'success' : 'secondary' }}">
                                        {{ $method->is_enabled ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </td>
                                <td>
                                    @if($method->is_enabled && !empty($method->regions))
                                        @foreach($method->regions as $region)
                                            <span class="badge bg-{{ $region === 'intl' ? 'info' : 'secondary' }} me-1">
                                                {{ $region === 'intl' ? 'International' : 'Local' }}
                                            </span>
                                        @endforeach
                                    @else
                                        <span class="text-muted">None</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('admin.payment-methods.edit', $method) }}" class="btn btn-sm btn-warning me-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.payment-methods.destroy', $method) }}" class="d-inline"
                                        onsubmit="return confirm('Are you sure you want to delete this payment method?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger" type="submit">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="fas fa-circle-info me-1"></i> No payment methods found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@extends('layouts.admin')
@section('title', 'Billing Overview')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary"><i class="fas fa-file-invoice-dollar me-2"></i>Billing Overview</h2>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($subscriptions->count())
        <div class="table-responsive card shadow-sm">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Plan</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Tokens Used</th>
                        <th>Auto Renew</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subscriptions as $sub)
                        <tr>
                            <td>{{ $sub->user->name }}<br><small class="text-muted">{{ $sub->user->email }}</small></td>
                            <td>{{ $sub->plan->name ?? 'N/A' }}</td>
                            <td>{{ \Carbon\Carbon::parse($sub->start_date)->format('Y-m-d') }}</td>
                            <td>{{ \Carbon\Carbon::parse($sub->end_date)->format('Y-m-d') }}</td>
                            <td>{{ $sub->tokens_used }}</td>
                            <td>
                                <span class="badge bg-{{ $sub->auto_renew ? 'success' : 'secondary' }}">
                                    {{ $sub->auto_renew ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('admin.billing.userHistory', $sub->user_id) }}"
                                    class="btn btn-sm btn-outline-primary">
                                    View History
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $subscriptions->links() }}
        </div>
    @else
        <div class="alert alert-info">
            <i class="fas fa-circle-info me-1"></i> No subscriptions found.
        </div>
    @endif
@endsection

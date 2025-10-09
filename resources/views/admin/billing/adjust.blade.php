@extends('layouts.admin')
@section('title', 'Adjust Token Usage')

@section('content')
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-sliders-h me-1"></i> Adjust Subscription Usage
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.billing.updateUsage', $subscription->id) }}">
                @csrf

                {{-- User Info --}}
                <div class="mb-3">
                    <label class="form-label fw-bold">User</label>
                    <input type="text" class="form-control"
                        value="{{ $subscription->user->name }} ({{ $subscription->user->email }})" disabled>
                </div>

                {{-- Plan Info --}}
                <div class="mb-3">
                    <label class="form-label fw-bold">Plan</label>
                    <input type="text" class="form-control" value="{{ $subscription->plan->name }}" disabled>
                </div>

                {{-- Tokens Progress --}}
                <div class="mb-3">
                    <label class="form-label fw-bold">Token Usage</label>
                    <div class="progress mb-2" style="height: 20px;">
                        @php
                            $percent = min(100, ($subscription->tokens_used / $subscription->plan->max_tokens) * 100);
                        @endphp
                        <div class="progress-bar {{ $percent >= 90 ? 'bg-danger' : ($percent >= 70 ? 'bg-warning' : 'bg-success') }}"
                            role="progressbar" style="width: {{ $percent }}%;">
                            {{ number_format($subscription->tokens_used) }} /
                            {{ number_format($subscription->plan->max_tokens) }}
                        </div>
                    </div>
                </div>

                {{-- Token Input --}}
                <div class="mb-3">
                    <label for="tokens_used" class="form-label fw-bold">Update Tokens Used</label>
                    <input type="number" name="tokens_used" id="tokens_used" class="form-control"
                        value="{{ old('tokens_used', $subscription->tokens_used) }}" min="0" required>
                </div>

                {{-- Reason Input --}}
                <div class="mb-3">
                    <label for="reason" class="form-label fw-bold">Reason for Adjustment (Optional)</label>
                    <input type="text" name="reason" id="reason" class="form-control"
                        placeholder="e.g., bonus tokens, reset due to bug, manual credit">
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <a href="{{ route('admin.billing.userHistory', $subscription->user_id) }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>

                    <div>
                        <button type="submit" class="btn btn-success me-2">
                            <i class="fas fa-check-circle me-1"></i> Update Usage
                        </button>

                        {{-- Reset Button (JS helper) --}}
                        <button type="button" class="btn btn-outline-danger"
                            onclick="document.getElementById('tokens_used').value = 0">
                            <i class="fas fa-undo me-1"></i> Reset Tokens
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

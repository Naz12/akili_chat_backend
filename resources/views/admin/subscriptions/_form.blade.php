<form method="POST"
    action="{{ isset($subscription) ? route('admin.subscriptions.update', $subscription) : route('admin.subscriptions.store') }}">
    @csrf
    @if (isset($subscription))
        @method('PUT')
    @endif

    <div class="mb-3">
        <label>User</label>
        <select name="user_id" class="form-select" required>
            <option value="">Select user</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}"
                    {{ old('user_id', $subscription->user_id ?? '') == $user->id ? 'selected' : '' }}>
                    {{ $user->name }} ({{ $user->email }})
                </option>
            @endforeach
        </select>
    </div>

    <div class="mb-3">
        <label>Plan</label>
        <select name="plan_id" class="form-select" required>
            <option value="">Select plan</option>
            @foreach ($plans as $plan)
                <option value="{{ $plan->id }}"
                    {{ old('plan_id', $subscription->plan_id ?? '') == $plan->id ? 'selected' : '' }}>
                    {{ $plan->name }} - {{ $plan->monthly_price }} ETB
                </option>
            @endforeach
        </select>
    </div>

    <div class="mb-3">
        <label>Start Date</label>
        <input type="date" name="start_date" class="form-control"
            value="{{ old('start_date', isset($subscription) ? \Carbon\Carbon::parse($subscription->start_date)->format('Y-m-d') : now()->format('Y-m-d')) }}"
            required>
    </div>

    <div class="mb-3">
        <label>End Date</label>
        <input type="date" name="end_date" class="form-control"
            value="{{ old('end_date', isset($subscription) ? \Carbon\Carbon::parse($subscription->end_date)->format('Y-m-d') : now()->addMonth()->format('Y-m-d')) }}"
            required>
    </div>

    <div class="mb-3">
        <label>Tokens Used</label>
        <input type="number" name="tokens_used" class="form-control"
            value="{{ old('tokens_used', $subscription->tokens_used ?? 0) }}" min="0">
    </div>

    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
            {{ old('is_active', $subscription->is_active ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_active">
            Active Subscription
        </label>
    </div>

    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="auto_renew" id="auto_renew" value="1"
            {{ old('auto_renew', $subscription->auto_renew ?? false) ? 'checked' : '' }}>
        <label class="form-check-label" for="auto_renew">
            Auto Renew
        </label>
    </div>

    @if (isset($subscription))
        <hr>
        <h5>Metadata</h5>
        
        @php
            $metadata = $subscription->metadata ?? [];
        @endphp

        <div class="mb-3">
            <label>Payment Method</label>
            <select name="payment_method" class="form-select">
                <option value="">None</option>
                <option value="stripe" {{ ($metadata['payment_method'] ?? '') === 'stripe' ? 'selected' : '' }}>Stripe</option>
                <option value="chapa" {{ ($metadata['payment_method'] ?? '') === 'chapa' ? 'selected' : '' }}>Chapa</option>
                <option value="telebirr" {{ ($metadata['payment_method'] ?? '') === 'telebirr' ? 'selected' : '' }}>Telebirr</option>
            </select>
        </div>

        <div class="mb-3">
            <label>Stripe Subscription ID</label>
            <input type="text" name="stripe_subscription_id" class="form-control"
                value="{{ $metadata['stripe_subscription_id'] ?? '' }}"
                placeholder="sub_...">
            <small class="text-muted">Stripe subscription ID for automatic renewals</small>
        </div>

        <div class="mb-3">
            <label>Billing Cycle</label>
            <select name="billing_cycle" class="form-select">
                <option value="monthly" {{ ($metadata['billing_cycle'] ?? 'monthly') === 'monthly' ? 'selected' : '' }}>Monthly</option>
                <option value="quarterly" {{ ($metadata['billing_cycle'] ?? '') === 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                <option value="annual" {{ ($metadata['billing_cycle'] ?? '') === 'annual' ? 'selected' : '' }}>Annual</option>
            </select>
        </div>

        <div class="mb-3">
            <label>Grace Period Ends At</label>
            <input type="datetime-local" name="grace_period_ends_at" class="form-control"
                value="{{ $subscription->grace_period_ends_at ? $subscription->grace_period_ends_at->format('Y-m-d\TH:i') : '' }}">
            <small class="text-muted">Leave empty if no grace period</small>
        </div>

        <div class="mb-3">
            <label>Payment Failure Count</label>
            <input type="number" name="payment_failure_count" class="form-control"
                value="{{ $subscription->payment_failure_count ?? 0 }}" min="0">
        </div>
    @endif

    <button class="btn btn-success w-100">
        {{ isset($subscription) ? 'Update' : 'Assign' }} Subscription
    </button>
</form>

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

    <div class="form-check mb-4">
        <input class="form-check-input" type="checkbox" name="auto_renew" id="auto_renew" value="1"
            {{ old('auto_renew', $subscription->auto_renew ?? false) ? 'checked' : '' }}>
        <label class="form-check-label" for="auto_renew">
            Auto Renew
        </label>
    </div>

    <button class="btn btn-success w-100">
        {{ isset($subscription) ? 'Update' : 'Assign' }} Subscription
    </button>
</form>

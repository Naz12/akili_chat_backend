<form method="POST"
    action="{{ isset($paymentMethod) ? route('admin.payment-methods.update', $paymentMethod) : route('admin.payment-methods.store') }}">
    @csrf
    @if (isset($paymentMethod))
        @method('PUT')
    @endif

    <div class="mb-3">
        <label class="form-label">Name</label>
        <input name="name" class="form-control" value="{{ old('name', $paymentMethod->name ?? '') }}" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Key (e.g., stripe, telebirr)</label>
        <input name="key" class="form-control" value="{{ old('key', $paymentMethod->key ?? '') }}" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Description (optional)</label>
        <textarea name="description" class="form-control">{{ old('description', $paymentMethod->description ?? '') }}</textarea>
    </div>

    <div class="mb-3">
        <label class="form-label">Configuration (JSON)</label>
        <textarea name="config" class="form-control" rows="5">{{ old('config', isset($paymentMethod) ? json_encode($paymentMethod->config, JSON_PRETTY_PRINT) : '{}') }}</textarea>
        <div class="form-text">Enter key-value pairs like {"api_key":"...","webhook":"..."}</div>
    </div>

    <div class="form-check mb-4">
        <input type="hidden" name="is_enabled" value="0">
        <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="is_enabled"
            {{ old('is_enabled', $paymentMethod->is_enabled ?? false) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_enabled">Enable Payment Method</label>
    </div>

    <button class="btn btn-success w-100">
        {{ isset($paymentMethod) ? 'Update Payment Method' : 'Create Payment Method' }}
    </button>
</form>

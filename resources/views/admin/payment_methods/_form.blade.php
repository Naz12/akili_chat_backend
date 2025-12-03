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

    <div class="form-check mb-3">
        <input type="hidden" name="is_enabled" value="0">
        <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="is_enabled"
            {{ old('is_enabled', $paymentMethod->is_enabled ?? false) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_enabled">Enable Payment Method</label>
    </div>

    <div class="mb-4">
        <label class="form-label fw-bold">Enable for Regions</label>
        <div class="form-text mb-2">Select which regions this payment method should be available for:</div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="regions[]" value="local" id="region_local"
                {{ in_array('local', old('regions', $paymentMethod->regions ?? [])) ? 'checked' : '' }}>
            <label class="form-check-label" for="region_local">
                <strong>Local</strong> (Ethiopia - ETB currency, Chapa)
            </label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="regions[]" value="intl" id="region_intl"
                {{ in_array('intl', old('regions', $paymentMethod->regions ?? [])) ? 'checked' : '' }}>
            <label class="form-check-label" for="region_intl">
                <strong>International</strong> (USD currency, Stripe)
            </label>
        </div>
        <div class="form-text mt-2">
            <small class="text-muted">Note: Payment method must be enabled above for region selection to take effect.</small>
        </div>
    </div>

    <button class="btn btn-success w-100">
        {{ isset($paymentMethod) ? 'Update Payment Method' : 'Create Payment Method' }}
    </button>
</form>

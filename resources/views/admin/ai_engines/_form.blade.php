<div class="row">
    <!-- Form Section -->
    <div class="col-md-7">
        <form method="POST"
            action="{{ isset($ai_engine) && $ai_engine->id ? route('admin.ai-engines.update', $ai_engine->id) : route('admin.ai-engines.store') }}">
            @csrf
            @if (isset($ai_engine) && $ai_engine->id)
                @method('PUT')
            @endif

            <!-- Name -->
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input name="name" class="form-control" value="{{ old('name', $ai_engine->name ?? '') }}" required>
            </div>

            <!-- Provider -->
            <div class="mb-3">
                <label class="form-label">Provider</label>
                <input name="provider" class="form-control" value="{{ old('provider', $ai_engine->provider ?? '') }}"
                    required>
            </div>

            <!-- API URL -->
            <div class="mb-3">
                <label class="form-label">API URL</label>
                <input name="api_url" class="form-control" value="{{ old('api_url', $ai_engine->api_url ?? '') }}"
                    required>
            </div>

            <!-- API Key -->
            <div class="mb-3">
                <label class="form-label">API Key (optional)</label>
                <div class="input-group">
                    <input type="password" name="api_key" class="form-control" id="apiKeyField"
                        value="{{ old('api_key', isset($ai_engine) && $ai_engine->api_key ? '[ENCRYPTED]' : '') }}"
                        {{ isset($ai_engine) ? 'readonly' : '' }}>
                    <button class="btn btn-outline-secondary" type="button" onclick="toggleAPIKeyVisibility(this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                @if (isset($ai_engine) && $ai_engine->readonly_api_key)
                    <div class="text-muted small">This API key is read-only and cannot be edited.</div>
                @endif
            </div>

            <!-- Max Tokens -->
            <div class="mb-3">
                <label class="form-label">Max Tokens</label>
                <input type="number" name="max_tokens" class="form-control"
                    value="{{ old('max_tokens', $ai_engine->max_tokens ?? 4096) }}" required>
            </div>

            <!-- Price per 1K -->
            <div class="mb-3">
                <label class="form-label">Price per 1K Tokens (USD)</label>
                <input type="number" step="0.0001" name="price_per_1k" class="form-control"
                    value="{{ old('price_per_1k', $ai_engine->price_per_1k ?? '') }}" required>
            </div>

            <!-- Model Type -->
            <div class="mb-3">
                <label class="form-label">Model Type</label>
                <input name="model_type" class="form-control"
                    value="{{ old('model_type', $ai_engine->model_type ?? '') }}" required>
            </div>

            <!-- Vision Support Toggle -->
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox"
                    name="is_vision_support" value="1"
                    {{ old('is_vision_support', $ai_engine->is_vision_support ?? false) ? 'checked' : '' }}>
                <label class="form-check-label">
                    Supports Vision (images / PDFs / DOCX)
                </label>
            </div>

            <!-- Version -->
            <div class="mb-3">
                <label class="form-label">Version</label>
                <input name="version" class="form-control" value="{{ old('version', $ai_engine->version ?? '') }}">
            </div>

            <!-- Priority -->
            <div class="mb-3">
                <label class="form-label">Priority Order</label>
                <input type="number" name="priority_order" class="form-control"
                    value="{{ old('priority_order', $ai_engine->priority_order ?? 1) }}" required>
            </div>

            <!-- Fallback Toggle -->
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="is_fallback" value="1"
                    {{ old('is_fallback', $ai_engine->is_fallback ?? false) ? 'checked' : '' }}>
                <label class="form-check-label">Is Fallback Engine?</label>
            </div>

            <!-- Active Toggle -->
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="is_active" value="1"
                    {{ old('is_active', $ai_engine->is_active ?? true) ? 'checked' : '' }}>
                <label class="form-check-label">Active</label>
            </div>

            <!-- Submit -->
            <button class="btn btn-success w-100">{{ isset($ai_engine) ? 'Update Engine' : 'Add Engine' }}</button>
        </form>
    </div>


    <!-- Side Info Panel -->
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <i class="fas fa-chart-line me-1"></i> AI Pricing & Currency
            </div>
            <div class="card-body small">
                <p><strong>USD to ETB:</strong> <span id="usd-etb-rate" class="text-primary">Loading...</span></p>

                <div class="list-group list-group-flush" id="engineSelector">
                    @foreach ($models as $model)
                        <button type="button" class="list-group-item list-group-item-action engine-option"
                            data-name="{{ $model['name'] }}" data-provider="{{ $model['provider'] }}"
                            data-price="{{ $model['price'] }}" data-tokens="{{ $model['tokens'] }}"
                            data-api="{{ $model['api'] }}" data-model_type="{{ $model['model_type'] }}"
                            data-version="{{ $model['version'] }}">
                            <strong>{{ $model['name'] }}</strong><br>
                            ${{ number_format($model['price'], 4) }}/1K →
                            <span class="text-muted price-local" data-usd="{{ $model['price'] }}">...</span> ETB
                            <div class="small text-muted">{{ $model['api'] }}</div>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header bg-info text-white">
                <i class="fas fa-calculator me-2"></i> ETB Cost Estimator
            </div>
            <div class="card-body small">
                <p class="mb-1"><strong>Estimates based on entered Max Tokens and Price/1K</strong></p>
                <p class="mb-2">
                    <strong>Estimated USD:</strong>
                    <span id="usd-cost">0.0000</span><br>
                    <strong>Estimated ETB:</strong>
                    <span id="etb-cost">0.00</span>
                </p>
            </div>
        </div>
    </div>

</div>

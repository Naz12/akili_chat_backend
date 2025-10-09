<div class="container">
    <div class="row">
        <!-- 📋 Plan Form -->
        <div class="col-md-7">
            <form method="POST"
                action="{{ isset($plan) ? route('admin.plans.update', $plan) : route('admin.plans.store') }}">
                @csrf
                @if (isset($plan))
                    @method('PUT')
                @endif

                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input name="name" class="form-control" value="{{ old('name', $plan->name ?? '') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Monthly Price</label>
                    <input name="monthly_price" type="number" step="0.01" class="form-control"
                        value="{{ old('monthly_price', $plan->monthly_price ?? '') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Max Tokens</label>
                    <input name="max_tokens" type="number" class="form-control"
                        value="{{ old('max_tokens', $plan->max_tokens ?? '') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Daily Message Limit</label>
                    <input name="daily_message_limit" type="number" class="form-control"
                        value="{{ old('daily_message_limit', $plan->daily_message_limit ?? '') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Engine</label>
                    <select name="engine_id" class="form-select" required>
                        <option value="">Select Engine</option>
                        @foreach ($engines as $engine)
                            <option value="{{ $engine->id }}"
                                {{ old('engine_id', $plan->engine_id ?? '') == $engine->id ? 'selected' : '' }}>
                                {{ $engine->name }} ({{ $engine->version }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Region</label>
                    <select name="region" id="region" class="form-control" required>
                        <option value="local" {{ old('region', $plan->region ?? '') === 'local' ? 'selected' : '' }}>
                            Local</option>
                        <option value="intl" {{ old('region', $plan->region ?? '') === 'intl' ? 'selected' : '' }}>
                            International</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Currency</label>
                    <select name="currency" class="form-control" required>
                        <option value="">Select Currency</option>
                        @foreach ($currencies as $code => $label)
                            <option value="{{ $code }}"
                                {{ old('currency', $plan->currency ?? '') === $code ? 'selected' : '' }}>
                                {{ $code }} - {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>


                <!-- Hidden value to handle unchecked state -->
                <input type="hidden" name="ads_enabled" value="0">

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="ads_enabled" id="ads_enabled" value="1"
                        {{ old('ads_enabled', $plan->ads_enabled ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label" for="ads_enabled">
                        Enable Ads
                    </label>
                </div>
                {{-- ─────── Banner / Hero image ───────--}}
                <div class="mb-3">
                    <label class="form-label">Image URL (Optional)</label>
                    <input  name="image_url"
                            type="url"
                            class="form-control"
                            placeholder="https://cdn.example.com/banners/pro.webp"
                            value="{{ old('image_url', $plan->image_url ?? '') }}">
                    <small class="text-muted">Shown on the plan card in the mobile app (16:9 recommended).</small>
                </div>

                {{-- ─────── Friendly marketing description ───────--}}
                <div class="mb-3">
                    <label class="form-label">Description (Optional)</label>
                    <textarea name="description"
                            rows="3"
                            class="form-control"
                            placeholder="Unlimited GPT-4, 200k tokens/day, vision support">{{ old('description', $plan->description ?? '') }}</textarea>
                </div>

                {{-- ─────── Tag / Badge (Optional) ───────--}}
                <div class="mb-3">
                    <label class="form-label">Plan Tag</label>
                    <select name="tag" class="form-select">
                        <option value="" {{ old('tag', $plan->tag ?? '') == '' ? 'selected' : '' }}>None</option>
                        <option value="starter"   {{ old('tag', $plan->tag ?? '') == 'starter'   ? 'selected' : '' }}>Starter</option>
                        <option value="popular"   {{ old('tag', $plan->tag ?? '') == 'popular'   ? 'selected' : '' }}>Popular</option>
                        <option value="best_value"{{ old('tag', $plan->tag ?? '') == 'best_value'? 'selected' : '' }}>Best Value</option>
                        <option value="recommended"{{ old('tag', $plan->tag ?? '') == 'recommended'? 'selected' : '' }}>Recommended</option>
                    </select>
                    <small class="text-muted">Displayed as a coloured badge in the app.</small>
                </div>

                <input type="hidden" name="is_default" value="0">
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="is_default" id="is_default" value="1"
                        {{ old('is_default', $plan->is_default ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_default">
                        Make this the Default Guest Plan
                    </label>
                    <div class="form-text text-muted">
                        Only one plan can be default at a time. Default is used for guest users with no subscription.
                    </div>
                </div>

                <button class="btn btn-success w-100">
                    {{ isset($plan) ? 'Update Plan' : 'Create Plan' }}
                </button>
            </form>


        </div>

        <!-- 📊 Estimation Panel -->
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-light fw-bold">
                    <i class="fas fa-calculator text-info me-1"></i> Profitability Estimation
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush small">

                        <li class="list-group-item">
                            <strong>Monthly Revenue:</strong>
                            <span id="est_revenue">-</span> ETB
                            <br><small class="text-muted">The amount charged to the user per month.</small>
                        </li>

                        <li class="list-group-item">
                            <strong>Messages/Month:</strong> <span id="est_msgs">-</span>
                            <br><small class="text-muted">Daily limit × 30 days.</small>
                        </li>

                        <li class="list-group-item">
                            <strong>Tokens/Month:</strong> <span id="est_tokens">-</span>
                            <br><small class="text-muted">Total tokens used based on daily messages.</small>
                        </li>

                        <li class="list-group-item">
                            <strong>AI Cost:</strong> <span id="est_cost">-</span> ETB
                            <br><small class="text-muted">Total token cost converted from USD using real-time exchange
                                rate.</small>
                        </li>

                        <li class="list-group-item">
                            <strong>Cost/Message:</strong> <span id="est_cost_per_msg">-</span> ETB
                            <br><small class="text-muted">How much each user message costs on average.</small>
                        </li>

                        <li class="list-group-item text-primary">
                            <strong>Platform Cost:</strong> <span id="platform_cost">5.00</span> ETB
                            <br><small class="text-muted">Base monthly fee for hosting, support, and overhead.</small>
                        </li>

                        <li class="list-group-item fw-bold">
                            <strong>Estimated Profit:</strong> <span id="est_profit">-</span> ETB
                            <br><small class="text-muted">Revenue minus AI and platform costs.</small>
                        </li>

                        <li class="list-group-item">
                            <strong>Recommended Price:</strong> <span id="recommended_price"
                                class="text-primary">-</span> ETB
                            <br><small class="text-muted">Suggested minimum to break even with 10% margin.</small>
                        </li>

                    </ul>

                    <div id="recommendationBox" class="mt-3">
                        <div class="alert alert-info p-2 m-0" id="planRecommendation">
                            💡 Estimating recommendation...
                        </div>
                    </div>

                    <div class="mt-3" id="warningBox" style="display:none;">
                        <div class="alert alert-danger p-2 m-0">
                            ⚠️ This plan will lose money.
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

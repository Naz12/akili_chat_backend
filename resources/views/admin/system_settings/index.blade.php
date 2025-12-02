@extends('layouts.admin')

@section('title', 'System Settings')

@section('content')
    <style>
        .settings-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .settings-header {
            color: white;
        }

        .settings-header h2 {
            font-weight: 700;
            font-size: 2rem;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .settings-accordion .accordion-item {
            border: none;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .settings-accordion .accordion-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .settings-accordion .accordion-button {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border: none;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            font-size: 1.1rem;
            color: #2d3748;
            box-shadow: none;
        }

        .settings-accordion .accordion-button:not(.collapsed) {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .settings-accordion .accordion-button:focus {
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }

        .settings-accordion .accordion-body {
            background: #f8f9fa;
            padding: 2rem;
        }

        .setting-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            height: 100%;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .setting-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
            transform: translateY(-2px);
        }

        .setting-label {
            font-weight: 600;
            font-size: 1rem;
            color: #2d3748;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .setting-label i {
            color: #667eea;
            font-size: 0.9rem;
        }

        .setting-description {
            font-size: 0.875rem;
            color: #718096;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .setting-input {
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .setting-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }

        .form-switch .form-check-input {
            width: 3rem;
            height: 1.5rem;
            cursor: pointer;
        }

        .form-switch .form-check-input:checked {
            background-color: #10b981;
            border-color: #10b981;
        }

        .setting-meta {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .setting-range {
            font-size: 0.8rem;
            color: #718096;
            background: #f7fafc;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }

        .btn-reset {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            color: #4a5568;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .btn-reset:hover {
            background: #edf2f7;
            border-color: #cbd5e0;
            color: #2d3748;
        }

        .save-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 2px solid #e2e8f0;
        }

        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.875rem 2rem;
            font-weight: 600;
            font-size: 1.1rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
        }

        .category-badge {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .accordion-button:not(.collapsed) .category-badge {
            background: rgba(255, 255, 255, 0.4);
        }

        .info-text {
            color: #718096;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-text i {
            color: #667eea;
        }

        .alert {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #718096;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e0;
        }
    </style>

    <div class="container-fluid px-4">
        <!-- Header Section -->
        <div class="settings-container">
            <div class="settings-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h2>
                        <i class="fas fa-cogs me-2"></i>System Settings
                    </h2>
                    <p class="mb-0 mt-2" style="opacity: 0.9;">
                        Manage system-wide configurations and preferences
                    </p>
                </div>
                <button type="button" class="btn btn-light btn-lg" onclick="location.reload()" style="border-radius: 8px;">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </button>
            </div>
        </div>

        <!-- Alerts -->
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><strong>Success!</strong> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><strong>Warning!</strong> {{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><strong>Error!</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Settings Form -->
        <form method="POST" action="{{ route('admin.system-settings.update') }}" id="settingsForm">
            @csrf
            @method('PUT')

            <div class="accordion settings-accordion" id="settingsAccordion">
                @foreach ($settingsByCategory as $category => $data)
                    @php
                        $icons = [
                            'payment' => 'credit-card',
                            'subscription' => 'calendar-alt',
                            'usage' => 'chart-line',
                            'guest' => 'user-friends',
                            'cache' => 'database',
                            'dashboard' => 'tachometer-alt',
                            'audit' => 'clipboard-list'
                        ];
                        $icon = $icons[$category] ?? 'cog';
                    @endphp
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading{{ ucfirst($category) }}">
                            <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button"
                                data-bs-toggle="collapse" data-bs-target="#collapse{{ ucfirst($category) }}"
                                aria-expanded="{{ $loop->first ? 'true' : 'false' }}">
                                <i class="fas fa-{{ $icon }} me-3" style="font-size: 1.2rem;"></i>
                                <span style="flex: 1;">{{ $data['label'] }}</span>
                                <span class="category-badge">{{ count($data['settings']) }} setting{{ count($data['settings']) !== 1 ? 's' : '' }}</span>
                            </button>
                        </h2>
                        <div id="collapse{{ ucfirst($category) }}"
                            class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}"
                            data-bs-parent="#settingsAccordion">
                            <div class="accordion-body">
                                @if(count($data['settings']) > 0)
                                    <div class="row g-4">
                                        @foreach ($data['settings'] as $setting)
                                            <div class="col-md-6 col-lg-4">
                                                <div class="setting-card">
                                                    <label class="setting-label" for="setting_{{ $setting->id }}">
                                                        <i class="fas fa-sliders-h"></i>
                                                        {{ $setting->label }}
                                                        @if ($setting->unit)
                                                            <span class="badge bg-light text-dark ms-2" style="font-size: 0.7rem;">{{ $setting->unit }}</span>
                                                        @endif
                                                    </label>
                                                    
                                                    @if ($setting->description)
                                                        <p class="setting-description">
                                                            <i class="fas fa-info-circle me-1" style="font-size: 0.75rem;"></i>
                                                            {{ $setting->description }}
                                                        </p>
                                                    @endif

                                                    @if ($setting->type === 'boolean')
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" 
                                                                name="settings[{{ $setting->key }}]"
                                                                id="setting_{{ $setting->id }}"
                                                                {{ $setting->getTypedValue() ? 'checked' : '' }}
                                                                onchange="updateSwitchLabel(this)">
                                                            <label class="form-check-label ms-2" for="setting_{{ $setting->id }}" id="label_{{ $setting->id }}">
                                                                <span class="badge bg-{{ $setting->getTypedValue() ? 'success' : 'secondary' }}">
                                                                    {{ $setting->getTypedValue() ? 'Enabled' : 'Disabled' }}
                                                                </span>
                                                            </label>
                                                        </div>
                                                    @elseif ($setting->type === 'integer' || $setting->type === 'float')
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light">
                                                                <i class="fas fa-hashtag"></i>
                                                            </span>
                                                            <input type="number" 
                                                                class="form-control setting-input"
                                                                name="settings[{{ $setting->key }}]"
                                                                id="setting_{{ $setting->id }}"
                                                                value="{{ $setting->getTypedValue() }}"
                                                                @if ($setting->min_value !== null) min="{{ $setting->min_value }}" @endif
                                                                @if ($setting->max_value !== null) max="{{ $setting->max_value }}" @endif
                                                                step="{{ $setting->type === 'float' ? '0.01' : '1' }}"
                                                                required>
                                                        </div>
                                                        @if ($setting->min_value !== null || $setting->max_value !== null)
                                                            <div class="setting-range mt-2">
                                                                <i class="fas fa-arrows-alt-h me-1"></i>
                                                                Range: 
                                                                @if ($setting->min_value !== null) 
                                                                    <strong>{{ $setting->min_value }}</strong>
                                                                @else 
                                                                    <span class="text-muted">∞</span>
                                                                @endif
                                                                - 
                                                                @if ($setting->max_value !== null) 
                                                                    <strong>{{ $setting->max_value }}</strong>
                                                                @else 
                                                                    <span class="text-muted">∞</span>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    @else
                                                        <input type="text" 
                                                            class="form-control setting-input"
                                                            name="settings[{{ $setting->key }}]"
                                                            id="setting_{{ $setting->id }}"
                                                            value="{{ $setting->getTypedValue() }}">
                                                    @endif

                                                    @if ($setting->default_value)
                                                        <div class="setting-meta">
                                                            <div>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-undo me-1"></i>
                                                                    Default: <strong>{{ $setting->default_value }}</strong>
                                                                    @if ($setting->unit) {{ $setting->unit }} @endif
                                                                </small>
                                                            </div>
                                                            <a href="{{ route('admin.system-settings.reset', $setting->key) }}" 
                                                                class="btn-reset"
                                                                onclick="return confirm('Reset {{ $setting->label }} to default value ({{ $setting->default_value }}{{ $setting->unit ? ' ' . $setting->unit : '' }})?')">
                                                                <i class="fas fa-undo me-1"></i>Reset
                                                            </a>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>No settings available in this category.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Save Section -->
            <div class="save-section">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <button type="submit" class="btn btn-save text-white">
                            <i class="fas fa-save me-2"></i>Save All Settings
                        </button>
                        <button type="button" class="btn btn-outline-secondary ms-2" 
                                onclick="if(confirm('Reset all changes to original values?')) document.getElementById('settingsForm').reset()"
                                style="padding: 0.875rem 2rem; border-radius: 8px;">
                            <i class="fas fa-times me-2"></i>Reset Form
                        </button>
                    </div>
                    <div class="info-text">
                        <i class="fas fa-info-circle"></i>
                        <span>Changes take effect immediately. Cache will be cleared automatically.</span>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Update switch label on change
        function updateSwitchLabel(checkbox) {
            const label = document.getElementById('label_' + checkbox.id);
            if (label) {
                const badge = label.querySelector('.badge');
                if (badge) {
                    if (checkbox.checked) {
                        badge.className = 'badge bg-success';
                        badge.textContent = 'Enabled';
                    } else {
                        badge.className = 'badge bg-secondary';
                        badge.textContent = 'Disabled';
                    }
                }
            }
        }

        // Form submission confirmation
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to save all settings? This will update system-wide configurations and may affect all users.')) {
                e.preventDefault();
            }
        });

        // Auto-expand accordion on page load if there are errors
        @if ($errors->any())
            document.addEventListener('DOMContentLoaded', function() {
                // Find the first accordion with an error and expand it
                const form = document.getElementById('settingsForm');
                const errorInputs = form.querySelectorAll('.is-invalid, input:invalid');
                if (errorInputs.length > 0) {
                    const firstError = errorInputs[0];
                    const accordionItem = firstError.closest('.accordion-item');
                    if (accordionItem) {
                        const collapse = accordionItem.querySelector('.accordion-collapse');
                        const button = accordionItem.querySelector('.accordion-button');
                        if (collapse && button && button.classList.contains('collapsed')) {
                            button.click();
                        }
                    }
                }
            });
        @endif
    </script>
@endsection

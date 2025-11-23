<div class="sidebar"
    style="width:260px; background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%); color:#fff; position:fixed; top:0; left:0; height:100vh; overflow-y:auto; box-shadow: 2px 0 12px rgba(0,0,0,0.15);">

    <!-- Branding -->
    <div class="py-4 px-4 border-bottom border-secondary">
        <h4 class="text-white mb-0 d-flex align-items-center">
            <i class="fas fa-robot me-2" style="font-size: 1.5rem; color: #6366f1;"></i>
            <span style="font-weight: 700; letter-spacing: -0.5px;">ChatDagu</span>
        </h4>
        <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">Admin Panel</small>
    </div>

    <!-- Navigation Links -->
    <nav class="mt-3">

        <a href="{{ route('admin.dashboard') }}"
            class="d-flex align-items-center px-4 py-3 text-decoration-none {{ request()->routeIs('admin.dashboard') ? 'bg-primary text-white' : 'text-light' }}"
            style="transition: all 0.2s; border-left: 3px solid {{ request()->routeIs('admin.dashboard') ? '#6366f1' : 'transparent' }};">
            <i class="fas fa-home me-3" style="width: 20px;"></i>
            <span style="font-weight: 500;">Dashboard</span>
        </a>

        <a href="{{ route('admin.plans.index') }}"
            class="d-flex align-items-center px-4 py-3 text-decoration-none {{ request()->routeIs('admin.plans.*') ? 'bg-primary text-white' : 'text-light' }}"
            style="transition: all 0.2s; border-left: 3px solid {{ request()->routeIs('admin.plans.*') ? '#6366f1' : 'transparent' }};">
            <i class="fas fa-layer-group me-3" style="width: 20px;"></i>
            <span style="font-weight: 500;">Plans</span>
        </a>

        <a href="{{ route('admin.ai-engines.index') }}"
            class="d-flex align-items-center px-4 py-3 text-decoration-none {{ request()->routeIs('admin.ai-engines.*') ? 'bg-primary text-white' : 'text-light' }}"
            style="transition: all 0.2s; border-left: 3px solid {{ request()->routeIs('admin.ai-engines.*') ? '#6366f1' : 'transparent' }};">
            <i class="fas fa-brain me-3" style="width: 20px;"></i>
            <span style="font-weight: 500;">AI Engines</span>
        </a>

        <a href="{{ route('admin.subscriptions.index') }}"
            class="d-flex align-items-center px-4 py-3 text-decoration-none {{ request()->routeIs('admin.subscriptions.*') ? 'bg-primary text-white' : 'text-light' }}"
            style="transition: all 0.2s; border-left: 3px solid {{ request()->routeIs('admin.subscriptions.*') ? '#6366f1' : 'transparent' }};">
            <i class="fas fa-user-tag me-3" style="width: 20px;"></i>
            <span style="font-weight: 500;">Subscriptions</span>
        </a>

        <a href="{{ route('admin.billing.index') }}"
            class="d-flex align-items-center px-4 py-3 text-decoration-none {{ request()->routeIs('admin.billing.*') ? 'bg-primary text-white' : 'text-light' }}"
            style="transition: all 0.2s; border-left: 3px solid {{ request()->routeIs('admin.billing.*') ? '#6366f1' : 'transparent' }};">
            <i class="fas fa-file-invoice-dollar me-3" style="width: 20px;"></i>
            <span style="font-weight: 500;">Billing</span>
        </a>

        <a href="{{ route('admin.usage.logs') }}"
            class="d-flex align-items-center px-4 py-3 text-decoration-none {{ request()->routeIs('admin.usage.logs') ? 'bg-primary text-white' : 'text-light' }}"
            style="transition: all 0.2s; border-left: 3px solid {{ request()->routeIs('admin.usage.logs') ? '#6366f1' : 'transparent' }};">
            <i class="fas fa-list-ol me-3" style="width: 20px;"></i>
            <span style="font-weight: 500;">Usage Logs</span>
        </a>

        <!-- ⚙️ System Settings Collapsible -->
        <div class="accordion mt-2" id="adminSettingsAccordion">
            <div class="accordion-item bg-transparent border-0">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed text-light px-4 py-3 d-flex align-items-center" type="button"
                        data-bs-toggle="collapse" data-bs-target="#systemSettings" aria-expanded="false"
                        style="background-color: transparent; border: none; box-shadow: none; font-weight: 500;">
                        <i class="fas fa-cogs me-3" style="width: 20px;"></i>
                        <span>System Settings</span>
                    </button>
                </h2>
                <div id="systemSettings"
                    class="accordion-collapse collapse {{ request()->routeIs('admin.payment-methods.*', 'admin.preferences.*', 'admin.webhooks.*', 'admin.notifier.*') ? 'show' : '' }}"
                    data-bs-parent="#adminSettingsAccordion">
                    <div class="accordion-body py-0 px-0" style="background-color: rgba(0,0,0,0.2);">

                        <a href="{{ route('admin.payment-methods.index') }}"
                            class="d-flex align-items-center px-4 py-2 text-decoration-none {{ request()->routeIs('admin.payment-methods.*') ? 'bg-primary text-white' : 'text-light' }}"
                            style="transition: all 0.2s; padding-left: 3rem !important;">
                            <i class="fas fa-credit-card me-3" style="width: 20px; font-size: 0.875rem;"></i>
                            <span style="font-size: 0.875rem;">Payment Methods</span>
                        </a>

                        <a href="{{ route('admin.preferences.index') }}"
                            class="d-flex align-items-center px-4 py-2 text-decoration-none {{ request()->routeIs('admin.preferences.*') ? 'bg-primary text-white' : 'text-light' }}"
                            style="transition: all 0.2s; padding-left: 3rem !important;">
                            <i class="fas fa-sliders-h me-3" style="width: 20px; font-size: 0.875rem;"></i>
                            <span style="font-size: 0.875rem;">Preferences</span>
                        </a>

                        <a href="{{ route('admin.webhooks.index') }}"
                            class="d-flex align-items-center px-4 py-2 text-decoration-none {{ request()->routeIs('admin.webhooks.*') ? 'bg-primary text-white' : 'text-light' }}"
                            style="transition: all 0.2s; padding-left: 3rem !important;">
                            <i class="fas fa-plug me-3" style="width: 20px; font-size: 0.875rem;"></i>
                            <span style="font-size: 0.875rem;">Webhooks</span>
                        </a>

                        <a href="{{ route('admin.notifier.index') }}"
                            class="d-flex align-items-center px-4 py-2 text-decoration-none {{ request()->routeIs('admin.notifier.*') ? 'bg-primary text-white' : 'text-light' }}"
                            style="transition: all 0.2s; padding-left: 3rem !important;">
                            <i class="fas fa-bullhorn me-3" style="width: 20px; font-size: 0.875rem;"></i>
                            <span style="font-size: 0.875rem;">Notifier</span>
                        </a>

                    </div>
                </div>
            </div>
        </div>

    </nav>

    <!-- Logout -->
    <div class="mt-auto px-3 pb-4" style="position: absolute; bottom: 0; width: 100%;">
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button class="btn btn-outline-light w-100 d-flex align-items-center justify-content-center"
                style="border-radius: 10px; font-weight: 500; padding: 10px;">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </button>
        </form>
    </div>
</div>

<div class="sidebar" id="adminSidebar"
    style="width:260px; background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%); color:#fff; position:fixed; top:0; left:0; height:100vh; display:flex; flex-direction:column; box-shadow: 2px 0 12px rgba(0,0,0,0.15); transition: width 0.3s ease, transform 0.3s ease; z-index: 1000;">

    <!-- Branding -->
    <div class="py-4 px-4 border-bottom border-secondary d-flex align-items-center justify-content-between" style="flex-shrink: 0;">
        <div class="d-flex align-items-center" style="flex: 1; min-width: 0;">
            <i class="fas fa-robot me-2 sidebar-icon" style="font-size: 1.5rem; color: #6366f1; flex-shrink: 0;"></i>
            <div style="overflow: hidden;">
                <h4 class="text-white mb-0 sidebar-brand" style="font-weight: 700; letter-spacing: -0.5px; white-space: nowrap;">
                    <span>ChatDagu</span>
                </h4>
                <small class="text-muted d-block mt-1 sidebar-subtitle" style="font-size: 0.75rem; white-space: nowrap;">Admin Panel</small>
            </div>
        </div>
        <button type="button" id="sidebarToggle" class="btn btn-link text-light p-1" style="border: none; background: transparent; flex-shrink: 0; margin-left: 8px;" aria-label="Toggle sidebar">
            <i class="fas fa-bars" id="sidebarToggleIcon"></i>
        </button>
    </div>

    <!-- Navigation Links -->
    <nav class="mt-3 sidebar-nav" style="flex: 1; overflow-y: auto; overflow-x: hidden; padding-bottom: 20px;">

        @if(auth()->user()->hasPermission('dashboard.view') || auth()->user()->isSuperAdmin())
        <a href="{{ route('admin.dashboard') }}"
            class="d-flex align-items-center px-4 py-3 text-decoration-none sidebar-link {{ request()->routeIs('admin.dashboard') ? 'bg-primary text-white' : 'text-light' }}"
            style="transition: all 0.2s; border-left: 3px solid {{ request()->routeIs('admin.dashboard') ? '#6366f1' : 'transparent' }};"
            title="Dashboard">
            <i class="fas fa-home sidebar-link-icon" style="width: 20px; flex-shrink: 0;"></i>
            <span class="sidebar-link-text" style="font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Dashboard</span>
        </a>
        @endif

        @if(auth()->user()->hasPermission('plans.view') || auth()->user()->isSuperAdmin())
        <a href="{{ route('admin.plans.index') }}"
            class="d-flex align-items-center px-4 py-3 text-decoration-none {{ request()->routeIs('admin.plans.*') ? 'bg-primary text-white' : 'text-light' }}"
            style="transition: all 0.2s; border-left: 3px solid {{ request()->routeIs('admin.plans.*') ? '#6366f1' : 'transparent' }};">
            <i class="fas fa-layer-group me-3" style="width: 20px;"></i>
            <span style="font-weight: 500;">Plans</span>
        </a>
        @endif

        @if(auth()->user()->hasPermission('ai_engines.view') || auth()->user()->isSuperAdmin())
        <a href="{{ route('admin.ai-engines.index') }}"
            class="d-flex align-items-center px-4 py-3 text-decoration-none {{ request()->routeIs('admin.ai-engines.*') ? 'bg-primary text-white' : 'text-light' }}"
            style="transition: all 0.2s; border-left: 3px solid {{ request()->routeIs('admin.ai-engines.*') ? '#6366f1' : 'transparent' }};">
            <i class="fas fa-brain me-3" style="width: 20px;"></i>
            <span style="font-weight: 500;">AI Engines</span>
        </a>
        @endif

        @if(auth()->user()->hasPermission('subscriptions.view') || auth()->user()->isSuperAdmin())
        <a href="{{ route('admin.subscriptions.index') }}"
            class="d-flex align-items-center px-4 py-3 text-decoration-none {{ request()->routeIs('admin.subscriptions.*') ? 'bg-primary text-white' : 'text-light' }}"
            style="transition: all 0.2s; border-left: 3px solid {{ request()->routeIs('admin.subscriptions.*') ? '#6366f1' : 'transparent' }};">
            <i class="fas fa-user-tag me-3" style="width: 20px;"></i>
            <span style="font-weight: 500;">Subscriptions</span>
        </a>
        @endif

        @if(auth()->user()->hasPermission('billing.view') || auth()->user()->isSuperAdmin())
        <a href="{{ route('admin.billing.index') }}"
            class="d-flex align-items-center px-4 py-3 text-decoration-none {{ request()->routeIs('admin.billing.*') ? 'bg-primary text-white' : 'text-light' }}"
            style="transition: all 0.2s; border-left: 3px solid {{ request()->routeIs('admin.billing.*') ? '#6366f1' : 'transparent' }};">
            <i class="fas fa-file-invoice-dollar me-3" style="width: 20px;"></i>
            <span style="font-weight: 500;">Billing</span>
        </a>
        @endif

        @if(auth()->user()->hasPermission('payments.view') || auth()->user()->isSuperAdmin())
        <a href="{{ route('admin.payments.index') }}"
            class="d-flex align-items-center px-4 py-3 text-decoration-none {{ request()->routeIs('admin.payments.*') ? 'bg-primary text-white' : 'text-light' }}"
            style="transition: all 0.2s; border-left: 3px solid {{ request()->routeIs('admin.payments.*') ? '#6366f1' : 'transparent' }};">
            <i class="fas fa-money-bill-wave me-3" style="width: 20px;"></i>
            <span style="font-weight: 500;">Payments</span>
        </a>
        @endif

        @if(auth()->user()->hasPermission('usage_logs.view') || auth()->user()->isSuperAdmin())
        <a href="{{ route('admin.usage.logs') }}"
            class="d-flex align-items-center px-4 py-3 text-decoration-none {{ request()->routeIs('admin.usage.logs') ? 'bg-primary text-white' : 'text-light' }}"
            style="transition: all 0.2s; border-left: 3px solid {{ request()->routeIs('admin.usage.logs') ? '#6366f1' : 'transparent' }};">
            <i class="fas fa-list-ol me-3" style="width: 20px;"></i>
            <span style="font-weight: 500;">Usage Logs</span>
        </a>
        @endif

        @if(auth()->user()->hasPermission('visitors.view') || auth()->user()->isSuperAdmin())
        <a href="{{ route('admin.visitors.index') }}"
            class="d-flex align-items-center px-4 py-3 text-decoration-none {{ request()->routeIs('admin.visitors.*') ? 'bg-primary text-white' : 'text-light' }}"
            style="transition: all 0.2s; border-left: 3px solid {{ request()->routeIs('admin.visitors.*') ? '#6366f1' : 'transparent' }};">
            <i class="fas fa-users me-3" style="width: 20px;"></i>
            <span style="font-weight: 500;">Visitors</span>
        </a>
        @endif

        @if((auth()->user()->hasPermission('admins.view') || auth()->user()->isSuperAdmin()))
        <a href="{{ route('admin.admins.index') }}"
            class="d-flex align-items-center px-4 py-3 text-decoration-none {{ request()->routeIs('admin.admins.*') ? 'bg-primary text-white' : 'text-light' }}"
            style="transition: all 0.2s; border-left: 3px solid {{ request()->routeIs('admin.admins.*') ? '#6366f1' : 'transparent' }};">
            <i class="fas fa-users-cog me-3" style="width: 20px;"></i>
            <span style="font-weight: 500;">Admin Users</span>
        </a>
        @endif

        @if((auth()->user()->hasPermission('admins.view_audit_logs') || auth()->user()->isSuperAdmin()))
        <a href="{{ route('admin.audit-logs.index') }}"
            class="d-flex align-items-center px-4 py-3 text-decoration-none {{ request()->routeIs('admin.audit-logs.*') ? 'bg-primary text-white' : 'text-light' }}"
            style="transition: all 0.2s; border-left: 3px solid {{ request()->routeIs('admin.audit-logs.*') ? '#6366f1' : 'transparent' }};">
            <i class="fas fa-history me-3" style="width: 20px;"></i>
            <span style="font-weight: 500;">Audit Logs</span>
        </a>
        @endif

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
                    class="accordion-collapse collapse {{ request()->routeIs('admin.payment-methods.*', 'admin.preferences.*', 'admin.webhooks.*', 'admin.notifier.*', 'admin.system-settings.*') ? 'show' : '' }}"
                    data-bs-parent="#adminSettingsAccordion">
                    <div class="accordion-body py-0 px-0" style="background-color: rgba(0,0,0,0.2);">

                        @if(auth()->user()->hasPermission('payment_methods.manage') || auth()->user()->isSuperAdmin())
                        <a href="{{ route('admin.payment-methods.index') }}"
                            class="d-flex align-items-center px-4 py-2 text-decoration-none {{ request()->routeIs('admin.payment-methods.*') ? 'bg-primary text-white' : 'text-light' }}"
                            style="transition: all 0.2s; padding-left: 3rem !important;">
                            <i class="fas fa-credit-card me-3" style="width: 20px; font-size: 0.875rem;"></i>
                            <span style="font-size: 0.875rem;">Payment Methods</span>
                        </a>
                        @endif

                        @if(auth()->user()->hasPermission('preferences.manage') || auth()->user()->isSuperAdmin())
                        <a href="{{ route('admin.preferences.index') }}"
                            class="d-flex align-items-center px-4 py-2 text-decoration-none {{ request()->routeIs('admin.preferences.*') ? 'bg-primary text-white' : 'text-light' }}"
                            style="transition: all 0.2s; padding-left: 3rem !important;">
                            <i class="fas fa-sliders-h me-3" style="width: 20px; font-size: 0.875rem;"></i>
                            <span style="font-size: 0.875rem;">Preferences</span>
                        </a>
                        @endif

                        @if(auth()->user()->hasPermission('settings.edit') || auth()->user()->isSuperAdmin())
                        <a href="{{ route('admin.system-settings.index') }}"
                            class="d-flex align-items-center px-4 py-2 text-decoration-none {{ request()->routeIs('admin.system-settings.*') ? 'bg-primary text-white' : 'text-light' }}"
                            style="transition: all 0.2s; padding-left: 3rem !important;">
                            <i class="fas fa-cog me-3" style="width: 20px; font-size: 0.875rem;"></i>
                            <span style="font-size: 0.875rem;">Configure System</span>
                        </a>
                        @endif

                        @if(auth()->user()->hasPermission('webhooks.view') || auth()->user()->isSuperAdmin())
                        <a href="{{ route('admin.webhooks.index') }}"
                            class="d-flex align-items-center px-4 py-2 text-decoration-none {{ request()->routeIs('admin.webhooks.*') ? 'bg-primary text-white' : 'text-light' }}"
                            style="transition: all 0.2s; padding-left: 3rem !important;">
                            <i class="fas fa-plug me-3" style="width: 20px; font-size: 0.875rem;"></i>
                            <span style="font-size: 0.875rem;">Webhooks</span>
                        </a>
                        @endif

                        @if(auth()->user()->hasPermission('notifier.manage') || auth()->user()->isSuperAdmin())
                        <a href="{{ route('admin.notifier.index') }}"
                            class="d-flex align-items-center px-4 py-2 text-decoration-none {{ request()->routeIs('admin.notifier.*') ? 'bg-primary text-white' : 'text-light' }}"
                            style="transition: all 0.2s; padding-left: 3rem !important;">
                            <i class="fas fa-bullhorn me-3" style="width: 20px; font-size: 0.875rem;"></i>
                            <span style="font-size: 0.875rem;">Notifier</span>
                        </a>
                        @endif

                    </div>
                </div>
            </div>
        </div>

    </nav>

    <!-- Logout -->
    <div class="px-3 pb-4 pt-3 border-top border-secondary sidebar-logout" style="flex-shrink: 0; background-color: rgba(0,0,0,0.2);">
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button class="btn btn-outline-light w-100 d-flex align-items-center justify-content-center sidebar-logout-btn"
                style="border-radius: 10px; font-weight: 500; padding: 10px;" title="Logout">
                <i class="fas fa-sign-out-alt sidebar-logout-icon"></i>
                <span class="sidebar-logout-text ms-2">Logout</span>
            </button>
        </form>
    </div>
</div>

<div class="sidebar"
    style="width:240px; background-color:#212529; color:#fff; position:fixed; top:0; left:0; height:100vh; overflow-y:auto;">

    <!-- Branding -->
    <div class="py-3 px-4 border-bottom">
        <h5 class="text-white mb-0">
            <i class="fas fa-robot me-2"></i>ChatDagu
        </h5>
    </div>

    <!-- Navigation Links -->
    <nav class="mt-3">

        <a href="{{ route('admin.dashboard') }}"
            class="d-block px-4 py-2 text-decoration-none {{ request()->routeIs('admin.dashboard') ? 'bg-secondary text-white' : 'text-light' }}">
            <i class="fas fa-home me-2"></i> Dashboard
        </a>

        <a href="{{ route('admin.plans.index') }}"
            class="d-block px-4 py-2 text-decoration-none {{ request()->routeIs('admin.plans.*') ? 'bg-secondary text-white' : 'text-light' }}">
            <i class="fas fa-layer-group me-2"></i> Plans
        </a>

        <a href="{{ route('admin.ai-engines.index') }}"
            class="d-block px-4 py-2 text-decoration-none {{ request()->routeIs('admin.ai-engines.*') ? 'bg-secondary text-white' : 'text-light' }}">
            <i class="fas fa-brain me-2"></i> AI Engines
        </a>

        <a href="{{ route('admin.subscriptions.index') }}"
            class="d-block px-4 py-2 text-decoration-none {{ request()->routeIs('admin.subscriptions.*') ? 'bg-secondary text-white' : 'text-light' }}">
            <i class="fas fa-user-tag me-2"></i> Subscriptions
        </a>

        <a href="{{ route('admin.billing.index') }}"
            class="d-block px-4 py-2 text-decoration-none {{ request()->routeIs('admin.billing.*') ? 'bg-secondary text-white' : 'text-light' }}">
            <i class="fas fa-file-invoice-dollar me-2"></i> Billing
        </a>

        <a href="{{ route('admin.usage.logs') }}"
            class="d-block px-4 py-2 text-decoration-none {{ request()->routeIs('admin.usage.logs') ? 'bg-secondary text-white' : 'text-light' }}">
            <i class="fas fa-list-ol me-2"></i> Usage Logs
        </a>

        <!-- ⚙️ System Settings Collapsible -->
        <div class="accordion" id="adminSettingsAccordion">
            <div class="accordion-item bg-transparent border-0">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed bg-dark text-light px-4 py-2" type="button"
                        data-bs-toggle="collapse" data-bs-target="#systemSettings" aria-expanded="false">
                        <i class="fas fa-cogs me-2"></i> System Settings
                    </button>
                </h2>
                <div id="systemSettings"
                    class="accordion-collapse collapse {{ request()->routeIs('admin.payment-methods.*', 'admin.preferences.*', 'admin.webhooks.*', 'admin.notifier.*') ? 'show' : '' }}"
                    data-bs-parent="#adminSettingsAccordion">
                    <div class="accordion-body py-0 px-0">

                        <a href="{{ route('admin.payment-methods.index') }}"
                            class="d-block px-4 py-2 text-decoration-none {{ request()->routeIs('admin.payment-methods.*') ? 'bg-secondary text-white' : 'text-light' }}">
                            <i class="fas fa-credit-card me-2"></i> Payment Methods
                        </a>

                        <a href="{{ route('admin.preferences.index') }}"
                            class="d-block px-4 py-2 text-decoration-none {{ request()->routeIs('admin.preferences.*') ? 'bg-secondary text-white' : 'text-light' }}">
                            <i class="fas fa-sliders-h me-2"></i> Preferences
                        </a>

                        <a href="{{ route('admin.webhooks.index') }}"
                            class="d-block px-4 py-2 text-decoration-none {{ request()->routeIs('admin.webhooks.*') ? 'bg-secondary text-white' : 'text-light' }}">
                            <i class="fas fa-plug me-2"></i> Webhooks
                        </a>

                        <a href="{{ route('admin.notifier.index') }}"
                            class="d-block px-4 py-2 text-decoration-none {{ request()->routeIs('admin.notifier.*') ? 'bg-secondary text-white' : 'text-light' }}">
                            <i class="fas fa-bullhorn me-2"></i> Notifier
                        </a>

                    </div>
                </div>
            </div>
        </div>

    </nav>



    <!-- Logout -->
    <div class="mt-4 px-3">
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button class="btn btn-outline-light w-100">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </button>
        </form>
    </div>
</div>

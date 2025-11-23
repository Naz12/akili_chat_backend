<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4" style="border-radius: 12px;">
    <div class="container-fluid">
        <span class="navbar-brand fw-bold" style="color: #1e293b; font-size: 1.25rem;">
            <i class="fas fa-cogs me-2" style="color: #6366f1;"></i>@yield('title', 'Admin')
        </span>
        <div class="d-flex align-items-center">
            <div class="d-none d-lg-flex align-items-center">
                <div class="d-flex align-items-center px-3 py-2" style="background-color: #f1f5f9; border-radius: 8px;">
                    <i class="fas fa-user-circle me-2" style="color: #6366f1; font-size: 1.25rem;"></i>
                    <div>
                        <small class="text-muted d-block" style="font-size: 0.7rem; line-height: 1;">Welcome,</small>
                        <span class="fw-semibold" style="color: #1e293b; font-size: 0.9rem;">{{ auth()->user()->name ?? 'Admin' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

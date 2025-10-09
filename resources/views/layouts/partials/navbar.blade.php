<nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm mb-4">
    <div class="container-fluid">
        <span class="navbar-brand"><i class="fas fa-cogs me-1"></i>@yield('title', 'Admin')</span>
        <div class="d-none d-lg-block">
            <span class="text-muted small">Welcome, {{ auth()->user()->name ?? 'Admin' }}</span>
        </div>
    </div>
</nav>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Admin Panel')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Bootstrap & FontAwesome --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    {{-- Optional custom CSS --}}
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .admin-wrapper {
            flex: 1;
            display: flex;
        }

        .main-content {
            margin-left: 240px;
            padding: 20px;
            width: 100%;
        }
    </style>

    @stack('styles')
</head>

<body>

    {{-- Sidebar --}}
    @include('layouts.partials.sidebar')

    {{-- Main area --}}
    <div class="admin-wrapper">
        <div class="main-content">
            {{-- Navbar --}}
            @include('layouts.partials.navbar')

            {{-- Flash message --}}
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- Main content --}}
            @yield('content')
        </div>
    </div>

    {{-- Footer --}}
    @include('layouts.partials.footer')

    {{-- JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>

</html>

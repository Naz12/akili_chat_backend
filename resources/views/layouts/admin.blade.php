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
        :root {
            --sidebar-width: 260px;
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --sidebar-bg: #1e293b;
            --sidebar-hover: #334155;
        }

        html {
            overflow-x: hidden;
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #f8fafc;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            overflow-x: hidden;
            width: 100%;
            max-width: 100vw;
        }

        .admin-wrapper {
            flex: 1;
            display: flex;
            width: 100%;
            overflow-x: hidden;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 24px;
            width: calc(100vw - var(--sidebar-width));
            max-width: calc(100vw - var(--sidebar-width));
            transition: margin-left 0.3s ease;
            overflow-x: auto;
            box-sizing: border-box;
            position: relative;
        }

        /* Card enhancements */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        /* Table improvements */
        .table {
            border-radius: 8px;
            overflow: hidden;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-responsive table {
            min-width: 100%;
            width: max-content;
        }

        .table thead th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            border: none;
            padding: 12px 16px;
        }

        .table tbody tr {
            transition: background-color 0.15s;
        }

        .table tbody tr:hover {
            background-color: #f8fafc;
        }

        .table tbody td {
            vertical-align: middle;
            padding: 12px 16px;
            border-color: #e2e8f0;
        }

        /* Button enhancements */
        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 8px 16px;
            transition: all 0.2s;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(79, 70, 229, 0.3);
        }

        /* Badge improvements */
        .badge {
            padding: 4px 10px;
            font-weight: 500;
            border-radius: 6px;
        }

        /* Alert improvements */
        .alert {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
                width: 100%;
                max-width: 100%;
            }
        }

        /* Pagination fixes */
        .pagination {
            margin-bottom: 0;
            justify-content: center;
        }

        .pagination .page-link {
            border-radius: 6px;
            margin: 0 2px;
            color: #6366f1;
            border: 1px solid #e2e8f0;
            padding: 6px 12px;
            font-size: 0.875rem;
            min-width: 38px;
            text-align: center;
        }

        .pagination .page-link:hover {
            background-color: #f1f5f9;
            color: #4338ca;
            border-color: #cbd5e1;
        }

        .pagination .page-item.active .page-link {
            background-color: #6366f1;
            border-color: #6366f1;
            color: white;
        }

        .pagination .page-item.disabled .page-link {
            background-color: #f8fafc;
            border-color: #e2e8f0;
            color: #cbd5e1;
            cursor: not-allowed;
            opacity: 0.6;
        }

        /* Fix for pagination arrows - make them smaller and consistent */
        .pagination .page-link[aria-label*="previous"],
        .pagination .page-link[aria-label*="next"],
        .pagination .page-item:first-child .page-link,
        .pagination .page-item:last-child .page-link {
            padding: 6px 10px !important;
            font-size: 0.875rem !important;
            min-width: 38px !important;
            max-width: 38px !important;
            width: 38px !important;
            text-align: center !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            line-height: 1 !important;
            overflow: hidden !important;
            box-sizing: border-box !important;
        }

        /* Ensure FontAwesome icons in pagination are properly sized */
        .pagination .page-link i,
        .pagination .page-link i.fas,
        .pagination .page-link i.fa-chevron-left,
        .pagination .page-link i.fa-chevron-right {
            font-size: 0.75rem !important;
            line-height: 1 !important;
            width: auto !important;
            height: auto !important;
            max-width: 12px !important;
            max-height: 12px !important;
            display: inline-block !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Prevent any icon from expanding */
        .pagination .page-item:first-child,
        .pagination .page-item:last-child {
            max-width: 38px !important;
            flex-shrink: 0 !important;
        }

        /* Ensure pagination doesn't break layout */
        .pagination {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            max-width: 100%;
        }

        .pagination .page-item {
            flex-shrink: 0;
        }
    </style>

    {{-- Google Font --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

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

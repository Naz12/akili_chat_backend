@extends('layouts.guest') {{-- or use 'layouts.app' or 'layouts.admin' as needed --}}

@section('title', 'Privacy Policy')

@section('content')
    <div class="container py-5">
        <h2 class="mb-4">Privacy Policy</h2>

        <p>Your privacy is important to us. This policy explains how we collect, use, and protect your information.</p>

        <h4>1. Information We Collect</h4>
        <ul>
            <li>Account details (name, email, phone, etc.)</li>
            <li>Usage data (logs, preferences, interactions)</li>
            <li>Device and app metadata (for mobile users)</li>
        </ul>

        <h4>2. How We Use Your Data</h4>
        <p>We use your information to provide and improve our services, process transactions, and send relevant
            notifications.</p>

        <h4>3. Sharing Your Information</h4>
        <p>We never sell your data. We may share it with service providers who help us run the platform, under strict
            confidentiality terms.</p>

        <h4>4. Your Rights</h4>
        <p>You have the right to access, update, or delete your personal data. Contact support to make a request.</p>

        <h4>5. Data Security</h4>
        <p>We implement security safeguards to protect your data from unauthorized access or disclosure.</p>

        <h4>6. Changes to Policy</h4>
        <p>This privacy policy may be updated. Continued use of our services implies acceptance of any changes.</p>

        <p class="mt-5">If you have any questions or concerns, please contact our privacy team.</p>
    </div>
@endsection

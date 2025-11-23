@extends('layouts.guest') {{-- or use 'layouts.app' or 'layouts.admin' as needed --}}

@section('title', 'Terms & Conditions')

@section('content')
    <div class="container py-5">
        <h2 class="mb-4">Terms & Conditions</h2>

        <p>Welcome to our platform. By accessing or using our service, you agree to be bound by the following terms and
            conditions.</p>

        <h4>1. Use of Service</h4>
        <p>You agree to use the platform only for lawful purposes and in a way that does not infringe the rights of others.
        </p>

        <h4>2. Account Responsibility</h4>
        <p>You are responsible for maintaining the confidentiality of your account and password and for all activities that
            occur under your account.</p>

        <h4>3. Subscription & Payments</h4>
        <p>Our services may include paid features. You agree to pay all applicable charges in accordance with your selected
            plan.</p>

        <h4>4. Termination</h4>
        <p>We reserve the right to suspend or terminate your access if you violate these terms or misuse the service.</p>

        <h4>5. Changes to Terms</h4>
        <p>We may update these terms from time to time. Continued use of the platform means you accept the revised terms.
        </p>

        <p class="mt-5">If you have any questions about these terms, please contact us.</p>
    </div>
@endsection

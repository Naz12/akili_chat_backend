@extends('layouts.admin')

@section('title', 'Payment Methods')

@section('content')
    <h2 class="mb-3">Payment Methods</h2>
    <a href="{{ route('admin.payment-methods.create') }}" class="btn btn-primary mb-3">Add New Payment Method</a>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Name</th>
                <th>Key</th>
                <th>Status</th>
                <th>Config</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($methods as $method)
                <tr>
                    <td>{{ $method->name }}</td>
                    <td><code>{{ $method->key }}</code></td>
                    <td>
                        <span class="badge bg-{{ $method->is_enabled ? 'success' : 'secondary' }}">
                            {{ $method->is_enabled ? 'Enabled' : 'Disabled' }}
                        </span>
                    </td>
                    <td>
                        <small class="text-muted">{{ json_encode($method->config) }}</small>
                    </td>
                    <td>
                        <a href="{{ route('admin.payment-methods.edit', $method) }}" class="btn btn-sm btn-warning">Edit</a>
                        <form method="POST" action="{{ route('admin.payment-methods.destroy', $method) }}" class="d-inline">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger"
                                onclick="return confirm('Delete this method?')">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

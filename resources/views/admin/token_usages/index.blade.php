@extends('layouts.admin')
@section('title', 'Token Usage Logs')

@section('content')
    <h2 class="mb-3">Token Usage Logs</h2>

    <table class="table table-bordered table-sm">
        <thead>
            <tr>
                <th>User</th>
                <th>Engine</th>
                <th>Tokens</th>
                <th>Cost</th>
                <th>Prompt</th>
                <th>Response</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($usages as $usage)
                <tr>
                    <td>{{ $usage->user->name ?? 'N/A' }}</td>
                    <td>{{ $usage->engine->name ?? 'Unknown' }}</td>
                    <td>{{ $usage->tokens_used }}</td>
                    <td>${{ number_format($usage->cost, 4) }}</td>
                    <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis;">
                        {{ Str::limit($usage->prompt, 80) }}</td>
                    <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis;">
                        {{ Str::limit($usage->response, 80) }}</td>
                    <td>{{ $usage->created_at->format('Y-m-d H:i') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">No usage logs found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-3">
        {{ $usages->links() }}
    </div>
@endsection

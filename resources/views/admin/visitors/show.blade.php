@extends('layouts.admin')
@section('title', 'Visitor Details')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.visitors.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Visitors
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-user me-2"></i>Visitor Details
                    </h4>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="200">Session ID:</th>
                            <td><code>{{ $visitor->session_id }}</code></td>
                        </tr>
                        <tr>
                            <th>User:</th>
                            <td>
                                @if ($visitor->is_logged_in && $visitor->user)
                                    <strong>{{ $visitor->user->name }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $visitor->user->email }}</small>
                                @else
                                    <span class="text-muted">Guest User</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>IP Address:</th>
                            <td>{{ $visitor->ip_address }}</td>
                        </tr>
                        <tr>
                            <th>Location:</th>
                            <td>
                                @if ($visitor->country)
                                    <span class="badge bg-info">{{ $visitor->country }}</span>
                                    {{ $visitor->country_name }}
                                    @if ($visitor->city)
                                        <br>
                                        <small class="text-muted">{{ $visitor->city }}, {{ $visitor->region }}</small>
                                    @endif
                                @else
                                    <span class="text-muted">Unknown</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Device:</th>
                            <td>
                                <i class="{{ $visitor->device_icon }}"></i>
                                {{ ucfirst($visitor->device_type) }}
                                @if ($visitor->is_mobile)
                                    <span class="badge bg-primary ms-2">Mobile</span>
                                @endif
                                @if ($visitor->is_tablet)
                                    <span class="badge bg-info ms-2">Tablet</span>
                                @endif
                                @if ($visitor->is_desktop)
                                    <span class="badge bg-success ms-2">Desktop</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Browser:</th>
                            <td>
                                {{ $visitor->browser }}
                                @if ($visitor->browser_version)
                                    <small class="text-muted">v{{ $visitor->browser_version }}</small>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Operating System:</th>
                            <td>
                                {{ $visitor->os }}
                                @if ($visitor->os_version)
                                    <small class="text-muted">{{ $visitor->os_version }}</small>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Screen Resolution:</th>
                            <td>{{ $visitor->screen_resolution ?? 'Unknown' }}</td>
                        </tr>
                        <tr>
                            <th>Language:</th>
                            <td>{{ $visitor->language ?? 'Unknown' }}</td>
                        </tr>
                        <tr>
                            <th>Timezone:</th>
                            <td>{{ $visitor->timezone ?? 'Unknown' }}</td>
                        </tr>
                        <tr>
                            <th>Referrer:</th>
                            <td>
                                @if ($visitor->referrer)
                                    <a href="{{ $visitor->referrer }}" target="_blank" class="text-decoration-none">
                                        {{ \Illuminate\Support\Str::limit($visitor->referrer, 50) }}
                                        <i class="fas fa-external-link-alt ms-1"></i>
                                    </a>
                                @else
                                    <span class="text-muted">Direct</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Page Views:</th>
                            <td><strong>{{ $visitor->page_views }}</strong></td>
                        </tr>
                        <tr>
                            <th>Session Duration:</th>
                            <td>{{ $visitor->formatted_duration }}</td>
                        </tr>
                        <tr>
                            <th>First Visit:</th>
                            <td>
                                {{ $visitor->first_visit_at->format('Y-m-d H:i:s') }}
                                <br>
                                <small class="text-muted">{{ $visitor->first_visit_at->diffForHumans() }}</small>
                            </td>
                        </tr>
                        <tr>
                            <th>Last Visit:</th>
                            <td>
                                {{ $visitor->last_visit_at->format('Y-m-d H:i:s') }}
                                <br>
                                <small class="text-muted">{{ $visitor->last_visit_at->diffForHumans() }}</small>
                            </td>
                        </tr>
                        <tr>
                            <th>Last Activity:</th>
                            <td>
                                @if ($visitor->last_activity_at)
                                    {{ $visitor->last_activity_at->format('Y-m-d H:i:s') }}
                                    <br>
                                    <small class="text-muted">{{ $visitor->last_activity_at->diffForHumans() }}</small>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Is Bot:</th>
                            <td>
                                <span class="badge bg-{{ $visitor->is_bot ? 'danger' : 'success' }}">
                                    {{ $visitor->is_bot ? 'Yes' : 'No' }}
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- UTM Parameters -->
            @if ($visitor->utm_source || $visitor->utm_medium || $visitor->utm_campaign)
            <div class="card shadow-sm mt-4">
                <div class="card-header">
                    <h5 class="mb-0">UTM Parameters</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        @if ($visitor->utm_source)
                        <tr>
                            <th width="200">Source:</th>
                            <td>{{ $visitor->utm_source }}</td>
                        </tr>
                        @endif
                        @if ($visitor->utm_medium)
                        <tr>
                            <th>Medium:</th>
                            <td>{{ $visitor->utm_medium }}</td>
                        </tr>
                        @endif
                        @if ($visitor->utm_campaign)
                        <tr>
                            <th>Campaign:</th>
                            <td>{{ $visitor->utm_campaign }}</td>
                        </tr>
                        @endif
                        @if ($visitor->utm_term)
                        <tr>
                            <th>Term:</th>
                            <td>{{ $visitor->utm_term }}</td>
                        </tr>
                        @endif
                        @if ($visitor->utm_content)
                        <tr>
                            <th>Content:</th>
                            <td>{{ $visitor->utm_content }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
            @endif

            <!-- Metadata -->
            @if ($visitor->metadata)
            <div class="card shadow-sm mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Metadata</h5>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 rounded">{{ json_encode($visitor->metadata, JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
            @endif
        </div>

        <div class="col-md-4">
            <!-- Quick Info -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Quick Info</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        <strong>Status:</strong><br>
                        @if ($visitor->is_logged_in)
                            <span class="badge bg-success">Logged In</span>
                        @else
                            <span class="badge bg-secondary">Guest</span>
                        @endif
                    </p>
                    <p class="mb-2">
                        <strong>User Agent:</strong><br>
                        <small class="text-muted">{{ \Illuminate\Support\Str::limit($visitor->user_agent, 100) }}</small>
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection


@extends('layouts.admin')

@section('title', 'Send Notifications')

@section('content')
    <div class="container">
        <h2 class="mb-4">Send Notification (Multi-User, Multi-Channel)</h2>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <h5 class="alert-heading"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}</h5>
                
                @if (session('channel_stats'))
                    <hr>
                    <h6 class="mb-2">Channel Results:</h6>
                    <div class="row g-2">
                        @foreach (session('channel_stats') as $channel => $stats)
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body p-2">
                                        <strong>
                                            @if ($channel === 'database') 📋 Database
                                            @elseif ($channel === 'email') 📧 Email
                                            @elseif ($channel === 'push') 🔔 Push (FCM)
                                            @elseif ($channel === 'websocket') 🔌 WebSocket
                                            @elseif ($channel === 'webpush') 🌐 Web Push
                                            @elseif ($channel === 'sms') 📲 SMS
                                            @else {{ ucfirst($channel) }}
                                            @endif
                                        </strong>
                                        <div class="mt-1">
                                            @if ($stats['success'] > 0)
                                                <span class="badge bg-success me-1">✓ {{ $stats['success'] }} sent</span>
                                            @endif
                                            @if ($stats['failed'] > 0)
                                                <span class="badge bg-danger me-1">✗ {{ $stats['failed'] }} failed</span>
                                            @endif
                                            @if ($stats['skipped'] > 0)
                                                <span class="badge bg-secondary me-1">⊘ {{ $stats['skipped'] }} skipped</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                
                @if (session('user_results') && count(session('user_results')) <= 10)
                    <hr>
                    <h6 class="mb-2">Per-User Results:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    @foreach (['database', 'email', 'push', 'websocket', 'webpush', 'sms'] as $ch)
                                        @if (collect(session('user_results'))->pluck('channels')->flatten()->keys()->contains($ch))
                                            <th class="text-center">
                                                @if ($ch === 'database') 📋
                                                @elseif ($ch === 'email') 📧
                                                @elseif ($ch === 'push') 🔔
                                                @elseif ($ch === 'websocket') 🔌
                                                @elseif ($ch === 'webpush') 🌐
                                                @elseif ($ch === 'sms') 📲
                                                @endif
                                            </th>
                                        @endif
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (session('user_results') as $result)
                                    <tr>
                                        <td>{{ $result['user'] }}</td>
                                        @foreach (['database', 'email', 'push', 'websocket', 'webpush', 'sms'] as $ch)
                                            @if (collect(session('user_results'))->pluck('channels')->flatten()->keys()->contains($ch))
                                                <td class="text-center">
                                                    @if (isset($result['channels'][$ch]))
                                                        @if ($result['channels'][$ch] === 'success')
                                                            <span class="badge bg-success">✓</span>
                                                        @elseif ($result['channels'][$ch] === 'failed')
                                                            <span class="badge bg-danger">✗</span>
                                                        @else
                                                            <span class="badge bg-secondary">⊘</span>
                                                        @endif
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                            @endif
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @elseif (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.notifier.broadcast') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label fw-bold">Select Recipients</label>
                
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="recipient_type" id="recipientAll" value="all" checked>
                            <label class="form-check-label fw-semibold" for="recipientAll">
                                <i class="fas fa-users me-2 text-primary"></i>All Users
                                <span class="badge bg-secondary ms-2">{{ \App\Models\User::count() }} users</span>
                            </label>
                        </div>
                        
                        <hr class="my-3">
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="recipient_type" id="recipientSelected" value="selected">
                            <label class="form-check-label fw-semibold" for="recipientSelected">
                                <i class="fas fa-user-check me-2 text-info"></i>Selected Users
                            </label>
                        </div>
                        
                        <div id="userSelectContainer" class="mt-3" style="display: none;">
                            <label class="form-label small text-muted">Choose specific users:</label>
                            <div class="input-group mb-2">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="userSearch" placeholder="Search users by name or email...">
                            </div>
                            <select name="user_ids[]" class="form-select" id="userSelect" multiple size="8" style="min-height: 200px;">
                                @foreach (\App\Models\User::with('preference')->orderBy('name')->get() as $user)
                                    <option value="{{ $user->id }}" data-name="{{ strtolower($user->name) }}" data-email="{{ strtolower($user->email) }}">
                                        {{ $user->name }} ({{ $user->email }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Hold <kbd>Ctrl</kbd> (Windows) or <kbd>Cmd</kbd> (Mac) to select multiple users.
                                <span id="selectedCount" class="badge bg-primary ms-2">0 selected</span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Channels</label><br>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="channels[]" value="database" checked>
                    <label class="form-check-label">📋 Database</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="channels[]" value="email" checked>
                    <label class="form-check-label">📧 Email</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="channels[]" value="push">
                    <label class="form-check-label">🔔 Push (FCM)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="channels[]" value="websocket">
                    <label class="form-check-label">🔌 WebSocket</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="channels[]" value="webpush">
                    <label class="form-check-label">🌐 Web Push</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="channels[]" value="sms">
                    <label class="form-check-label">📲 SMS</label>
                </div>
                <small class="text-muted d-block mt-2">
                    <strong>Note:</strong> Channels respect user preferences. WebSocket only works for online users. Web Push requires user subscription.
                </small>
            </div>

            <div class="mb-3">
                <label class="form-label">Subject / Title</label>
                <input type="text" name="subject" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Message</label>
                <textarea name="message" class="form-control" rows="4" required></textarea>
            </div>

            <button type="submit" class="btn btn-primary" id="submitBtn">
                <i class="fas fa-bullhorn me-1"></i> <span id="btnText">Broadcast</span>
                <span id="btnSpinner" class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
            </button>
        </form>
    </div>

    <script>
        // Handle recipient type selection
        const recipientAll = document.getElementById('recipientAll');
        const recipientSelected = document.getElementById('recipientSelected');
        const userSelectContainer = document.getElementById('userSelectContainer');
        const userSelect = document.getElementById('userSelect');
        const userSearch = document.getElementById('userSearch');
        const selectedCount = document.getElementById('selectedCount');

        function updateUserSelectVisibility() {
            if (recipientSelected.checked) {
                userSelectContainer.style.display = 'block';
                userSelect.required = true;
            } else {
                userSelectContainer.style.display = 'none';
                userSelect.required = false;
                userSelect.value = [];
                updateSelectedCount();
            }
        }

        recipientAll.addEventListener('change', updateUserSelectVisibility);
        recipientSelected.addEventListener('change', updateUserSelectVisibility);

        // User search functionality
        userSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const options = userSelect.querySelectorAll('option');
            
            options.forEach(option => {
                const name = option.getAttribute('data-name') || '';
                const email = option.getAttribute('data-email') || '';
                
                if (name.includes(searchTerm) || email.includes(searchTerm)) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            });
        });

        // Update selected count
        function updateSelectedCount() {
            const count = userSelect.selectedOptions.length;
            selectedCount.textContent = count + ' selected';
            if (count > 0) {
                selectedCount.classList.remove('bg-secondary');
                selectedCount.classList.add('bg-success');
            } else {
                selectedCount.classList.remove('bg-success');
                selectedCount.classList.add('bg-primary');
            }
        }

        userSelect.addEventListener('change', updateSelectedCount);
        updateSelectedCount();

        // Form submission - handle "all" vs "selected"
        document.querySelector('form').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnSpinner = document.getElementById('btnSpinner');
            
            // If "All Users" is selected, add "all" to user_ids
            if (recipientAll.checked) {
                // Create a hidden input for "all"
                const allInput = document.createElement('input');
                allInput.type = 'hidden';
                allInput.name = 'user_ids[]';
                allInput.value = 'all';
                this.appendChild(allInput);
                
                // Clear the select to avoid conflicts
                userSelect.value = [];
            }
            
            btn.disabled = true;
            btnText.textContent = 'Sending...';
            btnSpinner.classList.remove('d-none');
        });

        // Auto-dismiss success alerts after 10 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 10000);
    </script>
@endsection

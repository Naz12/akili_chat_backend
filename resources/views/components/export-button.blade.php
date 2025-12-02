@props([
    'route' => '',
    'title' => 'Export Data',
    'currentCount' => 0,
    'totalCount' => 0,
    'hasFilters' => false,
])

<div class="export-button-wrapper">
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#exportModal{{ md5($route) }}">
        <i class="fas fa-download me-2"></i>Export
    </button>

    <!-- Export Modal -->
    <div class="modal fade" id="exportModal{{ md5($route) }}" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportModalLabel">
                        <i class="fas fa-download me-2"></i>{{ $title }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="exportForm{{ md5($route) }}" method="GET" action="{{ $route }}">
                        <!-- Export Format -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-file me-2"></i>Export Format
                            </label>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="form-check form-check-card">
                                        <input class="form-check-input" type="radio" name="format" id="formatExcel{{ md5($route) }}" value="excel" checked>
                                        <label class="form-check-label w-100" for="formatExcel{{ md5($route) }}">
                                            <div class="card border-2">
                                                <div class="card-body text-center">
                                                    <i class="fas fa-file-excel text-success" style="font-size: 2rem;"></i>
                                                    <p class="mb-0 mt-2 fw-bold">Excel</p>
                                                    <small class="text-muted">.xlsx</small>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-check form-check-card">
                                        <input class="form-check-input" type="radio" name="format" id="formatPdf{{ md5($route) }}" value="pdf">
                                        <label class="form-check-label w-100" for="formatPdf{{ md5($route) }}">
                                            <div class="card border-2">
                                                <div class="card-body text-center">
                                                    <i class="fas fa-file-pdf text-danger" style="font-size: 2rem;"></i>
                                                    <p class="mb-0 mt-2 fw-bold">PDF</p>
                                                    <small class="text-muted">.pdf</small>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Export Range -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-filter me-2"></i>Export Range
                            </label>
                            <div class="list-group">
                                <label class="list-group-item list-group-item-action cursor-pointer">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div>
                                            <input class="form-check-input me-2" type="radio" name="range" id="rangeCurrent{{ md5($route) }}" value="current" checked>
                                            <strong>Current Page</strong>
                                            <p class="mb-0 text-muted small">Export only the data visible on current page</p>
                                        </div>
                                        <span class="badge bg-primary rounded-pill">{{ $currentCount }} records</span>
                                    </div>
                                </label>
                                
                                @if($hasFilters)
                                <label class="list-group-item list-group-item-action cursor-pointer">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div>
                                            <input class="form-check-input me-2" type="radio" name="range" id="rangeFiltered{{ md5($route) }}" value="filtered">
                                            <strong>Filtered Data</strong>
                                            <p class="mb-0 text-muted small">Export all data matching current filters</p>
                                        </div>
                                        <span class="badge bg-info rounded-pill">{{ $totalCount }} records</span>
                                    </div>
                                </label>
                                @endif

                                <label class="list-group-item list-group-item-action cursor-pointer">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div class="flex-grow-1">
                                            <input class="form-check-input me-2" type="radio" name="range" id="rangeCustom{{ md5($route) }}" value="custom">
                                            <strong>Custom Count</strong>
                                            <p class="mb-0 text-muted small">Export a specific number of records</p>
                                        </div>
                                    </div>
                                    <div class="mt-2 ms-4" id="customCountInput{{ md5($route) }}" style="display: none;">
                                        <input type="number" name="limit" id="limitInput{{ md5($route) }}" class="form-control form-control-sm" 
                                               placeholder="Enter number of records" min="1" max="10000" value="100">
                                        <small class="text-muted">Maximum 10,000 records</small>
                                    </div>
                                </label>
                                
                                <label class="list-group-item list-group-item-action cursor-pointer">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div>
                                            <input class="form-check-input me-2" type="radio" name="range" id="rangeAll{{ md5($route) }}" value="all">
                                            <strong>All Data</strong>
                                            <p class="mb-0 text-muted small">Export all records in the database</p>
                                        </div>
                                        <span class="badge bg-secondary rounded-pill">All</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Date Range Filter -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-calendar-alt me-2"></i>Date Range (Optional)
                            </label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label for="dateFrom{{ md5($route) }}" class="form-label small">From Date</label>
                                    <input type="date" name="date_from" id="dateFrom{{ md5($route) }}" class="form-control form-control-sm">
                                </div>
                                <div class="col-6">
                                    <label for="dateTo{{ md5($route) }}" class="form-label small">To Date</label>
                                    <input type="date" name="date_to" id="dateTo{{ md5($route) }}" class="form-control form-control-sm">
                                </div>
                            </div>
                            <small class="text-muted">Leave empty to export all dates. Date filter applies to all range options.</small>
                        </div>

                        <!-- Preserve current filters (GET parameters) -->
                        @php
                            $currentParams = request()->except(['page', 'format', 'range']);
                        @endphp
                        @foreach($currentParams as $key => $value)
                            @if(is_array($value))
                                @foreach($value as $subKey => $subValue)
                                    <input type="hidden" name="{{ $key }}[{{ $subKey }}]" value="{{ $subValue }}">
                                @endforeach
                            @else
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="exportForm{{ md5($route) }}" class="btn btn-success">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .form-check-card input[type="radio"] {
        position: absolute;
        opacity: 0;
    }
    
    .form-check-card input[type="radio"]:checked + label .card {
        border-color: #667eea !important;
        background-color: #f0f4ff;
    }
    
    .form-check-card label {
        cursor: pointer;
    }
    
    .list-group-item {
        cursor: pointer;
    }
    
    .list-group-item:hover {
        background-color: #f8f9fa;
    }
    
    .list-group-item input[type="radio"]:checked ~ * {
        color: #667eea;
    }
    
    .list-group-item input[type="radio"]:checked {
        border-color: #667eea;
        background-color: #667eea;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const routeHash = '{{ md5($route) }}';
        const modalId = 'exportModal' + routeHash;
        const modalElement = document.getElementById(modalId);
        const customRadio = document.getElementById('rangeCustom' + routeHash);
        const customInput = document.getElementById('customCountInput' + routeHash);
        const limitInput = document.getElementById('limitInput' + routeHash);
        const allRadios = document.querySelectorAll('input[name="range"]');

        // Fix accessibility: Ensure aria-hidden is properly managed by Bootstrap
        if (modalElement) {
            // Listen to Bootstrap modal events to ensure proper aria-hidden management
            modalElement.addEventListener('show.bs.modal', function() {
                // Bootstrap should handle this, but ensure it's set correctly
                this.setAttribute('aria-hidden', 'false');
            });

            modalElement.addEventListener('shown.bs.modal', function() {
                // Ensure aria-hidden is false when modal is fully shown
                this.setAttribute('aria-hidden', 'false');
                // Remove any focus from elements that shouldn't be focused yet
                const focusedElement = document.activeElement;
                if (focusedElement && this.contains(focusedElement)) {
                    // Focus is already inside modal, which is fine
                }
            });

            modalElement.addEventListener('hide.bs.modal', function() {
                // Set aria-hidden back to true when hiding
                this.setAttribute('aria-hidden', 'true');
            });

            modalElement.addEventListener('hidden.bs.modal', function() {
                // Ensure aria-hidden is true when modal is fully hidden
                this.setAttribute('aria-hidden', 'true');
            });
        }

        // Show/hide custom count input based on radio selection
        function toggleCustomInput() {
            if (customRadio && customInput) {
                if (customRadio.checked) {
                    customInput.style.display = 'block';
                    // Only focus if modal is visible and aria-hidden is false
                    if (limitInput && modalElement) {
                        const isModalVisible = modalElement.classList.contains('show') && 
                                            modalElement.getAttribute('aria-hidden') !== 'true';
                        if (isModalVisible) {
                            // Use setTimeout to ensure focus happens after modal is fully shown
                            setTimeout(() => {
                                if (modalElement.getAttribute('aria-hidden') !== 'true') {
                                    limitInput.focus();
                                }
                            }, 100);
                        }
                    }
                } else {
                    customInput.style.display = 'none';
                }
            }
        }

        // Add event listeners to all range radio buttons
        allRadios.forEach(radio => {
            radio.addEventListener('change', toggleCustomInput);
        });

        // Initial check
        toggleCustomInput();

        // Validate custom count on form submit
        const exportForm = document.getElementById('exportForm' + routeHash);
        if (exportForm) {
            exportForm.addEventListener('submit', function(e) {
                if (customRadio && customRadio.checked) {
                    const limit = limitInput ? parseInt(limitInput.value) : 0;
                    if (!limit || limit < 1) {
                        e.preventDefault();
                        alert('Please enter a valid number of records (1-10,000)');
                        if (limitInput) {
                            limitInput.focus();
                        }
                        return false;
                    }
                    if (limit > 10000) {
                        e.preventDefault();
                        alert('Maximum 10,000 records allowed');
                        if (limitInput) {
                            limitInput.focus();
                        }
                        return false;
                    }
                }
            });
        }
    });
</script>


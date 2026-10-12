@push("style-include")
<link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
<style>
.stat-card { background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%); border: 1px solid #e0e0e0; border-radius: 12px; padding: 1.25rem; text-align: center; }
.stat-card .stat-value { font-size: 1.75rem; font-weight: 700; color: var(--primary); }
.stat-card .stat-label { font-size: 0.85rem; color: #666; }
.lead-preview { cursor: pointer; }
.lead-preview:hover { background-color: rgba(var(--primary-rgb), 0.05); }
.quality-excellent { background: #d4edda; color: #155724; }
.quality-good { background: #d1ecf1; color: #0c5460; }
.quality-fair { background: #fff3cd; color: #856404; }
.quality-poor { background: #f8d7da; color: #721c24; }
.lead-detail-row { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #eee; }
.lead-detail-row:last-child { border-bottom: none; }
.social-icon { width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; margin-right: 0.25rem; }
.filter-badge { display: inline-flex; align-items: center; background: #e9ecef; border-radius: 20px; padding: 0.25rem 0.75rem; margin-right: 0.5rem; margin-bottom: 0.5rem; font-size: 0.85rem; }
.filter-badge .remove-filter { margin-left: 0.5rem; cursor: pointer; }

/* Ensure action buttons are clickable */
.table-container table td .icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border: none;
    background: transparent;
}
.table-container table td .icon-btn.info-soft {
    background-color: var(--color-info-light, rgba(14, 165, 233, 0.1));
    color: var(--color-info, #0ea5e9);
}
.table-container table td .icon-btn.danger-soft {
    background-color: var(--color-danger-light, rgba(239, 68, 68, 0.1));
    color: var(--color-danger, #ef4444);
}
.table-container table td .icon-btn:hover {
    opacity: 0.8;
}

/* Page Header Fix */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.page-header-right {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}
</style>
@endpush

@extends('admin.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ $title }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            @foreach($breadcrumbs as $breadcrumb)
                                @if(isset($breadcrumb['url']))
                                    <li class="breadcrumb-item">
                                        <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['name'] }}</a>
                                    </li>
                                @else
                                    <li class="breadcrumb-item active" aria-current="page">{{ $breadcrumb['name'] }}</li>
                                @endif
                            @endforeach
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                <a href="{{ route('admin.lead-generation.index') }}" class="i-btn btn--dark outline btn--sm">
                    <i class="ri-arrow-left-line"></i> {{ translate('Back') }}
                </a>
            </div>
        </div>

        <!-- Job Info Card -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <h5 class="mb-2">
                            <i class="ri-{{ $job->type->value == 'google_maps' ? 'map-pin' : 'global' }}-line text-primary me-2"></i>
                            {{ $job->type->label() }}
                        </h5>
                        @php $params = $job->parameters; @endphp
                        @if($job->type->value == 'google_maps')
                            <p class="text-muted mb-0">
                                <strong>{{ $params['query'] ?? '-' }}</strong> in <strong>{{ $params['location'] ?? '-' }}</strong>
                            </p>
                        @endif
                        <small class="text-muted">
                            {{ translate('Started') }}: {{ $job->created_at->format('M d, Y H:i') }}
                            @if($job->completed_at)
                                | {{ translate('Completed') }}: {{ $job->completed_at->format('M d, Y H:i') }}
                            @endif
                        </small>
                    </div>
                    <div class="col-lg-6">
                        <div class="row g-3">
                            <div class="col-4">
                                <div class="stat-card">
                                    <div class="stat-value">{{ number_format($jobStats['total']) }}</div>
                                    <div class="stat-label">{{ translate('Total Leads') }}</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-card">
                                    <div class="stat-value text-success">{{ number_format($jobStats['with_email']) }}</div>
                                    <div class="stat-label">{{ translate('With Email') }}</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-card">
                                    <div class="stat-value text-info">{{ number_format($jobStats['with_phone']) }}</div>
                                    <div class="stat-label">{{ translate('With Phone') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters & Search -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="" method="GET" id="filterForm">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-3">
                            <label class="form-label">{{ translate('Search') }}</label>
                            <input type="text" class="form-control" name="search" value="{{ request('search') }}"
                                   placeholder="{{ translate('Business name, email, phone...') }}">
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label">{{ translate('Has Email') }}</label>
                            <select class="form-select" name="has_email">
                                <option value="">{{ translate('Any') }}</option>
                                <option value="1" {{ request('has_email') ? 'selected' : '' }}>{{ translate('Yes') }}</option>
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label">{{ translate('Has Phone') }}</label>
                            <select class="form-select" name="has_phone">
                                <option value="">{{ translate('Any') }}</option>
                                <option value="1" {{ request('has_phone') ? 'selected' : '' }}>{{ translate('Yes') }}</option>
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label">{{ translate('Quality') }}</label>
                            <select class="form-select" name="min_quality">
                                <option value="">{{ translate('Any') }}</option>
                                <option value="80" {{ request('min_quality') == '80' ? 'selected' : '' }}>80%+ {{ translate('Excellent') }}</option>
                                <option value="60" {{ request('min_quality') == '60' ? 'selected' : '' }}>60%+ {{ translate('Good') }}</option>
                                <option value="40" {{ request('min_quality') == '40' ? 'selected' : '' }}>40%+ {{ translate('Fair') }}</option>
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label">{{ translate('Status') }}</label>
                            <select class="form-select" name="not_imported">
                                <option value="">{{ translate('Any') }}</option>
                                <option value="1" {{ request('not_imported') ? 'selected' : '' }}>{{ translate('Not Imported') }}</option>
                            </select>
                        </div>
                        <div class="col-lg-1">
                            <button type="submit" class="i-btn btn--primary btn--md w-100">
                                <i class="ri-filter-line"></i>
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Active Filters -->
                @if(request()->hasAny(['search', 'has_email', 'has_phone', 'min_quality', 'not_imported']))
                    <div class="mt-3 pt-3 border-top">
                        <span class="text-muted me-2">{{ translate('Active filters:') }}</span>
                        @if(request('search'))
                            <span class="filter-badge">
                                {{ translate('Search') }}: {{ request('search') }}
                                <span class="remove-filter" data-filter="search">&times;</span>
                            </span>
                        @endif
                        @if(request('has_email'))
                            <span class="filter-badge">
                                {{ translate('Has Email') }}
                                <span class="remove-filter" data-filter="has_email">&times;</span>
                            </span>
                        @endif
                        @if(request('has_phone'))
                            <span class="filter-badge">
                                {{ translate('Has Phone') }}
                                <span class="remove-filter" data-filter="has_phone">&times;</span>
                            </span>
                        @endif
                        @if(request('min_quality'))
                            <span class="filter-badge">
                                {{ translate('Quality') }} {{ request('min_quality') }}%+
                                <span class="remove-filter" data-filter="min_quality">&times;</span>
                            </span>
                        @endif
                        @if(request('not_imported'))
                            <span class="filter-badge">
                                {{ translate('Not Imported') }}
                                <span class="remove-filter" data-filter="not_imported">&times;</span>
                            </span>
                        @endif
                        <a href="{{ route('admin.lead-generation.results', $job->uid) }}" class="text-danger small ms-2">
                            {{ translate('Clear all') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <!-- Bulk Actions Bar -->
        <div class="card mb-4 d-none" id="bulkActionBar">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <strong id="selectedCount">0</strong> {{ translate('leads selected') }}
                        <button type="button" class="btn btn-link btn-sm" id="selectAllVisible">{{ translate('Select all visible') }}</button>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="i-btn btn--primary btn--sm" data-bs-toggle="modal" data-bs-target="#importModal">
                            <i class="ri-download-line"></i> {{ translate('Import to Contacts') }}
                        </button>
                        <button class="i-btn btn--info btn--sm" id="exportSelectedBtn">
                            <i class="ri-file-excel-line"></i> {{ translate('Export to Excel') }}
                        </button>
                        <button class="i-btn btn--danger btn--sm" id="bulkDeleteBtn">
                            <i class="ri-delete-bin-line"></i> {{ translate('Delete') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leads Table -->
        <div class="card">
            <div class="card-header">
                <div class="card-header-left">
                    <h4 class="card-title">{{ translate('Leads') }} ({{ $leads->total() }})</h4>
                </div>
                <div class="card-header-right d-flex gap-2">
                    <a href="{{ route('admin.lead-generation.leads.export', ['job_id' => $job->id] + request()->query()) }}"
                       class="i-btn btn--success outline btn--sm">
                        <i class="ri-file-excel-line"></i> {{ translate('Export All') }}
                    </a>
                </div>
            </div>
            <div class="card-body px-0 pt-0">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th width="40"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                <th>{{ translate('Business') }}</th>
                                <th>{{ translate('Email') }}</th>
                                <th>{{ translate('Phone') }}</th>
                                <th>{{ translate('Location') }}</th>
                                <th>{{ translate('Quality') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th width="100">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($leads as $lead)
                                <tr class="lead-preview" data-lead-id="{{ $lead->id }}">
                                    <td onclick="event.stopPropagation();">
                                        <input type="checkbox" class="form-check-input lead-checkbox" value="{{ $lead->id }}">
                                    </td>
                                    <td>
                                        <strong>{{ $lead->display_name }}</strong>
                                        @if($lead->category)
                                            <br><small class="text-muted">{{ $lead->category }}</small>
                                        @endif
                                        @if($lead->rating)
                                            <br><small class="text-warning">
                                                <i class="ri-star-fill"></i> {{ number_format($lead->rating, 1) }}
                                                @if($lead->reviews_count)
                                                    ({{ $lead->reviews_count }})
                                                @endif
                                            </small>
                                        @endif
                                    </td>
                                    <td>
                                        @if($lead->email)
                                            <a href="mailto:{{ $lead->email }}" onclick="event.stopPropagation();">{{ $lead->email }}</a>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($lead->phone)
                                            <a href="tel:{{ $lead->phone }}" onclick="event.stopPropagation();">{{ $lead->phone }}</a>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $lead->city ?: '' }}{{ $lead->city && $lead->country ? ', ' : '' }}{{ $lead->country ?: '' }}
                                    </td>
                                    <td>
                                        @php
                                            $qualityClass = match(true) {
                                                $lead->quality_score >= 80 => 'quality-excellent',
                                                $lead->quality_score >= 60 => 'quality-good',
                                                $lead->quality_score >= 40 => 'quality-fair',
                                                default => 'quality-poor',
                                            };
                                        @endphp
                                        <span class="i-badge {{ $qualityClass }} pill">{{ $lead->quality_score }}%</span>
                                    </td>
                                    <td>
                                        @if($lead->isImported())
                                            <span class="i-badge success-soft pill">{{ translate('Imported') }}</span>
                                        @else
                                            <span class="i-badge warning-soft pill">{{ translate('Pending') }}</span>
                                        @endif
                                    </td>
                                    <td onclick="event.stopPropagation();">
                                        <div class="d-flex gap-1">
                                            <button type="button" class="icon-btn btn-ghost btn-sm info-soft circle view-lead"
                                                    title="{{ translate('View Details') }}"
                                                    data-lead-id="{{ $lead->id }}"
                                                    data-business="{{ $lead->business_name ?? '' }}"
                                                    data-email="{{ $lead->email ?? '' }}"
                                                    data-phone="{{ $lead->phone ?? '' }}"
                                                    data-website="{{ $lead->website ?? '' }}"
                                                    data-category="{{ $lead->category ?? '' }}"
                                                    data-address="{{ $lead->address ?? '' }}"
                                                    data-city="{{ $lead->city ?? '' }}"
                                                    data-country="{{ $lead->country ?? '' }}"
                                                    data-rating="{{ $lead->rating ?? '' }}"
                                                    data-quality="{{ $lead->quality_score ?? 0 }}"
                                                    data-facebook="{{ $lead->facebook ?? '' }}"
                                                    data-instagram="{{ $lead->instagram ?? '' }}"
                                                    data-twitter="{{ $lead->twitter ?? '' }}"
                                                    data-linkedin="{{ $lead->linkedin ?? '' }}">
                                                <i class="ri-eye-line"></i>
                                            </button>
                                            <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle delete-lead" title="{{ translate('Delete') }}" data-id="{{ $lead->id }}" data-name="{{ $lead->display_name }}">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                                        {{ translate('No leads found') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @include('admin.partials.pagination', ['paginator' => $leads])
            </div>
        </div>
    </div>
</main>

@endsection

@section('modal')
<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ translate('Import Leads to Contacts') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">{{ translate('Import Type') }}</label>
                    <select class="form-select" id="importType">
                        <option value="all">{{ translate('All contact types (Email + SMS + WhatsApp)') }}</option>
                        <option value="email">{{ translate('Email contacts only') }}</option>
                        <option value="sms">{{ translate('SMS contacts only') }}</option>
                        <option value="whatsapp">{{ translate('WhatsApp contacts only') }}</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ translate('Destination') }}</label>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="importDest" id="destExisting" value="existing" checked>
                        <label class="form-check-label" for="destExisting">{{ translate('Existing Group') }}</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="importDest" id="destNew" value="new">
                        <label class="form-check-label" for="destNew">{{ translate('Create New Group') }}</label>
                    </div>
                </div>

                <div id="existingGroupDiv" class="mb-3">
                    <label class="form-label">{{ translate('Select Group') }}</label>
                    <select class="form-select select2-search" id="importGroupId">
                        <option value="">{{ translate('Select') }}</option>
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div id="newGroupDiv" class="mb-3 d-none">
                    <label class="form-label">{{ translate('New Group Name') }}</label>
                    <input type="text" class="form-control" id="newGroupName" placeholder="{{ translate('Enter group name') }}">
                </div>

                <div class="alert alert-info small mb-0">
                    <i class="ri-information-line me-1"></i>
                    {{ translate('Duplicate contacts will be automatically skipped.') }}
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                <button type="button" class="i-btn btn--primary btn--md" id="confirmImport">
                    <i class="ri-download-line"></i> {{ translate('Import') }}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Lead Detail Modal -->
<div class="modal fade" id="leadDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="leadDetailName">{{ translate('Lead Details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-lg-6">
                        <h6 class="mb-3">{{ translate('Contact Information') }}</h6>
                        <div class="lead-detail-row">
                            <span class="text-muted">{{ translate('Business Name') }}</span>
                            <strong id="detailBusinessName">-</strong>
                        </div>
                        <div class="lead-detail-row">
                            <span class="text-muted">{{ translate('Email') }}</span>
                            <span id="detailEmail">-</span>
                        </div>
                        <div class="lead-detail-row">
                            <span class="text-muted">{{ translate('Phone') }}</span>
                            <span id="detailPhone">-</span>
                        </div>
                        <div class="lead-detail-row">
                            <span class="text-muted">{{ translate('Website') }}</span>
                            <span id="detailWebsite">-</span>
                        </div>
                        <div class="lead-detail-row">
                            <span class="text-muted">{{ translate('Category') }}</span>
                            <span id="detailCategory">-</span>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <h6 class="mb-3">{{ translate('Location') }}</h6>
                        <div class="lead-detail-row">
                            <span class="text-muted">{{ translate('Address') }}</span>
                            <span id="detailAddress">-</span>
                        </div>
                        <div class="lead-detail-row">
                            <span class="text-muted">{{ translate('City') }}</span>
                            <span id="detailCity">-</span>
                        </div>
                        <div class="lead-detail-row">
                            <span class="text-muted">{{ translate('Country') }}</span>
                            <span id="detailCountry">-</span>
                        </div>
                        <div class="lead-detail-row">
                            <span class="text-muted">{{ translate('Rating') }}</span>
                            <span id="detailRating">-</span>
                        </div>
                        <div class="lead-detail-row">
                            <span class="text-muted">{{ translate('Quality Score') }}</span>
                            <span id="detailQuality">-</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4" id="socialLinks">
                    <h6 class="mb-3">{{ translate('Social Profiles') }}</h6>
                    <div id="socialLinksContent"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate('Close') }}</button>
                <button type="button" class="i-btn btn--primary btn--md" id="importSingleLead">
                    <i class="ri-download-line"></i> {{ translate('Import This Lead') }}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center pt-0">
                <div class="confirm-icon mb-3">
                    <i class="ri-error-warning-line text-warning" style="font-size: 3rem;"></i>
                </div>
                <h5 class="mb-2" id="confirmTitle">{{ translate('Are you sure?') }}</h5>
                <p class="text-muted mb-0" id="confirmMessage">{{ translate('This action cannot be undone.') }}</p>
            </div>
            <div class="modal-footer border-0 justify-content-center pt-0">
                <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                <button type="button" class="i-btn btn--danger btn--md" id="confirmAction">{{ translate('Delete') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push("script-include")
<script src="{{asset('assets/theme/global/js/select2.min.js')}}"></script>
@endpush

@push('script-push')
<script>
(function($) {
    "use strict";

    select2_search($('.select2-search').data('placeholder'));

    var selectedLeads = [];
    var currentLeadId = null;
    var pendingAction = null;
    var pendingData = null;

    // Select all
    $('#selectAll').on('change', function() {
        $('.lead-checkbox').prop('checked', $(this).prop('checked'));
        updateSelection();
    });

    $('#selectAllVisible').on('click', function() {
        $('.lead-checkbox').prop('checked', true);
        updateSelection();
    });

    // Individual checkbox
    $(document).on('change', '.lead-checkbox', updateSelection);

    function updateSelection() {
        selectedLeads = [];
        $('.lead-checkbox:checked').each(function() {
            selectedLeads.push(parseInt($(this).val()));
        });
        $('#bulkActionBar').toggleClass('d-none', selectedLeads.length === 0);
        $('#selectedCount').text(selectedLeads.length);
    }

    // Confirmation Modal Helper
    function showConfirmDialog(title, message, callback) {
        pendingAction = callback;
        $('#confirmTitle').text(title);
        $('#confirmMessage').text(message);
        $('#confirmModal').modal('show');
    }

    // Handle confirm action
    $('#confirmAction').on('click', function() {
        $('#confirmModal').modal('hide');
        if (typeof pendingAction === 'function') {
            pendingAction();
            pendingAction = null;
        }
    });

    // Import destination toggle
    $('input[name="importDest"]').on('change', function() {
        if ($(this).val() === 'new') {
            $('#existingGroupDiv').addClass('d-none');
            $('#newGroupDiv').removeClass('d-none');
        } else {
            $('#existingGroupDiv').removeClass('d-none');
            $('#newGroupDiv').addClass('d-none');
        }
    });

    // Confirm import
    $('#confirmImport').on('click', function() {
        if (!selectedLeads.length) {
            notify('error', '{{ translate("No leads selected") }}');
            return;
        }

        var importDest = $('input[name="importDest"]:checked').val();
        var data = {
            _token: '{{ csrf_token() }}',
            lead_ids: selectedLeads,
            import_type: $('#importType').val()
        };

        if (importDest === 'new') {
            var newGroupName = $('#newGroupName').val().trim();
            if (!newGroupName) {
                notify('error', '{{ translate("Please enter group name") }}');
                return;
            }
            data.new_group = newGroupName;
        } else {
            var groupId = $('#importGroupId').val();
            if (!groupId) {
                notify('error', '{{ translate("Please select a group") }}');
                return;
            }
            data.group_id = groupId;
        }

        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>{{ translate("Importing...") }}');

        $.post('{{ route("admin.lead-generation.leads.import") }}', data, function(response) {
            $('#confirmImport').prop('disabled', false).html('<i class="ri-download-line"></i> {{ translate("Import") }}');
            if (response.status) {
                notify('success', response.message);
                $('#importModal').modal('hide');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                notify('error', response.message);
            }
        }).fail(function(xhr) {
            $('#confirmImport').prop('disabled', false).html('<i class="ri-download-line"></i> {{ translate("Import") }}');
            notify('error', xhr.responseJSON?.message || '{{ translate("Import failed") }}');
        });
    });

    // Export selected
    $('#exportSelectedBtn').on('click', function() {
        if (!selectedLeads.length) {
            notify('error', '{{ translate("No leads selected") }}');
            return;
        }
        var url = '{{ route("admin.lead-generation.leads.export") }}?' + $.param({ lead_ids: selectedLeads });
        window.location.href = url;
    });

    // Delete single lead
    $(document).on('click', '.delete-lead', function(e) {
        e.stopPropagation();
        e.preventDefault();

        var id = $(this).data('id');
        var name = $(this).data('name') || '{{ translate("this lead") }}';
        var row = $(this).closest('tr');

        showConfirmDialog(
            '{{ translate("Delete Lead?") }}',
            '{{ translate("Are you sure you want to delete") }} "' + name + '"? {{ translate("This action cannot be undone.") }}',
            function() {
                $.ajax({
                    url: '{{ url("admin/lead-generation/lead") }}/' + id,
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.status) {
                            row.fadeOut(300, function() { $(this).remove(); });
                            notify('success', response.message);
                        } else {
                            notify('error', response.message || '{{ translate("Delete failed") }}');
                        }
                    },
                    error: function(xhr) {
                        console.error('Delete error:', xhr);
                        notify('error', xhr.responseJSON?.message || '{{ translate("Delete failed") }}');
                    }
                });
            }
        );
    });

    // Bulk delete
    $('#bulkDeleteBtn').on('click', function() {
        showConfirmDialog(
            '{{ translate("Delete Selected Leads?") }}',
            '{{ translate("Are you sure you want to delete") }} ' + selectedLeads.length + ' {{ translate("selected leads? This action cannot be undone.") }}',
            function() {
                $.post('{{ route("admin.lead-generation.leads.bulk-delete") }}', {
                    _token: '{{ csrf_token() }}',
                    lead_ids: selectedLeads
                }, function(response) {
                    if (response.status) {
                        notify('success', response.message);
                        location.reload();
                    } else {
                        notify('error', response.message || '{{ translate("Delete failed") }}');
                    }
                }).fail(function(xhr) {
                    notify('error', xhr.responseJSON?.message || '{{ translate("Delete failed") }}');
                });
            }
        );
    });

    // View lead details - using data attributes
    $(document).on('click', '.view-lead', function(e) {
        e.stopPropagation();
        e.preventDefault();

        var $btn = $(this);
        var lead = {
            id: $btn.data('lead-id'),
            business_name: $btn.data('business'),
            email: $btn.data('email'),
            phone: $btn.data('phone'),
            website: $btn.data('website'),
            category: $btn.data('category'),
            address: $btn.data('address'),
            city: $btn.data('city'),
            country: $btn.data('country'),
            rating: $btn.data('rating'),
            quality_score: $btn.data('quality'),
            facebook: $btn.data('facebook'),
            instagram: $btn.data('instagram'),
            twitter: $btn.data('twitter'),
            linkedin: $btn.data('linkedin')
        };

        showLeadDetail(lead);
    });

    // Click on row to view lead
    $(document).on('click', '.lead-preview', function(e) {
        if ($(e.target).closest('.lead-checkbox, a, button').length) return;
        $(this).find('.view-lead').trigger('click');
    });

    function showLeadDetail(lead) {
        currentLeadId = lead.id;
        $('#leadDetailName').text(lead.business_name || '{{ translate("Lead Details") }}');
        $('#detailBusinessName').text(lead.business_name || '-');
        $('#detailEmail').html(lead.email ? '<a href="mailto:' + lead.email + '">' + lead.email + '</a>' : '-');
        $('#detailPhone').html(lead.phone ? '<a href="tel:' + lead.phone + '">' + lead.phone + '</a>' : '-');
        $('#detailWebsite').html(lead.website ? '<a href="' + lead.website + '" target="_blank">' + lead.website + '</a>' : '-');
        $('#detailCategory').text(lead.category || '-');
        $('#detailAddress').text(lead.address || '-');
        $('#detailCity').text(lead.city || '-');
        $('#detailCountry').text(lead.country || '-');
        $('#detailRating').html(lead.rating ? '<span class="text-warning"><i class="ri-star-fill"></i> ' + lead.rating + '</span>' : '-');

        var qualityClass = lead.quality_score >= 80 ? 'success' : (lead.quality_score >= 60 ? 'info' : 'warning');
        $('#detailQuality').html('<span class="i-badge ' + qualityClass + '-soft pill">' + lead.quality_score + '%</span>');

        // Social links
        var socialHtml = '';
        if (lead.facebook) socialHtml += '<a href="' + lead.facebook + '" target="_blank" class="social-icon bg-primary text-white"><i class="ri-facebook-fill"></i></a>';
        if (lead.instagram) socialHtml += '<a href="' + lead.instagram + '" target="_blank" class="social-icon" style="background:#E4405F;color:#fff"><i class="ri-instagram-fill"></i></a>';
        if (lead.twitter) socialHtml += '<a href="' + lead.twitter + '" target="_blank" class="social-icon bg-info text-white"><i class="ri-twitter-fill"></i></a>';
        if (lead.linkedin) socialHtml += '<a href="' + lead.linkedin + '" target="_blank" class="social-icon" style="background:#0A66C2;color:#fff"><i class="ri-linkedin-fill"></i></a>';

        if (socialHtml) {
            $('#socialLinks').show();
            $('#socialLinksContent').html(socialHtml);
        } else {
            $('#socialLinks').hide();
        }

        $('#leadDetailModal').modal('show');
    }

    // Import single lead
    $('#importSingleLead').on('click', function() {
        if (!currentLeadId) return;
        selectedLeads = [currentLeadId];
        $('#leadDetailModal').modal('hide');
        setTimeout(function() { $('#importModal').modal('show'); }, 300);
    });

    // Remove filter
    $(document).on('click', '.remove-filter', function() {
        var filter = $(this).data('filter');
        $('input[name="' + filter + '"], select[name="' + filter + '"]').val('');
        $('#filterForm').submit();
    });

})(jQuery);
</script>
@endpush

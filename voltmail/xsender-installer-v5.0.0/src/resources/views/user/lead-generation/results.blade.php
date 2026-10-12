@push("style-include")
<link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
<style>
    .stats-card {
        padding: 1.25rem;
        border-radius: 8px;
        background: linear-gradient(135deg, var(--bg-start, #f8f9fa) 0%, var(--bg-end, #e9ecef) 100%);
    }
    .stats-card.primary { --bg-start: #e3f2fd; --bg-end: #bbdefb; }
    .stats-card.success { --bg-start: #e8f5e9; --bg-end: #c8e6c9; }
    .stats-card.warning { --bg-start: #fff3e0; --bg-end: #ffe0b2; }
    .stats-card.info { --bg-start: #e1f5fe; --bg-end: #b3e5fc; }
    .stats-card h3 { font-size: 1.75rem; margin-bottom: 0.25rem; }
    .stats-card p { margin-bottom: 0; color: #666; font-size: 0.875rem; }

    .filter-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem 0.75rem;
        background: #e3f2fd;
        border-radius: 20px;
        font-size: 0.813rem;
    }
    .filter-badge .remove-filter {
        cursor: pointer;
        opacity: 0.7;
    }
    .filter-badge .remove-filter:hover { opacity: 1; }

    .lead-detail-item {
        padding: 0.75rem 0;
        border-bottom: 1px solid #eee;
    }
    .lead-detail-item:last-child { border-bottom: none; }
    .lead-detail-item label {
        font-weight: 600;
        color: #666;
        font-size: 0.75rem;
        text-transform: uppercase;
        margin-bottom: 0.25rem;
    }

    .quality-bar {
        height: 6px;
        background: #e9ecef;
        border-radius: 3px;
        overflow: hidden;
    }
    .quality-bar .fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.3s;
    }
</style>
@endpush

@extends('user.layouts.app')
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
                <div class="d-flex gap-2">
                    <a href="{{ route('user.lead-generation.leads.export', ['job_id' => $job->id]) }}" class="i-btn btn--primary btn--sm">
                        <i class="bi bi-download"></i> {{ translate('Export All') }}
                    </a>
                    <a href="{{ route('user.lead-generation.scraper') }}" class="i-btn btn--dark outline btn--sm">
                        <i class="bi bi-plus-lg"></i> {{ translate('New Search') }}
                    </a>
                </div>
            </div>
        </div>

        <!-- Job Stats -->
        @if(isset($jobStats))
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card primary">
                    <h3>{{ number_format($jobStats['total']) }}</h3>
                    <p>{{ translate('Total Leads') }}</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card success">
                    <h3>{{ number_format($jobStats['with_email']) }}</h3>
                    <p>{{ translate('With Email') }}</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card warning">
                    <h3>{{ number_format($jobStats['with_phone']) }}</h3>
                    <p>{{ translate('With Phone') }}</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card info">
                    <h3>{{ number_format($jobStats['imported']) }}</h3>
                    <p>{{ translate('Imported') }}</p>
                </div>
            </div>
        </div>
        @endif

        <!-- Job Info Card -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <h5 class="mb-1">{{ $job->type->label() }}</h5>
                        @if($job->type->value == 'google_maps')
                            <p class="text-muted mb-0">
                                <strong>{{ $job->search_query }}</strong> in <strong>{{ $job->location }}</strong>
                            </p>
                        @endif
                        <small class="text-muted">{{ translate('Created') }}: {{ $job->created_at->diffForHumans() }}</small>
                    </div>
                    <div class="col-lg-4 text-end">
                        <span class="i-badge {{ $job->status->value == 'completed' ? 'success' : 'warning' }}-soft pill fs-6">
                            {{ $job->status->label() }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" id="filterForm">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Search') }}</label>
                            <input type="text" name="search" class="form-control"
                                   placeholder="{{ translate('Business name, email, phone...') }}"
                                   value="{{ request('search') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ translate('Has Email') }}</label>
                            <select name="has_email" class="form-select">
                                <option value="">{{ translate('Any') }}</option>
                                <option value="1" {{ request('has_email') == '1' ? 'selected' : '' }}>{{ translate('Yes') }}</option>
                                <option value="0" {{ request('has_email') == '0' ? 'selected' : '' }}>{{ translate('No') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ translate('Has Phone') }}</label>
                            <select name="has_phone" class="form-select">
                                <option value="">{{ translate('Any') }}</option>
                                <option value="1" {{ request('has_phone') == '1' ? 'selected' : '' }}>{{ translate('Yes') }}</option>
                                <option value="0" {{ request('has_phone') == '0' ? 'selected' : '' }}>{{ translate('No') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ translate('Min Quality') }}</label>
                            <select name="min_quality" class="form-select">
                                <option value="">{{ translate('Any') }}</option>
                                <option value="25" {{ request('min_quality') == '25' ? 'selected' : '' }}>25%+</option>
                                <option value="50" {{ request('min_quality') == '50' ? 'selected' : '' }}>50%+</option>
                                <option value="75" {{ request('min_quality') == '75' ? 'selected' : '' }}>75%+</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <div class="d-flex gap-2">
                                <button type="submit" class="i-btn btn--primary btn--md flex-grow-1">
                                    <i class="bi bi-funnel"></i> {{ translate('Filter') }}
                                </button>
                                @if(request()->hasAny(['search', 'has_email', 'has_phone', 'min_quality', 'not_imported']))
                                    <a href="{{ route('user.lead-generation.job.results', $job->uid) }}" class="i-btn btn--dark outline btn--md">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="not_imported" value="1"
                                   id="notImported" {{ request('not_imported') ? 'checked' : '' }} onchange="this.form.submit()">
                            <label class="form-check-label" for="notImported">{{ translate('Show only not imported') }}</label>
                        </div>
                    </div>
                </form>

                @if(request()->hasAny(['search', 'has_email', 'has_phone', 'min_quality', 'not_imported']))
                    <div class="mt-3 d-flex flex-wrap gap-2">
                        <span class="text-muted">{{ translate('Active filters') }}:</span>
                        @if(request('search'))
                            <span class="filter-badge">
                                {{ translate('Search') }}: {{ request('search') }}
                                <a href="{{ request()->fullUrlWithoutQuery('search') }}" class="remove-filter"><i class="bi bi-x"></i></a>
                            </span>
                        @endif
                        @if(request('has_email'))
                            <span class="filter-badge">
                                {{ translate('Has Email') }}
                                <a href="{{ request()->fullUrlWithoutQuery('has_email') }}" class="remove-filter"><i class="bi bi-x"></i></a>
                            </span>
                        @endif
                        @if(request('has_phone'))
                            <span class="filter-badge">
                                {{ translate('Has Phone') }}
                                <a href="{{ request()->fullUrlWithoutQuery('has_phone') }}" class="remove-filter"><i class="bi bi-x"></i></a>
                            </span>
                        @endif
                        @if(request('min_quality'))
                            <span class="filter-badge">
                                {{ translate('Quality') }}: {{ request('min_quality') }}%+
                                <a href="{{ request()->fullUrlWithoutQuery('min_quality') }}" class="remove-filter"><i class="bi bi-x"></i></a>
                            </span>
                        @endif
                        @if(request('not_imported'))
                            <span class="filter-badge">
                                {{ translate('Not Imported') }}
                                <a href="{{ request()->fullUrlWithoutQuery('not_imported') }}" class="remove-filter"><i class="bi bi-x"></i></a>
                            </span>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="card mb-4 d-none" id="bulkActionBar">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span><strong id="selectedCount">0</strong> {{ translate('leads selected') }}</span>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="i-btn btn--primary btn--sm" data-bs-toggle="modal" data-bs-target="#importModal">
                            <i class="bi bi-download"></i> {{ translate('Import Selected') }}
                        </button>
                        <button class="i-btn btn--info btn--sm" id="exportSelectedBtn">
                            <i class="bi bi-file-earmark-excel"></i> {{ translate('Export Selected') }}
                        </button>
                        <button class="i-btn btn--danger btn--sm" id="bulkDeleteBtn">
                            <i class="bi bi-trash"></i> {{ translate('Delete') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leads Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">{{ translate('Leads') }} ({{ $leads->total() }})</h4>
            </div>
            <div class="card-body px-0 pt-0">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                <th>{{ translate('Business') }}</th>
                                <th>{{ translate('Contact Info') }}</th>
                                <th>{{ translate('Location') }}</th>
                                <th>{{ translate('Quality') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($leads as $lead)
                                <tr>
                                    <td><input type="checkbox" class="form-check-input lead-checkbox" value="{{ $lead->id }}"></td>
                                    <td>
                                        <strong>{{ $lead->display_name }}</strong>
                                        @if($lead->category)
                                            <br><small class="text-muted">{{ $lead->category }}</small>
                                        @endif
                                        @if($lead->rating)
                                            <br><small class="text-warning">
                                                @for($i = 1; $i <= 5; $i++)
                                                    <i class="bi bi-star{{ $i <= $lead->rating ? '-fill' : '' }}"></i>
                                                @endfor
                                                ({{ $lead->reviews_count ?? 0 }})
                                            </small>
                                        @endif
                                    </td>
                                    <td>
                                        @if($lead->email)
                                            <div><i class="bi bi-envelope text-primary"></i> {{ $lead->email }}</div>
                                        @endif
                                        @if($lead->phone)
                                            <div><i class="bi bi-telephone text-success"></i> {{ $lead->phone }}</div>
                                        @endif
                                        @if(!$lead->email && !$lead->phone)
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $lead->city }}{{ $lead->city && $lead->country ? ', ' : '' }}{{ $lead->country }}
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="quality-bar" style="width: 60px;">
                                                <div class="fill bg-{{ $lead->quality_score >= 70 ? 'success' : ($lead->quality_score >= 40 ? 'warning' : 'danger') }}"
                                                     style="width: {{ $lead->quality_score }}%"></div>
                                            </div>
                                            <span class="small">{{ $lead->quality_score }}%</span>
                                        </div>
                                    </td>
                                    <td>
                                        @if($lead->isImported())
                                            <span class="i-badge success-soft pill">{{ translate('Imported') }}</span>
                                        @else
                                            <span class="i-badge warning-soft pill">{{ translate('Pending') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button class="icon-btn btn-ghost btn-sm info-soft circle view-lead"
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
                                                    data-linkedin="{{ $lead->linkedin ?? '' }}"
                                                    title="{{ translate('View Details') }}">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            @if($lead->website)
                                                <a href="{{ $lead->website }}" target="_blank" class="icon-btn btn-ghost btn-sm primary-soft circle" title="{{ translate('Visit Website') }}">
                                                    <i class="bi bi-globe"></i>
                                                </a>
                                            @endif
                                            <button class="icon-btn btn-ghost btn-sm danger-soft circle delete-lead" data-id="{{ $lead->id }}" data-name="{{ $lead->display_name }}" title="{{ translate('Delete') }}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        {{ translate('No leads found') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @include('user.partials.pagination', ['paginator' => $leads])
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
                    <label class="form-label">{{ translate('Save To') }}</label>
                    <div class="d-flex gap-2 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="saveOption" id="saveExisting" value="existing" checked>
                            <label class="form-check-label" for="saveExisting">{{ translate('Existing Group') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="saveOption" id="saveNew" value="new">
                            <label class="form-check-label" for="saveNew">{{ translate('New Group') }}</label>
                        </div>
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

                <div class="mb-3">
                    <label class="form-label">{{ translate('Import As') }}</label>
                    <select class="form-select" id="importType">
                        <option value="all">{{ translate('All contact types') }}</option>
                        <option value="email">{{ translate('Email contacts only') }}</option>
                        <option value="sms">{{ translate('SMS contacts only') }}</option>
                        <option value="whatsapp">{{ translate('WhatsApp contacts only') }}</option>
                    </select>
                    <small class="text-muted">{{ translate('Filter which contacts to import based on available data') }}</small>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="skipDuplicates" checked>
                    <label class="form-check-label" for="skipDuplicates">{{ translate('Skip duplicate contacts') }}</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                <button type="button" class="i-btn btn--primary btn--md" id="confirmImport">
                    <i class="bi bi-download"></i> {{ translate('Import') }}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Lead Detail Modal -->
<div class="modal fade" id="leadDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ translate('Lead Details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="lead-detail-item">
                    <label>{{ translate('Business Name') }}</label>
                    <div id="detailName" class="fw-bold"></div>
                </div>
                <div class="lead-detail-item">
                    <label>{{ translate('Category') }}</label>
                    <div id="detailCategory"></div>
                </div>
                <div class="lead-detail-item">
                    <label>{{ translate('Contact Information') }}</label>
                    <div id="detailContact"></div>
                </div>
                <div class="lead-detail-item">
                    <label>{{ translate('Address') }}</label>
                    <div id="detailAddress"></div>
                </div>
                <div class="lead-detail-item">
                    <label>{{ translate('Website') }}</label>
                    <div id="detailWebsite"></div>
                </div>
                <div class="lead-detail-item">
                    <label>{{ translate('Rating') }}</label>
                    <div id="detailRating"></div>
                </div>
                <div class="lead-detail-item">
                    <label>{{ translate('Quality Score') }}</label>
                    <div id="detailQuality"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate('Close') }}</button>
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
                    <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
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

    // Select All
    $('#selectAll').on('change', function() {
        $('.lead-checkbox').prop('checked', $(this).prop('checked'));
        updateSelection();
    });

    $('.lead-checkbox').on('change', updateSelection);

    function updateSelection() {
        selectedLeads = [];
        $('.lead-checkbox:checked').each(function() {
            selectedLeads.push($(this).val());
        });
        $('#bulkActionBar').toggleClass('d-none', selectedLeads.length === 0);
        $('#selectedCount').text(selectedLeads.length);
    }

    // Import save option toggle
    $('input[name="saveOption"]').on('change', function() {
        if ($(this).val() === 'new') {
            $('#existingGroupDiv').addClass('d-none');
            $('#newGroupDiv').removeClass('d-none');
        } else {
            $('#existingGroupDiv').removeClass('d-none');
            $('#newGroupDiv').addClass('d-none');
        }
    });

    // Import leads
    $('#confirmImport').on('click', function() {
        if (!selectedLeads.length) {
            notify('error', '{{ translate("No leads selected") }}');
            return;
        }

        var saveOption = $('input[name="saveOption"]:checked').val();
        var groupId = null;
        var newGroupName = null;

        if (saveOption === 'existing') {
            groupId = $('#importGroupId').val();
            if (!groupId) {
                notify('error', '{{ translate("Select a group") }}');
                return;
            }
        } else {
            newGroupName = $('#newGroupName').val().trim();
            if (!newGroupName) {
                notify('error', '{{ translate("Enter group name") }}');
                return;
            }
        }

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> {{ translate("Importing...") }}');

        $.post('{{ route("user.lead-generation.leads.import") }}', {
            _token: '{{ csrf_token() }}',
            lead_ids: selectedLeads,
            group_id: groupId,
            new_group_name: newGroupName,
            import_type: $('#importType').val(),
            skip_duplicates: $('#skipDuplicates').is(':checked') ? 1 : 0
        }, function(response) {
            if (response.status) {
                notify('success', response.message);
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                notify('error', response.message);
                $btn.prop('disabled', false).html('<i class="bi bi-download"></i> {{ translate("Import") }}');
            }
        }).fail(function() {
            notify('error', '{{ translate("Import failed") }}');
            $btn.prop('disabled', false).html('<i class="bi bi-download"></i> {{ translate("Import") }}');
        });
    });

    // Export selected
    $('#exportSelectedBtn').on('click', function() {
        if (!selectedLeads.length) return;
        window.location.href = '{{ route("user.lead-generation.leads.export") }}?lead_ids=' + selectedLeads.join(',');
    });

    // Confirmation Modal Helper
    var pendingAction = null;

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

        $('#detailName').text(lead.business_name || '{{ translate("Lead Details") }}');
        $('#detailCategory').text(lead.category || '-');

        var contact = '';
        if (lead.email) contact += '<div><i class="bi bi-envelope text-primary"></i> ' + lead.email + '</div>';
        if (lead.phone) contact += '<div><i class="bi bi-telephone text-success"></i> ' + lead.phone + '</div>';
        $('#detailContact').html(contact || '-');

        var address = [lead.address, lead.city, lead.country].filter(Boolean).join(', ');
        $('#detailAddress').text(address || '-');

        if (lead.website) {
            $('#detailWebsite').html('<a href="' + lead.website + '" target="_blank">' + lead.website + '</a>');
        } else {
            $('#detailWebsite').text('-');
        }

        if (lead.rating) {
            var stars = '';
            for (var i = 1; i <= 5; i++) {
                stars += '<i class="bi bi-star' + (i <= lead.rating ? '-fill' : '') + ' text-warning"></i>';
            }
            $('#detailRating').html(stars);
        } else {
            $('#detailRating').text('-');
        }

        var qualityClass = lead.quality_score >= 70 ? 'success' : (lead.quality_score >= 40 ? 'warning' : 'danger');
        $('#detailQuality').html('<span class="i-badge ' + qualityClass + '-soft pill">' + lead.quality_score + '%</span>');

        $('#leadDetailModal').modal('show');
    });

    // Delete single lead with confirmation modal
    $(document).on('click', '.delete-lead', function(e) {
        e.stopPropagation();
        e.preventDefault();

        var id = $(this).data('id');
        var name = $(this).data('name') || '{{ translate("this lead") }}';
        var $row = $(this).closest('tr');

        showConfirmDialog(
            '{{ translate("Delete Lead?") }}',
            '{{ translate("Are you sure you want to delete") }} "' + name + '"? {{ translate("This action cannot be undone.") }}',
            function() {
                $.ajax({
                    url: '{{ url("user/lead-generation/lead") }}/' + id,
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.status) {
                            $row.fadeOut(300, function() { $(this).remove(); });
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

    // Bulk delete with confirmation modal
    $('#bulkDeleteBtn').on('click', function() {
        showConfirmDialog(
            '{{ translate("Delete Selected Leads?") }}',
            '{{ translate("Are you sure you want to delete") }} ' + selectedLeads.length + ' {{ translate("selected leads? This action cannot be undone.") }}',
            function() {
                $.post('{{ route("user.lead-generation.leads.bulk-delete") }}', {
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

})(jQuery);
</script>
@endpush

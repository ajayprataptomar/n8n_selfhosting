@push("style-include")
<link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
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
        </div>

        <!-- Filters -->
        <div class="table-filter mb-4">
            <form action="{{ route('user.lead-generation.leads') }}" method="GET">
                <div class="row g-3 align-items-end">
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
                        <label class="form-label">{{ translate('Status') }}</label>
                        <select class="form-select" name="not_imported">
                            <option value="">{{ translate('Any') }}</option>
                            <option value="1" {{ request('not_imported') ? 'selected' : '' }}>{{ translate('Not Imported') }}</option>
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">{{ translate('Quality') }}</label>
                        <select class="form-select" name="min_quality">
                            <option value="">{{ translate('Any') }}</option>
                            <option value="80" {{ request('min_quality') == '80' ? 'selected' : '' }}>80%+</option>
                            <option value="60" {{ request('min_quality') == '60' ? 'selected' : '' }}>60%+</option>
                        </select>
                    </div>
                    <div class="col-lg-4">
                        <button type="submit" class="i-btn btn--primary btn--md">{{ translate('Filter') }}</button>
                        <a href="{{ route('user.lead-generation.leads') }}" class="i-btn btn--danger outline btn--md">{{ translate('Reset') }}</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Bulk Actions -->
        <div class="card mb-4 d-none" id="bulkActionBar">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span><span id="selectedCount">0</span> {{ translate('selected') }}</span>
                    <div class="d-flex gap-2">
                        <button class="i-btn btn--primary btn--sm" data-bs-toggle="modal" data-bs-target="#importModal">
                            <i class="bi bi-download"></i> {{ translate('Import') }}
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
            <div class="card-header">
                <h4 class="card-title">{{ translate('All Leads') }}</h4>
                <a href="{{ route('user.lead-generation.index') }}" class="i-btn btn--primary btn--sm">
                    <i class="bi bi-plus"></i> {{ translate('New Job') }}
                </a>
            </div>
            <div class="card-body px-0 pt-0">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                <th>{{ translate('Business') }}</th>
                                <th>{{ translate('Email') }}</th>
                                <th>{{ translate('Phone') }}</th>
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
                                        @if($lead->category)<br><small class="text-muted">{{ $lead->category }}</small>@endif
                                    </td>
                                    <td>{{ $lead->email ?: '-' }}</td>
                                    <td>{{ $lead->phone ?: '-' }}</td>
                                    <td>{{ $lead->city ?: '' }}{{ $lead->city && $lead->country ? ', ' : '' }}{{ $lead->country ?: '' }}</td>
                                    <td><span class="i-badge {{ $lead->quality_badge_color }} pill">{{ $lead->quality_score }}%</span></td>
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
                                                    data-lead="{{ json_encode($lead) }}" title="{{ translate('View Details') }}">
                                                <i class="ri-eye-line"></i>
                                            </button>
                                            @if(!$lead->isImported())
                                            <button class="icon-btn btn-ghost btn-sm success-soft circle save-to-contact"
                                                    data-id="{{ $lead->id }}" title="{{ translate('Save to Contacts') }}">
                                                <i class="ri-user-add-line"></i>
                                            </button>
                                            @endif
                                            <button class="icon-btn btn-ghost btn-sm danger-soft circle delete-lead"
                                                    data-id="{{ $lead->id }}" title="{{ translate('Delete') }}">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">{{ translate('No leads found') }}</td>
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
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ translate('Import Leads') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">{{ translate('Select Group') }}</label>
                    <select class="form-select select2-search" id="importGroupId" required>
                        <option value="">{{ translate('Select') }}</option>
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ translate('Import As') }}</label>
                    <select class="form-select" id="importType">
                        <option value="all">{{ translate('All types') }}</option>
                        <option value="email">{{ translate('Email only') }}</option>
                        <option value="sms">{{ translate('SMS only') }}</option>
                        <option value="whatsapp">{{ translate('WhatsApp only') }}</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                <button type="button" class="i-btn btn--primary btn--md" id="confirmImport">{{ translate('Import') }}</button>
            </div>
        </div>
    </div>
</div>
{{-- Lead Detail Modal --}}
<div class="modal fade" id="leadDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-user-line"></i> {{ translate('Lead Details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">{{ translate('Contact Information') }}</h6>
                        <table class="table table-sm">
                            <tr><td class="text-muted" width="35%">{{ translate('Business') }}</td><td id="detail_business" class="fw-semibold">-</td></tr>
                            <tr><td class="text-muted">{{ translate('Email') }}</td><td id="detail_email">-</td></tr>
                            <tr><td class="text-muted">{{ translate('Phone') }}</td><td id="detail_phone">-</td></tr>
                            <tr><td class="text-muted">{{ translate('Website') }}</td><td id="detail_website">-</td></tr>
                            <tr><td class="text-muted">{{ translate('Location') }}</td><td id="detail_location">-</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">{{ translate('Social & Quality') }}</h6>
                        <table class="table table-sm">
                            <tr><td class="text-muted" width="35%">{{ translate('Facebook') }}</td><td id="detail_facebook">-</td></tr>
                            <tr><td class="text-muted">{{ translate('Instagram') }}</td><td id="detail_instagram">-</td></tr>
                            <tr><td class="text-muted">{{ translate('Twitter') }}</td><td id="detail_twitter">-</td></tr>
                            <tr><td class="text-muted">{{ translate('LinkedIn') }}</td><td id="detail_linkedin">-</td></tr>
                            <tr><td class="text-muted">{{ translate('Quality') }}</td><td id="detail_quality">-</td></tr>
                            <tr><td class="text-muted">{{ translate('Source') }}</td><td id="detail_source">-</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate('Close') }}</button>
                <button type="button" class="i-btn btn--success btn--md" id="detailSaveToContact" data-id="">
                    <i class="ri-user-add-line"></i> {{ translate('Save to Contacts') }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Quick Save to Contact Modal --}}
<div class="modal fade" id="quickSaveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ translate('Save to Contacts') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">{{ translate('Contact Group') }} <small class="text-danger">*</small></label>
                    <select class="form-select" id="quickSaveGroupId" required>
                        <option value="">{{ translate('Select Group') }}</option>
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                <button type="button" class="i-btn btn--success btn--md" id="confirmQuickSave">
                    <i class="ri-check-line"></i> {{ translate('Save') }}
                </button>
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

    $('#confirmImport').on('click', function() {
        if (!selectedLeads.length) return;
        var groupId = $('#importGroupId').val();
        if (!groupId) {
            notify('error', '{{ translate("Select a group") }}');
            return;
        }

        $.post('{{ route("user.lead-generation.leads.import") }}', {
            _token: '{{ csrf_token() }}',
            lead_ids: selectedLeads,
            group_id: groupId,
            import_type: $('#importType').val()
        }, function(response) {
            if (response.status) {
                notify('success', response.message);
                location.reload();
            } else {
                notify('error', response.message);
            }
        });
    });

    // View lead detail
    $(document).on('click', '.view-lead', function() {
        var lead = $(this).data('lead');
        if (typeof lead === 'string') lead = JSON.parse(lead);

        $('#detail_business').text(lead.business_name || '-');
        $('#detail_email').html(lead.email ? '<a href="mailto:' + lead.email + '">' + lead.email + '</a>' : '-');
        $('#detail_phone').html(lead.phone ? '<a href="tel:' + lead.phone + '">' + lead.phone + '</a>' : '-');
        $('#detail_website').html(lead.website ? '<a href="' + lead.website + '" target="_blank">' + lead.website + ' <i class="ri-external-link-line"></i></a>' : '-');
        $('#detail_location').text([lead.city, lead.state, lead.country].filter(Boolean).join(', ') || '-');
        $('#detail_facebook').html(lead.facebook ? '<a href="' + lead.facebook + '" target="_blank">{{ translate("View") }} <i class="ri-external-link-line"></i></a>' : '-');
        $('#detail_instagram').html(lead.instagram ? '<a href="' + lead.instagram + '" target="_blank">{{ translate("View") }} <i class="ri-external-link-line"></i></a>' : '-');
        $('#detail_twitter').html(lead.twitter ? '<a href="' + lead.twitter + '" target="_blank">{{ translate("View") }} <i class="ri-external-link-line"></i></a>' : '-');
        $('#detail_linkedin').html(lead.linkedin ? '<a href="' + lead.linkedin + '" target="_blank">{{ translate("View") }} <i class="ri-external-link-line"></i></a>' : '-');
        $('#detail_quality').html('<span class="i-badge ' + (lead.quality_score >= 70 ? 'success' : lead.quality_score >= 40 ? 'warning' : 'danger') + '-soft pill">' + (lead.quality_score || 0) + '%</span>');
        $('#detail_source').text(lead.source_url || '-');
        $('#detailSaveToContact').data('id', lead.id);

        $('#leadDetailModal').modal('show');
    });

    // Quick save single lead to contact
    var quickSaveLeadId = null;

    $(document).on('click', '.save-to-contact, #detailSaveToContact', function() {
        quickSaveLeadId = $(this).data('id');
        $('#leadDetailModal').modal('hide');
        $('#quickSaveModal').modal('show');
    });

    $('#confirmQuickSave').on('click', function() {
        var groupId = $('#quickSaveGroupId').val();
        if (!groupId) {
            notify('error', '{{ translate("Select a group") }}');
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true).html('<i class="ri-loader-2-line ri-spin"></i> {{ translate("Saving...") }}');

        $.post('{{ route("user.lead-generation.leads.import") }}', {
            _token: '{{ csrf_token() }}',
            lead_ids: [quickSaveLeadId],
            group_id: groupId,
            import_type: 'all'
        }, function(response) {
            if (response.status) {
                notify('success', response.message || '{{ translate("Lead saved to contacts") }}');
                $('#quickSaveModal').modal('hide');
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                notify('error', response.message || '{{ translate("Failed to save") }}');
            }
        }).fail(function() {
            notify('error', '{{ translate("Failed to save lead") }}');
        }).always(function() {
            btn.prop('disabled', false).html('<i class="ri-check-line"></i> {{ translate("Save") }}');
        });
    });

    $('.delete-lead').on('click', function() {
        if (!confirm('{{ translate("Delete?") }}')) return;
        $.ajax({
            url: '{{ route("user.lead-generation.lead.delete", "") }}/' + $(this).data('id'),
            method: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.status) location.reload();
            }
        });
    });

    $('#bulkDeleteBtn').on('click', function() {
        if (!confirm('{{ translate("Delete selected?") }}')) return;
        $.post('{{ route("user.lead-generation.leads.bulk-delete") }}', {
            _token: '{{ csrf_token() }}',
            lead_ids: selectedLeads
        }, function(response) {
            if (response.status) location.reload();
        });
    });

})(jQuery);
</script>
@endpush

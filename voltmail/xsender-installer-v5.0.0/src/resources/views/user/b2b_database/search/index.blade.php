@extends('user.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ translate("Search B2B Contacts") }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('user.dashboard') }}">{{ translate("Dashboard") }}</a></li>
                            <li class="breadcrumb-item active">{{ translate("B2B Search") }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <div class="card-header-left">
                    <h4 class="card-title">{{ translate("Search Filters") }}</h4>
                </div>
            </div>
            <div class="card-body">
                <form id="b2bSearchForm">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-inner">
                                <label class="form-label">{{ translate("Provider") }} <small class="text-danger">*</small></label>
                                <select name="provider_id" id="providerId" class="form-select" required>
                                    <option value="">{{ translate("Select Provider") }}</option>
                                    @foreach($providers as $provider)
                                        <option value="{{ $provider->id }}" data-type="{{ $provider->type instanceof \BackedEnum ? $provider->type->value : $provider->type }}">
                                            {{ $provider->name }} ({{ $provider->type instanceof \BackedEnum ? $provider->type->label() : $provider->type }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-inner">
                                <label class="form-label">{{ translate("Job Title") }}</label>
                                <input type="text" class="form-control" name="filters[job_title]" placeholder="{{ translate('CEO, CTO, VP Marketing') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-inner">
                                <label class="form-label">{{ translate("Company / Domain") }}</label>
                                <input type="text" class="form-control" name="filters[company]" placeholder="{{ translate('Acme Inc or acme.com') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-inner">
                                <label class="form-label">{{ translate("Domain") }}</label>
                                <input type="text" class="form-control" name="filters[domain]" placeholder="{{ translate('example.com') }}">
                                <small class="text-muted">{{ translate("Required for Hunter.io & Snov.io") }}</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-inner">
                                <label class="form-label">{{ translate("Location") }}</label>
                                <input type="text" class="form-control" name="filters[location]" placeholder="{{ translate('United States, London') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-inner">
                                <label class="form-label">{{ translate("Seniority") }}</label>
                                <select name="filters[seniority]" class="form-select">
                                    <option value="">{{ translate("Any") }}</option>
                                    <option value="c_suite">C-Level</option>
                                    <option value="vp">VP</option>
                                    <option value="director">Director</option>
                                    <option value="manager">Manager</option>
                                    <option value="senior">Senior</option>
                                    <option value="entry">Entry Level</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-inner">
                                <label class="form-label">{{ translate("Keywords") }}</label>
                                <input type="text" class="form-control" name="filters[keywords]" placeholder="{{ translate('SaaS, B2B, Marketing') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-inner">
                                <label class="form-label">{{ translate("Department") }}</label>
                                <select name="filters[department]" class="form-select">
                                    <option value="">{{ translate("Any") }}</option>
                                    <option value="engineering">Engineering</option>
                                    <option value="sales">Sales</option>
                                    <option value="marketing">Marketing</option>
                                    <option value="finance">Finance</option>
                                    <option value="hr">Human Resources</option>
                                    <option value="operations">Operations</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="i-btn btn--primary btn--md" id="searchBtn">
                                <i class="ri-search-line"></i> {{ translate("Search Contacts") }}
                            </button>
                            <button type="reset" class="i-btn btn--dark outline btn--md">
                                <i class="ri-refresh-line"></i> {{ translate("Reset") }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Search Results --}}
        <div class="card d-none" id="resultsCard">
            <div class="card-header">
                <div class="card-header-left">
                    <h4 class="card-title">
                        {{ translate("Results") }} (<span id="totalResults">0</span>)
                    </h4>
                </div>
                <div class="card-header-right">
                    <button class="i-btn btn--success btn--md d-none" id="importSelectedBtn">
                        <i class="ri-download-line"></i> {{ translate("Import Selected") }} (<span id="selectedCount">0</span>)
                    </button>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll"></th>
                                <th>{{ translate("Name") }}</th>
                                <th>{{ translate("Title") }}</th>
                                <th>{{ translate("Company") }}</th>
                                <th>{{ translate("Email") }}</th>
                                <th>{{ translate("Phone") }}</th>
                                <th>{{ translate("Location") }}</th>
                            </tr>
                        </thead>
                        <tbody id="resultsBody"></tbody>
                    </table>
                </div>
                <div id="pagination" class="d-flex justify-content-between align-items-center mt-3"></div>
            </div>
        </div>
    </div>
</main>

{{-- Import Modal --}}
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ translate("Import to Contact Group") }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">
                    <i class="ri-information-line"></i>
                    <span id="importSummary"></span>
                </div>

                <div class="form-inner mb-3">
                    <label class="form-label">{{ translate("Import To") }}</label>
                    <select id="importGroupId" class="form-select">
                        <option value="">{{ translate("Select Existing Group") }}</option>
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-inner mb-3">
                    <label class="form-label">{{ translate("Or Create New Group") }}</label>
                    <input type="text" id="newGroupName" class="form-control" placeholder="{{ translate('New group name') }}">
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="skipDuplicates" checked>
                    <label class="form-check-label" for="skipDuplicates">{{ translate("Skip duplicates (match by email/phone)") }}</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
                <button type="button" class="i-btn btn--success btn--md" id="confirmImport">
                    <i class="ri-download-line"></i> {{ translate("Import Contacts") }}
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('script-push')
<script>
(function($) {
    "use strict";

    var searchResults = [];
    var currentPage = 1;
    var currentProviderId = null;

    $('#b2bSearchForm').on('submit', function(e) {
        e.preventDefault();
        currentPage = 1;
        executeSearch();
    });

    function executeSearch() {
        var btn = $('#searchBtn');
        var formData = $('#b2bSearchForm').serializeArray();
        var data = { _token: '{{ csrf_token() }}', page: currentPage, per_page: 25 };

        formData.forEach(function(item) {
            if (item.name === 'provider_id') {
                data.provider_id = item.value;
                currentProviderId = item.value;
            } else if (item.name.startsWith('filters[')) {
                var key = item.name.replace('filters[', '').replace(']', '');
                if (item.value) {
                    if (!data.filters) data.filters = {};
                    data.filters[key] = item.value;
                }
            }
        });

        if (!data.provider_id) {
            notify('error', '{{ translate("Please select a provider") }}');
            return;
        }

        btn.prop('disabled', true).html('<i class="ri-loader-2-line ri-spin"></i> {{ translate("Searching...") }}');

        $.ajax({
            url: '{{ route("user.b2b-database.search.execute") }}',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            success: function(res) {
                if (res.success) {
                    searchResults = res.results || [];
                    renderResults(res);
                    $('#resultsCard').removeClass('d-none');
                    notify('success', '{{ translate("Found") }} ' + (res.total || 0) + ' {{ translate("contacts") }}');
                } else {
                    notify('error', res.error || '{{ translate("Search failed") }}');
                }
            },
            error: function(xhr) {
                notify('error', xhr.responseJSON?.message || '{{ translate("Search failed") }}');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="ri-search-line"></i> {{ translate("Search Contacts") }}');
            }
        });
    }

    function renderResults(res) {
        var html = '';
        var results = res.results || [];

        if (results.length === 0) {
            html = '<tr><td colspan="7" class="text-center text-muted py-4">{{ translate("No results found") }}</td></tr>';
        }

        results.forEach(function(r, i) {
            var email = r.is_revealed && r.email ? r.email : '<span class="text-muted"><i class="ri-lock-line"></i> {{ translate("Reveal on import") }}</span>';
            var phone = r.is_revealed && r.phone ? r.phone : '<span class="text-muted"><i class="ri-lock-line"></i></span>';

            html += '<tr>';
            html += '<td><input type="checkbox" class="result-check" data-index="' + i + '"></td>';
            html += '<td>' + (r.first_name || '') + ' ' + (r.last_name || '') + '</td>';
            html += '<td>' + (r.job_title || '-') + '</td>';
            html += '<td>' + (r.company || '-') + '</td>';
            html += '<td>' + email + '</td>';
            html += '<td>' + phone + '</td>';
            html += '<td>' + (r.location || '-') + '</td>';
            html += '</tr>';
        });

        $('#resultsBody').html(html);
        $('#totalResults').text(res.total || results.length);
        updateSelectedCount();

        // Pagination
        var paginationHtml = '<span class="text-muted small">{{ translate("Page") }} ' + currentPage + '</span>';
        if (results.length >= 25) {
            paginationHtml += '<button class="i-btn btn--primary outline btn--sm" id="nextPageBtn">{{ translate("Next Page") }} <i class="ri-arrow-right-line"></i></button>';
        }
        $('#pagination').html(paginationHtml);
    }

    $(document).on('click', '#nextPageBtn', function() {
        currentPage++;
        executeSearch();
    });

    $(document).on('change', '#selectAll', function() {
        $('.result-check').prop('checked', $(this).is(':checked'));
        updateSelectedCount();
    });

    $(document).on('change', '.result-check', function() {
        updateSelectedCount();
    });

    function updateSelectedCount() {
        var count = $('.result-check:checked').length;
        $('#selectedCount').text(count);
        if (count > 0) {
            $('#importSelectedBtn').removeClass('d-none');
        } else {
            $('#importSelectedBtn').addClass('d-none');
        }
    }

    $('#importSelectedBtn').on('click', function() {
        var count = $('.result-check:checked').length;
        $('#importSummary').text(count + ' {{ translate("contacts selected for import. Contacts will be revealed and imported.") }}');
        $('#importModal').modal('show');
    });

    $('#confirmImport').on('click', function() {
        var btn = $(this);
        var prospects = [];

        $('.result-check:checked').each(function() {
            var idx = $(this).data('index');
            if (searchResults[idx]) {
                prospects.push(searchResults[idx]);
            }
        });

        if (prospects.length === 0) {
            notify('error', '{{ translate("No contacts selected") }}');
            return;
        }

        var groupId = $('#importGroupId').val();
        var newGroupName = $('#newGroupName').val();

        if (!groupId && !newGroupName) {
            notify('error', '{{ translate("Please select or create a contact group") }}');
            return;
        }

        btn.prop('disabled', true).html('<i class="ri-loader-2-line ri-spin"></i> {{ translate("Importing...") }}');

        $.ajax({
            url: '{{ route("user.b2b-database.import") }}',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            data: JSON.stringify({
                _token: '{{ csrf_token() }}',
                provider_id: currentProviderId,
                prospects: prospects,
                group_id: groupId || null,
                new_group_name: newGroupName || null,
                skip_duplicates: $('#skipDuplicates').is(':checked')
            }),
            success: function(res) {
                if (res.success) {
                    notify('success', res.imported + ' {{ translate("contacts imported") }}' + (res.duplicates_skipped > 0 ? ', ' + res.duplicates_skipped + ' {{ translate("duplicates skipped") }}' : ''));
                    $('#importModal').modal('hide');
                    $('.result-check').prop('checked', false);
                    updateSelectedCount();
                } else {
                    notify('error', res.error || '{{ translate("Import failed") }}');
                }
            },
            error: function(xhr) {
                notify('error', xhr.responseJSON?.message || '{{ translate("Import failed") }}');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="ri-download-line"></i> {{ translate("Import Contacts") }}');
            }
        });
    });

})(jQuery);
</script>
@endpush

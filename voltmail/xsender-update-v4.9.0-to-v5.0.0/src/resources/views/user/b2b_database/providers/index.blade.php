@extends('user.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ translate("B2B Contact Providers") }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('user.dashboard') }}">{{ translate("Dashboard") }}</a></li>
                            <li class="breadcrumb-item active">{{ translate("B2B Providers") }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        {{-- How It Works --}}
        <div class="card mb-4">
            <div class="card-body p-4">
                <h5 class="mb-3"><i class="ri-question-line"></i> {{ translate("How B2B Database Works") }}</h5>
                <div class="row g-4">
                    <div class="col-md-3 text-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2" style="width:48px;height:48px;"><span class="fw-bold text-primary">1</span></div>
                        <h6>{{ translate("Connect Provider") }}</h6>
                        <small class="text-muted">{{ translate("Add your API key from any provider below. You manage your own API account and credits.") }}</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2" style="width:48px;height:48px;"><span class="fw-bold text-primary">2</span></div>
                        <h6>{{ translate("Search Contacts") }}</h6>
                        <small class="text-muted">{{ translate("Search by job title, company, domain, or location. Apollo & RocketReach search is free; Hunter & Snov use credits per search.") }}</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2" style="width:48px;height:48px;"><span class="fw-bold text-primary">3</span></div>
                        <h6>{{ translate("Import to Contacts") }}</h6>
                        <small class="text-muted">{{ translate("Select prospects and import them into contact groups with automatic duplicate detection.") }}</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2" style="width:48px;height:48px;"><span class="fw-bold text-primary">4</span></div>
                        <h6>{{ translate("Launch Campaign") }}</h6>
                        <small class="text-muted">{{ translate("Use imported contacts for Email, SMS, or WhatsApp campaigns.") }}</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Provider Cards --}}
        <div class="row g-4 mb-4">
            @foreach($availableTypes as $type)
                @php
                    $connected = $providers->first(fn($p) => ($p->type instanceof \BackedEnum ? $p->type->value : $p->type) === $type['value']);
                    $meta = $connected ? (is_object($connected->meta_data) ? (array) $connected->meta_data : json_decode($connected->meta_data ?? '{}', true)) : [];
                @endphp
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card h-100 {{ $connected ? 'border-success' : '' }}">
                        <div class="card-body p-4">
                            {{-- Header --}}
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="rounded-circle {{ $connected ? 'bg-success' : 'bg-secondary' }} bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;">
                                    <i class="{{ $type['icon'] }} fs-5 {{ $connected ? 'text-success' : 'text-secondary' }}"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">{{ $type['label'] }}</h6>
                                    <small class="text-muted">{{ $type['description'] }}</small>
                                </div>
                            </div>

                            @if($connected)
                                {{-- Connected State --}}
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <span class="badge bg-success"><i class="ri-check-line"></i> {{ translate("API Connected") }}</span>
                                    @if($connected->is_active)
                                        <span class="badge bg-primary bg-opacity-10 text-primary">{{ translate("Active") }}</span>
                                    @endif
                                </div>

                                {{-- API Details --}}
                                <div class="bg-light rounded p-3 mb-3">
                                    <table class="w-100" style="font-size: 0.8rem;">
                                        @if(!empty($meta['api_key']))
                                        <tr>
                                            <td class="text-muted pe-2 py-1">{{ translate("API Key") }}</td>
                                            <td class="py-1"><code class="bg-white px-2 py-1 rounded">{{ substr($meta['api_key'], 0, 12) }}••••••</code></td>
                                        </tr>
                                        @endif
                                        @if(!empty($meta['client_id']))
                                        <tr>
                                            <td class="text-muted pe-2 py-1">{{ translate("Client ID") }}</td>
                                            <td class="py-1"><code class="bg-white px-2 py-1 rounded">{{ substr($meta['client_id'], 0, 12) }}••••</code></td>
                                        </tr>
                                        @endif
                                        <tr>
                                            <td class="text-muted pe-2 py-1">{{ translate("Label") }}</td>
                                            <td class="py-1">{{ $connected->name }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted pe-2 py-1">{{ translate("Lookups") }}</td>
                                            <td class="py-1"><strong>{{ $connected->monthly_lookups }}</strong> {{ translate("this month") }} / {{ $connected->total_lookups }} {{ translate("total") }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted pe-2 py-1">{{ translate("Added") }}</td>
                                            <td class="py-1">{{ $connected->created_at->format('M d, Y') }}</td>
                                        </tr>
                                    </table>
                                </div>

                                {{-- Actions --}}
                                <div class="d-flex gap-2">
                                    <button class="i-btn btn--success outline btn--sm flex-fill test-connection"
                                            data-id="{{ $connected->id }}"
                                            data-url="{{ route('user.b2b-database.providers.test', $connected->id) }}">
                                        <i class="ri-wifi-line"></i> {{ translate("Test") }}
                                    </button>
                                    <button class="i-btn btn--primary outline btn--sm flex-fill connect-provider"
                                            data-type="{{ $type['value'] }}"
                                            data-label="{{ $type['label'] }}"
                                            data-fields="{{ json_encode($type['fields']) }}"
                                            data-meta="{{ json_encode($meta) }}"
                                            data-name="{{ $connected->name }}">
                                        <i class="ri-edit-line"></i> {{ translate("Update") }}
                                    </button>
                                    <button class="i-btn btn--danger outline btn--sm"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal{{ $connected->id }}">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </div>
                            @else
                                {{-- Not Connected State --}}
                                <div class="text-center py-3">
                                    <div class="mb-3">
                                        <i class="ri-plug-line fs-1 text-muted opacity-50"></i>
                                    </div>
                                    <p class="text-muted small mb-3">{{ translate("No API key configured. Connect to start searching contacts.") }}</p>
                                    <button class="i-btn btn--primary btn--md w-100 connect-provider"
                                            data-type="{{ $type['value'] }}"
                                            data-label="{{ $type['label'] }}"
                                            data-fields="{{ json_encode($type['fields']) }}">
                                        <i class="ri-plug-line"></i> {{ translate("Connect") }} {{ $type['label'] }}
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Delete Modal --}}
                @if($connected)
                <div class="modal fade" id="deleteModal{{ $connected->id }}" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered modal-sm">
                        <div class="modal-content">
                            <div class="modal-body text-center p-4">
                                <i class="ri-error-warning-line fs-1 text-danger"></i>
                                <h5 class="mt-2">{{ translate("Remove Provider?") }}</h5>
                                <p class="text-muted">{{ translate("This will disconnect") }} {{ $type['label'] }}</p>
                                <form action="{{ route('user.b2b-database.providers.destroy', $connected->id) }}" method="POST">
                                    @csrf @method('DELETE')
                                    <div class="d-flex gap-2 justify-content-center mt-3">
                                        <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
                                        <button type="submit" class="i-btn btn--danger btn--md">{{ translate("Remove") }}</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            @endforeach
        </div>
    </div>
</main>

{{-- Connect/Update Provider Modal --}}
<div class="modal fade" id="addProviderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('user.b2b-database.providers.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="providerModalTitle">{{ translate("Connect Provider") }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="type" id="providerType">

                    <div class="form-inner mb-3">
                        <label class="form-label">{{ translate("Label") }} <small class="text-muted">({{ translate("optional") }})</small></label>
                        <input type="text" class="form-control" name="name" id="providerName" placeholder="{{ translate('e.g. My Apollo Account') }}">
                    </div>

                    <div id="credentialFields"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
                    <button type="submit" class="i-btn btn--primary btn--md">
                        <i class="ri-save-line"></i> {{ translate("Save & Connect") }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('script-push')
<script>
(function($) {
    "use strict";

    var fieldLabels = {
        'api_key': '{{ translate("API Key") }}',
        'client_id': '{{ translate("Client ID") }}',
        'client_secret': '{{ translate("Client Secret") }}'
    };

    var fieldHints = {
        'apollo': { url: 'https://apollo.io', text: '{{ translate("Sign up at apollo.io, then go to Settings > Integrations > API Keys to get your API key.") }}' },
        'hunter': { url: 'https://hunter.io', text: '{{ translate("Sign up at hunter.io, then go to your Dashboard > API Keys to get your API key.") }}' },
        'snov': { url: 'https://snov.io', text: '{{ translate("Sign up at snov.io, then go to Account > API to get your Client ID and Client Secret.") }}' },
        'rocketreach': { url: 'https://rocketreach.co', text: '{{ translate("Sign up at rocketreach.co, then go to your Account > API Keys to get your API key.") }}' }
    };

    $('.connect-provider').on('click', function() {
        var type = $(this).data('type');
        var label = $(this).data('label');
        var fields = $(this).data('fields');
        var savedMeta = $(this).data('meta') || {};
        var savedName = $(this).data('name') || '';
        var isEdit = !$.isEmptyObject(savedMeta);

        $('#providerType').val(type);
        $('#providerName').val(savedName);
        $('#providerModalTitle').text((isEdit ? '{{ translate("Update") }} ' : '{{ translate("Connect") }} ') + label);

        var html = '';
        if (fieldHints[type]) {
            html += '<div class="alert alert-info small mb-3">';
            html += '<i class="ri-information-line me-1"></i> ' + fieldHints[type].text;
            html += '<br><a href="' + fieldHints[type].url + '" target="_blank" class="fw-bold mt-1 d-inline-block">' + fieldHints[type].url + ' <i class="ri-external-link-line"></i></a>';
            html += '</div>';
        }

        fields.forEach(function(field) {
            var savedValue = savedMeta[field] || '';
            html += '<div class="form-inner mb-3">';
            html += '<label class="form-label">' + (fieldLabels[field] || field) + ' <small class="text-danger">*</small></label>';
            html += '<input type="' + (isEdit ? 'text' : 'password') + '" class="form-control" name="meta_data[' + field + ']" required value="' + savedValue + '" placeholder="{{ translate("Paste your") }} ' + (fieldLabels[field] || field) + ' {{ translate("here") }}">';
            html += '<div class="form-text"><a href="#" class="toggle-visibility" style="font-size:0.8rem;"><i class="ri-eye-line"></i> {{ translate("Show/Hide") }}</a></div>';
            html += '</div>';
        });

        $('#credentialFields').html(html);
        $('#addProviderModal').modal('show');
    });

    $(document).on('click', '.toggle-visibility', function(e) {
        e.preventDefault();
        var input = $(this).closest('.form-inner').find('input');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            $(this).html('<i class="ri-eye-off-line"></i> {{ translate("Hide") }}');
        } else {
            input.attr('type', 'password');
            $(this).html('<i class="ri-eye-line"></i> {{ translate("Show") }}');
        }
    });

    $('.test-connection').on('click', function() {
        var btn = $(this);
        var url = btn.data('url');
        var originalHtml = btn.html();

        btn.prop('disabled', true).html('<i class="ri-loader-2-line ri-spin"></i> {{ translate("Testing...") }}');

        $.ajax({
            url: url,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            success: function(res) {
                if (res.success) {
                    notify('success', res.message);
                } else {
                    notify('error', res.message || '{{ translate("Connection failed") }}');
                }
            },
            error: function() { notify('error', '{{ translate("Connection test failed") }}'); },
            complete: function() { btn.prop('disabled', false).html(originalHtml); }
        });
    });

})(jQuery);
</script>
@endpush

@extends('admin.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ $title }} - {{ $domain->domain }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="{{ route("admin.dashboard") }}">{{ translate("Dashboard") }}</a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="{{ route("admin.gateway.sending-domain.index") }}">{{ translate("Sending Domains") }}</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">{{ translate("DNS Records") }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        @php
            $keysGenerated = !empty($dnsRecords['dkim']['value']);
            $verifiedCount = ($dnsRecords['dkim']['verified'] ? 1 : 0) + ($dnsRecords['spf']['verified'] ? 1 : 0) + ($dnsRecords['dmarc']['verified'] ? 1 : 0);
            $allVerified = $verifiedCount === 3;
        @endphp

        {{-- Domain Status Card --}}
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <h5 class="mb-1">{{ $domain->domain }}</h5>
                                <span class="i-badge dot {{ $domain->getStatusBadgeClass() }}-soft pill">{{ ucfirst($domain->status) }}</span>
                                @if($domain->verified_at)
                                    <small class="text-muted ms-2">{{ translate('Verified') }}: {{ $domain->verified_at->diffForHumans() }}</small>
                                @endif
                            </div>
                            <div class="d-flex gap-2">
                                @if($keysGenerated)
                                    <button type="button" class="i-btn btn--primary btn--sm" id="verifyDnsBtn" data-url="{{ route('admin.gateway.sending-domain.verify', $domain->uid) }}">
                                        <i class="ri-shield-check-line"></i> {{ translate("Verify DNS Records") }}
                                    </button>
                                @endif
                                <button type="button" class="i-btn btn--warning btn--sm" data-bs-toggle="modal" data-bs-target="#regenerateKeysModal">
                                    <i class="ri-refresh-line"></i> {{ translate("Regenerate Keys") }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(!$keysGenerated)
            <div class="alert alert-danger d-flex align-items-start gap-3 mb-4" role="alert">
                <i class="ri-error-warning-line fs-4 mt-1"></i>
                <div>
                    <strong>{{ translate('DKIM Keys Not Generated') }}</strong>
                    <p class="mb-2 mt-1">{{ translate('The cryptographic keys for this domain could not be generated automatically. Click the button below to try again.') }}</p>
                    <form action="{{ route('admin.gateway.sending-domain.regenerate', $domain->uid) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="i-btn btn--primary btn--sm">
                            <i class="ri-key-2-line"></i> {{ translate("Generate DKIM Keys") }}
                        </button>
                    </form>
                </div>
            </div>

            {{-- Server Diagnostic Info --}}
            @if($opensslCheck)
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="card-header-left">
                            <h4 class="card-title"><i class="ri-server-line me-1"></i> {{ translate("Server Diagnostic") }}</h4>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <tbody>
                                    <tr>
                                        <td class="fw-semibold" style="width: 250px;">{{ translate("OpenSSL Extension") }}</td>
                                        <td>
                                            @if($opensslCheck['openssl_loaded'])
                                                <span class="i-badge success-soft pill"><i class="ri-check-line"></i> {{ translate("Installed") }}</span>
                                            @else
                                                <span class="i-badge danger-soft pill"><i class="ri-close-line"></i> {{ translate("Not Installed") }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @if($opensslCheck['openssl_version'])
                                        <tr>
                                            <td class="fw-semibold">{{ translate("OpenSSL Version") }}</td>
                                            <td>{{ $opensslCheck['openssl_version'] }}</td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <td class="fw-semibold">{{ translate("Config File (openssl.cnf)") }}</td>
                                        <td>
                                            @if($opensslCheck['config_path'])
                                                <span class="i-badge success-soft pill"><i class="ri-check-line"></i> {{ translate("Found") }}</span>
                                                <small class="text-muted ms-2">{{ $opensslCheck['config_path'] }}</small>
                                            @else
                                                <span class="i-badge danger-soft pill"><i class="ri-close-line"></i> {{ translate("Not Found") }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="fw-semibold">{{ translate("Key Generation Test") }}</td>
                                        <td>
                                            @if($opensslCheck['ready'])
                                                <span class="i-badge success-soft pill"><i class="ri-check-line"></i> {{ translate("Working") }}</span>
                                            @else
                                                <span class="i-badge danger-soft pill"><i class="ri-close-line"></i> {{ translate("Failed") }}</span>
                                                @if($opensslCheck['error'])
                                                    <br><small class="text-danger">{{ $opensslCheck['error'] }}</small>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        @if(!$opensslCheck['ready'])
                            <div class="alert alert-warning mt-3 mb-0" role="alert">
                                <strong><i class="ri-lightbulb-line"></i> {{ translate("How to fix") }}</strong>
                                <ul class="mb-0 mt-2">
                                    @if(!$opensslCheck['openssl_loaded'])
                                        <li>{{ translate("Enable the OpenSSL extension in your php.ini file: uncomment") }} <code>extension=openssl</code></li>
                                        <li>{{ translate("Restart your web server (Apache/Nginx) after making changes") }}</li>
                                    @elseif(!$opensslCheck['config_path'])
                                        <li>{{ translate("The openssl.cnf file could not be found on your server") }}</li>
                                        <li>{{ translate("Set the OPENSSL_CONF environment variable to point to your openssl.cnf file") }}</li>
                                        <li>{{ translate("On Linux:") }} <code>export OPENSSL_CONF=/etc/ssl/openssl.cnf</code></li>
                                        <li>{{ translate("On Windows (Laragon): The file is typically at") }} <code>php/extras/ssl/openssl.cnf</code></li>
                                    @else
                                        <li>{{ translate("OpenSSL is installed and config file was found, but key generation still failed") }}</li>
                                        <li>{{ translate("Check your server error logs for more details") }}</li>
                                        <li>{{ translate("Ensure your PHP process has write permissions and sufficient memory") }}</li>
                                    @endif
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        @else
            @if(!$allVerified)
                <div class="alert alert-primary d-flex align-items-start gap-3 mb-4" role="alert">
                    <i class="ri-list-ordered fs-4 mt-1"></i>
                    <div>
                        <strong>{{ translate('Setup Steps') }}</strong>
                        <ol class="mb-0 mt-1 ps-3">
                            <li>{{ translate('Copy the DNS records below and add them to your domain\'s DNS settings (usually in your domain registrar or hosting panel)') }}</li>
                            <li>{{ translate('Wait for DNS propagation (can take up to 24-48 hours, but usually minutes)') }}</li>
                            <li>{{ translate('Click "Verify DNS Records" above to confirm everything is configured') }}</li>
                        </ol>
                    </div>
                </div>
            @endif

            {{-- Verification Status --}}
            <div class="row mb-4 g-3">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <div class="mb-2">
                                @if($dnsRecords['dkim']['verified'])
                                    <i class="ri-checkbox-circle-fill text-success fs-1"></i>
                                @else
                                    <i class="ri-time-line text-warning fs-1"></i>
                                @endif
                            </div>
                            <h6>{{ translate("DKIM") }}</h6>
                            <small class="text-muted">{{ translate("Email Signing") }}</small>
                            <div class="mt-2">
                                <span class="i-badge {{ $dnsRecords['dkim']['verified'] ? 'success' : 'warning' }}-soft pill" id="dkim-status">
                                    {{ $dnsRecords['dkim']['verified'] ? translate('Verified') : translate('Awaiting Setup') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <div class="mb-2">
                                @if($dnsRecords['spf']['verified'])
                                    <i class="ri-checkbox-circle-fill text-success fs-1"></i>
                                @else
                                    <i class="ri-time-line text-warning fs-1"></i>
                                @endif
                            </div>
                            <h6>{{ translate("SPF") }}</h6>
                            <small class="text-muted">{{ translate("Sender Authorization") }}</small>
                            <div class="mt-2">
                                <span class="i-badge {{ $dnsRecords['spf']['verified'] ? 'success' : 'warning' }}-soft pill" id="spf-status">
                                    {{ $dnsRecords['spf']['verified'] ? translate('Verified') : translate('Awaiting Setup') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <div class="mb-2">
                                @if($dnsRecords['dmarc']['verified'])
                                    <i class="ri-checkbox-circle-fill text-success fs-1"></i>
                                @else
                                    <i class="ri-time-line text-warning fs-1"></i>
                                @endif
                            </div>
                            <h6>{{ translate("DMARC") }}</h6>
                            <small class="text-muted">{{ translate("Delivery Policy") }}</small>
                            <div class="mt-2">
                                <span class="i-badge {{ $dnsRecords['dmarc']['verified'] ? 'success' : 'warning' }}-soft pill" id="dmarc-status">
                                    {{ $dnsRecords['dmarc']['verified'] ? translate('Verified') : translate('Awaiting Setup') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- DNS Records --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-header-left">
                        <h4 class="card-title">{{ translate("DNS Records to Configure") }}</h4>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">{{ translate("Add the following TXT records in your domain's DNS settings. You can find DNS settings in your domain registrar (GoDaddy, Namecheap, Cloudflare, etc).") }}</p>

                    {{-- DKIM Record --}}
                    <div class="card mb-3 {{ $dnsRecords['dkim']['verified'] ? 'border-success' : '' }}">
                        <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
                            <h6 class="mb-0">
                                <i class="ri-shield-keyhole-line text-primary me-1"></i>
                                {{ translate("DKIM Record") }}
                                <span class="i-badge danger-soft pill ms-2">{{ translate("Required") }}</span>
                            </h6>
                            @if($dnsRecords['dkim']['verified'])
                                <span class="i-badge success-soft pill"><i class="ri-check-line"></i> {{ translate("Verified") }}</span>
                            @endif
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3" style="font-size: 13px;">{{ translate("DKIM cryptographically signs your emails so recipients can verify they genuinely came from your domain. This is required for email delivery.") }}</p>

                            <div class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">{{ translate("Type") }}</label>
                                    <div class="form-control bg-light">TXT</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">{{ translate("Hostname / Name") }}</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control bg-light" value="{{ $dnsRecords['dkim']['hostname'] }}" readonly />
                                        <button class="btn btn-outline-secondary copy-btn" type="button" data-copy="{{ $dnsRecords['dkim']['hostname'] }}">
                                            <i class="ri-file-copy-line"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">{{ translate("Value / Content") }}</label>

                                    @if($dnsRecords['dkim']['needs_split'])
                                        {{-- Format tabs for long DKIM keys --}}
                                        <ul class="nav nav-tabs nav-tabs-sm mb-2" role="tablist" style="font-size: 12px;">
                                            <li class="nav-item">
                                                <button class="nav-link active py-1 px-2" data-bs-toggle="tab" data-bs-target="#dkim-standard" type="button">
                                                    {{ translate("Standard") }}
                                                </button>
                                            </li>
                                            <li class="nav-item">
                                                <button class="nav-link py-1 px-2" data-bs-toggle="tab" data-bs-target="#dkim-split" type="button">
                                                    {{ translate("Split Format") }}
                                                    <span class="i-badge warning-soft pill ms-1" style="font-size: 10px;">{{ translate("For 255-char limit") }}</span>
                                                </button>
                                            </li>
                                        </ul>
                                        <div class="tab-content">
                                            <div class="tab-pane fade show active" id="dkim-standard">
                                                <div class="input-group">
                                                    <textarea class="form-control bg-light" rows="3" readonly>{{ $dnsRecords['dkim']['value'] }}</textarea>
                                                    <button class="btn btn-outline-secondary copy-btn" type="button" data-copy="{{ $dnsRecords['dkim']['value'] }}">
                                                        <i class="ri-file-copy-line"></i>
                                                    </button>
                                                </div>
                                                <small class="text-muted mt-1 d-block">{{ translate("Use this format for Cloudflare, GoDaddy, Namecheap, and most modern DNS providers.") }}</small>
                                            </div>
                                            <div class="tab-pane fade" id="dkim-split">
                                                <div class="input-group">
                                                    <textarea class="form-control bg-light" rows="4" readonly>{{ $dnsRecords['dkim']['value_split'] }}</textarea>
                                                    <button class="btn btn-outline-secondary copy-btn" type="button" data-copy="{{ $dnsRecords['dkim']['value_split'] }}">
                                                        <i class="ri-file-copy-line"></i>
                                                    </button>
                                                </div>
                                                <small class="text-muted mt-1 d-block">{{ translate("Use this format if your DNS provider rejects the standard value due to a 255-character limit (common with cPanel DNS, some registrars).") }}</small>
                                            </div>
                                        </div>
                                    @else
                                        <div class="input-group">
                                            <textarea class="form-control bg-light" rows="3" readonly>{{ $dnsRecords['dkim']['value'] }}</textarea>
                                            <button class="btn btn-outline-secondary copy-btn" type="button" data-copy="{{ $dnsRecords['dkim']['value'] }}">
                                                <i class="ri-file-copy-line"></i>
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @if($dnsRecords['dkim']['needs_split'])
                                <div class="alert alert-warning d-flex align-items-start gap-2 mt-3 mb-0 py-2 px-3" role="alert" style="font-size: 13px;">
                                    <i class="ri-information-line mt-1 flex-shrink-0"></i>
                                    <div>
                                        <strong>{{ translate("Long DKIM Key") }}</strong> ({{ $dnsRecords['dkim']['value_length'] }} {{ translate("characters") }})
                                        <br>{{ translate("This DKIM key exceeds 255 characters. Most DNS providers (Cloudflare, GoDaddy, Namecheap) handle this automatically. If your provider rejects it, use the \"Split Format\" tab above.") }}
                                    </div>
                                </div>
                            @endif

                            {{-- DKIM Selector Information --}}
                            <div class="alert alert-info d-flex align-items-start gap-2 mt-3 mb-0 py-2 px-3" role="alert" style="font-size: 13px;">
                                <i class="ri-lightbulb-line mt-1 flex-shrink-0"></i>
                                <div>
                                    <strong>{{ translate("About DKIM Selector") }}</strong>
                                    <br>{{ translate("This application uses the selector") }} <code>{{ $dnsRecords['dkim']['selector'] }}</code>{{ translate(", which is independent from your server's default DKIM key (if any). Both can coexist without conflict. Your server may already sign emails with its own DKIM selector (e.g., \"default\") — this is normal and both signatures are valid per email standards (RFC 6376).") }}
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- SPF Record --}}
                    <div class="card mb-3 {{ $dnsRecords['spf']['verified'] ? 'border-success' : '' }}">
                        <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
                            <h6 class="mb-0">
                                <i class="ri-mail-check-line text-info me-1"></i>
                                {{ translate("SPF Record") }}
                                <span class="i-badge info-soft pill ms-2">{{ translate("Recommended") }}</span>
                            </h6>
                            @if($dnsRecords['spf']['verified'])
                                <span class="i-badge success-soft pill"><i class="ri-check-line"></i> {{ translate("Verified") }}</span>
                            @endif
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3" style="font-size: 13px;">{{ translate("SPF tells email providers which servers are authorized to send emails from your domain. If you already have an SPF record, merge the include statement into your existing record.") }}</p>
                            <div class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">{{ translate("Type") }}</label>
                                    <div class="form-control bg-light">TXT</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">{{ translate("Hostname / Name") }}</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control bg-light" value="{{ $dnsRecords['spf']['hostname'] }}" readonly />
                                        <button class="btn btn-outline-secondary copy-btn" type="button" data-copy="{{ $dnsRecords['spf']['hostname'] }}">
                                            <i class="ri-file-copy-line"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">{{ translate("Value / Content") }}</label>
                                    <div class="input-group">
                                        <textarea class="form-control bg-light" rows="2" readonly>{{ $dnsRecords['spf']['value'] }}</textarea>
                                        <button class="btn btn-outline-secondary copy-btn" type="button" data-copy="{{ $dnsRecords['spf']['value'] }}">
                                            <i class="ri-file-copy-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- DMARC Record --}}
                    <div class="card mb-3 {{ $dnsRecords['dmarc']['verified'] ? 'border-success' : '' }}">
                        <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
                            <h6 class="mb-0">
                                <i class="ri-lock-line text-info me-1"></i>
                                {{ translate("DMARC Record") }}
                                <span class="i-badge info-soft pill ms-2">{{ translate("Recommended") }}</span>
                            </h6>
                            @if($dnsRecords['dmarc']['verified'])
                                <span class="i-badge success-soft pill"><i class="ri-check-line"></i> {{ translate("Verified") }}</span>
                            @endif
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3" style="font-size: 13px;">{{ translate("DMARC tells email providers what to do when emails fail authentication. Start with 'p=none' (monitoring mode) and tighten later.") }}</p>
                            <div class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold">{{ translate("Type") }}</label>
                                    <div class="form-control bg-light">TXT</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">{{ translate("Hostname / Name") }}</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control bg-light" value="{{ $dnsRecords['dmarc']['hostname'] }}" readonly />
                                        <button class="btn btn-outline-secondary copy-btn" type="button" data-copy="{{ $dnsRecords['dmarc']['hostname'] }}">
                                            <i class="ri-file-copy-line"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">{{ translate("Value / Content") }}</label>
                                    <div class="input-group">
                                        <textarea class="form-control bg-light" rows="2" readonly>{{ $dnsRecords['dmarc']['value'] }}</textarea>
                                        <button class="btn btn-outline-secondary copy-btn" type="button" data-copy="{{ $dnsRecords['dmarc']['value'] }}">
                                            <i class="ri-file-copy-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Verification Messages --}}
            <div id="verificationMessages" class="mt-3" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">{{ translate("Verification Results") }}</h5>
                    </div>
                    <div class="card-body">
                        <ul id="messageList" class="list-unstyled mb-0"></ul>
                    </div>
                </div>
            </div>
        @endif
    </div>
</main>

@endsection

@section('modal')
<div class="modal fade actionModal" id="regenerateKeysModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header text-start">
                <span class="action-icon danger">
                    <i class="bi bi-exclamation-circle"></i>
                </span>
            </div>
            <form action="{{ route('admin.gateway.sending-domain.regenerate', $domain->uid) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="action-message">
                        <h5>{{ translate("Regenerate DKIM Keys?") }}</h5>
                        <p>{{ translate("This will generate new cryptographic keys. You will need to update the DNS records at your domain registrar after regeneration.") }}</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="i-btn btn--dark outline btn--lg" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
                    <button type="submit" class="i-btn btn--warning btn--lg">{{ translate("Regenerate Keys") }}</button>
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

    $(document).on('click', '.copy-btn', function() {
        var text = $(this).data('copy');
        if (!text) return;
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        notify('success', '{{ translate("Copied to clipboard") }}');
    });

    $('#verifyDnsBtn').on('click', function() {
        var btn = $(this);
        var url = btn.data('url');

        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> {{ translate("Verifying...") }}');

        $.ajax({
            method: 'POST',
            url: url,
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            dataType: 'json'
        }).then(function(response) {
            btn.prop('disabled', false).html('<i class="ri-shield-check-line"></i> {{ translate("Verify DNS Records") }}');

            updateBadge('#dkim-status', response.dkim, '{{ translate("Verified") }}', '{{ translate("Awaiting Setup") }}');
            updateBadge('#spf-status', response.spf, '{{ translate("Verified") }}', '{{ translate("Awaiting Setup") }}');
            updateBadge('#dmarc-status', response.dmarc, '{{ translate("Verified") }}', '{{ translate("Awaiting Setup") }}');

            if (response.messages && response.messages.length > 0) {
                var msgList = $('#messageList');
                msgList.empty();
                response.messages.forEach(function(msg) {
                    msgList.append('<li class="mb-1"><i class="ri-arrow-right-s-line"></i> ' + msg + '</li>');
                });
                $('#verificationMessages').slideDown();
            }

            if (response.dkim) {
                notify('success', response.message);
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                notify('info', response.message);
            }
        }).fail(function() {
            btn.prop('disabled', false).html('<i class="ri-shield-check-line"></i> {{ translate("Verify DNS Records") }}');
            notify('error', '{{ translate("Verification request failed") }}');
        });
    });

    function updateBadge(selector, verified, verifiedText, notVerifiedText) {
        var el = $(selector);
        el.removeClass('success-soft danger-soft warning-soft');
        if (verified) {
            el.addClass('success-soft').text(verifiedText);
        } else {
            el.addClass('warning-soft').text(notVerifiedText);
        }
    }

})(jQuery);
</script>
@endpush

@extends('user.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-fluid">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div class="page-header-left">
                <h3 class="mb-1">{{ translate("Add Mailbox to Warmup") }}</h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('user.dashboard') }}">{{ translate("Dashboard") }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('user.warmup.index') }}">{{ translate("Email Warmup") }}</a></li>
                        <li class="breadcrumb-item active">{{ translate("Add Mailbox") }}</li>
                    </ol>
                </nav>
            </div>
            <div class="page-header-right">
                <a href="{{ route('user.warmup.index') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-arrow-left-line me-1"></i> {{ translate("Back to List") }}
                </a>
            </div>
        </div>

        @if($eligibleGateways->isEmpty())
            <div class="card p-5 text-center">
                <i class="ri-mail-close-line fs-1 text-warning mb-3 d-block"></i>
                <h4>{{ translate("No Active Email Accounts Found") }}</h4>
                <p class="text-muted mb-4">
                    {{ translate("You must configure at least one active Email Gateway before starting warmup. Your existing credentials will be reused securely.") }}
                </p>
                <div>
                    <a href="{{ route('user.gateway.email.index') }}" class="i-btn btn--primary btn--md">
                        <i class="ri-add-line me-1"></i> {{ translate("Configure Email Gateway First") }}
                    </a>
                </div>
            </div>
        @else
            <form action="{{ route('user.warmup.store') }}" method="POST" id="warmupForm">
                @csrf
                <div class="row g-4">
                    {{-- Left Column: Mailbox Selection & Schedule Parameters --}}
                    <div class="col-lg-7">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h4 class="card-title">{{ translate("1. Select Configured Email Account") }}</h4>
                                <p class="text-muted fs-13 mb-0">{{ translate("Reuses your existing SMTP/API gateway configuration. Zero duplicate setup.") }}</p>
                            </div>
                            <div class="card-body">
                                <div class="mb-4">
                                    <label class="form-label required">{{ translate("Configured Mailbox") }}</label>
                                    <select name="gateway_id" id="gatewaySelect" class="form-select" required>
                                        <option value="" disabled selected>{{ translate("Select an existing email account...") }}</option>
                                        @foreach($eligibleGateways as $gw)
                                            <option value="{{ $gw->id }}" 
                                                    data-has-imap="{{ $gw->has_imap ? '1' : '0' }}"
                                                    data-address="{{ $gw->address }}"
                                                    data-type="{{ strtoupper($gw->type) }}"
                                                    {{ old('gateway_id') == $gw->id ? 'selected' : '' }}>
                                                {{ $gw->name }} &lt;{{ $gw->address }}&gt; [{{ strtoupper($gw->type) }}] 
                                                {{ $gw->has_imap ? '— ' . translate('IMAP Configured') : '— ' . translate('IMAP Needed') }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">
                                        <i class="ri-shield-check-line text-success me-1"></i>
                                        {{ translate("Your saved SMTP credentials will be reused automatically. You will never be asked to enter them twice.") }}
                                    </div>
                                </div>

                                <div class="p-3 bg-light rounded-3 mb-2" id="gatewayDetailsBox" style="display: none;">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <span class="fw-semibold" id="gwNameLabel"></span>
                                            <small class="text-muted d-block" id="gwAddressLabel"></small>
                                        </div>
                                        <span id="gwImapBadge" class="badge"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Warmup Limits & Schedule --}}
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">{{ translate("2. Warmup Schedule & Daily Limits") }}</h4>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label required">{{ translate("Starting Quota (Day 1)") }}</label>
                                        <input type="number" name="starting_quota" class="form-control" value="{{ old('starting_quota', 5) }}" min="1" max="50" required>
                                        <small class="text-muted">{{ translate("Initial volume on day one.") }}</small>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label required">{{ translate("Daily Ramp-up Increment") }}</label>
                                        <input type="number" name="rampup_increment" class="form-control" value="{{ old('rampup_increment', 2) }}" min="1" max="20" required>
                                        <small class="text-muted">{{ translate("Emails added per day.") }}</small>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label required">{{ translate("Maximum Daily Target Limit") }}</label>
                                        <input type="number" name="daily_limit" class="form-control" value="{{ old('daily_limit', 40) }}" min="5" max="500" required>
                                        <small class="text-muted">{{ translate("Target steady-state daily emails.") }}</small>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">{{ translate("Peer Reply Target Weight (%)") }}</label>
                                        <input type="number" name="reply_rate_percent" class="form-control" value="{{ old('reply_rate_percent', 30) }}" min="0" max="100">
                                        <small class="text-muted">{{ translate("Weight for reciprocal peer exchanges. No artificial or fake replies.") }}</small>
                                    </div>

                                    <div class="col-md-12">
                                        <label class="form-label">{{ translate("Start Date") }}</label>
                                        <input type="date" name="start_date" class="form-control" value="{{ old('start_date', date('Y-m-d')) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Right Column: Inbound Verification (IMAP) --}}
                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">{{ translate("3. Inbound IMAP Verification") }}</h4>
                                <p class="text-muted fs-13 mb-0">{{ translate("Required to verify inbox placement and remove test messages from Spam/Junk.") }}</p>
                            </div>
                            <div class="card-body">
                                <div id="imapAlreadyConfiguredAlert" class="alert alert-success d-flex align-items-center mb-3" style="display: none;">
                                    <i class="ri-checkbox-circle-fill fs-4 me-2"></i>
                                    <div>
                                        <strong>{{ translate("IMAP Already Verified") }}</strong>
                                        <div class="fs-13">{{ translate("This email account already has active IMAP settings saved. No additional input needed.") }}</div>
                                    </div>
                                </div>

                                <div id="imapFormSection">
                                    <p class="text-muted fs-13 mb-3">
                                        {{ translate("Voltmail needs IMAP access to check inbox delivery and verify your sender reputation. Fill only this missing information:") }}
                                    </p>

                                    <div class="mb-3">
                                        <label class="form-label">{{ translate("IMAP Server Host") }}</label>
                                        <input type="text" name="imap_host" id="imapHost" class="form-control" value="{{ old('imap_host') }}" placeholder="{{ translate('e.g. imap.mailtrap.io or imap.gmail.com') }}">
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label">{{ translate("IMAP Port") }}</label>
                                            <input type="number" name="imap_port" class="form-control" value="{{ old('imap_port', 993) }}">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">{{ translate("Encryption") }}</label>
                                            <select name="imap_encryption" class="form-select">
                                                <option value="ssl" {{ old('imap_encryption') == 'ssl' ? 'selected' : '' }}>SSL (Port 993)</option>
                                                <option value="tls" {{ old('imap_encryption') == 'tls' ? 'selected' : '' }}>TLS / STARTTLS</option>
                                                <option value="none" {{ old('imap_encryption') == 'none' ? 'selected' : '' }}>None</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">{{ translate("IMAP Username") }}</label>
                                        <input type="text" name="imap_username" id="imapUsername" class="form-control" value="{{ old('imap_username') }}" placeholder="{{ translate('user@domain.com') }}">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">{{ translate("IMAP Password / App Password") }}</label>
                                        <input type="password" name="imap_password" class="form-control" placeholder="{{ translate('••••••••••••') }}">
                                        <small class="text-muted">{{ translate("Encrypted securely using Laravel AES-256 GCM before saving.") }}</small>
                                    </div>
                                </div>

                                <div class="d-grid mt-4">
                                    <button type="submit" class="i-btn btn--primary btn--lg">
                                        <i class="ri-fire-fill me-1"></i> {{ translate("Activate Warmup Schedule") }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        @endif
    </div>
</main>

@push('script-push')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const gatewaySelect = document.getElementById('gatewaySelect');
        const detailsBox    = document.getElementById('gatewayDetailsBox');
        const nameLabel     = document.getElementById('gwNameLabel');
        const addressLabel  = document.getElementById('gwAddressLabel');
        const imapBadge     = document.getElementById('gwImapBadge');
        const imapAlert     = document.getElementById('imapAlreadyConfiguredAlert');
        const imapSection   = document.getElementById('imapFormSection');

        function updateSelection() {
            if (!gatewaySelect) return;
            const selected = gatewaySelect.options[gatewaySelect.selectedIndex];
            if (!selected || !selected.value) {
                if (detailsBox) detailsBox.style.display = 'none';
                return;
            }

            const hasImap = selected.getAttribute('data-has-imap') === '1';
            const address = selected.getAttribute('data-address');
            const type    = selected.getAttribute('data-type');

            detailsBox.style.display = 'block';
            nameLabel.textContent = selected.text.split('—')[0].trim();
            addressLabel.textContent = address + ' (' + type + ')';

            if (hasImap) {
                imapBadge.className = 'badge bg-success text-white';
                imapBadge.textContent = '{{ translate("IMAP Ready") }}';
                imapAlert.style.display = 'flex';
            } else {
                imapBadge.className = 'badge bg-warning text-dark';
                imapBadge.textContent = '{{ translate("IMAP Required") }}';
                imapAlert.style.display = 'none';
            }
        }

        if (gatewaySelect) {
            gatewaySelect.addEventListener('change', updateSelection);
            updateSelection();
        }
    });
</script>
@endpush

@endsection

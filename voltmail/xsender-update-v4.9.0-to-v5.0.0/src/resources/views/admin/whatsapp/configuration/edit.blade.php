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
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Dashboard') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.configuration.index') }}">{{ translate('WhatsApp Configurations') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ translate('Edit') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                <button type="button" class="i-btn btn--success btn--md test-connection" data-uid="{{ $configuration->uid }}">
                    <i class="ri-wifi-line"></i> {{ translate('Test Connection') }}
                </button>
            </div>
        </div>

        <div class="alert alert-warning d-flex align-items-start gap-3 mb-4" role="alert">
            <i class="ri-information-line fs-22 mt-1 flex-shrink-0"></i>
            <div>
                <strong>{{ translate('Two ways to use WhatsApp Cloud API') }}</strong>
                <p class="mb-1 mt-1">
                    <strong>{{ translate('Tech Provider / BSP') }}</strong> {{ translate('(Embedded Signup, resell to your clients) — requires Meta-approved Tech Provider status.') }}
                    <br>
                    <strong>{{ translate('Manual Setup / Single WABA') }}</strong> {{ translate('(your own number, no Meta approval) — paste a long-lived System User Access Token below.') }}
                </p>
                <small class="text-muted">{{ translate('Seeing') }} <code>"Embedded signup is only available for BSPs or TPs"</code>? {{ translate('Your app is not Tech Provider approved — switch to Manual Setup.') }}</small>
            </div>
        </div>

        <form action="{{ route('admin.whatsapp.configuration.update', $configuration->uid) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row g-4">
                <div class="col-xxl-8 col-xl-8">
                    {{-- Basic Configuration --}}
                    <div class="card mb-4">
                        <div class="card-header">
                            <div class="card-header-left">
                                <h4 class="card-title">{{ translate('Basic Configuration') }}</h4>
                            </div>
                            <div class="card-header-right">
                                @if($configuration->is_default)
                                    <span class="i-badge primary-solid pill">{{ translate('Default') }}</span>
                                @endif
                                <span class="i-badge {{ $configuration->status == 'active' ? 'success-solid' : 'secondary-solid' }} pill">
                                    {{ ucfirst($configuration->status) }}
                                </span>
                                {{-- Live connection status pill — populated by connection-status.js probe on page load.
                                     Renders as one of: checking / connected / degraded / broken so the admin can
                                     see instantly whether the credentials on this page actually work with Meta. --}}
                                <span class="i-badge secondary-solid pill" id="connectionStatusPill" data-uid="{{ $configuration->uid }}">
                                    <i class="ri-loader-4-line ri-spin"></i> {{ translate('Checking…') }}
                                </span>
                            </div>
                        </div>

                        <div class="card-body pt-0">
                            <div class="form-element">
                                <div class="row gy-4">
                                    <div class="col-xxl-2 col-xl-3">
                                        <h5 class="form-element-title">{{ translate('App Details') }}</h5>
                                    </div>
                                    <div class="col-xxl-10 col-xl-9">
                                        <div class="row gy-4">
                                            <div class="col-md-6">
                                                <div class="form-inner">
                                                    <label for="name" class="form-label">{{ translate('Configuration Name') }} <small class="text-danger">*</small></label>
                                                    <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $configuration->name) }}" required>
                                                    <p class="form-element-note">{{ translate('A friendly name to identify this configuration') }}</p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-inner">
                                                    <label for="environment" class="form-label">{{ translate('Environment') }} <small class="text-danger">*</small></label>
                                                    <select name="environment" id="environment" class="form-select" required>
                                                        <option value="production" {{ $configuration->environment == 'production' ? 'selected' : '' }}>{{ translate('Production') }}</option>
                                                        <option value="sandbox" {{ $configuration->environment == 'sandbox' ? 'selected' : '' }}>{{ translate('Sandbox') }}</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-inner">
                                                    <label for="app_id" class="form-label">{{ translate('Meta App ID') }} <small class="text-danger">*</small></label>
                                                    <input type="text" id="app_id" name="app_id" class="form-control" value="{{ old('app_id', $configuration->app_id) }}" required>
                                                    <p class="form-element-note">{{ translate('Found in Meta Developer Portal > Your App > Settings > Basic') }}</p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-inner">
                                                    <label for="app_secret" class="form-label d-flex align-items-center gap-2">
                                                        {{ translate('Meta App Secret') }}
                                                        @if(!empty($configuration->app_secret))
                                                            <span class="badge bg-success-subtle text-success">
                                                                <i class="ri-shield-check-line"></i> {{ translate('Saved') }}
                                                            </span>
                                                        @else
                                                            <span class="badge bg-warning-subtle text-warning">
                                                                <i class="ri-error-warning-line"></i> {{ translate('Not set') }}
                                                            </span>
                                                        @endif
                                                    </label>
                                                    <div class="input-group">
                                                        <input type="password" id="app_secret" name="app_secret" class="form-control" placeholder="{{ !empty($configuration->app_secret) ? '••••••••••••  ' . translate('(leave empty to keep current)') : translate('Enter your Meta App Secret') }}">
                                                        <span class="input-group-text pointer toggle-password" data-target="app_secret">
                                                            <i class="ri-eye-line"></i>
                                                        </span>
                                                    </div>
                                                    <p class="form-element-note">
                                                        @if(!empty($configuration->app_secret))
                                                            {{ translate('Your secret is saved and encrypted. Field is intentionally blank — type a new value only if you want to replace it.') }}
                                                        @else
                                                            {{ translate('Found in Meta Developer Portal > Your App > Settings > Basic') }}
                                                        @endif
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-inner">
                                                    <label for="api_version" class="form-label">{{ translate('API Version') }} <small class="text-danger">*</small></label>
                                                    <select name="api_version" id="api_version" class="form-select" required>
                                                        <option value="v26.0" {{ $configuration->api_version == 'v26.0' ? 'selected' : '' }}>v26.0 ({{ translate('Latest') }})</option>
                                                        <option value="v25.0" {{ $configuration->api_version == 'v25.0' ? 'selected' : '' }}>v25.0</option>
                                                        <option value="v24.0" {{ $configuration->api_version == 'v24.0' ? 'selected' : '' }}>v24.0</option>
                                                        <option value="v23.0" {{ $configuration->api_version == 'v23.0' ? 'selected' : '' }}>v23.0</option>
                                                    </select>
                                                    <p class="form-element-note">{{ translate('Meta rolls a new Graph API version every 3 months. Old versions stay supported for ~24 months.') }}</p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-inner">
                                                    <label class="form-label">{{ translate('Set as Default') }}</label>
                                                    <div class="form-check form-switch mt-2">
                                                        <input type="checkbox" name="is_default" value="1" class="form-check-input" id="is_default" {{ $configuration->is_default ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="is_default">{{ translate('Use as default configuration') }}</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-element">
                                <div class="row gy-4">
                                    <div class="col-xxl-2 col-xl-3">
                                        <h5 class="form-element-title">{{ translate('Embedded Signup') }}</h5>
                                        <p class="form-element-note">{{ translate('Configuration for WhatsApp Business onboarding') }}</p>
                                    </div>
                                    <div class="col-xxl-10 col-xl-9">
                                        @if(!$configuration->config_id)
                                        <div class="note-container mb-4">
                                            <div class="note note--warning">
                                                <div class="note-body">
                                                    <div class="note-icon">
                                                        <i class="ri-error-warning-line"></i>
                                                    </div>
                                                    <div class="note-content">
                                                        <h6 class="note-title">{{ translate('Configuration ID Missing') }}</h6>
                                                        <p class="note-text">
                                                            {{ translate('Add Configuration ID to enable Embedded Signup for seamless WhatsApp Business onboarding.') }}
                                                            <a href="https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/overview" target="_blank">{{ translate('Learn more') }} <i class="ri-external-link-line"></i></a>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @else
                                        <div class="note-container mb-4">
                                            <div class="note note--success">
                                                <div class="note-body">
                                                    <div class="note-icon">
                                                        <i class="ri-checkbox-circle-line"></i>
                                                    </div>
                                                    <div class="note-content">
                                                        <p class="note-text mb-0">{{ translate('Embedded Signup is fully configured and ready to use.') }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif

                                        <div class="row gy-4">
                                            <div class="col-md-6">
                                                <div class="form-inner">
                                                    <label for="config_id" class="form-label">{{ translate('Configuration ID') }} <small class="text-danger">*</small></label>
                                                    <input type="text" id="config_id" name="config_id" class="form-control" value="{{ old('config_id', $configuration->config_id) }}" placeholder="{{ translate('e.g., 123456789012345') }}">
                                                    <p class="form-element-note">{{ translate('Required for Embedded Signup. Found in Meta Business Settings > WhatsApp > Embedded Signup') }}</p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-inner">
                                                    <label for="solution_id" class="form-label">{{ translate('Solution ID') }}</label>
                                                    <input type="text" id="solution_id" name="solution_id" class="form-control" value="{{ old('solution_id', $configuration->solution_id) }}" placeholder="{{ translate('Optional - for verified solutions') }}">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-inner">
                                                    <label for="business_manager_id" class="form-label">{{ translate('Business Manager ID') }}</label>
                                                    <input type="text" id="business_manager_id" name="business_manager_id" class="form-control" value="{{ old('business_manager_id', $configuration->business_manager_id) }}" placeholder="{{ translate('Your Meta Business Manager ID') }}">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-inner">
                                                    <label for="tech_provider_id" class="form-label">{{ translate('Tech Provider ID') }}</label>
                                                    <input type="text" id="tech_provider_id" name="tech_provider_id" class="form-control" value="{{ old('tech_provider_id', $configuration->tech_provider_id) }}" placeholder="{{ translate('Tech Provider Business ID') }}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-element">
                                <div class="row gy-4">
                                    <div class="col-xxl-2 col-xl-3">
                                        <h5 class="form-element-title">{{ translate('Manual Setup (Single WABA)') }}</h5>
                                        <p class="form-element-note">{{ translate('Use this if your Meta App is NOT a Tech Provider. Connects your own WhatsApp Business Account directly — no Meta approval needed.') }}</p>
                                    </div>
                                    <div class="col-xxl-10 col-xl-9">
                                        @if($configuration->system_user_token)
                                            <div class="alert alert-success py-2 mb-3" role="alert">
                                                <small>
                                                    <i class="ri-checkbox-circle-line"></i>
                                                    <strong>{{ translate('System User token is configured.') }}</strong>
                                                    @if($configuration->system_user_token_expires_at)
                                                        {{ translate('Expires:') }} {{ $configuration->system_user_token_expires_at->format('M d, Y H:i') }}.
                                                    @else
                                                        {{ translate('Long-lived (no expiration).') }}
                                                    @endif
                                                    {{ translate('Leave the token field blank to keep the existing value, or paste a new token to replace it.') }}
                                                </small>
                                            </div>
                                        @endif
                                        <div class="row gy-4">
                                            <div class="col-md-12">
                                                <div class="form-inner">
                                                    <label for="system_user_token" class="form-label">{{ translate('System User Access Token') }}</label>
                                                    <div class="input-group">
                                                        <input type="password" id="system_user_token" name="system_user_token" class="form-control" placeholder="{{ $configuration->system_user_token ? translate('Leave blank to keep existing token') : translate('Paste long-lived System User token (EAAB...)') }}" autocomplete="new-password">
                                                        <span class="input-group-text pointer toggle-password" data-target="system_user_token">
                                                            <i class="ri-eye-line"></i>
                                                        </span>
                                                    </div>
                                                    <p class="form-element-note">{{ translate('Encrypted before storage. Long-lived tokens start with') }} <code>EAAB</code>{{ translate('. Generate at business.facebook.com → Settings → Users → System Users → Add → Generate New Token.') }}</p>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="form-inner">
                                                    <label for="system_user_id" class="form-label">{{ translate('System User ID') }}</label>
                                                    <input type="text" id="system_user_id" name="system_user_id" class="form-control" value="{{ old('system_user_id', $configuration->system_user_id) }}" placeholder="{{ translate('Numeric ID of the System User') }}">
                                                    <p class="form-element-note">{{ translate('Found next to the System User name in Business Manager') }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-4">
                                <div class="col-xxl-10 col-xl-9 offset-xxl-2 offset-xl-3">
                                    <div class="form-action justify-content-end">
                                        <a href="{{ route('admin.whatsapp.configuration.index') }}" class="i-btn btn--danger outline btn--md">{{ translate('Cancel') }}</a>
                                        <button type="submit" class="i-btn btn--primary btn--md">{{ translate('Update Configuration') }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-4 col-xl-4">
                    {{-- Meta Developer Setup Card --}}
                    <div class="card mb-4">
                        <div class="card-header">
                            <div class="card-header-left">
                                <h4 class="card-title">{{ translate('Meta Developer Setup') }}</h4>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="meta-setup-compact">
                                {{-- Webhook URL --}}
                                <div class="setup-item">
                                    <div class="setup-item-header">
                                        <span class="setup-badge webhook"><i class="ri-links-line"></i></span>
                                        <span class="setup-label">{{ translate('Webhook Callback URL') }}</span>
                                    </div>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" value="{{ route('webhook') }}" id="webhook_url" readonly>
                                        <span class="input-group-text pointer copy-text" data-target="webhook_url"><i class="ri-file-copy-line"></i></span>
                                    </div>
                                </div>

                                {{-- Verify Token --}}
                                <div class="setup-item">
                                    <div class="setup-item-header">
                                        <span class="setup-badge token"><i class="ri-key-2-line"></i></span>
                                        <span class="setup-label">{{ translate('Verify Token') }}</span>
                                    </div>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" value="{{ $configuration->webhook_verify_token }}" id="verify_token" readonly>
                                        <span class="input-group-text pointer copy-text" data-target="verify_token"><i class="ri-file-copy-line"></i></span>
                                        <span class="input-group-text pointer regenerate-token" data-uid="{{ $configuration->uid }}" title="{{ translate('Regenerate') }}"><i class="ri-refresh-line"></i></span>
                                    </div>
                                </div>

                                {{-- OAuth Admin URL --}}
                                <div class="setup-item">
                                    <div class="setup-item-header">
                                        <span class="setup-badge oauth"><i class="ri-shield-user-line"></i></span>
                                        <span class="setup-label">{{ translate('OAuth Redirect (Admin)') }}</span>
                                    </div>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" value="{{ route('admin.gateway.whatsapp.cloud.api.embedded.callback') }}" id="admin_oauth_url" readonly>
                                        <span class="input-group-text pointer copy-text" data-target="admin_oauth_url"><i class="ri-file-copy-line"></i></span>
                                    </div>
                                </div>

                                {{-- OAuth User URL --}}
                                <div class="setup-item">
                                    <div class="setup-item-header">
                                        <span class="setup-badge oauth-user"><i class="ri-user-line"></i></span>
                                        <span class="setup-label">{{ translate('OAuth Redirect (User)') }}</span>
                                    </div>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" value="{{ route('user.gateway.whatsapp.cloud.api.embedded.callback') }}" id="user_oauth_url" readonly>
                                        <span class="input-group-text pointer copy-text" data-target="user_oauth_url"><i class="ri-file-copy-line"></i></span>
                                    </div>
                                </div>

                                {{-- App Domain --}}
                                <div class="setup-item">
                                    <div class="setup-item-header">
                                        <span class="setup-badge domain"><i class="ri-global-line"></i></span>
                                        <span class="setup-label">{{ translate('App Domain') }}</span>
                                    </div>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" value="{{ request()->getHost() }}" id="app_domain" readonly>
                                        <span class="input-group-text pointer copy-text" data-target="app_domain"><i class="ri-file-copy-line"></i></span>
                                    </div>
                                </div>
                            </div>
                            <p class="text-muted small mt-3 mb-0">
                                <i class="ri-information-line"></i>
                                {{ translate('Add these URLs to your Meta App settings for Embedded Signup to work.') }}
                            </p>
                        </div>
                    </div>

                    {{-- Statistics Card --}}
                    <div class="card">
                        <div class="card-header">
                            <div class="card-header-left">
                                <h4 class="card-title">{{ translate('Statistics') }}</h4>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item fs-14 d-flex justify-content-between align-items-center px-0">
                                    <span>{{ translate('Connected Gateways') }}</span>
                                    <span class="i-badge primary-solid pill">{{ $configuration->gateways()->count() }}</span>
                                </li>
                                <li class="list-group-item fs-14 d-flex justify-content-between align-items-center px-0">
                                    <span>{{ translate('Client Onboardings') }}</span>
                                    <span class="i-badge info-solid pill">{{ $configuration->clientOnboardings()->count() }}</span>
                                </li>
                                <li class="list-group-item fs-14 d-flex justify-content-between align-items-center px-0">
                                    <span>{{ translate('Created') }}</span>
                                    <small class="text-muted">{{ $configuration->created_at->format('M d, Y') }}</small>
                                </li>
                                <li class="list-group-item fs-14 d-flex justify-content-between align-items-center px-0">
                                    <span>{{ translate('Last Updated') }}</span>
                                    <small class="text-muted">{{ $configuration->updated_at->format('M d, Y H:i') }}</small>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>

@endsection

@push('style-push')
<style>
/* Page Header Fix */
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
}
.page-header-left h2 {
    margin-bottom: 4px;
}
.page-header-left .breadcrumb {
    margin-bottom: 0;
}
.page-header-right {
    flex-shrink: 0;
}

/* Meta Developer Setup Compact */
.meta-setup-compact {
    background: var(--card-bg);
    border-radius: 10px;
    padding: 12px;
    border: 1px solid var(--color-border);
}
.setup-item {
    padding: 10px 0;
    border-bottom: 1px dashed var(--color-border);
}
.setup-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.setup-item:first-child {
    padding-top: 0;
}
.setup-item-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}
.setup-badge {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    flex-shrink: 0;
}
.setup-badge.webhook {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #fff;
}
.setup-badge.token {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    color: #fff;
}
.setup-badge.oauth {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    color: #fff;
}
.setup-badge.oauth-user {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: #fff;
}
.setup-badge.domain {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #fff;
}
.setup-label {
    font-size: 12px;
    font-weight: 500;
    color: var(--text-secondary);
}
.setup-item .input-group-sm .form-control {
    font-size: 11px;
    padding: 6px 10px;
    background: var(--card-bg);
}
.setup-item .input-group-text {
    padding: 4px 8px;
    font-size: 12px;
    background: #fff;
    border-color: #e2e8f0;
    color: var(--text-primary);
}
.setup-item .input-group-text:hover {
    background: var(--primary-color, #6366f1);
    border-color: var(--primary-color, #6366f1);
    color: #fff;
}
</style>
@endpush

@push('script-push')
<script>
"use strict";
(function($) {
    $(document).ready(function() {
        // Toggle password visibility
        $('.toggle-password').on('click', function() {
            var target = $(this).data('target');
            var input = $('#' + target);
            var icon = $(this).find('i');

            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('ri-eye-line').addClass('ri-eye-off-line');
            } else {
                input.attr('type', 'password');
                icon.removeClass('ri-eye-off-line').addClass('ri-eye-line');
            }
        });

        // Copy to clipboard
        $('.copy-text').on('click', function() {
            var target = $(this).data('target');
            var input = document.getElementById(target);
            var text = input.value;

            // Try modern clipboard API first, fallback to execCommand
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function() {
                    notify('success', '{{ translate("Copied to clipboard") }}');
                }).catch(function() {
                    fallbackCopy(input);
                });
            } else {
                fallbackCopy(input);
            }
        });

        function fallbackCopy(input) {
            input.select();
            input.setSelectionRange(0, 99999);
            try {
                document.execCommand('copy');
                notify('success', '{{ translate("Copied to clipboard") }}');
            } catch (err) {
                notify('error', '{{ translate("Failed to copy") }}');
            }
        }

        // Regenerate token
        $('.regenerate-token').on('click', function() {
            var btn = $(this);
            var uid = btn.data('uid');
            var icon = btn.find('i');

            icon.addClass('ri-spin');

            $.ajax({
                url: "{{ route('admin.whatsapp.configuration.regenerate-token', ':uid') }}".replace(':uid', uid),
                type: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(response) {
                    if (response.success) {
                        $('#verify_token').val(response.token);
                        notify('success', response.message);
                    } else {
                        notify('error', response.message || '{{ translate("Failed to regenerate token") }}');
                    }
                },
                error: function(xhr) {
                    notify('error', xhr.responseJSON?.message || '{{ translate("Failed to regenerate token") }}');
                },
                complete: function() {
                    icon.removeClass('ri-spin');
                }
            });
        });

        // Test connection — renders a per-check pass/fail/skip report so the
        // admin can see WHERE the setup broke, not just a green toast that
        // lies when App ID / Secret are wrong.
        $('.test-connection').on('click', function() {
            var btn = $(this);
            var uid = btn.data('uid');
            var originalHtml = btn.html();

            btn.html('<i class="ri-loader-4-line ri-spin"></i> {{ translate("Testing...") }}').prop('disabled', true);

            $.ajax({
                url: "{{ route('admin.whatsapp.configuration.test', ':uid') }}".replace(':uid', uid),
                type: 'GET',
                success: function(response) {
                    renderConnectionReport(response);
                },
                error: function(xhr) {
                    notify('error', xhr.responseJSON?.message || '{{ translate("Connection test failed") }}');
                },
                complete: function() {
                    btn.html(originalHtml).prop('disabled', false);
                }
            });
        });

        // Auto-probe on load — one lightweight call so the pill in the header
        // reflects real connectivity before the admin clicks anything.
        (function autoProbeStatus() {
            var pill = document.getElementById('connectionStatusPill');
            if (!pill) return;
            var uid = pill.getAttribute('data-uid');
            if (!uid) return;
            $.ajax({
                url: "{{ route('admin.whatsapp.configuration.test', ':uid') }}".replace(':uid', uid),
                type: 'GET',
                success: function(res) {
                    var failed = (res.checks || []).filter(function (c) { return c.status === 'fail'; });
                    if (res.success) {
                        pill.className = 'i-badge success-solid pill';
                        pill.innerHTML = '<i class="ri-checkbox-circle-fill"></i> {{ translate("Connected") }}';
                    } else if (failed.length && failed.length < (res.checks || []).length) {
                        pill.className = 'i-badge warning-solid pill';
                        pill.innerHTML = '<i class="ri-error-warning-fill"></i> {{ translate("Degraded") }}';
                    } else {
                        pill.className = 'i-badge danger-solid pill';
                        pill.innerHTML = '<i class="ri-close-circle-fill"></i> {{ translate("Broken") }}';
                    }
                    pill.title = res.message || '';
                    pill.style.cursor = 'pointer';
                    pill.onclick = function () { renderConnectionReport(res); };
                },
                error: function () {
                    pill.className = 'i-badge secondary-solid pill';
                    pill.innerHTML = '<i class="ri-question-line"></i> {{ translate("Unknown") }}';
                }
            });
        })();

        function renderConnectionReport(res) {
            var esc = function (s) {
                return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
                });
            };
            var iconFor = {
                pass: '<i class="ri-checkbox-circle-fill text-success"></i>',
                fail: '<i class="ri-close-circle-fill text-danger"></i>',
                skip: '<i class="ri-indeterminate-circle-line text-muted"></i>',
                info: '<i class="ri-information-fill text-info"></i>'
            };
            var rows = (res.checks || []).map(function (c) {
                var meta = '';
                if (c.meta && typeof c.meta === 'object') {
                    var pieces = [];
                    for (var k in c.meta) {
                        var v = c.meta[k];
                        if (Array.isArray(v)) v = v.join(', ');
                        if (v === null || v === '') continue;
                        pieces.push('<code class="small">' + esc(k) + '=' + esc(v) + '</code>');
                    }
                    if (pieces.length) meta = '<div class="mt-1">' + pieces.join(' &nbsp; ') + '</div>';
                }
                return ''
                    + '<div class="d-flex gap-2 py-2 border-bottom">'
                    + '<div style="font-size:20px; line-height:1;">' + (iconFor[c.status] || iconFor.info) + '</div>'
                    + '<div class="flex-grow-1">'
                    + '<div class="fw-semibold">' + esc(c.label) + '</div>'
                    + '<div class="small text-muted">' + esc(c.detail || '') + '</div>'
                    + meta
                    + '</div>'
                    + '</div>';
            }).join('');

            var header = res.success
                ? '<div class="alert alert-success mb-3"><i class="ri-checkbox-circle-fill"></i> ' + esc(res.message) + '</div>'
                : '<div class="alert alert-danger mb-3"><i class="ri-error-warning-fill"></i> ' + esc(res.message) + '</div>';

            var modal = $('#testConnectionReportModal');
            if (!modal.length) {
                $('body').append(''
                    + '<div class="modal fade" id="testConnectionReportModal" tabindex="-1">'
                    + '  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">'
                    + '    <div class="modal-content">'
                    + '      <div class="modal-header"><h5 class="modal-title"><i class="ri-wifi-line"></i> {{ translate("Connection Report") }}</h5>'
                    + '        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>'
                    + '      <div class="modal-body" id="testConnectionReportBody"></div>'
                    + '      <div class="modal-footer"><button type="button" class="i-btn btn--dark outline btn--sm" data-bs-dismiss="modal">{{ translate("Close") }}</button></div>'
                    + '    </div>'
                    + '  </div>'
                    + '</div>');
                modal = $('#testConnectionReportModal');
            }
            $('#testConnectionReportBody').html(header + rows
                + '<div class="small text-muted mt-3">'
                + '<i class="ri-git-branch-line"></i> {{ translate("API version") }}: <code>' + esc(res.api_version || '') + '</code>'
                + '</div>');
            modal.modal('show');

            if (typeof notify === 'function') {
                notify(res.success ? 'success' : 'error', res.message);
            }
        }
    });
})(jQuery);
</script>
@endpush

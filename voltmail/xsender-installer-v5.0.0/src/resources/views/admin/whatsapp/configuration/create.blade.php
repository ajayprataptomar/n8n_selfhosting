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
                            <li class="breadcrumb-item active" aria-current="page">{{ translate('Add New') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <div class="alert alert-warning d-flex align-items-start gap-3 mb-4" role="alert">
            <i class="ri-information-line fs-22 mt-1 flex-shrink-0"></i>
            <div>
                <strong>{{ translate('Two ways to use WhatsApp Cloud API') }}</strong>
                <p class="mb-2 mt-1">{{ translate('Pick the one that matches your situation — both work, but they require different fields below.') }}</p>
                <ol class="mb-1 ps-3">
                    <li class="mb-2">
                        <strong>{{ translate('Tech Provider / BSP mode') }}</strong>
                        — {{ translate('for reselling WhatsApp to your own customers (multi-tenant SaaS). Requires Meta-approved Tech Provider status. Fill in the') }} <em>{{ translate('Embedded Signup') }}</em> {{ translate('section (App ID, Secret, Configuration ID).') }}
                    </li>
                    <li>
                        <strong>{{ translate('Manual Setup / Single WABA mode') }}</strong>
                        — {{ translate('for sending from your own WhatsApp Business Account. No Meta approval needed. Fill in the') }} <em>{{ translate('Manual Setup (Single WABA)') }}</em> {{ translate('section (System User Token + WABA ID + Phone Number ID). Works today.') }}
                    </li>
                </ol>
                <small class="d-block mt-2 text-muted">{{ translate('Seeing the error') }} <code>"Embedded signup is only available for BSPs or TPs"</code>? {{ translate('Your Meta App is not Tech Provider approved — use Manual Setup instead.') }}</small>
            </div>
        </div>

        <form action="{{ route('admin.whatsapp.configuration.store') }}" method="POST">
            @csrf

            <div class="card mb-4">
                <div class="card-header">
                    <div class="card-header-left">
                        <h4 class="card-title">{{ translate('Basic Configuration') }}</h4>
                    </div>
                </div>

                <div class="card-body pt-0">
                    <div class="form-element">
                        <div class="row gy-4">
                            <div class="col-xxl-2 col-xl-3">
                                <h5 class="form-element-title">{{ translate('App Details') }}</h5>
                            </div>
                            <div class="col-xxl-8 col-xl-9">
                                <div class="row gy-4">
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="name" class="form-label">{{ translate('Configuration Name') }} <small class="text-danger">*</small></label>
                                            <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}" placeholder="{{ translate('e.g., Production WhatsApp App') }}" required>
                                            <p class="form-element-note">{{ translate('A friendly name to identify this configuration') }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="environment" class="form-label">{{ translate('Environment') }} <small class="text-danger">*</small></label>
                                            <select name="environment" id="environment" class="form-select" required>
                                                <option value="production" {{ old('environment') == 'production' ? 'selected' : '' }}>{{ translate('Production') }}</option>
                                                <option value="sandbox" {{ old('environment') == 'sandbox' ? 'selected' : '' }}>{{ translate('Sandbox') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="app_id" class="form-label">{{ translate('Meta App ID') }} <small class="text-danger">*</small></label>
                                            <input type="text" id="app_id" name="app_id" class="form-control" value="{{ old('app_id') }}" placeholder="{{ translate('Enter your Meta App ID') }}" required>
                                            <p class="form-element-note">{{ translate('Found in Meta Developer Portal > Your App > Settings > Basic') }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="app_secret" class="form-label">{{ translate('Meta App Secret') }} <small class="text-danger">*</small></label>
                                            <div class="input-group">
                                                <input type="password" id="app_secret" name="app_secret" class="form-control" placeholder="{{ translate('Enter your Meta App Secret') }}" required>
                                                <span class="input-group-text pointer toggle-password" data-target="app_secret">
                                                    <i class="ri-eye-line"></i>
                                                </span>
                                            </div>
                                            <p class="form-element-note">{{ translate('Will be encrypted before storage') }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="api_version" class="form-label">{{ translate('API Version') }} <small class="text-danger">*</small></label>
                                            <select name="api_version" id="api_version" class="form-select" required>
                                                <option value="v26.0" {{ old('api_version', 'v26.0') == 'v26.0' ? 'selected' : '' }}>v26.0 ({{ translate('Latest') }})</option>
                                                <option value="v25.0" {{ old('api_version') == 'v25.0' ? 'selected' : '' }}>v25.0</option>
                                                <option value="v24.0" {{ old('api_version') == 'v24.0' ? 'selected' : '' }}>v24.0</option>
                                                <option value="v23.0" {{ old('api_version') == 'v23.0' ? 'selected' : '' }}>v23.0</option>
                                            </select>
                                            <p class="form-element-note">{{ translate('Meta rolls a new Graph API version every 3 months. Old versions stay supported for ~24 months. Use the latest for new features.') }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label class="form-label">{{ translate('Set as Default') }}</label>
                                            <div class="form-check form-switch mt-2">
                                                <input type="checkbox" name="is_default" value="1" class="form-check-input" id="is_default" {{ old('is_default') ? 'checked' : '' }}>
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
                            <div class="col-xxl-8 col-xl-9">
                                <div class="row gy-4">
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="config_id" class="form-label">{{ translate('Configuration ID') }} <small class="text-danger">*</small></label>
                                            <input type="text" id="config_id" name="config_id" class="form-control" value="{{ old('config_id') }}" placeholder="{{ translate('e.g., 123456789012345') }}">
                                            <p class="form-element-note">{{ translate('Required for Embedded Signup. Found in Meta Business Settings > WhatsApp > Embedded Signup') }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="solution_id" class="form-label">{{ translate('Solution ID') }}</label>
                                            <input type="text" id="solution_id" name="solution_id" class="form-control" value="{{ old('solution_id') }}" placeholder="{{ translate('Optional - for verified solutions') }}">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="business_manager_id" class="form-label">{{ translate('Business Manager ID') }}</label>
                                            <input type="text" id="business_manager_id" name="business_manager_id" class="form-control" value="{{ old('business_manager_id') }}" placeholder="{{ translate('Your Meta Business Manager ID') }}">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="tech_provider_id" class="form-label">{{ translate('Tech Provider ID') }}</label>
                                            <input type="text" id="tech_provider_id" name="tech_provider_id" class="form-control" value="{{ old('tech_provider_id') }}" placeholder="{{ translate('Tech Provider Business ID') }}">
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
                            <div class="col-xxl-8 col-xl-9">
                                <div class="alert alert-info py-2 mb-3" role="alert">
                                    <small>
                                        <strong>{{ translate('How to get a System User Token:') }}</strong><br>
                                        1. {{ translate('Open') }} <a href="https://business.facebook.com/settings" target="_blank" rel="noopener">business.facebook.com/settings</a> → <strong>{{ translate('Users → System Users') }}</strong> → <strong>{{ translate('Add') }}</strong>.<br>
                                        2. {{ translate('Role:') }} <strong>Admin</strong>. {{ translate('Name it') }} <code>XSender API</code>.<br>
                                        3. {{ translate('Click') }} <strong>{{ translate('Generate New Token') }}</strong> → {{ translate('select your Meta App, permissions:') }} <code>whatsapp_business_management</code>, <code>whatsapp_business_messaging</code>, <code>business_management</code>. {{ translate('Expiration:') }} <strong>{{ translate('Never') }}</strong>.<br>
                                        4. {{ translate('Assign your WABA to the System User in') }} <strong>{{ translate('Accounts → WhatsApp Accounts → Assign') }}</strong>.<br>
                                        5. {{ translate('Paste the token below + the WABA ID and Phone Number ID from your WhatsApp Business profile.') }}
                                    </small>
                                </div>
                                <div class="row gy-4">
                                    <div class="col-md-12">
                                        <div class="form-inner">
                                            <label for="system_user_token" class="form-label">{{ translate('System User Access Token') }}</label>
                                            <div class="input-group">
                                                <input type="password" id="system_user_token" name="system_user_token" class="form-control" placeholder="{{ translate('Paste long-lived System User token (EAAB...)') }}" autocomplete="new-password">
                                                <span class="input-group-text pointer toggle-password" data-target="system_user_token">
                                                    <i class="ri-eye-line"></i>
                                                </span>
                                            </div>
                                            <p class="form-element-note">{{ translate('Will be encrypted before storage. Long-lived tokens start with') }} <code>EAAB</code>{{ translate(' and have no expiration.') }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="system_user_id" class="form-label">{{ translate('System User ID') }}</label>
                                            <input type="text" id="system_user_id" name="system_user_id" class="form-control" value="{{ old('system_user_id') }}" placeholder="{{ translate('Numeric ID of the System User') }}">
                                            <p class="form-element-note">{{ translate('Found next to the System User name in Business Manager') }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="business_manager_id_manual" class="form-label">{{ translate('Business Manager ID (Manual Setup)') }}</label>
                                            <input type="text" id="business_manager_id_manual" name="business_manager_id_manual" class="form-control" value="{{ old('business_manager_id_manual') }}" placeholder="{{ translate('Same as above; fill if blank') }}">
                                            <p class="form-element-note">{{ translate('Optional — if already filled in Embedded Signup section, leave blank.') }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-success mt-3 py-2 mb-0" role="alert">
                                    <small>
                                        <strong>{{ translate('After saving:') }}</strong>
                                        {{ translate('go to') }} <strong>{{ translate('Gateway → Messaging Gateways → WhatsApp Cloud APIs') }}</strong>
                                        → <strong>{{ translate('Add Manually') }}</strong> {{ translate('to register your Phone Number ID against this configuration. Each phone number becomes one gateway you can pick when sending campaigns.') }}
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-element">
                        <div class="row gy-4">
                            <div class="col-xxl-2 col-xl-3">
                                <h5 class="form-element-title">{{ translate('Meta Developer Setup') }}</h5>
                                <p class="form-element-note">{{ translate('URLs required for Meta App configuration') }}</p>
                            </div>
                            <div class="col-xxl-8 col-xl-9">
                                <div class="meta-setup-guide">
                                    {{-- Webhook URL --}}
                                    <div class="setup-url-item">
                                        <div class="setup-url-header">
                                            <span class="setup-url-badge webhook"><i class="ri-links-line"></i></span>
                                            <div class="setup-url-info">
                                                <h6 class="setup-url-title">{{ translate('Webhook Callback URL') }}</h6>
                                                <p class="setup-url-desc">{{ translate('Add to: Meta App → WhatsApp → Configuration → Callback URL') }}</p>
                                            </div>
                                        </div>
                                        <div class="input-group">
                                            <input type="text" class="form-control" value="{{ route('webhook') }}" id="webhook_url" readonly>
                                            <span class="input-group-text pointer copy-text" data-target="webhook_url">
                                                <i class="ri-file-copy-line"></i>
                                            </span>
                                        </div>
                                    </div>

                                    {{-- OAuth Redirect URIs --}}
                                    <div class="setup-url-item">
                                        <div class="setup-url-header">
                                            <span class="setup-url-badge oauth"><i class="ri-shield-user-line"></i></span>
                                            <div class="setup-url-info">
                                                <h6 class="setup-url-title">{{ translate('Valid OAuth Redirect URIs') }}</h6>
                                                <p class="setup-url-desc">{{ translate('Add to: Meta App → Use Cases → Customize → Settings → Valid OAuth Redirect URIs') }}</p>
                                            </div>
                                        </div>
                                        <div class="oauth-urls-list">
                                            <div class="input-group mb-2">
                                                <span class="input-group-text oauth-label">{{ translate('Admin') }}</span>
                                                <input type="text" class="form-control" value="{{ route('admin.gateway.whatsapp.cloud.api.embedded.callback') }}" id="admin_oauth_url" readonly>
                                                <span class="input-group-text pointer copy-text" data-target="admin_oauth_url">
                                                    <i class="ri-file-copy-line"></i>
                                                </span>
                                            </div>
                                            <div class="input-group">
                                                <span class="input-group-text oauth-label">{{ translate('User') }}</span>
                                                <input type="text" class="form-control" value="{{ route('user.gateway.whatsapp.cloud.api.embedded.callback') }}" id="user_oauth_url" readonly>
                                                <span class="input-group-text pointer copy-text" data-target="user_oauth_url">
                                                    <i class="ri-file-copy-line"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- App Domain --}}
                                    <div class="setup-url-item">
                                        <div class="setup-url-header">
                                            <span class="setup-url-badge domain"><i class="ri-global-line"></i></span>
                                            <div class="setup-url-info">
                                                <h6 class="setup-url-title">{{ translate('App Domain') }}</h6>
                                                <p class="setup-url-desc">{{ translate('Add to: Meta App → App Settings → Basic → App Domains') }}</p>
                                            </div>
                                        </div>
                                        <div class="input-group">
                                            <input type="text" class="form-control" value="{{ request()->getHost() }}" id="app_domain" readonly>
                                            <span class="input-group-text pointer copy-text" data-target="app_domain">
                                                <i class="ri-file-copy-line"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="note-container mt-3">
                                    <div class="note note-info alert alert-primary d-flex align-items-start gap-3 mb-4">
                                        <div class="d-flex justify-content-start gap-2">
                                            <div class="note-icon setup-url-badge info">
                                                <i class="ri-lightbulb-line"></i>
                                            </div>
                                            <div class="note-content">
                                                <p class="note-text mb-0">
                                                    {{ translate('Verify token will be generated automatically after saving. Make sure your Meta App is in Live mode for production use.') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-xxl-10">
                            <div class="form-action justify-content-end">
                                <a href="{{ route('admin.whatsapp.configuration.index') }}" class="i-btn btn--danger outline btn--md">{{ translate('Cancel') }}</a>
                                <button type="submit" class="i-btn btn--primary btn--md">{{ translate('Save Configuration') }}</button>
                            </div>
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

/* Meta Developer Setup Guide */
.meta-setup-guide {
    /* background: #f8fafc; */
    border-radius: 12px;
    padding: 20px;
    border: 1px solid #e2e8f0;
}
.setup-url-item {
    padding: 16px 0;
    border-bottom: 1px dashed #e2e8f0;
}
.setup-url-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.setup-url-item:first-child {
    padding-top: 0;
}
.setup-url-header {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 12px;
}
.setup-url-badge {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
}
.setup-url-badge.webhook {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #fff;
}
.setup-url-badge.oauth {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    color: #fff;
}
.setup-url-badge.domain {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #fff;
}
.setup-url-badge.info {
    background: linear-gradient(135deg, #3281ff 0%, #2986ff 100%);
    color: #fff;
}
.setup-url-info {
    flex: 1;
}
.setup-url-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 2px;
}
.setup-url-desc {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 0;
}
.setup-url-item .input-group {
    background: #fff;
    border-radius: 8px;
}
.setup-url-item .form-control {
    font-size: 13px;
    background: #fff;
    border-color: #e2e8f0;
}
.setup-url-item .form-control:focus {
    border-color: var(--primary-color, #6366f1);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}
.oauth-urls-list .input-group-text.oauth-label {
    min-width: 60px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    background: #f1f5f9;
    border-color: #e2e8f0;
    color: #64748b;
}
.setup-url-item .copy-text {
    background: #f8fafc;
    border-color: #e2e8f0;
    color: #64748b;
    transition: all 0.2s;
}
.setup-url-item .copy-text:hover {
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
    });
})(jQuery);
</script>
@endpush

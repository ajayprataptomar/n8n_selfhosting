@extends('admin.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ k2t($title) }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="{{ route("admin.dashboard") }}">{{ translate("Dashboard") }}</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">{{ k2t($title) }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        @php
            $metaConfigs = \App\Models\MetaConfiguration::where('status', 'active')->get();
            $hasEnterpriseConfig = $metaConfigs->isNotEmpty();
        @endphp

        {{-- Deprecation Notice --}}
        <div class="deprecation-notice">
            <div class="deprecation-icon">
                <i class="ri-error-warning-line"></i>
            </div>
            <div class="deprecation-content">
                <h5 class="deprecation-title">{{ translate('This page is deprecated') }}</h5>
                <p class="deprecation-text">
                    {{ translate('Please use the new Configuration Management system for enhanced features including multi-environment support, health monitoring, and client onboarding tracking.') }}
                </p>
                <div class="deprecation-actions">
                    <a href="{{ route('admin.whatsapp.configuration.index') }}" class="i-btn btn--primary btn--md">
                        <i class="ri-settings-3-line"></i> {{ translate('Go to Configuration Management') }}
                    </a>
                </div>
            </div>
        </div>

        {{-- Active Configurations Summary --}}
        @if($hasEnterpriseConfig)
        <div class="card mb-4">
            <div class="card-header">
                <div class="card-header-left">
                    <h4 class="card-title">
                        <i class="ri-checkbox-circle-fill text--success me-2"></i>
                        {{ translate('Active Configurations') }}
                    </h4>
                </div>
                <div class="card-header-right">
                    <span class="i-badge success-solid pill">{{ $metaConfigs->count() }} {{ translate('Active') }}</span>
                </div>
            </div>
            <div class="card-body px-0 pt-0">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>{{ translate('Name') }}</th>
                                <th>{{ translate('Environment') }}</th>
                                <th>{{ translate('Configuration ID') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th class="text-end">{{ translate('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($metaConfigs as $config)
                            <tr>
                                <td data-label="{{ translate('Name') }}">
                                    <strong>{{ $config->name }}</strong>
                                </td>
                                <td data-label="{{ translate('Environment') }}">
                                    <span class="i-badge {{ $config->environment == 'production' ? 'primary-solid' : 'secondary-solid' }} pill">
                                        {{ ucfirst($config->environment) }}
                                    </span>
                                </td>
                                <td data-label="{{ translate('Configuration ID') }}">
                                    @if($config->config_id)
                                        <code class="config-code">{{ Str::limit($config->config_id, 20) }}</code>
                                    @else
                                        <span class="text-muted">{{ translate('Not configured') }}</span>
                                    @endif
                                </td>
                                <td data-label="{{ translate('Status') }}">
                                    @if($config->is_default)
                                        <span class="i-badge info-solid pill">{{ translate('Default') }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td data-label="{{ translate('Action') }}" class="text-end">
                                    <a href="{{ route('admin.whatsapp.configuration.edit', $config->uid) }}"
                                       class="i-btn btn--primary outline btn--sm">
                                        <i class="ri-edit-line"></i> {{ translate('Edit') }}
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- Legacy Settings Card --}}
        <div class="card legacy-card">
            <div class="card-header">
                <div class="card-header-left">
                    <h4 class="card-title">
                        <i class="ri-history-line me-2"></i>
                        {{ translate('Legacy Settings') }}
                    </h4>
                </div>
                <div class="card-header-right">
                    <span class="i-badge secondary-solid pill">{{ translate('Deprecated') }}</span>
                </div>
            </div>
            <div class="card-body pt-0">
                @if($hasEnterpriseConfig)
                <div class="legacy-info-box">
                    <i class="ri-information-line"></i>
                    <span>{{ translate('These settings are used as fallback only. We recommend using the new configuration system above.') }}</span>
                </div>
                @else
                <div class="legacy-info-box warning">
                    <i class="ri-alert-line"></i>
                    <span>{{ translate('No active configurations found. Please set up a new configuration for full functionality.') }}</span>
                </div>
                @endif

                <form action="{{ route("admin.system.setting.store") }}" method="POST" enctype="multipart/form-data" class="settingsForm">
                    @csrf
                    <div class="form-element">
                        <div class="row gy-4">
                            <div class="col-xxl-2 col-xl-3">
                                <h5 class="form-element-title">{{ translate("App Credentials") }}</h5>
                            </div>
                            <div class="col-xxl-8 col-xl-9">
                                <div class="row gy-4">
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="meta-app-id" class="form-label">{{ translate("Meta App ID") }} <small class="text-danger">*</small></label>
                                            <input type="text"
                                                id="meta-app-id"
                                                name="site_settings[{{ \App\Enums\SettingKey::META_APP_ID->value }}]"
                                                class="form-control"
                                                placeholder="{{ translate('Enter your Meta App ID') }}"
                                                value="{{ site_settings(\App\Enums\SettingKey::META_APP_ID->value) }}"/>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="meta-app-secret" class="form-label">{{ translate("Meta App Secret") }} <small class="text-danger">*</small></label>
                                            <input type="text"
                                                id="meta-app-secret"
                                                name="site_settings[{{ \App\Enums\SettingKey::META_APP_SECRET->value }}]"
                                                class="form-control"
                                                placeholder="{{ translate('Enter your Meta App Secret') }}"
                                                value="{{ site_settings(\App\Enums\SettingKey::META_APP_SECRET->value) }}"/>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-element">
                        <div class="row gy-4">
                            <div class="col-xxl-2 col-xl-3">
                                <h5 class="form-element-title">{{ translate("Webhook") }}</h5>
                            </div>
                            <div class="col-xxl-8 col-xl-9">
                                <div class="row gy-4">
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="callback_url" class="form-label">{{ translate("Callback URL") }} <small class="text-danger">*</small></label>
                                            <div class="input-group">
                                                <input disabled type="text" id="callback_url" class="form-control" value="{{ route('webhook') }}"/>
                                                <button type="button" class="input-group-text copy-btn" data-copy-target="callback_url">
                                                    <i class="ri-file-copy-line"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="webhook_verify_token" class="form-label">{{ translate("Verify Token") }} <small class="text-danger">*</small></label>
                                            <div class="input-group">
                                                <input type="text" id="webhook_verify_token" class="form-control verify_token" value="{{ site_settings("webhook_verify_token") }}" name="site_settings[webhook_verify_token]"/>
                                                <button type="button" class="input-group-text generate-token">
                                                    <i class="ri-restart-line"></i>
                                                </button>
                                                <button type="button" class="input-group-text copy-btn" data-copy-target="webhook_verify_token">
                                                    <i class="ri-file-copy-line"></i>
                                                </button>
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
                                <button type="reset" class="i-btn btn--danger outline btn--md">{{ translate("Reset") }}</button>
                                <button type="submit" class="i-btn btn--primary btn--md">{{ translate("Save") }}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

@endsection

@push('style-push')
<style>
/* Deprecation Notice */
.deprecation-notice {
    display: flex;
    align-items: flex-start;
    gap: 20px;
    padding: 24px;
    background: linear-gradient(135deg, #fef3c7 0%, #f6ecc5 100%);
    border: 1px solid #f59e0b;
    border-radius: 12px;
    margin-bottom: 24px;
}
.deprecation-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(245, 158, 11, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.deprecation-icon i {
    font-size: 24px;
    color: #d97706;
}
.deprecation-content {
    flex: 1;
}
.deprecation-title {
    font-size: 16px;
    font-weight: 600;
    color: #92400e !important;
    margin-bottom: 8px;
}
.deprecation-text {
    font-size: 14px;
    color: #a16207;
    margin-bottom: 16px;
    line-height: 1.5;
}
.deprecation-actions {
    display: flex;
    gap: 12px;
}

/* Legacy Card Styling */
.legacy-card {
    opacity: 0.85;
    border: 1px dashed #d1d5db;
}
.legacy-card:hover {
    opacity: 1;
}

/* Legacy Info Box */
.legacy-info-box {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 8px;
    margin-bottom: 24px;
    font-size: 13px;
    color: #0369a1;
}
.legacy-info-box.warning {
    background: #fffbeb;
    border-color: #fde68a;
    color: #b45309;
}
.legacy-info-box i {
    font-size: 18px;
    flex-shrink: 0;
}

/* Config Code */
.config-code {
    font-size: 12px;
    background: #f1f5f9;
    padding: 4px 8px;
    border-radius: 4px;
    color: #475569;
}

/* Copy Button */
.copy-btn {
    cursor: pointer;
    border: none;
    background: #f3f4f6;
    transition: all 0.2s;
}
.copy-btn:hover {
    background: var(--primary-color, #6366f1);
    color: #fff;
}
</style>
@endpush

@push("script-push")
<script>
"use strict";
(function($) {
    $(document).ready(function() {
        // Copy to clipboard with fallback for HTTP
        $('.copy-btn').on('click', function() {
            var targetId = $(this).data('copy-target');
            var input = document.getElementById(targetId);
            var text = input.value;

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
            var wasDisabled = input.disabled;
            input.disabled = false;
            input.select();
            input.setSelectionRange(0, 99999);
            try {
                document.execCommand('copy');
                notify('success', '{{ translate("Copied to clipboard") }}');
            } catch (err) {
                notify('error', '{{ translate("Failed to copy") }}');
            }
            input.disabled = wasDisabled;
        }

        // Generate random token
        $('.generate-token').on('click', function() {
            var randomToken = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
            $('.verify_token').val(randomToken);
            notify('success', '{{ translate("New token generated") }}');
        });
    });
})(jQuery);
</script>
@endpush

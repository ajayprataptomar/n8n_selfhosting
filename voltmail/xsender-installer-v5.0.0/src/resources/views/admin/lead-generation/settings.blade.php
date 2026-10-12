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
                <a href="{{ route('admin.lead-generation.index') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-arrow-left-line"></i> {{ translate("Back to Dashboard") }}
                </a>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column: API Configuration -->
            <div class="col-xl-7">
                <!-- Google Maps API Key Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="card-header-left">
                            <h4 class="card-title">
                                <i class="ri-key-2-line me-2 text-primary"></i>
                                {{ translate("Google Maps API Key") }}
                            </h4>
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.lead-generation.settings.update') }}" method="POST">
                            @csrf
                            <div class="row gy-4">
                                <div class="col-12">
                                    <div class="form-inner">
                                        <label for="google_maps_api_key" class="form-label">
                                            {{ translate("API Key") }}
                                            <small class="text-danger">*</small>
                                        </label>
                                        <div class="input-group">
                                            <input type="password"
                                                   class="form-control"
                                                   name="google_maps_api_key"
                                                   id="google_maps_api_key"
                                                   value="{{ $settings->google_maps_api_key }}"
                                                   placeholder="{{ translate('Enter your Google Maps API Key (e.g., AIzaSy...)') }}">
                                            <button type="button" class="input-group-text" id="toggleApiKey">
                                                <i class="ri-eye-line"></i>
                                            </button>
                                        </div>
                                        <p class="form-element-note">{{ translate("Required for Google Maps Business Scraper. Your API key is stored securely.") }}</p>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-inner">
                                        <label for="api_docs_url" class="form-label">{{ translate("Documentation URL for Users") }}</label>
                                        <input type="url"
                                               class="form-control"
                                               name="api_docs_url"
                                               id="api_docs_url"
                                               value="{{ $settings->api_docs_url ?? 'https://developers.google.com/maps/documentation/places/web-service/get-api-key' }}"
                                               placeholder="{{ translate('Enter documentation URL') }}">
                                        <p class="form-element-note">{{ translate("Custom documentation link shown to users for API key setup guidance.") }}</p>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-action justify-content-end">
                                        <button type="submit" class="i-btn btn--primary btn--md">
                                            <i class="ri-save-line"></i> {{ translate("Save API Key") }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Admin Access Card -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <h4 class="card-title">
                                <i class="ri-shield-check-line me-2 text-success"></i>
                                {{ translate("Admin Privileges") }}
                            </h4>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="privilege-box">
                            <div class="privilege-icon">
                                <i class="ri-infinity-line"></i>
                            </div>
                            <div class="privilege-content">
                                <h5>{{ translate("Unlimited Scraping Access") }}</h5>
                                <p>{{ translate("As an administrator, you have unlimited access to both Google Maps and Website Scraper. There are no daily or monthly limits for admin-level scraping.") }}</p>
                                <div class="privilege-note">
                                    <i class="ri-information-line"></i>
                                    <span>{{ translate("User scraping limits are controlled through their subscription plans.") }}
                                        <a href="{{ route('admin.membership.plan.index') }}">{{ translate("Manage Plans") }}</a>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Documentation & Info -->
            <div class="col-xl-5">
                <!-- Setup Guide Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="card-header-left">
                            <h4 class="card-title">
                                <i class="ri-book-open-line me-2 text-info"></i>
                                {{ translate("Setup Guide") }}
                            </h4>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">{{ translate("Follow these steps to obtain a Google Maps API key:") }}</p>

                        <div class="setup-steps">
                            <div class="setup-step-item">
                                <span class="step-badge">1</span>
                                <div class="step-info">
                                    <strong>{{ translate("Google Cloud Console") }}</strong>
                                    <p>{{ translate("Visit") }} <a href="https://console.cloud.google.com" target="_blank" rel="noopener">console.cloud.google.com</a></p>
                                </div>
                            </div>

                            <div class="setup-step-item">
                                <span class="step-badge">2</span>
                                <div class="step-info">
                                    <strong>{{ translate("Create Project") }}</strong>
                                    <p>{{ translate("Create a new project or select an existing one") }}</p>
                                </div>
                            </div>

                            <div class="setup-step-item">
                                <span class="step-badge">3</span>
                                <div class="step-info">
                                    <strong>{{ translate("Enable Places API") }}</strong>
                                    <p>{{ translate("Go to APIs & Services > Library > Search 'Places API' > Enable") }}</p>
                                </div>
                            </div>

                            <div class="setup-step-item">
                                <span class="step-badge">4</span>
                                <div class="step-info">
                                    <strong>{{ translate("Create API Key") }}</strong>
                                    <p>{{ translate("Go to Credentials > Create Credentials > API Key") }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 d-flex flex-wrap gap-2">
                            <a href="https://developers.google.com/maps/documentation/places/web-service/get-api-key" target="_blank" class="i-btn btn--dark outline btn--md rounded-3 w-100">
                                <i class="ri-external-link-line"></i> {{ translate("Official Documentation") }}
                            </a>
                            <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="i-btn btn--primary btn--md rounded-3 w-100">
                                <i class="ri-key-line"></i> {{ translate("Go to Google Cloud Console") }}
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Important Notes Card -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <h4 class="card-title">
                                <i class="ri-error-warning-line me-2 text-warning"></i>
                                {{ translate("Important Notes") }}
                            </h4>
                        </div>
                    </div>
                    <div class="card-body">
                        <ul class="info-list">
                            <li>
                                <i class="ri-money-dollar-circle-line text-warning"></i>
                                <span>{{ translate("Google Places API may incur charges. Google provides \$200 free credit monthly.") }}</span>
                            </li>
                            <li>
                                <i class="ri-notification-3-line text-info"></i>
                                <span>{{ translate("Set up billing alerts in Google Cloud Console to monitor spending.") }}</span>
                            </li>
                            <li>
                                <i class="ri-lock-line text-danger"></i>
                                <span>{{ translate("Restrict your API key to specific IPs or HTTP referrers for security.") }}</span>
                            </li>
                            <li>
                                <i class="ri-check-double-line text-success"></i>
                                <span>{{ translate("Website Scraper does not require any API key and has no external costs.") }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection

@push('style-include')
<style>
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

    /* Privilege Box */
    .privilege-box {
        display: flex;
        gap: 1rem;
        padding: 1.25rem;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(16, 185, 129, 0.04) 100%);
        border: 1px solid rgba(16, 185, 129, 0.2);
        border-radius: 12px;
    }

    .privilege-icon {
        width: 56px;
        height: 56px;
        min-width: 56px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: #fff;
    }

    .privilege-content h5 {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-color, #1f2937);
    }

    .privilege-content p {
        font-size: 0.875rem;
        color: var(--text-muted, #6b7280);
        margin-bottom: 0.75rem;
        line-height: 1.6;
    }

    .privilege-note {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        padding: 0.625rem 0.875rem;
        background: var(--card-bg, #fff);
        border-radius: 8px;
        font-size: 0.8125rem;
        color: var(--text-muted, #6b7280);
    }

    .privilege-note i {
        color: #10b981;
        font-size: 1rem;
        margin-top: 0.125rem;
    }

    .privilege-note a {
        color: #059669;
        font-weight: 500;
    }

    /* Setup Steps */
    .setup-steps {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .setup-step-item {
        display: flex;
        align-items: flex-start;
        gap: 0.875rem;
        padding: 0.875rem;
        background: var(--bg-light, #f8fafc);
        border-radius: 10px;
        border: 1px solid var(--border-color, #e2e8f0);
        transition: all 0.2s ease;
    }

    .setup-step-item:hover {
        border-color: var(--color-primary, #5046e5);
        box-shadow: 0 2px 8px rgba(80, 70, 229, 0.1);
    }

    .step-badge {
        width: 28px;
        height: 28px;
        min-width: 28px;
        background: var(--color-primary-light);
        color: var(--color-primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8125rem;
        font-weight: 700;
    }

    .step-info strong {
        display: block;
        font-size: 0.875rem;
        color: var(--text-color, #1f2937);
        margin-bottom: 0.25rem;
    }

    .step-info p {
        font-size: 0.8125rem;
        color: var(--text-muted, #6b7280);
        margin: 0;
        line-height: 1.5;
    }

    .step-info a {
        color: var(--primary-color, #5046e5);
        text-decoration: none;
    }

    .step-info a:hover {
        text-decoration: underline;
    }

    /* Info List */
    .info-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .info-list li {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--border-color, #e2e8f0);
        font-size: 0.875rem;
        color: var(--text-color, #374151);
        line-height: 1.5;
    }

    .info-list li:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .info-list li:first-child {
        padding-top: 0;
    }

    .info-list li i {
        font-size: 1.125rem;
        margin-top: 0.125rem;
    }

    /* Toggle API Key Button */
    #toggleApiKey {
        cursor: pointer;
        background: var(--input-bg, #fff);
        border-color: var(--border-color, #e2e8f0);
    }

    #toggleApiKey:hover {
        background: var(--bg-light, #f1f5f9);
    }

    /* Card Title Icons */
    .card-title i {
        font-size: 1.125rem;
    }

    /* Dark Mode */
    [data-theme="dark"] .privilege-box {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.12) 0%, rgba(16, 185, 129, 0.06) 100%);
        border-color: rgba(16, 185, 129, 0.25);
    }

    [data-theme="dark"] .privilege-content h5 {
        color: var(--text-color-dark, #f9fafb);
    }

    [data-theme="dark"] .privilege-note {
        background: var(--bg-light-dark, #374151);
    }

    [data-theme="dark"] .setup-step-item {
        background: var(--bg-light-dark, #374151);
        border-color: var(--border-color-dark, #4b5563);
    }

    [data-theme="dark"] .setup-step-item:hover {
        border-color: var(--primary-color, #818cf8);
    }

    [data-theme="dark"] .step-info strong {
        color: var(--text-color-dark, #f9fafb);
    }

    [data-theme="dark"] .info-list li {
        border-color: var(--border-color-dark, #4b5563);
        color: var(--text-color-dark, #e5e7eb);
    }
</style>
@endpush

@push('script-push')
<script>
(function($) {
    "use strict";

    // Toggle API key visibility
    $('#toggleApiKey').on('click', function() {
        var input = $('#google_maps_api_key');
        var icon = $(this).find('i');

        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('ri-eye-line').addClass('ri-eye-off-line');
        } else {
            input.attr('type', 'password');
            icon.removeClass('ri-eye-off-line').addClass('ri-eye-line');
        }
    });

})(jQuery);
</script>
@endpush

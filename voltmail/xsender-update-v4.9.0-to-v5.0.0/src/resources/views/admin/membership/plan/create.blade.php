@push("style-include")
<link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">

@endpush

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
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.dashboard') }}">{{ translate("Dashboard") }}</a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.membership.plan.index') }}">{{ translate("Plans") }}</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">{{ translate("Create Plan") }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.membership.plan.store') }}" method="POST" id="planForm">
            @csrf

            <div class="plan-builder-layout">
                <!-- LEFT: Configuration Area -->
                <div class="plan-config-area">

                    <!-- Basic Information -->
                    <div class="config-section">
                        <div class="config-section-header">
                            <div class="section-icon" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);">
                                <i class="ri-information-line"></i>
                            </div>
                            <h5>{{ translate("Basic Information") }}</h5>
                        </div>
                        <div class="config-section-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>{{ translate("Plan Name") }} <span class="required">*</span></label>
                                    <input type="text" name="name" id="plan_name" class="form-control" placeholder="{{ translate('e.g., Professional Plan') }}" value="{{ old('name') }}" required>
                                </div>
                                <div class="form-group">
                                    <label>{{ translate("Price") }} <span class="required">*</span></label>
                                    <div class="input-group">
                                        <input type="number" min="0" step="0.01" name="amount" id="plan_amount" class="form-control" placeholder="0.00" value="{{ old('amount', '29.99') }}" required>
                                        <span class="input-group-text">{{ getDefaultCurrencyCode(json_decode(site_settings('currencies'), true)) }}</span>
                                    </div>
                                    <div class="quick-values">
                                        <button type="button" class="quick-btn" data-target="plan_amount" data-value="0">{{ translate("Free") }}</button>
                                        <button type="button" class="quick-btn" data-target="plan_amount" data-value="9.99">$9.99</button>
                                        <button type="button" class="quick-btn" data-target="plan_amount" data-value="29.99">$29.99</button>
                                        <button type="button" class="quick-btn" data-target="plan_amount" data-value="99.99">$99.99</button>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>{{ translate("Duration") }} <span class="required">*</span></label>
                                    <div class="input-group">
                                        <input type="number" min="0" name="duration" id="plan_duration" class="form-control" placeholder="30" value="{{ old('duration', '30') }}" required>
                                        <span class="input-group-text">{{ translate("Days") }}</span>
                                    </div>
                                    <div class="quick-values">
                                        <button type="button" class="quick-btn" data-target="plan_duration" data-value="7">7d</button>
                                        <button type="button" class="quick-btn" data-target="plan_duration" data-value="30">30d</button>
                                        <button type="button" class="quick-btn" data-target="plan_duration" data-value="90">90d</button>
                                        <button type="button" class="quick-btn" data-target="plan_duration" data-value="365">1yr</button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row mt-3">
                                <div class="form-group">
                                    <label>{{ translate("Description") }}</label>
                                    <textarea name="description" id="plan_description" class="form-control" rows="2" placeholder="{{ translate('Brief description of what this plan offers...') }}">{{ old('description') }}</textarea>
                                </div>
                                <div class="form-group">
                                    <label>{{ translate("Affiliate Commission") }}</label>
                                    <div class="input-group">
                                        <input type="number" min="0" max="100" name="affiliate_commission" class="form-control" placeholder="10" value="{{ old('affiliate_commission', '10') }}">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="form-group d-flex align-items-end">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="allow_carry_forward" name="allow_carry_forward" value="true">
                                        <label class="form-check-label" for="allow_carry_forward">
                                            {{ translate("Carry Forward") }}
                                            <small class="d-block text-muted">{{ translate("Unused credits roll over") }}</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gateway Access Mode -->
                    <div class="config-section">
                        <div class="config-section-header">
                            <div class="section-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                                <i class="ri-shield-keyhole-line"></i>
                            </div>
                            <h5>{{ translate("Gateway Access Mode") }}</h5>
                        </div>
                        <div class="config-section-body">
                            <div class="gateway-mode-toggle">
                                <div class="mode-info">
                                    <h6>{{ translate("Use Admin Gateways") }}</h6>
                                    <p>{{ translate("When enabled, users send messages through your configured gateways. Otherwise, users configure their own.") }}</p>
                                </div>
                                <div class="switch-wrapper">
                                    <input type="checkbox" class="switch-input" id="allow_admin_creds" name="allow_admin_creds" value="true">
                                    <label for="allow_admin_creds" class="toggle"><span></span></label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Messaging Channels -->
                    <div class="config-section">
                        <div class="config-section-header">
                            <div class="section-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                                <i class="ri-message-3-line"></i>
                            </div>
                            <h5>{{ translate("Messaging Channels & Credits") }}</h5>
                        </div>
                        <div class="config-section-body">
                            <div class="channel-cards">
                                <!-- SMS Channel -->
                                <div class="channel-card enabled" data-channel="sms">
                                    <div class="channel-card-header">
                                        <div class="channel-info">
                                            <i class="ri-message-2-line text-primary"></i>
                                            <span>{{ translate("SMS") }}</span>
                                        </div>
                                        <div class="switch-wrapper">
                                            <input type="checkbox" class="switch-input channel-toggle" id="channel_sms" data-channel="sms" checked>
                                            <label for="channel_sms" class="toggle"><span></span></label>
                                        </div>
                                    </div>
                                    <div class="channel-card-body">
                                        <!-- Admin Mode -->
                                        <div class="admin-mode-fields">
                                            <input type="hidden" name="allow_admin_sms" value="true">
                                            <div class="credit-row">
                                                <div class="form-group">
                                                    <label>{{ translate("Total Credits") }}</label>
                                                    <input type="number" min="-1" name="sms_credit_admin" id="sms_credit" class="form-control credit-input" value="1000">
                                                    <div class="quick-values">
                                                        <button type="button" class="quick-btn is-unlimited" data-target="sms_credit" data-value="-1">&infin;</button>
                                                        <button type="button" class="quick-btn" data-target="sms_credit" data-value="500">500</button>
                                                        <button type="button" class="quick-btn" data-target="sms_credit" data-value="1000">1K</button>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ translate("Daily Limit") }}</label>
                                                    <input type="number" min="0" name="sms_credit_per_day_admin" class="form-control" value="0" placeholder="0 = No limit">
                                                </div>
                                            </div>
                                            <div class="form-check mt-2">
                                                <input type="checkbox" class="form-check-input" id="allow_admin_android" name="allow_admin_android" value="true">
                                                <label class="form-check-label" for="allow_admin_android">
                                                    {{ translate("Allow Android Gateway") }}
                                                </label>
                                            </div>
                                        </div>
                                        <!-- User Mode -->
                                        <div class="user-mode-fields" style="display: none;">
                                            <div class="credit-row">
                                                <div class="form-group">
                                                    <label>{{ translate("Total Credits") }}</label>
                                                    <input type="number" min="-1" name="sms_credit_user" class="form-control credit-input" value="1000">
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ translate("Daily Limit") }}</label>
                                                    <input type="number" min="0" name="sms_credit_per_day_user" class="form-control" value="0">
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <div class="form-check mb-2">
                                                    <input type="checkbox" class="form-check-input" id="allow_user_android" name="allow_user_android" value="true">
                                                    <label class="form-check-label" for="allow_user_android">{{ translate("Allow Android Gateway") }}</label>
                                                </div>
                                                <div class="form-group" id="android_limit_group" style="display: none;">
                                                    <label>{{ translate("Android Gateway Limit") }}</label>
                                                    <input type="number" min="-1" name="user_android_gateway_limit" class="form-control" value="1">
                                                </div>
                                                <div class="form-check mb-2">
                                                    <input type="checkbox" class="form-check-input" id="sms_multi_gateway" name="sms_multi_gateway" value="true">
                                                    <label class="form-check-label" for="sms_multi_gateway">{{ translate("Allow API Gateways") }}</label>
                                                </div>
                                                <div id="sms_gateways_area" style="display: none;">
                                                    <select class="form-select form-select-sm select2-search" id="sms_gateways_select">
                                                        <option value="">{{ translate("Select gateway") }}</option>
                                                        @foreach($sms_credentials as $credential)
                                                            <option value="{{ $credential }}">{{ ucfirst($credential) }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="button" class="btn btn-sm btn-primary mt-1 add-sms-gateway">
                                                        <i class="ri-add-line"></i> {{ translate("Add") }}
                                                    </button>
                                                    <div class="sms-gateways-list mt-2"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- WhatsApp Channel -->
                                <div class="channel-card enabled" data-channel="whatsapp">
                                    <div class="channel-card-header">
                                        <div class="channel-info">
                                            <i class="ri-whatsapp-line text-success"></i>
                                            <span>{{ translate("WhatsApp") }}</span>
                                        </div>
                                        <div class="switch-wrapper">
                                            <input type="checkbox" class="switch-input channel-toggle" id="channel_whatsapp" data-channel="whatsapp" checked>
                                            <label for="channel_whatsapp" class="toggle"><span></span></label>
                                        </div>
                                    </div>
                                    <div class="channel-card-body">
                                        <!-- Admin Mode -->
                                        <div class="admin-mode-fields">
                                            <input type="hidden" name="allow_admin_whatsapp" value="true">
                                            <div class="form-group mb-2">
                                                <label>{{ translate("Device Limit") }}</label>
                                                <input type="number" min="-1" name="whatsapp_device_limit" class="form-control" value="1">
                                            </div>
                                            <div class="credit-row">
                                                <div class="form-group">
                                                    <label>{{ translate("Total Credits") }}</label>
                                                    <input type="number" min="-1" name="whatsapp_credit_admin" id="whatsapp_credit" class="form-control credit-input" value="1000">
                                                    <div class="quick-values">
                                                        <button type="button" class="quick-btn is-unlimited" data-target="whatsapp_credit" data-value="-1">&infin;</button>
                                                        <button type="button" class="quick-btn" data-target="whatsapp_credit" data-value="500">500</button>
                                                        <button type="button" class="quick-btn" data-target="whatsapp_credit" data-value="1000">1K</button>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ translate("Daily Limit") }}</label>
                                                    <input type="number" min="0" name="whatsapp_credit_per_day_admin" class="form-control" value="0">
                                                </div>
                                            </div>
                                        </div>
                                        <!-- User Mode -->
                                        <div class="user-mode-fields" style="display: none;">
                                            <div class="form-check mb-2">
                                                <input type="checkbox" class="form-check-input" id="allow_user_whatsapp" name="allow_user_whatsapp" value="true">
                                                <label class="form-check-label" for="allow_user_whatsapp">{{ translate("Allow WhatsApp") }}</label>
                                            </div>
                                            <div class="form-group mb-2">
                                                <label>{{ translate("Device Limit") }}</label>
                                                <input type="number" min="-1" name="user_whatsapp_device_limit" class="form-control" value="1">
                                            </div>
                                            <div class="credit-row">
                                                <div class="form-group">
                                                    <label>{{ translate("Total Credits") }}</label>
                                                    <input type="number" min="-1" name="whatsapp_credit_user" class="form-control credit-input" value="1000">
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ translate("Daily Limit") }}</label>
                                                    <input type="number" min="0" name="whatsapp_credit_per_day_user" class="form-control" value="0">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Email Channel -->
                                <div class="channel-card enabled" data-channel="email">
                                    <div class="channel-card-header">
                                        <div class="channel-info">
                                            <i class="ri-mail-line text-info"></i>
                                            <span>{{ translate("Email") }}</span>
                                        </div>
                                        <div class="switch-wrapper">
                                            <input type="checkbox" class="switch-input channel-toggle" id="channel_email" data-channel="email" checked>
                                            <label for="channel_email" class="toggle"><span></span></label>
                                        </div>
                                    </div>
                                    <div class="channel-card-body">
                                        <!-- Admin Mode -->
                                        <div class="admin-mode-fields">
                                            <input type="hidden" name="allow_admin_email" value="true">
                                            <div class="credit-row">
                                                <div class="form-group">
                                                    <label>{{ translate("Total Credits") }}</label>
                                                    <input type="number" min="-1" name="email_credit_admin" id="email_credit" class="form-control credit-input" value="5000">
                                                    <div class="quick-values">
                                                        <button type="button" class="quick-btn is-unlimited" data-target="email_credit" data-value="-1">&infin;</button>
                                                        <button type="button" class="quick-btn" data-target="email_credit" data-value="1000">1K</button>
                                                        <button type="button" class="quick-btn" data-target="email_credit" data-value="5000">5K</button>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ translate("Daily Limit") }}</label>
                                                    <input type="number" min="0" name="email_credit_per_day_admin" class="form-control" value="0">
                                                </div>
                                            </div>
                                        </div>
                                        <!-- User Mode -->
                                        <div class="user-mode-fields" style="display: none;">
                                            <div class="form-check mb-2">
                                                <input type="checkbox" class="form-check-input" id="mail_multi_gateway" name="mail_multi_gateway" value="true">
                                                <label class="form-check-label" for="mail_multi_gateway">{{ translate("Allow Email Gateways") }}</label>
                                            </div>
                                            <div id="mail_gateways_area" style="display: none;">
                                                <select class="form-select form-select-sm select2-search" id="mail_gateways_select">
                                                    <option value="">{{ translate("Select gateway") }}</option>
                                                    @foreach($mail_credentials as $credential)
                                                        <option value="{{ strtolower($credential) }}">{{ strtoupper($credential) }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="button" class="btn btn-sm btn-primary mt-1 add-mail-gateway">
                                                    <i class="ri-add-line"></i> {{ translate("Add") }}
                                                </button>
                                                <div class="mail-gateways-list mt-2"></div>
                                            </div>
                                            <div class="credit-row mt-2">
                                                <div class="form-group">
                                                    <label>{{ translate("Total Credits") }}</label>
                                                    <input type="number" min="-1" name="email_credit_user" class="form-control credit-input" value="5000">
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ translate("Daily Limit") }}</label>
                                                    <input type="number" min="0" name="email_credit_per_day_user" class="form-control" value="0">
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Domain Limits (shared across modes) -->
                                        <div class="credit-row mt-3 pt-3" style="border-top: 1px solid rgba(0,0,0,.06);">
                                            <div class="form-group">
                                                <label>{{ translate("Sending Domains") }}</label>
                                                <input type="number" min="0" name="max_sending_domains" class="form-control" value="3">
                                                <small class="text-muted">{{ translate("Max authenticated sending domains") }}</small>
                                            </div>
                                            <div class="form-group">
                                                <label>{{ translate("Tracking Domains") }}</label>
                                                <input type="number" min="0" name="max_tracking_domains" class="form-control" value="2">
                                                <small class="text-muted">{{ translate("Max custom tracking domains") }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced Features -->
                    <div class="config-section">
                        <div class="config-section-header">
                            <div class="section-icon" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                                <i class="ri-sparkling-line"></i>
                            </div>
                            <h5>{{ translate("Advanced Features") }}</h5>
                        </div>
                        <div class="config-section-body">
                            <div class="form-row">
                                <!-- Lead Generation -->
                                <div class="feature-toggle-card" id="lead_gen_card">
                                    <div class="feature-header">
                                        <div class="feature-info">
                                            <i class="ri-search-eye-line" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;"></i>
                                            <div>
                                                <h6 class="mb-1">{{ translate("Lead Generation") }}</h6>
                                                <small class="d-block text-muted">{{ translate("Scrape business contacts") }}</small>
                                            </div>
                                        </div>
                                        <div class="switch-wrapper">
                                            <input type="checkbox" class="switch-input feature-toggle" id="lead_generation_enabled" name="lead_generation_enabled" value="true">
                                            <label for="lead_generation_enabled" class="toggle"><span></span></label>
                                        </div>
                                    </div>
                                    <div class="feature-body">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>{{ translate("Daily Limit") }}</label>
                                                <input type="number" min="-1" name="lead_daily_limit" class="form-control" value="100">
                                                <small class="text-muted">-1 = {{ translate("Unlimited") }}</small>
                                            </div>
                                            <div class="form-group">
                                                <label>{{ translate("Monthly Limit") }}</label>
                                                <input type="number" min="-1" name="lead_monthly_limit" class="form-control" value="1000">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- B2B Database Access -->
                                <div class="feature-toggle-card" id="b2b_database_card">
                                    <div class="feature-header">
                                        <div class="feature-info">
                                            <i class="ri-database-2-line" style="background: rgba(16, 185, 129, 0.15); color: #10b981;"></i>
                                            <div>
                                                <h6 class="mb-2 fs-14">{{ translate("B2B Database") }}</h6>
                                                <small class="d-block text-muted">{{ translate("Apollo, Hunter, Snov, RocketReach") }}</small>
                                            </div>
                                        </div>
                                        <div class="switch-wrapper">
                                            <input type="checkbox" class="switch-input feature-toggle" id="b2b_database_enabled" name="b2b_database_enabled" value="true">
                                            <label for="b2b_database_enabled" class="toggle"><span></span></label>
                                        </div>
                                    </div>
                                    <div class="feature-body">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>{{ translate("Daily Lookups") }}</label>
                                                <input type="number" min="-1" name="b2b_daily_lookups" class="form-control" value="100">
                                                <small class="text-muted">-1 = {{ translate("Unlimited") }}</small>
                                            </div>
                                            <div class="form-group">
                                                <label>{{ translate("Monthly Lookups") }}</label>
                                                <input type="number" min="-1" name="b2b_monthly_lookups" class="form-control" value="500">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Workflow Automation -->
                                <div class="feature-toggle-card" id="automation_card">
                                    <div class="feature-header">
                                        <div class="feature-info">
                                            <i class="ri-flow-chart" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;"></i>
                                            <div>
                                                <h6 class="mb-1">{{ translate("Workflow Automation") }}</h6>
                                                <small class="d-block text-muted">{{ translate("Automated sequences") }}</small>
                                            </div>
                                        </div>
                                        <div class="switch-wrapper">
                                            <input type="checkbox" class="switch-input feature-toggle" id="automation_enabled" name="automation_enabled" value="true">
                                            <label for="automation_enabled" class="toggle"><span></span></label>
                                        </div>
                                    </div>
                                    <div class="feature-body">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>{{ translate("Workflow Limit") }}</label>
                                                <input type="number" min="-1" name="automation_workflow_limit" class="form-control" value="5">
                                            </div>
                                            <div class="form-group">
                                                <label>{{ translate("Monthly Executions") }}</label>
                                                <input type="number" min="-1" name="automation_execution_limit" class="form-control" value="1000">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- AI Campaign Intelligence -->
                                <div class="feature-toggle-card" id="ai_intelligence_card">
                                    <div class="feature-header">
                                        <div class="feature-info">
                                            <i class="ri-brain-line" style="background: rgba(246, 59, 96, 0.15); color: rgb(246, 59, 96);"></i>
                                            <!-- <div class="feature-icon bg--primary-light"></div> -->
                                            <div>
                                                <h6 class="mb-1">{{ translate("AI Campaign Intelligence") }}</h6>
                                                <small class="d-block text-muted">{{ translate("Smart insights & A/B testing") }}</small>
                                            </div>
                                        </div>
                                        <div class="switch-wrapper">
                                            <input type="checkbox" class="switch-input feature-toggle" id="ai_intelligence_enabled" name="ai_intelligence_enabled" value="true">
                                            <label for="ai_intelligence_enabled" class="toggle"><span></span></label>
                                        </div>
                                    </div>
                                    <div class="feature-body">
                                        <div class="form-row mb-3">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="ai_campaign_insights" name="ai_campaign_insights" value="true">
                                                <label class="form-check-label" for="ai_campaign_insights">{{ translate("Campaign Insights & Analytics") }}</label>
                                            </div>
                                        </div>
                                        <div class="form-row mb-3">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="ai_ab_testing" name="ai_ab_testing" value="true">
                                                <label class="form-check-label" for="ai_ab_testing">{{ translate("A/B Testing") }}</label>
                                            </div>
                                        </div>
                                        <div class="form-row mb-3">
                                            <div class="form-group">
                                                <label>{{ translate("A/B Test Limit (per month)") }}</label>
                                                <input type="number" min="0" name="ai_ab_test_limit" class="form-control" value="5">
                                                <small class="text-muted">0 = {{ translate("Unlimited") }}</small>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="ai_send_time_optimizer" name="ai_send_time_optimizer" value="true">
                                                <label class="form-check-label" for="ai_send_time_optimizer">{{ translate("Send Time Optimizer") }}</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Display Features (for pricing page) -->
                    @if($displayFeatures->count() > 0)
                    <div class="config-section">
                        <div class="config-section-header">
                            <div class="section-icon" style="background: linear-gradient(135deg, #ec4899, #f472b6);">
                                <i class="ri-list-check-2"></i>
                            </div>
                            <h5>{{ translate("Pricing Page Features") }}</h5>
                        </div>
                        <div class="config-section-body">
                            <div class="display-features-grid">
                                @foreach($displayFeatures as $feature)
                                <label class="display-feature-item" for="display_feature_{{ $feature->id }}">
                                    <input type="checkbox" name="display_features[]" value="{{ $feature->id }}" id="display_feature_{{ $feature->id }}" class="display-feature-checkbox">
                                    <i class="{{ $feature->icon ?? 'ri-checkbox-circle-line' }} feature-icon"></i>
                                    <span class="feature-name">{{ translate($feature->name) }}</span>
                                </label>
                                @endforeach
                            </div>
                            <div class="display-features-help">
                                <i class="ri-information-line"></i>
                                {{ translate("Select features to show with checkmark on pricing page. Unchecked features appear with strikethrough.") }}
                                <a href="{{ route('admin.system.settings.plan-features.index') }}" class="ms-2 manage-feature">{{ translate("Manage Features") }}</a>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Submit Bar -->
                    <div class="submit-bar">
                        <div class="summary-items">
                            <div class="summary-item">
                                <div class="label">{{ translate("Price") }}</div>
                                <div class="value" id="summary_price">$29.99</div>
                            </div>
                            <div class="summary-item">
                                <div class="label">{{ translate("Duration") }}</div>
                                <div class="value" id="summary_duration">30 {{ translate("days") }}</div>
                            </div>
                            <div class="summary-item">
                                <div class="label">{{ translate("Mode") }}</div>
                                <div class="value" id="summary_mode">{{ translate("User") }}</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.membership.plan.index') }}" class="i-btn btn--dark outline btn--md">
                                <i class="ri-arrow-left-line"></i> {{ translate("Cancel") }}
                            </a>
                            <button type="submit" class="i-btn btn--primary btn--md">
                                <i class="ri-check-line"></i> {{ translate("Create Plan") }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Live Preview -->
                <div class="plan-preview-wrapper" id="planPreviewWrapper">
                    <div class="preview-card" style="position: relative;">
                        <button type="button" class="plan-preview-close-btn" id="planPreviewClose"><i class="ri-close-line"></i></button>
                        <div class="preview-card-header">
                            <h6>{{ translate("Live Preview") }}</h6>
                        </div>
                        <div class="preview-card-body">
                            <div class="preview-plan-name" id="preview_name">{{ translate("Plan Name") }}</div>
                            <div class="preview-plan-price">
                                <span class="price-amount" id="preview_price">$29.99</span>
                                <span class="price-period">/ <span id="preview_duration">30</span> {{ translate("days") }}</span>
                            </div>

                            <div class="preview-credits-summary">
                                <div class="credit-item">
                                    <div class="credit-value" id="preview_sms">1000</div>
                                    <div class="credit-label">{{ translate("SMS") }}</div>
                                </div>
                                <div class="credit-item">
                                    <div class="credit-value" id="preview_whatsapp">1000</div>
                                    <div class="credit-label">{{ translate("WhatsApp") }}</div>
                                </div>
                                <div class="credit-item">
                                    <div class="credit-value" id="preview_email">5000</div>
                                    <div class="credit-label">{{ translate("Email") }}</div>
                                </div>
                            </div>

                            <ul class="preview-features-list" id="preview_features">
                                @foreach($displayFeatures as $feature)
                                <li class="excluded" data-feature-id="{{ $feature->id }}">
                                    <i class="ri-close-line excluded"></i>
                                    <span>{{ translate($feature->name) }}</span>
                                </li>
                                @endforeach
                            </ul>

                            <button type="button" class="preview-cta-btn">
                                {{ translate("Choose Plan") }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>

<!-- Floating Preview Button (visible below 1200px) -->
<button type="button" class="plan-preview-fab" id="planPreviewFab">
    <i class="ri-eye-line"></i> {{ translate("Preview") }}
</button>

@endsection

@push('script-include')
<script src="{{ asset('assets/theme/global/js/select2.min.js') }}"></script>
@endpush

@push('script-push')
<script>
(function($) {
    "use strict";

    // Initialize Select2
    if (typeof select2_search === 'function') {
        select2_search($('.select2-search').data('placeholder'));
    } else {
        $('.select2-search').select2({ width: '100%' });
    }

    const currencySymbol = '{{ getDefaultCurrencyCode(json_decode(site_settings("currencies"), true)) }}';

    // Update preview in real-time
    function updatePreview() {
        // Name
        const name = $('#plan_name').val() || '{{ translate("Plan Name") }}';
        $('#preview_name').text(name);

        // Price
        const amount = parseFloat($('#plan_amount').val()) || 0;
        $('#preview_price').text(currencySymbol + ' ' + amount.toFixed(2));
        $('#summary_price').text(currencySymbol + ' ' + amount.toFixed(2));

        // Duration
        const duration = $('#plan_duration').val() || 30;
        $('#preview_duration').text(duration);
        $('#summary_duration').text(duration + ' {{ translate("days") }}');

        // Credits
        const isAdminMode = $('#allow_admin_creds').is(':checked');
        const smsCredit = parseInt(isAdminMode ? $('input[name="sms_credit_admin"]').val() : $('input[name="sms_credit_user"]').val()) || 0;
        const whatsappCredit = parseInt(isAdminMode ? $('input[name="whatsapp_credit_admin"]').val() : $('input[name="whatsapp_credit_user"]').val()) || 0;
        const emailCredit = parseInt(isAdminMode ? $('input[name="email_credit_admin"]').val() : $('input[name="email_credit_user"]').val()) || 0;

        $('#preview_sms').text(formatCredit(smsCredit));
        $('#preview_whatsapp').text(formatCredit(whatsappCredit));
        $('#preview_email').text(formatCredit(emailCredit));

        // Mode summary
        $('#summary_mode').text(isAdminMode ? '{{ translate("Admin") }}' : '{{ translate("User") }}');
    }

    function formatCredit(value) {
        if (value === -1) return '∞';
        if (value >= 1000000) return (value / 1000000).toFixed(1) + 'M';
        if (value >= 1000) return (value / 1000).toFixed(0) + 'K';
        return value;
    }

    // Quick value buttons
    $(document).on('click', '.quick-btn', function(e) {
        e.preventDefault();
        const target = $(this).data('target');
        const value = $(this).data('value');
        $('#' + target).val(value).trigger('input');
    });

    // Gateway mode toggle
    $('#allow_admin_creds').on('change', function() {
        if ($(this).is(':checked')) {
            $('.admin-mode-fields').show();
            $('.user-mode-fields').hide();
        } else {
            $('.admin-mode-fields').hide();
            $('.user-mode-fields').show();
        }
        updatePreview();
    });

    // Channel toggle
    $('.channel-toggle').on('change', function() {
        const card = $(this).closest('.channel-card');
        if ($(this).is(':checked')) {
            card.addClass('enabled');
        } else {
            card.removeClass('enabled');
        }
        updatePreview();
    });

    // Feature toggle
    $('.feature-toggle').on('change', function() {
        const card = $(this).closest('.feature-toggle-card');
        if ($(this).is(':checked')) {
            card.addClass('enabled');
        } else {
            card.removeClass('enabled');
        }
    });

    // Display feature checkboxes
    $('.display-feature-checkbox').on('change', function() {
        const featureId = $(this).val();
        const listItem = $('#preview_features li[data-feature-id="' + featureId + '"]');
        const label = $(this).closest('.display-feature-item');

        if ($(this).is(':checked')) {
            label.addClass('checked');
            listItem.removeClass('excluded');
            listItem.find('i').removeClass('ri-close-line excluded').addClass('ri-check-line included');
        } else {
            label.removeClass('checked');
            listItem.addClass('excluded');
            listItem.find('i').removeClass('ri-check-line included').addClass('ri-close-line excluded');
        }
    });

    // User mode specific toggles
    $('#allow_user_android').on('change', function() {
        $('#android_limit_group').toggle($(this).is(':checked'));
    });

    $('#sms_multi_gateway').on('change', function() {
        $('#sms_gateways_area').toggle($(this).is(':checked'));
    });

    $('#mail_multi_gateway').on('change', function() {
        $('#mail_gateways_area').toggle($(this).is(':checked'));
    });

    // Add SMS Gateway
    $('.add-sms-gateway').on('click', function() {
        const gateway = $('#sms_gateways_select').val();
        if (!gateway) return;

        if ($(`.sms-gateways-list input[value="${gateway}"]`).length > 0) {
            notify('error', '{{ translate("Gateway already added") }}');
            return;
        }

        const html = `
            <div class="d-flex gap-2 mb-2 gateway-item">
                <input type="text" class="form-control form-control-sm" value="${gateway.toUpperCase()}" readonly style="flex: 1;">
                <input type="hidden" name="sms_gateways[]" value="${gateway}">
                <input type="number" name="total_sms_gateway[]" class="form-control form-control-sm" min="1" value="1" style="width: 70px;">
                <button type="button" class="btn btn-sm btn-danger remove-gateway"><i class="ri-delete-bin-line"></i></button>
            </div>
        `;
        $('.sms-gateways-list').append(html);
        $('#sms_gateways_select').val('').trigger('change');
    });

    // Add Mail Gateway
    $('.add-mail-gateway').on('click', function() {
        const gateway = $('#mail_gateways_select').val();
        if (!gateway) return;

        if ($(`.mail-gateways-list input[value="${gateway}"]`).length > 0) {
            notify('error', '{{ translate("Gateway already added") }}');
            return;
        }

        const html = `
            <div class="d-flex gap-2 mb-2 gateway-item">
                <input type="text" class="form-control form-control-sm" value="${gateway.toUpperCase()}" readonly style="flex: 1;">
                <input type="hidden" name="mail_gateways[]" value="${gateway}">
                <input type="number" name="total_mail_gateway[]" class="form-control form-control-sm" min="1" value="1" style="width: 70px;">
                <button type="button" class="btn btn-sm btn-danger remove-gateway"><i class="ri-delete-bin-line"></i></button>
            </div>
        `;
        $('.mail-gateways-list').append(html);
        $('#mail_gateways_select').val('').trigger('change');
    });

    // Remove gateway
    $(document).on('click', '.remove-gateway', function() {
        $(this).closest('.gateway-item').remove();
    });

    // Listen for input changes
    $('#plan_name, #plan_amount, #plan_duration').on('input', updatePreview);
    $('input[name="sms_credit_admin"], input[name="sms_credit_user"]').on('input', updatePreview);
    $('input[name="whatsapp_credit_admin"], input[name="whatsapp_credit_user"]').on('input', updatePreview);
    $('input[name="email_credit_admin"], input[name="email_credit_user"]').on('input', updatePreview);

    // Initialize
    updatePreview();

    // Preview overlay toggle (below 1200px)
    $('#planPreviewFab').on('click', function() {
        $('#planPreviewWrapper').addClass('show');
        $('body').css('overflow', 'hidden');
    });
    $('#planPreviewClose').on('click', function() {
        $('#planPreviewWrapper').removeClass('show');
        $('body').css('overflow', '');
    });
    $('#planPreviewWrapper').on('click', function(e) {
        if (e.target === this) {
            $(this).removeClass('show');
            $('body').css('overflow', '');
        }
    });

})(jQuery);
</script>
@endpush

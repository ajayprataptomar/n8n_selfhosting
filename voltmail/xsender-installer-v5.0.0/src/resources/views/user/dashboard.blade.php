@extends('user.layouts.app')
@push("style-include")
<link rel="stylesheet" href="{{asset('assets/theme/user/css/dashboard.css')}}">
@endpush
@section('panel')
    @php
        $plan_access = planAccess(auth()->user());
    @endphp
    <main class="main-body">
        <div class="container-fluid px-0 main-content">
            <div class="page-header">
                <div class="page-header-left">
                    <h2>{{ $title }} </h2>
                </div>
            </div>

            {{-- Row 0: Setup Guide Banner (only if user has a plan with pending setup) --}}
            @if(!empty($setupGuide['has_plan']) && $setupGuide['percent'] < 100 && !($setupGuide['is_admin_plan'] ?? false))
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="setup-guide">
                        <div class="setup-guide__header">
                            <div class="setup-guide__icon">
                                <i class="ri-rocket-2-line"></i>
                            </div>
                            <div class="setup-guide__info">
                                <h6 class="setup-guide__title">{{ translate('Complete Your Setup') }}</h6>
                                <p class="setup-guide__desc">{{ translate('Configure your gateways to start sending messages') }}</p>
                            </div>
                            <div class="setup-guide__progress-wrap">
                                <span class="setup-guide__progress-label">{{ $setupGuide['completed'] }}/{{ $setupGuide['total'] }}</span>
                                <div class="setup-guide__progress">
                                    <div class="setup-guide__progress-bar" style="width: {{ $setupGuide['percent'] }}%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="setup-guide__items">
                            @foreach($setupGuide['items'] as $item)
                            <a href="{{ $item['route'] }}" class="setup-guide__step {{ $item['done'] ? 'is-done' : 'is-pending' }}">
                                <span class="setup-guide__step-icon">
                                    <i class="{{ $item['done'] ? 'ri-checkbox-circle-fill' : $item['icon'] }}"></i>
                                </span>
                                <span class="setup-guide__step-body">
                                    <span class="setup-guide__step-label">{{ $item['label'] }}</span>
                                    <span class="setup-guide__step-hint">{{ $item['hint'] }}</span>
                                </span>
                                <span class="setup-guide__step-action">
                                    {{ $item['action'] }} <i class="ri-arrow-right-s-line"></i>
                                </span>
                            </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            @elseif(empty($setupGuide['has_plan']))
            {{-- No active plan --}}
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="setup-guide setup-guide--warning">
                        <div class="setup-guide__header">
                            <div class="setup-guide__icon setup-guide__icon--warning">
                                <i class="ri-error-warning-line"></i>
                            </div>
                            <div class="setup-guide__info">
                                <h6 class="setup-guide__title">{{ translate('No Active Plan') }}</h6>
                                <p class="setup-guide__desc">{{ translate('Purchase a plan to unlock messaging features and start sending') }}</p>
                            </div>
                            <a href="{{ route('user.plan.create') }}" class="btn btn-sm setup-guide__cta">
                                <i class="ri-shopping-cart-2-line me-1"></i> {{ translate('Get a Plan') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- Row 1: Quick Stats --}}
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-sm-6">
                    <div class="card overview-card border h-100">
                        <div class="card-body p-3">
                            <div class="overview-card__icon overview-card__icon--primary">
                                <i class="ri-contacts-book-line"></i>
                            </div>
                            <div class="overview-card__value">{{ formatNumber($quickStats['total_contacts']) }}</div>
                            <div class="overview-card__label">{{ translate('Contacts') }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="card overview-card border h-100">
                        <div class="card-body p-3">
                            <div class="overview-card__icon overview-card__icon--success">
                                <i class="ri-group-line"></i>
                            </div>
                            <div class="overview-card__value">{{ formatNumber($quickStats['total_groups']) }}</div>
                            <div class="overview-card__label">{{ translate('Groups') }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="card overview-card border h-100">
                        <div class="card-body p-3">
                            <div class="overview-card__icon overview-card__icon--info">
                                <i class="ri-send-plane-line"></i>
                            </div>
                            <div class="overview-card__value">{{ formatNumber($quickStats['messages_today']) }}</div>
                            <div class="overview-card__label">{{ translate('Messages Today') }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="card overview-card border h-100">
                        <div class="card-body p-3">
                            <div class="overview-card__icon overview-card__icon--warning">
                                <i class="ri-settings-4-line"></i>
                            </div>
                            <div class="overview-card__value">{{ formatNumber($quickStats['total_gateways']) }}</div>
                            <div class="overview-card__label">{{ translate('Active Gateways') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Row 2: Credit Cards --}}
            <div class="row g-4 mb-4" id="user-dashboard-cards-container">
                {{-- Conditional Wallet Balance Card --}}
                @if(site_settings("affiliate_system", \App\Enums\StatusEnum::FALSE->status()) == \App\Enums\StatusEnum::TRUE->status())
                <div class="col-xl-3 col-md-6">
                    <div class="card user-dashboard-wallet-card">
                        <div class="card-body p-4 d-flex flex-column h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="user-dashboard-wallet-icon">
                                        <i class="ri-wallet-3-line"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1 fw-semibold">{{ translate('Wallet Balance') }}</h6>
                                        <small class="text-muted">{{ translate('Available funds') }}</small>
                                    </div>
                                </div>
                               <div class="user-dashboard-growth-indicator"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    title="{{ translate('Monthly growth percentage') }}">
                                    <i class="ri-arrow-up-line"></i>
                                    <span class="user-dashboard-growth-text">{{ $growthRateFormatted }}</span>
                                </div>
                            </div>

                            <div class="user-dashboard-balance-section mb-3 flex-grow-1 d-flex flex-column justify-content-center">
                                <div class="d-flex align-items-baseline gap-2 mb-1">
                                    <h3 class="mb-0 fw-bold user-dashboard-balance-amount">
                                         {{ getDefaultCurrencySymbol()}}
                                    </h3>
                                    <h3 class="mb-0 fw-bold user-dashboard-balance-amount">
                                        {{$user->wallet_balance !== null ? convert_to_default_currency("USD", $user->wallet_balance) : "--" }}</h3>
                                    <span class="text-muted">{{ getDefaultCurrencyCode() }}</span>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-auto">
                                <button class="flex-fill user-dashboard-btn-affiliate show-affiliate-system" type="button" data-bs-toggle="modal" data-bs-target="#affiliateModal">
                                    <i class="ri-settings-3-line me-1"></i> {{ translate("Affiliate") }}
                                </button>
                                <a href="{{ route("user.withdraw.create") }}" class="btn flex-fill user-dashboard-btn-withdraw">
                                    <i class="ri-arrow-up-circle-line me-1"></i>
                                    {{ translate('Withdraw') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- SMS Credit Card --}}
                <div class="@if(site_settings("affiliate_system", \App\Enums\StatusEnum::FALSE->status()) == \App\Enums\StatusEnum::TRUE->status()) col-xl-3 @else col-xl-4 @endif col-md-6">
                    <div class="card credit-card user-dashboard-credit-card" data-credit-type="sms">
                        <div class="card-body p-4 d-flex flex-column h-100">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="user-dashboard-credit-icon user-dashboard-credit-icon-sms">
                                    <i class="ri-message-2-line"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <p class="text-muted mb-1 small">{{ translate('SMS Credit') }}</p>
                                    @if(@$plan_access['sms']['is_allowed'] || @$plan_access['android']['is_allowed'])
                                        <h6 class="mb-0">{{ auth()->user()->sms_credit == -1 ? translate('Unlimited') : formatNumber(auth()->user()->sms_credit) ?? translate('N\A') }}</h6>
                                    @else
                                        <h6 class="mb-0">{{ translate("Disabled") }}</h6>
                                    @endif
                                </div>
                            </div>

                            <div class="flex-grow-1">
                                @if(auth()->user()->sms_credit != -1 && (@$plan_access['sms']['is_allowed'] || @$plan_access['android']['is_allowed']))
                                <div class="user-dashboard-daily-limit mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small text-muted">{{ translate('Daily Limit') }}</span>
                                        <span class="small fw-medium user-dashboard-limit-text" data-credit-type="sms">
                                            <span class="user-dashboard-used-count">{{ $creditUsage['sms']['used'] }}</span> /
                                            <span class="user-dashboard-total-limit">
                                                {{ $creditUsage['sms']['total'] == -1 ? translate('Unlimited') : $creditUsage['sms']['total'] }}
                                            </span>
                                        </span>
                                    </div>
                                    <div class="progress user-dashboard-progress" style="height: 6px;">
                                        <div class="progress-bar user-dashboard-progress-sms" role="progressbar"
                                            style="width: {{ $creditUsage['sms']['total'] > 0 ? ($creditUsage['sms']['used'] / $creditUsage['sms']['total']) * 100 : 0 }}%">
                                        </div>
                                    </div>
                                </div>
                                @elseif(auth()->user()->sms_credit == -1)
                                <div class="mb-3">
                                    <span class="badge user-dashboard-unlimited-badge">
                                        <i class="ri-check-line me-1"></i>{{ translate('Unlimited Daily Limit') }}
                                    </span>
                                </div>
                                @endif
                            </div>

                            <div class="mt-auto">
                                <a href="{{ route('user.plan.create') }}" class="btn w-100 user-dashboard-buy-btn">
                                    {{ translate('Buy Credit') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Email Credit Card --}}
                <div class="@if(site_settings("affiliate_system", \App\Enums\StatusEnum::FALSE->status()) == \App\Enums\StatusEnum::TRUE->status()) col-xl-3 @else col-xl-4 @endif col-md-6">
                    <div class="card credit-card user-dashboard-credit-card" data-credit-type="email">
                        <div class="card-body p-4 d-flex flex-column h-100">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="user-dashboard-credit-icon user-dashboard-credit-icon-email">
                                    <i class="ri-mail-line"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <p class="text-muted mb-1 small">{{ translate('Email Credit') }}</p>
                                    @if(@$plan_access['email']['is_allowed'])
                                        <h6 class="mb-0">{{ auth()->user()->email_credit == -1 ? translate('Unlimited') : formatNumber(auth()->user()->email_credit) ?? translate('N\A') }}</h6>
                                    @else
                                        <h6 class="mb-0">{{ translate("Disabled") }}</h6>
                                    @endif
                                </div>
                            </div>

                            <div class="flex-grow-1">
                                @if(auth()->user()->email_credit != -1 && @$plan_access['email']['is_allowed'])
                                <div class="user-dashboard-daily-limit mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small text-muted">{{ translate('Daily Limit') }}</span>
                                        <span class="small fw-medium user-dashboard-limit-text" data-credit-type="email">
                                            <span class="user-dashboard-used-count">{{ $creditUsage['email']['used'] }}</span> /
                                            <span class="user-dashboard-total-limit">
                                                {{ $creditUsage['email']['total'] == -1 ? translate('Unlimited') : $creditUsage['email']['total'] }}
                                            </span>
                                        </span>
                                    </div>
                                    <div class="progress user-dashboard-progress" style="height: 6px;">
                                        <div class="progress-bar user-dashboard-progress-email" role="progressbar"
                                            style="width: {{ $creditUsage['email']['total'] > 0 ? ($creditUsage['email']['used'] / $creditUsage['email']['total']) * 100 : 0 }}%">
                                        </div>
                                    </div>
                                </div>
                                @elseif(auth()->user()->email_credit == -1)
                                <div class="mb-3">
                                    <span class="badge user-dashboard-unlimited-badge">
                                        <i class="ri-check-line me-1"></i>{{ translate('Unlimited Daily Limit') }}
                                    </span>
                                </div>
                                @endif
                            </div>

                            <div class="mt-auto">
                                <a href="{{ route('user.plan.create') }}" class="btn w-100 user-dashboard-buy-btn">
                                    {{ translate('Buy Credit') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- WhatsApp Credit Card --}}
                <div class="@if(site_settings("affiliate_system", \App\Enums\StatusEnum::FALSE->status()) == \App\Enums\StatusEnum::TRUE->status()) col-xl-3 @else col-xl-4 @endif col-md-6">
                    <div class="card credit-card user-dashboard-credit-card" data-credit-type="whatsapp">
                        <div class="card-body p-4 d-flex flex-column h-100">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="user-dashboard-credit-icon user-dashboard-credit-icon-whatsapp">
                                    <i class="ri-whatsapp-line"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <p class="text-muted mb-1 small">{{ translate('Whatsapp Credit') }}</p>
                                    @if(@$plan_access['whatsapp']['is_allowed'])
                                        <h6 class="mb-0">{{ auth()->user()->whatsapp_credit == -1 ? translate('Unlimited') : formatNumber(auth()->user()->whatsapp_credit) ?? translate('N\A') }}</h6>
                                    @else
                                        <h6 class="mb-0">{{ translate("Disabled") }}</h6>
                                    @endif
                                </div>
                            </div>

                            <div class="flex-grow-1">
                                @if(auth()->user()->whatsapp_credit != -1 && @$plan_access['whatsapp']['is_allowed'])
                                <div class="user-dashboard-daily-limit mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small text-muted">{{ translate('Daily Limit') }}</span>
                                        <span class="small fw-medium user-dashboard-limit-text" data-credit-type="whatsapp">
                                            <span class="user-dashboard-used-count">{{ $creditUsage['whatsapp']['used'] }}</span> /
                                            <span class="user-dashboard-total-limit">
                                                {{ $creditUsage['whatsapp']['total'] == -1 ? translate('Unlimited') : $creditUsage['whatsapp']['total'] }}
                                            </span>
                                        </span>
                                    </div>
                                    <div class="progress user-dashboard-progress" style="height: 6px;">
                                        <div class="progress-bar user-dashboard-progress-whatsapp" role="progressbar"
                                            style="width: {{ $creditUsage['whatsapp']['total'] > 0 ? ($creditUsage['whatsapp']['used'] / $creditUsage['whatsapp']['total']) * 100 : 0 }}%">
                                        </div>
                                    </div>
                                </div>
                                @elseif(auth()->user()->whatsapp_credit == -1)
                                <div class="mb-3">
                                    <span class="badge user-dashboard-unlimited-badge">
                                        <i class="ri-check-line me-1"></i>{{ translate('Unlimited Daily Limit') }}
                                    </span>
                                </div>
                                @endif
                            </div>

                            <div class="mt-auto">
                                <a href="{{ route('user.plan.create') }}" class="btn w-100 user-dashboard-buy-btn">
                                    {{ translate('Buy Credit') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Row 3: Channel Statistics (Full Width) --}}
            <div class="row g-4 mb-4">
                <div class="col-xxl-4 col-xl-4 col-lg-6 col-md-6">
                    <div class="card feature-card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-0 pb-2 px-3 pt-3">
                            <div class="d-flex gap-3 justify-content-between align-items-center w-100">
                                <div class="card-header-left">
                                    <h4 class="card-title mb-0 fw-semibold fs-6">{{ translate("SMS Statistics") }}</h4>
                                </div>
                                <div class="card-header-right">
                                    <span class="fs-4">
                                        <i class="ri-message-2-line"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body pt-1 px-3 pb-3">
                            <div class="row g-2">
                                <div class="col-6">
                                    <a href="{{ route('user.communication.sms.index') }}" target="_blank" class="text-decoration-none">
                                        <div class="feature-status feature-status-primary p-3 rounded-3 border position-relative overflow-hidden">
                                            <div class="d-flex justify-content-start mb-2">
                                                <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-2 shadow-sm">
                                                    <i class="ri-message-2-line"></i>
                                                </span>
                                            </div>
                                            <div class="text-center">
                                                <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['sms']['all']) }}</p>
                                                <p class="mb-0 fw-medium text-uppercase">{{ translate("Total") }}</p>
                                            </div>
                                            <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="{{route('user.communication.sms.index') }}?status={{ \App\Enums\System\CommunicationStatusEnum::DELIVERED->value }}" target="_blank" class="text-decoration-none">
                                        <div class="feature-status feature-status-success p-3 rounded-3 border position-relative overflow-hidden">
                                            <div class="d-flex justify-content-start mb-2">
                                                <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-2 shadow-sm">
                                                    <i class="ri-check-double-line"></i>
                                                </span>
                                            </div>
                                            <div class="text-center">
                                                <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['sms']['success']) }}</p>
                                                <p class="mb-0 fw-medium text-uppercase">{{ translate("Success") }}</p>
                                            </div>
                                            <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="{{route('user.communication.sms.index') }}?status={{ \App\Enums\System\CommunicationStatusEnum::PENDING->value }}" target="_blank" class="text-decoration-none">
                                        <div class="feature-status feature-status-warning p-3 rounded-3 border position-relative overflow-hidden">
                                            <div class="d-flex justify-content-start mb-2">
                                                <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-2 shadow-sm">
                                                    <i class="ri-hourglass-fill"></i>
                                                </span>
                                            </div>
                                            <div class="text-center">
                                                <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['sms']['pending']) }}</p>
                                                <p class="mb-0 fw-medium text-uppercase">{{ translate("Pending") }}</p>
                                            </div>
                                            <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="{{route('user.communication.sms.index') }}?status={{ \App\Enums\System\CommunicationStatusEnum::FAIL->value }}" target="_blank" class="text-decoration-none">
                                        <div class="feature-status feature-status-danger p-3 rounded-3 border position-relative overflow-hidden">
                                            <div class="d-flex justify-content-start mb-2">
                                                <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-2 shadow-sm">
                                                    <i class="ri-mail-close-line"></i>
                                                </span>
                                            </div>
                                            <div class="text-center">
                                                <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['sms']['failed']) }}</p>
                                                <p class="mb-0 fw-medium text-uppercase">{{ translate("Failed") }}</p>
                                            </div>
                                            <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-4 col-xl-4 col-lg-6 col-md-6">
                    <div class="card feature-card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-0 pb-2 px-3 pt-3">
                            <div class="d-flex gap-3 justify-content-between align-items-center w-100">
                                <div class="card-header-left">
                                    <h4 class="card-title mb-0 fw-semibold fs-6">{{ translate("Email Statistics") }}</h4>
                                </div>
                                <div class="card-header-right">
                                    <span class="fs-4">
                                        <i class="ri-mail-line text-danger"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body pt-1 px-3 pb-3">
                            <div class="row g-2">
                                <div class="col-6">
                                    <a href="{{route('user.communication.email.index') }}" target="_blank" class="text-decoration-none">
                                        <div class="feature-status feature-status-primary p-3 rounded-3 border position-relative overflow-hidden">
                                            <div class="d-flex justify-content-start mb-2">
                                                <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-2 shadow-sm">
                                                    <i class="ri-mail-line"></i>
                                                </span>
                                            </div>
                                            <div class="text-center">
                                                <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['email']['all']) }}</p>
                                                <p class="mb-0 fw-medium text-uppercase">{{ translate("Total") }}</p>
                                            </div>
                                            <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="{{route('user.communication.email.index') }}?status={{ \App\Enums\System\CommunicationStatusEnum::DELIVERED->value }}" target="_blank" class="text-decoration-none">
                                        <div class="feature-status feature-status-success p-3 rounded-3 border position-relative overflow-hidden">
                                            <div class="d-flex justify-content-start mb-2">
                                                <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-2 shadow-sm">
                                                    <i class="ri-check-double-line"></i>
                                                </span>
                                            </div>
                                            <div class="text-center">
                                                <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['email']['success']) }}</p>
                                                <p class="mb-0 fw-medium text-uppercase">{{ translate("Success") }}</p>
                                            </div>
                                            <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="{{route('user.communication.email.index') }}?status={{ \App\Enums\System\CommunicationStatusEnum::PENDING->value }}" target="_blank" class="text-decoration-none">
                                        <div class="feature-status feature-status-warning p-3 rounded-3 border position-relative overflow-hidden">
                                            <div class="d-flex justify-content-start mb-2">
                                                <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-2 shadow-sm">
                                                    <i class="ri-hourglass-fill"></i>
                                                </span>
                                            </div>
                                            <div class="text-center">
                                                <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['email']['pending']) }}</p>
                                                <p class="mb-0 fw-medium text-uppercase">{{ translate("Pending") }}</p>
                                            </div>
                                            <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="{{route('user.communication.email.index') }}?status={{ \App\Enums\System\CommunicationStatusEnum::FAIL->value }}" target="_blank" class="text-decoration-none">
                                        <div class="feature-status feature-status-danger p-3 rounded-3 border position-relative overflow-hidden">
                                            <div class="d-flex justify-content-start mb-2">
                                                <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-2 shadow-sm">
                                                    <i class="ri-mail-close-line"></i>
                                                </span>
                                            </div>
                                            <div class="text-center">
                                                <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['email']['failed']) }}</p>
                                                <p class="mb-0 fw-medium text-uppercase">{{ translate("Failed") }}</p>
                                            </div>
                                            <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-4 col-xl-4 col-lg-6 col-md-6">
                    <div class="card feature-card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-0 pb-2 px-3 pt-3">
                            <div class="d-flex gap-3 justify-content-between align-items-center w-100">
                                <div class="card-header-left">
                                    <h4 class="card-title mb-0 fw-semibold fs-6">{{ translate("Whatsapp Statistics") }}</h4>
                                </div>
                                <div class="card-header-right">
                                    <span class="fs-4">
                                        <i class="ri-whatsapp-line text-success"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body pt-1 px-3 pb-3">
                            <div class="row g-2">
                                <div class="col-6">
                                    <a href="{{route('user.communication.whatsapp.index') }}" target="_blank" class="text-decoration-none">
                                        <div class="feature-status feature-status-primary p-3 rounded-3 border position-relative overflow-hidden">
                                            <div class="d-flex justify-content-start mb-2">
                                                <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-2 shadow-sm">
                                                    <i class="ri-whatsapp-line"></i>
                                                </span>
                                            </div>
                                            <div class="text-center">
                                                <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['whats_app']['all']) }}</p>
                                                <p class="mb-0 fw-medium text-uppercase">{{ translate("Total") }}</p>
                                            </div>
                                            <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="{{route('user.communication.whatsapp.index') }}?status={{ \App\Enums\System\CommunicationStatusEnum::DELIVERED->value }}" target="_blank" class="text-decoration-none">
                                        <div class="feature-status feature-status-success p-3 rounded-3 border position-relative overflow-hidden">
                                            <div class="d-flex justify-content-start mb-2">
                                                <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-2 shadow-sm">
                                                    <i class="ri-check-double-line"></i>
                                                </span>
                                            </div>
                                            <div class="text-center">
                                                <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['whats_app']['success']) }}</p>
                                                <p class="mb-0 fw-medium text-uppercase">{{ translate("Success") }}</p>
                                            </div>
                                            <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="{{route('user.communication.whatsapp.index') }}?status={{ \App\Enums\System\CommunicationStatusEnum::PENDING->value }}" target="_blank" class="text-decoration-none">
                                        <div class="feature-status feature-status-warning p-3 rounded-3 border position-relative overflow-hidden">
                                            <div class="d-flex justify-content-start mb-2">
                                                <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-2 shadow-sm">
                                                    <i class="ri-hourglass-fill"></i>
                                                </span>
                                            </div>
                                            <div class="text-center">
                                                <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['whats_app']['pending']) }}</p>
                                                <p class="mb-0 fw-medium text-uppercase">{{ translate("Pending") }}</p>
                                            </div>
                                            <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="{{route('user.communication.whatsapp.index') }}?status={{ \App\Enums\System\CommunicationStatusEnum::FAIL->value }}" target="_blank" class="text-decoration-none">
                                        <div class="feature-status feature-status-danger p-3 rounded-3 border position-relative overflow-hidden">
                                            <div class="d-flex justify-content-start mb-2">
                                                <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-2 shadow-sm">
                                                    <i class="ri-mail-close-line"></i>
                                                </span>
                                            </div>
                                            <div class="text-center">
                                                <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['whats_app']['failed']) }}</p>
                                                <p class="mb-0 fw-medium text-uppercase">{{ translate("Failed") }}</p>
                                            </div>
                                            <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Row 4: Tables --}}
            <div class="row g-4">
                <div class="col-xxl-6">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-header-left">
                                <h4 class="card-title">{{ translate('Latest Credit Log') }}</h4>
                            </div>
                        </div>
                        <div class="card-body px-0 pt-0">
                            <div class="table-container">
                                <div class="default_table">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>{{ translate('Date') }}</th>
                                                <th>{{ translate('Trx Number') }}</th>
                                                <th>{{ translate('Channel') }}</th>
                                                <th>{{ translate('Previous Credit') }}</th>
                                                <th>{{ translate('Credit') }}</th>
                                            </tr>
                                        </thead>
                                        @forelse($credits as $credit_data)
                                            <tr class="@if ($loop->even) @endif">
                                                <td data-label="{{ translate('Date') }}">
                                                    <span>{{ diffForHumans($credit_data->created_at) }}</span><br>
                                                    {{ getDateTime($credit_data->created_at) }}
                                                </td>

                                                <td data-label="{{ translate('Trx Number') }}">
                                                    {{ $credit_data->trx_number }}
                                                </td>

                                                <td data-label="{{ translate('Channel') }}">
                                                    <span
                                                        class="i-badge {{ $credit_data->type == \App\Enums\ServiceType::SMS->value ? 'info-soft' : ($credit_data->type == \App\Enums\ServiceType::WHATSAPP->value ? 'success-soft' : 'warning-soft') }}">{{ ucfirst(strtolower(\App\Enums\ServiceType::keyVal((int)$credit_data->type))) }}
                                                    </span>
                                                </td>
                                                <td data-label="{{ translate('Previous Credit') }}">
                                                    {{ $credit_data->post_credit }} {{ translate('Credit') }}
                                                </td>
                                                <td data-label="{{ translate('Credit') }}">
                                                    <span
                                                        class="i-badge {{ $credit_data->credit_type == \App\Enums\StatusEnum::TRUE->status() ? 'success-soft' : 'danger-soft' }}">{{ $credit_data->credit_type == \App\Enums\StatusEnum::TRUE->status() ? '+' : '-' }}
                                                        {{ $credit_data->credit }}
                                                    </span>
                                                </td>

                                            </tr>
                                        @empty
                                            <tr>
                                                <td class="text-muted text-center" colspan="100%">
                                                    {{ translate('No Data Found') }}</td>
                                            </tr>
                                        @endforelse
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xxl-6">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-header-left">
                                <h4 class="card-title">{{ translate('Latest Transactions Log') }}</h4>
                            </div>
                        </div>
                        <div class="card-body px-0 pt-0">
                            <div class="table-container">
                                <div class="default_table">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>{{ translate('Date') }}</th>
                                                <th>{{ translate('Trx Number') }}</th>
                                                <th>{{ translate('Amount') }}</th>
                                                <th>{{ translate('Detail') }}</th>
                                            </tr>
                                        </thead>
                                        @forelse($transactions as $transaction)
                                            <tr class="@if ($loop->even) @endif">
                                                <td data-label="{{ translate('Date') }}">
                                                    <span>{{ diffForHumans($transaction->created_at) }}</span><br>
                                                    {{ getDateTime($transaction->created_at) }}
                                                </td>

                                                <td data-label="{{ translate('Trx Number') }}">
                                                    {{ $transaction->transaction_number }}
                                                </td>

                                                <td data-label="{{ translate('Amount') }}">
                                                    <span
                                                        class="i-badge @if ($transaction->transaction_type == '+') success-soft @else danger-soft @endif">{{ $transaction->transaction_type }}
                                                        {{ shortAmount($transaction->amount) }}
                                                    </span>
                                                </td>

                                                <td data-label="{{ translate('Details') }}">
                                                    {{ $transaction->details }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td class="text-muted text-center" colspan="100%">
                                                    {{ translate('No Data Found') }}</td>
                                            </tr>
                                        @endforelse
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
@section('modal')
@if(site_settings("affiliate_system", \App\Enums\StatusEnum::FALSE->status()) == \App\Enums\StatusEnum::TRUE->status())
<div class="modal fade" id="affiliateModal" tabindex="-1" aria-labelledby="affiliateModal" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Affiliate System") }} </h5>
                <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                    <i class="ri-close-large-line"></i>
                </button>
            </div>
            <div class="modal-body modal-md-custom-height">
                <div class="row g-4">
                    <div class="col-lg-12">
                        <!-- How It Works Steps -->
                        <div class="affiliate-modal-steps-container mb-4">
                            <h6 class="affiliate-modal-steps-title mb-3">{{ translate("How It Works") }}</h6>
                            <div class="row g-3">
                                <!-- Step 1 -->
                                <div class="col-md-6 col-sm-12">
                                    <div class="affiliate-modal-step-card">
                                        <div class="affiliate-modal-step-icon">
                                            <i class="ri-share-line"></i>
                                        </div>
                                        <div class="affiliate-modal-step-content">
                                            <h6 class="affiliate-modal-step-number">{{ translate("Step 1") }}</h6>
                                            <p class="affiliate-modal-step-text">{{ translate("Share your unique affiliate link with friends and family") }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 2 -->
                                <div class="col-md-6 col-sm-12">
                                    <div class="affiliate-modal-step-card">
                                        <div class="affiliate-modal-step-icon">
                                            <i class="ri-user-add-line"></i>
                                        </div>
                                        <div class="affiliate-modal-step-content">
                                            <h6 class="affiliate-modal-step-number">{{ translate("Step 2") }}</h6>
                                            <p class="affiliate-modal-step-text">{{ translate("When someone registers using your link, they become your referral") }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 3 -->
                                <div class="col-md-6 col-sm-12">
                                    <div class="affiliate-modal-step-card">
                                        <div class="affiliate-modal-step-icon">
                                            <i class="ri-money-dollar-circle-line"></i>
                                        </div>
                                        <div class="affiliate-modal-step-content">
                                            <h6 class="affiliate-modal-step-number">{{ translate("Step 3") }}</h6>
                                            <p class="affiliate-modal-step-text">{{ translate("Earn commissions from their activities and purchases") }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 4 -->
                                <div class="col-md-6 col-sm-12">
                                    <div class="affiliate-modal-step-card">
                                        <div class="affiliate-modal-step-icon">
                                            <i class="ri-wallet-line"></i>
                                        </div>
                                        <div class="affiliate-modal-step-content">
                                            <h6 class="affiliate-modal-step-number">{{ translate("Step 4") }}</h6>
                                            <p class="affiliate-modal-step-text">{{ translate("Check your wallet and withdraw from the available withdraw options") }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-inner">
                            <label for="affiliateLink" class="form-label">
                                {{ translate("Affiliate Link") }}
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="affiliateLink" value="{{route("register")."/".$user->uid}}" aria-label="affiliate link" aria-describedby="basic-addon2">
                                <span class="input-group-text bg--primary pointer" onclick="myFunction()" id="basic-addon2">
                                    <i class="me-1 las la-copy fs-5"></i> {{translate('Copy')}}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
@push('script-push')
    <script>
        (function($){
            "use strict";
            window.myFunction = function() {
                var copyText = document.getElementById("affiliateLink");
                copyText.select();
                copyText.setSelectionRange(0, 99999);
                document.execCommand("copy");
                notify('success', 'Copied the text : ' + copyText.value);
            }

            /**
             * User Dashboard Credit Management
             */
            class UserDashboardManager {
                constructor(creditData) {
                    this.creditData = creditData;
                    this.init();
                }
                init() {
                    this.initializeTooltips();
                    this.updateProgressBars();
                    this.bindEvents();
                    this.startPeriodicUpdate();
                }
                initializeTooltips() {
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.map(function (tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                }
                getCSSVariableValue(variableName) {
                    return getComputedStyle(document.documentElement)
                        .getPropertyValue(variableName)
                        .trim();
                }
                updateProgressBars() {
                    Object.keys(this.creditData).forEach(creditType => {
                        const data = this.creditData[creditType];
                        const percentage = data.total > 0 ? (data.used / data.total) * 100 : 0;
                        const progressBar = document.querySelector(`.user-dashboard-progress-${creditType}`);
                        if (progressBar) {
                            progressBar.style.width = `${percentage}%`;
                            progressBar.setAttribute('aria-valuenow', percentage);
                        }
                        const limitText = document.querySelector(`[data-credit-type="${creditType}"] .user-dashboard-limit-text`);
                        if (limitText) {
                            const usedSpan = limitText.querySelector('.user-dashboard-used-count');
                            const totalSpan = limitText.querySelector('.user-dashboard-total-limit');
                            if (usedSpan) usedSpan.textContent = data.used;
                            if (totalSpan) totalSpan.textContent = data.total;
                        }
                        data.available = data.total - data.used;
                    });
                }
                updateCreditUsage(creditType, used, total = null) {
                    if (this.creditData[creditType]) {
                        this.creditData[creditType].used = used;
                        if (total !== null) {
                            this.creditData[creditType].total = total;
                        }
                        this.updateProgressBars();
                    }
                }
                getCreditData() {
                    return this.creditData;
                }
                bindEvents() {
                    const affiliateBtn = document.querySelector('.user-dashboard-btn-affiliate');
                    const withdrawBtn = document.querySelector('.user-dashboard-btn-withdraw');
                    const buyBtns = document.querySelectorAll('.user-dashboard-buy-btn');
                    [affiliateBtn, withdrawBtn, ...buyBtns].forEach(btn => {
                        if (btn) {
                            btn.addEventListener('click', this.handleButtonClick.bind(this));
                            btn.addEventListener('mousedown', this.createRipple.bind(this));
                        }
                    });
                    const growthIndicator = document.querySelector('.user-dashboard-growth-indicator');
                    if (growthIndicator) {
                        growthIndicator.addEventListener('mouseenter', this.handleGrowthHover.bind(this));
                    }
                    [affiliateBtn, withdrawBtn, ...buyBtns].forEach(btn => {
                        if (btn) {
                            btn.addEventListener('focus', this.handleButtonFocus.bind(this));
                            btn.addEventListener('blur', this.handleButtonBlur.bind(this));
                        }
                    });
                }
                createRipple(event) {
                    const button = event.currentTarget;
                    const rect = button.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = event.clientX - rect.left - size / 2;
                    const y = event.clientY - rect.top - size / 2;
                    const ripple = document.createElement('span');
                    ripple.classList.add('user-dashboard-ripple');
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    button.appendChild(ripple);
                    setTimeout(() => {
                        if (ripple.parentNode) {
                            ripple.parentNode.removeChild(ripple);
                        }
                    }, 300);
                }
                handleButtonClick(event) {
                    const button = event.currentTarget;
                    button.style.transform = 'translateY(0) scale(0.98)';
                    setTimeout(() => {
                        button.style.transform = '';
                    }, 150);
                }
                handleButtonFocus(event) {
                    const button = event.currentTarget;
                    button.style.animation = 'user-dashboard-focus-pulse 1s ease-in-out infinite';
                }
                handleButtonBlur(event) {
                    const button = event.currentTarget;
                    button.style.animation = '';
                }
                handleGrowthHover(event) {
                    const indicator = event.currentTarget;
                    indicator.style.animation = 'user-dashboard-pulse 0.6s ease-in-out';
                    setTimeout(() => {
                        indicator.style.animation = '';
                    }, 600);
                }
                startPeriodicUpdate() {
                    setInterval(() => {
                        this.updateProgressBars();
                    }, 30000);
                }
            }
            window.UserDashboardManager = UserDashboardManager;
        })(jQuery);
        document.addEventListener('DOMContentLoaded', function() {
            window.userDashboard = new UserDashboardManager(@json($creditUsage));
        });
    </script>
@endpush

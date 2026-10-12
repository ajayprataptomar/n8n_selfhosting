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
                            <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.onboarding.index') }}">{{ translate('Client Onboarding') }}</a></li>
                            <li class="breadcrumb-item active">{{ translate('Details') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                @if($onboarding->canRetry())
                <form action="{{ route('admin.whatsapp.onboarding.retry', $onboarding->uid) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="i-btn btn--warning btn--md">
                        <i class="ri-refresh-line"></i> {{ translate('Retry Onboarding') }}
                    </button>
                </form>
                @endif
                <a href="{{ route('admin.whatsapp.onboarding.index') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-arrow-left-line"></i> {{ translate('Back') }}
                </a>
            </div>
        </div>

        <div class="row g-4">
            {{-- Main Content --}}
            <div class="col-xxl-8 col-xl-8">
                {{-- Status Overview Card --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="card-header-left">
                            <h4 class="card-title">{{ translate('Onboarding Status') }}</h4>
                        </div>
                        <div class="card-header-right">
                            @php
                                $statusClass = match($onboarding->onboarding_status) {
                                    'completed' => 'success-solid',
                                    'failed' => 'danger-solid',
                                    'initiated', 'waba_connected', 'phone_selected' => 'warning-solid',
                                    default => 'secondary-solid'
                                };
                            @endphp
                            <span class="i-badge {{ $statusClass }} pill">
                                {{ ucfirst(str_replace('_', ' ', $onboarding->onboarding_status)) }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        {{-- Progress Bar --}}
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">{{ translate('Progress') }}</span>
                                <span class="fw-semibold">{{ $onboarding->getProgressPercentage() }}%</span>
                            </div>
                            @php
                                $progressClass = match($onboarding->onboarding_status) {
                                    'completed' => 'success',
                                    'failed' => 'danger',
                                    default => 'primary'
                                };
                            @endphp
                            <div class="progress" style="height: 10px; border-radius: 5px;">
                                <div class="progress-bar bg-{{ $progressClass }}" style="width: {{ $onboarding->getProgressPercentage() }}%; border-radius: 5px;"></div>
                            </div>
                        </div>

                        {{-- Steps Timeline --}}
                        <div class="onboarding-timeline">
                            <div class="timeline-item {{ $onboarding->initiated_at ? 'completed' : 'pending' }}">
                                <div class="timeline-marker">
                                    @if($onboarding->initiated_at)
                                        <i class="ri-checkbox-circle-fill"></i>
                                    @else
                                        <i class="ri-time-line"></i>
                                    @endif
                                </div>
                                <div class="timeline-content">
                                    <h6 class="timeline-title">{{ translate('Initiated') }}</h6>
                                    <p class="timeline-text">
                                        {{ $onboarding->initiated_at ? $onboarding->initiated_at->format('M d, Y H:i:s') : translate('Pending') }}
                                    </p>
                                </div>
                            </div>

                            <div class="timeline-item {{ $onboarding->waba_id ? 'completed' : 'pending' }}">
                                <div class="timeline-marker">
                                    @if($onboarding->waba_id)
                                        <i class="ri-checkbox-circle-fill"></i>
                                    @else
                                        <i class="ri-building-line"></i>
                                    @endif
                                </div>
                                <div class="timeline-content">
                                    <h6 class="timeline-title">{{ translate('WABA Connected') }}</h6>
                                    <p class="timeline-text">
                                        {{ $onboarding->waba_id ?? translate('Pending') }}
                                    </p>
                                </div>
                            </div>

                            <div class="timeline-item {{ $onboarding->phone_number_id ? 'completed' : 'pending' }}">
                                <div class="timeline-marker">
                                    @if($onboarding->phone_number_id)
                                        <i class="ri-checkbox-circle-fill"></i>
                                    @else
                                        <i class="ri-phone-line"></i>
                                    @endif
                                </div>
                                <div class="timeline-content">
                                    <h6 class="timeline-title">{{ translate('Phone Registered') }}</h6>
                                    <p class="timeline-text">
                                        {{ $onboarding->phone_number ?? translate('Pending') }}
                                    </p>
                                </div>
                            </div>

                            <div class="timeline-item {{ $onboarding->gateway_id ? 'completed' : 'pending' }}">
                                <div class="timeline-marker">
                                    @if($onboarding->gateway_id)
                                        <i class="ri-checkbox-circle-fill"></i>
                                    @else
                                        <i class="ri-link"></i>
                                    @endif
                                </div>
                                <div class="timeline-content">
                                    <h6 class="timeline-title">{{ translate('Gateway Created') }}</h6>
                                    <p class="timeline-text">
                                        @if($onboarding->gateway_id && $onboarding->gateway)
                                            <a href="{{ route('admin.gateway.whatsapp.edit', $onboarding->gateway->uid ?? '') }}" class="text--primary">
                                                <i class="ri-external-link-line"></i> {{ translate('View Gateway') }}
                                            </a>
                                        @else
                                            {{ translate('Pending') }}
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <div class="timeline-item {{ $onboarding->completed_at ? 'completed' : 'pending' }}">
                                <div class="timeline-marker">
                                    @if($onboarding->completed_at)
                                        <i class="ri-checkbox-circle-fill"></i>
                                    @else
                                        <i class="ri-flag-line"></i>
                                    @endif
                                </div>
                                <div class="timeline-content">
                                    <h6 class="timeline-title">{{ translate('Completed') }}</h6>
                                    <p class="timeline-text">
                                        {{ $onboarding->completed_at ? $onboarding->completed_at->format('M d, Y H:i:s') : translate('Pending') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- WABA Details Card --}}
                @if($onboarding->waba_id)
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="card-header-left">
                            <h4 class="card-title">
                                <i class="ri-building-line me-2"></i>{{ translate('WhatsApp Business Account') }}
                            </h4>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="info-block">
                                    <span class="info-label">{{ translate('WABA ID') }}</span>
                                    <span class="info-value"><code>{{ $onboarding->waba_id }}</code></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-block">
                                    <span class="info-label">{{ translate('Business Name') }}</span>
                                    <span class="info-value">{{ $onboarding->waba_name ?? '-' }}</span>
                                </div>
                            </div>
                            @if($onboarding->business_id)
                            <div class="col-md-6">
                                <div class="info-block">
                                    <span class="info-label">{{ translate('Business ID') }}</span>
                                    <span class="info-value"><code>{{ $onboarding->business_id }}</code></span>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                {{-- Phone Details Card --}}
                @if($onboarding->phone_number_id)
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="card-header-left">
                            <h4 class="card-title">
                                <i class="ri-phone-line me-2"></i>{{ translate('Phone Number') }}
                            </h4>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="info-block">
                                    <span class="info-label">{{ translate('Phone Number') }}</span>
                                    <span class="info-value fw-semibold">{{ $onboarding->phone_number }}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-block">
                                    <span class="info-label">{{ translate('Phone Number ID') }}</span>
                                    <span class="info-value"><code>{{ $onboarding->phone_number_id }}</code></span>
                                </div>
                            </div>
                            @if($onboarding->verified_name)
                            <div class="col-md-6">
                                <div class="info-block">
                                    <span class="info-label">{{ translate('Verified Name') }}</span>
                                    <span class="info-value">{{ $onboarding->verified_name }}</span>
                                </div>
                            </div>
                            @endif
                            @if($onboarding->quality_rating)
                            <div class="col-md-6">
                                <div class="info-block">
                                    <span class="info-label">{{ translate('Quality Rating') }}</span>
                                    <span class="info-value">
                                        @php
                                            $qualityClass = match(strtoupper($onboarding->quality_rating)) {
                                                'GREEN' => 'success-solid',
                                                'YELLOW' => 'warning-solid',
                                                'RED' => 'danger-solid',
                                                default => 'secondary-solid'
                                            };
                                        @endphp
                                        <span class="i-badge {{ $qualityClass }} pill">{{ $onboarding->quality_rating }}</span>
                                    </span>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                {{-- Error Details Card --}}
                @if($onboarding->error_message || $onboarding->error_details)
                <div class="card mb-4 border-danger">
                    <div class="card-header bg-danger">
                        <div class="card-header-left">
                            <h4 class="card-title text-white">
                                <i class="ri-error-warning-line me-2"></i>{{ translate('Error Details') }}
                            </h4>
                        </div>
                        @if($onboarding->last_error_at)
                        <div class="card-header-right">
                            <small class="text-white-50">{{ $onboarding->last_error_at->format('M d, Y H:i:s') }}</small>
                        </div>
                        @endif
                    </div>
                    <div class="card-body pt-0">
                        @if($onboarding->error_message)
                        <div class="note-container mb-3">
                            <div class="note note--danger">
                                <div class="note-body">
                                    <div class="note-icon">
                                        <i class="ri-close-circle-line"></i>
                                    </div>
                                    <div class="note-content">
                                        <p class="note-text mb-0">{{ $onboarding->error_message }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if($onboarding->error_details)
                        <div class="mb-2">
                            <strong>{{ translate('Technical Details:') }}</strong>
                        </div>
                        <div class="code-block">
                            <pre><code>{{ json_encode($onboarding->error_details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="col-xxl-4 col-xl-4">
                {{-- Client Information Card --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="card-header-left">
                            <h4 class="card-title">{{ translate('Client Information') }}</h4>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        @if($onboarding->user)
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="user-avatar">
                                <img src="{{ $onboarding->user->getProfileImage() ?? asset('assets/images/default-avatar.png') }}"
                                     alt="{{ $onboarding->user->name }}" class="rounded-circle">
                            </div>
                            <div class="user-info">
                                <h6 class="mb-1">{{ $onboarding->user->name }}</h6>
                                <small class="text-muted">{{ $onboarding->user->email }}</small>
                            </div>
                        </div>
                        <a href="{{ route('admin.user.details', $onboarding->user->uid) }}" class="i-btn btn--primary outline btn--md w-100">
                            <i class="ri-user-line"></i> {{ translate('View User Profile') }}
                        </a>
                        @else
                        <div class="text-center py-4">
                            <div class="empty-icon mb-3">
                                <i class="ri-admin-line"></i>
                            </div>
                            <p class="text-muted mb-0">{{ translate('Admin Initiated Onboarding') }}</p>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Configuration Card --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="card-header-left">
                            <h4 class="card-title">{{ translate('Configuration') }}</h4>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <ul class="info-list">
                            <li class="info-list-item">
                                <span class="info-list-label">{{ translate('Meta Config') }}</span>
                                <span class="info-list-value">
                                    @if($onboarding->metaConfiguration)
                                        <a href="{{ route('admin.whatsapp.configuration.edit', $onboarding->metaConfiguration->uid) }}" class="text--primary">
                                            {{ $onboarding->metaConfiguration->name }}
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </span>
                            </li>
                            <li class="info-list-item">
                                <span class="info-list-label">{{ translate('State Token') }}</span>
                                <span class="info-list-value">
                                    <code class="small">{{ Str::limit($onboarding->state, 12) }}</code>
                                </span>
                            </li>
                            <li class="info-list-item">
                                <span class="info-list-label">{{ translate('Retry Count') }}</span>
                                <span class="info-list-value">
                                    <span class="i-badge secondary-solid pill">{{ $onboarding->retry_count }}</span>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>

                {{-- Token Status Card --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="card-header-left">
                            <h4 class="card-title">{{ translate('Token Status') }}</h4>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        @if($onboarding->user_access_token)
                        <div class="token-status success">
                            <i class="ri-checkbox-circle-fill"></i>
                            <span>{{ translate('User Access Token') }}</span>
                        </div>
                        @if($onboarding->token_expires_at)
                        <div class="mt-2">
                            <small class="text-muted">{{ translate('Expires:') }} {{ $onboarding->token_expires_at->format('M d, Y H:i') }}</small>
                            @if($onboarding->token_expires_at->isPast())
                                <span class="i-badge danger-solid pill ms-1">{{ translate('Expired') }}</span>
                            @elseif($onboarding->token_expires_at->diffInDays() < 7)
                                <span class="i-badge warning-solid pill ms-1">{{ translate('Expiring Soon') }}</span>
                            @else
                                <span class="i-badge success-soft pill ms-1">{{ translate('Valid') }}</span>
                            @endif
                        </div>
                        @endif
                        @else
                        <div class="token-status muted">
                            <i class="ri-close-circle-line"></i>
                            <span>{{ translate('No token captured') }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Timeline Card --}}
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <h4 class="card-title">{{ translate('Timeline') }}</h4>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <ul class="info-list">
                            <li class="info-list-item">
                                <span class="info-list-label">{{ translate('Created') }}</span>
                                <span class="info-list-value">
                                    <small>{{ $onboarding->created_at->format('M d, Y H:i') }}</small>
                                </span>
                            </li>
                            @if($onboarding->initiated_at)
                            <li class="info-list-item">
                                <span class="info-list-label">{{ translate('Initiated') }}</span>
                                <span class="info-list-value">
                                    <small>{{ $onboarding->initiated_at->format('M d, Y H:i') }}</small>
                                </span>
                            </li>
                            @endif
                            @if($onboarding->completed_at)
                            <li class="info-list-item">
                                <span class="info-list-label">{{ translate('Completed') }}</span>
                                <span class="info-list-value">
                                    <small class="text--success">{{ $onboarding->completed_at->format('M d, Y H:i') }}</small>
                                </span>
                            </li>
                            @endif
                            <li class="info-list-item">
                                <span class="info-list-label">{{ translate('Last Updated') }}</span>
                                <span class="info-list-value">
                                    <small>{{ $onboarding->updated_at->format('M d, Y H:i') }}</small>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

@endsection

@push('style-push')
<style>
/* Timeline Styles */
.onboarding-timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    padding-bottom: 24px;
}

.timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: -23px;
    top: 28px;
    bottom: 0;
    width: 2px;
    background: var(--border-color, #e5e7eb);
}

.timeline-item.completed:not(:last-child)::before {
    background: var(--success-color, #10b981);
}

.timeline-marker {
    position: absolute;
    left: -32px;
    width: 24px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.timeline-item.completed .timeline-marker {
    color: var(--success-color, #10b981);
}

.timeline-item.pending .timeline-marker {
    color: var(--muted-color, #9ca3af);
}

.timeline-content {
    padding-left: 8px;
}

.timeline-title {
    margin-bottom: 4px;
    font-size: 14px;
    font-weight: 600;
}

.timeline-text {
    margin-bottom: 0;
    font-size: 13px;
    color: var(--muted-color, #6b7280);
}

/* Info Block Styles */
.info-block {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.info-label {
    font-size: 12px;
    color: var(--muted-color, #6b7280);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 14px;
}

/* Info List Styles */
.info-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color, #e5e7eb);
}

.info-list-item:last-child {
    border-bottom: none;
}

.info-list-label {
    color: var(--muted-color, #6b7280);
    font-size: 13px;
}

.info-list-value {
    font-size: 13px;
}

/* User Avatar */
.user-avatar {
    width: 50px;
    height: 50px;
    flex-shrink: 0;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-info h6 {
    font-weight: 600;
}

/* Token Status */
.token-status {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px;
    border-radius: 8px;
}

.token-status.success {
    background: rgba(var(--success-rgb, 16, 185, 129), 0.1);
    color: var(--success-color, #10b981);
}

.token-status.muted {
    background: var(--bg-light, #f3f4f6);
    color: var(--muted-color, #6b7280);
}

.token-status i {
    font-size: 18px;
}

/* Code Block */
.code-block {
    background: #1e293b;
    border-radius: 8px;
    padding: 16px;
    overflow-x: auto;
}

.code-block pre {
    margin: 0;
    color: #e2e8f0;
    font-size: 12px;
    line-height: 1.6;
}

.code-block code {
    color: inherit;
}

/* Empty Icon */
.empty-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--bg-light, #f3f4f6);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.empty-icon i {
    font-size: 28px;
    color: var(--muted-color, #6b7280);
}

/* Border Danger Card */
.card.border-danger {
    border: 1px solid var(--danger-color, #ef4444) !important;
}

.card-header.bg-danger {
    background: var(--danger-color, #ef4444) !important;
    border-bottom: none;
}
</style>
@endpush

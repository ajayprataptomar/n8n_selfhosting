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
                            <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.configuration.index') }}">{{ translate('WhatsApp Configuration') }}</a></li>
                            <li class="breadcrumb-item active">{{ translate('Client Onboarding') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                <a href="{{ route('admin.whatsapp.configuration.index') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-arrow-left-line"></i> {{ translate('Back to Configurations') }}
                </a>
            </div>
        </div>

        {{-- Stats Overview Card --}}
        <div class="card mb-4">
            <div class="card-header border-0 pb-0">
                <div class="card-header-left">
                    <h4 class="card-title fw-semibold mb-2">{{ translate('Onboarding Overview') }}</h4>
                    <p class="card-text text-muted small mb-0">{{ translate('Track client WhatsApp Business onboarding progress') }}</p>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                        <div class="stats-card stats-card--primary">
                            <div class="stats-card-icon">
                                <i class="ri-user-add-line"></i>
                            </div>
                            <div class="stats-card-content">
                                <span class="stats-card-value">{{ $stats['total'] }}</span>
                                <span class="stats-card-label">{{ translate('Total Onboardings') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                        <div class="stats-card stats-card--success">
                            <div class="stats-card-icon">
                                <i class="ri-checkbox-circle-fill"></i>
                            </div>
                            <div class="stats-card-content">
                                <span class="stats-card-value">{{ $stats['completed'] }}</span>
                                <span class="stats-card-label">{{ translate('Completed') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                        <div class="stats-card stats-card--warning">
                            <div class="stats-card-icon">
                                <i class="ri-time-line"></i>
                            </div>
                            <div class="stats-card-content">
                                <span class="stats-card-value">{{ $stats['pending'] }}</span>
                                <span class="stats-card-label">{{ translate('Pending') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                        <div class="stats-card stats-card--danger">
                            <div class="stats-card-icon">
                                <i class="ri-close-circle-fill"></i>
                            </div>
                            <div class="stats-card-content">
                                <span class="stats-card-value">{{ $stats['failed'] }}</span>
                                <span class="stats-card-label">{{ translate('Failed') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Onboardings Table --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-left">
                    <h4 class="card-title">{{ translate('Client Onboardings') }}</h4>
                </div>
                <div class="card-header-right d-flex gap-2">
                    <div class="search-box">
                        <input type="text" id="searchOnboarding" class="form-control form-control-sm" placeholder="{{ translate('Search...') }}">
                        <i class="ri-search-line"></i>
                    </div>
                    @if($stats['total'] > 0)
                    <div class="dropdown clear-logs-dropdown">
                        <button class="i-btn btn--danger outline btn--md dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="ri-delete-bin-line"></i> {{ translate('Clear Logs') }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <h6 class="dropdown-header">{{ translate('Clear Records') }}</h6>
                            </li>
                            @if($stats['completed'] > 0)
                            <li>
                                <a class="dropdown-item clear-logs-btn" href="#" data-type="completed" data-count="{{ $stats['completed'] }}">
                                    <i class="ri-checkbox-circle-line text--success"></i> {{ translate('Completed') }}
                                    <span class="badge bg-success ms-auto">{{ $stats['completed'] }}</span>
                                </a>
                            </li>
                            @endif
                            @if($stats['failed'] > 0)
                            <li>
                                <a class="dropdown-item clear-logs-btn" href="#" data-type="failed" data-count="{{ $stats['failed'] }}">
                                    <i class="ri-close-circle-line text--danger"></i> {{ translate('Failed') }}
                                    <span class="badge bg-danger ms-auto">{{ $stats['failed'] }}</span>
                                </a>
                            </li>
                            @endif
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item clear-logs-btn" href="#" data-type="older_30">
                                    <i class="ri-calendar-line text--warning"></i> {{ translate('Older than 30 days') }}
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item clear-logs-btn" href="#" data-type="older_90">
                                    <i class="ri-calendar-2-line text--info"></i> {{ translate('Older than 90 days') }}
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item clear-logs-btn text-danger" href="#" data-type="all" data-count="{{ $stats['total'] }}">
                                    <i class="ri-delete-bin-7-line"></i> {{ translate('Clear All Records') }}
                                    <span class="badge bg-danger ms-auto">{{ $stats['total'] }}</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endif
                </div>
            </div>
            <div class="card-body px-0 pt-0">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">{{ translate('Client') }}</th>
                                <th scope="col">{{ translate('WABA') }}</th>
                                <th scope="col">{{ translate('Phone') }}</th>
                                <th scope="col">{{ translate('Status') }}</th>
                                <th scope="col">{{ translate('Progress') }}</th>
                                <th scope="col">{{ translate('Initiated') }}</th>
                                <th scope="col" class="text-end">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($onboardings as $onboarding)
                            <tr>
                                <td data-label="{{ translate('Client') }}">
                                    @if($onboarding->user)
                                        <div class="client-info">
                                            <div class="client-avatar">
                                                @if($onboarding->user->image)
                                                    <img src="{{ asset($onboarding->user->image) }}" alt="{{ $onboarding->user->name }}">
                                                @else
                                                    <span>{{ strtoupper(substr($onboarding->user->name, 0, 2)) }}</span>
                                                @endif
                                            </div>
                                            <div class="client-details">
                                                <strong>{{ $onboarding->user->name }}</strong>
                                                <small>{{ $onboarding->user->email }}</small>
                                            </div>
                                        </div>
                                    @else
                                        <div class="client-info">
                                            <div class="client-avatar admin">
                                                <i class="ri-admin-line"></i>
                                            </div>
                                            <div class="client-details">
                                                <strong>{{ translate('Admin') }}</strong>
                                                <small class="text-muted">{{ translate('System initiated') }}</small>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                                <td data-label="{{ translate('WABA') }}">
                                    @if($onboarding->waba_name)
                                        <div class="waba-info">
                                            <strong>{{ $onboarding->waba_name }}</strong>
                                            <code class="waba-id">{{ Str::limit($onboarding->waba_id, 15) }}</code>
                                        </div>
                                    @else
                                        <span class="text-muted">{{ translate('Not connected') }}</span>
                                    @endif
                                </td>
                                <td data-label="{{ translate('Phone') }}">
                                    @if($onboarding->phone_number)
                                        <div class="phone-info">
                                            <strong>{{ $onboarding->phone_number }}</strong>
                                            @if($onboarding->verified_name)
                                                <small class="verified-name">
                                                    <i class="ri-verified-badge-fill text--success"></i>
                                                    {{ $onboarding->verified_name }}
                                                </small>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted">{{ translate('Not registered') }}</span>
                                    @endif
                                </td>
                                <td data-label="{{ translate('Status') }}">
                                    @php
                                        $statusConfig = match($onboarding->onboarding_status) {
                                            'completed' => ['class' => 'success', 'icon' => 'ri-checkbox-circle-fill'],
                                            'failed' => ['class' => 'danger', 'icon' => 'ri-close-circle-fill'],
                                            'initiated' => ['class' => 'info', 'icon' => 'ri-play-circle-line'],
                                            'waba_connected' => ['class' => 'warning', 'icon' => 'ri-building-line'],
                                            'phone_selected' => ['class' => 'warning', 'icon' => 'ri-phone-line'],
                                            default => ['class' => 'secondary', 'icon' => 'ri-question-line']
                                        };
                                    @endphp
                                    <span class="status-badge status-badge--{{ $statusConfig['class'] }}">
                                        <i class="{{ $statusConfig['icon'] }}"></i>
                                        {{ ucfirst(str_replace('_', ' ', $onboarding->onboarding_status)) }}
                                    </span>
                                </td>
                                <td data-label="{{ translate('Progress') }}">
                                    @php
                                        $progress = $onboarding->getProgressPercentage();
                                        $progressClass = match(true) {
                                            $progress >= 100 => 'success',
                                            $onboarding->onboarding_status === 'failed' => 'danger',
                                            $progress >= 50 => 'warning',
                                            default => 'primary'
                                        };
                                    @endphp
                                    <div class="progress-wrapper">
                                        <div class="progress-bar-container">
                                            <div class="progress-bar-fill progress-bar-fill--{{ $progressClass }}" style="width: {{ $progress }}%"></div>
                                        </div>
                                        <span class="progress-text">{{ $progress }}%</span>
                                    </div>
                                </td>
                                <td data-label="{{ translate('Initiated') }}">
                                    @if($onboarding->initiated_at)
                                        <div class="time-info">
                                            <span class="time-date">{{ $onboarding->initiated_at->format('M d, Y') }}</span>
                                            <span class="time-ago">{{ $onboarding->initiated_at->diffForHumans() }}</span>
                                        </div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td data-label="{{ translate('Actions') }}" class="text-end">
                                    <div class="action-buttons">
                                        <a href="{{ route('admin.whatsapp.onboarding.details', $onboarding->uid) }}"
                                           class="action-btn action-btn--info" data-bs-toggle="tooltip" title="{{ translate('View Details') }}">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                        @if($onboarding->canRetry())
                                        <form action="{{ route('admin.whatsapp.onboarding.retry', $onboarding->uid) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="action-btn action-btn--warning" data-bs-toggle="tooltip" title="{{ translate('Retry Onboarding') }}">
                                                <i class="ri-refresh-line"></i>
                                            </button>
                                        </form>
                                        @endif
                                        @if($onboarding->onboarding_status === 'completed' && $onboarding->gateway)
                                        <a href="{{ route('admin.gateway.whatsapp.edit', $onboarding->gateway->uid ?? '') }}"
                                           class="action-btn action-btn--success" data-bs-toggle="tooltip" title="{{ translate('View Gateway') }}">
                                            <i class="ri-link"></i>
                                        </a>
                                        @endif
                                        <button type="button" class="action-btn action-btn--danger delete-onboarding-btn"
                                                data-uid="{{ $onboarding->uid }}"
                                                data-bs-toggle="tooltip" title="{{ translate('Delete Record') }}">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr class="empty-row">
                                <td colspan="7" class="empty-cell">
                                    <div class="empty-state">
                                        <div class="empty-icon-wrapper">
                                            <i class="ri-user-add-line"></i>
                                        </div>
                                        <h5 class="empty-state-title">{{ translate('No Onboarding Records Found') }}</h5>
                                        <p class="empty-state-text">{{ translate('Client onboarding records will appear here when users connect their WhatsApp Business accounts.') }}</p>
                                        <a href="{{ route('admin.whatsapp.configuration.index') }}" class="i-btn btn--primary btn--md">
                                            <i class="ri-settings-3-line"></i> {{ translate('Manage Configurations') }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($onboardings->hasPages())
                <div class="pagination-wrapper">
                    @include('admin.partials.pagination', ['paginator' => $onboardings])
                </div>
                @endif
            </div>
        </div>

        {{-- Quick Stats Summary --}}
        @if($stats['total'] > 0)
        <div class="row g-4 mt-3 mb-4">
            <div class="col-xl-6 col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-3 fw-semibold">{{ translate('Completion Rate') }}</h6>
                        <div class="completion-rate">
                            @php
                                $completionRate = $stats['total'] > 0 ? round(($stats['completed'] / $stats['total']) * 100, 1) : 0;
                            @endphp
                            <div class="rate-circle">
                                <svg viewBox="0 0 36 36">
                                    <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"></path>
                                    <path class="circle-fill" stroke-dasharray="{{ $completionRate }}, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"></path>
                                </svg>
                                <span class="rate-text">{{ $completionRate }}%</span>
                            </div>
                            <div class="rate-info">
                                <p class="mb-1"><strong>{{ $stats['completed'] }}</strong> {{ translate('of') }} <strong>{{ $stats['total'] }}</strong> {{ translate('completed successfully') }}</p>
                                <small class="text-muted">{{ translate('Overall onboarding success rate') }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6 col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-3 fw-semibold">{{ translate('Status Breakdown') }}</h6>
                        <div class="status-breakdown">
                            <div class="breakdown-item">
                                <div class="breakdown-label">
                                    <span class="breakdown-dot breakdown-dot--success"></span>
                                    {{ translate('Completed') }}
                                </div>
                                <div class="breakdown-bar">
                                    <div class="breakdown-fill breakdown-fill--success" style="width: {{ $stats['total'] > 0 ? ($stats['completed'] / $stats['total'] * 100) : 0 }}%"></div>
                                </div>
                                <span class="breakdown-count">{{ $stats['completed'] }}</span>
                            </div>
                            <div class="breakdown-item">
                                <div class="breakdown-label">
                                    <span class="breakdown-dot breakdown-dot--warning"></span>
                                    {{ translate('Pending') }}
                                </div>
                                <div class="breakdown-bar">
                                    <div class="breakdown-fill breakdown-fill--warning" style="width: {{ $stats['total'] > 0 ? ($stats['pending'] / $stats['total'] * 100) : 0 }}%"></div>
                                </div>
                                <span class="breakdown-count">{{ $stats['pending'] }}</span>
                            </div>
                            <div class="breakdown-item">
                                <div class="breakdown-label">
                                    <span class="breakdown-dot breakdown-dot--danger"></span>
                                    {{ translate('Failed') }}
                                </div>
                                <div class="breakdown-bar">
                                    <div class="breakdown-fill breakdown-fill--danger" style="width: {{ $stats['total'] > 0 ? ($stats['failed'] / $stats['total'] * 100) : 0 }}%"></div>
                                </div>
                                <span class="breakdown-count">{{ $stats['failed'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

</main>

{{-- Delete Single Record Modal --}}
<div class="modal fade confirm-modal" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <form id="deleteForm" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <div class="confirm-modal-icon danger">
                        <i class="ri-delete-bin-line"></i>
                    </div>
                    <h5 class="confirm-modal-title">{{ translate("Delete Record?") }}</h5>
                    <p class="confirm-modal-text">{{ translate("This action cannot be undone.") }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="i-btn btn--outline btn--md" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
                    <button type="submit" class="i-btn btn--danger btn--md">{{ translate("Delete") }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Clear Logs Modal --}}
<div class="modal fade confirm-modal" id="clearLogsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <form id="clearLogsForm" action="{{ route('admin.whatsapp.onboarding.clear-logs') }}" method="POST">
                @csrf
                <input type="hidden" name="clear_type" id="clearType" value="">
                <div class="modal-body">
                    <div class="confirm-modal-icon warning">
                        <i class="ri-error-warning-line"></i>
                    </div>
                    <h5 class="confirm-modal-title">{{ translate("Clear Records?") }}</h5>
                    <p class="confirm-modal-text" id="clearLogsMessage">{{ translate("This action cannot be undone.") }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="i-btn btn--outline btn--md" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
                    <button type="submit" class="i-btn btn--danger btn--md">{{ translate("Clear") }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

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

/* Stats Cards */
.stats-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 10px;
    border-radius: 12px;
    background: var(--card-bg);
    border: 1px solid var(--color-border);
    transition: all 0.3s ease;
}
.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.stats-card-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.stats-card--primary .stats-card-icon { background: rgba(var(--primary-rgb), 0.15); color: var(--primary-color); }
.stats-card--success .stats-card-icon { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.stats-card--warning .stats-card-icon { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.stats-card--danger .stats-card-icon { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.stats-card-content { flex: 1; }
.stats-card-value { display: block; font-size: 28px; font-weight: 700; color: var(--text-primary); line-height: 1.2; }
.stats-card-label { display: block; font-size: 13px; color: var(--text-secondary); margin-top: 2px; }

/* Search Box */
.search-box {
    position: relative;
}
.search-box input {
    padding-left: 36px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    min-width: 200px;
}
.search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
}

/* Client Info */
.client-info {
    display: flex;
    align-items: center;
    gap: 12px;
}
.client-avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.2) 0%, rgba(var(--primary-rgb), 0.1) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 600;
    color: var(--primary-color);
    overflow: hidden;
}
.client-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.client-avatar.admin {
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
    color: #6b7280;
}
.client-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.client-details strong { color: #1f2937; font-size: 14px; }
.client-details small { color: #6b7280; font-size: 12px; }

/* WABA Info */
.waba-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.waba-info strong { color: #1f2937; font-size: 14px; }
.waba-id {
    font-size: 11px;
    background: #f1f5f9;
    padding: 2px 6px;
    border-radius: 4px;
    color: #64748b;
}

/* Phone Info */
.phone-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.phone-info strong { color: #1f2937; font-size: 14px; }
.verified-name {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: #6b7280;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}
.status-badge i { font-size: 14px; }
.status-badge--success { background: rgba(16, 185, 129, 0.1); color: #059669; }
.status-badge--danger { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
.status-badge--warning { background: rgba(245, 158, 11, 0.1); color: #d97706; }
.status-badge--info { background: rgba(59, 130, 246, 0.1); color: #2563eb; }
.status-badge--secondary { background: rgba(107, 114, 128, 0.1); color: #6b7280; }

/* Progress Wrapper */
.progress-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
}
.progress-bar-container {
    width: 80px;
    height: 8px;
    background: #f1f5f9;
    border-radius: 4px;
    overflow: hidden;
}
.progress-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}
.progress-bar-fill--primary { background: var(--primary-color); }
.progress-bar-fill--success { background: #10b981; }
.progress-bar-fill--warning { background: #f59e0b; }
.progress-bar-fill--danger { background: #ef4444; }
.progress-text {
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
    min-width: 36px;
}

/* Time Info */
.time-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.time-date { font-size: 13px; color: #1f2937; }
.time-ago { font-size: 11px; color: #9ca3af; }

/* Action Buttons */
.action-buttons {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
}
.action-btn {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}
.action-btn--info { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.action-btn--info:hover { background: #3b82f6; color: #fff; }
.action-btn--warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.action-btn--warning:hover { background: #f59e0b; color: #fff; }
.action-btn--success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.action-btn--success:hover { background: #10b981; color: #fff; }
.action-btn--danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.action-btn--danger:hover { background: #ef4444; color: #fff; }

/* Clear Logs Dropdown - Scoped */
.clear-logs-dropdown .dropdown-menu {
    min-width: 240px;
    padding: 8px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    border-radius: 10px;
}
.clear-logs-dropdown .dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 6px;
    font-size: 13px;
    color: #374151;
}
.clear-logs-dropdown .dropdown-item:hover {
    background: #f8fafc;
}
.clear-logs-dropdown .dropdown-item i {
    font-size: 16px;
    width: 18px;
    text-align: center;
}
.clear-logs-dropdown .dropdown-item .badge {
    margin-left: auto;
    font-size: 11px;
    font-weight: 500;
}
.clear-logs-dropdown .dropdown-header {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #9ca3af;
    padding: 8px 12px 4px;
}
.clear-logs-dropdown .dropdown-divider {
    margin: 6px 0;
    border-color: #f1f5f9;
}

/* Empty State */
.empty-row {
    background: transparent !important;
}
.empty-row:hover {
    background: transparent !important;
}
.empty-cell {
    text-align: center !important;
    padding: 0 !important;
    border: none !important;
}
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 80px 20px;
    width: 100%;
}
.empty-icon-wrapper {
    width: 100px;
    height: 100px;
    border-radius: 24px;
    background: linear-gradient(135deg, rgba(var(--primary-rgb, 99, 102, 241), 0.15) 0%, rgba(var(--primary-rgb, 99, 102, 241), 0.05) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 24px;
    font-size: 42px;
    color: var(--primary-color, #6366f1);
}
.empty-state-title {
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 8px;
}
.empty-state-text {
    color: #6b7280;
    margin-bottom: 24px;
    max-width: 400px;
    line-height: 1.6;
}

/* Pagination Wrapper */
.pagination-wrapper {
    padding: 16px 24px;
    border-top: 1px solid #e5e7eb;
}

/* Completion Rate */
.completion-rate {
    display: flex;
    align-items: center;
    gap: 24px;
}
.rate-circle {
    position: relative;
    width: 100px;
    height: 100px;
    flex-shrink: 0;
}
.rate-circle svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}
.circle-bg {
    fill: none;
    stroke: #f1f5f9;
    stroke-width: 3.8;
}
.circle-fill {
    fill: none;
    stroke: #10b981;
    stroke-width: 3.8;
    stroke-linecap: round;
    transition: stroke-dasharray 0.5s ease;
}
.rate-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 20px;
    font-weight: 700;
    color: #1f2937;
}
.rate-info p { font-size: 14px; color: #1f2937; }

/* Status Breakdown */
.status-breakdown {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.breakdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
}
.breakdown-label {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100px;
    font-size: 13px;
    color: #6b7280;
}
.breakdown-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}
.breakdown-dot--success { background: #10b981; }
.breakdown-dot--warning { background: #f59e0b; }
.breakdown-dot--danger { background: #ef4444; }
.breakdown-bar {
    flex: 1;
    height: 8px;
    background: #f1f5f9;
    border-radius: 4px;
    overflow: hidden;
}
.breakdown-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.5s ease;
}
.breakdown-fill--success { background: #10b981; }
.breakdown-fill--warning { background: #f59e0b; }
.breakdown-fill--danger { background: #ef4444; }
.breakdown-count {
    width: 30px;
    text-align: right;
    font-size: 14px;
    font-weight: 600;
    color: #1f2937;
}

/* Footer spacing fix */
.main-content {
    padding-bottom: 80px;
}
</style>
@endpush

@push('script-push')
<script>
"use strict";
(function($) {
    $(document).ready(function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Search filter
        $('#searchOnboarding').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });

        // Clear logs handler
        $('.clear-logs-btn').on('click', function(e) {
            e.preventDefault();
            var type = $(this).data('type');
            var messages = {
                'completed': '{{ translate("Are you sure to clear all completed records?") }}',
                'failed': '{{ translate("Are you sure to clear all failed records?") }}',
                'older_30': '{{ translate("Are you sure to clear records older than 30 days?") }}',
                'older_90': '{{ translate("Are you sure to clear records older than 90 days?") }}',
                'all': '{{ translate("Are you sure to clear ALL onboarding records?") }}'
            };

            $('#clearType').val(type);
            $('#clearLogsMessage').text(messages[type]);
            $('#clearLogsModal').modal('show');
        });

        // Delete single record handler
        $('.delete-onboarding-btn').on('click', function(e) {
            e.preventDefault();
            var uid = $(this).data('uid');
            var deleteUrl = "{{ route('admin.whatsapp.onboarding.delete', ':uid') }}".replace(':uid', uid);
            $('#deleteForm').attr('action', deleteUrl);
            $('#deleteModal').modal('show');
        });
    });
})(jQuery);
</script>
@endpush

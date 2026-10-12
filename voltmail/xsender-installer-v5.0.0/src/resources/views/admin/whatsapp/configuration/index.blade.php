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
                            <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                <a href="{{ route('admin.whatsapp.configuration.create') }}" class="i-btn btn--primary btn--md">
                    <i class="ri-add-line"></i> {{ translate('Add Configuration') }}
                </a>
            </div>
        </div>

        {{-- Stats Overview Card --}}
        @php
            $totalConfigs = $configurations->total();
            $activeConfigs = \App\Models\MetaConfiguration::where('status', 'active')->count();
            $withConfigId = \App\Models\MetaConfiguration::whereNotNull('config_id')->count();
            $totalGateways = $healthSummary['total'] ?? 0;
        @endphp
        <div class="card mb-4">
            <div class="card-header border-0 pb-0">
                <div class="card-header-left">
                    <h4 class="card-title fw-semibold mb-2">{{ translate('Configuration Overview') }}</h4>
                    <p class="card-text text-muted small mb-0">{{ translate('Status of your Meta App configurations') }}</p>
                </div>
                <div class="card-header-right">
                    <a href="{{ route('admin.whatsapp.health.index') }}" class="i-btn btn--success outline btn--sm">
                        <i class="ri-heart-pulse-line"></i> {{ translate('Health Monitor') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                        <div class="stats-card stats-card--primary">
                            <div class="stats-card-icon">
                                <i class="ri-apps-line"></i>
                            </div>
                            <div class="stats-card-content">
                                <span class="stats-card-value">{{ $totalConfigs }}</span>
                                <span class="stats-card-label">{{ translate('Total Configurations') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                        <div class="stats-card stats-card--success">
                            <div class="stats-card-icon">
                                <i class="ri-checkbox-circle-line"></i>
                            </div>
                            <div class="stats-card-content">
                                <span class="stats-card-value">{{ $activeConfigs }}</span>
                                <span class="stats-card-label">{{ translate('Active') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                        <div class="stats-card stats-card--info">
                            <div class="stats-card-icon">
                                <i class="ri-shield-check-line"></i>
                            </div>
                            <div class="stats-card-content">
                                <span class="stats-card-value">{{ $withConfigId }}</span>
                                <span class="stats-card-label">{{ translate('With Config ID') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                        <div class="stats-card stats-card--warning">
                            <div class="stats-card-icon">
                                <i class="ri-whatsapp-line"></i>
                            </div>
                            <div class="stats-card-content">
                                <span class="stats-card-value">{{ $totalGateways }}</span>
                                <span class="stats-card-label">{{ translate('Cloud API Gateways') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Alert for Missing Configuration ID --}}
        @if($missingConfigIdCount > 0)
        <div class="alert alert-warning alert-dismissible fade show d-flex align-items-start gap-3 mb-4" role="alert">
            <div class="alert-icon">
                <i class="ri-error-warning-line fs-4"></i>
            </div>
            <div class="alert-content flex-grow-1">
                <h6 class="alert-heading mb-1">{{ translate('Configuration ID Missing') }}</h6>
                <p class="mb-0 small">
                    {{ translate('Some configurations are missing the Configuration ID (config_id) required for Embedded Signup.') }}
                    <a href="https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/overview" target="_blank" class="alert-link">
                        {{ translate('Learn more') }} <i class="ri-external-link-line"></i>
                    </a>
                </p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        {{-- Configurations Table --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-left">
                    <h4 class="card-title">{{ translate('Meta App Configurations') }}</h4>
                </div>
                <div class="card-header-right d-flex gap-2">
                    <div class="search-box">
                        <input type="text" id="searchConfig" class="form-control form-control-sm" placeholder="{{ translate('Search...') }}">
                        <i class="ri-search-line"></i>
                    </div>
                </div>
            </div>

            <div class="card-body px-0 pt-0">
                <div class="table-container">
                    <table class="table-striped">
                        <thead>
                            <tr>
                                <th scope="col">{{ translate('Configuration') }}</th>
                                <th scope="col">{{ translate('App ID') }}</th>
                                <th scope="col">{{ translate('Config ID') }}</th>
                                <th scope="col">{{ translate('Environment') }}</th>
                                <th scope="col">{{ translate('Status') }}</th>
                                <th scope="col">{{ translate('Gateways') }}</th>
                                <th scope="col" class="text-end">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($configurations as $config)
                            <tr>
                                <td data-label="{{ translate('Configuration') }}">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="config-avatar {{ $config->is_default ? 'default' : '' }}">
                                            <i class="ri-apps-line"></i>
                                        </div>
                                        <div>
                                            <div class="d-flex align-items-center gap-2">
                                                <strong class="text-dark">{{ $config->name }}</strong>
                                                @if($config->is_default)
                                                <span class="i-badge primary-soft pill fs-11">{{ translate('Default') }}</span>
                                                @endif
                                            </div>
                                            <small class="text-muted">{{ translate('API Version') }}: {{ $config->api_version }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="{{ translate('App ID') }}">
                                    <div class="code-badge">
                                        <code>{{ Str::limit($config->app_id, 18) }}</code>
                                        <button type="button" class="copy-btn" data-copy="{{ $config->app_id }}" title="{{ translate('Copy') }}">
                                            <i class="ri-file-copy-line"></i>
                                        </button>
                                    </div>
                                </td>
                                <td data-label="{{ translate('Config ID') }}">
                                    @if($config->config_id)
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="status-indicator success"></span>
                                            <code class="text-success">{{ Str::limit($config->config_id, 15) }}</code>
                                        </div>
                                    @else
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="status-indicator warning"></span>
                                            <span class="text-warning">{{ translate('Not Set') }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td data-label="{{ translate('Environment') }}">
                                    @if($config->environment === 'production')
                                        <span class="i-badge success-solid pill">
                                            <i class="ri-checkbox-circle-line me-1"></i>{{ translate('Production') }}
                                        </span>
                                    @else
                                        <span class="i-badge info-solid pill">
                                            <i class="ri-test-tube-line me-1"></i>{{ translate('Sandbox') }}
                                        </span>
                                    @endif
                                </td>
                                <td data-label="{{ translate('Status') }}">
                                    @if($config->status === 'active')
                                        <span class="status-badge status-badge--success">
                                            <span class="status-dot"></span>
                                            {{ translate('Active') }}
                                        </span>
                                    @else
                                        <span class="status-badge status-badge--danger">
                                            <span class="status-dot"></span>
                                            {{ translate('Inactive') }}
                                        </span>
                                    @endif
                                </td>
                                <td data-label="{{ translate('Gateways') }}">
                                    <span class="gateway-count">{{ $config->gateways_count }}</span>
                                </td>
                                <td data-label="{{ translate('Actions') }}">
                                    <div class="action-buttons">
                                        <a href="{{ route('admin.whatsapp.configuration.edit', $config->uid) }}"
                                           class="action-btn action-btn--info" data-bs-toggle="tooltip" title="{{ translate('Edit Configuration') }}">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        @if(!$config->is_default)
                                        <form action="{{ route('admin.whatsapp.configuration.set-default', $config->uid) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="action-btn action-btn--primary" data-bs-toggle="tooltip" title="{{ translate('Set as Default') }}">
                                                <i class="ri-star-line"></i>
                                            </button>
                                        </form>
                                        @else
                                        <span class="action-btn action-btn--primary active" data-bs-toggle="tooltip" title="{{ translate('Current Default') }}">
                                            <i class="ri-star-fill"></i>
                                        </span>
                                        @endif
                                        <button type="button" class="action-btn action-btn--success test-connection"
                                                data-uid="{{ $config->uid }}" data-bs-toggle="tooltip" title="{{ translate('Test Connection') }}">
                                            <i class="ri-wifi-line"></i>
                                        </button>
                                        @if($config->gateways_count == 0)
                                        <button type="button"
                                                class="action-btn action-btn--danger delete-config"
                                                data-uid="{{ $config->uid }}"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteModal"
                                                title="{{ translate('Delete') }}">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-state-illustration">
                                            <div class="empty-icon-wrapper">
                                                <i class="ri-settings-4-line"></i>
                                            </div>
                                            <div class="empty-decoration">
                                                <span></span><span></span><span></span>
                                            </div>
                                        </div>
                                        <h5 class="empty-state-title">{{ translate('No Configurations Found') }}</h5>
                                        <p class="empty-state-text">{{ translate('Create your first Meta App configuration to get started') }}</p>
                                        <a href="{{ route('admin.whatsapp.configuration.create') }}" class="i-btn btn--primary btn--md">
                                            <i class="ri-add-line"></i> {{ translate('Add Configuration') }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($configurations->hasPages())
                <div class="pagination-wrapper">
                    @include('admin.partials.pagination', ['paginator' => $configurations])
                </div>
                @endif
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="row g-4 mt-3">
            <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="quick-action-card">
                    <div class="quick-action-icon quick-action-icon--primary">
                        <i class="ri-user-add-line"></i>
                    </div>
                    <div class="quick-action-content">
                        <h6>{{ translate('Client Onboarding') }}</h6>
                        <p>{{ translate('Manage client WhatsApp onboarding') }}</p>
                    </div>
                    <a href="{{ route('admin.whatsapp.onboarding.index') }}" class="stretched-link"></a>
                    <i class="ri-arrow-right-line quick-action-arrow"></i>
                </div>
            </div>
            <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="quick-action-card">
                    <div class="quick-action-icon quick-action-icon--success">
                        <i class="ri-heart-pulse-line"></i>
                    </div>
                    <div class="quick-action-content">
                        <h6>{{ translate('Health Monitor') }}</h6>
                        <p>{{ translate('Check gateway health status') }}</p>
                    </div>
                    <a href="{{ route('admin.whatsapp.health.index') }}" class="stretched-link"></a>
                    <i class="ri-arrow-right-line quick-action-arrow"></i>
                </div>
            </div>
            <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="quick-action-card">
                    <div class="quick-action-icon quick-action-icon--info">
                        <i class="ri-book-read-line"></i>
                    </div>
                    <div class="quick-action-content">
                        <h6>{{ translate('Documentation') }}</h6>
                        <p>{{ translate('WhatsApp Embedded Signup guide') }}</p>
                    </div>
                    <a href="https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/overview" target="_blank" class="stretched-link"></a>
                    <i class="ri-external-link-line quick-action-arrow"></i>
                </div>
            </div>
        </div>
    </div>
</main>

{{-- Delete Modal --}}
<div class="modal fade confirm-modal" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <form method="POST" id="deleteForm">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <div class="confirm-modal-icon danger">
                        <i class="ri-delete-bin-line"></i>
                    </div>
                    <h5 class="confirm-modal-title">{{ translate('Delete Configuration?') }}</h5>
                    <p class="confirm-modal-text">{{ translate('This action cannot be undone.') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="i-btn btn--outline btn--md" data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="submit" class="i-btn btn--danger btn--md">{{ translate('Delete') }}</button>
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
    font-size: 28px;
}
.stats-card--primary .stats-card-icon { background: rgba(var(--primary-rgb), 0.15); color: var(--primary-color); }
.stats-card--success .stats-card-icon { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.stats-card--info .stats-card-icon { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.stats-card--warning .stats-card-icon { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.stats-card-content { flex: 1; }
.stats-card-value { display: block; font-size: 28px; font-weight: 700; color: var(--text-primary); line-height: 1.2; }
.stats-card-label { display: block; font-size: 13px; color: var(--text-secondary); margin-top: 2px; }

/* Config Avatar */
.config-avatar {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
    color: #6b7280;
    font-size: 20px;
}
.config-avatar.default {
    background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.2) 0%, rgba(var(--primary-rgb), 0.1) 100%);
    color: var(--primary-color);
}

/* Code Badge */
.code-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #f1f5f9;
    padding: 4px 8px;
    border-radius: 6px;
}
.code-badge code {
    font-size: 12px;
    color: #475569;
    background: none;
    padding: 0;
}
.copy-btn {
    background: none;
    border: none;
    padding: 2px;
    cursor: pointer;
    color: #94a3b8;
    transition: color 0.2s;
}
.copy-btn:hover { color: var(--primary-color); }

/* Status Indicator */
.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}
.status-indicator.success { background: #10b981; }
.status-indicator.warning { background: #f59e0b; }
.status-indicator.danger { background: #ef4444; }

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
.status-badge .status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}
.status-badge--success { background: rgba(16, 185, 129, 0.1); color: #059669; }
.status-badge--success .status-dot { background: #10b981; }
.status-badge--danger { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
.status-badge--danger .status-dot { background: #ef4444; }

/* Gateway Count */
.gateway-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    background: #f1f5f9;
    border-radius: 8px;
    font-weight: 600;
    color: #475569;
}

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
.action-btn--primary { background: rgba(var(--primary-rgb), 0.1); color: var(--primary-color); }
.action-btn--primary:hover, .action-btn--primary.active { background: var(--primary-color); color: #fff; }
.action-btn--success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.action-btn--success:hover { background: #10b981; color: #fff; }
.action-btn--danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.action-btn--danger:hover { background: #ef4444; color: #fff; }

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

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}
.empty-state-illustration {
    position: relative;
    margin-bottom: 24px;
}
.empty-icon-wrapper {
    width: 100px;
    height: 100px;
    border-radius: 24px;
    background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.15) 0%, rgba(var(--primary-rgb), 0.05) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    font-size: 42px;
    color: var(--primary-color);
}
.empty-decoration {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 140px;
    height: 140px;
}
.empty-decoration span {
    position: absolute;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--primary-color);
    opacity: 0.3;
}
.empty-decoration span:nth-child(1) { top: 0; left: 50%; transform: translateX(-50%); }
.empty-decoration span:nth-child(2) { bottom: 10px; left: 10px; }
.empty-decoration span:nth-child(3) { bottom: 10px; right: 10px; }
.empty-state-title { font-size: 18px; font-weight: 600; color: #1f2937; margin-bottom: 8px; }
.empty-state-text { color: #6b7280; margin-bottom: 20px; max-width: 300px; margin-left: auto; margin-right: auto; }

/* Quick Action Cards */
.quick-action-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    background: var(--card-bg);
    border: 1px solid var(--color-border);
    border-radius: 12px;
    position: relative;
    transition: all 0.3s;
}
.quick-action-card:hover {
    border-color: var(--primary-color);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.1);
}
.quick-action-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.quick-action-icon--primary { background: rgba(var(--primary-rgb), 0.1); color: var(--primary-color); }
.quick-action-icon--success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.quick-action-icon--info { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.quick-action-content h6 { font-size: 15px; font-weight: 600; color: #1f2937; margin-bottom: 4px; }
.quick-action-content p { font-size: 13px; color: #6b7280; margin-bottom: 0; }
.quick-action-arrow {
    position: absolute;
    right: 20px;
    color: #d1d5db;
    font-size: 18px;
    transition: all 0.3s;
}
.quick-action-card:hover .quick-action-arrow {
    color: var(--primary-color);
    transform: translateX(4px);
}

/* Table Improvements */
.table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(0,0,0,0.02);
}

/* Alert Styling */
.alert-icon {
    flex-shrink: 0;
}
.alert-heading {
    font-weight: 600;
}

/* Pagination Wrapper */
.pagination-wrapper {
    padding: 16px 24px;
    border-top: 1px solid #e5e7eb;
}

/* Responsive Table - Card Layout on Small Screens */
@media (max-width: 991.98px) {
    .table-container {
        overflow-x: visible;
    }

    .table-striped thead {
        display: none;
    }

    .table-striped tbody {
        display: flex;
        flex-direction: column;
        gap: 16px;
        padding: 16px;
    }

    .table-striped tbody tr {
        display: flex;
        flex-direction: column;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 0;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .table-striped tbody tr:nth-of-type(odd) {
        background-color: #fff;
    }

    .table-striped tbody td {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        border-bottom: 1px solid #f3f4f6;
        gap: 12px;
    }

    .table-striped tbody td:last-child {
        border-bottom: none;
    }

    .table-striped tbody td::before {
        content: attr(data-label);
        font-weight: 600;
        font-size: 12px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        flex-shrink: 0;
        min-width: 100px;
    }

    /* Configuration cell - special layout */
    .table-striped tbody td[data-label="{{ translate('Configuration') }}"] {
        background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.05) 0%, #fff 100%);
        padding: 16px;
        border-bottom: 2px solid rgba(var(--primary-rgb), 0.1);
    }

    .table-striped tbody td[data-label="{{ translate('Configuration') }}"]::before {
        display: none;
    }

    .table-striped tbody td[data-label="{{ translate('Configuration') }}"] > .d-flex {
        width: 100%;
    }

    /* Actions cell - special layout */
    .table-striped tbody td[data-label="{{ translate('Actions') }}"] {
        background: #f9fafb;
        justify-content: center;
        padding: 12px 16px;
    }

    .table-striped tbody td[data-label="{{ translate('Actions') }}"]::before {
        display: none;
    }

    .table-striped tbody td[data-label="{{ translate('Actions') }}"] .action-buttons {
        justify-content: center;
        width: 100%;
    }

    /* Code badge responsive */
    .code-badge {
        max-width: 150px;
        overflow: hidden;
    }

    .code-badge code {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Empty state in responsive */
    .table-striped tbody tr td[colspan] {
        display: block;
        border: none;
        padding: 0;
    }

    .table-striped tbody tr td[colspan]::before {
        display: none;
    }

    .table-striped tbody tr:has(td[colspan]) {
        border: none;
        box-shadow: none;
        background: transparent;
    }
}

/* Extra small screens */
@media (max-width: 575.98px) {
    .table-striped tbody td {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }

    .table-striped tbody td::before {
        margin-bottom: 2px;
    }

    .table-striped tbody td[data-label="{{ translate('Actions') }}"] {
        flex-direction: row;
        align-items: center;
    }

    .action-buttons {
        flex-wrap: wrap;
        justify-content: center;
    }

    .page-header {
        flex-direction: column;
        align-items: stretch;
    }

    .page-header-right {
        margin-top: 12px;
    }

    .page-header-right .i-btn {
        width: 100%;
        justify-content: center;
    }

    .stats-card {
        flex-direction: column;
        text-align: center;
    }

    .quick-action-card {
        flex-direction: column;
        text-align: center;
        padding: 24px 16px;
    }

    .quick-action-arrow {
        position: static;
        margin-top: 8px;
    }
}

/* Dark mode support for responsive cards */
[data-theme="dark"] .table-striped tbody tr {
    background: var(--card-bg, #1f2937);
    border-color: var(--border-color, #374151);
}

[data-theme="dark"] .table-striped tbody td {
    border-color: var(--border-color, #374151);
}

[data-theme="dark"] .table-striped tbody td::before {
    color: var(--text-muted, #9ca3af);
}

[data-theme="dark"] .table-striped tbody td[data-label="{{ translate('Configuration') }}"] {
    background: rgba(var(--primary-rgb), 0.1);
}

[data-theme="dark"] .table-striped tbody td[data-label="{{ translate('Actions') }}"] {
    background: rgba(255, 255, 255, 0.03);
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

        // Copy to clipboard (works on HTTP and HTTPS)
        function copyText(text) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(text);
            }
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;left:-9999px;top:-9999px';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            try { document.execCommand('copy'); } catch(e) {}
            document.body.removeChild(ta);
            return Promise.resolve();
        }

        $('.copy-btn').on('click', function(e) {
            e.preventDefault();
            var text = $(this).data('copy');
            copyText(text).then(function() {
                notify('success', '{{ translate("Copied to clipboard") }}');
            });
        });

        // Search filter
        $('#searchConfig').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });

        // Test connection — opens the per-check report modal (same UX as edit page).
        $('.test-connection').on('click', function() {
            var btn = $(this);
            var uid = btn.data('uid');
            var originalHtml = btn.html();

            btn.html('<i class="ri-loader-4-line ri-spin"></i>').prop('disabled', true);

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

        // Delete configuration
        $('.delete-config').on('click', function() {
            var uid = $(this).data('uid');
            var modal = $('#deleteModal');
            modal.find('#deleteForm').attr('action', "{{ route('admin.whatsapp.configuration.destroy', ':uid') }}".replace(':uid', uid));
        });
    });
})(jQuery);
</script>
@endpush

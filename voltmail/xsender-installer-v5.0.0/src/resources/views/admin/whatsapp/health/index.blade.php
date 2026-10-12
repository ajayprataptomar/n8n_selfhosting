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
                            <li class="breadcrumb-item active" aria-current="page">{{ translate('Health Monitor') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                <button type="button" class="i-btn btn--primary btn--md" id="runAllHealthChecks" {{ $summary['total'] == 0 ? 'disabled' : '' }}>
                    <i class="ri-heart-pulse-line"></i> {{ translate('Run All Health Checks') }}
                </button>
            </div>
        </div>

        @if($summary['total'] == 0)
        {{-- Empty State --}}
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-state-illustration">
                        <div class="empty-icon-wrapper">
                            <i class="ri-heart-pulse-line"></i>
                        </div>
                    </div>
                    <h5 class="empty-state-title">{{ translate('No Cloud API Gateways') }}</h5>
                    <p class="empty-state-text">{{ translate('Connect WhatsApp Business Accounts via Cloud API to monitor their health status here.') }}</p>
                    <a href="{{ route('admin.gateway.whatsapp.cloud.api.index') }}" class="i-btn btn--primary btn--md">
                        <i class="ri-add-line"></i> {{ translate('Add Cloud API Gateway') }}
                    </a>
                </div>
            </div>
        </div>
        @else

        {{-- Health Overview Card --}}
        <div class="card mb-4">
            <div class="card-header border-0 pb-0">
                <div class="card-header-left">
                    <h4 class="card-title fw-semibold">{{ translate('Health Overview') }}</h4>
                    <p class="card-text text-muted small mb-0">{{ translate('Real-time health status of Cloud API gateways') }}</p>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-xl-3 col-lg-4 col-md-4 col-sm-6">
                        <div class="health-stat-card health-stat-card--default">
                            <div class="health-stat-icon">
                                <i class="ri-cloud-line"></i>
                            </div>
                            <div class="health-stat-content">
                                <span class="health-stat-value">{{ $summary['total'] }}</span>
                                <span class="health-stat-label">{{ translate('Total Gateways') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-4 col-md-4 col-sm-6">
                        <div class="health-stat-card health-stat-card--success">
                            <div class="health-stat-icon">
                                <i class="ri-heart-pulse-fill"></i>
                            </div>
                            <div class="health-stat-content">
                                <span class="health-stat-value">{{ $summary['healthy'] }}</span>
                                <span class="health-stat-label">{{ translate('Healthy') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-4 col-md-4 col-sm-6">
                        <div class="health-stat-card health-stat-card--warning">
                            <div class="health-stat-icon">
                                <i class="ri-alert-line"></i>
                            </div>
                            <div class="health-stat-content">
                                <span class="health-stat-value">{{ $summary['degraded'] }}</span>
                                <span class="health-stat-label">{{ translate('Degraded') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-4 col-md-4 col-sm-6">
                        <div class="health-stat-card health-stat-card--danger">
                            <div class="health-stat-icon">
                                <i class="ri-heart-3-line"></i>
                            </div>
                            <div class="health-stat-content">
                                <span class="health-stat-value">{{ $summary['unhealthy'] + $summary['unknown'] }}</span>
                                <span class="health-stat-label">{{ translate('Unhealthy / Unknown') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quality Rating Distribution --}}
        <div class="row g-4 mb-4">
            <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="quality-card quality-card--green">
                    <div class="quality-card-header">
                        <div class="quality-icon">
                            <i class="ri-thumb-up-fill"></i>
                        </div>
                        <div class="quality-info">
                            <h5>{{ translate('Green Quality') }}</h5>
                            <span>{{ translate('High quality, no issues') }}</span>
                        </div>
                    </div>
                    <div class="quality-card-body">
                        <span class="quality-value">{{ $summary['quality']['green'] }}</span>
                        <span class="quality-label">{{ translate('gateways') }}</span>
                    </div>
                    <div class="quality-card-footer">
                        <div class="quality-bar">
                            <div class="quality-bar-fill" style="width: {{ $summary['total'] > 0 ? ($summary['quality']['green'] / $summary['total'] * 100) : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="quality-card quality-card--yellow">
                    <div class="quality-card-header">
                        <div class="quality-icon">
                            <i class="ri-alert-fill"></i>
                        </div>
                        <div class="quality-info">
                            <h5>{{ translate('Yellow Quality') }}</h5>
                            <span>{{ translate('Some quality issues') }}</span>
                        </div>
                    </div>
                    <div class="quality-card-body">
                        <span class="quality-value">{{ $summary['quality']['yellow'] }}</span>
                        <span class="quality-label">{{ translate('gateways') }}</span>
                    </div>
                    <div class="quality-card-footer">
                        <div class="quality-bar">
                            <div class="quality-bar-fill" style="width: {{ $summary['total'] > 0 ? ($summary['quality']['yellow'] / $summary['total'] * 100) : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="quality-card quality-card--red">
                    <div class="quality-card-header">
                        <div class="quality-icon">
                            <i class="ri-thumb-down-fill"></i>
                        </div>
                        <div class="quality-info">
                            <h5>{{ translate('Red Quality') }}</h5>
                            <span>{{ translate('Critical quality issues') }}</span>
                        </div>
                    </div>
                    <div class="quality-card-body">
                        <span class="quality-value">{{ $summary['quality']['red'] }}</span>
                        <span class="quality-label">{{ translate('gateways') }}</span>
                    </div>
                    <div class="quality-card-footer">
                        <div class="quality-bar">
                            <div class="quality-bar-fill" style="width: {{ $summary['total'] > 0 ? ($summary['quality']['red'] / $summary['total'] * 100) : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Needs Attention --}}
        @if($needsAttention->count() > 0)
        <div class="card mb-4 attention-card">
            <div class="card-header attention-header">
                <div class="card-header-left">
                    <div class="d-flex align-items-center gap-3">
                        <div class="attention-icon">
                            <i class="ri-error-warning-line"></i>
                        </div>
                        <div>
                            <h4 class="card-title mb-0 text-white">{{ translate('Needs Attention') }}</h4>
                            <small class="text-white-50">{{ $needsAttention->count() }} {{ translate('gateways require attention') }}</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body px-0 pt-0">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">{{ translate('Gateway') }}</th>
                                <th scope="col">{{ translate('Health') }}</th>
                                <th scope="col">{{ translate('Quality') }}</th>
                                <th scope="col">{{ translate('Issue') }}</th>
                                <th scope="col" class="text-end">{{ translate('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($needsAttention as $gateway)
                            <tr>
                                <td data-label="{{ translate('Gateway') }}">
                                    <div class="gateway-info">
                                        <strong>{{ $gateway->name }}</strong>
                                        @if($gateway->address)
                                            <span class="gateway-address">{{ $gateway->address }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="{{ translate('Health') }}">
                                    @php
                                        $healthClass = match($gateway->health_status) {
                                            'healthy' => 'success',
                                            'degraded' => 'warning',
                                            'unhealthy' => 'danger',
                                            default => 'secondary'
                                        };
                                    @endphp
                                    <span class="health-badge health-badge--{{ $healthClass }}">
                                        <span class="health-dot"></span>
                                        {{ ucfirst($gateway->health_status ?? 'unknown') }}
                                    </span>
                                </td>
                                <td data-label="{{ translate('Quality') }}">
                                    @if($gateway->quality_rating)
                                        @php
                                            $qualityClass = match(strtolower($gateway->quality_rating)) {
                                                'green' => 'success',
                                                'yellow' => 'warning',
                                                'red' => 'danger',
                                                default => 'secondary'
                                            };
                                        @endphp
                                        <span class="i-badge {{ $qualityClass }}-solid pill">{{ $gateway->quality_rating }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td data-label="{{ translate('Issue') }}">
                                    @if($gateway->consecutive_failures > 3)
                                        <span class="issue-text issue-text--danger">
                                            <i class="ri-error-warning-line"></i>
                                            {{ $gateway->consecutive_failures }} {{ translate('consecutive failures') }}
                                        </span>
                                    @elseif($gateway->quality_rating === 'RED')
                                        <span class="issue-text issue-text--danger">
                                            <i class="ri-alert-line"></i>
                                            {{ translate('Quality rating critical') }}
                                        </span>
                                    @elseif(!$gateway->last_health_check_at)
                                        <span class="issue-text issue-text--warning">
                                            <i class="ri-time-line"></i>
                                            {{ translate('Never checked') }}
                                        </span>
                                    @else
                                        <span class="issue-text issue-text--warning">
                                            <i class="ri-eye-line"></i>
                                            {{ translate('Needs review') }}
                                        </span>
                                    @endif
                                </td>
                                <td data-label="{{ translate('Action') }}" class="text-end">
                                    <button type="button" class="i-btn btn--primary btn--sm check-gateway" data-id="{{ $gateway->id }}">
                                        <i class="ri-refresh-line"></i> {{ translate('Check') }}
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- All Gateways --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-left">
                    <h4 class="card-title">{{ translate('Cloud API Gateways') }}</h4>
                </div>
                <div class="card-header-right d-flex gap-2">
                    <div class="search-box">
                        <input type="text" id="searchGateway" class="form-control form-control-sm" placeholder="{{ translate('Search...') }}">
                        <i class="ri-search-line"></i>
                    </div>
                </div>
            </div>
            <div class="card-body px-0 pt-0">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">{{ translate('Gateway') }}</th>
                                <th scope="col">{{ translate('Health') }}</th>
                                <th scope="col">{{ translate('Quality') }}</th>
                                <th scope="col">{{ translate('Messaging Tier') }}</th>
                                <th scope="col">{{ translate('Last Check') }}</th>
                                <th scope="col" class="text-end">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($gateways as $gateway)
                            <tr>
                                <td data-label="{{ translate('Gateway') }}">
                                    <div class="gateway-info">
                                        <strong>{{ $gateway->name }}</strong>
                                        @if($gateway->address)
                                            <span class="gateway-address">{{ $gateway->address }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="{{ translate('Health') }}">
                                    @php
                                        $healthClass = match($gateway->health_status) {
                                            'healthy' => 'success',
                                            'degraded' => 'warning',
                                            'unhealthy' => 'danger',
                                            default => 'secondary'
                                        };
                                    @endphp
                                    <span class="health-badge health-badge--{{ $healthClass }}">
                                        <span class="health-dot"></span>
                                        {{ ucfirst($gateway->health_status ?? 'unknown') }}
                                    </span>
                                </td>
                                <td data-label="{{ translate('Quality') }}">
                                    @if($gateway->quality_rating)
                                        @php
                                            $qualityClass = match(strtolower($gateway->quality_rating)) {
                                                'green' => 'success',
                                                'yellow' => 'warning',
                                                'red' => 'danger',
                                                default => 'secondary'
                                            };
                                        @endphp
                                        <span class="i-badge {{ $qualityClass }}-solid pill">{{ $gateway->quality_rating }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td data-label="{{ translate('Messaging Tier') }}">
                                    @if($gateway->messaging_limit_tier)
                                        <span class="tier-badge">{{ str_replace('TIER_', 'Tier ', $gateway->messaging_limit_tier) }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td data-label="{{ translate('Last Check') }}">
                                    @if($gateway->last_health_check_at)
                                        <span class="last-check">
                                            <i class="ri-time-line"></i>
                                            {{ $gateway->last_health_check_at->diffForHumans() }}
                                        </span>
                                    @else
                                        <span class="text-muted">{{ translate('Never') }}</span>
                                    @endif
                                </td>
                                <td data-label="{{ translate('Actions') }}" class="text-end">
                                    <button type="button" class="action-btn action-btn--success check-gateway" data-id="{{ $gateway->id }}" data-bs-toggle="tooltip" title="{{ translate('Run Health Check') }}">
                                        <i class="ri-refresh-line"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <div class="empty-state-illustration">
                                            <div class="empty-icon-wrapper">
                                                <i class="ri-heart-pulse-line"></i>
                                            </div>
                                        </div>
                                        <h5 class="empty-state-title">{{ translate('No Cloud API Gateways') }}</h5>
                                        <p class="empty-state-text">{{ translate('Add Cloud API gateways to monitor their health status') }}</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($gateways->hasPages())
                <div class="pagination-wrapper">
                    @include('admin.partials.pagination', ['paginator' => $gateways])
                </div>
                @endif
            </div>
        </div>
        @endif
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

/* Health Stat Cards */
.health-stat-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px;
    border-radius: 12px;
    background: #fff;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}
.health-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}
.health-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}
.health-stat-card--default .health-stat-icon { background: rgba(var(--primary-rgb), 0.15); color: var(--primary-color); }
.health-stat-card--success .health-stat-icon { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.health-stat-card--warning .health-stat-icon { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.health-stat-card--danger .health-stat-icon { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.health-stat-content { flex: 1; }
.health-stat-value { display: block; font-size: 24px; font-weight: 700; color: #1f2937; line-height: 1.2; }
.health-stat-label { display: block; font-size: 12px; color: #6b7280; margin-top: 2px; }

/* Quality Cards */
.quality-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    transition: all 0.3s;
}
.quality-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.quality-card-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 20px;
}
.quality-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.quality-card--green .quality-icon { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.quality-card--yellow .quality-icon { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.quality-card--red .quality-icon { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.quality-info h5 { font-size: 15px; font-weight: 600; color: #1f2937; margin-bottom: 2px; }
.quality-info span { font-size: 12px; color: #6b7280; }
.quality-card-body {
    padding: 0 20px 20px;
    display: flex;
    align-items: baseline;
    gap: 6px;
}
.quality-value { font-size: 36px; font-weight: 700; }
.quality-card--green .quality-value { color: #10b981; }
.quality-card--yellow .quality-value { color: #f59e0b; }
.quality-card--red .quality-value { color: #ef4444; }
.quality-label { font-size: 14px; color: #6b7280; }
.quality-card-footer { padding: 0 20px 20px; }
.quality-bar {
    height: 6px;
    background: #f3f4f6;
    border-radius: 3px;
    overflow: hidden;
}
.quality-bar-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease; }
.quality-card--green .quality-bar-fill { background: #10b981; }
.quality-card--yellow .quality-bar-fill { background: #f59e0b; }
.quality-card--red .quality-bar-fill { background: #ef4444; }

/* Attention Card */
.attention-card { border: 1px solid #ef4444 !important; }
.attention-header {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
    border-bottom: none !important;
}
.attention-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 20px;
}

/* Gateway Info */
.gateway-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.gateway-info strong { color: #1f2937; }
.gateway-address { font-size: 12px; color: #6b7280; }

/* Health Badge */
.health-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}
.health-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}
.health-badge--success { background: rgba(16, 185, 129, 0.1); color: #059669; }
.health-badge--success .health-dot { background: #10b981; }
.health-badge--warning { background: rgba(245, 158, 11, 0.1); color: #d97706; }
.health-badge--warning .health-dot { background: #f59e0b; }
.health-badge--danger { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
.health-badge--danger .health-dot { background: #ef4444; }
.health-badge--secondary { background: rgba(107, 114, 128, 0.1); color: #6b7280; }
.health-badge--secondary .health-dot { background: #9ca3af; }

/* Tier Badge */
.tier-badge {
    display: inline-block;
    padding: 4px 10px;
    background: #f1f5f9;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    color: #475569;
}

/* Issue Text */
.issue-text {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
}
.issue-text--danger { color: #dc2626; }
.issue-text--warning { color: #d97706; }

/* Last Check */
.last-check {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #6b7280;
}

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

/* Action Button */
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
}
.action-btn--success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.action-btn--success:hover { background: #10b981; color: #fff; }

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}
.empty-state-illustration { margin-bottom: 20px; }
.empty-icon-wrapper {
    width: 80px;
    height: 80px;
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.15) 0%, rgba(var(--primary-rgb), 0.05) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    font-size: 36px;
    color: var(--primary-color);
}
.empty-state-title { font-size: 18px; font-weight: 600; color: #1f2937; margin-bottom: 8px; }
.empty-state-text { color: #6b7280; max-width: 300px; margin: 0 auto; margin-bottom: 20px; }

/* Pagination Wrapper */
.pagination-wrapper {
    padding: 16px 24px;
    border-top: 1px solid #e5e7eb;
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
        $('#searchGateway').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });

        // Check single gateway
        $('.check-gateway').on('click', function() {
            var btn = $(this);
            var id = btn.data('id');
            var originalHtml = btn.html();

            btn.html('<i class="ri-loader-4-line ri-spin"></i>').prop('disabled', true);

            $.ajax({
                url: "{{ route('admin.whatsapp.health.check', ':id') }}".replace(':id', id),
                type: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(response) {
                    notify(response.success ? 'success' : 'error', response.message);
                    if (response.success) {
                        setTimeout(function() { location.reload(); }, 1500);
                    }
                },
                error: function(xhr) {
                    notify('error', xhr.responseJSON?.message || '{{ translate("Health check failed") }}');
                },
                complete: function() {
                    btn.html(originalHtml).prop('disabled', false);
                }
            });
        });

        // Run all health checks
        $('#runAllHealthChecks').on('click', function() {
            var btn = $(this);
            var originalHtml = btn.html();

            btn.html('<i class="ri-loader-4-line ri-spin me-2"></i>{{ translate("Running...") }}').prop('disabled', true);

            $.ajax({
                url: "{{ route('admin.whatsapp.health.check-all') }}",
                type: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(response) {
                    notify('success', response.message);
                    setTimeout(function() { location.reload(); }, 2000);
                },
                error: function(xhr) {
                    notify('error', xhr.responseJSON?.message || '{{ translate("Health check failed") }}');
                },
                complete: function() {
                    btn.html(originalHtml).prop('disabled', false);
                }
            });
        });
    });
})(jQuery);
</script>
@endpush

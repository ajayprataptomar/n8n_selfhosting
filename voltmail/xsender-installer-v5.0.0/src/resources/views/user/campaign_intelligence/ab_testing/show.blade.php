@extends('user.layouts.app')

@push("style-include")
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

    .icon-circle-lg {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .icon-circle-lg.bg-primary {
        background: var(--color-primary-light);
        color: var(--color-primary);
    }
    .icon-circle-lg.bg-success {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }
    .icon-circle-lg.bg-warning {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
    }
    .icon-circle-lg.bg-info {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }

    .variant-card {
        background: var(--card-bg, #fff);
        border: 2px solid var(--border-color, #e9ecef);
        border-radius: 16px;
        padding: 24px;
        text-align: center;
        height: 100%;
        transition: all 0.3s ease;
    }
    .variant-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    }
    .variant-card-winner {
        border-color: var(--success-color, #10b981);
        background: rgba(16, 185, 129, 0.03);
    }
    .variant-label-lg {
        width: 72px;
        height: 72px;
        background: var(--color-primary);
        color: #fff;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 2rem;
        position: relative;
    }
    .variant-label-lg.variant-winner {
        background: linear-gradient(135deg, #10b981, #059669);
    }
    .variant-label-lg .trophy-icon {
        position: absolute;
        top: -10px;
        right: -10px;
        width: 28px;
        height: 28px;
        background: linear-gradient(135deg, #ffd700, #ffb347);
        color: #000;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.875rem;
    }
    .variant-stats {
        margin-top: 24px;
        text-align: left;
    }
    .stat-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid var(--border-color, #e9ecef);
    }
    .stat-row:last-child {
        border-bottom: none;
    }
    .stat-row.highlight {
        background: var(--bg-light, #f8f9fa);
        margin: 0 -16px;
        padding: 12px 16px;
        border-radius: 8px;
        border: none;
    }
    .stat-label {
        color: var(--text-muted, #6b7280);
        font-size: 0.875rem;
    }
    .stat-value {
        font-weight: 600;
        font-size: 1rem;
    }
    .variant-action {
        margin-top: 24px;
        padding-top: 24px;
        border-top: 1px solid var(--border-color, #e9ecef);
    }

    .summary-stat-card {
        background: var(--bg-light, #f8f9fa);
        border-radius: 12px;
        padding: 1.5rem;
        text-align: center;
        transition: all 0.3s ease;
    }
    .summary-stat-card:hover {
        transform: translateY(-2px);
    }
    .summary-stat-value {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .summary-stat-label {
        font-size: 0.875rem;
        color: var(--text-muted, #6b7280);
        margin-top: 0.25rem;
    }

    .chart-container {
        position: relative;
        min-height: 280px;
    }

    /* Dark Mode */
    [data-theme="dark"] .variant-card {
        background: var(--card-bg-dark, #1f2937);
        border-color: var(--border-color-dark, #374151);
    }
    [data-theme="dark"] .variant-card-winner {
        border-color: #10b981;
        background: rgba(16, 185, 129, 0.08);
    }
    [data-theme="dark"] .summary-stat-card {
        background: var(--card-bg-dark, #1f2937);
    }
</style>
@endpush

@section('panel')
<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ $title }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('user.dashboard') }}">{{ translate('Dashboard') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('user.campaign.intelligence.ab-test.index') }}">{{ translate('A/B Tests') }}</a></li>
                            <li class="breadcrumb-item active">{{ translate('Results') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                <div class="d-flex gap-2">
                    @if($test->status == 'running')
                        <form action="{{ route('user.campaign.intelligence.ab-test.pause', $test->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="i-btn btn--warning btn--md">
                                <i class="ri-pause-line me-1"></i>{{ translate('Pause Test') }}
                            </button>
                        </form>
                    @elseif($test->status == 'paused')
                        <form action="{{ route('user.campaign.intelligence.ab-test.resume', $test->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="i-btn btn--primary btn--md">
                                <i class="ri-play-line me-1"></i>{{ translate('Resume Test') }}
                            </button>
                        </form>
                    @endif
                    @if($test->hasWinner())
                        <form action="{{ route('user.campaign.intelligence.ab-test.apply-winner', $test->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="i-btn btn--success btn--md">
                                <i class="ri-check-double-line me-1"></i>{{ translate('Apply Winner') }}
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('user.campaign.intelligence.ab-test.index') }}" class="i-btn btn--dark outline btn--md">
                        <i class="ri-arrow-left-line"></i> {{ translate('Back') }}
                    </a>
                </div>
            </div>
        </div>

        <!-- Test Status Card -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-center gap-3">
                            @php
                                $statusEnum = \App\Enums\Campaign\AbTestStatus::tryFrom($test->status);
                                $statusClass = $statusEnum ? str_replace('badge--', '', $statusEnum->badgeClass()) : 'secondary';
                            @endphp
                            <div class="icon-circle-lg bg-{{ $statusClass == 'warning' ? 'warning' : ($statusClass == 'success' ? 'success' : 'primary') }}">
                                <i class="{{ $statusEnum ? $statusEnum->icon() : 'ri-flask-line' }} fs-3"></i>
                            </div>
                            <div>
                                <span class="i-badge capsuled {{ $statusEnum ? str_replace('badge--', '', $statusEnum->badgeClass()) : 'secondary' }} mb-2">
                                    {{ $statusEnum ? $statusEnum->label() : ucfirst($test->status) }}
                                </span>
                                <h4 class="mb-1">{{ $test->name }}</h4>
                                <p class="text-muted mb-0">
                                    {{ translate('Campaign') }}: <strong>{{ $test->campaign->name ?? 'N/A' }}</strong>
                                    <span class="mx-2">&bull;</span>
                                    {{ translate('Started') }}: {{ $test->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                        @if($test->isRunning())
                            <div class="text-muted small mb-1">{{ translate('Time Remaining') }}</div>
                            <div class="fs-3 fw-bold" style="color: var(--color-primary)">
                                {{ $summary['time_remaining'] ?? 0 }} {{ translate('hours') }}
                            </div>
                        @elseif($test->hasWinner())
                            <div class="text-muted small mb-1">{{ translate('Winner Selected') }}</div>
                            <div class="fs-3 fw-bold text-success">
                                <i class="ri-trophy-line me-1"></i>{{ translate('Variant') }} {{ $test->winningVariant->variant_label ?? 'N/A' }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-6">
                <div class="summary-stat-card">
                    <div class="summary-stat-value">{{ number_format($summary['total_contacts'] ?? 0) }}</div>
                    <div class="summary-stat-label">{{ translate('Total Contacts') }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="summary-stat-card">
                    <div class="summary-stat-value" style="color: var(--color-primary)">{{ number_format($summary['total_sent'] ?? 0) }}</div>
                    <div class="summary-stat-label">{{ translate('Messages Sent') }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="summary-stat-card">
                    <div class="summary-stat-value text-success">{{ number_format($summary['avg_delivery_rate'] ?? 0, 1) }}%</div>
                    <div class="summary-stat-label">{{ translate('Avg. Delivery Rate') }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="summary-stat-card">
                    <div class="summary-stat-value" style="color: #3b82f6">{{ number_format($summary['avg_open_rate'] ?? 0, 1) }}%</div>
                    <div class="summary-stat-label">{{ translate('Avg. Open Rate') }}</div>
                </div>
            </div>
        </div>

        <!-- Variant Comparison -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="card-title mb-0">
                    <i class="ri-bar-chart-grouped-line me-2"></i>{{ translate('Variant Performance Comparison') }}
                </h4>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    @foreach($evaluation['variants'] ?? [] as $label => $data)
                        @php $variant = $data['variant']; @endphp
                        <div class="col-lg-{{ 12 / max(1, count($evaluation['variants'] ?? [])) }}">
                            <div class="variant-card {{ $variant->is_winner ? 'variant-card-winner' : '' }}">
                                <div class="variant-label-lg {{ $variant->is_winner ? 'variant-winner' : '' }}">
                                    {{ $label }}
                                    @if($variant->is_winner)
                                        <span class="trophy-icon"><i class="ri-trophy-fill"></i></span>
                                    @endif
                                </div>
                                <h5 class="mt-3 mb-0">{{ translate('Variant') }} {{ $label }}</h5>
                                @if($variant->is_winner)
                                    <span class="i-badge capsuled success mt-2">{{ translate('Winner') }}</span>
                                @endif

                                <div class="variant-stats">
                                    <div class="stat-row">
                                        <span class="stat-label">{{ translate('Contacts') }}</span>
                                        <span class="stat-value">{{ number_format($data['contact_count'] ?? 0) }}</span>
                                    </div>
                                    <div class="stat-row">
                                        <span class="stat-label">{{ translate('Sent') }}</span>
                                        <span class="stat-value">{{ number_format($data['sent_count'] ?? 0) }}</span>
                                    </div>
                                    <div class="stat-row">
                                        <span class="stat-label">{{ translate('Delivered') }}</span>
                                        <span class="stat-value">{{ number_format($data['delivered_count'] ?? 0) }}</span>
                                    </div>
                                    <div class="stat-row highlight">
                                        <span class="stat-label">{{ translate('Delivery Rate') }}</span>
                                        <span class="stat-value text-success">{{ number_format($data['delivery_rate'] ?? 0, 1) }}%</span>
                                    </div>
                                    <div class="stat-row">
                                        <span class="stat-label">{{ translate('Opened') }}</span>
                                        <span class="stat-value">{{ number_format($data['opened_count'] ?? 0) }}</span>
                                    </div>
                                    <div class="stat-row highlight">
                                        <span class="stat-label">{{ translate('Open Rate') }}</span>
                                        <span class="stat-value" style="color: #3b82f6">{{ number_format($data['open_rate'] ?? 0, 1) }}%</span>
                                    </div>
                                    <div class="stat-row">
                                        <span class="stat-label">{{ translate('Clicked') }}</span>
                                        <span class="stat-value">{{ number_format($data['clicked_count'] ?? 0) }}</span>
                                    </div>
                                    <div class="stat-row highlight">
                                        <span class="stat-label">{{ translate('Click Rate') }}</span>
                                        <span class="stat-value" style="color: #f59e0b">{{ number_format($data['click_rate'] ?? 0, 1) }}%</span>
                                    </div>
                                </div>

                                @if(!$test->hasWinner() && ($evaluation['can_select_winner'] ?? false))
                                    <div class="variant-action">
                                        <form action="{{ route('user.campaign.intelligence.ab-test.select-winner', $test->id) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                                            <button type="submit" class="i-btn btn--primary btn--md w-100">
                                                <i class="ri-trophy-line me-1"></i>{{ translate('Select as Winner') }}
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Performance Chart -->
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">
                    <i class="ri-line-chart-line me-2"></i>{{ translate('Metric Comparison Chart') }}
                </h4>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="variantChart"></canvas>
                </div>
            </div>
        </div>

    </div>
</main>
@endsection

@push("script-push")
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function($) {
    "use strict";

    // Variant Comparison Chart
    const variants = @json($evaluation['variants'] ?? []);
    const labels = Object.keys(variants).map(k => '{{ translate("Variant") }} ' + k);
    const deliveryRates = Object.values(variants).map(v => v.delivery_rate || 0);
    const openRates = Object.values(variants).map(v => v.open_rate || 0);
    const clickRates = Object.values(variants).map(v => v.click_rate || 0);

    const ctx = document.getElementById('variantChart');
    if (ctx && labels.length > 0) {
        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: '{{ translate("Delivery Rate") }}',
                        data: deliveryRates,
                        backgroundColor: 'rgba(16, 185, 129, 0.8)',
                        borderRadius: 6,
                    },
                    {
                        label: '{{ translate("Open Rate") }}',
                        data: openRates,
                        backgroundColor: 'rgba(59, 130, 246, 0.8)',
                        borderRadius: 6,
                    },
                    {
                        label: '{{ translate("Click Rate") }}',
                        data: clickRates,
                        backgroundColor: 'rgba(245, 158, 11, 0.8)',
                        borderRadius: 6,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

})(jQuery);
</script>
@endpush

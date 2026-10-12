@extends('admin.layouts.app')

@push("style-include")
<style>
    /* Page Header */
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

    /* Test Hero Card */
    .test-hero-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 20px;
        padding: 1.25rem;
        color: #fff;
        position: relative;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }
    .test-hero-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        border-radius: 50%;
    }
    .test-hero-card::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
        border-radius: 50%;
    }
    .test-hero-content {
        position: relative;
        z-index: 1;
    }
    .test-hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: rgba(255,255,255,0.2);
        backdrop-filter: blur(10px);
        padding: 0.15rem 1rem;
        border-radius: 50px;
        font-size: 0.8125rem;
        font-weight: 600;
        margin-bottom: 1rem;
    }
    .test-hero-badge i {
        font-size: 1rem;
    }
    .test-hero-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    .test-hero-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin-top: 1rem;
        opacity: 0.9;
    }
    .test-hero-meta-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 4px 10px;
        border-radius: 20px;
    }
    .test-hero-timer {
        text-align: right;
    }
    .test-hero-timer-label {
        font-size: 0.8125rem;
        opacity: 0.8;
        margin-bottom: 0.25rem;
    }
    .test-hero-timer-value {
        font-size: 1.75rem;
        font-weight: 600;
        line-height: 1;
    }
    .test-hero-timer-unit {
        font-size: 1rem;
        font-weight: 400;
        opacity: 0.8;
        margin-left: 0.25rem;
    }

    /* Status specific colors */
    .test-hero-card.status-running {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    .test-hero-card.status-completed {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }
    .test-hero-card.status-paused {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    .test-hero-card.status-draft {
        /* background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); */
        background: var(--card-bg);
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    @media (max-width: 1200px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 576px) {
        .stats-grid { grid-template-columns: 1fr; }
        .test-hero-card { padding: 1.5rem; }
        .test-hero-title { font-size: 1.25rem; }
        .test-hero-timer-value { font-size: 2rem; }
    }

    .stat-card {
        background: var(--card-bg, #fff);
        border-radius: 16px;
        padding: 1rem;
        border: 1px solid var(--color-border-light);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
        display: flex;
        align-items:center;
        gap: 16px;
    }
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 3px;
        height: 100%;
    }
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    }
    .stat-card.contacts::before { background: linear-gradient(180deg, #6366f1, #8b5cf6);opacity:0.4; }
    .stat-card.sent::before { background: linear-gradient(180deg, #3b82f6, #0ea5e9);opacity:0.4; }
    .stat-card.delivery::before { background: linear-gradient(180deg, #10b981, #14b8a6);opacity:0.4; }
    .stat-card.opens::before { background: linear-gradient(180deg, #f59e0b, #f97316);opacity:0.4; }

    .stat-card-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }
    .stat-card.contacts .stat-card-icon { background: rgba(99, 102, 241, 0.1); color: #6366f1; }
    .stat-card.sent .stat-card-icon { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .stat-card.delivery .stat-card-icon { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .stat-card.opens .stat-card-icon { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

    .stat-card-value {
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.2;
        margin-bottom: 0.25rem;
        color: var(--text-primary);
        margin-top: -8px;
    }
    .stat-card-label {
        font-size: 0.8125rem;
        color: var(--text-muted);
        font-weight: 500;
    }
    .stat-card-trend {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.75rem;
        font-weight: 600;
        margin-top: 0.5rem;
        padding: 0.25rem 0.5rem;
        border-radius: 20px;
    }
    .stat-card-trend.up {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }
    .stat-card-trend.down {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }

    /* Variant Cards */
    .variants-section {
        margin-bottom: 1.5rem;
    }
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    .section-title {
        font-size: 1.125rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .section-title i {
        color: var(--color-primary);
    }

    .variant-card {
        background: var(--card-bg, #fff);
        border: 2px solid var(--color-border-light);
        border-radius: 20px;
        overflow: hidden;
        height: 100%;
        transition: all 0.3s ease;
    }
    .variant-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
    }
    .variant-card.winner {
        border-color: #10b981;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
    }
    .variant-card-header {
        padding: 1.5rem;
        text-align: center;
        background: var(--site-bg, #f8fafc);
        border-bottom: 1px solid var(--color-border-light);
        position: relative;
    }
    .variant-card.winner .variant-card-header {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.05) 100%);
    }
    .variant-label-circle {
        width: 80px;
        height: 80px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 2.5rem;
        margin: 0 auto 1rem;
        position: relative;
        background: linear-gradient(135deg, var(--color-primary) 0%, #8b5cf6 100%);
        color: #fff;
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
    }
    .variant-card.winner .variant-label-circle {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
    }
    .winner-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        width: 32px;
        height: 32px;
        background: linear-gradient(135deg, #ffd700, #ffb347);
        color: #000;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        box-shadow: 0 4px 12px rgba(255, 215, 0, 0.4);
    }
    .variant-name {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    .variant-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.375rem 0.75rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }

    .variant-card-body {
        padding: 1.5rem;
    }
    .metric-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.875rem 0;
        border-bottom: 1px solid var(--color-border-light);
    }
    .metric-item:last-child {
        border-bottom: none;
    }
    .metric-item.highlight {
        background: var(--site-bg, #f8fafc);
        margin: 0 -1.5rem;
        padding: 0.875rem 1.5rem;
        border-bottom: none;
    }
    .metric-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--text-muted);
        font-size: 0.875rem;
    }
    .metric-label i {
        font-size: 1rem;
        width: 20px;
    }
    .metric-value {
        font-weight: 600;
        font-size: 0.9375rem;
    }
    .metric-value.success { color: #10b981; }
    .metric-value.primary { color: var(--color-primary); }
    .metric-value.warning { color: #f59e0b; }
    .metric-value.info { color: #3b82f6; }

    .metric-progress {
        margin-top: 0.5rem;
    }
    .progress-bar-sm {
        height: 6px;
        background: var(--color-border-light);
        border-radius: 3px;
        overflow: hidden;
    }
    .progress-bar-sm .fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.6s ease;
    }
    .progress-bar-sm .fill.success { background: linear-gradient(90deg, #10b981, #14b8a6); }
    .progress-bar-sm .fill.primary { background: linear-gradient(90deg, var(--color-primary), #8b5cf6); }
    .progress-bar-sm .fill.warning { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
    .progress-bar-sm .fill.info { background: linear-gradient(90deg, #3b82f6, #60a5fa); }

    .variant-card-footer {
        padding: 1rem 1.5rem;
        background: var(--site-bg, #f8fafc);
        border-top: 1px solid var(--color-border-light);
    }

    /* Test Config Card */
    .config-card {
        background: var(--card-bg, #fff);
        border-radius: 16px;
        border: 1px solid var(--color-border-light);
        margin-bottom: 1.5rem;
    }
    .config-card-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--color-border-light);
    }
    .config-card-title {
        font-size: 1rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0;
    }
    .config-card-title i {
        color: var(--color-primary);
    }
    .config-card-body {
        padding: 1.5rem;
    }
    .config-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
    }
    .config-item {
        text-align: left;
        padding: 12px;
        transition: all 0.4s ease;
        border-radius: 12px;
        display: flex;
        gap: 12px;
        border: 1px solid var(--color-border);
    }
    .config-item:hover{
        border: 1px solid var(--color-primary);
    }
    .config-item-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--color-primary-light);
        color: var(--color-primary);
        margin-bottom: 0.75rem;
        font-size: 20px
    }
    .config-item-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
        font-weight: 600;
        margin-bottom: 0.125rem;
    }
    .config-item-value {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    /* Chart Card */
    .chart-card {
        background: var(--card-bg, #fff);
        border-radius: 16px;
        border: 1px solid var(--color-border-light);
        overflow: hidden;
    }
    .chart-card-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--color-border-light);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .chart-card-title {
        font-size: 1rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0;
    }
    .chart-card-title i {
        color: var(--color-primary);
    }
    .chart-tabs {
        display: flex;
        gap: 0.5rem;
    }
    .chart-tab {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.8125rem;
        font-weight: 500;
        border: none;
        background: transparent;
        color: var(--text-muted);
        cursor: pointer;
        transition: all 0.2s;
    }
    .chart-tab:hover {
        background: var(--site-bg, #f8fafc);
    }
    .chart-tab.active {
        background: var(--color-primary);
        color: #fff;
    }
    .chart-card-body {
        padding: 1.5rem;
    }
    .chart-container {
        position: relative;
        min-height: 320px;
    }

    /* Confidence Indicator */
    .confidence-section {
        margin-top: 1.5rem;
    }
    .confidence-card {
        background: var(--card-bg, #fff);
        border-radius: 16px;
        border: 1px solid var(--color-border-light);
        padding: 1.5rem;
    }
    .confidence-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    .confidence-title {
        font-size: 1rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .confidence-title i {
        color: var(--color-primary);
    }
    .confidence-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #10b981;
    }
    .confidence-bar {
        height: 12px;
        background: var(--color-border-light);
        border-radius: 6px;
        overflow: hidden;
        margin-bottom: 0.75rem;
    }
    .confidence-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #10b981, #059669);
        border-radius: 6px;
        transition: width 0.8s ease;
    }
    .confidence-labels {
        display: flex;
        justify-content: space-between;
        font-size: 0.75rem;
        color: var(--text-muted);
    }
    .confidence-note {
        margin-top: 1rem;
        padding: 1rem;
        background: rgba(16, 185, 129, 0.05);
        border-radius: 10px;
        border-left: 4px solid #10b981;
        font-size: 0.875rem;
        color: var(--text-muted);
    }
    .confidence-note i {
        color: #10b981;
        margin-right: 0.5rem;
    }

    /* Delete Modal */
    .delete-icon-wrapper {
        display: flex;
        justify-content: center;
    }
    .delete-icon {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: rgba(239, 68, 68, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .delete-icon i {
        font-size: 1.75rem;
        color: #ef4444;
    }

    /* Dark Mode */
    [data-theme="dark"] .test-hero-card {
        background: linear-gradient(135deg, #4f46e5 0%, #6d28d9 100%);
    }
    [data-theme="dark"] .stat-card,
    [data-theme="dark"] .variant-card,
    [data-theme="dark"] .config-card,
    [data-theme="dark"] .chart-card,
    [data-theme="dark"] .confidence-card {
        background: var(--card-bg-dark, #1f2937);
        border-color: var(--border-color-dark, #374151);
    }
    [data-theme="dark"] .variant-card-header,
    [data-theme="dark"] .variant-card-footer,
    [data-theme="dark"] .config-item,
    [data-theme="dark"] .metric-item.highlight {
        background: var(--bg-dark-2, #111827);
    }
    [data-theme="dark"] .chart-tab:hover {
        background: var(--bg-dark-2, #111827);
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
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Dashboard') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.campaign.intelligence.ab-test.index') }}">{{ translate('A/B Tests') }}</a></li>
                            <li class="breadcrumb-item active">{{ translate('Results') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                @if($test->status == 'running')
                    <form action="{{ route('admin.campaign.intelligence.ab-test.pause', $test->id) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="i-btn btn--warning btn--md">
                            <i class="ri-pause-line me-1"></i>{{ translate('Pause Test') }}
                        </button>
                    </form>
                @elseif($test->status == 'paused')
                    <form action="{{ route('admin.campaign.intelligence.ab-test.resume', $test->id) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="i-btn btn--primary btn--md">
                            <i class="ri-play-line me-1"></i>{{ translate('Resume Test') }}
                        </button>
                    </form>
                @endif
                @if($test->hasWinner())
                    <form action="{{ route('admin.campaign.intelligence.ab-test.apply-winner', $test->id) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="i-btn btn--success btn--md">
                            <i class="ri-check-double-line me-1"></i>{{ translate('Apply Winner') }}
                        </button>
                    </form>
                @endif
                <a href="{{ route('admin.campaign.intelligence.ab-test.index') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-arrow-left-line"></i> {{ translate('Back') }}
                </a>
            </div>
        </div>

        <!-- Test Hero Card -->
        @php
            $statusEnum = \App\Enums\Campaign\AbTestStatus::tryFrom($test->status);
            $metricEnum = \App\Enums\Campaign\AbTestWinningMetric::tryFrom($test->winning_metric);
        @endphp
        <div class="test-hero-card status-{{ $test->status }}">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <div class="test-hero-content">
                         <div class="d-flex justify-content-start gap-2">
                            <h1 class="test-hero-title">{{ $test->name }}</h1>

                                <div class="test-hero-badge">
                                <i class="{{ $statusEnum ? $statusEnum->icon() : 'ri-flask-line' }}"></i>
                                {{ $statusEnum ? $statusEnum->label() : ucfirst($test->status) }}
                            </div>
                         </div>
                        <div class="test-hero-meta">
                            <div class="test-hero-meta-item">
                                <i class="ri-megaphone-line"></i>
                                {{ $test->campaign->name ?? translate('N/A') }}
                            </div>
                            <div class="test-hero-meta-item">
                                <i class="ri-calendar-line"></i>
                                {{ translate('Started') }} {{ $test->created_at->format('M d, Y') }}
                            </div>
                            <div class="test-hero-meta-item">
                                <i class="ri-bar-chart-grouped-line"></i>
                                {{ $metricEnum ? $metricEnum->label() : ucfirst($test->winning_metric) }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5 text-lg-end mt-4 mt-lg-0">
                   
                    <div class="test-hero-content">
                        @if($test->isRunning())
                            <div class="test-hero-timer">
                                <div class="test-hero-timer-label">{{ translate('Time Remaining') }}</div>
                                <div class="test-hero-timer-value">
                                    {{ $summary['time_remaining'] ?? 0 }}
                                    <span class="test-hero-timer-unit">{{ translate('hours') }}</span>
                                </div>
                            </div>
                        @elseif($test->hasWinner())
                            <div class="test-hero-timer">
                                <div class="test-hero-timer-label">{{ translate('Winner') }}</div>
                                <div class="test-hero-timer-value">
                                    <i class="ri-trophy-fill me-2"></i>{{ $test->winningVariant->variant_label ?? 'N/A' }}
                                </div>
                            </div>
                        @else
                            <div class="test-hero-timer">
                                <div class="test-hero-timer-label">{{ translate('Duration') }}</div>
                                <div class="test-hero-timer-value">
                                    {{ $test->test_duration ?? 24 }}
                                    <span class="test-hero-timer-unit">{{ translate('hours') }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card contacts">
                <div class="stat-card-icon">
                    <i class="ri-team-line"></i>
                </div>
                <div>
                    <div class="stat-card-value">{{ number_format($summary['total_contacts'] ?? 0) }}</div>
                <div class="stat-card-label">{{ translate('Total Contacts') }}</div>
                </div>
            </div>
            <div class="stat-card sent">
                <div class="stat-card-icon">
                    <i class="ri-send-plane-2-line"></i>
                </div>
                <div>
                    <div class="stat-card-value">{{ number_format($summary['total_sent'] ?? 0) }}</div>
                <div class="stat-card-label">{{ translate('Messages Sent') }}</div>
                </div>
            </div>
            <div class="stat-card delivery">
                <div class="stat-card-icon">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
                <div>
                    <div class="stat-card-value">{{ number_format($summary['avg_delivery_rate'] ?? 0, 1) }}%</div>
                <div class="stat-card-label">{{ translate('Avg. Delivery Rate') }}</div>
                </div>
            </div>
            <div class="stat-card opens">
                <div class="stat-card-icon">
                    <i class="ri-eye-line"></i>
                </div>
                <div>
                    <div class="stat-card-value">{{ number_format($summary['avg_open_rate'] ?? 0, 1) }}%</div>
                <div class="stat-card-label">{{ translate('Avg. Open Rate') }}</div>
                </div>
            </div>
        </div>

        <!-- Test Configuration -->
        <div class="config-card">
            <div class="config-card-header">
                <h4 class="config-card-title">
                    <i class="ri-settings-3-line"></i>{{ translate('Test Configuration') }}
                </h4>
            </div>
            <div class="config-card-body">
                <div class="config-grid">
                    <div class="config-item">
                        <div class="config-item-icon">
                            <i class="ri-percent-line"></i>
                        </div>
                        <div>
                            <div class="config-item-label">{{ translate('Test Percentage') }}</div>
                            <div class="config-item-value">{{ $test->test_percentage ?? 20 }}%</div>
                        </div>
                    </div>
                    <div class="config-item">
                        <div class="config-item-icon">
                            <i class="ri-time-line"></i>
                        </div>
                        <div>
                            <div class="config-item-label">{{ translate('Test Duration') }}</div>
                        <div class="config-item-value">{{ $test->test_duration ?? 24 }} {{ translate('hours') }}</div>
                        </div>
                    </div>
                    <div class="config-item">
                        <div class="config-item-icon">
                            <i class="ri-trophy-line"></i>
                        </div>
                        <div>
                            <div class="config-item-label">{{ translate('Winning Metric') }}</div>
                        <div class="config-item-value">{{ $metricEnum ? $metricEnum->label() : ucfirst($test->winning_metric) }}</div>
                        </div>
                    </div>
                    <div class="config-item">
                        <div class="config-item-icon">
                            <i class="ri-robot-line"></i>
                        </div>
                        <div>
                            <div class="config-item-label">{{ translate('Auto Select Winner') }}</div>
                        <div class="config-item-value">{{ $test->auto_select_winner ? translate('Yes') : translate('No') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Variant Comparison -->
        <div class="variants-section">
            <div class="section-header">
                <h3 class="section-title">
                    <i class="ri-bar-chart-grouped-line"></i>{{ translate('Variant Performance') }}
                </h3>
            </div>
            <div class="row g-4">
                @foreach($evaluation['variants'] as $label => $data)
                    @php $variant = $data['variant']; @endphp
                    <div class="col-lg-{{ 12 / max(1, min(3, count($evaluation['variants']))) }}">
                        <div class="variant-card {{ $variant->is_winner ? 'winner' : '' }}">
                            <div class="variant-card-header">
                                <div class="variant-label-circle">
                                    {{ $label }}
                                    @if($variant->is_winner)
                                        <span class="winner-badge"><i class="ri-trophy-fill"></i></span>
                                    @endif
                                </div>
                                <div class="variant-name">{{ translate('Variant') }} {{ $label }}</div>
                                @if($variant->is_winner)
                                    <div class="variant-status-badge">
                                        <i class="ri-trophy-fill"></i>{{ translate('Winner') }}
                                    </div>
                                @endif
                            </div>
                            <div class="variant-card-body">
                                <div class="metric-item">
                                    <span class="metric-label"><i class="ri-team-line"></i>{{ translate('Contacts') }}</span>
                                    <span class="metric-value">{{ number_format($data['contact_count'] ?? 0) }}</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-label"><i class="ri-send-plane-line"></i>{{ translate('Sent') }}</span>
                                    <span class="metric-value">{{ number_format($data['sent_count'] ?? 0) }}</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-label"><i class="ri-checkbox-circle-line"></i>{{ translate('Delivered') }}</span>
                                    <span class="metric-value">{{ number_format($data['delivered_count'] ?? 0) }}</span>
                                </div>
                                <div class="metric-item highlight">
                                    <span class="metric-label"><i class="ri-bar-chart-line"></i>{{ translate('Delivery Rate') }}</span>
                                    <span class="metric-value success">{{ number_format($data['delivery_rate'] ?? 0, 1) }}%</span>
                                </div>
                                <div class="metric-progress">
                                    <div class="progress-bar-sm">
                                        <div class="fill success" style="width: {{ min(100, $data['delivery_rate'] ?? 0) }}%"></div>
                                    </div>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-label"><i class="ri-eye-line"></i>{{ translate('Opened') }}</span>
                                    <span class="metric-value">{{ number_format($data['opened_count'] ?? 0) }}</span>
                                </div>
                                <div class="metric-item highlight">
                                    <span class="metric-label"><i class="ri-bar-chart-2-line"></i>{{ translate('Open Rate') }}</span>
                                    <span class="metric-value info">{{ number_format($data['open_rate'] ?? 0, 1) }}%</span>
                                </div>
                                <div class="metric-progress">
                                    <div class="progress-bar-sm">
                                        <div class="fill info" style="width: {{ min(100, $data['open_rate'] ?? 0) }}%"></div>
                                    </div>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-label"><i class="ri-cursor-line"></i>{{ translate('Clicked') }}</span>
                                    <span class="metric-value">{{ number_format($data['clicked_count'] ?? 0) }}</span>
                                </div>
                                <div class="metric-item highlight">
                                    <span class="metric-label"><i class="ri-line-chart-line"></i>{{ translate('Click Rate') }}</span>
                                    <span class="metric-value warning">{{ number_format($data['click_rate'] ?? 0, 1) }}%</span>
                                </div>
                                <div class="metric-progress">
                                    <div class="progress-bar-sm">
                                        <div class="fill warning" style="width: {{ min(100, $data['click_rate'] ?? 0) }}%"></div>
                                    </div>
                                </div>
                            </div>
                            @if(!$test->hasWinner() && ($evaluation['can_select_winner'] ?? false))
                                <div class="variant-card-footer">
                                    <form action="{{ route('admin.campaign.intelligence.ab-test.select-winner', $test->id) }}" method="POST">
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

        <!-- Statistical Confidence (if available) -->
        @if(isset($evaluation['confidence_level']) && $evaluation['confidence_level'] > 0)
        <div class="confidence-section">
            <div class="confidence-card">
                <div class="confidence-header">
                    <div class="confidence-title">
                        <i class="ri-shield-check-line"></i>{{ translate('Statistical Confidence') }}
                    </div>
                    <div class="confidence-value">{{ number_format($evaluation['confidence_level'], 1) }}%</div>
                </div>
                <div class="confidence-bar">
                    <div class="confidence-bar-fill" style="width: {{ min(100, $evaluation['confidence_level']) }}%"></div>
                </div>
                <div class="confidence-labels">
                    <span>0%</span>
                    <span>{{ translate('Target') }}: {{ $test->confidence_threshold ?? 95 }}%</span>
                    <span>100%</span>
                </div>
                @if($evaluation['confidence_level'] >= ($test->confidence_threshold ?? 95))
                    <div class="confidence-note">
                        <i class="ri-checkbox-circle-fill"></i>
                        {{ translate('The results have reached statistical significance. You can confidently select a winner.') }}
                    </div>
                @else
                    <div class="confidence-note" style="border-left-color: #f59e0b; background: rgba(245, 158, 11, 0.05);">
                        <i class="ri-information-line" style="color: #f59e0b;"></i>
                        {{ translate('More data is needed to reach statistical significance. Consider letting the test run longer.') }}
                    </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Performance Chart -->
        <div class="chart-card mt-4">
            <div class="chart-card-header">
                <h4 class="chart-card-title">
                    <i class="ri-line-chart-line"></i>{{ translate('Performance Comparison') }}
                </h4>
                <div class="chart-tabs">
                    <button class="chart-tab active" data-chart="bar">{{ translate('Bar') }}</button>
                    <button class="chart-tab" data-chart="radar">{{ translate('Radar') }}</button>
                </div>
            </div>
            <div class="chart-card-body">
                <div class="chart-container" id="barChartContainer">
                    <canvas id="variantBarChart"></canvas>
                </div>
                <div class="chart-container" id="radarChartContainer" style="display: none;">
                    <canvas id="variantRadarChart"></canvas>
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

    const variants = @json($evaluation['variants']);
    const labels = Object.keys(variants).map(k => '{{ translate("Variant") }} ' + k);
    const deliveryRates = Object.values(variants).map(v => v.delivery_rate || 0);
    const openRates = Object.values(variants).map(v => v.open_rate || 0);
    const clickRates = Object.values(variants).map(v => v.click_rate || 0);

    // Chart colors
    const colors = {
        delivery: { bg: 'rgba(16, 185, 129, 0.8)', border: '#10b981' },
        open: { bg: 'rgba(59, 130, 246, 0.8)', border: '#3b82f6' },
        click: { bg: 'rgba(245, 158, 11, 0.8)', border: '#f59e0b' }
    };

    // Bar Chart
    const barCtx = document.getElementById('variantBarChart');
    let barChart = null;
    if (barCtx) {
        barChart = new Chart(barCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: '{{ translate("Delivery Rate") }}',
                        data: deliveryRates,
                        backgroundColor: colors.delivery.bg,
                        borderColor: colors.delivery.border,
                        borderWidth: 2,
                        borderRadius: 8,
                        borderSkipped: false,
                    },
                    {
                        label: '{{ translate("Open Rate") }}',
                        data: openRates,
                        backgroundColor: colors.open.bg,
                        borderColor: colors.open.border,
                        borderWidth: 2,
                        borderRadius: 8,
                        borderSkipped: false,
                    },
                    {
                        label: '{{ translate("Click Rate") }}',
                        data: clickRates,
                        backgroundColor: colors.click.bg,
                        borderColor: colors.click.border,
                        borderWidth: 2,
                        borderRadius: 8,
                        borderSkipped: false,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: { size: 12, weight: '500' }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        cornerRadius: 8,
                        titleFont: { size: 13, weight: '600' },
                        bodyFont: { size: 12 },
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toFixed(1) + '%';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) { return value + '%'; },
                            font: { size: 11 }
                        },
                        grid: { color: 'rgba(0, 0, 0, 0.05)' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 12, weight: '500' } }
                    }
                }
            }
        });
    }

    // Radar Chart
    const radarCtx = document.getElementById('variantRadarChart');
    let radarChart = null;
    if (radarCtx) {
        const variantColors = [
            { bg: 'rgba(99, 102, 241, 0.2)', border: '#6366f1' },
            { bg: 'rgba(16, 185, 129, 0.2)', border: '#10b981' },
            { bg: 'rgba(245, 158, 11, 0.2)', border: '#f59e0b' },
            { bg: 'rgba(239, 68, 68, 0.2)', border: '#ef4444' }
        ];

        const radarDatasets = Object.keys(variants).map((label, index) => {
            const v = variants[label];
            const colorIndex = index % variantColors.length;
            return {
                label: '{{ translate("Variant") }} ' + label,
                data: [v.delivery_rate || 0, v.open_rate || 0, v.click_rate || 0],
                backgroundColor: variantColors[colorIndex].bg,
                borderColor: variantColors[colorIndex].border,
                borderWidth: 2,
                pointBackgroundColor: variantColors[colorIndex].border,
                pointRadius: 4
            };
        });

        radarChart = new Chart(radarCtx.getContext('2d'), {
            type: 'radar',
            data: {
                labels: ['{{ translate("Delivery Rate") }}', '{{ translate("Open Rate") }}', '{{ translate("Click Rate") }}'],
                datasets: radarDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: { size: 12, weight: '500' }
                        }
                    }
                },
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) { return value + '%'; },
                            backdropColor: 'transparent'
                        },
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        angleLines: { color: 'rgba(0, 0, 0, 0.05)' }
                    }
                }
            }
        });
    }

    // Chart tab switching
    $('.chart-tab').on('click', function() {
        const chartType = $(this).data('chart');
        $('.chart-tab').removeClass('active');
        $(this).addClass('active');

        if (chartType === 'bar') {
            $('#barChartContainer').show();
            $('#radarChartContainer').hide();
        } else {
            $('#barChartContainer').hide();
            $('#radarChartContainer').show();
        }
    });

})(jQuery);
</script>
@endpush

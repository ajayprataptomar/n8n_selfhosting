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

    /* Intelligence Dashboard Styles */
    .intelligence-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .intelligence-stat-card {
        background: var(--card-bg, #fff);
        border-radius: 12px;
        padding: 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        border: 1px solid var(--border-color, #e9ecef);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .intelligence-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        border-radius: 4px 0 0 4px;
    }

    .intelligence-stat-card.stat-primary::before { background: var(--color-primary); }
    .intelligence-stat-card.stat-success::before { background: var(--success-color, #10b981); }
    .intelligence-stat-card.stat-info::before { background: var(--info-color, #0ea5e9); }
    .intelligence-stat-card.stat-warning::before { background: var(--warning-color, #f59e0b); }

    .intelligence-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    }

    .intelligence-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .intelligence-stat-icon.icon-primary { background: var(--color-primary-light); color: var(--color-primary); }
    .intelligence-stat-icon.icon-success { background: rgba(16, 185, 129, 0.1); color: var(--success-color, #10b981); }
    .intelligence-stat-icon.icon-info { background: rgba(14, 165, 233, 0.1); color: var(--info-color, #0ea5e9); }
    .intelligence-stat-icon.icon-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning-color, #f59e0b); }

    .intelligence-stat-content h3 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
        line-height: 1;
        color: var(--text-color, #1f2937);
    }

    .intelligence-stat-content p {
        font-size: 0.813rem;
        color: var(--text-muted, #6b7280);
        margin: 0;
        font-weight: 500;
    }

    /* Feature Cards */
    .feature-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
    }

    .feature-card {
        background: var(--card-bg, #fff);
        border-radius: 16px;
        border: 1px solid var(--border-color, #e9ecef);
        transition: all 0.3s ease;
        overflow: hidden;
        text-align: center;
        padding: 2rem 1.5rem;
    }

    .feature-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
    }

    .feature-card-icon {
        width: 64px;
        height: 64px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        margin-bottom: 1rem;
    }

    .feature-card-icon.icon-primary { background: var(--color-primary-light); color: var(--color-primary); }
    .feature-card-icon.icon-info { background: rgba(14, 165, 233, 0.1); color: var(--info-color, #0ea5e9); }
    .feature-card-icon.icon-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning-color, #f59e0b); }

    .feature-card h5 {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-color, #1f2937);
    }

    .feature-card p {
        font-size: 0.875rem;
        color: var(--text-muted, #6b7280);
        margin-bottom: 1.25rem;
        line-height: 1.5;
    }

    .feature-card .i-btn {
        min-width: 140px;
    }

    /* Rank Badge */
    .rank-badge {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
        background: var(--bg-light, #f3f4f6);
        color: var(--text-muted, #6b7280);
        flex-shrink: 0;
    }

    .rank-badge.rank-1 {
        background: linear-gradient(135deg, #ffd700, #ffb347);
        color: #000;
    }
    .rank-badge.rank-2 {
        background: linear-gradient(135deg, #c0c0c0, #a8a8a8);
        color: #000;
    }
    .rank-badge.rank-3 {
        background: linear-gradient(135deg, #cd7f32, #b8860b);
        color: #fff;
    }

    /* Empty State */
    .empty-state {
        padding: 3rem 1.5rem;
        text-align: center;
    }

    .empty-state-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: var(--bg-light, #f3f4f6);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 2rem;
        color: var(--text-muted, #6b7280);
    }

    /* Dark Mode */
    [data-theme="dark"] .intelligence-stat-card,
    [data-theme="dark"] .feature-card {
        background: var(--card-bg-dark, #1f2937);
        border-color: var(--border-color-dark, #374151);
    }

    [data-theme="dark"] .intelligence-stat-content h3,
    [data-theme="dark"] .feature-card h5 {
        color: var(--text-color-dark, #f9fafb);
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
                            <li class="breadcrumb-item active">{{ translate('Campaign Intelligence') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                <a href="{{ route('user.campaign.intelligence.insights.send-time') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-time-line"></i> {{ translate('Send Time Analysis') }}
                </a>
                <a href="{{ route('user.campaign.intelligence.insights.compare') }}" class="i-btn btn--primary btn--md">
                    <i class="ri-git-merge-line"></i> {{ translate('Compare Campaigns') }}
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="intelligence-stats-grid mb-4">
            <div class="intelligence-stat-card stat-primary">
                <div class="intelligence-stat-icon icon-primary">
                    <i class="ri-megaphone-line"></i>
                </div>
                <div class="intelligence-stat-content">
                    <h3>{{ number_format($stats['total_campaigns']) }}</h3>
                    <p>{{ translate('Total Campaigns') }}</p>
                </div>
            </div>
            <div class="intelligence-stat-card stat-success">
                <div class="intelligence-stat-icon icon-success">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
                <div class="intelligence-stat-content">
                    <h3>{{ number_format($stats['completed_campaigns']) }}</h3>
                    <p>{{ translate('Completed') }}</p>
                </div>
            </div>
            <div class="intelligence-stat-card stat-info">
                <div class="intelligence-stat-icon icon-info">
                    <i class="ri-send-plane-line"></i>
                </div>
                <div class="intelligence-stat-content">
                    <h3>{{ number_format($stats['avg_delivery_rate'], 1) }}%</h3>
                    <p>{{ translate('Avg. Delivery') }}</p>
                </div>
            </div>
            <div class="intelligence-stat-card stat-warning">
                <div class="intelligence-stat-icon icon-warning">
                    <i class="ri-mail-open-line"></i>
                </div>
                <div class="intelligence-stat-content">
                    <h3>{{ number_format($stats['avg_open_rate'], 1) }}%</h3>
                    <p>{{ translate('Avg. Open Rate') }}</p>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Recent Campaigns -->
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">{{ translate('Recent Campaign Performance') }}</h4>
                    </div>
                    <div class="card-body px-0 pt-0">
                        @if($campaigns->count() > 0)
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>{{ translate('Campaign') }}</th>
                                        <th>{{ translate('Channels') }}</th>
                                        <th>{{ translate('Delivery') }}</th>
                                        <th>{{ translate('Opens') }}</th>
                                        <th>{{ translate('Trend') }}</th>
                                        <th class="text-end">{{ translate('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($campaigns as $campaign)
                                        <tr>
                                            <td>
                                                <strong>{{ \Illuminate\Support\Str::limit($campaign->name, 25) }}</strong>
                                                <br><small class="text-muted">{{ $campaign->created_at->format('M d, Y') }}</small>
                                            </td>
                                            <td>
                                                @php $channels = is_array($campaign->channels) ? $campaign->channels : json_decode($campaign->channels, true) ?? []; @endphp
                                                <div class="d-flex gap-1 flex-wrap">
                                                    @foreach($channels as $channel)
                                                        <span class="i-badge capsuled info">{{ ucfirst($channel) }}</span>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="job-progress-bar" style="width: 60px; height: 6px; background: var(--bg-light); border-radius: 3px; overflow: hidden;">
                                                        <div style="width: {{ $campaign->getDeliveryRate() }}%; height: 100%; background: var(--success-color, #10b981); border-radius: 3px;"></div>
                                                    </div>
                                                    <small>{{ number_format($campaign->getDeliveryRate(), 1) }}%</small>
                                                </div>
                                            </td>
                                            <td>
                                                <small>{{ number_format($campaign->getOpenRate(), 1) }}%</small>
                                            </td>
                                            <td>
                                                @if($campaign->insight)
                                                    @if($campaign->insight->trend_direction == 'improving')
                                                        <span class="i-badge capsuled success"><i class="ri-arrow-up-line"></i> {{ translate('Up') }}</span>
                                                    @elseif($campaign->insight->trend_direction == 'declining')
                                                        <span class="i-badge capsuled danger"><i class="ri-arrow-down-line"></i> {{ translate('Down') }}</span>
                                                    @else
                                                        <span class="i-badge capsuled secondary"><i class="ri-subtract-line"></i> {{ translate('Stable') }}</span>
                                                    @endif
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('user.campaign.intelligence.insights.show', $campaign->id) }}"
                                                   class="icon-btn btn-ghost btn-sm" title="{{ translate('View Insights') }}">
                                                    <i class="ri-pie-chart-line"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="ri-bar-chart-box-line"></i>
                            </div>
                            <h5>{{ translate('No Campaigns Found') }}</h5>
                            <p class="text-muted">{{ translate('Create your first campaign to see performance insights') }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Top Performing -->
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h4 class="card-title mb-0">{{ translate('Top Performing Campaigns') }}</h4>
                    </div>
                    <div class="card-body">
                        @forelse($topCampaigns as $index => $campaign)
                            <div class="d-flex align-items-center gap-3 {{ !$loop->last ? 'mb-3 pb-3 border-bottom' : '' }}">
                                <div class="rank-badge rank-{{ $index + 1 }}">{{ $index + 1 }}</div>
                                <div class="flex-grow-1">
                                    <strong>{{ \Illuminate\Support\Str::limit($campaign->name, 20) }}</strong>
                                    <br><small class="text-muted">{{ number_format($campaign->total_contacts) }} {{ translate('contacts') }}</small>
                                </div>
                                <div class="text-end">
                                    <strong class="text-success">{{ number_format($campaign->getDeliveryRate(), 1) }}%</strong>
                                    <br><small class="text-muted">{{ translate('delivery') }}</small>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-muted">
                                {{ translate('No completed campaigns') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Features -->
        @php
            $user = auth()->user();
            $planAccess = planAccess($user);
            $hasABTesting = $planAccess['ai_intelligence']['ab_testing'] ?? false;
            $hasSendTimeOptimizer = $planAccess['ai_intelligence']['send_time_optimizer'] ?? false;
        @endphp
        <div class="feature-cards-grid">
            @if($hasABTesting)
            <div class="feature-card">
                <div class="feature-card-icon icon-primary">
                    <i class="ri-flask-line"></i>
                </div>
                <h5>{{ translate('A/B Testing') }}</h5>
                <p>{{ translate('Test different message versions to find what works best for your audience') }}</p>
                <a href="{{ route('user.campaign.intelligence.ab-test.index') }}" class="i-btn btn--primary btn--md">
                    {{ translate('View Tests') }} <i class="ri-arrow-right-line ms-1"></i>
                </a>
            </div>
            @endif

            @if($hasSendTimeOptimizer)
            <div class="feature-card">
                <div class="feature-card-icon icon-info">
                    <i class="ri-time-line"></i>
                </div>
                <h5>{{ translate('Send Time Optimization') }}</h5>
                <p>{{ translate('Discover the best times to reach your contacts based on engagement data') }}</p>
                <a href="{{ route('user.campaign.intelligence.insights.send-time') }}" class="i-btn btn--info btn--md">
                    {{ translate('Analyze') }} <i class="ri-arrow-right-line ms-1"></i>
                </a>
            </div>
            @endif

            <div class="feature-card">
                <div class="feature-card-icon icon-warning">
                    <i class="ri-git-merge-line"></i>
                </div>
                <h5>{{ translate('Compare Campaigns') }}</h5>
                <p>{{ translate('Compare multiple campaigns side by side to analyze performance') }}</p>
                <a href="{{ route('user.campaign.intelligence.insights.compare') }}" class="i-btn btn--warning btn--md">
                    {{ translate('Compare') }} <i class="ri-arrow-right-line ms-1"></i>
                </a>
            </div>
        </div>

    </div>
</main>
@endsection

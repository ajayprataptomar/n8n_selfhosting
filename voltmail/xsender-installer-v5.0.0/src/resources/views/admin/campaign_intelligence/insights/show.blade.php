@extends('admin.layouts.app')

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

    /* Metric Cards */
    .metric-card {
        background: var(--card-bg, #fff);
        border: 1px solid var(--border-color, #e9ecef);
        border-radius: 12px;
        padding: 1.25rem;
        transition: all 0.3s ease;
    }
    .metric-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    }
    .metric-value {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .metric-label {
        font-size: 0.875rem;
        color: var(--text-muted, #6b7280);
        margin-top: 0.25rem;
    }

    /* Heatmap */
    .heatmap-container {
        overflow-x: auto;
        padding-bottom: 0.5rem;
    }
    .heatmap-header, .heatmap-row {
        display: flex;
        gap: 3px;
        margin-bottom: 3px;
    }
    .heatmap-label {
        width: 48px;
        font-size: 0.75rem;
        color: var(--text-muted, #6b7280);
        flex-shrink: 0;
        display: flex;
        align-items: center;
    }
    .heatmap-hour {
        width: 22px;
        font-size: 0.65rem;
        text-align: center;
        color: var(--text-muted, #6b7280);
    }
    .heatmap-cell {
        width: 22px;
        height: 22px;
        border-radius: 4px;
        cursor: pointer;
        transition: transform 0.15s ease;
    }
    .heatmap-cell:hover {
        transform: scale(1.3);
        z-index: 1;
    }
    .heatmap-legend {
        width: 120px;
        height: 12px;
        background: linear-gradient(to right, rgba(var(--color-primary-rgb, 99, 102, 241), 0.1), rgba(var(--color-primary-rgb, 99, 102, 241), 1));
        border-radius: 6px;
    }

    /* Recommendation Items */
    .recommendation-item {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        padding: 1rem;
        background: var(--bg-light, #f8f9fa);
        border-radius: 10px;
        margin-bottom: 0.75rem;
    }
    .recommendation-item:last-child {
        margin-bottom: 0;
    }
    .rec-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1.125rem;
    }
    .rec-icon.danger {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }
    .rec-icon.success {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }
    .rec-icon.warning {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
    }
    .rec-icon.info {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }

    /* Summary Stats */
    .summary-stat {
        text-align: center;
        padding: 1.25rem;
        background: var(--bg-light, #f8f9fa);
        border-radius: 12px;
    }
    .summary-value {
        font-size: 1.75rem;
        font-weight: 700;
    }
    .summary-label {
        font-size: 0.875rem;
        color: var(--text-muted, #6b7280);
    }

    /* Chart Container */
    .chart-container {
        position: relative;
        min-height: 220px;
    }

    /* Trend Badge */
    .trend-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.875rem;
    }
    .trend-badge.improving {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }
    .trend-badge.declining {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }
    .trend-badge.stable {
        background: rgba(107, 114, 128, 0.1);
        color: #6b7280;
    }

    /* Dark Mode */
    [data-theme="dark"] .metric-card,
    [data-theme="dark"] .recommendation-item,
    [data-theme="dark"] .summary-stat {
        background: var(--card-bg-dark, #1f2937);
        border-color: var(--border-color-dark, #374151);
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
                            <li class="breadcrumb-item"><a href="{{ route('admin.campaign.index') }}">{{ translate('Campaigns') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.campaign.intelligence.insights.index') }}">{{ translate('Intelligence') }}</a></li>
                            <li class="breadcrumb-item active">{{ translate('Insights') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                <div class="d-flex gap-2">
                    <button type="button" class="i-btn btn--dark outline btn--md" id="refreshInsights">
                        <i class="ri-refresh-line me-1"></i>{{ translate('Refresh') }}
                    </button>
                    <a href="{{ route('admin.campaign.intelligence.insights.export', $campaign->id) }}"
                       class="i-btn btn--primary btn--md">
                        <i class="ri-download-line me-1"></i>{{ translate('Export Report') }}
                    </a>
                </div>
            </div>
        </div>

        <!-- Campaign Info Card -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <h4 class="mb-1">{{ $campaign->name }}</h4>
                        <p class="text-muted mb-0">
                            @php $channels = is_array($campaign->channels) ? $campaign->channels : json_decode($campaign->channels, true) ?? []; @endphp
                            <span class="me-3">
                                <i class="ri-stack-line me-1"></i>
                                @foreach($channels as $channel)
                                    <span class="i-badge capsuled info">{{ ucfirst($channel) }}</span>
                                @endforeach
                            </span>
                            <span>
                                <i class="ri-calendar-line me-1"></i>{{ $campaign->created_at->format('M d, Y') }}
                            </span>
                        </p>
                    </div>
                    <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                        @if($insight->trend_direction == 'improving')
                            <span class="trend-badge improving">
                                <i class="ri-arrow-up-line"></i>{{ translate('Performance Improving') }}
                            </span>
                        @elseif($insight->trend_direction == 'declining')
                            <span class="trend-badge declining">
                                <i class="ri-arrow-down-line"></i>{{ translate('Performance Declining') }}
                            </span>
                        @else
                            <span class="trend-badge stable">
                                <i class="ri-subtract-line"></i>{{ translate('Performance Stable') }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-lg-2 col-md-4 col-6">
                <div class="metric-card text-center">
                    <div class="metric-value" style="color: var(--color-primary)">{{ number_format($insight->delivery_rate ?? 0, 1) }}%</div>
                    <div class="metric-label">{{ translate('Delivery Rate') }}</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="metric-card text-center">
                    <div class="metric-value text-success">{{ number_format($insight->open_rate ?? 0, 1) }}%</div>
                    <div class="metric-label">{{ translate('Open Rate') }}</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="metric-card text-center">
                    <div class="metric-value" style="color: #3b82f6">{{ number_format($insight->click_rate ?? 0, 1) }}%</div>
                    <div class="metric-label">{{ translate('Click Rate') }}</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="metric-card text-center">
                    <div class="metric-value" style="color: #f59e0b">{{ number_format($insight->reply_rate ?? 0, 1) }}%</div>
                    <div class="metric-label">{{ translate('Reply Rate') }}</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="metric-card text-center">
                    <div class="metric-value text-danger">{{ number_format($insight->bounce_rate ?? 0, 1) }}%</div>
                    <div class="metric-label">{{ translate('Bounce Rate') }}</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="metric-card text-center">
                    <div class="metric-value">{{ number_format($realTimeStats['progress'] ?? 0, 0) }}%</div>
                    <div class="metric-label">{{ translate('Completion') }}</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Channel Comparison -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            <i class="ri-pie-chart-line me-2"></i>{{ translate('Channel Performance') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        @php
                            $channelData = $insight->channel_comparison ?? [];
                            $hasChannelData = !empty($channelData);
                        @endphp
                        @if($hasChannelData)
                            <div class="chart-container">
                                <canvas id="channelChart"></canvas>
                            </div>
                        @else
                            <div class="text-center py-5 text-muted">
                                <i class="ri-pie-chart-line fs-1 mb-2"></i>
                                <p class="mb-0">{{ translate('No channel data available') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Hourly Performance -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            <i class="ri-line-chart-line me-2"></i>{{ translate('Hourly Performance') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        @php
                            $hourlyData = $insight->hourly_stats ?? [];
                            $hasHourlyData = !empty($hourlyData);
                        @endphp
                        @if($hasHourlyData)
                            <div class="chart-container">
                                <canvas id="hourlyChart"></canvas>
                            </div>
                        @else
                            <div class="text-center py-5 text-muted">
                                <i class="ri-line-chart-line fs-1 mb-2"></i>
                                <p class="mb-0">{{ translate('No hourly data available') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Engagement Heatmap -->
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            <i class="ri-fire-line me-2"></i>{{ translate('Engagement Heatmap') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        @php
                            $days = [translate('Sun'), translate('Mon'), translate('Tue'), translate('Wed'), translate('Thu'), translate('Fri'), translate('Sat')];
                            $heatmap = $insight->engagement_heatmap ?? [];
                            $hasHeatmapData = !empty($heatmap);
                        @endphp
                        @if($hasHeatmapData)
                            <div class="heatmap-container">
                                <div class="heatmap-header">
                                    <div class="heatmap-label"></div>
                                    @for($h = 0; $h < 24; $h++)
                                        <div class="heatmap-hour">{{ $h }}</div>
                                    @endfor
                                </div>
                                @foreach($days as $dayIndex => $dayName)
                                    <div class="heatmap-row">
                                        <div class="heatmap-label">{{ $dayName }}</div>
                                        @for($h = 0; $h < 24; $h++)
                                            @php
                                                $score = $heatmap[$dayIndex][$h]['engagement_score'] ?? 0;
                                                $intensity = min(100, $score);
                                            @endphp
                                            <div class="heatmap-cell"
                                                 style="background: rgba(var(--color-primary-rgb, 99, 102, 241), {{ $intensity / 100 }});"
                                                 title="{{ $dayName }} {{ $h }}:00 - {{ number_format($score, 1) }}%">
                                            </div>
                                        @endfor
                                    </div>
                                @endforeach
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <span class="small text-muted">{{ translate('Low Engagement') }}</span>
                                <div class="heatmap-legend"></div>
                                <span class="small text-muted">{{ translate('High Engagement') }}</span>
                            </div>
                        @else
                            <div class="text-center py-5 text-muted">
                                <i class="ri-fire-line fs-1 mb-2"></i>
                                <p class="mb-0">{{ translate('No engagement data available') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- AI Recommendations -->
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            <i class="ri-lightbulb-line me-2"></i>{{ translate('AI Recommendations') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        @php $recommendations = $insight->ai_recommendations ?? []; @endphp
                        @forelse($recommendations as $rec)
                            <div class="recommendation-item">
                                @php
                                    $recType = $rec['type'] ?? 'info';
                                @endphp
                                <div class="rec-icon {{ $recType }}">
                                    @if($recType == 'warning' || $recType == 'danger')
                                        <i class="ri-error-warning-line"></i>
                                    @elseif($recType == 'success')
                                        <i class="ri-check-line"></i>
                                    @elseif($recType == 'suggestion')
                                        <i class="ri-lightbulb-line"></i>
                                    @else
                                        <i class="ri-information-line"></i>
                                    @endif
                                </div>
                                <div>
                                    <h6 class="mb-1">{{ $rec['title'] ?? '' }}</h6>
                                    <p class="text-muted small mb-0">{{ $rec['message'] ?? '' }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-muted">
                                <i class="ri-robot-line fs-1 mb-2"></i>
                                <p class="mb-0">{{ translate('No recommendations at this time') }}</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Summary -->
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">
                    <i class="ri-file-list-3-line me-2"></i>{{ translate('Performance Summary') }}
                </h4>
            </div>
            <div class="card-body">
                @php $summary = $insight->performance_summary ?? []; @endphp
                <div class="row g-4">
                    <div class="col-md-3 col-6">
                        <div class="summary-stat">
                            <div class="summary-value">{{ number_format($summary['total_contacts'] ?? 0) }}</div>
                            <div class="summary-label">{{ translate('Total Contacts') }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="summary-stat">
                            <div class="summary-value" style="color: var(--color-primary)">{{ number_format($summary['processed'] ?? 0) }}</div>
                            <div class="summary-label">{{ translate('Processed') }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="summary-stat">
                            <div class="summary-value text-success">{{ number_format($summary['delivered'] ?? 0) }}</div>
                            <div class="summary-label">{{ translate('Delivered') }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="summary-stat">
                            <div class="summary-value text-danger">{{ number_format($summary['failed'] ?? 0) }}</div>
                            <div class="summary-label">{{ translate('Failed') }}</div>
                        </div>
                    </div>
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

    // Channel Performance Chart
    @php
        $channelData = $insight->channel_comparison ?? [];
    @endphp
    @if(!empty($channelData))
    const channelData = @json($channelData);
    const channelLabels = Object.keys(channelData).map(c => c.charAt(0).toUpperCase() + c.slice(1));
    const deliveryRates = Object.values(channelData).map(d => d.delivery_rate || 0);
    const openRates = Object.values(channelData).map(d => d.open_rate || 0);

    if (channelLabels.length > 0) {
        const channelCtx = document.getElementById('channelChart');
        if (channelCtx) {
            new Chart(channelCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: channelLabels,
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
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: { callback: v => v + '%' },
                            grid: { color: 'rgba(0, 0, 0, 0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    }
    @endif

    // Hourly Performance Chart
    @php
        $hourlyData = $insight->hourly_stats ?? [];
    @endphp
    @if(!empty($hourlyData))
    const hourlyData = @json($hourlyData);
    const hours = Object.keys(hourlyData);
    const sentData = Object.values(hourlyData).map(h => h.sent || 0);
    const openedData = Object.values(hourlyData).map(h => h.opened || 0);

    const hourlyCtx = document.getElementById('hourlyChart');
    if (hourlyCtx) {
        new Chart(hourlyCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: hours.map(h => h + ':00'),
                datasets: [
                    {
                        label: '{{ translate("Sent") }}',
                        data: sentData,
                        borderColor: 'rgb(var(--color-primary-rgb, 99, 102, 241))',
                        backgroundColor: 'rgba(var(--color-primary-rgb, 99, 102, 241), 0.1)',
                        fill: true,
                        tension: 0.4,
                    },
                    {
                        label: '{{ translate("Opened") }}',
                        data: openedData,
                        borderColor: 'rgb(16, 185, 129)',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.4,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0, 0, 0, 0.05)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }
    @endif

    // Refresh Insights
    $('#refreshInsights').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin me-1"></i>{{ translate("Refreshing...") }}');

        $.post('{{ route("admin.campaign.intelligence.insights.refresh", $campaign->id) }}', {
            _token: '{{ csrf_token() }}'
        })
        .done(function() {
            location.reload();
        })
        .fail(function() {
            btn.prop('disabled', false).html('<i class="ri-refresh-line me-1"></i>{{ translate("Refresh") }}');
            notify('error', '{{ translate("Failed to refresh insights") }}');
        });
    });

})(jQuery);
</script>
@endpush

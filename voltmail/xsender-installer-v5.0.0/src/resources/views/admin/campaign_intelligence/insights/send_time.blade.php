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

    /* Channel Stats Cards */
    .channel-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1rem;
    }

    .channel-stat-card {
        background: var(--card-bg, #fff);
        border-radius: 12px;
        padding: 1.25rem;
        border: 1px solid var(--border-color, #e9ecef);
        transition: all 0.3s ease;
    }

    .channel-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    }

    .channel-stat-card.active {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 1px var(--color-primary);
    }

    .channel-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .channel-icon.channel-email {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }
    .channel-icon.channel-sms {
        background: var(--color-primary-light);
        color: var(--color-primary);
    }
    .channel-icon.channel-whatsapp {
        background: rgba(37, 211, 102, 0.1);
        color: #25d366;
    }

    /* Recommendation Cards */
    .recommendation-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .recommendation-card {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 20px;
        background: var(--bg-light, #f8f9fa);
        border-radius: 12px;
        border: 1px solid var(--border-color, #e9ecef);
    }

    .rec-rank {
        width: 48px;
        height: 48px;
        background: var(--color-primary);
        color: #fff;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: 700;
        flex-shrink: 0;
    }

    .rec-rank.rank-1 {
        background: linear-gradient(135deg, #ffd700, #ffb347);
        color: #000;
    }
    .rec-rank.rank-2 {
        background: linear-gradient(135deg, #c0c0c0, #a8a8a8);
        color: #000;
    }
    .rec-rank.rank-3 {
        background: linear-gradient(135deg, #cd7f32, #b8860b);
        color: #fff;
    }

    .rec-time {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-color, #1f2937);
    }

    .rec-score {
        font-size: 0.875rem;
        color: var(--text-muted, #6b7280);
    }

    /* Day Progress */
    .day-progress-item {
        margin-bottom: 1rem;
    }

    .day-progress-item:last-child {
        margin-bottom: 0;
    }

    .day-progress-bar {
        height: 8px;
        background: var(--bg-light, #e9ecef);
        border-radius: 4px;
        overflow: hidden;
    }

    .day-progress-bar .fill {
        height: 100%;
        background: var(--color-primary);
        border-radius: 4px;
        transition: width 0.3s ease;
    }

    /* Empty Data State */
    .empty-data {
        padding: 3rem 1.5rem;
        text-align: center;
        background: var(--bg-light, #f8f9fa);
        border-radius: 12px;
    }

    .empty-data-icon {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: var(--card-bg, #fff);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.75rem;
        color: var(--text-muted, #6b7280);
    }

    /* Chart Container */
    .chart-container {
        position: relative;
        min-height: 300px;
    }

    /* Dark Mode */
    [data-theme="dark"] .channel-stat-card,
    [data-theme="dark"] .recommendation-card {
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
                            <li class="breadcrumb-item active">{{ translate('Send Time') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                <a href="{{ route('admin.campaign.intelligence.insights.index') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-arrow-left-line"></i> {{ translate('Back to Intelligence') }}
                </a>
            </div>
        </div>

        <!-- Channel Selector -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label">{{ translate('Select Channel') }}</label>
                        <select name="channel" class="form-select" onchange="this.form.submit()">
                            <option value="email" {{ $channel == 'email' ? 'selected' : '' }}>{{ translate('Email') }}</option>
                            <option value="sms" {{ $channel == 'sms' ? 'selected' : '' }}>{{ translate('SMS') }}</option>
                            <option value="whatsapp" {{ $channel == 'whatsapp' ? 'selected' : '' }}>{{ translate('WhatsApp') }}</option>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <!-- Channel Stats -->
        <div class="channel-stats-grid mb-4">
            @foreach($engagementStats as $ch => $stats)
                <div class="channel-stat-card {{ $ch == $channel ? 'active' : '' }}">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="mb-0">{{ ucfirst($ch) }}</h5>
                            <small class="text-muted">{{ translate('Channel Statistics') }}</small>
                        </div>
                        <div class="channel-icon channel-{{ $ch }}">
                            <i class="ri-{{ $ch == 'email' ? 'mail' : ($ch == 'sms' ? 'message-2' : 'whatsapp') }}-line"></i>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <small class="text-muted">{{ translate('Contacts') }}</small>
                            <div class="fw-semibold">{{ number_format($stats->total_contacts ?? 0) }}</div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">{{ translate('Avg Score') }}</small>
                            <div class="fw-semibold">{{ number_format($stats->avg_score ?? 0, 1) }}%</div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">{{ translate('Total Sent') }}</small>
                            <div class="fw-semibold">{{ number_format($stats->total_sent ?? 0) }}</div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">{{ translate('Total Opened') }}</small>
                            <div class="fw-semibold">{{ number_format($stats->total_opened ?? 0) }}</div>
                        </div>
                    </div>
                </div>
            @endforeach

            @if($engagementStats->isEmpty())
                <div class="channel-stat-card">
                    <div class="text-center py-3 text-muted">
                        <i class="ri-bar-chart-box-line fs-2 mb-2"></i>
                        <p class="mb-0">{{ translate('No engagement data available yet') }}</p>
                    </div>
                </div>
            @endif
        </div>

        <div class="row g-4 mb-4">
            <!-- Optimal Hours Chart -->
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            <i class="ri-time-line me-2"></i>{{ translate('Best Hours to Send') }} ({{ ucfirst($channel) }})
                        </h4>
                    </div>
                    <div class="card-body">
                        @php
                            $hasData = array_sum($optimalHours) > 0;
                        @endphp
                        @if($hasData)
                            <div class="chart-container">
                                <canvas id="hourlyChart"></canvas>
                            </div>
                        @else
                            <div class="empty-data">
                                <div class="empty-data-icon">
                                    <i class="ri-bar-chart-2-line"></i>
                                </div>
                                <h5>{{ translate('No Data Available') }}</h5>
                                <p class="text-muted mb-0">{{ translate('Send more campaigns to see optimal hours analysis') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Best Days -->
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            <i class="ri-calendar-line me-2"></i>{{ translate('Best Days to Send') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        @php
                            $dayNames = [
                                translate('Sunday'),
                                translate('Monday'),
                                translate('Tuesday'),
                                translate('Wednesday'),
                                translate('Thursday'),
                                translate('Friday'),
                                translate('Saturday')
                            ];
                            $hasDayData = array_sum($optimalDays) > 0;
                            if ($hasDayData) {
                                arsort($optimalDays);
                            }
                        @endphp
                        @if($hasDayData)
                            @foreach($optimalDays as $day => $score)
                                <div class="day-progress-item">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="fw-semibold">{{ $dayNames[$day] ?? 'Day ' . $day }}</span>
                                        <span class="text-muted">{{ number_format($score, 1) }}%</span>
                                    </div>
                                    <div class="day-progress-bar">
                                        <div class="fill" style="width: {{ min(100, $score * 2) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="ri-calendar-line fs-2 mb-2"></i>
                                <p class="mb-0">{{ translate('No day data available') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Recommendations -->
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">
                    <i class="ri-lightbulb-line me-2"></i>{{ translate('Send Time Recommendations') }}
                </h4>
            </div>
            <div class="card-body">
                @php
                    $sortedHours = $optimalHours;
                    arsort($sortedHours);
                    $topHours = array_slice($sortedHours, 0, 3, true);
                    $hasRecommendations = array_sum($topHours) > 0;
                @endphp

                @if($hasRecommendations)
                    <div class="recommendation-grid mb-4">
                        @foreach($topHours as $hour => $score)
                            <div class="recommendation-card">
                                <div class="rec-rank rank-{{ $loop->iteration }}">{{ $loop->iteration }}</div>
                                <div>
                                    <div class="rec-time">{{ sprintf('%02d:00', $hour) }} - {{ sprintf('%02d:00', ($hour + 1) % 24) }}</div>
                                    <div class="rec-score">{{ number_format($score, 1) }}% {{ translate('engagement') }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="alert alert-primary mb-0">
                        <div class="d-flex gap-3">
                            <div class="flex-shrink-0">
                                <i class="ri-lightbulb-line fs-4"></i>
                            </div>
                            <div>
                                <strong>{{ translate('Tip') }}:</strong>
                                {{ translate('Schedule your campaigns during peak engagement hours for better results. Consider your audience\'s timezone when planning send times.') }}
                            </div>
                        </div>
                    </div>
                @else
                    <div class="empty-data">
                        <div class="empty-data-icon">
                            <i class="ri-lightbulb-line"></i>
                        </div>
                        <h5>{{ translate('No Recommendations Yet') }}</h5>
                        <p class="text-muted mb-3">{{ translate('Send more campaigns to generate personalized send time recommendations') }}</p>
                        <a href="{{ route('admin.campaign.create') }}" class="i-btn btn--primary btn--md">
                            <i class="ri-add-line me-1"></i>{{ translate('Create Campaign') }}
                        </a>
                    </div>
                @endif
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

    @if(array_sum($optimalHours) > 0)
    // Hourly Chart
    const hourlyData = @json($optimalHours);
    const labels = Object.keys(hourlyData).map(h => h + ':00');
    const values = Object.values(hourlyData);

    // Find top 3 values for highlighting
    const sortedValues = [...values].sort((a, b) => b - a);
    const threshold = sortedValues[2] || 0;

    const ctx = document.getElementById('hourlyChart');
    if (ctx) {
        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '{{ translate("Engagement Score") }}',
                    data: values,
                    backgroundColor: values.map(v => v >= threshold && v > 0 ? 'rgba(var(--color-primary-rgb, 99, 102, 241), 0.9)' : 'rgba(var(--color-primary-rgb, 99, 102, 241), 0.3)'),
                    borderRadius: 4,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
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
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    @endif

})(jQuery);
</script>
@endpush

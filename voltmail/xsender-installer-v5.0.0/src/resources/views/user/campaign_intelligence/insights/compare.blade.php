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

    .comparison-card {
        background: var(--card-bg, #fff);
        border: 1px solid var(--border-color, #e9ecef);
        border-radius: 12px;
        padding: 1.5rem;
        height: 100%;
    }

    .comparison-card h5 {
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--border-color, #e9ecef);
    }

    .stat-row {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px dashed var(--border-color, #e9ecef);
    }

    .stat-row:last-child {
        border-bottom: none;
    }

    .stat-label {
        color: var(--text-muted, #6b7280);
    }

    .stat-value {
        font-weight: 600;
    }

    .chart-container {
        position: relative;
        min-height: 300px;
    }

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

    [data-theme="dark"] .comparison-card {
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
                            <li class="breadcrumb-item"><a href="{{ route('user.dashboard') }}">{{ translate('Dashboard') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('user.campaign.intelligence.insights.index') }}">{{ translate('Intelligence') }}</a></li>
                            <li class="breadcrumb-item active">{{ translate('Compare') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                <a href="{{ route('user.campaign.intelligence.insights.index') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-arrow-left-line"></i> {{ translate('Back to Intelligence') }}
                </a>
            </div>
        </div>

        <!-- Campaign Selection -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ translate('Select Campaigns to Compare') }}</h4>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-lg-8">
                        <label class="form-label">{{ translate('Choose up to 4 campaigns') }}</label>
                        <select name="campaigns[]" class="form-select select2" multiple>
                            @foreach($availableCampaigns as $campaign)
                                <option value="{{ $campaign->id }}" {{ in_array($campaign->id, $campaignIds) ? 'selected' : '' }}>
                                    {{ $campaign->name }} ({{ $campaign->created_at->format('M d, Y') }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <button type="submit" class="i-btn btn--primary btn--md w-100">
                            <i class="ri-git-merge-line me-1"></i>{{ translate('Compare') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @if(!empty($comparison))
            <!-- Comparison Chart -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="card-title mb-0">
                        <i class="ri-bar-chart-grouped-line me-2"></i>{{ translate('Performance Comparison') }}
                    </h4>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="comparisonChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Detailed Comparison -->
            <div class="row g-4">
                @foreach($comparison as $campaignId => $data)
                    <div class="col-lg-{{ 12 / min(4, count($comparison)) }}">
                        <div class="comparison-card">
                            <h5 class="text-truncate" title="{{ $data['name'] ?? 'Campaign' }}">
                                {{ \Illuminate\Support\Str::limit($data['name'] ?? 'Campaign', 25) }}
                            </h5>
                            <div class="stat-row">
                                <span class="stat-label">{{ translate('Total Contacts') }}</span>
                                <span class="stat-value">{{ number_format($data['total_contacts'] ?? 0) }}</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">{{ translate('Processed') }}</span>
                                <span class="stat-value">{{ number_format($data['processed'] ?? 0) }}</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">{{ translate('Delivery Rate') }}</span>
                                <span class="stat-value text-success">{{ number_format($data['delivery_rate'] ?? 0, 1) }}%</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">{{ translate('Open Rate') }}</span>
                                <span class="stat-value" style="color: #3b82f6">{{ number_format($data['open_rate'] ?? 0, 1) }}%</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">{{ translate('Click Rate') }}</span>
                                <span class="stat-value" style="color: #f59e0b">{{ number_format($data['click_rate'] ?? 0, 1) }}%</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">{{ translate('Bounce Rate') }}</span>
                                <span class="stat-value text-danger">{{ number_format($data['bounce_rate'] ?? 0, 1) }}%</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">{{ translate('Channels') }}</span>
                                <span class="stat-value">
                                    @if(!empty($data['channels']))
                                        @foreach($data['channels'] as $ch)
                                            <span class="i-badge capsuled info">{{ ucfirst($ch) }}</span>
                                        @endforeach
                                    @else
                                        -
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="card">
                <div class="card-body">
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="ri-git-merge-line"></i>
                        </div>
                        <h5>{{ translate('Select Campaigns to Compare') }}</h5>
                        <p class="text-muted">{{ translate('Choose 2 or more campaigns from the dropdown above to see a side-by-side comparison of their performance.') }}</p>
                    </div>
                </div>
            </div>
        @endif

    </div>
</main>
@endsection

@push("script-push")
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function($) {
    "use strict";

    // Initialize Select2
    $('.select2').select2({
        placeholder: "{{ translate('Select campaigns...') }}",
        allowClear: true,
        maximumSelectionLength: 4
    });

    @if(!empty($comparison))
    // Comparison Chart
    const comparisonData = @json($comparison);
    const labels = Object.values(comparisonData).map(d => d.name ? d.name.substring(0, 20) : 'Campaign');
    const deliveryRates = Object.values(comparisonData).map(d => d.delivery_rate || 0);
    const openRates = Object.values(comparisonData).map(d => d.open_rate || 0);
    const clickRates = Object.values(comparisonData).map(d => d.click_rate || 0);

    const ctx = document.getElementById('comparisonChart');
    if (ctx) {
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
    @endif

})(jQuery);
</script>
@endpush

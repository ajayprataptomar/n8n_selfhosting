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

    .comparison-table th,
    .comparison-table td {
        text-align: center;
        vertical-align: middle;
    }
    .comparison-table th:first-child,
    .comparison-table td:first-child {
        text-align: left;
        font-weight: 600;
    }
    .metric-winner {
        color: var(--success-color, #10b981);
        font-weight: 700;
    }
    .metric-winner i {
        font-size: 0.875rem;
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
    .chart-container {
        position: relative;
        min-height: 300px;
    }
</style>
@endpush

@section('panel')
<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ translate($title) }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Dashboard') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.campaign.index') }}">{{ translate('Campaigns') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.campaign.intelligence.insights.index') }}">{{ translate('Intelligence') }}</a></li>
                            <li class="breadcrumb-item active">{{ translate('Compare') }}</li>
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

        <!-- Campaign Selector -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-lg-9 col-md-8">
                        <label class="form-label">{{ translate('Select Campaigns to Compare') }} ({{ translate('max 4') }})</label>
                        <select name="campaigns[]" class="form-select select2" multiple data-placeholder="{{ translate('Select campaigns...') }}">
                            @foreach($availableCampaigns as $campaign)
                                <option value="{{ $campaign->id }}" {{ in_array($campaign->id, $campaignIds) ? 'selected' : '' }}>
                                    {{ $campaign->name }} ({{ $campaign->created_at->format('M d, Y') }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-4">
                        <button type="submit" class="i-btn btn--primary btn--md w-100">
                            <i class="ri-git-merge-line me-1"></i> {{ translate('Compare') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @if(!empty($comparison))
            <!-- Comparison Table -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="card-title mb-0">
                        <i class="ri-table-line me-2"></i>{{ translate('Campaign Comparison') }}
                    </h4>
                </div>
                <div class="card-body px-0 pt-0">
                    <div class="table-container">
                        <table class="comparison-table">
                            <thead>
                                <tr>
                                    <th>{{ translate('Metric') }}</th>
                                    @foreach($comparison as $id => $data)
                                        <th>{{ \Illuminate\Support\Str::limit($data['name'], 20) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ translate('Status') }}</td>
                                    @foreach($comparison as $data)
                                        <td>
                                            <span class="i-badge capsuled {{ $data['status'] == 'completed' ? 'success' : 'primary' }}">
                                                {{ ucfirst($data['status']) }}
                                            </span>
                                        </td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <td>{{ translate('Total Contacts') }}</td>
                                    @foreach($comparison as $data)
                                        <td>{{ number_format($data['total_contacts']) }}</td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <td>{{ translate('Delivery Rate') }}</td>
                                    @php
                                        $maxDelivery = max(array_column($comparison, 'delivery_rate'));
                                    @endphp
                                    @foreach($comparison as $data)
                                        <td class="{{ $data['delivery_rate'] == $maxDelivery ? 'metric-winner' : '' }}">
                                            {{ number_format($data['delivery_rate'], 1) }}%
                                            @if($data['delivery_rate'] == $maxDelivery)
                                                <i class="ri-trophy-line ms-1"></i>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <td>{{ translate('Open Rate') }}</td>
                                    @php
                                        $maxOpen = max(array_column($comparison, 'open_rate'));
                                    @endphp
                                    @foreach($comparison as $data)
                                        <td class="{{ $data['open_rate'] == $maxOpen ? 'metric-winner' : '' }}">
                                            {{ number_format($data['open_rate'], 1) }}%
                                            @if($data['open_rate'] == $maxOpen)
                                                <i class="ri-trophy-line ms-1"></i>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <td>{{ translate('Channels') }}</td>
                                    @foreach($comparison as $data)
                                        <td>
                                            @php $channels = is_array($data['channels']) ? $data['channels'] : []; @endphp
                                            <div class="d-flex gap-1 justify-content-center flex-wrap">
                                                @foreach($channels as $channel)
                                                    <span class="i-badge capsuled info">{{ ucfirst($channel) }}</span>
                                                @endforeach
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <td>{{ translate('Created') }}</td>
                                    @foreach($comparison as $data)
                                        <td>
                                            {{ \Carbon\Carbon::parse($data['created_at'])->format('M d, Y') }}
                                        </td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Comparison Chart -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">
                        <i class="ri-bar-chart-grouped-line me-2"></i>{{ translate('Performance Chart') }}
                    </h4>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="comparisonChart"></canvas>
                    </div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body">
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="ri-git-merge-line"></i>
                        </div>
                        <h5>{{ translate('Select Campaigns to Compare') }}</h5>
                        <p class="text-muted">{{ translate('Choose 2-4 campaigns from the dropdown above to see a side-by-side comparison') }}</p>
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
        maximumSelectionLength: 4,
        allowClear: true
    });

    @if(!empty($comparison))
    // Comparison Chart
    const comparison = @json($comparison);
    const labels = Object.values(comparison).map(c => {
        const name = c.name;
        return name.length > 15 ? name.substring(0, 15) + '...' : name;
    });

    const ctx = document.getElementById('comparisonChart');
    if (ctx) {
        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: '{{ translate("Delivery Rate") }}',
                        data: Object.values(comparison).map(c => c.delivery_rate),
                        backgroundColor: 'rgba(16, 185, 129, 0.8)',
                        borderRadius: 6,
                    },
                    {
                        label: '{{ translate("Open Rate") }}',
                        data: Object.values(comparison).map(c => c.open_rate),
                        backgroundColor: 'rgba(59, 130, 246, 0.8)',
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

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

    .step-number {
        width: 28px;
        height: 28px;
        background: linear-gradient(40deg, var(--color-primary-light), transparent);
        color: var(--color-primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 14px;
        flex-shrink: 0;
    }
    .help-card {
        background: var(--bg-light, #f8f9fa);
        border: 1px solid var(--border-color, #e9ecef);
        border-radius: 12px;
        padding: 1.5rem;
    }
    .metric-item {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    .metric-item:last-child {
        margin-bottom: 0;
    }
    .metric-item i {
        color: var(--color-primary);
        font-size: 1.25rem;
        flex-shrink: 0;
        margin-top: 2px;
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
                            <li class="breadcrumb-item"><a href="{{ route('admin.campaign.intelligence.ab-test.index') }}">{{ translate('A/B Tests') }}</a></li>
                            <li class="breadcrumb-item active">{{ translate('Create') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            {{ translate('Test Configuration') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.campaign.intelligence.ab-test.store') }}" method="POST">
                            @csrf

                            <div class="row g-4">
                                <!-- Campaign Selection -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold">{{ translate('Select Campaign') }} <span class="text-danger">*</span></label>
                                    <select name="campaign_id" class="form-select select2" required>
                                        <option value="">{{ translate('Choose a campaign...') }}</option>
                                        @foreach($campaigns as $c)
                                            <option value="{{ $c->id }}" {{ ($campaign && $campaign->id == $c->id) ? 'selected' : '' }}>
                                                {{ $c->name }} - {{ ucfirst($c->status->value ?? $c->status) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted fs-12">{{ translate('Only draft or scheduled campaigns can have A/B tests') }}</small>
                                </div>

                                <!-- Test Name -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold">{{ translate('Test Name') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" required
                                           value="{{ old('name', $campaign ? $campaign->name . ' - A/B Test' : '') }}"
                                           placeholder="{{ translate('Enter test name...') }}">
                                </div>

                                <!-- Test Percentage -->
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">{{ translate('Test Sample Size') }} <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="test_percentage" class="form-control" required
                                               min="5" max="50" value="{{ old('test_percentage', 20) }}">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <small class="text-muted fs-12">{{ translate('Percentage of contacts to use for testing (5-50%)') }}</small>
                                </div>

                                <!-- Test Duration -->
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">{{ translate('Test Duration') }} <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="test_duration_hours" class="form-control" required
                                               min="1" max="168" value="{{ old('test_duration_hours', 24) }}">
                                        <span class="input-group-text">{{ translate('hours') }}</span>
                                    </div>
                                    <small class="text-muted fs-12">{{ translate('How long to run the test before selecting winner') }}</small>
                                </div>

                                <!-- Winning Metric -->
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">{{ translate('Winning Metric') }} <span class="text-danger">*</span></label>
                                    <select name="winning_metric" class="form-select" required>
                                        @foreach($winningMetrics as $metric)
                                            <option value="{{ $metric->value }}" {{ old('winning_metric', 'delivered') == $metric->value ? 'selected' : '' }}>
                                                {{ $metric->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted fs-12">{{ translate('Metric used to determine the winning variant') }}</small>
                                </div>

                                <!-- Auto Select Winner -->
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">{{ translate('Winner Selection') }}</label>
                                    <div class="form-check form-switch mt-2">
                                        <input type="checkbox" name="auto_select_winner" id="autoSelectWinner"
                                               class="form-check-input" value="1" {{ old('auto_select_winner', true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="autoSelectWinner">
                                            {{ translate('Automatically select winner after test duration') }}
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <div class="d-flex justify-content-between">
                                <a href="{{ route('admin.campaign.intelligence.ab-test.index') }}" class="i-btn btn--dark outline btn--md">
                                    <i class="ri-arrow-left-line me-1"></i>{{ translate('Cancel') }}
                                </a>
                                <button type="submit" class="i-btn btn--primary btn--md">
                                    {{ translate('Create Test & Add Variants') }}
                                    <i class="ri-arrow-right-line ms-1"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Info Sidebar -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                         <h5 class="mb-3">
                            <i class="ri-lightbulb-line me-2"></i>{{ translate('How A/B Testing Works') }}
                        </h5>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex gap-3 mb-3">
                        <div class="step-number">1</div>
                        <div>
                            <strong class="fs-15">{{ translate('Create Variants') }}</strong>
                            <p class="text-muted small mb-0">{{ translate('Create different versions of your message to test') }}</p>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mb-3">
                        <div class="step-number">2</div>
                        <div>
                            <strong class="fs-15">{{ translate('Split Audience') }}</strong>
                            <p class="text-muted small mb-0">{{ translate('Your test sample is divided equally among variants') }}</p>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mb-3">
                        <div class="step-number">3</div>
                        <div>
                            <strong class="fs-15">{{ translate('Measure Results') }}</strong>
                            <p class="text-muted small mb-0">{{ translate('Track performance using your chosen metric') }}</p>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <div class="step-number">4</div>
                        <div>
                            <strong class="fs-15">{{ translate('Select Winner') }}</strong>
                            <p class="text-muted small mb-0">{{ translate('Winner is selected and used for remaining contacts') }}</p>
                        </div>
                    </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-bar-chart-line me-2"></i>{{ translate('Winning Metrics Explained') }}
                        </h5>
                    </div>
                    <div class="card-body pt-0">
                        @foreach($winningMetrics as $metric)
                            <div class="metric-item">
                                <i class="{{ $metric->icon() }}"></i>
                                <div>
                                    <strong class="fs-15">{{ $metric->label() }}</strong>
                                    <p class="text-muted small mb-0">{{ $metric->description() }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>
@endsection

@push("script-push")
<script>
(function($) {
    "use strict";

    $('.select2').select2({
        placeholder: "{{ translate('Choose a campaign...') }}",
        allowClear: true
    });

})(jQuery);
</script>
@endpush

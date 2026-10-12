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

    .icon-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    .icon-circle.icon-primary {
        background: var(--color-primary-light);
        color: var(--color-primary);
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
    .progress-bar-wrapper {
        width: 80px;
        height: 6px;
        background: var(--bg-light, #e9ecef);
        border-radius: 3px;
        overflow: hidden;
    }
    .progress-bar-wrapper .fill {
        height: 100%;
        background: var(--color-primary);
        border-radius: 3px;
    }
    .quota-card {
        background: var(--bg-light, #f8f9fa);
        border-radius: 12px;
        padding: 1rem;
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
                            <li class="breadcrumb-item active">{{ translate('A/B Tests') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                @if($canCreate)
                    <a href="{{ route('user.campaign.intelligence.ab-test.create') }}" class="i-btn btn--primary btn--md">
                        <i class="ri-add-line"></i> {{ translate('Create A/B Test') }}
                    </a>
                @else
                    <button class="i-btn btn--primary btn--md" disabled title="{{ translate('Monthly limit reached') }}">
                        <i class="ri-add-line"></i> {{ translate('Create A/B Test') }}
                    </button>
                @endif
            </div>
        </div>

        <!-- Monthly Quota -->
        @if($monthlyLimit > 0)
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center gap-3">
                            <div class="icon-circle icon-primary">
                                <i class="ri-flask-line"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">{{ translate('Monthly A/B Test Quota') }}</h5>
                                <p class="text-muted mb-0">
                                    {{ translate('You have used') }} <strong>{{ $usedThisMonth }}</strong> {{ translate('of') }} <strong>{{ $monthlyLimit }}</strong> {{ translate('tests this month') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <div class="quota-card d-inline-block">
                            <span class="fw-bold fs-4" style="color: var(--color-primary)">{{ $monthlyLimit - $usedThisMonth }}</span>
                            <span class="text-muted">{{ translate('remaining') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="{{ route('user.campaign.intelligence.ab-test.index') }}" method="GET">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label">{{ translate('Search') }}</label>
                            <input type="text" name="search" value="{{ request('search') }}"
                                   class="form-control" placeholder="{{ translate('Test name or campaign...') }}">
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">{{ translate('Status') }}</label>
                            <select name="status" class="form-select">
                                <option value="">{{ translate('All Status') }}</option>
                                @foreach(\App\Enums\Campaign\AbTestStatus::cases() as $status)
                                    <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>
                                        {{ $status->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <button type="submit" class="i-btn btn--primary btn--md w-100">
                                <i class="ri-filter-3-line"></i> {{ translate('Filter') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tests List -->
        <div class="card">
            <div class="card-body px-0 pt-0">
                @if($tests->count() > 0)
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>{{ translate('Test Name') }}</th>
                                <th>{{ translate('Campaign') }}</th>
                                <th>{{ translate('Variants') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Winning Metric') }}</th>
                                <th>{{ translate('Progress') }}</th>
                                <th class="text-end">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tests as $test)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="icon-circle icon-primary">
                                                <i class="ri-flask-line"></i>
                                            </div>
                                            <div>
                                                <strong>{{ $test->name }}</strong>
                                                <br><small class="text-muted">{{ $test->created_at->format('M d, Y') }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($test->campaign)
                                            {{ \Illuminate\Support\Str::limit($test->campaign->name, 30) }}
                                        @else
                                            <span class="text-muted">{{ translate('N/A') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            @foreach($test->variants as $variant)
                                                <span class="i-badge capsuled {{ $variant->is_winner ? 'success' : 'secondary' }}">
                                                    {{ $variant->variant_label }}
                                                    @if($variant->is_winner)
                                                        <i class="ri-trophy-line ms-1"></i>
                                                    @endif
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        @php
                                            $statusEnum = \App\Enums\Campaign\AbTestStatus::tryFrom($test->status);
                                        @endphp
                                        <span class="i-badge capsuled {{ $statusEnum ? str_replace('badge--', '', $statusEnum->badgeClass()) : 'secondary' }}">
                                            <i class="{{ $statusEnum ? $statusEnum->icon() : 'ri-question-line' }} me-1"></i>
                                            {{ $statusEnum ? $statusEnum->label() : $test->status }}
                                        </span>
                                    </td>
                                    <td>
                                        @php
                                            $metricEnum = \App\Enums\Campaign\AbTestWinningMetric::tryFrom($test->winning_metric);
                                        @endphp
                                        <span class="text-muted">
                                            <i class="{{ $metricEnum ? $metricEnum->icon() : 'ri-bar-chart-line' }} me-1"></i>
                                            {{ $metricEnum ? $metricEnum->label() : ucfirst($test->winning_metric) }}
                                        </span>
                                    </td>
                                    <td>
                                        @php
                                            $totalSent = $test->variants->sum('sent_count');
                                            $totalContacts = $test->variants->sum('contact_count');
                                            $progress = $totalContacts > 0 ? round(($totalSent / $totalContacts) * 100) : 0;
                                        @endphp
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress-bar-wrapper">
                                                <div class="fill" style="width: {{ $progress }}%"></div>
                                            </div>
                                            <small class="text-muted">{{ $progress }}%</small>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <a href="{{ route('user.campaign.intelligence.ab-test.show', $test->id) }}"
                                               class="icon-btn btn-ghost btn-sm" title="{{ translate('View') }}">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            @if($test->status == 'draft')
                                                <a href="{{ route('user.campaign.intelligence.ab-test.edit', $test->id) }}"
                                                   class="icon-btn btn-ghost btn-sm" title="{{ translate('Edit') }}">
                                                    <i class="ri-pencil-line"></i>
                                                </a>
                                            @endif
                                            @if($test->status == 'draft' && $test->variants->count() >= 2)
                                                <form action="{{ route('user.campaign.intelligence.ab-test.start', $test->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="icon-btn btn-ghost btn-sm text-success" title="{{ translate('Start') }}">
                                                        <i class="ri-play-line"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            @if($test->status == 'running')
                                                <form action="{{ route('user.campaign.intelligence.ab-test.pause', $test->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="icon-btn btn-ghost btn-sm text-warning" title="{{ translate('Pause') }}">
                                                        <i class="ri-pause-line"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            @if($test->status != 'running')
                                                <form action="{{ route('user.campaign.intelligence.ab-test.destroy', $test->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="icon-btn btn-ghost btn-sm text-danger" title="{{ translate('Delete') }}" onclick="return confirm('{{ translate('Are you sure?') }}')">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @include('user.partials.pagination', ['paginator' => $tests])
                @else
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="ri-flask-line"></i>
                    </div>
                    <h5>{{ translate('No A/B Tests Found') }}</h5>
                    <p class="text-muted mb-3">{{ translate('Create your first A/B test to optimize your campaigns') }}</p>
                    @if($canCreate)
                        <a href="{{ route('user.campaign.intelligence.ab-test.create') }}" class="i-btn btn--primary btn--md">
                            <i class="ri-add-line me-1"></i>{{ translate('Create A/B Test') }}
                        </a>
                    @endif
                </div>
                @endif
            </div>
        </div>

    </div>
</main>
@endsection

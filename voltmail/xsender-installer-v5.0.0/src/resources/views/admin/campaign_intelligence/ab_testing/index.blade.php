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
                            <li class="breadcrumb-item active">{{ translate('A/B Tests') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                <a href="{{ route('admin.campaign.intelligence.ab-test.create') }}" class="i-btn btn--primary btn--md">
                    <i class="ri-add-line"></i> {{ translate('Create A/B Test') }}
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="{{ route('admin.campaign.intelligence.ab-test.index') }}" method="GET">
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
                                            <a href="{{ route('admin.campaign.show', $test->campaign->id) }}" class="text-primary">
                                                {{ \Illuminate\Support\Str::limit($test->campaign->name, 30) }}
                                            </a>
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
                                            <a href="{{ route('admin.campaign.intelligence.ab-test.show', $test->id) }}"
                                               class="icon-btn btn-ghost btn-sm" title="{{ translate('View') }}">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            @if($test->status == 'draft')
                                                <a href="{{ route('admin.campaign.intelligence.ab-test.edit', $test->id) }}"
                                                   class="icon-btn btn-ghost btn-sm" title="{{ translate('Edit') }}">
                                                    <i class="ri-pencil-line"></i>
                                                </a>
                                            @endif
                                            @if($test->status == 'draft' && $test->variants->count() >= 2)
                                                <form action="{{ route('admin.campaign.intelligence.ab-test.start', $test->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="icon-btn btn-ghost btn-sm text-success" title="{{ translate('Start') }}">
                                                        <i class="ri-play-line"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            @if($test->status == 'running')
                                                <form action="{{ route('admin.campaign.intelligence.ab-test.pause', $test->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="icon-btn btn-ghost btn-sm text-warning" title="{{ translate('Pause') }}">
                                                        <i class="ri-pause-line"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            @if($test->status != 'running')
                                                <button type="button" class="icon-btn btn-ghost btn-sm text-danger delete-test-btn"
                                                        data-id="{{ $test->id }}"
                                                        data-name="{{ $test->name }}"
                                                        title="{{ translate('Delete') }}">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @include('admin.partials.pagination', ['paginator' => $tests])
                @else
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="ri-flask-line"></i>
                    </div>
                    <h5>{{ translate('No A/B Tests Found') }}</h5>
                    <p class="text-muted mb-3">{{ translate('Create your first A/B test to optimize your campaigns') }}</p>
                    <a href="{{ route('admin.campaign.intelligence.ab-test.create') }}" class="i-btn btn--primary btn--md">
                        <i class="ri-add-line me-1"></i>{{ translate('Create A/B Test') }}
                    </a>
                </div>
                @endif
            </div>
        </div>

    </div>
</main>
@endsection

@section('modal')
{{-- Delete Confirmation Modal --}}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="delete-icon-wrapper mb-3">
                    <div class="delete-icon">
                        <i class="ri-delete-bin-line"></i>
                    </div>
                </div>
                <h5 class="mb-2">{{ translate('Delete A/B Test?') }}</h5>
                <p class="text-muted mb-1" id="deleteTestName"></p>
                <p class="text-muted mb-4">{{ translate('This action cannot be undone.') }}</p>
                <form id="deleteForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">
                            {{ translate('Cancel') }}
                        </button>
                        <button type="submit" class="i-btn btn--danger btn--md" id="confirmDeleteBtn">
                            <i class="ri-delete-bin-line me-1"></i> {{ translate('Delete') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script-push')
<script>
(function($) {
    "use strict";

    var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

    // Delete test - show modal
    $(document).on('click', '.delete-test-btn', function() {
        var testId = $(this).data('id');
        var testName = $(this).data('name');

        $('#deleteTestName').text(testName);
        $('#deleteForm').attr('action', '{{ route("admin.campaign.intelligence.ab-test.destroy", "") }}/' + testId);
        deleteModal.show();
    });

    // Handle form submission with loading state
    $('#deleteForm').on('submit', function() {
        var btn = $('#confirmDeleteBtn');
        btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin me-1"></i> {{ translate("Deleting...") }}');
    });

    // Reset on modal hide
    $('#deleteModal').on('hidden.bs.modal', function() {
        $('#confirmDeleteBtn').prop('disabled', false).html('<i class="ri-delete-bin-line me-1"></i> {{ translate("Delete") }}');
    });

})(jQuery);
</script>
@endpush

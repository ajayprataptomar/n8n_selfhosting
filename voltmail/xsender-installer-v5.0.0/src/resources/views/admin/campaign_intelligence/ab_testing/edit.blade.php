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

    .variant-label {
        width: 44px;
        height: 44px;
        background: var(--color-primary);
        color: #fff;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    .variant-label.variant-winner {
        background: var(--success-color, #10b981);
    }
    .variant-stats {
        background: var(--bg-light, #f8f9fa);
        border-radius: 8px;
        padding: 1rem;
        margin-top: 1rem;
    }
    .variant-stats .stat-item {
        text-align: center;
    }
    .variant-stats .stat-label {
        font-size: 0.75rem;
        color: var(--text-muted);
        display: block;
    }
    .variant-stats .stat-value {
        font-weight: 600;
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
                            <li class="breadcrumb-item active">{{ translate('Edit') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                @if($test->variants->count() >= 2)
                    <form action="{{ route('admin.campaign.intelligence.ab-test.start', $test->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="i-btn btn--success btn--md">
                            <i class="ri-play-line me-1"></i>{{ translate('Start Test') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="row g-4">
            <!-- Test Configuration -->
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            <i class="ri-settings-3-line me-2"></i>{{ translate('Test Configuration') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.campaign.intelligence.ab-test.update', $test->id) }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ translate('Test Name') }}</label>
                                <input type="text" name="name" class="form-control" required value="{{ old('name', $test->name) }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ translate('Campaign') }}</label>
                                <input type="text" class="form-control" readonly value="{{ $test->campaign->name ?? 'N/A' }}">
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-semibold">{{ translate('Test Sample') }}</label>
                                    <div class="input-group">
                                        <input type="number" name="test_percentage" class="form-control"
                                               min="5" max="50" value="{{ old('test_percentage', $test->test_percentage) }}">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold">{{ translate('Duration') }}</label>
                                    <div class="input-group">
                                        <input type="number" name="test_duration_hours" class="form-control"
                                               min="1" max="168" value="{{ old('test_duration_hours', $test->test_duration_hours) }}">
                                        <span class="input-group-text">hrs</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ translate('Winning Metric') }}</label>
                                <select name="winning_metric" class="form-select">
                                    @foreach($winningMetrics as $metric)
                                        <option value="{{ $metric->value }}" {{ $test->winning_metric == $metric->value ? 'selected' : '' }}>
                                            {{ $metric->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-check form-switch mb-4">
                                <input type="checkbox" name="auto_select_winner" id="autoSelectWinner"
                                       class="form-check-input" value="1" {{ $test->auto_select_winner ? 'checked' : '' }}>
                                <label class="form-check-label" for="autoSelectWinner">
                                    {{ translate('Auto-select winner') }}
                                </label>
                            </div>

                            <button type="submit" class="i-btn btn--primary btn--md w-100">
                                <i class="ri-save-line me-1"></i>{{ translate('Save Changes') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Variants -->
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">
                            <i class="ri-git-branch-line me-2"></i>{{ translate('Test Variants') }}
                            <span class="i-badge capsuled primary ms-2">{{ $test->variants->count() }}</span>
                        </h4>
                        @if($availableMessages->count() > 0)
                            <button type="button" class="i-btn btn--primary btn--sm" data-bs-toggle="modal" data-bs-target="#addVariantModal">
                                <i class="ri-add-line me-1"></i>{{ translate('Add Variant') }}
                            </button>
                        @endif
                    </div>
                    <div class="card-body">
                        @if($test->variants->count() > 0)
                            @foreach($test->variants as $variant)
                                <div class="d-flex justify-content-between align-items-start p-3 border rounded mb-3">
                                    <div class="d-flex gap-3">
                                        <div class="variant-label {{ $variant->is_winner ? 'variant-winner' : '' }}">
                                            {{ $variant->variant_label }}
                                        </div>
                                        <div>
                                            <h6 class="mb-1">
                                                {{ translate('Variant') }} {{ $variant->variant_label }}
                                                @if($variant->is_winner)
                                                    <span class="i-badge capsuled success ms-1">
                                                        <i class="ri-trophy-line me-1"></i>{{ translate('Winner') }}
                                                    </span>
                                                @endif
                                            </h6>
                                            @if($variant->campaignMessage)
                                                <p class="text-muted small mb-1">
                                                    <i class="ri-{{ $variant->campaignMessage->channel->value == 'email' ? 'mail' : ($variant->campaignMessage->channel->value == 'sms' ? 'message-2' : 'whatsapp') }}-line me-1"></i>
                                                    {{ ucfirst($variant->campaignMessage->channel->value) }}
                                                    @if($variant->campaignMessage->subject)
                                                        - {{ plain_text_preview($variant->campaignMessage->subject, 40) }}
                                                    @endif
                                                </p>
                                                <p class="text-muted small mb-0">
                                                    {{ plain_text_preview($variant->campaignMessage->content, 60) }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                    <button type="button" class="icon-btn btn-ghost btn-sm text-danger remove-variant"
                                            data-variant-id="{{ $variant->id }}" data-test-id="{{ $test->id }}">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </div>

                                @if($variant->contact_count > 0)
                                    <div class="variant-stats">
                                        <div class="row g-3">
                                            <div class="col stat-item">
                                                <span class="stat-label">{{ translate('Contacts') }}</span>
                                                <span class="stat-value">{{ number_format($variant->contact_count) }}</span>
                                            </div>
                                            <div class="col stat-item">
                                                <span class="stat-label">{{ translate('Sent') }}</span>
                                                <span class="stat-value">{{ number_format($variant->sent_count ?? 0) }}</span>
                                            </div>
                                            <div class="col stat-item">
                                                <span class="stat-label">{{ translate('Delivered') }}</span>
                                                <span class="stat-value">{{ number_format($variant->delivered_count) }}</span>
                                            </div>
                                            <div class="col stat-item">
                                                <span class="stat-label">{{ translate('Opened') }}</span>
                                                <span class="stat-value">{{ number_format($variant->opened_count) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @else
                            <div class="text-center py-5">
                                <i class="ri-git-branch-line fs-1 text-muted mb-3"></i>
                                <h5>{{ translate('No Variants Yet') }}</h5>
                                <p class="text-muted">{{ translate('Add at least 2 variants to start the test') }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Help Card -->
                @if($test->variants->count() < 2)
                    <div class="alert alert-info mt-4 mb-0 fs-14">
                        <i class="ri-information-line me-2"></i>
                        {{ translate('You need at least 2 variants to start an A/B test. Add different message versions to compare their performance.') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</main>

<!-- Add Variant Modal -->
<div class="modal fade" id="addVariantModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ translate('Add Test Variant') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addVariantForm">
                    <input type="hidden" name="test_id" value="{{ $test->id }}">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ translate('Variant Label') }}</label>
                        <input type="text" name="variant_label" class="form-control" maxlength="1"
                               placeholder="{{ chr(65 + $test->variants->count()) }}"
                               value="{{ chr(65 + $test->variants->count()) }}">
                        <small class="text-muted">{{ translate('Single letter (A, B, C, etc.)') }}</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ translate('Campaign Message') }}</label>
                        <select name="campaign_message_id" class="form-select" required>
                            <option value="">{{ translate('Select a message...') }}</option>
                            @foreach($availableMessages as $message)
                                <option value="{{ $message->id }}">
                                    {{ ucfirst($message->channel->value) }}
                                    @if($message->subject) - {{ plain_text_preview($message->subject, 30) }}@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                <button type="button" class="i-btn btn--primary btn--md" id="saveVariant">
                    <i class="ri-add-line me-1"></i>{{ translate('Add Variant') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push("script-push")
<script>
(function($) {
    "use strict";

    // Add Variant
    $('#saveVariant').on('click', function() {
        const form = $('#addVariantForm');
        const data = {
            campaign_message_id: form.find('[name="campaign_message_id"]').val(),
            variant_label: form.find('[name="variant_label"]').val(),
            _token: '{{ csrf_token() }}'
        };

        if (!data.campaign_message_id) {
            notify('error', '{{ translate("Please select a campaign message") }}');
            return;
        }

        $.post('{{ route("admin.campaign.intelligence.ab-test.add-variant", $test->id) }}', data)
            .done(function(response) {
                if (response.success) {
                    notify('success', response.message);
                    location.reload();
                }
            })
            .fail(function(xhr) {
                notify('error', xhr.responseJSON?.error || '{{ translate("Failed to add variant") }}');
            });
    });

    // Remove Variant
    $('.remove-variant').on('click', function() {
        if (!confirm('{{ translate("Are you sure you want to remove this variant?") }}')) {
            return;
        }

        const variantId = $(this).data('variant-id');
        const testId = $(this).data('test-id');
        const $btn = $(this);

        $.ajax({
            url: '/admin/campaign/intelligence/ab-test/' + testId + '/variant/' + variantId,
            method: 'DELETE',
            data: { _token: '{{ csrf_token() }}' }
        })
        .done(function(response) {
            if (response.success) {
                notify('success', response.message);
                $btn.closest('.d-flex').fadeOut(function() {
                    $(this).remove();
                });
            }
        })
        .fail(function(xhr) {
            notify('error', xhr.responseJSON?.error || '{{ translate("Failed to remove variant") }}');
        });
    });

})(jQuery);
</script>
@endpush

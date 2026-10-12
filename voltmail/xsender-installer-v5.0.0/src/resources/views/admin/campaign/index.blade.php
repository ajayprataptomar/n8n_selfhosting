@extends('admin.layouts.app')

@push('style-push')
@include('admin.partials.lead-stat-styles')
@endpush

@section('panel')
<main class="main-body">
    <div class="container-fluid px-0 main-content">
        {{-- Page Header --}}
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ translate('Campaigns') }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Dashboard') }}</a></li>
                            <li class="breadcrumb-item active">{{ translate('Campaigns') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                <a href="{{ route('admin.campaign.create') }}" class="i-btn btn--primary btn--md">
                    <i class="ri-add-line"></i> {{ translate('Create Campaign') }}
                </a>
            </div>
        </div>

        {{-- Stats Overview --}}
        <div class="lead-stats-grid mb-4">
            <div class="lead-stat-card stat-primary">
                <div class="lead-stat-icon icon-primary">
                    <i class="ri-megaphone-line"></i>
                </div>
                <div class="lead-stat-content">
                    <h3>{{ number_format($statistics['total']) }}</h3>
                    <p>{{ translate('Total Campaigns') }}</p>
                </div>
            </div>
            <div class="lead-stat-card stat-warning">
                <div class="lead-stat-icon icon-warning">
                    <i class="ri-draft-line"></i>
                </div>
                <div class="lead-stat-content">
                    <h3>{{ number_format($statistics['draft']) }}</h3>
                    <p>{{ translate('Draft') }}</p>
                </div>
            </div>
            <div class="lead-stat-card stat-info">
                <div class="lead-stat-icon icon-info">
                    <i class="ri-play-circle-line"></i>
                </div>
                <div class="lead-stat-content">
                    <h3>{{ number_format($statistics['running']) }}</h3>
                    <p>{{ translate('Running') }}</p>
                </div>
            </div>
            <div class="lead-stat-card stat-success">
                <div class="lead-stat-icon icon-success">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
                <div class="lead-stat-content">
                    <h3>{{ number_format($statistics['completed']) }}</h3>
                    <p>{{ translate('Completed') }}</p>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card mb-4">
            <div class="card-body">
                <form action="{{ route('admin.campaign.index') }}" method="GET">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">{{ translate('Search') }}</label>
                            <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="{{ translate('Campaign name...') }}">
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">{{ translate('Status') }}</label>
                            <select name="status" class="form-select">
                                <option value="">{{ translate('All Status') }}</option>
                                @foreach(\App\Enums\Campaign\UnifiedCampaignStatus::cases() as $status)
                                <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>
                                    {{ $status->label() }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">{{ translate('Channel') }}</label>
                            <select name="channel" class="form-select">
                                <option value="">{{ translate('All Channels') }}</option>
                                @foreach(\App\Enums\Campaign\CampaignChannel::cases() as $channel)
                                <option value="{{ $channel->value }}" {{ request('channel') == $channel->value ? 'selected' : '' }}>
                                    {{ $channel->label() }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">{{ translate('From Date') }}</label>
                            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">{{ translate('To Date') }}</label>
                            <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                        </div>
                        <div class="col-lg-1 col-md-6">
                            <button type="submit" class="i-btn btn--primary btn--md w-100">
                                <i class="ri-search-line"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Campaigns Table --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-left">
                    <h4 class="card-title">{{ translate('All Campaigns') }}</h4>
                </div>
                <div class="card-header-right">
                    <span class="text-muted">{{ $campaigns->total() }} {{ translate('campaigns') }}</span>
                </div>
            </div>
            <div class="card-body px-0 pt-0">
                @if($campaigns->count() > 0)
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>{{ translate('Campaign') }}</th>
                                <th>{{ translate('Channels') }}</th>
                                <th>{{ translate('Contacts') }}</th>
                                <th>{{ translate('Progress') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Schedule') }}</th>
                                <th class="text-end">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($campaigns as $campaign)
                            <tr>
                                <td data-label="{{ translate('Campaign') }}">
                                    <a href="{{ route('admin.campaign.show', $campaign->uid) }}" class="fw-semibold text-dark">{{ Str::limit($campaign->name, 30) }}</a>
                                    <br><small class="text-muted">{{ $campaign->contactGroup?->name ?? translate('No Group') }}</small>
                                </td>
                                <td data-label="{{ translate('Channels') }}">
                                    @foreach($campaign->channels ?? [] as $channel)
                                        @php $channelEnum = \App\Enums\Campaign\CampaignChannel::tryFrom($channel); @endphp
                                        @if($channelEnum)
                                            @php
                                                $badgeColor = match($channel) {
                                                    'whatsapp' => 'success',
                                                    'email' => 'info',
                                                    'sms' => 'primary',
                                                    default => 'secondary'
                                                };
                                            @endphp
                                            <span class="i-badge {{ $badgeColor }}-soft pill">{{ $channelEnum->label() }}</span>
                                        @endif
                                    @endforeach
                                </td>
                                <td data-label="{{ translate('Contacts') }}">
                                    <span class="i-badge primary-soft pill"><i class="ri-group-line"></i> {{ number_format($campaign->total_contacts) }}</span>
                                </td>
                                <td data-label="{{ translate('Progress') }}">
                                    @php $progress = $campaign->getProgressPercentage(); @endphp
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px; min-width: 80px;">
                                            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $progress }}%"></div>
                                        </div>
                                        <small class="text-muted">{{ $progress }}%</small>
                                    </div>
                                </td>
                                <td data-label="{{ translate('Status') }}">
                                    @php
                                        $statusColor = match($campaign->status->value) {
                                            'draft' => 'secondary',
                                            'scheduled' => 'warning',
                                            'running' => 'info',
                                            'paused' => 'danger',
                                            'completed' => 'success',
                                            'cancelled' => 'secondary',
                                            default => 'secondary'
                                        };
                                    @endphp
                                    <span class="i-badge {{ $statusColor }}-soft pill">{{ $campaign->status->label() }}</span>
                                </td>
                                <td data-label="{{ translate('Schedule') }}">
                                    @if($campaign->schedule_at)
                                        <small>{{ $campaign->schedule_at->format('M d, Y') }}</small>
                                        <br><small class="text-muted">{{ $campaign->schedule_at->format('h:i A') }}</small>
                                    @else
                                        <small class="text-muted"><i class="ri-flashlight-line"></i> {{ translate('Instant') }}</small>
                                    @endif
                                </td>
                                <td data-label="{{ translate('Actions') }}">
                                    <div class="d-flex align-items-center justify-content-end gap-1">
                                        <a href="{{ route('admin.campaign.show', $campaign->uid) }}" class="icon-btn btn-ghost btn-sm" title="{{ translate('View') }}">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                        @if($campaign->canEdit())
                                        <a href="{{ route('admin.campaign.edit', $campaign->uid) }}" class="icon-btn btn-ghost btn-sm" title="{{ translate('Edit') }}">
                                            <i class="ri-pencil-line"></i>
                                        </a>
                                        @endif
                                        <div class="dropdown">
                                            <button class="icon-btn btn-ghost btn-sm" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport">
                                                <i class="ri-more-2-fill"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                @if($campaign->canStart())
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('admin.campaign.review', $campaign->uid) }}">
                                                        <i class="ri-play-circle-line text-success me-2"></i>{{ translate('Launch Campaign') }}
                                                    </a>
                                                </li>
                                                @endif
                                                @if($campaign->canPause())
                                                <li>
                                                    <form action="{{ route('admin.campaign.pause', $campaign->uid) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="ri-pause-circle-line text-warning me-2"></i>{{ translate('Pause') }}
                                                        </button>
                                                    </form>
                                                </li>
                                                @endif
                                                @if($campaign->status === \App\Enums\Campaign\UnifiedCampaignStatus::PAUSED)
                                                <li>
                                                    <form action="{{ route('admin.campaign.resume', $campaign->uid) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="ri-play-line text-info me-2"></i>{{ translate('Resume') }}
                                                        </button>
                                                    </form>
                                                </li>
                                                @endif
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('admin.campaign.duplicate', $campaign->uid) }}">
                                                        <i class="ri-file-copy-line text-primary me-2"></i>{{ translate('Duplicate') }}
                                                    </a>
                                                </li>
                                                @if($campaign->canCancel())
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <button type="button" class="dropdown-item text-warning cancel-campaign-btn"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#cancelCampaignModal"
                                                            data-action="{{ route('admin.campaign.cancel', $campaign->uid) }}"
                                                            data-name="{{ $campaign->name }}">
                                                        <i class="ri-close-circle-line me-2"></i>{{ translate('Cancel Campaign') }}
                                                    </button>
                                                </li>
                                                @endif
                                                @if($campaign->status !== \App\Enums\Campaign\UnifiedCampaignStatus::RUNNING)
                                                <li>
                                                    <button type="button" class="dropdown-item text-danger delete-campaign-btn"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#deleteCampaignModal"
                                                            data-action="{{ route('admin.campaign.destroy', $campaign->uid) }}"
                                                            data-name="{{ $campaign->name }}">
                                                        <i class="ri-delete-bin-line me-2"></i>{{ translate('Delete') }}
                                                    </button>
                                                </li>
                                                @endif
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @include('admin.partials.pagination', ['paginator' => $campaigns])
                @else
                <div class="text-center py-5">
                    <span class="icon-btn btn--primary btn--xl mb-3"><i class="ri-megaphone-line fs-24"></i></span>
                    <h5>{{ translate('No Campaigns Yet') }}</h5>
                    <p class="text-muted mb-3">{{ translate('Create your first campaign to start reaching your contacts across multiple channels.') }}</p>
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

@section('modal')
{{-- Cancel Campaign Modal --}}
<div class="modal fade actionModal" id="cancelCampaignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header text-start">
                <span class="action-icon warning">
                    <i class="ri-close-circle-line"></i>
                </span>
            </div>
            <form id="cancelCampaignForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="action-message">
                        <h5>{{ translate('Cancel Campaign') }}</h5>
                        <p>{{ translate('Are you sure you want to cancel') }} <strong id="cancelCampaignName"></strong>? {{ translate('This action cannot be undone.') }}</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate('No') }}</button>
                    <button type="submit" class="i-btn btn--warning btn--md">{{ translate('Yes, Cancel') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Delete Campaign Modal --}}
<div class="modal fade actionModal" id="deleteCampaignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header text-start">
                <span class="action-icon danger">
                    <i class="ri-delete-bin-line"></i>
                </span>
            </div>
            <form id="deleteCampaignForm" method="POST">
                @csrf
                <input type="hidden" name="_method" value="DELETE">
                <div class="modal-body">
                    <div class="action-message">
                        <h5>{{ translate('Delete Campaign') }}</h5>
                        <p>{{ translate('Are you sure you want to delete') }} <strong id="deleteCampaignName"></strong>? {{ translate('All associated data will be permanently removed.') }}</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="submit" class="i-btn btn--danger btn--md">{{ translate('Yes, Delete') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('script-push')
<script>
(function($) {
    "use strict";

    // Cancel Campaign Modal
    $('.cancel-campaign-btn').on('click', function() {
        var action = $(this).data('action');
        var name = $(this).data('name');
        $('#cancelCampaignForm').attr('action', action);
        $('#cancelCampaignName').text(name);
    });

    // Delete Campaign Modal
    $('.delete-campaign-btn').on('click', function() {
        var action = $(this).data('action');
        var name = $(this).data('name');
        $('#deleteCampaignForm').attr('action', action);
        $('#deleteCampaignName').text(name);
    });
})(jQuery);
</script>
@endpush

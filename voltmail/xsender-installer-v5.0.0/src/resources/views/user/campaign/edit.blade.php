@push("style-include")
<link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
@endpush

@extends('user.layouts.app')
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
                            <li class="breadcrumb-item"><a href="{{ route('user.campaign.index') }}">{{ translate('Campaigns') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ translate('Edit') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <form action="{{ route('user.campaign.update', $campaign->id) }}" method="POST" id="campaignForm">
            @csrf
            @method('PUT')

            <div class="row g-4">
                <div class="col-xl-8">
                    {{-- Campaign Details --}}
                    <div class="card">
                        <div class="form-header">
                            <h4 class="card-title">{{ translate('Campaign Details') }}</h4>
                        </div>
                        <div class="card-body pt-0">
                            <div class="form-element">
                                <div class="row">
                                    <div class="col-xxl-2 col-xl-3">
                                        <h5 class="form-element-title">{{ translate('Basic Info') }}</h5>
                                    </div>
                                    <div class="col-xxl-10 col-xl-9">
                                        <div class="row g-4">
                                            <div class="col-12">
                                                <div class="form-inner">
                                                    <label for="campaignName" class="form-label">{{ translate('Campaign Name') }} <span class="text-danger">*</span></label>
                                                    <input type="text" name="name" id="campaignName" class="form-control" value="{{ old('name', $campaign->name) }}" placeholder="{{ translate('Enter campaign name') }}" required>
                                                    @error('name')<small class="text-danger">{{ $message }}</small>@enderror
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-inner">
                                                    <label for="campaignDesc" class="form-label">{{ translate('Description') }}</label>
                                                    <textarea name="description" id="campaignDesc" class="form-control" rows="3" placeholder="{{ translate('Brief description of this campaign...') }}">{{ old('description', $campaign->description) }}</textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-element">
                                <div class="row">
                                    <div class="col-xxl-2 col-xl-3">
                                        <h5 class="form-element-title">{{ translate('Target Audience') }}</h5>
                                    </div>
                                    <div class="col-xxl-10 col-xl-9">
                                        <div class="row g-4">
                                            <div class="col-md-6">
                                                <div class="form-inner">
                                                    <label for="contactGroup" class="form-label">{{ translate('Contact Group') }} <span class="text-danger">*</span></label>
                                                    <select name="contact_group_id" id="contactGroup" class="form-select select2-search" data-placeholder="{{ translate('Select Contact Group') }}" required>
                                                        <option value=""></option>
                                                        @foreach($contactGroups as $group)
                                                        <option value="{{ $group->id }}" {{ old('contact_group_id', $campaign->contact_group_id) == $group->id ? 'selected' : '' }}>
                                                            {{ $group->name }} ({{ number_format($group->contacts_count) }})
                                                        </option>
                                                        @endforeach
                                                    </select>
                                                    @error('contact_group_id')<small class="text-danger">{{ $message }}</small>@enderror
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-inner">
                                                    <label for="campaignType" class="form-label">{{ translate('Campaign Type') }} <span class="text-danger">*</span></label>
                                                    <select name="type" id="campaignType" class="form-select" required>
                                                        @foreach($campaignTypes as $type)
                                                        <option value="{{ $type->value }}" {{ old('type', $campaign->type->value ?? $campaign->type) == $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-element schedule-section" style="display: none;">
                                <div class="row">
                                    <div class="col-xxl-2 col-xl-3">
                                        <h5 class="form-element-title">{{ translate('Schedule') }}</h5>
                                    </div>
                                    <div class="col-xxl-10 col-xl-9">
                                        <div class="row g-4">
                                            <div class="col-md-6">
                                                <div class="form-inner">
                                                    <label for="scheduleAt" class="form-label">{{ translate('Date & Time') }} <span class="text-danger">*</span></label>
                                                    <input type="datetime-local" name="schedule_at" id="scheduleAt" class="form-control" value="{{ old('schedule_at', $campaign->schedule_at ? $campaign->schedule_at->format('Y-m-d\TH:i') : '') }}">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-inner">
                                                    <label for="timezone" class="form-label">{{ translate('Timezone') }}</label>
                                                    <select name="timezone" id="timezone" class="form-select">
                                                        @php $currentTz = old('timezone', $campaign->timezone ?? 'UTC'); @endphp
                                                        <option value="UTC" {{ $currentTz == 'UTC' ? 'selected' : '' }}>UTC</option>
                                                        <option value="America/New_York" {{ $currentTz == 'America/New_York' ? 'selected' : '' }}>EST (New York)</option>
                                                        <option value="America/Los_Angeles" {{ $currentTz == 'America/Los_Angeles' ? 'selected' : '' }}>PST (Los Angeles)</option>
                                                        <option value="Europe/London" {{ $currentTz == 'Europe/London' ? 'selected' : '' }}>GMT (London)</option>
                                                        <option value="Asia/Dubai" {{ $currentTz == 'Asia/Dubai' ? 'selected' : '' }}>GST (Dubai)</option>
                                                        <option value="Asia/Kolkata" {{ $currentTz == 'Asia/Kolkata' ? 'selected' : '' }}>IST (Kolkata)</option>
                                                        <option value="Asia/Dhaka" {{ $currentTz == 'Asia/Dhaka' ? 'selected' : '' }}>BST (Dhaka)</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Channels --}}
                    <div class="card mt-4">
                        <div class="form-header">
                            <h4 class="card-title">{{ translate('Channels') }}</h4>
                        </div>
                        <div class="card-body pt-0">
                            <div class="form-element">
                                <div class="row">
                                    <div class="col-xxl-2 col-xl-3">
                                        <h5 class="form-element-title">{{ translate('Select Channels') }}</h5>
                                    </div>
                                    <div class="col-xxl-10 col-xl-9">
                                        <div class="row g-3">
                                            @php $currentChannels = old('channels', $campaign->channels ?? []); @endphp
                                            @foreach(\App\Enums\Campaign\CampaignChannel::cases() as $channel)
                                            <div class="col-md-4">
                                                <label class="campaign-channel-card {{ in_array($channel->value, $currentChannels) ? 'active' : '' }}">
                                                    <input type="checkbox" name="channels[]" value="{{ $channel->value }}" {{ in_array($channel->value, $currentChannels) ? 'checked' : '' }}>
                                                    <span class="channel-icon channel-{{ $channel->value }}">
                                                        <i class="{{ $channel->icon() }}"></i>
                                                    </span>
                                                    <span class="channel-name">{{ $channel->label() }}</span>
                                                    <span class="channel-count" data-channel="{{ $channel->value }}">0 {{ translate('contacts') }}</span>
                                                    <span class="channel-check"><i class="ri-checkbox-circle-fill"></i></span>
                                                </label>
                                            </div>
                                            @endforeach
                                        </div>
                                        @error('channels')<small class="text-danger d-block mt-2">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-element">
                                <div class="row">
                                    <div class="col-xxl-2 col-xl-3">
                                        <h5 class="form-element-title">{{ translate('Delivery Mode') }}</h5>
                                    </div>
                                    <div class="col-xxl-10 col-xl-9">
                                        <div class="row g-3">
                                            @php $currentMode = old('channel_detection_mode', $campaign->channel_detection_mode->value ?? $campaign->channel_detection_mode ?? 'auto'); @endphp
                                            @foreach($detectionModes as $mode)
                                            <div class="col-lg-12">
                                                <label class="campaign-delivery-card {{ $currentMode == $mode->value ? 'active' : '' }}">
                                                    <input type="radio" name="channel_detection_mode" value="{{ $mode->value }}" {{ $currentMode == $mode->value ? 'checked' : '' }}>
                                                    <span class="delivery-header">
                                                        <span class="delivery-icon"><i class="{{ $mode->icon() }}"></i></span>
                                                        <span class="delivery-name">{{ $mode->label() }}</span>
                                                    </span>
                                                    <span class="delivery-desc">{{ $mode->description() }}</span>
                                                </label>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <a href="{{ route('user.campaign.show', $campaign->id) }}" class="i-btn btn--dark outline btn--md">{{ translate('Cancel') }}</a>
                        <button type="submit" class="i-btn btn--primary btn--md">{{ translate('Continue') }} <i class="ri-arrow-right-line ms-1"></i></button>
                    </div>
                </div>

                {{-- Sidebar --}}
                <div class="col-xl-4">
                    <div class="sticky-side-div">
                        {{-- Wizard Progress --}}
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="campaign-steps">
                                    <div class="campaign-step active">
                                        <span class="step-num">1</span>
                                        <span class="step-text">{{ translate('Campaign Setup') }}</span>
                                    </div>
                                    <div class="campaign-step">
                                        <span class="step-num">2</span>
                                        <span class="step-text">{{ translate('Messages') }}</span>
                                    </div>
                                    <div class="campaign-step">
                                        <span class="step-num">3</span>
                                        <span class="step-text">{{ translate('Review & Launch') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Distribution --}}
                        <div class="card">
                            <div class="form-header">
                                <h4 class="card-title">{{ translate('Contact Distribution') }}</h4>
                            </div>
                            <div class="card-body">
                                <div id="channelDistribution">
                                    <div class="distribution-empty">
                                        <i class="ri-pie-chart-2-line"></i>
                                        <p>{{ translate('Select a contact group to see distribution') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Tips --}}
                        <div class="card mt-4">
                            <div class="card-body">
                                <h6 class="d-flex align-items-center gap-2 fs-18 mb-3">
                                    {{ translate('Quick Tips') }}
                                </h6>
                                <ul class="campaign-tips">
                                    <li>{{ translate('Select multiple channels to maximize reach') }}</li>
                                    <li>{{ translate('Auto Detect mode sends via the best available channel') }}</li>
                                    <li>{{ translate('Schedule campaigns for optimal engagement times') }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>

@endsection


@push("script-include")
<script src="{{asset('assets/theme/global/js/select2.min.js')}}"></script>
@endpush

@push('script-push')
<script>
"use strict";
document.addEventListener('DOMContentLoaded', function() {
    if (typeof select2_search === 'function') {
        select2_search($('.select2-search').data('placeholder'));
    }

    var contactGroup = document.getElementById('contactGroup');
    var campaignType = document.getElementById('campaignType');
    var scheduleSection = document.querySelector('.schedule-section');
    var distributionDiv = document.getElementById('channelDistribution');

    // Channel card toggle
    document.querySelectorAll('.campaign-channel-card input').forEach(function(input) {
        input.addEventListener('change', function() {
            this.closest('.campaign-channel-card').classList.toggle('active', this.checked);
        });
    });

    // Delivery mode card toggle
    document.querySelectorAll('.campaign-delivery-card input').forEach(function(input) {
        input.addEventListener('change', function() {
            document.querySelectorAll('.campaign-delivery-card').forEach(function(c) { c.classList.remove('active'); });
            if (this.checked) this.closest('.campaign-delivery-card').classList.add('active');
        });
    });

    // Campaign type change - show/hide schedule
    campaignType.addEventListener('change', function() {
        var show = this.value === 'scheduled' || this.value === 'recurring';
        scheduleSection.style.display = show ? 'block' : 'none';
        var scheduleInput = scheduleSection.querySelector('input[type="datetime-local"]');
        if (scheduleInput) scheduleInput.required = show;
    });
    campaignType.dispatchEvent(new Event('change'));

    // Contact group change - load distribution
    contactGroup.addEventListener('change', function() {
        var groupId = this.value;
        if (!groupId) {
            distributionDiv.innerHTML = '<div class="distribution-empty"><i class="ri-pie-chart-2-line"></i><p>{{ translate("Select a contact group to see distribution") }}</p></div>';
            document.querySelectorAll('.channel-count').forEach(function(el) { el.textContent = '0 {{ translate("contacts") }}'; });
            return;
        }

        distributionDiv.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';

        fetch('{{ route("user.campaign.channel-distribution") }}?group_id=' + groupId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var total = data.total || 0;
                var channels = data.channels || {};
                var cfg = {
                    sms: { icon: 'ri-chat-1-line', label: 'SMS', color: '#6366f1' },
                    email: { icon: 'ri-mail-line', label: 'Email', color: '#3b82f6' },
                    whatsapp: { icon: 'ri-whatsapp-line', label: 'WhatsApp', color: '#25d366' }
                };

                var html = '<div class="distribution-total"><div class="total-icon"><i class="ri-group-line"></i></div><div class="total-info"><h4>' + total.toLocaleString() + '</h4><span>{{ translate("Total Contacts") }}</span></div></div>';

                for (var ch in channels) {
                    if (!channels.hasOwnProperty(ch) || !cfg[ch]) continue;
                    var count = channels[ch];
                    var c = cfg[ch];
                    var pct = total > 0 ? ((count / total) * 100).toFixed(1) : 0;
                    html += '<div class="distribution-item"><div class="item-icon ' + ch + '"><i class="' + c.icon + '"></i></div><div class="item-info"><h6>' + c.label + '</h6><div class="progress"><div class="progress-bar" style="width:' + pct + '%;background:' + c.color + '"></div></div></div><div class="item-count"><strong>' + count.toLocaleString() + '</strong><small>' + pct + '%</small></div></div>';
                    var countEl = document.querySelector('.channel-count[data-channel="' + ch + '"]');
                    if (countEl) countEl.textContent = count.toLocaleString() + ' {{ translate("contacts") }}';
                }
                distributionDiv.innerHTML = html;
            })
            .catch(function() {
                distributionDiv.innerHTML = '<div class="text-center py-4 text-danger"><i class="ri-error-warning-line fs-3"></i><p class="mt-2 mb-0">{{ translate("Failed to load") }}</p></div>';
            });
    });

    // Trigger distribution load on page load if group is pre-selected
    if (contactGroup.value) contactGroup.dispatchEvent(new Event('change'));
});
</script>
@endpush

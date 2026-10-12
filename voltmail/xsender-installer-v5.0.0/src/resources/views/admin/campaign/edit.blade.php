@push("style-include")
<link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
@endpush

@extends('admin.layouts.app')
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
                            <li class="breadcrumb-item active" aria-current="page">{{ translate('Edit') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.campaign.update', $campaign->uid) }}" method="POST">
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
                                                    <label class="form-label">{{ translate('Campaign Name') }} <span class="text-danger">*</span></label>
                                                    <input type="text" name="name" class="form-control" value="{{ old('name', $campaign->name) }}" required>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-inner">
                                                    <label class="form-label">{{ translate('Description') }}</label>
                                                    <textarea name="description" class="form-control" rows="3">{{ old('description', $campaign->description) }}</textarea>
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
                                                    <label class="form-label">{{ translate('Contact Group') }} <span class="text-danger">*</span></label>
                                                    <select name="contact_group_id" class="form-select select2-search" data-placeholder="{{ translate('Select Contact Group') }}" required>
                                                        <option value=""></option>
                                                        @foreach($contactGroups as $group)
                                                        <option value="{{ $group->id }}" {{ old('contact_group_id', $campaign->contact_group_id) == $group->id ? 'selected' : '' }}>
                                                            {{ $group->name }} ({{ number_format($group->contacts_count) }})
                                                        </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-inner">
                                                    <label class="form-label">{{ translate('Campaign Type') }} <span class="text-danger">*</span></label>
                                                    <select name="type" id="campaignType" class="form-select" required>
                                                        @foreach($campaignTypes as $type)
                                                        <option value="{{ $type->value }}" {{ old('type', $campaign->type->value) == $type->value ? 'selected' : '' }}>
                                                            {{ $type->label() }}
                                                        </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-element schedule-section" style="{{ in_array($campaign->type->value, ['scheduled', 'recurring']) ? '' : 'display:none;' }}">
                                <div class="row">
                                    <div class="col-xxl-2 col-xl-3">
                                        <h5 class="form-element-title">{{ translate('Schedule') }}</h5>
                                    </div>
                                    <div class="col-xxl-10 col-xl-9">
                                        <div class="row g-4">
                                            <div class="col-md-6">
                                                <div class="form-inner">
                                                    <label class="form-label">{{ translate('Date & Time') }}</label>
                                                    <input type="datetime-local" name="schedule_at" class="form-control" value="{{ old('schedule_at', $campaign->schedule_at?->format('Y-m-d\TH:i')) }}">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-inner">
                                                    <label class="form-label">{{ translate('Timezone') }}</label>
                                                    <select name="timezone" class="form-select">
                                                        <option value="UTC" {{ old('timezone', $campaign->timezone) == 'UTC' ? 'selected' : '' }}>UTC</option>
                                                        <option value="America/New_York" {{ old('timezone', $campaign->timezone) == 'America/New_York' ? 'selected' : '' }}>EST (New York)</option>
                                                        <option value="America/Los_Angeles" {{ old('timezone', $campaign->timezone) == 'America/Los_Angeles' ? 'selected' : '' }}>PST (Los Angeles)</option>
                                                        <option value="Europe/London" {{ old('timezone', $campaign->timezone) == 'Europe/London' ? 'selected' : '' }}>GMT (London)</option>
                                                        <option value="Asia/Dubai" {{ old('timezone', $campaign->timezone) == 'Asia/Dubai' ? 'selected' : '' }}>GST (Dubai)</option>
                                                        <option value="Asia/Kolkata" {{ old('timezone', $campaign->timezone) == 'Asia/Kolkata' ? 'selected' : '' }}>IST (Kolkata)</option>
                                                        <option value="Asia/Dhaka" {{ old('timezone', $campaign->timezone) == 'Asia/Dhaka' ? 'selected' : '' }}>BST (Dhaka)</option>
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
                                            @foreach(\App\Enums\Campaign\CampaignChannel::cases() as $channel)
                                            <div class="col-md-4">
                                                <label class="campaign-channel-card {{ in_array($channel->value, old('channels', $campaign->channels ?? [])) ? 'active' : '' }}">
                                                    <input type="checkbox" name="channels[]" value="{{ $channel->value }}" {{ in_array($channel->value, old('channels', $campaign->channels ?? [])) ? 'checked' : '' }}>
                                                    <span class="channel-icon channel-{{ $channel->value }}">
                                                        <i class="{{ $channel->icon() }}"></i>
                                                    </span>
                                                    <span class="channel-name">{{ $channel->label() }}</span>
                                                    <span class="channel-check"><i class="ri-checkbox-circle-fill"></i></span>
                                                </label>
                                            </div>
                                            @endforeach
                                        </div>
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
                                            @foreach($detectionModes as $mode)
                                            <div class="col-lg-12">
                                                <label class="campaign-delivery-card {{ old('channel_detection_mode', $campaign->channel_detection_mode->value) == $mode->value ? 'active' : '' }}">
                                                    <input type="radio" name="channel_detection_mode" value="{{ $mode->value }}" {{ old('channel_detection_mode', $campaign->channel_detection_mode->value) == $mode->value ? 'checked' : '' }}>
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
                </div>

                {{-- Sidebar --}}
                <div class="col-xl-4">
                    <div class="sticky-side-div">
                        {{-- Current Status --}}
                        <div class="card">
                            <div class="form-header">
                                <h4 class="card-title">{{ translate('Current Status') }}</h4>
                            </div>
                            <div class="card-body pt-0">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <span class="badge {{ $campaign->status->badgeClass() }} fs-6">
                                        <i class="{{ $campaign->status->icon() }} me-1"></i>
                                        {{ $campaign->status->label() }}
                                    </span>
                                </div>
                                <small class="text-muted">
                                    {{ translate('Created') }}: {{ $campaign->created_at->format('M d, Y H:i') }}
                                </small>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="card mt-4">
                            <div class="card-body">
                                <button type="submit" class="i-btn btn--primary btn--lg w-100">
                                    <i class="ri-save-line me-2"></i>
                                    {{ translate('Save Changes') }}
                                </button>

                                <a href="{{ route('admin.campaign.messages', $campaign->uid) }}" class="i-btn btn--info outline btn--lg w-100 mt-3">
                                    <i class="ri-mail-line me-2"></i>
                                    {{ translate('Edit Messages') }}
                                </a>

                                <a href="{{ route('admin.campaign.show', $campaign->uid) }}" class="i-btn btn--dark outline btn--lg w-100 mt-3">
                                    {{ translate('Cancel') }}
                                </a>
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
document.addEventListener('DOMContentLoaded', function() {
    if (typeof select2_search === 'function') {
        select2_search($('.select2-search').data('placeholder'));
    }

    const campaignType = document.getElementById('campaignType');
    const scheduleSection = document.querySelector('.schedule-section');

    campaignType.addEventListener('change', function() {
        const show = this.value === 'scheduled' || this.value === 'recurring';
        scheduleSection.style.display = show ? 'block' : 'none';
    });

    document.querySelectorAll('.campaign-channel-card input').forEach(input => {
        input.addEventListener('change', function() {
            this.closest('.campaign-channel-card').classList.toggle('active', this.checked);
        });
    });

    document.querySelectorAll('.campaign-delivery-card input').forEach(input => {
        input.addEventListener('change', function() {
            document.querySelectorAll('.campaign-delivery-card').forEach(c => c.classList.remove('active'));
            if (this.checked) this.closest('.campaign-delivery-card').classList.add('active');
        });
    });
});
</script>
@endpush

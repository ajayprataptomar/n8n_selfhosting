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
                            <li class="breadcrumb-item active" aria-current="page">{{ translate('Review') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        {{-- Validation Alerts --}}
        @if(!$validation['valid'])
        <div class="alert alert-danger mb-4">
            <h6 class="alert-heading mb-2"><i class="ri-error-warning-line me-2"></i>{{ translate('Please fix the following issues') }}</h6>
            <ul class="mb-0 ps-3">
                @foreach($validation['errors'] as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @if(!empty($validation['warnings']))
        <div class="alert alert-warning mb-4">
            <h6 class="alert-heading mb-2"><i class="ri-alert-line me-2"></i>{{ translate('Warnings') }}</h6>
            <ul class="mb-0 ps-3">
                @foreach($validation['warnings'] as $warning)
                <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="row g-4">
            <div class="col-xl-8">
                {{-- Campaign Overview --}}
                <div class="card">
                    <div class="form-header">
                        <h4 class="card-title">{{ translate('Campaign Overview') }}</h4>
                    </div>
                    <div class="card-body">
                         <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3 border rounded h-100">
                                    <div class="text-muted small mb-1">
                                        {{ translate('Campaign Name') }}
                                    </div>
                                    <div class="fw-semibold fs-6">
                                        {{ $campaign->name }}
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="p-3 border rounded h-100">
                                    <div class="text-muted small mb-1">
                                        {{ translate('Contact Group') }}
                                    </div>
                                    <div class="fw-semibold fs-6">
                                        {{ $campaign->contactGroup?->name ?? '-' }}
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="p-3 border rounded h-100">
                                    <div class="text-muted small mb-1">
                                        {{ translate('Campaign Type') }}
                                    </div>
                                    <div class="fw-semibold fs-6 text-capitalize">
                                        {{ $campaign->type instanceof \App\Enums\Campaign\CampaignType ? $campaign->type->label() : ucfirst($campaign->type ?? '-') }}
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="p-3 border rounded h-100 bg-light">
                                    <div class="text-muted small mb-1">
                                        {{ translate('Total Contacts') }}
                                    </div>
                                    <div class="fw-bold fs-4 text-primary">
                                        {{ number_format($campaign->total_contacts) }}
                                    </div>
                                </div>
                            </div>

                            @if($campaign->schedule_at)
                            <div class="col-md-6">
                                <div class="p-3 border rounded h-100">
                                    <div class="text-muted small mb-1">
                                        {{ translate('Schedule') }}
                                    </div>
                                    <div class="fw-semibold">
                                        <i class="ri-calendar-event-line text-primary me-1"></i>
                                        {{ $campaign->schedule_at->format('M d, Y H:i') }}
                                        <span class="text-muted small">
                                            ({{ $campaign->timezone }})
                                        </span>
                                    </div>
                                </div>
                            </div>
                            @endif

                            <div class="col-md-6">
                                <div class="p-3 border rounded h-100">
                                    <div class="text-muted small mb-1">
                                        {{ translate('Delivery Mode') }}
                                    </div>
                                    <div class="fw-semibold text-capitalize">
                                        <i class="ri-send-plane-line text-success me-1"></i>
                                        {{ $campaign->channel_detection_mode instanceof \App\Enums\Campaign\ChannelDetectionMode ? $campaign->channel_detection_mode->label() : ucfirst($campaign->channel_detection_mode ?? '-') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Channel Messages --}}
                <div class="card mt-4">
                    <div class="form-header">
                        <h4 class="card-title">{{ translate('Messages') }}</h4>
                    </div>
                    <div class="card-body p-0">
                        @forelse($campaign->messages as $message)
                        @php
                            $channelValue = $message->channel instanceof \App\Enums\Campaign\CampaignChannel
                                ? $message->channel->value
                                : (string) $message->channel;
                            $channelEnum = \App\Enums\Campaign\CampaignChannel::tryFrom($channelValue);
                        @endphp
                        <div class="message-preview p-4 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="d-flex align-items-center gap-3">
                                    @if($channelEnum)
                                    <div class="channel-badge {{ $channelValue }}">
                                        <i class="{{ $channelEnum->icon() }}"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">{{ $channelEnum->label() }}</h6>
                                        <small class="text-muted">{{ $message->gateway_display_name ?? ((isset($planType) && $planType == \App\Enums\StatusEnum::TRUE->status()) ? translate('Auto-assigned') : translate('No Gateway')) }}</small>
                                    </div>
                                    @else
                                    <div>
                                        <h6 class="mb-0">{{ ucfirst($channelValue) }}</h6>
                                        <small class="text-muted">{{ $message->gateway_display_name ?? ((isset($planType) && $planType == \App\Enums\StatusEnum::TRUE->status()) ? translate('Auto-assigned') : translate('No Gateway')) }}</small>
                                    </div>
                                    @endif
                                </div>
                                <a href="{{ route('user.campaign.messages', $campaign->id) }}" class="i-btn btn--primary outline btn--sm">
                                    <i class="ri-pencil-line me-1"></i> {{ translate('Edit') }}
                                </a>
                            </div>

                            @if($message->subject)
                            <div class="mb-2">
                                <span class="text-muted small">{{ translate('Subject') }}:</span>
                                <span class="fw-semibold">{{ $message->subject }}</span>
                            </div>
                            @endif

                            <div class="message-content">
                                @if($channelValue === 'email')
                                    {{-- Content injected via JS + document.write() instead of a
                                         srcdoc attribute — v4.7.0.2 shipped a srcdoc whose escape
                                         layer leaked backslashes into the rendered HTML, so users
                                         saw the raw `<!DOCTYPE html>...<meta charset=\"utf-8\">`
                                         as text instead of a rendered email. @json is XSS-safe. --}}
                                    @php $emailFrameId = 'email-preview-frame-' . $message->id; @endphp
                                    <iframe
                                        id="{{ $emailFrameId }}"
                                        class="email-preview-frame"
                                        style="width:100%; min-height:520px; border:1px solid #e5e7eb; border-radius:8px; background:#ffffff;"
                                    ></iframe>
                                    <script>
                                    (function () {
                                        var body = @json((string) $message->content);
                                        var html = '<!DOCTYPE html><html><head><meta charset="utf-8"><base target="_blank"><style>html,body{margin:0;padding:16px;background:#ffffff;color:#111827;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;}p{margin:0 0 8px 0;}p:last-child{margin-bottom:0;}h1,h2,h3,h4,h5,h6{margin:12px 0 8px 0;}img{max-width:100%;height:auto;}a{color:#2563eb;}</style></head><body>' + body + '</body></html>';
                                        var run = function () {
                                            var f = document.getElementById(@json($emailFrameId));
                                            if (!f) return;
                                            var doc = f.contentDocument || (f.contentWindow && f.contentWindow.document);
                                            if (!doc) return;
                                            doc.open(); doc.write(html); doc.close();
                                            try { f.style.height = (doc.documentElement.scrollHeight + 8) + 'px'; } catch (e) {}
                                        };
                                        if (document.readyState === 'loading') {
                                            document.addEventListener('DOMContentLoaded', run);
                                        } else { run(); }
                                    })();
                                    </script>
                                @else
                                    {{ plain_text_preview($message->content, 300) }}
                                @endif
                            </div>

                            @if($channelValue === 'sms' && $message->content)
                            <div class="mt-2 text-muted small">
                                <i class="ri-file-text-line me-1"></i>
                                {{ strlen($message->content) }} {{ translate('characters') }}
                            </div>
                            @endif
                        </div>
                        @empty
                        <div class="text-center py-5 text-muted">
                            <i class="ri-mail-line fs-1"></i>
                            <p class="mt-2">{{ translate('No messages configured') }}</p>
                            <a href="{{ route('user.campaign.messages', $campaign->id) }}" class="i-btn btn--primary btn--sm">
                                {{ translate('Add Messages') }}
                            </a>
                        </div>
                        @endforelse
                    </div>
                </div>

                {{-- Back Button --}}
                <div class="mt-4">
                    <a href="{{ route('user.campaign.messages', $campaign->id) }}" class="i-btn btn--dark outline btn--md">
                        <i class="ri-arrow-left-line me-1"></i> {{ translate('Back to Messages') }}
                    </a>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="col-xl-4">
                <div class="sticky-side-div">
                    {{-- Wizard Progress --}}
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="campaign-steps">
                                <div class="campaign-step completed">
                                    <span class="step-num"><i class="ri-check-line"></i></span>
                                    <span class="step-text">{{ translate('Campaign Setup') }}</span>
                                </div>
                                <div class="campaign-step completed">
                                    <span class="step-num"><i class="ri-check-line"></i></span>
                                    <span class="step-text">{{ translate('Messages') }}</span>
                                </div>
                                <div class="campaign-step active">
                                    <span class="step-num">3</span>
                                    <span class="step-text">{{ translate('Review & Launch') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Delivery Estimate --}}
                    <div class="card">
                        <div class="form-header">
                            <h4 class="card-title">{{ translate('Delivery Estimate') }}</h4>
                        </div>
                        <div class="card-body">
                            @foreach($channelDistribution['channels'] as $channel => $count)
                            @if(in_array($channel, $campaign->channels))
                            @php $channelEnum = \App\Enums\Campaign\CampaignChannel::tryFrom($channel); @endphp
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span>
                                    <i class="{{ $channelEnum->icon() }} me-2" style="color: {{ $channel == 'sms' ? '#6366f1' : ($channel == 'email' ? '#3b82f6' : '#25d366') }}"></i>
                                    {{ $channelEnum->label() }}
                                </span>
                                <span class="fw-semibold">{{ number_format($count) }}</span>
                            </div>
                            @endif
                            @endforeach

                            <hr>

                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-semibold fs-15">{{ translate('Total Messages') }}</span>
                                <span class="fw-bold fs-5" style="color: var(--color-primary)">
                                    @php
                                        $totalMessages = 0;
                                        foreach($campaign->channels as $ch) {
                                            $totalMessages += $channelDistribution['channels'][$ch] ?? 0;
                                        }
                                    @endphp
                                    {{ number_format($totalMessages) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Launch Actions --}}
                    <div class="card mt-4">
                        <div class="card-body">
                            @if($validation['valid'])
                            <form action="{{ route('user.campaign.launch', $campaign->id) }}" method="POST" id="launchForm">
                                @csrf
                                @php
                                    $typeValue = $campaign->type instanceof \App\Enums\Campaign\CampaignType
                                        ? $campaign->type->value
                                        : (string) $campaign->type;
                                @endphp
                                <button type="button" class="i-btn btn--success btn--lg w-100" id="launchBtn">
                                    <i class="ri-rocket-line me-2"></i>
                                    @if($typeValue === 'instant')
                                    {{ translate('Launch Campaign Now') }}
                                    @else
                                    {{ translate('Schedule Campaign') }}
                                    @endif
                                </button>
                            </form>
                            @else
                            <button class="i-btn btn--dark outline btn--lg w-100" disabled>
                                <i class="ri-error-warning-line me-2"></i>
                                {{ translate('Fix Errors to Launch') }}
                            </button>
                            <div class="mt-3">
                                <small class="text-danger">
                                    @foreach($validation['errors'] as $error)
                                    <div class="mb-1"><i class="ri-close-circle-line me-1"></i>{{ $error }}</div>
                                    @endforeach
                                </small>
                            </div>
                            @endif

                            <hr class="my-3">

                            <a href="{{ route('user.campaign.edit', $campaign->id) }}" class="d-block text-center text-muted">
                                <i class="ri-settings-3-line me-1"></i>
                                {{ translate('Edit Campaign Settings') }}
                            </a>
                        </div>
                    </div>

                    {{-- Tips --}}
                    <div class="card mt-4">
                        <div class="card-body">
                            <h6 class="d-flex align-items-center gap-2 mb-3 fs-18">
                                {{ translate('Tips') }}
                            </h6>
                            <ul class="campaign-tips">
                                <li>{{ translate('Review your message content for any typos') }}</li>
                                <li>{{ translate('Make sure personalization variables are correct') }}</li>
                                <li>{{ translate('Consider sending a test message first') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

{{-- Launch Confirm Modal --}}
@if($validation['valid'])
<div class="confirm-modal-overlay" id="launchConfirmModal">
    <div class="confirm-modal-box">
        <div class="confirm-modal-icon success">
            <i class="ri-rocket-line"></i>
        </div>
        <h5 class="confirm-modal-title">
            @if(($campaign->type instanceof \App\Enums\Campaign\CampaignType ? $campaign->type->value : (string) $campaign->type) === 'instant')
            {{ translate('Launch Campaign') }}
            @else
            {{ translate('Schedule Campaign') }}
            @endif
        </h5>
        <p class="confirm-modal-message">{{ translate('Are you sure you want to launch this campaign? This action cannot be undone.') }}</p>
        <div class="confirm-modal-actions">
            <button type="button" class="i-btn btn--dark outline btn--md" id="launchCancelBtn">
                {{ translate('Cancel') }}
            </button>
            <button type="button" class="i-btn btn--success btn--md" id="launchConfirmBtn">
                <i class="ri-rocket-line me-1"></i>
                @if(($campaign->type instanceof \App\Enums\Campaign\CampaignType ? $campaign->type->value : (string) $campaign->type) === 'instant')
                {{ translate('Launch Now') }}
                @else
                {{ translate('Schedule') }}
                @endif
            </button>
        </div>
    </div>
</div>
@endif

@endsection


@push('script-push')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var launchBtn = document.getElementById('launchBtn');
    var launchForm = document.getElementById('launchForm');
    var modal = document.getElementById('launchConfirmModal');
    var cancelBtn = document.getElementById('launchCancelBtn');
    var confirmBtn = document.getElementById('launchConfirmBtn');

    if (launchBtn && launchForm && modal) {
        launchBtn.addEventListener('click', function() {
            modal.classList.add('show');
        });

        cancelBtn.addEventListener('click', function() {
            modal.classList.remove('show');
        });

        modal.addEventListener('click', function(e) {
            if (e.target === modal) modal.classList.remove('show');
        });

        confirmBtn.addEventListener('click', function() {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="ri-loader-4-line me-2 spin"></i> {{ translate("Launching...") }}';
            launchBtn.disabled = true;
            launchForm.submit();
        });
    }
});
</script>
@endpush

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
                                    <label class="text-muted small mb-1">{{ translate('Campaign Name') }}</label>
                                    <p class="fw-semibold fs-6">{{ $campaign->name }}</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded h-100">
                                    <label class="text-muted small mb-1">{{ translate('Contact Group') }}</label>
                                    <p class="fw-semibold fs-6">{{ $campaign->contactGroup?->name ?? '-' }}</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded h-100">
                                    <label class="text-muted small mb-1">{{ translate('Campaign Type') }}</label>
                                    <p class="fw-semibold fs-6">{{ $campaign->type->label() }}</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded h-100">
                                    <label class="text-muted small mb-1">{{ translate('Total Contacts') }}</label>
                                    <p class="fw-semibold fs-6">{{ number_format($campaign->total_contacts) }}</p>
                                </div>
                            </div>
                            @if($campaign->schedule_at)
                            <div class="col-md-6">
                                <div class="p-3 border rounded h-100">
                                    <label class="text-muted small mb-1">{{ translate('Schedule') }}</label>
                                    <p class="fw-semibold fs-6">
                                        <i class="ri-calendar-event-line me-1"></i>
                                        {{ $campaign->schedule_at->format('M d, Y H:i') }} {{ $campaign->timezone }}
                                    </p>
                                </div>
                            </div>
                            @endif
                            <div class="col-md-6">
                                <div class="p-3 border rounded h-100">
                                    <label class="text-muted small mb-1">{{ translate('Delivery Mode') }}</label>
                                    <p class="fw-semibold fs-6">{{ $campaign->channel_detection_mode->label() }}</p>
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
                        @foreach($campaign->messages as $message)
                        @php $channelEnum = \App\Enums\Campaign\CampaignChannel::tryFrom($message->channel->value); @endphp
                        <div class="message-preview p-4 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="channel-badge {{ $message->channel->value }}">
                                        <i class="{{ $channelEnum->icon() }}"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">{{ $channelEnum->label() }}</h6>
                                        <small class="text-muted">{{ $message->gateway_display_name ?? translate('No Gateway') }}</small>
                                    </div>
                                </div>
                                <a href="{{ route('admin.campaign.messages', $campaign->uid) }}" class="i-btn btn--primary outline btn--sm">
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
                                @if($message->isEmail())
                                    {{-- Render email HTML inside a sandboxed iframe so it does NOT
                                         inherit the admin panel's dark theme, fonts, or spacing.
                                         The recipient sees the email against a plain white body,
                                         which is how it will actually arrive in their inbox.

                                         Content is injected via JS + document.write() rather than
                                         a srcdoc attribute — attribute-escaping made backslashes
                                         inside the wrapper HTML leak through as literal characters
                                         (v4.7.0.2 shipped a broken srcdoc that showed the raw
                                         `<!DOCTYPE html>...<meta charset=\"utf-8\">` as text).
                                         @json is XSS-safe: it escapes </script> as <\/script>. --}}
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

                            @if($message->isSms())
                            <div class="mt-2 text-muted small">
                                <i class="ri-file-text-line me-1"></i>
                                {{ $message->getSmsCharacterCount() }} {{ translate('characters') }} /
                                {{ $message->getSmsSegmentCount() }} {{ translate('segments') }}
                            </div>
                            @endif
                        </div>
                        @endforeach

                        @if($campaign->messages->isEmpty())
                        <div class="text-center py-5 text-muted">
                            <i class="ri-mail-line fs-1"></i>
                            <p class="mt-2">{{ translate('No messages configured') }}</p>
                            <a href="{{ route('admin.campaign.messages', $campaign->uid) }}" class="i-btn btn--primary btn--sm">
                                {{ translate('Add Messages') }}
                            </a>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Back Button --}}
                <div class="mt-4">
                    <a href="{{ route('admin.campaign.messages', $campaign->uid) }}" class="i-btn btn--dark outline btn--md">
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
                        <div class="card-body pt-0">
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
                                <span class="fw-semibold">{{ translate('Total Messages') }}</span>
                                <span class="fw-bold fs-5 text-primary">
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
                            <form action="{{ route('admin.campaign.launch', $campaign->uid) }}" method="POST" id="launchForm">
                                @csrf
                                <button type="button" class="i-btn btn--success btn--lg w-100" id="launchBtn">
                                    <i class="ri-rocket-line me-2"></i>
                                    @if($campaign->type->value === 'instant')
                                    {{ translate('Launch Campaign Now') }}
                                    @else
                                    {{ translate('Schedule Campaign') }}
                                    @endif
                                </button>
                            </form>
                            @else
                            <button class="i-btn btn--success btn--lg w-100" disabled>
                                <i class="ri-rocket-line me-2"></i>
                                {{ translate('Fix Errors to Launch') }}
                            </button>
                            @endif

                            <hr class="my-3">

                            <a href="{{ route('admin.campaign.edit', $campaign->uid) }}" class="btn btn-link text-muted w-100 p-0">
                                <i class="ri-settings-3-line me-1"></i>
                                {{ translate('Edit Campaign Settings') }}
                            </a>
                        </div>
                    </div>

                    {{-- Tips --}}
                    <div class="card mt-4">
                        <div class="card-body">
                            <h6 class="d-flex align-items-center gap-2 mb-3">
                                <i class="ri-lightbulb-flash-line text-warning fs-5"></i>
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
            @if($campaign->type->value === 'instant')
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
                @if($campaign->type->value === 'instant')
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

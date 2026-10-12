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
                            <li class="breadcrumb-item active" aria-current="page">{{ translate('Messages') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <form action="{{ route('user.campaign.messages.store', $campaign->id) }}" method="POST">
            @csrf

            <div class="row g-4">
                <div class="col-xl-8">
                    {{-- Channel Tabs --}}
                    <div class="card">
                        <div class="card-header border-bottom-0 pb-0">
                            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                                @foreach($campaign->channels as $index => $channel)
                                @php $channelEnum = \App\Enums\Campaign\CampaignChannel::tryFrom($channel); @endphp
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $index === 0 ? 'active' : '' }}" id="tab-{{ $channel }}" data-bs-toggle="tab" data-bs-target="#content-{{ $channel }}" type="button" role="tab">
                                        <i class="{{ $channelEnum->icon() }} me-2"></i>
                                        {{ $channelEnum->label() }}
                                        @php $message = $campaign->getMessageForChannel($channel); @endphp
                                        @if($message && $message->content)
                                        <i class="ri-checkbox-circle-fill text-success ms-2"></i>
                                        @endif
                                    </button>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="card-body pt-4">
                            <div class="tab-content">
                                @foreach($campaign->channels as $index => $channel)
                                @php
                                    $channelEnum = \App\Enums\Campaign\CampaignChannel::tryFrom($channel);
                                    $message = $campaign->getMessageForChannel($channel);
                                    $channelGateways = $gateways[$channel] ?? collect();
                                    $channelGatewaysGrouped = $gatewaysGrouped[$channel] ?? [];
                                    $channelTemplates = $templates[$channel] ?? collect();
                                @endphp
                                <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}" id="content-{{ $channel }}" role="tabpanel">

                                    {{-- Channel Header --}}
                                    <div class="channel-header mb-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="channel-header-icon channel-{{ $channel }}">
                                                <i class="{{ $channelEnum->icon() }}"></i>
                                            </div>
                                            <div>
                                                <h5 class="mb-1">{{ $channelEnum->label() }} {{ translate('Message') }}</h5>
                                                <p class="text-muted small mb-0">{{ translate('Configure your') }} {{ strtolower($channelEnum->label()) }} {{ translate('message content and gateway') }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row g-4">
                                        {{-- Gateway Selection --}}
                                        @php
                                            $isAdminPlan = (isset($planType) && $planType == \App\Enums\StatusEnum::TRUE->status());
                                            $showGatewaySelector = !$isAdminPlan || ($channel === 'whatsapp' && $channelGateways->isNotEmpty());
                                        @endphp

                                        @if($showGatewaySelector)
                                        <div class="col-12">
                                            <div class="form-inner">
                                                <label class="form-label">
                                                    <i class="ri-router-line me-1"></i>
                                                    {{ translate('Select Gateway') }}
                                                    @if(!$isAdminPlan)
                                                    <span class="text-danger">*</span>
                                                    @endif
                                                </label>
                                                <select name="messages[{{ $channel }}][gateway_id]" class="form-select select2-search" data-placeholder="{{ translate('Choose a gateway') }}" {{ !$isAdminPlan ? 'required' : '' }}>
                                                    <option value=""></option>
                                                    @if(!empty($channelGatewaysGrouped))
                                                        @foreach($channelGatewaysGrouped as $typeName => $typeGateways)
                                                        <optgroup label="{{ $typeName }}">
                                                            @foreach($typeGateways as $gateway)
                                                            <option value="{{ $gateway->id }}" {{ ($message && (($message->gateway_id !== null && $message->gateway_id == $gateway->id) || ($message->android_session_id && $gateway->id === ('android-' . $message->android_session_id)))) ? 'selected' : '' }}>
                                                                {{ $gateway->name }}
                                                                @if($gateway->is_default) ({{ translate('Default') }}) @endif
                                                                @if($gateway->address) - {{ Str::limit($gateway->address, 30) }} @endif
                                                            </option>
                                                            @endforeach
                                                        </optgroup>
                                                        @endforeach
                                                    @else
                                                        @foreach($channelGateways as $gateway)
                                                        <option value="{{ $gateway->id }}" {{ ($message && (($message->gateway_id !== null && $message->gateway_id == $gateway->id) || ($message->android_session_id && $gateway->id === ('android-' . $message->android_session_id)))) ? 'selected' : '' }}>
                                                            {{ $gateway->name }}
                                                            @if($gateway->is_default) ({{ translate('Default') }}) @endif
                                                        </option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                                @if($channelGateways->isEmpty())
                                                <div class="alert alert-warning mt-2 mb-0 py-2">
                                                    <i class="ri-alert-line me-1"></i>
                                                    {{ translate('No gateways available for this channel. Please configure a gateway first.') }}
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                        @else
                                        <div class="col-12">
                                            <div class="alert alert-info mb-0 py-2">
                                                <i class="ri-information-line me-1"></i>
                                                {{ translate('Gateway will be automatically assigned from admin gateways for this channel.') }}
                                            </div>
                                        </div>
                                        @endif

                                        @if($channel === 'email')
                                        {{-- Email Subject --}}
                                        <div class="col-12">
                                            <div class="form-inner">
                                                <label class="form-label">
                                                    <i class="ri-text me-1"></i>
                                                    {{ translate('Subject Line') }} <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" name="messages[{{ $channel }}][subject]" class="form-control" id="email-subject" value="{{ $message->subject ?? old("messages.{$channel}.subject") }}" placeholder="{{ translate('Enter email subject') }}" required>
                                                <small class="text-muted">{{ translate('Use') }} <code>@{{first_name}}</code> {{ translate('for personalization') }}</small>
                                            </div>
                                        </div>

                                        {{-- Sender display name --}}
                                        <div class="col-md-6">
                                            <div class="form-inner">
                                                <label class="form-label">{{ translate('From Name') }}</label>
                                                <input type="text" name="messages[{{ $channel }}][from_name]" class="form-control" value="{{ $message->from_name ?? old("messages.{$channel}.from_name") }}" placeholder="{{ translate('e.g. Acme Sales Team') }}">
                                                <small class="text-muted">{{ translate('Optional. Falls back to gateway default if empty.') }}</small>
                                            </div>
                                        </div>

                                        {{-- Reply-To --}}
                                        <div class="col-md-6">
                                            <div class="form-inner">
                                                <label class="form-label">{{ translate('Reply-To') }}</label>
                                                <input type="email" name="messages[{{ $channel }}][reply_to]" class="form-control" value="{{ $message->reply_to ?? old("messages.{$channel}.reply_to") }}" placeholder="{{ translate('e.g. support@example.com') }}">
                                                <small class="text-muted">{{ translate('Optional. Replies route here instead of the gateway sender.') }}</small>
                                            </div>
                                        </div>
                                        @endif

                                        {{-- Message Content --}}
                                        <div class="col-12">
                                            <div class="form-inner">
                                                <div class="d-flex align-items-center justify-content-between mb-2">
                                                    <label class="form-label mb-0">
                                                        <i class="ri-file-text-line me-1"></i>
                                                        {{ translate('Message Content') }} <span class="text-danger">*</span>
                                                    </label>
                                                    @if($channelTemplates->isNotEmpty())
                                                    <button type="button" class="i-btn btn--sm p-0 bg-transparent text-primary use-template-btn" data-bs-toggle="modal" data-bs-target="#templateModal-{{ $channel }}">
                                                        <i class="ri-layout-fill me-1"></i>{{ translate('Use Template') }}
                                                    </button>
                                                    @endif
                                                </div>
                                                @if($channel === 'email')
                                                {{-- v4.8 — Preserve Original HTML mode toggle (see admin/campaign/messages.blade.php for rationale). --}}
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <small class="text-muted">{{ translate('Use the rich text editor to create beautiful HTML emails') }}</small>
                                                    <label class="form-check form-switch d-inline-flex align-items-center gap-2 mb-0" style="cursor:pointer;">
                                                        <input class="form-check-input preserve-html-toggle" type="checkbox" id="preserve-html-{{ $channel }}" data-channel="{{ $channel }}">
                                                        <span class="small text-muted">{{ translate('Preserve original HTML (BeeFree / imported)') }}</span>
                                                    </label>
                                                </div>
                                                <textarea name="messages[{{ $channel }}][content]" id="email-editor-{{ $channel }}" class="form-control email-editor" rows="15" placeholder="{{ translate('Enter your email content here...') }}">{{ $message->content ?? old("messages.{$channel}.content") }}</textarea>
                                                <iframe id="email-raw-preview-{{ $channel }}" class="email-preview-frame" style="display:none; width:100%; min-height:520px; border:1px solid #e5e7eb; border-radius:8px; background:#ffffff; margin-top:8px;"></iframe>
                                                @elseif($channel === 'sms')
                                                <textarea name="messages[{{ $channel }}][content]" id="sms-content" class="form-control" rows="6" placeholder="{{ translate('Enter your SMS message here...') }}" required>{{ $message->content ?? old("messages.{$channel}.content") }}</textarea>
                                                <div class="d-flex justify-content-between mt-2 small">
                                                    <span class="text-muted">
                                                        {{ translate('Characters') }}: <strong class="sms-char-count">0</strong>
                                                        <span class="text-muted mx-1">|</span>
                                                        {{ translate('Segments') }}: <strong class="sms-segment-count">1</strong>
                                                    </span>
                                                    <span class="text-muted">{{ translate('Max recommended: 160 chars per segment') }}</span>
                                                </div>
                                                @else
                                                <textarea name="messages[{{ $channel }}][content]" id="whatsapp-content" class="form-control" rows="8" placeholder="{{ translate('Enter your WhatsApp message here...') }}" required>{{ $message->content ?? old("messages.{$channel}.content") }}</textarea>
                                                @endif
                                            </div>
                                        </div>

                                        @if($channel === 'whatsapp')
                                        {{-- WhatsApp Template Selection --}}
                                        <div class="col-12">
                                            <div class="form-inner">
                                                <label class="form-label">
                                                    <i class="ri-layout-2-line me-1"></i>
                                                    {{ translate('Cloud API Template') }} <small class="text-muted">({{ translate('Optional - for Cloud API only') }})</small>
                                                </label>
                                                <select name="messages[{{ $channel }}][template_id]" class="form-select">
                                                    <option value="">{{ translate('No template - send as text message') }}</option>
                                                    @foreach($channelTemplates as $template)
                                                    <option value="{{ $template->id }}" {{ ($message && $message->template_id == $template->id) ? 'selected' : '' }}>
                                                        {{ $template->name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                                <small class="text-muted">{{ translate('For Meta Cloud API, select an approved template for better deliverability') }}</small>
                                            </div>
                                        </div>
                                        @endif
                                    </div>

                                    {{-- Personalization Variables --}}
                                    <div class="mt-4 pt-4 border-top">
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <h6 class="mb-0">
                                                <i class="ri-user-settings-line me-1"></i>
                                                {{ translate('Personalization Variables') }}
                                            </h6>
                                            <small class="text-muted">{{ translate('Click to insert at cursor position') }}</small>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button type="button" class="i-btn btn--dark outline btn--sm insert-var" data-var="@{{first_name}}" data-channel="{{ $channel }}">
                                                <i class="ri-user-line me-1"></i>@{{first_name}}
                                            </button>
                                            <button type="button" class="i-btn btn--dark outline btn--sm insert-var" data-var="@{{last_name}}" data-channel="{{ $channel }}">
                                                <i class="ri-user-line me-1"></i>@{{last_name}}
                                            </button>
                                            <button type="button" class="i-btn btn--dark outline btn--sm insert-var" data-var="@{{full_name}}" data-channel="{{ $channel }}">
                                                <i class="ri-user-2-line me-1"></i>@{{full_name}}
                                            </button>
                                            <button type="button" class="i-btn btn--dark outline btn--sm insert-var" data-var="@{{email}}" data-channel="{{ $channel }}">
                                                <i class="ri-mail-line me-1"></i>@{{email}}
                                            </button>
                                            <button type="button" class="i-btn btn--dark outline btn--sm insert-var" data-var="@{{phone}}" data-channel="{{ $channel }}">
                                                <i class="ri-phone-line me-1"></i>@{{phone}}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ route('user.campaign.edit', $campaign->id) }}" class="i-btn btn--dark outline btn--md">
                            <i class="ri-arrow-left-line me-1"></i> {{ translate('Back') }}
                        </a>
                        <button type="submit" class="i-btn btn--primary btn--md">
                            {{ translate('Continue to Review') }} <i class="ri-arrow-right-line ms-1"></i>
                        </button>
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
                                    <div class="campaign-step active">
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

                        {{-- Campaign Summary --}}
                        <div class="card">
                            <div class="form-header">
                                <h4 class="card-title">{{ translate('Campaign Summary') }}</h4>
                            </div>
                            <div class="card-body pt-0">
                                <div class="summary-item">
                                    <small class="text-muted">{{ translate('Campaign Name') }}</small>
                                    <div class="fw-semibold">{{ $campaign->name }}</div>
                                </div>
                                <div class="summary-item">
                                    <small class="text-muted">{{ translate('Contact Group') }}</small>
                                    <div class="fw-semibold">{{ $campaign->contactGroup?->name ?? '-' }}</div>
                                </div>
                                <div class="summary-item">
                                    <small class="text-muted">{{ translate('Total Contacts') }}</small>
                                    <div class="fw-semibold">{{ number_format($campaign->total_contacts) }}</div>
                                </div>
                                <div class="summary-item">
                                    <small class="text-muted">{{ translate('Type') }}</small>
                                    <div class="fw-semibold">{{ $campaign->type->label() }}</div>
                                </div>
                                @if($campaign->schedule_at)
                                <div class="summary-item">
                                    <small class="text-muted">{{ translate('Schedule') }}</small>
                                    <div class="fw-semibold">{{ $campaign->schedule_at->format('M d, Y H:i') }}</div>
                                </div>
                                @endif
                            </div>
                        </div>

                        {{-- Channel Distribution --}}
                        <div class="card mt-4">
                            <div class="form-header">
                                <h4 class="card-title">{{ translate('Channel Distribution') }}</h4>
                            </div>
                            <div class="card-body mt-1">
                                @foreach($channelDistribution['channels'] as $channel => $count)
                                @if(in_array($channel, $campaign->channels))
                                @php $channelEnum = \App\Enums\Campaign\CampaignChannel::tryFrom($channel); @endphp
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="d-flex align-items-center gap-2">
                                        <span class="channel-icon-sm channel-{{ $channel }}">
                                            <i class="{{ $channelEnum->icon() }}"></i>
                                        </span>
                                        {{ $channelEnum->label() }}
                                    </span>
                                    <span class="fw-semibold">{{ number_format($count) }}</span>
                                </div>
                                @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>

{{-- Template Modals --}}
@foreach($campaign->channels as $channel)
@php $channelTemplates = $templates[$channel] ?? collect(); @endphp
<div class="modal fade" id="templateModal-{{ $channel }}" tabindex="-1" aria-labelledby="templateModalLabel-{{ $channel }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-bottom align-items-start">
                <div class="d-flex align-items-center gap-3">
                    <div class="modal-icon {{ $channel }}">
                        <i class="{{ $channel === 'email' ? 'ri-mail-line' : ($channel === 'sms' ? 'ri-message-2-line' : 'ri-whatsapp-line') }}"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="templateModalLabel-{{ $channel }}">
                            {{ translate('Select') }} {{ ucfirst($channel) }} {{ translate('Template') }}
                        </h5>
                        <small class="text-muted">{{ translate('Choose a template to use for your message') }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="{{ translate('Close') }}"></button>
            </div>
            <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                @if($channelTemplates->isNotEmpty())
                <div class="row g-3">
                    @foreach($channelTemplates as $template)
                    @php
                        $templateContent = $template->template_data['mail_body']
                            ?? $template->template_data['body']
                            ?? $template->template_data['message']
                            ?? $template->template_data['content']
                            ?? $template->template_data['text']
                            ?? '';
                        $templateSubject = $template->template_data['subject'] ?? '';
                    @endphp
                    <div class="col-md-6">
                        <div class="template-card-new"
                             data-channel="{{ $channel }}"
                             data-template-id="{{ $template->id }}"
                             data-template-name="{{ $template->name }}"
                             data-template-content="{{ $templateContent }}"
                             data-template-subject="{{ $templateSubject }}">
                            <div class="template-card-header-new">
                                <h6 class="template-name mb-1">{{ $template->name }}</h6>
                                @if($channel === 'email' && $templateSubject)
                                <small class="text-muted d-block"><strong>{{ translate('Subject') }}:</strong> {{ Str::limit($templateSubject, 40) }}</small>
                                @endif
                            </div>
                            <div class="template-card-preview">
                                @if($templateContent)
                                <p class="preview-text">{{ Str::limit(strip_tags($templateContent), 120) }}</p>
                                @else
                                <p class="preview-text text-muted fst-italic">{{ translate('No preview available') }}</p>
                                @endif
                            </div>
                            <div class="template-card-action">
                                <button type="button" class="i-btn btn--primary btn--sm w-100 select-template-btn">
                                    <i class="ri-check-line me-1"></i> {{ translate('Use This Template') }}
                                </button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="empty-templates text-center py-5">
                    <div class="empty-icon mb-3">
                        <i class="{{ $channel === 'email' ? 'ri-mail-line' : ($channel === 'sms' ? 'ri-message-2-line' : 'ri-whatsapp-line') }}"></i>
                    </div>
                    <h5>{{ translate('No Templates Found') }}</h5>
                    <p class="text-muted mb-0">{{ translate('Create templates first to use them in campaigns') }}</p>
                </div>
                @endif
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">
                    <i class="ri-close-line me-1"></i>{{ translate('Cancel') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endforeach

@endsection

@push("script-include")
<script src="{{asset('assets/theme/global/js/select2.min.js')}}"></script>
@endpush

@push('script-push')
<script>
document.addEventListener('DOMContentLoaded', function() {

    // Initialize Select2
    if (typeof select2_search === 'function') {
        select2_search($('.select2-search').data('placeholder'));
    }

    // Initialize CKEditor for email
    @if(in_array('email', $campaign->channels))
    if (typeof ck_editor === 'function') {
        ck_editor('#email-editor-email');
    }
    @endif

    // Helper to get CKEditor instance for email
    function getEmailEditor() {
        if (typeof editors !== 'undefined' && editors['#email-editor-email']) {
            return editors['#email-editor-email'];
        }
        return null;
    }

    // v4.8 — Preserve Original HTML mode (mirror of admin/campaign/messages.blade.php).
    function refreshRawPreview(channel) {
        var iframe = document.getElementById('email-raw-preview-' + channel);
        var textarea = document.getElementById('email-editor-' + channel);
        if (!iframe || !textarea) return;
        var doc = iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document);
        if (!doc) return;
        var body = textarea.value || '';
        var html = '<!DOCTYPE html><html><head><meta charset="utf-8"><base target="_blank"><style>html,body{margin:0;padding:16px;background:#ffffff;color:#111827;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;}p{margin:0 0 8px 0;}p:last-child{margin-bottom:0;}img{max-width:100%;height:auto;}a{color:#2563eb;}</style></head><body>' + body + '</body></html>';
        doc.open(); doc.write(html); doc.close();
    }

    function setPreserveHtmlMode(channel, on) {
        var textarea = document.getElementById('email-editor-' + channel);
        var iframe   = document.getElementById('email-raw-preview-' + channel);
        var toggle   = document.getElementById('preserve-html-' + channel);
        if (!textarea) return;
        if (on) {
            var editorInstance = getEmailEditor();
            if (editorInstance && typeof editorInstance.destroy === 'function') {
                try { editorInstance.destroy(); } catch (e) {}
                if (typeof editors !== 'undefined') { delete editors['#email-editor-' + channel]; }
            }
            textarea.style.display = 'block';
            textarea.style.fontFamily = 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace';
            textarea.style.fontSize = '12px';
            if (iframe) {
                iframe.style.display = 'block';
                refreshRawPreview(channel);
                textarea.removeEventListener('input', textarea._xsRawPreview || null);
                textarea._xsRawPreview = function () { refreshRawPreview(channel); };
                textarea.addEventListener('input', textarea._xsRawPreview);
            }
        } else {
            textarea.style.fontFamily = '';
            textarea.style.fontSize = '';
            if (iframe) iframe.style.display = 'none';
            if (typeof ck_editor === 'function' && (typeof editors === 'undefined' || !editors['#email-editor-' + channel])) {
                try { ck_editor('#email-editor-' + channel); } catch (e) { console.error('CKEditor init failed', e); }
            }
        }
        if (toggle) toggle.checked = !!on;
    }

    document.querySelectorAll('.preserve-html-toggle').forEach(function (t) {
        t.addEventListener('change', function () {
            setPreserveHtmlMode(this.dataset.channel, this.checked);
        });
    });

    function isImportedHtml(html) {
        if (!html) return false;
        if (/^\s*<!doctype\s+html/i.test(html)) return true;
        if (html.indexOf('raw-html-embed') !== -1) return true;
        if (html.length > 5000) return true;
        return false;
    }
    document.querySelectorAll('textarea.email-editor').forEach(function (ta) {
        var channel = ta.id.replace('email-editor-', '');
        if (isImportedHtml(ta.value)) {
            setPreserveHtmlMode(channel, true);
        }
    });

    // Template Selection
    document.querySelectorAll('.select-template-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const card = this.closest('.template-card-new');
            if (!card) return;

            const channel = card.dataset.channel;
            const content = card.dataset.templateContent || '';
            const subject = card.dataset.templateSubject || '';

            if (channel === 'email') {
                const subjectInput = document.getElementById('email-subject');
                if (subjectInput && subject) {
                    subjectInput.value = subject;
                }
                if (isImportedHtml(content)) {
                    const textarea = document.getElementById('email-editor-' + channel);
                    if (textarea) textarea.value = content;
                    setPreserveHtmlMode(channel, true);
                } else {
                    const editorInstance = getEmailEditor();
                    if (editorInstance) {
                        editorInstance.setData(content);
                    } else {
                        const textarea = document.getElementById('email-editor-email');
                        if (textarea) textarea.value = content;
                    }
                }
            } else if (channel === 'sms') {
                const textarea = document.getElementById('sms-content');
                if (textarea) {
                    textarea.value = content;
                    textarea.dispatchEvent(new Event('input'));
                }
            } else if (channel === 'whatsapp') {
                const textarea = document.getElementById('whatsapp-content');
                if (textarea) textarea.value = content;
            }

            // Close modal
            const modalEl = document.getElementById('templateModal-' + channel);
            if (modalEl) {
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }
        });
    });

    // Insert personalization variable
    document.querySelectorAll('.insert-var').forEach(btn => {
        btn.addEventListener('click', function() {
            const variable = this.dataset.var;
            const channel = this.dataset.channel;

            if (channel === 'email') {
                const editorInstance = getEmailEditor();
                if (editorInstance) {
                    editorInstance.model.change(writer => {
                        editorInstance.model.insertContent(writer.createText(variable));
                    });
                    return;
                }
            }

            const activePane = document.querySelector('#content-' + channel);
            if (!activePane) return;

            const textarea = activePane.querySelector('textarea[name*="content"]');
            if (textarea) {
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const text = textarea.value;
                textarea.value = text.substring(0, start) + variable + text.substring(end);
                textarea.focus();
                textarea.selectionStart = textarea.selectionEnd = start + variable.length;
                textarea.dispatchEvent(new Event('input'));
            }
        });
    });

    // SMS Character Counter
    const smsTextarea = document.getElementById('sms-content');
    if (smsTextarea) {
        const charCount = document.querySelector('.sms-char-count');
        const segmentCount = document.querySelector('.sms-segment-count');

        function updateSmsCount() {
            const text = smsTextarea.value;
            const length = text.length;
            if (charCount) charCount.textContent = length;

            const isUnicode = /[^\x00-\x7F]/.test(text);
            let segments;
            if (isUnicode) {
                segments = length <= 70 ? 1 : Math.ceil(length / 67);
            } else {
                segments = length <= 160 ? 1 : Math.ceil(length / 153);
            }

            if (segmentCount) {
                segmentCount.textContent = segments;
                if (segments > 3) {
                    segmentCount.classList.add('text-warning');
                } else {
                    segmentCount.classList.remove('text-warning');
                }
            }
        }

        smsTextarea.addEventListener('input', updateSmsCount);
        updateSmsCount();
    }
});
</script>
@endpush

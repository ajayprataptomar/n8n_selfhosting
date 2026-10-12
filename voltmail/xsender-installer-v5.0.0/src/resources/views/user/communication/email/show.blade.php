@push("style-push")
<style>
    .email-show-header { background: var(--color-gradient); padding: 20px 24px; color: var(--color-primary-text); border-radius: 10px 10px 0 0; }
    .email-show-header h4 { color: var(--color-primary-text); }
    .email-show-sender-avatar { width: 32px; height: 32px; background: var(--color-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .email-show-sender-avatar i { color: var(--color-primary-text); font-size: 0.85rem; }
    .email-show-strip { padding: 12px 24px; background: var(--card-bg); border-bottom: 1px solid var(--border-color, rgba(0,0,0,0.08)); font-size: 0.825rem; }
    .email-show-card { border-radius: 10px; overflow: hidden; border: 1px solid var(--border-color, rgba(0,0,0,0.08)); }
    .email-show-sidebar-card { border-radius: 10px; border: 1px solid var(--border-color, rgba(0,0,0,0.08)); }
    .email-show-sidebar-title { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary, #8c8c8c); }
    .email-show-detail-label { font-size: 0.825rem; color: var(--text-secondary, #6b7280); }
    .email-show-detail-value { font-size: 0.825rem; font-weight: 500; color: var(--text-primary, #15192c); }
    .email-show-timeline-dot { width: 8px; height: 8px; border-radius: 50%; margin-top: 5px; flex-shrink: 0; z-index: 1; }
    .email-show-timeline-line { position: absolute; left: 3.5px; top: 13px; bottom: 0; width: 1px; background: var(--border-color, rgba(0,0,0,0.08)); }
    .email-show-timeline-label { font-size: 0.75rem; color: var(--text-secondary, #6b7280); }
    .email-show-timeline-date { font-size: 0.825rem; color: var(--text-primary, #15192c); }
    .email-show-attach-chip { background: var(--card-bg); border: 1px solid var(--border-color, rgba(0,0,0,0.08)); font-size: 0.775rem; transition: all 0.15s; }
    .email-show-attach-chip:hover { border-color: var(--color-primary); background: var(--color-primary-light); }
    .email-show-img-thumb { width: 120px; border: 1px solid var(--border-color, rgba(0,0,0,0.08)); background: var(--card-bg); }
    .email-show-img-footer { background: var(--site-bg, #f9f9fc); border-top: 1px solid var(--border-color, rgba(0,0,0,0.08)); }
    .email-show-empty { background: var(--site-bg, #f9f9fc); }
    .email-show-body-wrap { background: var(--card-bg, #fff); }
    .email-show-fail-alert { background: var(--color-danger-light); border: 1px solid var(--color-danger); border-radius: 8px; }
    .email-show-fail-icon { width: 36px; height: 36px; background: var(--color-danger); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
</style>
@endpush
@extends('user.layouts.app')
@section('panel')
    @php
        $emailSubject = @$log->message->subject ? replaceContactVariables($log->contact, $log->message->subject) : null;
        $emailTo = @$log->contact->email_contact;
        $emailFrom = $log->gatewayable ? $log->gatewayable->address : null;
        $emailFromName = $log->gatewayable ? $log->gatewayable->name : null;
        $attachments = @$log->message->file_info['attachments'] ?? [];
        $isFailed = \App\Enums\System\CommunicationStatusEnum::FAIL->value == $log->status->value;
        $isDelivered = \App\Enums\System\CommunicationStatusEnum::DELIVERED->value == $log->status->value;
        $sentDate = $log->sent_at ? \Carbon\Carbon::parse($log->sent_at) : ($log->created_at ? \Carbon\Carbon::parse($log->created_at) : null);
    @endphp
    <main class="main-body">
        <div class="container-fluid px-0 main-content">
            <div class="page-header">
                <div class="page-header-left">
                    <h2>{{ $title }}</h2>
                    <div class="breadcrumb-wrapper">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="{{ route("user.dashboard") }}">{{ translate("Dashboard") }}</a>
                                </li>
                                <li class="breadcrumb-item">
                                    <a href="{{ route("user.communication.email.index") }}">{{ translate("Email Dispatch Logs") }}</a>
                                </li>
                                <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
                            </ol>
                        </nav>
                    </div>
                </div>
                <div class="page-header-right d-flex gap-2">
                    <button class="i-btn btn--primary btn--sm resend-email-log"
                            data-url="{{ route('user.communication.email.resend', $log->id) }}">
                        <i class="ri-mail-send-line"></i> {{ translate("Resend") }}
                    </button>
                    <a href="{{ route('user.communication.email.index') }}" class="i-btn btn--dark outline btn--sm">
                        <i class="ri-arrow-left-line"></i> {{ translate("Back") }}
                    </a>
                </div>
            </div>

            <div class="row g-4">
                {{-- Left Column: Email Preview --}}
                <div class="col-lg-8">
                    @if($isFailed && $log->response_message)
                    <div class="alert d-flex align-items-start gap-3 mb-4 email-show-fail-alert" role="alert">
                        <div class="email-show-fail-icon">
                            <i class="ri-close-line" style="color: #fff; font-size: 1.1rem;"></i>
                        </div>
                        <div>
                            <strong style="color: var(--color-danger);">{{ translate("Delivery Failed") }}</strong>
                            <p class="mb-0 mt-1 text-break" style="font-size: 0.875rem;">{{ $log->response_message }}</p>
                        </div>
                    </div>
                    @endif

                    <div class="card email-show-card">
                        {{-- Header --}}
                        <div class="email-show-header">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div style="flex: 1; min-width: 0;">
                                    <h4 class="mb-1" style="font-size: 1.15rem; font-weight: 600; word-break: break-word;">
                                        {{ $emailSubject ?? translate("No Subject") }}
                                    </h4>
                                    <div style="font-size: 0.825rem; opacity: 0.85;">
                                        {{ translate("To") }}: {{ $emailTo ?? translate("N/A") }}
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                    {{ $log->status->badge() }}
                                    @if(@$log->message->main_body)
                                    <a href="{{ route('user.communication.email.show', $log->id) }}?raw=1" target="_blank" class="d-flex align-items-center text-decoration-none" style="font-size: 0.9rem; color: var(--color-primary-text); opacity: 0.85;" title="{{ translate('Open in New Tab') }}">
                                        <i class="ri-external-link-line"></i>
                                    </a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Sender Strip --}}
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 email-show-strip">
                            <div class="d-flex align-items-center gap-2">
                                <div class="email-show-sender-avatar">
                                    <i class="ri-mail-send-line"></i>
                                </div>
                                <div class="lh-sm">
                                    @if($log->gatewayable)
                                        @if($log->gatewayable->user_id == null)
                                            <span class="fw-semibold">{{ translate("Admin Gateway") }}</span>
                                        @else
                                            <span class="fw-semibold">{{ $emailFromName }}</span>
                                            @if($emailFrom)
                                            <span class="text-muted">&lt;{{ $emailFrom }}&gt;</span>
                                            @endif
                                        @endif
                                    @else
                                        <span class="text-muted">{{ translate("N/A") }}</span>
                                    @endif
                                </div>
                                @if($log->gatewayable)
                                <span class="i-badge pill info-soft" style="font-size: 0.65rem;">{{ strtoupper($log->gatewayable->type) }}</span>
                                @endif
                            </div>
                            <span class="text-muted" style="font-size: 0.8rem;">{{ $sentDate ? $sentDate->toDayDateTimeString() : '' }}</span>
                        </div>

                        {{-- Attachments --}}
                        @if(!empty($attachments))
                        <div class="email-show-strip">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="ri-attachment-2 text-muted" style="font-size: 0.85rem;"></i>
                                <span class="text-muted" style="font-size: 0.775rem; font-weight: 500;">{{ count($attachments) }} {{ translate("attachment") }}{{ count($attachments) > 1 ? 's' : '' }}</span>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                            @foreach($attachments as $attachment)
                                @php
                                    $fileName = \Illuminate\Support\Arr::get($attachment, 'name', 'file');
                                    $fileSize = \Illuminate\Support\Arr::get($attachment, 'size', 0);
                                    $storedName = \Illuminate\Support\Arr::get($attachment, 'stored_name');
                                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                                    $sizeFormatted = $fileSize >= 1048576 ? round($fileSize / 1048576, 1) . ' MB' : round($fileSize / 1024, 1) . ' KB';
                                    $isImage = in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp']);
                                    $isPdf = $ext === 'pdf';
                                    $iconClass = match(true) {
                                        $isPdf => 'ri-file-pdf-2-line text-danger',
                                        in_array($ext, ['doc', 'docx']) => 'ri-file-word-line text-primary',
                                        in_array($ext, ['xls', 'xlsx', 'csv']) => 'ri-file-excel-line text-success',
                                        $isImage => 'ri-image-line text-info',
                                        in_array($ext, ['zip', 'rar']) => 'ri-folder-zip-line text-warning',
                                        default => 'ri-file-line text-muted',
                                    };
                                    $viewUrl = $storedName ? route('user.communication.email.attachment.download', [$log->id, $storedName]) . '?view=1' : null;
                                    $downloadUrl = $storedName ? route('user.communication.email.attachment.download', [$log->id, $storedName]) : null;
                                @endphp
                                @if($storedName)
                                    @if($isImage)
                                    <div class="position-relative rounded overflow-hidden email-show-img-thumb">
                                        <a href="{{ $viewUrl }}" target="_blank" style="display: block;">
                                            <img src="{{ $viewUrl }}" alt="{{ $fileName }}" style="width: 120px; height: 80px; object-fit: cover; display: block;">
                                        </a>
                                        <div class="d-flex align-items-center justify-content-between px-2 py-1 email-show-img-footer">
                                            <span class="text-truncate" style="font-size: 0.675rem; max-width: 70px;" title="{{ $fileName }}">{{ $fileName }}</span>
                                            <a href="{{ $downloadUrl }}" title="{{ translate('Download') }}" class="text-muted" style="font-size: 0.8rem; line-height: 1;"><i class="ri-download-2-line"></i></a>
                                        </div>
                                    </div>
                                    @else
                                    <div class="d-inline-flex align-items-center gap-2 rounded-pill px-3 py-1 email-show-attach-chip">
                                        <i class="{{ $iconClass }}" style="font-size: 0.9rem;"></i>
                                        <span class="text-truncate" style="max-width: 120px;">{{ $fileName }}</span>
                                        <span class="text-muted">{{ $sizeFormatted }}</span>
                                        @if($isPdf)
                                        <a href="{{ $viewUrl }}" target="_blank" class="text-info" title="{{ translate('View') }}" style="line-height: 1;"><i class="ri-eye-line"></i></a>
                                        @endif
                                        <a href="{{ $downloadUrl }}" class="text-muted" title="{{ translate('Download') }}" style="line-height: 1;"><i class="ri-download-2-line"></i></a>
                                    </div>
                                    @endif
                                @else
                                <span class="d-inline-flex align-items-center gap-2 rounded-pill px-3 py-1 email-show-attach-chip" style="opacity: 0.5;">
                                    <i class="{{ $iconClass }}" style="font-size: 0.9rem;"></i>
                                    <span class="text-truncate" style="max-width: 140px;">{{ $fileName }}</span>
                                    <span class="text-danger">{{ translate("Missing") }}</span>
                                </span>
                                @endif
                            @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- Email Body --}}
                        @if(@$log->message->main_body)
                        <div class="email-show-body-wrap">
                            <iframe id="emailPreview"
                                    class="w-100 border-0"
                                    style="min-height: 500px;"
                                    sandbox="allow-same-origin"
                                    srcdoc="{!! htmlspecialchars('<style>body{margin:0;padding:24px 28px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;line-height:1.6;color:#333;}</style>' . replaceContactVariables($log->contact, $log->message->main_body)) !!}">
                            </iframe>
                        </div>
                        @else
                        <div class="text-center py-5 email-show-empty">
                            <i class="ri-mail-close-line d-block mb-2" style="font-size: 2.5rem; opacity: 0.3;"></i>
                            <p class="text-muted mb-0">{{ translate("No email body content available") }}</p>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Right Column: Details --}}
                <div class="col-lg-4">
                    {{-- Delivery Details --}}
                    <div class="card mb-4 email-show-sidebar-card">
                        <div class="card-body" style="padding: 20px;">
                            <h6 class="fw-semibold mb-3 email-show-sidebar-title">{{ translate("Delivery Details") }}</h6>
                            <div class="d-flex flex-column gap-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="email-show-detail-label">{{ translate("Status") }}</span>
                                    {{ $log->status->badge() }}
                                </div>
                                @if($log->gatewayable)
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="email-show-detail-label">{{ translate("Gateway") }}</span>
                                    <span class="email-show-detail-value">
                                        @if($log->gatewayable->user_id == null)
                                            {{ translate("Admin Gateway") }}
                                        @else
                                            {{ $log->gatewayable->name }}
                                        @endif
                                    </span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="email-show-detail-label">{{ translate("Type") }}</span>
                                    <span class="i-badge pill info-soft" style="font-size: 0.7rem;">{{ strtoupper($log->gatewayable->type) }}</span>
                                </div>
                                @endif
                                @if($log->campaign)
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="email-show-detail-label">{{ translate("Campaign") }}</span>
                                    <span class="email-show-detail-value">{{ $log->campaign->name }}</span>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Timeline --}}
                    <div class="card email-show-sidebar-card">
                        <div class="card-body" style="padding: 20px;">
                            <h6 class="fw-semibold mb-3 email-show-sidebar-title">{{ translate("Timeline") }}</h6>
                            <div class="d-flex flex-column gap-0">
                                @if($log->created_at)
                                <div class="d-flex align-items-start gap-3 position-relative" style="padding-bottom: 16px;">
                                    <div class="email-show-timeline-dot" style="background: var(--text-secondary, #d9d9d9);"></div>
                                    @if($log->sent_at || $log->processed_at)
                                    <div class="email-show-timeline-line"></div>
                                    @endif
                                    <div class="lh-sm">
                                        <span class="d-block email-show-timeline-label">{{ translate("Created") }}</span>
                                        <span class="email-show-timeline-date">{{ \Carbon\Carbon::parse($log->created_at)->toDayDateTimeString() }}</span>
                                    </div>
                                </div>
                                @endif
                                @if($log->sent_at)
                                <div class="d-flex align-items-start gap-3 position-relative" style="padding-bottom: 16px;">
                                    <div class="email-show-timeline-dot" style="background: var(--color-primary);"></div>
                                    @if($log->processed_at)
                                    <div class="email-show-timeline-line"></div>
                                    @endif
                                    <div class="lh-sm">
                                        <span class="d-block email-show-timeline-label">{{ translate("Sent") }}</span>
                                        <span class="email-show-timeline-date">{{ \Carbon\Carbon::parse($log->sent_at)->toDayDateTimeString() }}</span>
                                    </div>
                                </div>
                                @endif
                                @if($log->processed_at)
                                <div class="d-flex align-items-start gap-3">
                                    <div class="email-show-timeline-dot" style="background: {{ $isFailed ? 'var(--color-danger)' : 'var(--color-success)' }};"></div>
                                    <div class="lh-sm">
                                        <span class="d-block email-show-timeline-label">{{ $isFailed ? translate("Failed") : translate("Delivered") }}</span>
                                        <span class="email-show-timeline-date">{{ \Carbon\Carbon::parse($log->processed_at)->toDayDateTimeString() }}</span>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
@endsection

@section('modal')
<div class="modal fade actionModal" id="resendEmailLog" tabindex="-1" aria-labelledby="resendEmailLog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header text-start">
                <span class="action-icon success">
                    <i class="bi bi-send-check"></i>
                </span>
            </div>
            <form id="dispatchLogResend" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="action-message">
                        <h5>{{ translate("Are you sure you want to resend this email?") }}</h5>
                        <p>{{ translate("A new email will be dispatched to the same recipient. 1 email credit will be deducted.") }}</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="i-btn btn--dark outline btn--lg" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
                    <button type="submit" class="i-btn btn--primary btn--lg" data-bs-dismiss="modal">{{ translate("Resend") }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('script-push')
<script>
    "use strict";

    document.addEventListener('DOMContentLoaded', function() {
        var iframe = document.getElementById('emailPreview');
        if (iframe) {
            iframe.addEventListener('load', function() {
                try {
                    var doc = iframe.contentDocument;
                    setTimeout(function() {
                        iframe.style.height = Math.max(doc.body.scrollHeight + 10, 400) + 'px';
                    }, 100);
                } catch(e) {}
            });
        }
    });

    $('.resend-email-log').on('click', function() {
        const modal = $('#resendEmailLog');
        var form = modal.find('form[id=dispatchLogResend]');
        form.attr('action', $(this).data('url'));
        modal.modal('show');
    });
</script>
@endpush

@extends('user.layouts.app')

@push('style-include')
@include('user.partials.lead-stat-styles')
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
                            <li class="breadcrumb-item"><a href="{{ route('user.campaign.index') }}">{{ translate('Campaigns') }}</a></li>
                            <li class="breadcrumb-item active">{{ translate('Details') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                @if($campaign->canEdit())
                <a href="{{ route('user.campaign.edit', $campaign->id) }}" class="i-btn btn--primary outline btn--md">
                    <i class="ri-pencil-line"></i> {{ translate('Edit') }}
                </a>
                @endif
                <a href="{{ route('user.campaign.index') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-arrow-left-line"></i> {{ translate('Back') }}
                </a>
            </div>
        </div>

        {{-- Health Banner (only when campaign is RUNNING) --}}
        @include('partials.campaign.health_banner', [
            'campaign'        => $campaign,
            'diagnosticUrl'   => route('user.campaign.diagnostic', $campaign->id),
            'retryOrphansUrl' => route('user.campaign.retry-orphans', $campaign->id),
        ])

        <!-- Stats -->
        <div class="lead-stats-grid mb-4">
            <div class="lead-stat-card stat-primary">
                <div class="lead-stat-icon icon-primary">
                    <i class="ri-group-line"></i>
                </div>
                <div class="lead-stat-content">
                    <h3>{{ number_format($statistics['total_contacts'] ?? $campaign->total_contacts) }}</h3>
                    <p>{{ translate('Total Contacts') }}</p>
                </div>
            </div>
            <div class="lead-stat-card stat-info">
                <div class="lead-stat-icon icon-info">
                    <i class="ri-send-plane-line"></i>
                </div>
                <div class="lead-stat-content">
                    <h3>{{ number_format($statistics['sent'] ?? 0) }}</h3>
                    <p>{{ translate('Sent') }}</p>
                </div>
            </div>
            <div class="lead-stat-card stat-success">
                <div class="lead-stat-icon icon-success">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
                <div class="lead-stat-content">
                    <h3>{{ number_format($statistics['delivered'] ?? 0) }}</h3>
                    <p>{{ translate('Delivered') }}</p>
                </div>
            </div>
            <div class="lead-stat-card stat-danger">
                <div class="lead-stat-icon icon-danger">
                    <i class="ri-close-circle-line"></i>
                </div>
                <div class="lead-stat-content">
                    <h3>{{ number_format($statistics['failed'] ?? 0) }}</h3>
                    <p>{{ translate('Failed') }}</p>
                </div>
            </div>
            @if(($statistics['unsubscribed'] ?? 0) > 0)
            <div class="lead-stat-card" style="border-left: 3px solid #dc2626;">
                <div class="lead-stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #dc2626;">
                    <i class="ri-user-unfollow-line"></i>
                </div>
                <div class="lead-stat-content">
                    <h3>{{ number_format($statistics['unsubscribed']) }}</h3>
                    <p>{{ translate('Unsubscribed') }}</p>
                </div>
            </div>
            @endif
        </div>

        <!-- Progress -->
        @php $progress = $campaign->getProgressPercentage(); @endphp
        <div class="card mb-4">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-medium">{{ translate('Campaign Progress') }}</span>
                    <span class="text-muted">{{ $progress }}%</span>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $progress }}%"></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">{{ translate('Campaign Details') }}</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label text-muted mb-1">{{ translate('Campaign Name') }}</label>
                                <p class="fw-semibold mb-0">{{ $campaign->name }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted mb-1">{{ translate('Status') }}</label>
                                <p class="mb-0">
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
                                </p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted mb-1">{{ translate('Contact Group') }}</label>
                                <p class="fw-semibold mb-0">{{ $campaign->contactGroup?->name ?? translate('N/A') }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted mb-1">{{ translate('Campaign Type') }}</label>
                                <p class="fw-semibold mb-0">{{ ucfirst($campaign->type->value ?? $campaign->type) }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted mb-1">{{ translate('Scheduled At') }}</label>
                                <p class="fw-semibold mb-0">
                                    @if($campaign->schedule_at)
                                        {{ $campaign->schedule_at->format('M d, Y h:i A') }}
                                        <small class="text-muted d-block">{{ $campaign->timezone ?? config('app.timezone') }}</small>
                                    @else
                                        <span class="text-muted">{{ translate('Not scheduled') }}</span>
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted mb-1">{{ translate('Created') }}</label>
                                <p class="fw-semibold mb-0">{{ $campaign->created_at->format('M d, Y h:i A') }}</p>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted mb-1">{{ translate('Channels') }}</label>
                                <div class="d-flex gap-2 flex-wrap">
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
                                            <span class="i-badge {{ $badgeColor }}-soft pill">
                                                <i class="{{ $channelEnum->icon() }} me-1"></i>{{ $channelEnum->label() }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                            @if($campaign->description)
                            <div class="col-12">
                                <label class="form-label text-muted mb-1">{{ translate('Description') }}</label>
                                <p class="mb-0">{{ $campaign->description }}</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Messages Preview -->
                @if($campaign->messages->count() > 0)
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">{{ translate('Messages') }}</h4>
                        @if($campaign->canEdit())
                        <a href="{{ route('user.campaign.messages', $campaign->id) }}" class="i-btn btn--primary btn--sm">
                            <i class="ri-settings-3-line"></i> {{ translate('Configure') }}
                        </a>
                        @endif
                    </div>
                    <div class="card-body px-0 pt-0">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>{{ translate('Channel') }}</th>
                                        <th>{{ translate('Subject/Preview') }}</th>
                                        <th>{{ translate('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($campaign->messages as $message)
                                    <tr>
                                        <td>
                                            @php
                                                $msgChannel = $message->channel instanceof \App\Enums\Campaign\CampaignChannel
                                                    ? $message->channel
                                                    : \App\Enums\Campaign\CampaignChannel::tryFrom($message->channel);
                                                $channelValue = $msgChannel?->value ?? $message->channel;
                                                $msgBadgeColor = match($channelValue) {
                                                    'whatsapp' => 'success',
                                                    'email' => 'info',
                                                    'sms' => 'primary',
                                                    default => 'secondary'
                                                };
                                            @endphp
                                            <span class="i-badge {{ $msgBadgeColor }}-soft pill">
                                                {{ $msgChannel?->label() ?? ucfirst($channelValue) }}
                                            </span>
                                        </td>
                                        <td>
                                            @php
                                                // v5.0 — canonical plain_text_preview() helper. Strips
                                                // <style>/<script> block contents + tags + entity-decodes
                                                // + collapses Unicode whitespace + truncates in one call.
                                                $preview = $message->subject
                                                    ? plain_text_preview($message->subject, 50)
                                                    : plain_text_preview($message->content, 50);
                                            @endphp
                                            <span class="text-dark">{{ $preview ?: '-' }}</span>
                                        </td>
                                        <td>
                                            <span class="i-badge {{ $message->is_active ? 'success' : 'secondary' }}-soft pill">
                                                {{ $message->is_active ? translate('Active') : translate('Inactive') }}
                                            </span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif

                {{-- v4.8 — Recent Dispatches drill-down (previously admin-only).
                     Per-recipient delivery report with status filter, live AJAX reload,
                     and CSV export. Same shape as the admin table so behavior is
                     consistent across panels. --}}
                <div class="card mt-4">
                    <div class="form-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h4 class="card-title mb-0">{{ translate('Recent Dispatches') }}</h4>
                        <div class="d-flex gap-2 align-items-center">
                            <select id="dispatchFilter" class="form-select form-select-sm" style="width: auto;">
                                <option value="">{{ translate('All Status') }}</option>
                                @foreach(\App\Enums\Campaign\DispatchStatus::cases() as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            <a href="{{ route('user.campaign.dispatches.export', $campaign->id) }}" class="i-btn btn--primary outline btn--sm">
                                <i class="ri-download-2-line me-1"></i> {{ translate('Export CSV') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">{{ translate('Contact') }}</th>
                                        <th>{{ translate('Channel') }}</th>
                                        <th>{{ translate('Status') }}</th>
                                        <th class="pe-4">{{ translate('Sent At') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="dispatchesTable">
                                    @forelse($campaign->dispatches()->with('contact')->orderBy('created_at', 'desc')->limit(50)->get() as $dispatch)
                                    <tr>
                                        <td class="ps-4">
                                            <div>
                                                <span class="fw-semibold">{{ $dispatch->contact?->full_name ?? '-' }}</span>
                                                <br>
                                                <small class="text-muted">{{ $dispatch->getContactAddress() }}</small>
                                            </div>
                                        </td>
                                        <td>
                                            @php $chEnum = \App\Enums\Campaign\CampaignChannel::tryFrom(is_object($dispatch->channel) ? $dispatch->channel->value : $dispatch->channel); @endphp
                                            <span class="channel-badge-sm channel-{{ is_object($dispatch->channel) ? $dispatch->channel->value : $dispatch->channel }}">
                                                @if($chEnum)<i class="{{ $chEnum->icon() }}"></i> {{ $chEnum->label() }}@endif
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $dispatch->status->badgeClass() }}">
                                                {{ $dispatch->status->label() }}
                                            </span>
                                        </td>
                                        <td class="pe-4">
                                            <small>{{ $dispatch->sent_at?->format('M d, H:i') ?? '-' }}</small>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            <i class="ri-mail-send-line fs-2 d-block mb-2"></i>
                                            {{ translate('No dispatches yet') }}
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Unsubscribed Contacts --}}
                @php
                    $unsubscribes = \App\Models\CampaignUnsubscribe::where('campaign_id', $campaign->id)
                        ->with('contact')
                        ->orderBy('created_at', 'desc')
                        ->limit(20)
                        ->get();
                @endphp
                @if($unsubscribes->count() > 0)
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">
                            <i class="ri-user-unfollow-line me-1" style="color: #dc2626;"></i>
                            {{ translate('Unsubscribed Contacts') }}
                            <span class="badge bg-danger ms-2">{{ $statistics['unsubscribed'] ?? $unsubscribes->count() }}</span>
                        </h4>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">{{ translate('Contact') }}</th>
                                        <th>{{ translate('Email / Phone') }}</th>
                                        <th>{{ translate('Channel') }}</th>
                                        <th class="pe-4">{{ translate('Unsubscribed At') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($unsubscribes as $unsub)
                                    <tr>
                                        <td class="ps-4">
                                            <span class="fw-semibold">{{ $unsub->contact?->full_name ?? $unsub->contact_uid }}</span>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $unsub->contact?->email_contact ?? $unsub->contact?->sms_contact ?? '-' }}</small>
                                        </td>
                                        <td>
                                            @php
                                                $chLabel = match($unsub->channel) {
                                                    '1' => 'SMS',
                                                    '2' => 'WhatsApp',
                                                    '3' => 'Email',
                                                    default => $unsub->channel,
                                                };
                                            @endphp
                                            <span class="badge bg-secondary">{{ $chLabel }}</span>
                                        </td>
                                        <td class="pe-4">
                                            <small>{{ $unsub->created_at->format('M d, Y H:i') }}</small>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ translate('Quick Actions') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            @if($campaign->canEdit())
                            <a href="{{ route('user.campaign.messages', $campaign->id) }}" class="i-btn btn--primary btn--md w-100">
                                <i class="ri-message-2-line me-1"></i>{{ translate('Configure Messages') }}
                            </a>
                            @if($campaign->messages->count() > 0)
                            <a href="{{ route('user.campaign.review', $campaign->id) }}" class="i-btn btn--success btn--md w-100">
                                <i class="ri-rocket-line me-1"></i>{{ translate('Review & Launch') }}
                            </a>
                            @endif
                            @endif

                            @if($campaign->status->value === 'draft' && $campaign->messages->isEmpty())
                            <div class="alert alert-info py-2 mb-0">
                                <small><i class="ri-information-line me-1"></i>{{ translate('Configure messages before launching') }}</small>
                            </div>
                            @endif

                            @if($campaign->status->value === 'running')
                            <form action="{{ route('user.campaign.pause', $campaign->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="i-btn btn--warning btn--md w-100">
                                    <i class="ri-pause-line me-1"></i>{{ translate('Pause Campaign') }}
                                </button>
                            </form>
                            @endif

                            @if($campaign->status->value === 'paused')
                            <form action="{{ route('user.campaign.resume', $campaign->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="i-btn btn--info btn--md w-100">
                                    <i class="ri-play-line me-1"></i>{{ translate('Resume Campaign') }}
                                </button>
                            </form>
                            @endif

                            @if($campaign->canCancel())
                            <form action="{{ route('user.campaign.cancel', $campaign->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="i-btn btn--warning outline btn--md w-100" onclick="return confirm('{{ translate('Are you sure you want to cancel this campaign?') }}')">
                                    <i class="ri-close-circle-line me-1"></i>{{ translate('Cancel Campaign') }}
                                </button>
                            </form>
                            @endif

                            <form action="{{ route('user.campaign.duplicate', $campaign->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="i-btn btn--primary outline btn--md w-100">
                                    <i class="ri-file-copy-line me-1"></i>{{ translate('Duplicate Campaign') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Campaign Info -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ translate('Campaign Info') }}</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">{{ translate('Messages') }}</span>
                                <span class="fw-medium">{{ $campaign->messages->count() }}</span>
                            </li>
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">{{ translate('Total Contacts') }}</span>
                                <span class="fw-medium">{{ number_format($campaign->total_contacts) }}</span>
                            </li>
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">{{ translate('Progress') }}</span>
                                <span class="fw-medium">{{ $progress }}%</span>
                            </li>
                            @if($campaign->started_at)
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">{{ translate('Started At') }}</span>
                                <span class="fw-medium">{{ $campaign->started_at->format('M d, Y') }}</span>
                            </li>
                            @endif
                            @if($campaign->completed_at)
                            <li class="d-flex justify-content-between py-2">
                                <span class="text-muted">{{ translate('Completed At') }}</span>
                                <span class="fw-medium">{{ $campaign->completed_at->format('M d, Y') }}</span>
                            </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection

@push('script-push')
<script>
// v4.8 — Dispatch status filter (mirrors admin/campaign/show.blade.php).
(function () {
    var filter = document.getElementById('dispatchFilter');
    var tbody  = document.getElementById('dispatchesTable');
    if (!filter || !tbody) return;

    var escapeHtml = function (s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    };

    var renderRow = function (d) {
        var name    = (d.contact && ((d.contact.full_name) || ((d.contact.first_name || '') + ' ' + (d.contact.last_name || '')).trim())) || '-';
        var addr    = (d.contact && (d.contact.email_contact || d.contact.whatsapp_contact || d.contact.sms_contact)) || '';
        var channel = d.channel || '';
        var status  = d.status || '';
        var sentAt  = d.sent_at ? new Date(d.sent_at).toLocaleString() : '-';
        return '<tr>'
             + '<td class="ps-4"><div><span class="fw-semibold">' + escapeHtml(name) + '</span><br><small class="text-muted">' + escapeHtml(addr) + '</small></div></td>'
             + '<td><span class="channel-badge-sm channel-' + escapeHtml(channel) + '">' + escapeHtml(channel) + '</span></td>'
             + '<td><span class="badge">' + escapeHtml(status) + '</span></td>'
             + '<td class="pe-4"><small>' + escapeHtml(sentAt) + '</small></td>'
             + '</tr>';
    };

    filter.addEventListener('change', function () {
        var status = this.value || '';
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';

        var url = '{{ route("user.campaign.dispatches", $campaign->id) }}?per_page=50' + (status ? '&status=' + encodeURIComponent(status) : '');

        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                var rows = (payload && (payload.data || payload)) || [];
                if (!rows.length) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><i class="ri-inbox-line fs-2 d-block mb-2"></i>{{ translate("No dispatches match this filter") }}</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(renderRow).join('');
            })
            .catch(function (err) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">{{ translate("Failed to load") }}: ' + (err && err.message ? err.message : '') + '</td></tr>';
                if (window.console) console.error('dispatch filter fetch failed', err);
            });
    });
})();
</script>
@endpush

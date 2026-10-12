@extends('user.layouts.app')
@push("style-include")
<style>
    .execution-header {
        background: var(--card-bg, #fff);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border: 1px solid var(--border-color, #e9ecef);
    }

    .execution-status {
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.875rem;
    }

    .execution-status.running { background: rgba(14, 165, 233, 0.1); color: #0ea5e9; }
    .execution-status.waiting { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .execution-status.completed { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .execution-status.failed { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .execution-status.cancelled { background: rgba(107, 114, 128, 0.1); color: #6b7280; }

    .timeline {
        position: relative;
        padding-left: 30px;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 10px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: var(--border-color, #e9ecef);
    }

    .timeline-item {
        position: relative;
        padding-bottom: 1.5rem;
    }

    .timeline-item:last-child {
        padding-bottom: 0;
    }

    .timeline-marker {
        position: absolute;
        left: -24px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #fff;
        border: 2px solid var(--border-color, #e9ecef);
    }

    .timeline-marker.success { background: #10b981; border-color: #10b981; }
    .timeline-marker.failed { background: #ef4444; border-color: #ef4444; }
    .timeline-marker.skipped { background: #6b7280; border-color: #6b7280; }

    .timeline-content {
        background: var(--card-bg, #fff);
        border: 1px solid var(--border-color, #e9ecef);
        border-radius: 8px;
        padding: 1rem;
    }

    .timeline-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }

    .timeline-title {
        font-weight: 600;
        color: var(--text-color, #1f2937);
    }

    .timeline-time {
        font-size: 0.75rem;
        color: var(--text-muted, #6b7280);
    }

    .timeline-body {
        font-size: 0.875rem;
        color: var(--text-muted, #6b7280);
    }

    .timeline-data {
        margin-top: 0.5rem;
        padding: 0.5rem;
        background: #f8fafc;
        border-radius: 4px;
        font-family: monospace;
        font-size: 0.75rem;
    }

    .contact-card {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: var(--card-bg, #fff);
        border: 1px solid var(--border-color, #e9ecef);
        border-radius: 8px;
    }

    .contact-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: #5046e5;
    }

    .contact-info h5 {
        margin-bottom: 0.25rem;
        font-weight: 600;
    }

    .contact-info p {
        margin-bottom: 0;
        font-size: 0.875rem;
        color: var(--text-muted, #6b7280);
    }
</style>
@endpush

@section('panel')
<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ translate('Execution Details') }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            @foreach($breadcrumbs as $breadcrumb)
                                @if(isset($breadcrumb['url']))
                                    <li class="breadcrumb-item">
                                        <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['name'] }}</a>
                                    </li>
                                @else
                                    <li class="breadcrumb-item active" aria-current="page">{{ $breadcrumb['name'] }}</li>
                                @endif
                            @endforeach
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                <a href="{{ route('user.automation.show', $workflow->uid) }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-arrow-left-line"></i> {{ translate('Back') }}
                </a>
            </div>
        </div>

    <!-- Execution Header -->
    <div class="execution-header">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <h4 class="mb-0">{{ translate('Execution Details') }}</h4>
                    <span class="execution-status {{ $execution->status }}">
                        {{ ucfirst($execution->status) }}
                    </span>
                </div>
                <p class="text-muted mb-0">
                    {{ translate('Workflow') }}: {{ $workflow->name }}
                    &bull;
                    {{ translate('Started') }}: {{ $execution->started_at?->format('M d, Y H:i:s') }}
                    @if($execution->completed_at)
                        &bull;
                        {{ translate('Completed') }}: {{ $execution->completed_at?->format('M d, Y H:i:s') }}
                    @endif
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                @if($execution->canContinue())
                    <button class="btn btn-danger" onclick="cancelExecution()">
                        <i class="ri-stop-circle-line me-1"></i>{{ translate('Cancel Execution') }}
                    </button>
                @endif
                <a href="{{ route('user.automation.show', $workflow->uid) }}" class="btn btn-outline-secondary">
                    <i class="ri-arrow-left-line me-1"></i>{{ translate('Back to Workflow') }}
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Contact Info -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ translate('Contact') }}</h5>
                </div>
                <div class="card-body">
                    @if($execution->contact)
                        <div class="contact-card">
                            <div class="contact-avatar">
                                <i class="ri-user-line"></i>
                            </div>
                            <div class="contact-info">
                                <h5>
                                    {{ $execution->contact->first_name ?? '' }}
                                    {{ $execution->contact->last_name ?? '' }}
                                    @if(!$execution->contact->first_name && !$execution->contact->last_name)
                                        {{ translate('Unknown') }}
                                    @endif
                                </h5>
                                @if($execution->contact->email_contact)
                                    <p><i class="ri-mail-line me-1"></i>{{ $execution->contact->email_contact }}</p>
                                @endif
                                @if($execution->contact->sms_contact)
                                    <p><i class="ri-phone-line me-1"></i>{{ $execution->contact->sms_contact }}</p>
                                @endif
                                @if($execution->contact->whatsapp_contact)
                                    <p><i class="ri-whatsapp-line me-1"></i>{{ $execution->contact->whatsapp_contact }}</p>
                                @endif
                            </div>
                        </div>
                    @else
                        <p class="text-muted mb-0">{{ translate('Contact not found') }}</p>
                    @endif
                </div>
            </div>

            @if(!empty($execution->context) && is_array($execution->context))
            <!-- Execution Context -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">{{ translate('Context Variables') }}</h5>
                    <small class="text-muted">{{ translate('Resolves to') }}</small>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            @foreach($execution->context as $key => $value)
                            <tr>
                                <td class="text-muted ps-3" style="width:40%;">{{ $key }}</td>
                                <td class="font-monospace small">
                                    @if(is_array($value) || is_object($value))
                                        <pre class="mb-0" style="white-space:pre-wrap;">{{ json_encode($value, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
                                    @elseif(is_bool($value))
                                        {{ $value ? 'true' : 'false' }}
                                    @else
                                        {{ \Illuminate\Support\Str::limit((string) $value, 120) }}
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <!-- Current Status -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ translate('Status') }}</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td class="text-muted">{{ translate('Status') }}</td>
                            <td class="text-end fw-semibold">{{ ucfirst($execution->status) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">{{ translate('Current Node') }}</td>
                            <td class="text-end fw-semibold">
                                {{ $execution->currentNode?->display_label ?? translate('None') }}
                            </td>
                        </tr>
                        @if($execution->next_action_at)
                            <tr>
                                <td class="text-muted">{{ translate('Next Action') }}</td>
                                <td class="text-end fw-semibold">
                                    {{ $execution->next_action_at->format('M d, H:i') }}
                                </td>
                            </tr>
                        @endif
                        @if($execution->duration)
                            <tr>
                                <td class="text-muted">{{ translate('Duration') }}</td>
                                <td class="text-end fw-semibold">{{ gmdate("H:i:s", $execution->duration) }}</td>
                            </tr>
                        @endif
                        @if($execution->error_message)
                            <tr>
                                <td class="text-muted">{{ translate('Error') }}</td>
                                <td class="text-end text-danger">{{ $execution->error_message }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        <!-- Timeline -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ translate('Execution Timeline') }}</h5>
                </div>
                <div class="card-body">
                    @if(count($timeline) > 0)
                        <div class="timeline">
                            @foreach($timeline as $item)
                                <div class="timeline-item">
                                    <div class="timeline-marker {{ $item['result'] }}"></div>
                                    <div class="timeline-content">
                                        <div class="timeline-header">
                                            <span class="timeline-title">
                                                {{ $item['node_label'] }}
                                                <span class="badge bg-{{ $item['result'] === 'success' ? 'success' : ($item['result'] === 'failed' ? 'danger' : 'secondary') }} ms-2">
                                                    {{ ucfirst($item['result']) }}
                                                </span>
                                            </span>
                                            <span class="timeline-time">
                                                {{ \Carbon\Carbon::parse($item['executed_at'])->format('H:i:s') }}
                                            </span>
                                        </div>
                                        <div class="timeline-body">
                                            <strong>{{ ucfirst(str_replace('_', ' ', $item['action'])) }}</strong>
                                            @if(!empty($item['node_type']))
                                                <span class="badge bg-light text-dark ms-1">{{ ucfirst($item['node_type']) }}</span>
                                            @endif
                                            @if($item['since_prev_ms'] !== null)
                                                <span class="badge bg-light text-muted ms-1" title="{{ translate('Time since previous step') }}">
                                                    +{{ $item['since_prev_ms'] }}ms
                                                </span>
                                            @endif
                                            @if($item['error'])
                                                <div class="text-danger mt-1">
                                                    <i class="ri-error-warning-line me-1"></i>{{ $item['error'] }}
                                                </div>
                                            @endif
                                            @if(!empty($item['data']))
                                                <div class="timeline-data">
                                                    <pre class="mb-0">{{ json_encode($item['data'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="ri-time-line fs-2 text-muted mb-2 d-block"></i>
                            <p class="text-muted mb-0">{{ translate('No execution logs yet') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    </div>
</main>
@endsection

@push('script-push')
<script>
    function cancelExecution() {
        if (!confirm('{{ translate("Are you sure you want to cancel this execution?") }}')) return;

        fetch('{{ route("user.automation.execution.cancel", [$workflow->uid, $execution->uid]) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status) {
                notify('success', data.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                notify('error', data.message);
            }
        })
        .catch(error => notify('error', 'An error occurred'));
    }
</script>
@endpush

@extends('user.layouts.app')
@push("style-include")
<style>
    /* Page Header */
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

    /* Workflow Header Card */
    .workflow-detail-header {
        background: var(--color-primary);
        border-radius: 12px;
        padding: 1.5rem 2rem;
        color: #fff;
        margin-bottom: 1.5rem;
        position: relative;
        overflow: hidden;
    }

    .workflow-detail-header::before {
        content: '';
        position: absolute;
        top: -40%;
        right: -10%;
        width: 250px;
        height: 250px;
        background: rgba(255, 255, 255, 0.06);
        border-radius: 50%;
    }

    .workflow-detail-header h3 {
        font-size: 1.375rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        position: relative;
        z-index: 1;
    }

    .workflow-meta-row {
        display: flex;
        align-items: center;
        gap: 1.25rem;
        font-size: 0.813rem;
        opacity: 0.85;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
    }

    .workflow-meta-row span {
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }

    .workflow-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.3rem 0.75rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
        position: relative;
        z-index: 1;
    }

    .workflow-status-pill.active {
        background: rgba(255, 255, 255, 0.2);
        color: #fff;
    }

    .workflow-status-pill.paused {
        background: rgba(245, 158, 11, 0.25);
        color: #fde68a;
    }

    .workflow-status-pill.draft {
        background: rgba(255, 255, 255, 0.15);
        color: rgba(255, 255, 255, 0.8);
    }

    .workflow-status-pill .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }

    .workflow-status-pill.active .dot {
        animation: blink 1.8s infinite;
    }

    @keyframes blink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.3; }
    }

    /* Stats Grid */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .stat-box {
        background: var(--card-bg, #fff);
        border-radius: 10px;
        padding: 1.25rem;
        border: 1px solid var(--border-color, #e9ecef);
        display: flex;
        align-items: center;
        gap: 0.875rem;
        transition: box-shadow 0.2s;
    }

    .stat-box:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .stat-box-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.125rem;
        flex-shrink: 0;
    }

    .stat-box-icon.primary { background: var(--color-primary-light); color: var(--color-primary); }
    .stat-box-icon.success { background: rgba(16, 185, 129, 0.1); color: var(--success-color, #10b981); }
    .stat-box-icon.danger { background: rgba(239, 68, 68, 0.1); color: var(--danger-color, #ef4444); }
    .stat-box-icon.info { background: rgba(14, 165, 233, 0.1); color: var(--info-color, #0ea5e9); }
    .stat-box-icon.secondary { background: rgba(107, 114, 128, 0.1); color: var(--secondary-color, #6b7280); }

    .stat-box-value {
        font-size: 1.375rem;
        font-weight: 700;
        color: var(--text-color, #1f2937);
        line-height: 1.2;
    }

    .stat-box-label {
        font-size: 0.75rem;
        color: var(--text-muted, #6b7280);
        font-weight: 500;
    }

    /* Execution List */
    .execution-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.875rem 1.25rem;
        border-bottom: 1px solid var(--border-color, #e9ecef);
        transition: background 0.15s;
    }

    .execution-row:hover {
        background: var(--body-bg, #f9fafb);
    }

    .execution-row:last-child {
        border-bottom: none;
    }

    .execution-left {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .execution-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: var(--color-primary-light);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--color-primary);
        flex-shrink: 0;
    }

    .execution-name {
        font-weight: 600;
        color: var(--text-color, #1f2937);
        font-size: 0.875rem;
    }

    .execution-detail {
        font-size: 0.75rem;
        color: var(--text-muted, #6b7280);
        margin-top: 1px;
    }

    .badge-status {
        padding: 0.25rem 0.625rem;
        border-radius: 50px;
        font-size: 0.688rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .badge-status.running { background: rgba(14, 165, 233, 0.1); color: var(--info-color, #0ea5e9); }
    .badge-status.waiting { background: rgba(245, 158, 11, 0.1); color: var(--warning-color, #f59e0b); }
    .badge-status.completed { background: rgba(16, 185, 129, 0.1); color: var(--success-color, #10b981); }
    .badge-status.failed { background: rgba(239, 68, 68, 0.1); color: var(--danger-color, #ef4444); }
    .badge-status.cancelled { background: rgba(107, 114, 128, 0.1); color: var(--secondary-color, #6b7280); }

    /* Node Flow */
    .node-step {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.625rem 1rem;
        background: var(--card-bg, #fff);
        border: 1px solid var(--border-color, #e9ecef);
        border-radius: 8px;
        font-size: 0.813rem;
        transition: border-color 0.15s;
    }

    .node-step:hover {
        border-color: var(--color-primary);
    }

    .node-step-icon {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 0.875rem;
    }

    .node-step-icon.trigger { background: rgba(16, 185, 129, 0.1); color: var(--success-color, #10b981); }
    .node-step-icon.action { background: rgba(59, 130, 246, 0.1); color: var(--info-color, #3b82f6); }
    .node-step-icon.condition { background: rgba(245, 158, 11, 0.1); color: var(--warning-color, #f59e0b); }
    .node-step-icon.wait { background: rgba(107, 114, 128, 0.1); color: var(--secondary-color, #6b7280); }

    .node-step-label {
        font-weight: 600;
        color: var(--text-color, #1f2937);
        font-size: 0.813rem;
    }

    .node-step-type {
        font-size: 0.688rem;
        color: var(--text-muted, #6b7280);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .node-arrow {
        height: 20px;
        width: 2px;
        background: var(--border-color, #e9ecef);
        margin-left: 14px;
    }

    .node-flow-wrap {
        display: flex;
        flex-direction: column;
        gap: 0;
        padding: 1rem;
    }

    /* Empty State */
    .empty-box {
        text-align: center;
        padding: 2.5rem 1.5rem;
    }

    .empty-box-icon {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: var(--color-primary-light);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 0.75rem;
        font-size: 1.25rem;
        color: var(--color-primary);
    }

    .empty-box h6 {
        color: var(--text-color, #1f2937);
        font-weight: 600;
        margin-bottom: 0.25rem;
    }

    .empty-box p {
        color: var(--text-muted, #6b7280);
        font-size: 0.813rem;
        margin-bottom: 0;
    }

    /* Card extras */
    .card-count-badge {
        font-size: 0.688rem;
        padding: 0.2rem 0.5rem;
        border-radius: 50px;
        font-weight: 600;
        background: var(--color-primary-light);
        color: var(--color-primary);
    }

    /* Confirm Modal */
    .confirm-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1060;
        align-items: center;
        justify-content: center;
    }

    .confirm-modal-overlay.show {
        display: flex;
    }

    .confirm-modal-box {
        background: var(--card-bg, #fff);
        border-radius: 12px;
        width: 90%;
        max-width: 380px;
        padding: 1.75rem;
        text-align: center;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.12);
    }

    .confirm-modal-icon {
        width: 54px;
        height: 54px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.5rem;
    }

    .confirm-modal-icon.warning {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-color, #f59e0b);
    }

    .confirm-modal-icon.success {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success-color, #10b981);
    }

    .confirm-modal-title {
        font-size: 1.063rem;
        font-weight: 600;
        margin-bottom: 0.375rem;
        color: var(--text-color, #1f2937);
    }

    .confirm-modal-message {
        color: var(--text-muted, #6b7280);
        margin-bottom: 1.25rem;
        font-size: 0.813rem;
        line-height: 1.5;
    }

    .confirm-modal-actions {
        display: flex;
        gap: 0.625rem;
        justify-content: center;
    }

    @media (max-width: 768px) {
        .stats-row {
            grid-template-columns: repeat(2, 1fr);
        }
        .workflow-meta-row {
            gap: 0.75rem;
        }
    }
</style>
@endpush

@section('panel')
<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <!-- Page Header: Title Left, Buttons Right -->
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ $title }}</h2>
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
                @if($workflow->status !== 'active')
                    <button class="i-btn btn--success btn--md" onclick="activateWorkflow('{{ $workflow->uid }}')">
                        <i class="ri-play-circle-line"></i> {{ translate('Activate') }}
                    </button>
                @else
                    <button class="i-btn btn--warning btn--md" onclick="pauseWorkflow('{{ $workflow->uid }}')">
                        <i class="ri-pause-circle-line"></i> {{ translate('Pause') }}
                    </button>
                @endif
                <a href="{{ route('user.automation.edit', $workflow->uid) }}" class="i-btn btn--primary outline btn--md">
                    <i class="ri-edit-line"></i> {{ translate('Edit Builder') }}
                </a>
                <a href="{{ route('user.automation.index') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-arrow-left-line"></i> {{ translate('Back') }}
                </a>
            </div>
        </div>

        <!-- Workflow Detail Header -->
        <div class="workflow-detail-header">
            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                <h3 class="mb-0">{{ $workflow->name }}</h3>
                <span class="workflow-status-pill {{ $workflow->status }}">
                    <span class="dot"></span>
                    {{ ucfirst($workflow->status) }}
                </span>
            </div>
            @if($workflow->description)
                <p class="mb-2 opacity-75" style="font-size: 0.813rem; position: relative; z-index: 1;">{{ $workflow->description }}</p>
            @endif
            <div class="workflow-meta-row">
                <span>
                    <i class="ri-flashlight-line"></i>
                    {{ \App\Models\Automation\AutomationWorkflow::TRIGGER_TYPES[$workflow->trigger_type]['label'] ?? $workflow->trigger_type }}
                </span>
                <span>
                    <i class="ri-node-tree"></i>
                    {{ $workflow->nodes_count }} {{ translate('nodes') }}
                </span>
                <span>
                    <i class="ri-time-line"></i>
                    {{ translate('Created') }} {{ $workflow->created_at->diffForHumans() }}
                </span>
                @if($workflow->last_triggered_at)
                <span>
                    <i class="ri-history-line"></i>
                    {{ translate('Last run') }} {{ $workflow->last_triggered_at->diffForHumans() }}
                </span>
                @endif
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-icon primary">
                    <i class="ri-user-add-line"></i>
                </div>
                <div>
                    <div class="stat-box-value">{{ number_format($stats['total_enrolled']) }}</div>
                    <div class="stat-box-label">{{ translate('Enrolled') }}</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-box-icon success">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
                <div>
                    <div class="stat-box-value">{{ number_format($stats['total_completed']) }}</div>
                    <div class="stat-box-label">{{ translate('Completed') }}</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-box-icon danger">
                    <i class="ri-close-circle-line"></i>
                </div>
                <div>
                    <div class="stat-box-value">{{ number_format($stats['total_failed']) }}</div>
                    <div class="stat-box-label">{{ translate('Failed') }}</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-box-icon info">
                    <i class="ri-loader-4-line"></i>
                </div>
                <div>
                    <div class="stat-box-value">{{ number_format($stats['currently_running']) }}</div>
                    <div class="stat-box-label">{{ translate('Running') }}</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-box-icon secondary">
                    <i class="ri-percent-line"></i>
                </div>
                <div>
                    <div class="stat-box-value">{{ $stats['completion_rate'] }}%</div>
                    <div class="stat-box-label">{{ translate('Success Rate') }}</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Workflow Steps -->
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">{{ translate('Workflow Steps') }}</h5>
                        <span class="card-count-badge">{{ $workflow->nodes_count }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="node-flow-wrap">
                            @forelse($workflow->nodes as $node)
                                <div class="node-step">
                                    <div class="node-step-icon {{ $node->type }}">
                                        <i class="{{ $node->icon }}"></i>
                                    </div>
                                    <div>
                                        <div class="node-step-label">{{ $node->display_label }}</div>
                                        <div class="node-step-type">{{ ucfirst($node->type) }}</div>
                                    </div>
                                </div>
                                @if(!$loop->last)
                                    <div class="node-arrow"></div>
                                @endif
                            @empty
                                <div class="text-center py-4">
                                    <p class="text-muted mb-0 small">{{ translate('No steps configured') }}</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Executions -->
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">{{ translate('Recent Executions') }}</h5>
                        @if($executions->total() > 0)
                            <span class="card-count-badge">{{ $executions->total() }} {{ translate('total') }}</span>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        @if($executions->count() > 0)
                            @foreach($executions as $execution)
                                @php
                                    $contactName = trim(($execution->contact?->first_name ?? '') . ' ' . ($execution->contact?->last_name ?? ''));
                                    if (!$contactName) {
                                        $contactName = $execution->contact?->email_contact ?? $execution->contact?->sms_contact ?? translate('Unknown');
                                    }
                                    $initials = collect(explode(' ', $contactName))->map(fn($w) => strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('');
                                @endphp
                                <div class="execution-row">
                                    <div class="execution-left">
                                        <div class="execution-avatar">{{ $initials ?: '?' }}</div>
                                        <div>
                                            <div class="execution-name">{{ $contactName }}</div>
                                            <div class="execution-detail">
                                                {{ translate('Started') }} {{ $execution->started_at?->diffForHumans() }}
                                                @if($execution->currentNode)
                                                    &middot; {{ $execution->currentNode->display_label }}
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge-status {{ $execution->status }}">
                                            {{ ucfirst($execution->status) }}
                                        </span>
                                        <a href="{{ route('user.automation.execution', [$workflow->uid, $execution->uid]) }}"
                                           class="btn btn-sm btn-outline-secondary" title="{{ translate('View') }}">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                            @if($executions->hasPages())
                            <div class="p-3">
                                {{ $executions->links() }}
                            </div>
                            @endif
                        @else
                            <div class="empty-box">
                                <div class="empty-box-icon">
                                    <i class="ri-play-circle-line"></i>
                                </div>
                                <h6>{{ translate('No Executions Yet') }}</h6>
                                <p>{{ translate('Activate this workflow to start processing contacts.') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirm Modal -->
        <div class="confirm-modal-overlay" id="confirmModal">
            <div class="confirm-modal-box">
                <div class="confirm-modal-icon warning" id="confirmModalIcon">
                    <i class="ri-question-line"></i>
                </div>
                <h4 class="confirm-modal-title" id="confirmModalTitle">{{ translate('Confirm Action') }}</h4>
                <p class="confirm-modal-message" id="confirmModalMessage">{{ translate('Are you sure you want to proceed?') }}</p>
                <div class="confirm-modal-actions">
                    <button type="button" class="i-btn btn--dark outline btn--md" onclick="hideConfirmModal()">
                        {{ translate('Cancel') }}
                    </button>
                    <button type="button" class="i-btn btn--primary btn--md" id="confirmModalBtn" onclick="confirmModalAction()">
                        {{ translate('Confirm') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection

@push('script-push')
<script>
    let confirmModalCallback = null;

    function showConfirmModal(title, message, callback, type = 'warning') {
        const modal = document.getElementById('confirmModal');
        const iconEl = document.getElementById('confirmModalIcon');
        const titleEl = document.getElementById('confirmModalTitle');
        const messageEl = document.getElementById('confirmModalMessage');
        const btnEl = document.getElementById('confirmModalBtn');

        titleEl.textContent = title;
        messageEl.textContent = message;
        confirmModalCallback = callback;

        iconEl.className = 'confirm-modal-icon ' + type;
        if (type === 'success') {
            iconEl.innerHTML = '<i class="ri-play-circle-line"></i>';
            btnEl.className = 'i-btn btn--success btn--md';
            btnEl.textContent = '{{ translate("Activate") }}';
        } else {
            iconEl.innerHTML = '<i class="ri-pause-circle-line"></i>';
            btnEl.className = 'i-btn btn--warning btn--md';
            btnEl.textContent = '{{ translate("Pause") }}';
        }

        modal.classList.add('show');
    }

    function hideConfirmModal() {
        document.getElementById('confirmModal').classList.remove('show');
        confirmModalCallback = null;
    }

    function confirmModalAction() {
        if (confirmModalCallback && typeof confirmModalCallback === 'function') {
            confirmModalCallback();
        }
        hideConfirmModal();
    }

    document.getElementById('confirmModal').addEventListener('click', function(e) {
        if (e.target === this) hideConfirmModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') hideConfirmModal();
    });

    function activateWorkflow(uid) {
        showConfirmModal(
            '{{ translate("Activate Workflow") }}',
            '{{ translate("This workflow will start processing contacts based on its triggers. Continue?") }}',
            () => {
                fetch(`{{ url('user/automation/activate') }}/${uid}`, {
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
                });
            },
            'success'
        );
    }

    function pauseWorkflow(uid) {
        showConfirmModal(
            '{{ translate("Pause Workflow") }}',
            '{{ translate("Active executions will continue but no new contacts will be enrolled. Continue?") }}',
            () => {
                fetch(`{{ url('user/automation/pause') }}/${uid}`, {
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
                });
            },
            'warning'
        );
    }
</script>
@endpush

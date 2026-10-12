@extends('admin.layouts.app')
@push("style-include")
<link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css') }}">
<style>
    /* Page Header Fix */
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

    /* Workflow Detail Styles */
    .workflow-header {
        background: var(--card-bg);
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        padding: 20px;
        border-radius: var(--radius-12);
        margin-bottom: 24px;
    }

    .workflow-header h2 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .workflow-header-meta {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        font-size: 0.875rem;
        opacity: 0.9;
    }

    .workflow-header-meta span {
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }

    .workflow-header-actions {
        display: flex;
        gap: 0.75rem;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .stat-card {
        background: var(--card-bg, #fff);
        border-radius: 12px;
        padding: 1.25rem;
        border: 1px solid var(--border-color, #e9ecef);
        text-align: center;
    }

    .stat-card-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--color-primary);
        margin-bottom: 0.25rem;
    }

    .stat-card-label {
        font-size: 0.813rem;
        color: var(--text-muted, #6b7280);
        font-weight: 500;
    }

    /* Execution List */
    .execution-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
        border-bottom: 1px solid var(--border-color, #e9ecef);
    }

    .execution-item:last-child {
        border-bottom: none;
    }

    .execution-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .execution-contact {
        font-weight: 600;
        color: var(--text-color, #1f2937);
    }

    .execution-meta {
        font-size: 0.813rem;
        color: var(--text-muted, #6b7280);
    }

    .status-badge {
        padding: 0.375rem 0.75rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-badge.running { background: rgba(14, 165, 233, 0.1); color: #0ea5e9; }
    .status-badge.waiting { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .status-badge.completed { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .status-badge.failed { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .status-badge.cancelled { background: rgba(107, 114, 128, 0.1); color: #6b7280; }

    /* Node Flow Preview */
    .node-flow {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        padding: 1rem;
    }

    .node-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        background: var(--card-bg, #fff);
        border: 1px solid var(--border-color, #e9ecef);
        border-radius: 8px;
        font-size: 0.875rem;
    }

    .node-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .node-icon.trigger { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .node-icon.action { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .node-icon.condition { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .node-icon.wait { background: rgba(107, 114, 128, 0.1); color: #6b7280; }

    .node-connector {
        height: 20px;
        width: 2px;
        background: var(--border-color, #e9ecef);
        margin-left: 15px;
    }

    /* Confirmation Modal */
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
        max-width: 400px;
        padding: 1.5rem;
        text-align: center;
    }

    .confirm-modal-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.75rem;
    }

    .confirm-modal-icon.warning {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
    }

    .confirm-modal-icon.success {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }

    .confirm-modal-title {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-color, #1f2937);
    }

    .confirm-modal-message {
        color: var(--text-muted, #6b7280);
        margin-bottom: 1.5rem;
    }

    .confirm-modal-actions {
        display: flex;
        gap: 0.75rem;
        justify-content: center;
    }

    /* Manual Trigger Modal */
    #triggerModal .modal-content {
        border: none;
        border-radius: 16px;
        overflow: hidden;
    }

    #triggerModal .modal-header {
        background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary) 100%);
        border: none;
        padding: 1.25rem 1.5rem;
    }

    #triggerModal .modal-header .modal-title {
        color: #fff;
        font-weight: 600;
        font-size: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    #triggerModal .modal-header .btn-close {
        filter: brightness(0) invert(1);
        opacity: 0.8;
    }

    #triggerModal .modal-header .btn-close:hover {
        opacity: 1;
    }

    #triggerModal .modal-body {
        padding: 1.5rem;
    }

    .trigger-info-box {
        display: flex;
        gap: 0.75rem;
        padding: 1rem;
        background: rgba(59, 130, 246, 0.08);
        border-radius: 10px;
        margin-bottom: 1.25rem;
        border-left: 3px solid var(--color-primary);
    }

    .trigger-info-box i {
        color: var(--color-primary);
        font-size: 1.125rem;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .trigger-info-box p {
        margin: 0;
        font-size: 0.813rem;
        color: var(--text-muted, #6b7280);
        line-height: 1.5;
    }

    .trigger-form-group {
        margin-bottom: 0;
    }

    .trigger-form-group label {
        font-weight: 600;
        font-size: 0.875rem;
        color: var(--text-color, #1f2937);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .trigger-form-group label i {
        color: var(--color-primary);
    }

    .trigger-form-group .form-hint {
        font-size: 0.75rem;
        color: var(--text-muted, #6b7280);
        margin-top: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }

    .contact-count-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.375rem 0.75rem;
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-top: 0.75rem;
    }

    .contact-count-badge.empty {
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
    }

    #triggerModal .modal-footer {
        border-top: 1px solid var(--border-color, #e9ecef);
        padding: 1rem 1.5rem;
        gap: 0.75rem;
    }

    #triggerModal .select2-container {
        width: 100% !important;
    }

    #triggerModal .select2-container--default .select2-selection--multiple {
        border: 1px solid var(--border-color, #dee2e6);
        border-radius: 8px;
        min-height: 46px;
        padding: 0.375rem;
    }

    #triggerModal .select2-container--default .select2-selection--multiple:focus,
    #triggerModal .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.1);
    }

    #triggerModal .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background: var(--color-primary);
        border: none;
        border-radius: 6px;
        padding: 0.25rem 0.5rem;
        color: #fff;
        font-size: 0.813rem;
    }

    #triggerModal .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: rgba(255, 255, 255, 0.7);
        margin-right: 0.375rem;
    }

    #triggerModal .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: #fff;
        background: transparent;
    }
</style>
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
                <a href="{{ route('admin.automation.index') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-arrow-left-line"></i> {{ translate('Back') }}
                </a>
            </div>
        </div>

    <!-- Workflow Header -->
    <div class="workflow-header">
        <div class="d-flex align-items-start justify-content-between">
            <div>
                <h2>{{ $workflow->name }}</h2>
                <div class="workflow-header-meta">
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
                            {{ translate('Last triggered') }} {{ $workflow->last_triggered_at->diffForHumans() }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="workflow-header-actions">
                @if($workflow->status !== 'active')
                    <button class="i-btn btn--sm btn--primary" onclick="activateWorkflow('{{ $workflow->uid }}')">
                        <i class="ri-play-circle-line me-1"></i>{{ translate('Activate') }}
                    </button>
                @else
                    <button class="i-btn btn--secondary-light" onclick="pauseWorkflow('{{ $workflow->uid }}')">
                        <i class="ri-pause-circle-line me-1"></i>{{ translate('Pause') }}
                    </button>
                @endif
                <a href="{{ route('admin.automation.edit', $workflow->uid) }}" class="btn btn-light">
                    <i class="ri-edit-line me-1"></i>{{ translate('Edit') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-value">{{ number_format($stats['total_enrolled']) }}</div>
            <div class="stat-card-label">{{ translate('Total Enrolled') }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-value">{{ number_format($stats['total_completed']) }}</div>
            <div class="stat-card-label">{{ translate('Completed') }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-value">{{ number_format($stats['total_failed']) }}</div>
            <div class="stat-card-label">{{ translate('Failed') }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-value">{{ number_format($stats['currently_running']) }}</div>
            <div class="stat-card-label">{{ translate('Running') }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-value">{{ number_format($stats['currently_waiting']) }}</div>
            <div class="stat-card-label">{{ translate('Waiting') }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-value">{{ $stats['completion_rate'] }}%</div>
            <div class="stat-card-label">{{ translate('Success Rate') }}</div>
        </div>
    </div>

    <div class="row">
        <!-- Workflow Flow -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ translate('Workflow Steps') }}</h5>
                </div>
                <div class="card-body p-0">
                    <div class="node-flow">
                        @foreach($workflow->nodes as $node)
                            <div class="node-item">
                                <div class="node-icon {{ $node->type }}">
                                    <i class="{{ $node->icon }}"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">{{ $node->display_label }}</div>
                                    <small class="text-muted">{{ ucfirst($node->type) }}</small>
                                </div>
                            </div>
                            @if(!$loop->last)
                                <div class="node-connector"></div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Executions -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">{{ translate('Recent Executions') }}</h5>
                    @if($workflow->isActive())
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#triggerModal">
                            <i class="ri-play-line me-1"></i>{{ translate('Manual Trigger') }}
                        </button>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if($executions->count() > 0)
                        @foreach($executions as $execution)
                            <div class="execution-item">
                                <div class="execution-info">
                                    <div>
                                        <div class="execution-contact">
                                            {{ $execution->contact?->first_name ?? '' }}
                                            {{ $execution->contact?->last_name ?? '' }}
                                            @if(!$execution->contact?->first_name && !$execution->contact?->last_name)
                                                {{ $execution->contact?->email_contact ?? $execution->contact?->sms_contact ?? 'Unknown' }}
                                            @endif
                                        </div>
                                        <div class="execution-meta">
                                            {{ translate('Started') }} {{ $execution->started_at?->diffForHumans() }}
                                            @if($execution->currentNode)
                                                &bull; {{ translate('At') }}: {{ $execution->currentNode->display_label }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="status-badge {{ $execution->status }}">
                                        {{ ucfirst($execution->status) }}
                                    </span>
                                    <a href="{{ route('admin.automation.execution', [$workflow->uid, $execution->uid]) }}"
                                       class="btn btn-sm btn-outline-secondary">
                                        <i class="ri-eye-line"></i>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                        <div class="p-3">
                            {{ $executions->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="ri-inbox-line fs-2 text-muted mb-2 d-block"></i>
                            <p class="text-muted mb-0">{{ translate('No executions yet') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

<!-- Manual Trigger Modal -->
<div class="modal fade" id="triggerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ri-play-circle-line"></i>
                    {{ translate('Manual Trigger') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Info Box -->
                <div class="trigger-info-box">
                    <i class="ri-information-line"></i>
                    <p>{{ translate('Manually start this workflow for selected contacts. Each contact will go through all workflow steps starting from the first action.') }}</p>
                </div>

                <!-- Contact Selection -->
                <div class="trigger-form-group">
                    <label>
                        <i class="ri-user-line"></i>
                        {{ translate('Select Contacts') }}
                    </label>
                    <select id="triggerContacts" class="form-select select2-trigger" multiple data-placeholder="{{ translate('Search and select contacts...') }}">
                        <!-- Contacts will be loaded via AJAX -->
                    </select>
                    <div class="form-hint">
                        <i class="ri-lightbulb-line"></i>
                        {{ translate('You can select multiple contacts to enroll at once') }}
                    </div>
                    <div id="selectedContactCount" class="contact-count-badge empty" style="display: none;">
                        <i class="ri-user-follow-line"></i>
                        <span>0</span> {{ translate('contacts selected') }}
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">
                    <i class="ri-close-line"></i> {{ translate('Cancel') }}
                </button>
                <button type="button" class="i-btn btn--primary btn--md" id="triggerWorkflowBtn" onclick="triggerWorkflow()" disabled>
                    <i class="ri-play-line"></i> {{ translate('Start Workflow') }}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="confirm-modal-overlay" id="confirmModal">
    <div class="confirm-modal-box">
        <div class="confirm-modal-icon warning" id="confirmModalIcon">
            <i class="ri-question-line"></i>
        </div>
        <h4 class="confirm-modal-title" id="confirmModalTitle">{{ translate('Confirm Action') }}</h4>
        <p class="confirm-modal-message" id="confirmModalMessage">{{ translate('Are you sure you want to proceed?') }}</p>
        <div class="confirm-modal-actions">
            <button type="button" class="btn btn-outline-secondary" onclick="hideConfirmModal()">
                {{ translate('Cancel') }}
            </button>
            <button type="button" class="btn btn-primary" id="confirmModalBtn" onclick="confirmModalAction()">
                {{ translate('Confirm') }}
            </button>
        </div>
    </div>
</div>
    </div>
</main>
@endsection

@push('script-include')
<script src="{{ asset('assets/theme/global/js/select2.min.js') }}"></script>
@endpush

@push('script-push')
<script>
    // Confirmation Modal Functions
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
            btnEl.className = 'btn btn-success';
            btnEl.textContent = '{{ translate("Activate") }}';
        } else {
            iconEl.innerHTML = '<i class="ri-pause-circle-line"></i>';
            btnEl.className = 'btn btn-warning';
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

    // Close modal on overlay click
    document.getElementById('confirmModal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideConfirmModal();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideConfirmModal();
        }
    });

    function activateWorkflow(uid) {
        showConfirmModal(
            '{{ translate("Activate Workflow") }}',
            '{{ translate("Are you sure you want to activate this workflow? It will start processing contacts based on its triggers.") }}',
            () => {
                fetch(`{{ url('admin/automation/activate') }}/${uid}`, {
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
            '{{ translate("Are you sure you want to pause this workflow? Active executions will continue but no new contacts will be enrolled.") }}',
            () => {
                fetch(`{{ url('admin/automation/pause') }}/${uid}`, {
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

    function triggerWorkflow() {
        const contacts = $('#triggerContacts').val();
        if (!contacts || contacts.length === 0) {
            notify('error', '{{ translate("Please select at least one contact") }}');
            return;
        }

        // Disable button and show loading
        const btn = document.getElementById('triggerWorkflowBtn');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> {{ translate("Processing...") }}';

        fetch(`{{ route('admin.automation.trigger', $workflow->uid) }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ contact_ids: contacts })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status) {
                notify('success', data.message);
                $('#triggerModal').modal('hide');
                setTimeout(() => location.reload(), 1000);
            } else {
                notify('error', data.message);
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
    }

    // Initialize Select2 for trigger contacts
    $(document).ready(function() {
        // Initialize select2 when modal is shown
        $('#triggerModal').on('shown.bs.modal', function() {
            initTriggerContactsSelect();
        });

        // Clear selection when modal is hidden
        $('#triggerModal').on('hidden.bs.modal', function() {
            $('#triggerContacts').val(null).trigger('change');
            updateContactCount(0);
        });
    });

    function initTriggerContactsSelect() {
        if ($('#triggerContacts').hasClass('select2-hidden-accessible')) {
            return; // Already initialized
        }

        $('#triggerContacts').select2({
            dropdownParent: $('#triggerModal'),
            placeholder: '{{ translate("Search and select contacts...") }}',
            allowClear: true,
            ajax: {
                url: '{{ route("admin.contact.search") }}',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        search: params.term || '',
                        page: params.page || 1
                    };
                },
                processResults: function(data, params) {
                    params.page = params.page || 1;

                    // Handle empty or invalid response
                    if (!data || !data.data) {
                        return { results: [] };
                    }

                    return {
                        results: data.data.map(function(contact) {
                            let displayText = (contact.first_name || '') + ' ' + (contact.last_name || '');
                            if (contact.email_contact) {
                                displayText += ' (' + contact.email_contact + ')';
                            } else if (contact.sms_contact) {
                                displayText += ' (' + contact.sms_contact + ')';
                            } else if (contact.whatsapp_contact) {
                                displayText += ' (' + contact.whatsapp_contact + ')';
                            }
                            return {
                                id: contact.id,
                                text: displayText.trim() || '{{ translate("Unknown Contact") }}'
                            };
                        }),
                        pagination: {
                            more: (data.current_page || 1) < (data.last_page || 1)
                        }
                    };
                },
                error: function(xhr, status, error) {
                    console.error('Contact search error:', error);
                },
                cache: true
            },
            minimumInputLength: 0
        });

        // Handle selection change
        $('#triggerContacts').on('change', function() {
            const selectedCount = $(this).val() ? $(this).val().length : 0;
            updateContactCount(selectedCount);
        });
    }

    function updateContactCount(count) {
        const badge = document.getElementById('selectedContactCount');
        const btn = document.getElementById('triggerWorkflowBtn');

        if (count > 0) {
            badge.style.display = 'inline-flex';
            badge.querySelector('span').textContent = count;
            badge.classList.remove('empty');
            btn.disabled = false;
        } else {
            badge.style.display = 'none';
            badge.classList.add('empty');
            btn.disabled = true;
        }
    }
</script>
@endpush

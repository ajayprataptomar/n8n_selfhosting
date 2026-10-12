@extends('admin.layouts.app')
@push("style-include")
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

    /* Automation Dashboard Styles */
    .automation-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
    }

    .automation-stat-card {
        background: var(--card-bg, #fff);
        border-radius: 12px;
        padding: 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        border: 1px solid var(--border-color, #e9ecef);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .automation-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 0px;
        height: 100%;
        border-radius: 4px 0 0 4px;
    }

    .automation-stat-card.stat-primary::before { background: var(--color-primary); }
    .automation-stat-card.stat-success::before { background: var(--success-color, #10b981); }
    .automation-stat-card.stat-info::before { background: var(--info-color, #0ea5e9); }
    .automation-stat-card.stat-warning::before { background: var(--warning-color, #f59e0b); }
    .automation-stat-card.stat-danger::before { background: var(--danger-color, #ef4444); }

    .automation-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    }

    .automation-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .automation-stat-icon.icon-primary { background: var(--color-primary-light); color: var(--color-primary); }
    .automation-stat-icon.icon-success { background: rgba(16, 185, 129, 0.1); color: var(--success-color, #10b981); }
    .automation-stat-icon.icon-info { background: rgba(14, 165, 233, 0.1); color: var(--info-color, #0ea5e9); }
    .automation-stat-icon.icon-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning-color, #f59e0b); }
    .automation-stat-icon.icon-danger { background: rgba(239, 68, 68, 0.1); color: var(--danger-color, #ef4444); }

    .automation-stat-content h3 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
        line-height: 1;
        color: var(--text-color, #1f2937);
    }

    .automation-stat-content p {
        font-size: 0.813rem;
        color: var(--text-muted, #6b7280);
        margin: 0;
        font-weight: 500;
    }

    /* Workflow Cards - Grid View */
    .workflow-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 1.5rem;
    }

    .workflow-card {
        background: var(--card-bg, #fff);
        border-radius: 16px;
        border: 1px solid var(--border-color, #e9ecef);
        overflow: hidden;
        transition: all 0.3s ease;
        position: relative;
    }

    .workflow-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
    }

    .workflow-card-header {
        padding: 1.25rem 1.25rem 1rem;
        position: relative;
    }

    .workflow-card-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
    }

    .workflow-card.status-active .workflow-card-header::before {
        background: linear-gradient(90deg, var(--success-color, #10b981), #34d399);
    }

    .workflow-card.status-draft .workflow-card-header::before {
        background: linear-gradient(90deg, #9ca3af, #d1d5db);
        width: 0;
    }

    .workflow-card.status-paused .workflow-card-header::before {
        background: linear-gradient(90deg, var(--warning-color, #f59e0b), #fbbf24);
    }

    .workflow-card-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }

    .workflow-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        background: linear-gradient(135deg, var(--color-primary-light) 0%, var(--color-primary-light) 100%);
        color: var(--color-primary);
    }

    .workflow-card-actions {
        display: flex;
        gap: 0.5rem;
    }

    .workflow-card-actions .btn {
        padding: 0.375rem 0.625rem;
    }

    .workflow-card-title {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-color, #1f2937);
    }

    .workflow-card-title a {
        color: inherit;
        text-decoration: none;
    }

    .workflow-card-title a:hover {
        color: var(--color-primary);
    }

    .workflow-trigger-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.375rem 0.75rem;
        background: var(--color-primary-light);
        color: var(--color-primary);
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .workflow-card-body {
        padding: 0 1.25rem;
    }

    .workflow-stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
        padding: 1rem;
        background: var(--bg-light, #f8f9fa);
        border-radius: 12px;
        margin-bottom: 1rem;
    }

    .workflow-stat-item {
        text-align: center;
    }

    .workflow-stat-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-color, #1f2937);
        line-height: 1.2;
    }

    .workflow-stat-label {
        font-size: 0.688rem;
        color: var(--text-muted, #6b7280);
        text-transform: uppercase;
        font-weight: 500;
        letter-spacing: 0.5px;
    }

    .workflow-card-footer {
        padding: 1rem 1.25rem;
        border-top: 1px solid var(--border-color, #e9ecef);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .workflow-meta-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        font-size: 0.75rem;
        color: var(--text-muted, #6b7280);
    }

    .workflow-meta-info span {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .status-badge {
        padding: 0.375rem 0.75rem;
        border-radius: 50px;
        font-size: 0.688rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-badge.status-active {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }

    .status-badge.status-draft {
        background: rgba(107, 114, 128, 0.1);
        color: #6b7280;
    }

    .status-badge.status-paused {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
    }

    /* Quick Action Overlay */
    .workflow-quick-actions {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 1rem;
        background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        opacity: 0;
        transform: translateY(10px);
        transition: all 0.3s ease;
    }

    .workflow-card:hover .workflow-quick-actions {
        opacity: 1;
        transform: translateY(0);
    }

    .workflow-quick-actions .btn {
        padding: 0.5rem 1rem;
        font-size: 0.813rem;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
    }

    .empty-state-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--color-primary-light) 0%, var(--color-primary-light) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2rem;
        color: var(--color-primary);
    }

    .empty-state h3 {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .empty-state p {
        color: var(--text-muted, #6b7280);
        margin-bottom: 1.5rem;
    }

    /* Fix dropdown cropping */
    .workflow-item {
        overflow: visible;
    }

    .workflow-actions .dropdown-menu {
        z-index: 1050;
    }

    .workflow-list {
        overflow: visible;
    }

    .card-body {
        overflow: visible;
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

    .confirm-modal-icon.danger {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
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
                <a href="{{ route('admin.automation.help') }}" class="i-btn btn--info outline btn--md me-2">
                    <i class="ri-question-line"></i> {{ translate('Help') }}
                </a>
                <a href="{{ route('admin.automation.settings') }}" class="i-btn btn--dark outline btn--md me-2">
                    <i class="ri-settings-3-line"></i> {{ translate('Settings') }}
                </a>
                <a href="{{ route('admin.automation.create') }}" class="i-btn btn--primary btn--md">
                    <i class="ri-add-line"></i> {{ translate('Create Workflow') }}
                </a>
            </div>
        </div>

    <!-- Statistics -->
    <div class="automation-stats-grid mb-4">
        <div class="automation-stat-card stat-primary">
            <div class="automation-stat-icon icon-primary">
                <i class="ri-flow-chart"></i>
            </div>
            <div class="automation-stat-content">
                <h3>{{ number_format($stats['total_workflows']) }}</h3>
                <p>{{ translate('Total Workflows') }}</p>
            </div>
        </div>

        <div class="automation-stat-card stat-success">
            <div class="automation-stat-icon icon-success">
                <i class="ri-checkbox-circle-line"></i>
            </div>
            <div class="automation-stat-content">
                <h3>{{ number_format($stats['active_workflows']) }}</h3>
                <p>{{ translate('Active Workflows') }}</p>
            </div>
        </div>

        <div class="automation-stat-card stat-info">
            <div class="automation-stat-icon icon-info">
                <i class="ri-user-add-line"></i>
            </div>
            <div class="automation-stat-content">
                <h3>{{ number_format($stats['total_enrolled']) }}</h3>
                <p>{{ translate('Contacts Enrolled') }}</p>
            </div>
        </div>

        <div class="automation-stat-card stat-success">
            <div class="automation-stat-icon icon-success">
                <i class="ri-check-double-line"></i>
            </div>
            <div class="automation-stat-content">
                <h3>{{ number_format($stats['total_completed']) }}</h3>
                <p>{{ translate('Completed') }}</p>
            </div>
        </div>

        <div class="automation-stat-card stat-warning">
            <div class="automation-stat-icon icon-warning">
                <i class="ri-loader-4-line"></i>
            </div>
            <div class="automation-stat-content">
                <h3>{{ number_format($stats['active_executions']) }}</h3>
                <p>{{ translate('Running Now') }}</p>
            </div>
        </div>
    </div>

    <!-- Workflows Grid -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">{{ translate('Your Workflows') }}</h5>
            <div class="d-flex gap-2 align-items-center">
                <span class="text-muted small">{{ $workflows->total() }} {{ translate('workflows') }}</span>
            </div>
        </div>
        <div class="card-body">
            @if($workflows->count() > 0)
                <div class="workflow-grid">
                    @foreach($workflows as $workflow)
                        <div class="workflow-card status-{{ $workflow->status }}">
                            <div class="workflow-card-header">
                                <div class="workflow-card-top">
                                    <div class="workflow-icon">
                                        <i class="ri-flow-chart"></i>
                                    </div>
                                    <div class="workflow-card-actions">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown">
                                                <i class="ri-more-2-fill"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('admin.automation.edit', $workflow->uid) }}">
                                                        <i class="ri-edit-line me-2"></i>{{ translate('Edit') }}
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('admin.automation.show', $workflow->uid) }}">
                                                        <i class="ri-eye-line me-2"></i>{{ translate('View Details') }}
                                                    </a>
                                                </li>
                                                @if($workflow->status === 'draft' || $workflow->status === 'paused')
                                                    <li>
                                                        <a class="dropdown-item text-success" href="javascript:void(0)"
                                                           onclick="activateWorkflow('{{ $workflow->uid }}')">
                                                            <i class="ri-play-circle-line me-2"></i>{{ translate('Activate') }}
                                                        </a>
                                                    </li>
                                                @else
                                                    <li>
                                                        <a class="dropdown-item text-warning" href="javascript:void(0)"
                                                           onclick="pauseWorkflow('{{ $workflow->uid }}')">
                                                            <i class="ri-pause-circle-line me-2"></i>{{ translate('Pause') }}
                                                        </a>
                                                    </li>
                                                @endif
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0)"
                                                       onclick="duplicateWorkflow('{{ $workflow->uid }}')">
                                                        <i class="ri-file-copy-line me-2"></i>{{ translate('Duplicate') }}
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="javascript:void(0)"
                                                       onclick="deleteWorkflow('{{ $workflow->uid }}')">
                                                        <i class="ri-delete-bin-line me-2"></i>{{ translate('Delete') }}
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <h4 class="workflow-card-title">
                                    <a href="{{ route('admin.automation.show', $workflow->uid) }}">
                                        {{ $workflow->name }}
                                    </a>
                                </h4>

                                <span class="workflow-trigger-badge">
                                    <i class="ri-flashlight-line"></i>
                                    {{ \App\Models\Automation\AutomationWorkflow::TRIGGER_TYPES[$workflow->trigger_type]['label'] ?? $workflow->trigger_type }}
                                </span>
                            </div>

                            <div class="workflow-card-body">
                                <div class="workflow-stats-grid">
                                    <div class="workflow-stat-item">
                                        <div class="workflow-stat-value">{{ number_format($workflow->total_enrolled) }}</div>
                                        <div class="workflow-stat-label">{{ translate('Enrolled') }}</div>
                                    </div>
                                    <div class="workflow-stat-item">
                                        <div class="workflow-stat-value">{{ number_format($workflow->total_completed) }}</div>
                                        <div class="workflow-stat-label">{{ translate('Completed') }}</div>
                                    </div>
                                    <div class="workflow-stat-item">
                                        <div class="workflow-stat-value">{{ $workflow->completion_rate }}%</div>
                                        <div class="workflow-stat-label">{{ translate('Success') }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="workflow-card-footer">
                                <div class="workflow-meta-info">
                                    <span>
                                        <i class="ri-node-tree"></i>
                                        {{ $workflow->nodes_count }} {{ translate('nodes') }}
                                    </span>
                                    <span>
                                        <i class="ri-time-line"></i>
                                        {{ $workflow->created_at->diffForHumans() }}
                                    </span>
                                </div>
                                <span class="status-badge status-{{ $workflow->status }}">
                                    {{ ucfirst($workflow->status) }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    {{ $workflows->links() }}
                </div>
            @else
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="ri-flow-chart"></i>
                    </div>
                    <h3>{{ translate('No Workflows Yet') }}</h3>
                    <p>{{ translate('Create your first automation workflow to start engaging contacts automatically.') }}</p>
                    <a href="{{ route('admin.automation.create') }}" class="btn btn-primary">
                        <i class="ri-add-line me-1"></i>{{ translate('Create Your First Workflow') }}
                    </a>
                </div>
            @endif
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
</main>
@endsection

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
        if (type === 'danger') {
            iconEl.innerHTML = '<i class="ri-delete-bin-line"></i>';
            btnEl.className = 'btn btn-danger';
            btnEl.textContent = '{{ translate("Delete") }}';
        } else if (type === 'success') {
            iconEl.innerHTML = '<i class="ri-play-circle-line"></i>';
            btnEl.className = 'btn btn-success';
            btnEl.textContent = '{{ translate("Activate") }}';
        } else {
            iconEl.innerHTML = '<i class="ri-question-line"></i>';
            btnEl.className = 'btn btn-primary';
            btnEl.textContent = '{{ translate("Confirm") }}';
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
                })
                .catch(error => notify('error', 'An error occurred'));
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
                })
                .catch(error => notify('error', 'An error occurred'));
            },
            'warning'
        );
    }

    function duplicateWorkflow(uid) {
        showConfirmModal(
            '{{ translate("Duplicate Workflow") }}',
            '{{ translate("Create a copy of this workflow? The duplicate will be created as a draft.") }}',
            () => {
                fetch(`{{ url('admin/automation/duplicate') }}/${uid}`, {
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
                        if (data.data?.redirect_url) {
                            setTimeout(() => window.location.href = data.data.redirect_url, 1000);
                        }
                    } else {
                        notify('error', data.message);
                    }
                })
                .catch(error => notify('error', 'An error occurred'));
            },
            'warning'
        );
    }

    function deleteWorkflow(uid) {
        showConfirmModal(
            '{{ translate("Delete Workflow") }}',
            '{{ translate("Are you sure you want to delete this workflow? This action cannot be undone and all execution history will be lost.") }}',
            () => {
                fetch(`{{ url('admin/automation/delete') }}/${uid}`, {
                    method: 'DELETE',
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
            },
            'danger'
        );
    }
</script>
@endpush

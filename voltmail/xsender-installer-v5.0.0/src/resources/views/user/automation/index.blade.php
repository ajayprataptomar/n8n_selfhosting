@extends('user.layouts.app')
@push("style-include")
<style>
    .workflow-card {
        background: var(--card-bg, #fff);
        border-radius: 12px;
        padding: 1.5rem;
        border: 1px solid var(--border-color, #e9ecef);
        transition: all 0.3s ease;
        height: 100%;
    }

    .workflow-card:hover {
        border-color: var(--color-primary);
        box-shadow: 0 4px 12px var(--color-primary-light);
    }

    .workflow-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }

    .workflow-title {
        font-weight: 600;
        font-size: 1rem;
        color: var(--text-color, #1f2937);
        margin-bottom: 0.25rem;
    }

    .workflow-trigger {
        font-size: 0.75rem;
        color: var(--text-muted, #6b7280);
    }

    .workflow-status {
        padding: 0.25rem 0.75rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .workflow-status.active { background: rgba(16, 185, 129, 0.1); color: var(--success-color, #10b981); }
    .workflow-status.draft { background: rgba(107, 114, 128, 0.1); color: var(--secondary-color, #6b7280); }
    .workflow-status.paused { background: rgba(245, 158, 11, 0.1); color: var(--warning-color, #f59e0b); }

    .workflow-stats {
        display: flex;
        gap: 1.5rem;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border-color, #e9ecef);
    }

    .workflow-stat {
        text-align: center;
    }

    .workflow-stat-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-primary);
    }

    .workflow-stat-label {
        font-size: 0.75rem;
        color: var(--text-muted, #6b7280);
    }

    .workflow-actions {
        display: flex;
        gap: 0.5rem;
    }

    .stat-card {
        background: var(--card-bg, #fff);
        border-radius: 12px;
        padding: 1.25rem;
        border: 1px solid var(--border-color, #e9ecef);
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 3px;
        height: 100%;
        border-radius: 4px 0 0 4px;
        opacity: 0.7;
    }

    .stat-card.stat-primary::before { background: var(--color-primary); }
    .stat-card.stat-success::before { background: var(--success-color, #10b981); }
    .stat-card.stat-info::before { background: var(--info-color, #0ea5e9); }
    .stat-card.stat-warning::before { background: var(--warning-color, #f59e0b); }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    }

    .stat-card-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .stat-card-icon.icon-primary { background: var(--color-primary-light); color: var(--color-primary); }
    .stat-card-icon.icon-success { background: rgba(16, 185, 129, 0.1); color: var(--success-color, #10b981); }
    .stat-card-icon.icon-info { background: rgba(14, 165, 233, 0.1); color: var(--info-color, #0ea5e9); }
    .stat-card-icon.icon-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning-color, #f59e0b); }

    .stat-card-content h3 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
        line-height: 1;
        color: var(--text-color, #1f2937);
    }

    .stat-card-content p {
        font-size: 0.813rem;
        color: var(--text-muted, #6b7280);
        margin: 0;
        font-weight: 500;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: var(--card-bg, #fff);
        border-radius: 12px;
        border: 1px dashed var(--border-color, #e9ecef);
    }

    .empty-state-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: var(--bg-light, #f3f4f6);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
    }

    .empty-state-icon i {
        font-size: 2.5rem;
        color: var(--text-muted, #6b7280);
    }

    .empty-state h4 {
        color: var(--text-color, #1f2937);
        margin-bottom: 0.5rem;
    }

    .empty-state p {
        color: var(--text-muted, #6b7280);
        margin-bottom: 1.5rem;
    }

    .plan-limit-banner {
        background: var(--warning-bg, rgba(245, 158, 11, 0.1));
        border: 1px solid var(--warning-color, #f59e0b);
        border-radius: 12px;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .plan-limit-banner.warning {
        background: var(--danger-bg, rgba(239, 68, 68, 0.1));
        border-color: var(--danger-color, #ef4444);
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
                <a href="{{ route('user.automation.help') }}" class="i-btn btn--info outline btn--md me-2">
                    <i class="ri-question-line"></i> {{ translate('Help') }}
                </a>
                @if($canUseAutomation && isset($limits) && ($stats['total_workflows'] ?? 0) < ($limits['max_workflows'] ?? 10))
                    <a href="{{ route('user.automation.create') }}" class="i-btn btn--primary btn--md">
                        <i class="ri-add-line"></i> {{ translate('Create Workflow') }}
                    </a>
                @endif
            </div>
        </div>

    <!-- Plan Access Check -->
    @if(!$canUseAutomation)
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="ri-lock-line"></i>
            </div>
            <h4>{{ translate('Automation Not Available') }}</h4>
            <p>{{ translate('Your current plan does not include automation workflows. Upgrade your plan to access this feature.') }}</p>
            <a href="{{ route('user.plan.create') }}" class="i-btn btn--primary btn--md">
                <i class="ri-arrow-up-circle-line me-1"></i>{{ translate('Upgrade Plan') }}
            </a>
        </div>
    @else
        <!-- Plan Limits Banner -->
        @php
            $usedWorkflows = $stats['total_workflows'];
            $maxWorkflows = $limits['max_workflows'];
            $usagePercent = $maxWorkflows > 0 ? ($usedWorkflows / $maxWorkflows) * 100 : 0;
        @endphp
        @if($usagePercent >= 80)
            <div class="plan-limit-banner {{ $usagePercent >= 100 ? 'warning' : '' }}">
                <div>
                    <strong>{{ translate('Workflow Limit') }}:</strong>
                    {{ $usedWorkflows }}/{{ $maxWorkflows }} {{ translate('workflows used') }}
                </div>
                @if($usagePercent >= 100)
                    <a href="{{ route('user.plan.create') }}" class="i-btn btn--danger btn--sm">
                        {{ translate('Upgrade') }}
                    </a>
                @endif
            </div>
        @endif

        <!-- Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card stat-primary">
                    <div class="stat-card-icon icon-primary">
                        <i class="ri-flow-chart"></i>
                    </div>
                    <div class="stat-card-content">
                        <h3>{{ number_format($stats['total_workflows']) }}</h3>
                        <p>{{ translate('Total Workflows') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card stat-success">
                    <div class="stat-card-icon icon-success">
                        <i class="ri-play-circle-line"></i>
                    </div>
                    <div class="stat-card-content">
                        <h3>{{ number_format($stats['active_workflows']) }}</h3>
                        <p>{{ translate('Active') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card stat-info">
                    <div class="stat-card-icon icon-info">
                        <i class="ri-refresh-line"></i>
                    </div>
                    <div class="stat-card-content">
                        <h3>{{ number_format($stats['total_executions']) }}</h3>
                        <p>{{ translate('Total Executions') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card stat-warning">
                    <div class="stat-card-icon icon-warning">
                        <i class="ri-checkbox-circle-line"></i>
                    </div>
                    <div class="stat-card-content">
                        <h3>{{ number_format($stats['completed_executions']) }}</h3>
                        <p>{{ translate('Completed') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Workflows Grid -->
        @if($workflows->count() > 0)
            <div class="row g-4">
                @foreach($workflows as $workflow)
                    <div class="col-md-6 col-xl-4">
                        <div class="workflow-card">
                            <div class="workflow-header">
                                <div>
                                    <h5 class="workflow-title">{{ $workflow->name }}</h5>
                                    <span class="workflow-trigger">
                                        <i class="ri-flashlight-line me-1"></i>
                                        {{ \App\Models\Automation\AutomationWorkflow::TRIGGER_TYPES[$workflow->trigger_type]['label'] ?? $workflow->trigger_type }}
                                    </span>
                                </div>
                                <span class="workflow-status {{ $workflow->status }}">
                                    {{ ucfirst($workflow->status) }}
                                </span>
                            </div>

                            <div class="workflow-stats">
                                <div class="workflow-stat">
                                    <div class="workflow-stat-value">{{ $workflow->nodes_count }}</div>
                                    <div class="workflow-stat-label">{{ translate('Nodes') }}</div>
                                </div>
                                <div class="workflow-stat">
                                    <div class="workflow-stat-value">{{ $workflow->executions_count }}</div>
                                    <div class="workflow-stat-label">{{ translate('Executions') }}</div>
                                </div>
                            </div>

                            <div class="workflow-actions">
                                <a href="{{ route('user.automation.show', $workflow->uid) }}" class="icon-btn btn-ghost btn-sm" title="{{ translate('View') }}">
                                    <i class="ri-eye-line"></i>
                                </a>
                                <a href="{{ route('user.automation.edit', $workflow->uid) }}" class="icon-btn btn-ghost btn-sm" title="{{ translate('Edit') }}">
                                    <i class="ri-edit-line"></i>
                                </a>
                                @if($workflow->status !== 'active')
                                    <button class="icon-btn btn-ghost btn-sm text-success workflow-action-btn" data-action="activate" data-uid="{{ $workflow->uid }}" data-name="{{ $workflow->name }}" title="{{ translate('Activate') }}">
                                        <i class="ri-play-circle-line"></i>
                                    </button>
                                @else
                                    <button class="icon-btn btn-ghost btn-sm text-warning workflow-action-btn" data-action="pause" data-uid="{{ $workflow->uid }}" data-name="{{ $workflow->name }}" title="{{ translate('Pause') }}">
                                        <i class="ri-pause-circle-line"></i>
                                    </button>
                                @endif
                                <button class="icon-btn btn-ghost btn-sm text-info workflow-action-btn" data-action="duplicate" data-uid="{{ $workflow->uid }}" data-name="{{ $workflow->name }}" title="{{ translate('Duplicate') }}">
                                    <i class="ri-file-copy-line"></i>
                                </button>
                                <button class="icon-btn btn-ghost btn-sm text-danger workflow-action-btn" data-action="delete" data-uid="{{ $workflow->uid }}" data-name="{{ $workflow->name }}" title="{{ translate('Delete') }}">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if(method_exists($workflows, 'links'))
            <div class="mt-4">
                {{ $workflows->links() }}
            </div>
            @endif
        @else
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="ri-flow-chart"></i>
                </div>
                <h4>{{ translate('No Workflows Yet') }}</h4>
                <p>{{ translate('Create your first automation workflow to get started') }}</p>
                <a href="{{ route('user.automation.create') }}" class="i-btn btn--primary btn--md">
                    <i class="ri-add-line me-1"></i>{{ translate('Create Workflow') }}
                </a>
            </div>
        @endif
    @endif
    </div>
</main>

<!-- Workflow Action Confirmation Modal -->
<div class="modal fade confirm-modal" id="workflowActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-body">
                <div class="confirm-modal-icon" id="actionModalIcon">
                    <i id="actionModalIconClass"></i>
                </div>
                <h5 class="confirm-modal-title" id="actionModalTitle"></h5>
                <p class="confirm-modal-text" id="actionModalText"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
                <button type="button" class="i-btn btn--md" id="actionModalConfirm">{{ translate("Confirm") }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script-push')
<script>
(function() {
    'use strict';

    const actionModal = new bootstrap.Modal(document.getElementById('workflowActionModal'));
    let currentAction = null;
    let currentUid = null;

    const actionConfig = {
        activate: {
            icon: 'ri-play-circle-line',
            iconClass: 'success',
            title: '{{ translate("Activate Workflow") }}',
            text: '{{ translate("Are you sure you want to activate") }} "<span id="workflowName"></span>"?',
            btnClass: 'btn--success',
            btnText: '{{ translate("Activate") }}',
            url: '{{ url("user/automation/activate") }}',
            method: 'POST'
        },
        pause: {
            icon: 'ri-pause-circle-line',
            iconClass: 'warning',
            title: '{{ translate("Pause Workflow") }}',
            text: '{{ translate("Are you sure you want to pause") }} "<span id="workflowName"></span>"?',
            btnClass: 'btn--warning',
            btnText: '{{ translate("Pause") }}',
            url: '{{ url("user/automation/pause") }}',
            method: 'POST'
        },
        duplicate: {
            icon: 'ri-file-copy-line',
            iconClass: 'info',
            title: '{{ translate("Duplicate Workflow") }}',
            text: '{{ translate("Create a copy of") }} "<span id="workflowName"></span>"?',
            btnClass: 'btn--info',
            btnText: '{{ translate("Duplicate") }}',
            url: '{{ url("user/automation/duplicate") }}',
            method: 'POST'
        },
        delete: {
            icon: 'ri-delete-bin-line',
            iconClass: 'danger',
            title: '{{ translate("Delete Workflow") }}',
            text: '{{ translate("Are you sure you want to delete") }} "<span id="workflowName"></span>"? {{ translate("This action cannot be undone.") }}',
            btnClass: 'btn--danger',
            btnText: '{{ translate("Delete") }}',
            url: '{{ url("user/automation/delete") }}',
            method: 'DELETE'
        }
    };

    // Handle action button clicks
    document.querySelectorAll('.workflow-action-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const action = this.dataset.action;
            const uid = this.dataset.uid;
            const name = this.dataset.name;
            const config = actionConfig[action];

            if (!config) return;

            currentAction = action;
            currentUid = uid;

            // Update modal content
            document.getElementById('actionModalIcon').className = 'confirm-modal-icon ' + config.iconClass;
            document.getElementById('actionModalIconClass').className = config.icon;
            document.getElementById('actionModalTitle').textContent = config.title;
            document.getElementById('actionModalText').innerHTML = config.text.replace('<span id="workflowName"></span>', '<strong>' + name + '</strong>');

            const confirmBtn = document.getElementById('actionModalConfirm');
            confirmBtn.className = 'i-btn btn--md ' + config.btnClass;
            confirmBtn.textContent = config.btnText;

            actionModal.show();
        });
    });

    // Handle confirm button
    document.getElementById('actionModalConfirm').addEventListener('click', function() {
        if (!currentAction || !currentUid) return;

        const config = actionConfig[currentAction];
        const btn = this;

        btn.disabled = true;
        btn.innerHTML = '<i class="ri-loader-4-line ri-spin me-1"></i> {{ translate("Processing...") }}';

        fetch(`${config.url}/${currentUid}`, {
            method: config.method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            actionModal.hide();

            if (data.status) {
                notify('success', data.message);
                if (data.data?.redirect) {
                    setTimeout(() => window.location.href = data.data.redirect, 1000);
                } else {
                    setTimeout(() => location.reload(), 1000);
                }
            } else {
                notify('error', data.message);
                btn.disabled = false;
                btn.textContent = config.btnText;
            }
        })
        .catch(error => {
            actionModal.hide();
            notify('error', '{{ translate("An error occurred. Please try again.") }}');
            btn.disabled = false;
            btn.textContent = config.btnText;
        });
    });

    // Reset button state when modal is hidden
    document.getElementById('workflowActionModal').addEventListener('hidden.bs.modal', function() {
        const btn = document.getElementById('actionModalConfirm');
        if (currentAction && actionConfig[currentAction]) {
            btn.disabled = false;
            btn.textContent = actionConfig[currentAction].btnText;
        }
        currentAction = null;
        currentUid = null;
    });
})();
</script>
@endpush

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

    .settings-card {
        background: var(--card-bg, #fff);
        border-radius: 12px;
        border: 1px solid var(--border-color, #e9ecef);
        margin-bottom: 1.5rem;
    }

    .settings-card-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--border-color, #e9ecef);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .settings-card-header i {
        font-size: 1.5rem;
        color: var(--color-primary);
    }

    .settings-card-body {
        padding: 1.5rem;
    }

    .stat-box {
        background: var(--card-bg, #fff);
        border-radius: 8px;
        padding: 1.25rem;
        text-align: center;
        border: 1px solid var(--border-color, #e9ecef);
    }

    .stat-box .stat-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--color-primary);
    }

    .stat-box .stat-label {
        font-size: 0.875rem;
        color: var(--text-muted, #6b7280);
        margin-top: 0.25rem;
    }

    .code-block {
        background: #1e293b;
        border-radius: 8px;
        padding: 1rem 1.25rem;
        padding-right: 4rem;
        color: #fff !important;
        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
        font-size: 0.875rem;
        overflow-x: auto;
        margin: 1rem 0;
        position: relative;
        white-space: pre-wrap;
        word-break: break-all;
    }

    .code-block code {
        color: #fff !important;
        background: transparent !important;
        font-size: inherit;
        padding: 0;
        white-space: pre-wrap;
        word-break: break-all;
    }

    .code-block .copy-btn {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background: rgba(255,255,255,0.15);
        border: none;
        color: #e2e8f0;
        padding: 0.35rem 0.75rem;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.75rem;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .code-block .copy-btn:hover {
        background: rgba(255,255,255,0.25);
        color: #fff;
    }

    .code-block .copy-btn.copied {
        background: rgba(16, 185, 129, 0.3);
        color: #10b981;
    }

    .step-item {
        display: flex;
        gap: 1rem;
        padding: 1rem 0;
        border-bottom: 1px dashed var(--border-color, #e9ecef);
    }

    .step-item:last-child {
        border-bottom: none;
    }

    .step-number {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--color-primary);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        flex-shrink: 0;
    }

    .step-content h6 {
        margin-bottom: 0.25rem;
        font-weight: 600;
        color: var(--text-color, #1f2937);
    }

    .step-content p {
        margin-bottom: 0;
        color: var(--text-muted, #6b7280);
        font-size: 0.875rem;
    }

    .alert-info-custom {
        background: var(--color-primary-light, rgba(80, 70, 229, 0.1));
        border: 1px solid var(--color-primary);
        border-radius: 8px;
        padding: 1rem 1.25rem;
        color: var(--color-primary);
    }

    .alert-warning-custom {
        background: rgba(245, 158, 11, 0.1);
        border: 1px solid rgba(245, 158, 11, 0.2);
        border-radius: 8px;
        padding: 1rem 1.25rem;
        color: #92400e;
    }

    .alert-success-custom {
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid rgba(16, 185, 129, 0.2);
        border-radius: 8px;
        padding: 1rem 1.25rem;
        color: #065f46;
    }

    .tab-nav-custom {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .tab-nav-custom .tab-btn {
        padding: 0.5rem 1rem;
        border-radius: 6px;
        border: 1px solid var(--border-color, #e9ecef);
        background: var(--card-bg, #fff);
        color: var(--text-muted, #6b7280);
        cursor: pointer;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .tab-nav-custom .tab-btn.active {
        background: var(--color-primary);
        color: #fff;
        border-color: var(--color-primary);
    }

    .tab-nav-custom .tab-btn:hover:not(.active) {
        border-color: var(--color-primary);
        color: var(--color-primary);
    }

    .tab-content-custom {
        display: none;
    }

    .tab-content-custom.active {
        display: block;
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

    .confirm-modal-icon.info {
        background: var(--color-primary-light, rgba(80, 70, 229, 0.1));
        color: var(--color-primary);
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
                <h2>{{ translate('Automation Settings') }}</h2>
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
                    <i class="ri-arrow-left-line"></i> {{ translate('Back to Workflows') }}
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Stats Overview -->
            <div class="col-12 mb-4">
                <div class="row g-3">
                    <div class="col-sm-6 col-lg-3">
                        <div class="stat-box">
                            <div class="stat-value">{{ $templateStats['total'] }}</div>
                            <div class="stat-label">{{ translate('Total Templates') }}</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="stat-box">
                            <div class="stat-value">{{ $templateStats['active'] }}</div>
                            <div class="stat-label">{{ translate('Active Templates') }}</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="stat-box">
                            <div class="stat-value">{{ $templateStats['total_usage'] }}</div>
                            <div class="stat-label">{{ translate('Template Uses') }}</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="stat-box">
                            <div class="stat-value">{{ $queueStatus['pending_jobs'] }}</div>
                            <div class="stat-label">{{ translate('Pending Jobs') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Column -->
            <div class="col-lg-5">
                <!-- General Settings -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <i class="ri-settings-3-line"></i>
                        <div>
                            <h5 class="mb-0">{{ translate('General Settings') }}</h5>
                            <small class="text-muted">{{ translate('Configure automation behavior') }}</small>
                        </div>
                    </div>
                    <div class="settings-card-body">
                        <form action="{{ route('admin.automation.settings.update') }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="automation_enabled"
                                           id="automation_enabled" value="1" {{ $settings['automation_enabled'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="automation_enabled">
                                        {{ translate('Enable Automation System') }}
                                    </label>
                                </div>
                                <small class="text-muted">{{ translate('Turn off to disable all workflow executions') }}</small>
                            </div>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label">{{ translate('Max Workflows/User') }}</label>
                                    <input type="number" class="form-control" name="max_workflows_per_user"
                                           value="{{ $settings['max_workflows_per_user'] }}" min="1" max="1000">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ translate('Max Nodes/Workflow') }}</label>
                                    <input type="number" class="form-control" name="max_nodes_per_workflow"
                                           value="{{ $settings['max_nodes_per_workflow'] }}" min="5" max="100">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">{{ translate('Execution Timeout (minutes)') }}</label>
                                <input type="number" class="form-control" name="execution_timeout_minutes"
                                       value="{{ $settings['execution_timeout_minutes'] }}" min="1" max="1440">
                                <small class="text-muted">{{ translate('Max time a workflow can run') }}</small>
                            </div>

                            <hr>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="retry_failed_actions"
                                           id="retry_failed_actions" value="1" {{ $settings['retry_failed_actions'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="retry_failed_actions">
                                        {{ translate('Retry Failed Actions') }}
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">{{ translate('Retry Attempts') }}</label>
                                <input type="number" class="form-control" name="retry_attempts"
                                       value="{{ $settings['retry_attempts'] }}" min="0" max="10">
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="notify_on_failure"
                                           id="notify_on_failure" value="1" {{ $settings['notify_on_failure'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="notify_on_failure">
                                        {{ translate('Notify Admin on Failures') }}
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="i-btn btn--primary btn--md w-100">
                                <i class="ri-save-line me-1"></i> {{ translate('Save Settings') }}
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Templates Management -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <i class="ri-file-copy-2-line"></i>
                        <div>
                            <h5 class="mb-0">{{ translate('Workflow Templates') }}</h5>
                            <small class="text-muted">{{ translate('Pre-built templates for users') }}</small>
                        </div>
                    </div>
                    <div class="settings-card-body">
                        <p class="text-muted mb-3">
                            {{ translate('Seed default templates to provide users with ready-to-use workflow templates.') }}
                        </p>
                        <button type="button" class="i-btn btn--primary outline btn--md w-100" onclick="seedTemplates()">
                            <i class="ri-refresh-line me-1"></i> {{ translate('Seed Default Templates') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Documentation Column -->
            <div class="col-lg-7">
                <div class="settings-card">
                    <div class="settings-card-header">
                        <i class="ri-book-open-line"></i>
                        <div>
                            <h5 class="mb-0">{{ translate('Queue Worker Setup') }}</h5>
                            <small class="text-muted">{{ translate('Required for automation to work') }}</small>
                        </div>
                    </div>
                    <div class="settings-card-body">
                        @if($queueStatus['is_sync'])
                            <div class="alert-warning-custom mb-4">
                                <i class="ri-alert-line me-2"></i>
                                <strong>{{ translate('Warning:') }}</strong>
                                {{ translate('Queue driver is set to "sync". Workflows will execute immediately but may timeout. Configure a proper queue driver for production.') }}
                            </div>
                        @else
                            <div class="alert-success-custom mb-4">
                                <i class="ri-checkbox-circle-line me-2"></i>
                                {{ translate('Queue driver configured:') }} <strong>{{ $queueStatus['queue_driver'] }}</strong>
                            </div>
                        @endif

                        @php
                            $allQueues = 'automation,default,chat-whatsapp,regular-whatsapp,regular-sms,regular-email,campaign-whatsapp,campaign-sms,campaign-email,import-contacts,lead-scraping';
                        @endphp

                        <div class="alert-warning-custom mb-4">
                            <i class="ri-information-line me-2"></i>
                            <strong>{{ translate('Choose one approach:') }}</strong>
                            {{ translate('Cron job handles everything (no Supervisor needed). For high-volume instant processing (10,000+ messages/day), use Supervisor — but you still need a cron job for scheduled campaigns and maintenance.') }}
                        </div>

                        <!-- Tab Navigation -->
                        <div class="tab-nav-custom">
                            <button class="tab-btn active" onclick="showTab('cpanel')">{{ translate('cPanel / Shared Hosting') }}</button>
                            <button class="tab-btn" onclick="showTab('vps')">{{ translate('VPS / Dedicated') }}</button>
                            <button class="tab-btn" onclick="showTab('supervisor')">{{ translate('Supervisor (High Volume)') }}</button>
                        </div>

                        <!-- cPanel Tab -->
                        <div id="tab-cpanel" class="tab-content-custom active">
                            <div class="alert-info-custom mb-3">
                                <i class="ri-information-line me-2"></i>
                                {{ translate('For shared hosting with cPanel, a single cron job handles all background processing — no Supervisor needed.') }}
                            </div>

                            <div class="step-item">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h6>{{ translate('Access Cron Jobs') }}</h6>
                                    <p>{{ translate('Go to cPanel > Advanced > Cron Jobs') }}</p>
                                </div>
                            </div>

                            <div class="step-item">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <h6>{{ translate('Add Automation Cron (Handles Everything)') }}</h6>
                                    <p>{{ translate('Set to run every minute (* * * * *). This single command processes all queues, campaigns, and scheduled tasks.') }}</p>
                                    <div class="code-block">
                                        <button class="copy-btn" onclick="copyCode(this)">{{ translate('Copy') }}</button>
                                        <code>curl -s "{{ url('/automation/run') }}" > /dev/null 2>&1</code>
                                    </div>
                                    <p style="margin-top: 0.5rem; font-size: 0.8rem; color: var(--text-muted);">{{ translate('Alternative if curl is unavailable:') }}</p>
                                    <div class="code-block">
                                        <button class="copy-btn" onclick="copyCode(this)">{{ translate('Copy') }}</button>
                                        <code>wget -q -O /dev/null "{{ url('/automation/run') }}"</code>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- VPS Tab -->
                        <div id="tab-vps" class="tab-content-custom">
                            <div class="alert-info-custom mb-3">
                                <i class="ri-information-line me-2"></i>
                                {{ translate('For VPS/Dedicated servers, a single cron entry handles all background jobs via the Laravel scheduler — no Supervisor needed.') }}
                            </div>

                            <div class="step-item">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h6>{{ translate('Setup Scheduler Cron') }}</h6>
                                    <p>{{ translate('Run crontab -e and add this line. The scheduler handles campaigns, queue processing, and all maintenance tasks.') }}</p>
                                    <div class="code-block">
                                        <button class="copy-btn" onclick="copyCode(this)">{{ translate('Copy') }}</button>
                                        <code>* * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1</code>
                                    </div>
                                </div>
                            </div>

                            <div class="step-item">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <h6>{{ translate('Verify Scheduler is Running') }}</h6>
                                    <p>{{ translate('Wait 2 minutes, then check the system status on the Background Jobs page.') }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Supervisor Tab -->
                        <div id="tab-supervisor" class="tab-content-custom">
                            <div class="alert-info-custom mb-3">
                                <i class="ri-speed-up-line me-2"></i>
                                {{ translate('Recommended for high-volume operations (10,000+ messages/day). Supervisor provides instant, continuous queue processing with auto-restart on failure.') }}
                            </div>

                            <div class="step-item">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h6>{{ translate('Install Supervisor') }}</h6>
                                    <div class="code-block">
                                        <button class="copy-btn" onclick="copyCode(this)">{{ translate('Copy') }}</button>
                                        <code>sudo apt-get update && sudo apt-get install supervisor -y</code>
                                    </div>
                                </div>
                            </div>

                            <div class="step-item">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <h6>{{ translate('Create Config File') }}</h6>
                                    <p>{{ translate('Create file: /etc/supervisor/conf.d/xsender-worker.conf') }}</p>
                                    <div class="code-block">
                                        <button class="copy-btn" onclick="copyCode(this)">{{ translate('Copy') }}</button>
                                        <code>[program:xsender-worker]
process_name=%(program_name)s_%(process_num)02d
command=php {{ base_path() }}/artisan queue:work database --queue={{ $allQueues }} --sleep=3 --tries=3 --max-time=3600
directory={{ base_path() }}
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile={{ storage_path() }}/logs/worker.log
stopwaitsecs=3600</code>
                                    </div>
                                </div>
                            </div>

                            <div class="step-item">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <h6>{{ translate('Start Supervisor Workers') }}</h6>
                                    <div class="code-block">
                                        <button class="copy-btn" onclick="copyCode(this)">{{ translate('Copy') }}</button>
                                        <code>sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start xsender-worker:*</code>
                                    </div>
                                </div>
                            </div>

                            <div class="step-item">
                                <div class="step-number">4</div>
                                <div class="step-content">
                                    <h6>{{ translate('Add Scheduler Cron (Required Even With Supervisor)') }}</h6>
                                    <p>{{ translate('Supervisor handles queue processing, but cron is still required for scheduled campaigns, workflow triggers, and cleanup tasks.') }}</p>
                                    <div class="code-block">
                                        <button class="copy-btn" onclick="copyCode(this)">{{ translate('Copy') }}</button>
                                        <code>* * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1</code>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6><i class="ri-information-line me-2"></i>{{ translate('How Automation Works') }}</h6>
                        <ul class="mb-0" style="padding-left: 1.25rem; color: var(--text-muted);">
                            <li class="mb-2">{{ translate('When a workflow is activated, it listens for its configured trigger') }}</li>
                            <li class="mb-2">{{ translate('When triggered, an execution record is created and nodes are processed in sequence') }}</li>
                            <li class="mb-2">{{ translate('Actions (send SMS/Email/WhatsApp) are executed via the queue') }}</li>
                            <li class="mb-2">{{ translate('Wait nodes pause execution and schedule the next step') }}</li>
                            <li class="mb-2">{{ translate('Condition nodes evaluate rules and branch accordingly') }}</li>
                            <li>{{ translate('All execution logs are stored for debugging') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Confirmation Modal -->
<div class="confirm-modal-overlay" id="confirmModal">
    <div class="confirm-modal-box">
        <div class="confirm-modal-icon info" id="confirmModalIcon">
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
@endsection

@push('script-push')
<script>
    function showTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.tab-content-custom').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        // Show selected tab
        document.getElementById('tab-' + tabName).classList.add('active');
        event.target.classList.add('active');
    }

    function copyCode(btn) {
        const codeBlock = btn.parentElement;
        const code = codeBlock.querySelector('code').innerText;

        // Try modern clipboard API first, fallback to execCommand
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(code).then(() => {
                showCopied(btn);
            }).catch(() => {
                fallbackCopy(code, btn);
            });
        } else {
            fallbackCopy(code, btn);
        }
    }

    function fallbackCopy(text, btn) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-9999px';
        textArea.style.top = '-9999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            document.execCommand('copy');
            showCopied(btn);
        } catch (err) {
            notify('error', '{{ translate("Failed to copy") }}');
        }

        document.body.removeChild(textArea);
    }

    function showCopied(btn) {
        const originalText = btn.innerText;
        btn.innerText = '{{ translate("Copied!") }}';
        btn.classList.add('copied');
        setTimeout(() => {
            btn.innerText = originalText;
            btn.classList.remove('copied');
        }, 2000);
    }

    function seedTemplates() {
        showConfirmModal(
            '{{ translate("Seed Default Templates") }}',
            '{{ translate("This will create/update default workflow templates. Continue?") }}',
            () => {
                fetch('{{ route("admin.automation.templates.seed") }}', {
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
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        notify('error', data.message);
                    }
                })
                .catch(error => notify('error', '{{ translate("An error occurred") }}'));
            }
        );
    }

    // Confirmation Modal Functions
    let confirmModalCallback = null;

    function showConfirmModal(title, message, callback, type = 'info') {
        const modal = document.getElementById('confirmModal');
        const iconEl = document.getElementById('confirmModalIcon');
        const titleEl = document.getElementById('confirmModalTitle');
        const messageEl = document.getElementById('confirmModalMessage');

        titleEl.textContent = title;
        messageEl.textContent = message;
        confirmModalCallback = callback;

        iconEl.className = 'confirm-modal-icon ' + type;
        if (type === 'warning') {
            iconEl.innerHTML = '<i class="ri-alert-line"></i>';
        } else {
            iconEl.innerHTML = '<i class="ri-question-line"></i>';
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
</script>
@endpush

@extends('admin.layouts.app')

@push("style-include")
<style>

    .card-icon{
      width: 40px;
      height: 40px;
      border-radius: 50% !important;
      display: flex;
      justify-content: center;
      align-items: center;
    }
   .text--info    { color: var(--color-info) !important; }
   .text--warning { color: var(--color-warning) !important; }
   .text--danger  { color: var(--color-danger) !important; }

    /* ============================================================
       Terminal-style command block
       Replaces <input readonly> + copy-btn pattern with a proper
       <pre><code> block. Full-width, monospace, no truncation,
       horizontal scroll on long lines, copy button top-right.
       ============================================================ */
    .cmd-block {
        position: relative;
        background: #0f172a;
        color: #e2e8f0;
        border-radius: 8px;
        margin-bottom: 0;
        overflow: hidden;
        font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
        font-size: 13px;
        line-height: 1.55;
    }
    .cmd-block.cmd-block--light {
        background: #f8fafc;
        color: #0f172a;
        border: 1px solid #e2e8f0;
    }
    .cmd-block .cmd-block-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 8px 12px;
        background: rgba(255, 255, 255, 0.04);
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
    }
    .cmd-block--light .cmd-block-header {
        background: #f1f5f9;
        border-bottom-color: #e2e8f0;
        color: #64748b;
    }
    .cmd-block .cmd-block-label {
        display: flex; align-items: center; gap: 6px;
    }
    .cmd-block .cmd-block-label i { font-size: 14px; }
    .cmd-block .cmd-block-copy {
        background: transparent;
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: #cbd5e1;
        font-size: 11px;
        padding: 3px 10px;
        border-radius: 5px;
        cursor: pointer;
        display: inline-flex; align-items: center; gap: 4px;
        transition: all 0.15s ease;
    }
    .cmd-block--light .cmd-block-copy {
        border-color: #cbd5e1;
        color: #475569;
    }
    .cmd-block .cmd-block-copy:hover {
        background: rgba(99, 102, 241, 0.15);
        border-color: #6366f1;
        color: #fff;
    }
    .cmd-block--light .cmd-block-copy:hover {
        background: rgba(99, 102, 241, 0.08);
        color: #6366f1;
    }
    .cmd-block .cmd-block-copy.copied {
        background: rgba(16, 185, 129, 0.18);
        border-color: #10b981;
        color: #d1fae5;
    }
    .cmd-block--light .cmd-block-copy.copied {
        background: rgba(16, 185, 129, 0.12);
        color: #065f46;
    }
    .cmd-block pre {
        margin: 0;
        padding: 12px 14px;
        overflow-x: auto;
        white-space: pre;
        font: inherit;
        color: inherit;
        background: transparent;
        max-height: 360px;
    }
    .cmd-block pre code {
        font: inherit;
        color: inherit;
        background: transparent;
        padding: 0;
        white-space: inherit;
    }
    .cmd-block pre::-webkit-scrollbar { height: 6px; width: 6px; }
    .cmd-block pre::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.12);
        border-radius: 3px;
    }
    .cmd-block--light pre::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.12);
    }

    .cmd-block-help {
        font-size: 12px;
        color: var(--text-muted, #6b7280);
        margin: 6px 0 0 0;
    }

    /* Step circle polish */
    .setup-step { display: flex; gap: 14px; padding: 16px 0; border-bottom: 1px dashed rgba(0, 0, 0, 0.06); }
    .setup-step:last-child { border-bottom: 0; }
    .setup-step .step-number {
        flex-shrink: 0;
        width: 32px; height: 32px; border-radius: 50%;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.15));
        color: #6366f1;
        font-weight: 700;
        display: flex; align-items: center; justify-content: center;
        font-size: 14px;
    }
    .setup-step .step-content { flex: 1; min-width: 0; }
    .setup-step .step-content h6 { margin-bottom: 6px; }
</style>
@endpush

@section("panel")
<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ translate('Background Jobs & Scheduling') }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate("Dashboard") }}</a></li>
                            <li class="breadcrumb-item"><a href="#">{{ translate("System") }}</a></li>
                            <li class="breadcrumb-item active">{{ translate("Background Jobs") }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                <button type="button" class="i-btn btn--dark outline btn--md" id="refreshStatusBtn">
                    <i class="ri-refresh-line"></i> {{ translate("Refresh") }}
                </button>
            </div>
        </div>

        <x-hint-banner key="settings.cron.setup" />

        <!-- System Status Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <span class="card-icon  rounded" id="statusIconWrapper">
                                <i class="ri-loader-4-line ri-spin fs-5" id="statusIcon"></i>
                            </span>
                            <div>
                                <p class="mb-1 text-muted fs-13">{{ translate("System Status") }}</p>
                                <h5 class="mb-0 fs-16" id="healthStatus">{{ translate("Checking") }}...</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <span class="card-icon  bg--info-light rounded">
                                <i class="ri-time-line fs-5 text--info"></i>
                            </span>
                            <div>
                                <p class="mb-1 text-muted fs-13">{{ translate("Last Executed") }}</p>
                                <h5 class="mb-0 fs-16" id="lastRunTime">--</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <span class="card-icon  bg--warning-light rounded">
                                <i class="ri-hourglass-line fs-5 text--warning"></i>
                            </span>
                            <div>
                                <p class="mb-1 text-muted fs-13">{{ translate("Pending Jobs") }}</p>
                                <h5 class="mb-0 fs-16" id="pendingJobsCount">--</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <span class="card-icon  bg--danger-light rounded">
                                <i class="ri-error-warning-line fs-5 text--danger"></i>
                            </span>
                            <div>
                                <p class="mb-1 text-muted fs-13">{{ translate("Failed Jobs") }}</p>
                                <h5 class="mb-0 fs-16" id="failedJobsCount">--</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Warning Alert -->
        <div class="alert alert-soft-warning d-none mb-4 border border-5 shadow-md border-white" id="healthAlert" role="alert">
            <div class="d-flex gap-3">
                  <span class="card-icon  bg--warning rounded">
                                <i class="ri-alert-line fs-5 text-white"></i>
                            </span>
                <div>
                    <h6 class="alert-heading mb-1">{{ translate("Configuration Required") }}</h6>
                    <p class="mb-0 fs-16" id="healthAlertText"></p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card mb-4">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <h6 class="mb-1">{{ translate("Quick Actions") }}</h6>
                        <p class="mb-0 text-muted fs-13">{{ translate("Manually trigger automation or manage failed jobs") }}</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="i-btn btn--primary btn--sm" id="runNowBtn">
                            <i class="ri-play-fill"></i> {{ translate("Run Now") }}
                        </button>
                        <button type="button" class="i-btn btn--success outline btn--sm" id="retryFailedBtn">
                            <i class="ri-restart-line"></i> {{ translate("Retry Failed") }}
                        </button>
                        <button type="button" class="i-btn btn--danger outline btn--sm" id="clearFailedBtn">
                            <i class="ri-delete-bin-line"></i> {{ translate("Clear Failed") }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row g-4">
            <!-- Server Type Selection -->
            <div class="col-xl-4">
                <div class="card h-100">
                    <div class="card-header pt-3 pb-0">
                        <h6 class="card-title mb-0">{{ translate("Server Environment") }}</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted fs-13 mb-3">{{ translate("Select your hosting environment to view the appropriate setup instructions.") }}</p>

                        <div class="d-grid gap-2 mb-4">
                            <button type="button" class="btn btn-outline text-start p-3 server-type-btn active" data-type="cpanel">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="ri-cloud-line fs-4"></i>
                                    <div>
                                        <span class="d-block fw-semibold">{{ translate("Shared Hosting") }}</span>
                                        <span class="fs-12">{{ translate("cPanel, Plesk, DirectAdmin") }}</span>
                                    </div>
                                </div>
                            </button>
                            <button type="button" class="btn btn-outline text-start p-3 server-type-btn" data-type="vps">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="ri-server-line fs-4"></i>
                                    <div>
                                        <span class="d-block fw-semibold">{{ translate("VPS / Dedicated Server") }}</span>
                                        <span class="fs-12">{{ translate("SSH access required") }}</span>
                                    </div>
                                </div>
                            </button>
                        </div>

                        <hr>

                        <h6 class="mb-3 fs-14">{{ translate("Background Jobs Process:") }}</h6>
                        <ul class="list-unstyled mb-0 fs-13">
                            <li class="d-flex align-items-center gap-2 py-2 border-bottom">
                                <i class="ri-flow-chart text-primary fs-5"></i>
                                <span>{{ translate("Workflow Automations") }}</span>
                            </li>
                            <li class="d-flex align-items-center gap-2 py-2 border-bottom">
                                <i class="ri-whatsapp-line text-primary fs-5"></i>
                                <span>{{ translate("WhatsApp Messages") }}</span>
                            </li>
                            <li class="d-flex align-items-center gap-2 py-2 border-bottom">
                                <i class="ri-message-2-line text-primary fs-5"></i>
                                <span>{{ translate("SMS Messages") }}</span>
                            </li>
                            <li class="d-flex align-items-center gap-2 py-2 border-bottom">
                                <i class="ri-mail-line text-primary fs-5"></i>
                                <span>{{ translate("Email Messages") }}</span>
                            </li>
                            <li class="d-flex align-items-center gap-2 py-2 border-bottom">
                                <i class="ri-calendar-schedule-line text-primary fs-5"></i>
                                <span>{{ translate("Scheduled Campaigns") }}</span>
                            </li>
                            <li class="d-flex align-items-center gap-2 py-2 border-bottom">
                                <i class="ri-contacts-book-line text-primary fs-5"></i>
                                <span>{{ translate("Contact Imports") }}</span>
                            </li>
                            <li class="d-flex align-items-center gap-2 py-2">
                                <i class="ri-search-eye-line text-primary fs-5"></i>
                                <span>{{ translate("Lead Generation") }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Setup Instructions -->
            <div class="col-xl-8">
                <!-- Shared Hosting Panel -->
                <div class="card setup-panel" id="panel-cpanel">
                    <div class="card-header pt-3 d-flex align-items-center justify-content-between">
                        <h6 class="card-title mb-0">
                            <i class="ri-cloud-line me-2 text-primary"></i>{{ translate("Shared Hosting Configuration") }}
                        </h6>
                        <span class="badge bg-success-subtle text-success">{{ translate("Recommended") }}</span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-soft-info mb-4" role="alert">
                            <div class="d-flex gap-2 align-items-center">
                                <i class="ri-lightbulb-line fs-5"></i>
                                <p class="mb-0 fs-14">{{ translate("Add a single cron job to enable all background processing features.") }}</p>
                            </div>
                        </div>

                        @php $automationUrl = url('/automation/run'); @endphp

                        <div class="setup-steps">
                            <div class="setup-step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h6 class="mb-1 fs-15">{{ translate("Access Your Hosting Panel") }}</h6>
                                    <p class="text-muted fs-13 mb-0">{{ translate("Log in to cPanel, Plesk, or your hosting control panel.") }}</p>
                                </div>
                            </div>

                            <div class="setup-step">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <h6 class="mb-1 fs-15">{{ translate("Navigate to Cron Jobs") }}</h6>
                                    <p class="text-muted fs-13 mb-0">{{ translate("Look for 'Cron Jobs' or 'Scheduled Tasks' in the Advanced section.") }}</p>
                                </div>
                            </div>

                            <div class="setup-step">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <h6 class="mb-1 fs-15">{{ translate("Set Execution Frequency") }}</h6>
                                    <p class="text-muted fs-13 mb-2">{{ translate("Select 'Every Minute' or use this cron expression:") }}</p>
                                    @php $cronExpr = "* * * * *"; @endphp
                                    <div class="cmd-block">
                                        <div class="cmd-block-header">
                                            <span class="cmd-block-label"><i class="ri-time-line"></i>{{ translate("Cron Expression") }}</span>
                                            <button class="cmd-block-copy copy-btn" type="button" data-copy="{{ $cronExpr }}">
                                                <i class="ri-file-copy-line"></i> {{ translate("Copy") }}
                                            </button>
                                        </div>
                                        <pre><code>{{ $cronExpr }}</code></pre>
                                    </div>
                                </div>
                            </div>

                            <div class="setup-step">
                                <div class="step-number">4</div>
                                <div class="step-content">
                                    <h6 class="mb-1 fs-15">{{ translate("Enter the Command") }}</h6>
                                    <p class="text-muted fs-13 mb-2">{{ translate("Copy and paste this command:") }}</p>
                                    @php $curlCmd = 'curl -s "' . $automationUrl . '" > /dev/null 2>&1'; @endphp
                                    <div class="cmd-block mb-3">
                                        <div class="cmd-block-header">
                                            <span class="cmd-block-label"><i class="ri-terminal-line"></i>{{ translate("Shell command (curl)") }}</span>
                                            <button class="cmd-block-copy copy-btn" type="button" data-copy='{{ $curlCmd }}'>
                                                <i class="ri-file-copy-line"></i> {{ translate("Copy") }}
                                            </button>
                                        </div>
                                        <pre><code>{{ $curlCmd }}</code></pre>
                                    </div>
                                    <details class="fs-13">
                                        <summary class="text-primary cursor-pointer mb-2">{{ translate("Alternative command (if curl is unavailable)") }}</summary>
                                        @php $wgetCmd = 'wget -q -O /dev/null "' . $automationUrl . '"'; @endphp
                                        <div class="cmd-block">
                                            <div class="cmd-block-header">
                                                <span class="cmd-block-label"><i class="ri-terminal-line"></i>{{ translate("Shell command (wget)") }}</span>
                                                <button class="cmd-block-copy copy-btn" type="button" data-copy='{{ $wgetCmd }}'>
                                                    <i class="ri-file-copy-line"></i> {{ translate("Copy") }}
                                                </button>
                                            </div>
                                            <pre><code>{{ $wgetCmd }}</code></pre>
                                        </div>
                                    </details>
                                </div>
                            </div>

                            <div class="setup-step">
                                <div class="step-number">5</div>
                                <div class="step-content">
                                    <h6 class="mb-1 fs-15">{{ translate("Save and Verify") }}</h6>
                                    <p class="text-muted fs-13 mb-0">{{ translate("Save the cron job and wait 2 minutes. The status above will update when successful.") }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-soft-warning mt-4 mb-0" role="alert">
                            <p class="mb-0 fs-13"><i class="ri-information-line me-1"></i>{{ translate("Some hosting providers restrict cron to 5-minute intervals. In that case, use:") }} <code class="text-warning">*/5 * * * *</code></p>
                        </div>
                    </div>
                </div>

                <!-- VPS Panel -->
                <div class="card setup-panel d-none" id="panel-vps">
                    <div class="card-header py-3 d-flex align-items-center justify-content-between">
                        <h6 class="card-title mb-0">
                            <i class="ri-server-line me-2 text-info"></i>{{ translate("VPS / Dedicated Server Configuration") }}
                        </h6>
                        <span class="badge bg-info-subtle text-info">{{ translate("Best Performance") }}</span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-soft-warning mb-4" role="alert">
                            <p class="mb-0 fs-13"><i class="ri-information-line me-1"></i>
                                <strong>{{ translate("Choose one approach:") }}</strong>
                                {{ translate("Standard Setup (cron only) is sufficient for most use cases — no supervisor needed. For high-volume sending (10,000+ messages/day), use Supervisor for instant queue processing. If using Supervisor, you still need a cron job for scheduled campaigns and maintenance tasks.") }}
                            </p>
                        </div>

                        <ul class="nav nav-tabs card-header-tabs mb-4" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active fs-14" data-bs-toggle="tab" data-bs-target="#tab-simple">
                                    <i class="ri-timer-line me-1"></i>{{ translate("Standard Setup") }}
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link fs-14" data-bs-toggle="tab" data-bs-target="#tab-supervisor">
                                    <i class="ri-speed-up-line me-1"></i>{{ translate("High Volume") }}
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <!-- Standard Setup -->
                            <div class="tab-pane fade show active" id="tab-simple">
                                <div class="alert alert-soft-info mb-4" role="alert">
                                    <p class="mb-0 fs-14"><i class="ri-terminal-box-line me-1"></i>{{ translate("Configure a single cron entry via SSH terminal. This handles all background jobs including queue processing — no Supervisor needed.") }}</p>
                                </div>

                                @php $basePath = base_path(); @endphp

                                <div class="setup-steps">
                                    <div class="setup-step">
                                        <div class="step-number">1</div>
                                        <div class="step-content">
                                            <h6 class="mb-1 fs-15">{{ translate("Connect to Server via SSH") }}</h6>
                                            <p class="text-muted fs-13 mb-0">{{ translate("Use Terminal (Mac/Linux) or PuTTY (Windows) to connect.") }}</p>
                                        </div>
                                    </div>

                                    <div class="setup-step">
                                        <div class="step-number">2</div>
                                        <div class="step-content">
                                            <h6 class="mb-1 fs-15">{{ translate("Open Crontab Editor") }}</h6>
                                            <div class="cmd-block">
                                                <div class="cmd-block-header">
                                                    <span class="cmd-block-label"><i class="ri-terminal-line"></i>{{ translate("SSH command") }}</span>
                                                    <button class="cmd-block-copy copy-btn" type="button" data-copy="crontab -e">
                                                        <i class="ri-file-copy-line"></i> {{ translate("Copy") }}
                                                    </button>
                                                </div>
                                                <pre><code>crontab -e</code></pre>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="setup-step">
                                        <div class="step-number">3</div>
                                        <div class="step-content">
                                            <h6 class="mb-1 fs-15">{{ translate("Add Scheduler Entry") }}</h6>
                                            <p class="text-muted fs-13 mb-2">{{ translate("Add this line at the end of the file:") }}</p>
                                            @php $stdCronLine = "* * * * * cd {$basePath} && php artisan schedule:run >> /dev/null 2>&1"; @endphp
                                            <div class="cmd-block">
                                                <div class="cmd-block-header">
                                                    <span class="cmd-block-label"><i class="ri-time-line"></i>{{ translate("Cron line") }}</span>
                                                    <button class="cmd-block-copy copy-btn" type="button" data-copy="{{ $stdCronLine }}">
                                                        <i class="ri-file-copy-line"></i> {{ translate("Copy") }}
                                                    </button>
                                                </div>
                                                <pre><code>{{ $stdCronLine }}</code></pre>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="setup-step">
                                        <div class="step-number">4</div>
                                        <div class="step-content">
                                            <h6 class="mb-1 fs-15">{{ translate("Save and Exit") }}</h6>
                                            <p class="text-muted fs-13 mb-0">{{ translate("Press Ctrl+X, then Y, then Enter (nano) or type :wq (vim).") }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Supervisor Setup -->
                            <div class="tab-pane fade" id="tab-supervisor">
                                <div class="alert alert-soft-info mb-4" role="alert">
                                    <p class="mb-0 fs-14"><i class="ri-speed-up-line me-1"></i>{{ translate("Recommended for high-volume operations (10,000+ messages/day). Supervisor ensures continuous job processing.") }}</p>
                                </div>

                                @php
                                    $supervisorUser = 'www-data';
                                    $artisanPath = base_path('artisan');
                                    $logPath = base_path('storage/logs/worker.log');
                                    $queues = 'automation,default,chat-whatsapp,regular-whatsapp,regular-sms,regular-email,campaign-whatsapp,campaign-sms,campaign-email,import-contacts,lead-scraping';
                                @endphp

                                <div class="setup-steps">
                                    @php
                                        $supInstall = "sudo apt-get update && sudo apt-get install supervisor -y";
                                        $supEdit    = "sudo nano /etc/supervisor/conf.d/xsender-worker.conf";
                                        $supStart   = "sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start xsender-worker:*";
                                        $supCron    = "* * * * * cd {$basePath} && php artisan schedule:run >> /dev/null 2>&1";
                                    @endphp

                                    <div class="setup-step">
                                        <div class="step-number">1</div>
                                        <div class="step-content">
                                            <h6 class="mb-1 fs-15">{{ translate("Install Supervisor") }}</h6>
                                            <div class="cmd-block">
                                                <div class="cmd-block-header">
                                                    <span class="cmd-block-label"><i class="ri-terminal-line"></i>{{ translate("SSH command") }}</span>
                                                    <button class="cmd-block-copy copy-btn" type="button" data-copy="{{ $supInstall }}">
                                                        <i class="ri-file-copy-line"></i> {{ translate("Copy") }}
                                                    </button>
                                                </div>
                                                <pre><code>{{ $supInstall }}</code></pre>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="setup-step">
                                        <div class="step-number">2</div>
                                        <div class="step-content">
                                            <h6 class="mb-1 fs-15">{{ translate("Create Configuration File") }}</h6>
                                            <div class="cmd-block">
                                                <div class="cmd-block-header">
                                                    <span class="cmd-block-label"><i class="ri-terminal-line"></i>{{ translate("SSH command") }}</span>
                                                    <button class="cmd-block-copy copy-btn" type="button" data-copy="{{ $supEdit }}">
                                                        <i class="ri-file-copy-line"></i> {{ translate("Copy") }}
                                                    </button>
                                                </div>
                                                <pre><code>{{ $supEdit }}</code></pre>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="setup-step">
                                        <div class="step-number">3</div>
                                        <div class="step-content">
                                            <h6 class="mb-1 fs-15">{{ translate("Add Configuration") }}</h6>
                                            <p class="text-muted fs-13 mb-2">{{ translate("Paste this into the file opened in step 2:") }}</p>
                                            @php
$supervisorConfig = "[program:xsender-worker]
process_name=%(program_name)s_%(process_num)02d
command=php {$artisanPath} queue:work database --queue={$queues} --sleep=3 --tries=3 --max-time=3600
directory={$basePath}
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user={$supervisorUser}
numprocs=4
redirect_stderr=true
stdout_logfile={$logPath}
stopwaitsecs=3600";
                                            @endphp
                                            <div class="cmd-block">
                                                <div class="cmd-block-header">
                                                    <span class="cmd-block-label"><i class="ri-file-text-line"></i>{{ translate("supervisor config (.conf)") }}</span>
                                                    <button class="cmd-block-copy copy-btn" type="button" data-copy="{{ $supervisorConfig }}">
                                                        <i class="ri-file-copy-line"></i> {{ translate("Copy") }}
                                                    </button>
                                                </div>
                                                <pre><code>{{ $supervisorConfig }}</code></pre>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="setup-step">
                                        <div class="step-number">4</div>
                                        <div class="step-content">
                                            <h6 class="mb-1 fs-15">{{ translate("Start Supervisor Workers") }}</h6>
                                            <div class="cmd-block">
                                                <div class="cmd-block-header">
                                                    <span class="cmd-block-label"><i class="ri-terminal-line"></i>{{ translate("SSH command") }}</span>
                                                    <button class="cmd-block-copy copy-btn" type="button" data-copy="{{ $supStart }}">
                                                        <i class="ri-file-copy-line"></i> {{ translate("Copy") }}
                                                    </button>
                                                </div>
                                                <pre><code>{{ $supStart }}</code></pre>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="setup-step">
                                        <div class="step-number">5</div>
                                        <div class="step-content">
                                            <h6 class="mb-1 fs-15">{{ translate("Add Scheduler Cron (Required Even With Supervisor)") }}</h6>
                                            <p class="text-muted fs-13 mb-2">{{ translate("Supervisor handles queue processing, but cron is still required for scheduled campaigns, workflow triggers, cleanup tasks, and maintenance. Run") }} <code>crontab -e</code> {{ translate("and add:") }}</p>
                                            <div class="cmd-block">
                                                <div class="cmd-block-header">
                                                    <span class="cmd-block-label"><i class="ri-time-line"></i>{{ translate("Cron line") }}</span>
                                                    <button class="cmd-block-copy copy-btn" type="button" data-copy="{{ $supCron }}">
                                                        <i class="ri-file-copy-line"></i> {{ translate("Copy") }}
                                                    </button>
                                                </div>
                                                <pre><code>{{ $supCron }}</code></pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Terminal Commands -->
        <div class="card mt-4">
            <div class="card-header py-3 cursor-pointer" data-bs-toggle="collapse" data-bs-target="#terminalCommands">
                <div class="d-flex align-items-center justify-content-between">
                    <h6 class="card-title mb-0"><i class="ri-terminal-box-line me-2"></i>{{ translate("Terminal Commands") }}</h6>
                    <i class="ri-arrow-down-s-line fs-5 text-muted"></i>
                </div>
            </div>
            <div class="collapse" id="terminalCommands">
                <div class="card-body">
                    <p class="text-muted fs-13 mb-3">{{ translate("For local development or manual job processing:") }}</p>

                    @php $allQueues = 'automation,default,chat-whatsapp,regular-whatsapp,regular-sms,regular-email,campaign-whatsapp,campaign-sms,campaign-email,import-contacts,lead-scraping'; @endphp

                    @php
                        $tcQueueCmd = "php artisan queue:work database --queue={$allQueues}";
                    @endphp
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fs-13 text-muted mb-1">{{ translate("Run Scheduler (Continuous)") }}</label>
                            <div class="cmd-block">
                                <div class="cmd-block-header">
                                    <span class="cmd-block-label"><i class="ri-terminal-line"></i>artisan</span>
                                    <button class="cmd-block-copy copy-btn" type="button" data-copy="php artisan schedule:work">
                                        <i class="ri-file-copy-line"></i> {{ translate("Copy") }}
                                    </button>
                                </div>
                                <pre><code>php artisan schedule:work</code></pre>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-13 text-muted mb-1">{{ translate("Run Queue Worker") }}</label>
                            <div class="cmd-block">
                                <div class="cmd-block-header">
                                    <span class="cmd-block-label"><i class="ri-terminal-line"></i>artisan</span>
                                    <button class="cmd-block-copy copy-btn" type="button" data-copy="{{ $tcQueueCmd }}">
                                        <i class="ri-file-copy-line"></i> {{ translate("Copy") }}
                                    </button>
                                </div>
                                <pre><code>{{ $tcQueueCmd }}</code></pre>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-13 text-muted mb-1">{{ translate("Retry All Failed Jobs") }}</label>
                            <div class="cmd-block">
                                <div class="cmd-block-header">
                                    <span class="cmd-block-label"><i class="ri-terminal-line"></i>artisan</span>
                                    <button class="cmd-block-copy copy-btn" type="button" data-copy="php artisan queue:retry all">
                                        <i class="ri-file-copy-line"></i> {{ translate("Copy") }}
                                    </button>
                                </div>
                                <pre><code>php artisan queue:retry all</code></pre>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-13 text-muted mb-1">{{ translate("Clear All Failed Jobs") }}</label>
                            <div class="cmd-block">
                                <div class="cmd-block-header">
                                    <span class="cmd-block-label"><i class="ri-terminal-line"></i>artisan</span>
                                    <button class="cmd-block-copy copy-btn" type="button" data-copy="php artisan queue:flush">
                                        <i class="ri-file-copy-line"></i> {{ translate("Copy") }}
                                    </button>
                                </div>
                                <pre><code>php artisan queue:flush</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Confirmation Modal -->
<div class="modal fade confirm-modal" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-body">
                <div class="confirm-modal-icon" id="confirmIcon"><i id="confirmIconClass"></i></div>
                <h5 class="confirm-modal-title" id="confirmTitle"></h5>
                <p class="confirm-modal-text" id="confirmText"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
                <button type="button" class="i-btn btn--md" id="confirmActionBtn"></button>
            </div>
        </div>
    </div>
</div>
<!-- Run Now Modal -->
<div class="modal fade" id="runNowModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-semibold">
                    <i class="ri-play-circle-line me-2 text-primary"></i>{{ translate("Run Automation") }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="runNowCloseBtn"></button>
            </div>
            <div class="modal-body">
                <!-- Confirm State -->
                <div id="runNow-confirm">
                    <p class="text-muted fs-14 mb-3">{{ translate("This will manually trigger all background jobs including campaigns, queue processing, and scheduled tasks.") }}</p>
                    <div class="d-flex gap-2 justify-content-end">
                        <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
                        <button type="button" class="i-btn btn--primary btn--md" id="runNowConfirmBtn">
                            <i class="ri-play-fill me-1"></i> {{ translate("Run Now") }}
                        </button>
                    </div>
                </div>

                <!-- Processing State -->
                <div id="runNow-processing" class="d-none">
                    <div class="text-center py-3">
                        <div class="run-now-spinner mb-3">
                            <i class="ri-loader-4-line ri-spin" style="font-size: 2.5rem; color: var(--color-primary);"></i>
                        </div>
                        <h6 class="mb-1">{{ translate("Processing...") }}</h6>
                        <p class="text-muted fs-13 mb-0" id="runNowProcessText">{{ translate("Running campaigns and queue jobs") }}</p>
                    </div>

                    <div class="run-now-steps mt-3">
                        <div class="run-step" id="step-campaigns">
                            <i class="ri-loader-4-line ri-spin text-muted"></i>
                            <span>{{ translate("Processing campaigns") }}</span>
                        </div>
                        <div class="run-step" id="step-queues">
                            <i class="ri-time-line text-muted"></i>
                            <span>{{ translate("Processing queue jobs") }}</span>
                        </div>
                        <div class="run-step" id="step-cleanup">
                            <i class="ri-time-line text-muted"></i>
                            <span>{{ translate("Cleaning up stale jobs") }}</span>
                        </div>
                    </div>
                </div>

                <!-- Result State -->
                <div id="runNow-result" class="d-none">
                    <div class="text-center mb-3">
                        <div class="run-now-result-icon mb-2" id="runNowResultIcon">
                            <i class="ri-check-line"></i>
                        </div>
                        <h6 class="mb-1" id="runNowResultTitle"></h6>
                        <p class="text-muted fs-13 mb-0" id="runNowResultSubtitle"></p>
                    </div>

                    <div class="run-now-stats" id="runNowStats"></div>

                    <div class="d-flex gap-2 justify-content-end mt-3">
                        <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate("Close") }}</button>
                        <button type="button" class="i-btn btn--primary outline btn--md" id="runNowAgainBtn">
                            <i class="ri-refresh-line me-1"></i> {{ translate("Run Again") }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push("style-push")
<style>
.setup-panel { display: none; }
.setup-panel:not(.d-none) { display: block; }
.server-type-btn:hover { background-color: var(--color-primary); color: #fff; border-color: var(--color-primary); }
.server-type-btn.active { background-color: var(--color-primary-light); color: var(--color-primary); border-color: var(--color-primary); }
.server-type-btn.active .text-muted { color: rgba(255,255,255,0.8) !important; }
.cursor-pointer { cursor: pointer; }
.font-monospace { font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; }
.fs-12 { font-size: 0.75rem !important; }
.fs-13 { font-size: 0.8125rem !important; }
.fs-14 { font-size: 0.875rem !important; }
.fs-15 { font-size: 0.9375rem !important; }
.fs-16 { font-size: 1rem !important; }
details summary { list-style: none; cursor: pointer; }
details summary::-webkit-details-marker { display: none; }
.alert-soft-primary { background-color: rgba(var(--bs-primary-rgb), 0.1); border: none; color: var(--bs-primary); }
.alert-soft-warning { background-color: rgba(var(--bs-warning-rgb), 0.1); border: none; color: var(--bs-warning); }
.alert-soft-info { background-color: rgba(var(--bs-info-rgb), 0.1); border: none; color: var(--bs-info); }
.bg-success-subtle { background-color: rgba(var(--bs-success-rgb), 0.1); }
.bg-info-subtle { background-color: rgba(var(--bs-info-rgb), 0.1); }

/* Setup Steps */
.setup-steps { position: relative; }
.setup-step { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
.setup-step:last-child { margin-bottom: 0; }
.step-number {
    width: 32px; height: 32px; min-width: 32px;
    background: linear-gradient(40deg, var(--color-primary-light), transparent); color: var(--color-primary);font-weight:600;
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-weight: 600; font-size: 0.875rem;
}
.step-content { flex: 1; padding-top: 4px; padding-left: 10px }

/* Run Now Modal */
.run-now-steps { display: flex; flex-direction: column; gap: 0.5rem; }
.run-step {
    display: flex; align-items: center; gap: 0.625rem;
    padding: 0.5rem 0.75rem; border-radius: 6px;
    background: var(--color-gray-50, #f9fafb); font-size: 0.8125rem;
}
.run-step i { font-size: 1rem; min-width: 1rem; }
.run-step.done i { color: var(--bs-success) !important; }
.run-step.done span { color: var(--bs-success); }
.run-step.active i { color: var(--color-primary) !important; }
.run-step.failed i { color: var(--bs-danger) !important; }
.run-step.failed span { color: var(--bs-danger); }

.run-now-result-icon {
    width: 56px; height: 56px; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.75rem;
}
.run-now-result-icon.success { background: rgba(var(--bs-success-rgb), 0.1); color: var(--bs-success); }
.run-now-result-icon.error { background: rgba(var(--bs-danger-rgb), 0.1); color: var(--bs-danger); }

.run-now-stats {
    display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;
}
.run-stat {
    padding: 0.625rem 0.75rem; border-radius: 6px;
    background: var(--color-gray-50, #f9fafb);
}
.run-stat-label { font-size: 0.6875rem; color: var(--text-muted, #6b7280); text-transform: uppercase; letter-spacing: 0.5px; }
.run-stat-value { font-size: 0.9375rem; font-weight: 600; color: var(--text-color, #1f2937); }
</style>
@endpush

@push("script-push")
<script>
"use strict";

const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));

// Server type selection
document.querySelectorAll('.server-type-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.server-type-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        const type = this.dataset.type;
        document.querySelectorAll('.setup-panel').forEach(p => p.classList.add('d-none'));
        document.getElementById('panel-' + type).classList.remove('d-none');

        localStorage.setItem('xsender_server_type', type);
    });
});

// Copy to clipboard with fallback for non-HTTPS
function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    }
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.cssText = 'position:fixed;left:-9999px;top:-9999px';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    try { document.execCommand('copy'); } catch(e) {}
    document.body.removeChild(ta);
    return Promise.resolve();
}

// Copy buttons (works for both legacy .copy-btn and new .cmd-block-copy)
document.querySelectorAll('.copy-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        copyText(this.dataset.copy).then(() => {
            notify('success', '{{ translate("Copied to clipboard") }}');
            const icon = this.querySelector('i');
            const original = icon.className;
            icon.className = 'ri-check-line';
            this.classList.add('copied');
            setTimeout(() => {
                icon.className = original;
                this.classList.remove('copied');
            }, 1500);
        });
    });
});

// Health check
function refreshHealth() {
    const statusIcon = document.getElementById('statusIcon');
    const statusWrapper = document.getElementById('statusIconWrapper');
    const healthStatus = document.getElementById('healthStatus');

    statusIcon.className = 'ri-loader-4-line ri-spin fs-5';
    statusWrapper.style.background = 'var(--color-gray-100)';
    healthStatus.textContent = '{{ translate("Checking") }}...';

    fetch('{{ url("/automation/health") }}')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const h = data.data.health;

                if (h.is_healthy) {
                    statusIcon.className = 'ri-check-line fs-5 text-white';
                    statusWrapper.style.background = 'var(--bs-success)';
                    healthStatus.textContent = '{{ translate("Active") }}';
                } else {
                    statusIcon.className = 'ri-alert-line fs-5 text-white';
                    statusWrapper.style.background = 'var(--bs-warning)';
                    healthStatus.textContent = '{{ translate("Inactive") }}';
                }

                document.getElementById('lastRunTime').textContent = h.last_run_ago || '{{ translate("Never") }}';
                document.getElementById('pendingJobsCount').textContent = h.pending_queue_jobs || '0';
                document.getElementById('failedJobsCount').textContent = h.failed_queue_jobs || '0';

                const alert = document.getElementById('healthAlert');
                if (h.warnings?.length) {
                    document.getElementById('healthAlertText').textContent = h.warnings.join(' ');
                    alert.classList.remove('d-none');
                } else {
                    alert.classList.add('d-none');
                }
            }
        })
        .catch(() => {
            statusIcon.className = 'ri-error-warning-line fs-5 text-white';
            statusWrapper.style.background = 'var(--bs-danger)';
            healthStatus.textContent = '{{ translate("Error") }}';
        });
}

document.getElementById('refreshStatusBtn').addEventListener('click', refreshHealth);

document.getElementById('retryFailedBtn').addEventListener('click', function() {
    document.getElementById('confirmIcon').className = 'confirm-modal-icon success';
    document.getElementById('confirmIconClass').className = 'ri-restart-line';
    document.getElementById('confirmTitle').textContent = '{{ translate("Retry Failed Jobs") }}';
    document.getElementById('confirmText').textContent = '{{ translate("This will attempt to reprocess all failed jobs in the queue.") }}';
    document.getElementById('confirmActionBtn').className = 'i-btn btn--success btn--md';
    document.getElementById('confirmActionBtn').textContent = '{{ translate("Retry All") }}';
    document.getElementById('confirmActionBtn').onclick = function() {
        this.disabled = true;
        this.innerHTML = '<i class="ri-loader-4-line ri-spin"></i>';
        fetch('{{ url("/automation/retry-failed") }}')
            .then(r => r.json())
            .then(data => { notify(data.success ? 'success' : 'error', data.message); refreshHealth(); confirmModal.hide(); })
            .finally(() => { this.disabled = false; this.textContent = '{{ translate("Retry All") }}'; });
    };
    confirmModal.show();
});

document.getElementById('clearFailedBtn').addEventListener('click', function() {
    document.getElementById('confirmIcon').className = 'confirm-modal-icon danger';
    document.getElementById('confirmIconClass').className = 'ri-delete-bin-line';
    document.getElementById('confirmTitle').textContent = '{{ translate("Clear Failed Jobs") }}';
    document.getElementById('confirmText').textContent = '{{ translate("This will permanently remove all failed jobs from the queue.") }}';
    document.getElementById('confirmActionBtn').className = 'i-btn btn--danger btn--md';
    document.getElementById('confirmActionBtn').textContent = '{{ translate("Clear All") }}';
    document.getElementById('confirmActionBtn').onclick = function() {
        this.disabled = true;
        this.innerHTML = '<i class="ri-loader-4-line ri-spin"></i>';
        fetch('{{ url("/automation/clear-failed") }}')
            .then(r => r.json())
            .then(data => { notify(data.success ? 'success' : 'error', data.message); refreshHealth(); confirmModal.hide(); })
            .finally(() => { this.disabled = false; this.textContent = '{{ translate("Clear All") }}'; });
    };
    confirmModal.show();
});

// Run Now modal
const runNowModal = new bootstrap.Modal(document.getElementById('runNowModal'));

function runNowReset() {
    document.getElementById('runNow-confirm').classList.remove('d-none');
    document.getElementById('runNow-processing').classList.remove('d-none');
    document.getElementById('runNow-result').classList.remove('d-none');
    document.getElementById('runNow-confirm').classList.remove('d-none');
    document.getElementById('runNow-processing').classList.add('d-none');
    document.getElementById('runNow-result').classList.add('d-none');
    document.getElementById('runNowCloseBtn').disabled = false;

    // Reset step icons
    ['step-campaigns', 'step-queues', 'step-cleanup'].forEach(id => {
        const el = document.getElementById(id);
        el.className = 'run-step';
        el.querySelector('i').className = 'ri-time-line text-muted';
    });
}

function runNowSetStep(stepId, state) {
    const el = document.getElementById(stepId);
    el.className = 'run-step ' + state;
    const icon = el.querySelector('i');
    if (state === 'active') icon.className = 'ri-loader-4-line ri-spin text-primary';
    else if (state === 'done') icon.className = 'ri-check-line text-success';
    else if (state === 'failed') icon.className = 'ri-close-line text-danger';
}

function runNowExecute() {
    // Switch to processing state
    document.getElementById('runNow-confirm').classList.add('d-none');
    document.getElementById('runNow-processing').classList.remove('d-none');
    document.getElementById('runNowCloseBtn').disabled = true;

    // Animate steps
    runNowSetStep('step-campaigns', 'active');

    fetch('{{ url("/automation/run") }}')
        .then(r => r.json())
        .then(data => {
            // Mark all steps done
            runNowSetStep('step-campaigns', 'done');

            setTimeout(() => {
                runNowSetStep('step-queues', 'active');
                setTimeout(() => {
                    runNowSetStep('step-queues', 'done');
                    setTimeout(() => {
                        runNowSetStep('step-cleanup', 'active');
                        setTimeout(() => {
                            runNowSetStep('step-cleanup', 'done');
                            setTimeout(() => runNowShowResult(data), 300);
                        }, 400);
                    }, 200);
                }, 500);
            }, 300);
        })
        .catch(err => {
            runNowSetStep('step-campaigns', 'failed');
            runNowSetStep('step-queues', 'failed');
            runNowSetStep('step-cleanup', 'failed');
            setTimeout(() => {
                runNowShowResult({
                    success: false,
                    message: err.message || '{{ translate("Failed to connect to automation endpoint") }}'
                });
            }, 500);
        });
}

function runNowShowResult(data) {
    document.getElementById('runNow-processing').classList.add('d-none');
    document.getElementById('runNow-result').classList.remove('d-none');
    document.getElementById('runNowCloseBtn').disabled = false;

    const iconEl = document.getElementById('runNowResultIcon');
    const titleEl = document.getElementById('runNowResultTitle');
    const subtitleEl = document.getElementById('runNowResultSubtitle');
    const statsEl = document.getElementById('runNowStats');

    if (data.success) {
        const d = data.data || {};
        iconEl.className = 'run-now-result-icon success';
        iconEl.innerHTML = '<i class="ri-check-line"></i>';
        titleEl.textContent = '{{ translate("Automation Completed") }}';
        subtitleEl.textContent = d.note || '{{ translate("All tasks processed successfully") }}';

        statsEl.innerHTML = `
            <div class="run-stat">
                <div class="run-stat-label">{{ translate("Mode") }}</div>
                <div class="run-stat-value">${d.mode || '-'}</div>
            </div>
            <div class="run-stat">
                <div class="run-stat-label">{{ translate("Campaigns") }}</div>
                <div class="run-stat-value">${d.campaigns_processed ? '{{ translate("Processed") }}' : '{{ translate("Skipped") }}'}</div>
            </div>
            <div class="run-stat">
                <div class="run-stat-label">{{ translate("Jobs Processed") }}</div>
                <div class="run-stat-value">${d.jobs_processed || 0}</div>
            </div>
            <div class="run-stat">
                <div class="run-stat-label">{{ translate("Stale Cleaned") }}</div>
                <div class="run-stat-value">${d.stale_jobs_cleaned || 0}</div>
            </div>
        `;
    } else {
        iconEl.className = 'run-now-result-icon error';
        iconEl.innerHTML = '<i class="ri-error-warning-line"></i>';
        titleEl.textContent = '{{ translate("Automation Failed") }}';
        subtitleEl.textContent = data.message || '{{ translate("An error occurred while running automation") }}';
        statsEl.innerHTML = '';
    }

    // Refresh health stats
    refreshHealth();
}

document.getElementById('runNowBtn').addEventListener('click', function() {
    runNowReset();
    runNowModal.show();
});

document.getElementById('runNowConfirmBtn').addEventListener('click', runNowExecute);

document.getElementById('runNowAgainBtn').addEventListener('click', function() {
    runNowReset();
    runNowExecute();
});

document.addEventListener('DOMContentLoaded', function() {
    refreshHealth();
    const saved = localStorage.getItem('xsender_server_type');
    if (saved) {
        const btn = document.querySelector(`.server-type-btn[data-type="${saved}"]`);
        if (btn) btn.click();
    }
});
</script>
@endpush

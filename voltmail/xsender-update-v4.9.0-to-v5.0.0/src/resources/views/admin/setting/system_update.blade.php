@extends('admin.layouts.app')
@section("panel")

@push('style-push')
<link rel="stylesheet" href="{{ asset('assets/theme/update/css/update-system.css') }}">
<style>
/* ===== SERVER ENVIRONMENT PANEL ===== */
.us-env-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}
.us-env-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 10px;
    background: var(--card-bg, #fff);
    border: 1px solid var(--color-border-light, #e5e7eb);
    transition: var(--us-transition);
}
.us-env-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.us-env-icon--ok { background: rgba(var(--us-success-rgb), 0.1); color: rgb(var(--us-success-rgb)); }
.us-env-icon--warn { background: rgba(var(--us-warning-rgb), 0.1); color: rgb(var(--us-warning-rgb)); }
.us-env-icon--danger { background: rgba(var(--us-danger-rgb), 0.1); color: rgb(var(--us-danger-rgb)); }
.us-env-icon--info { background: rgba(var(--us-primary-rgb), 0.1); color: rgb(var(--us-primary-rgb)); }
.us-env-label { font-size: 12px; color: var(--text-secondary, #6b7280); margin-bottom: 2px; }
.us-env-value { font-size: 14px; font-weight: 600; color: var(--text-primary, #1f2937); }
.us-env-loading { text-align: center; padding: 20px; color: var(--text-secondary, #9ca3af); font-size: 14px; }

/* ===== UPLOAD SIZE WARNING ===== */
.us-upload-warning {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px 20px;
    border-radius: 10px;
    background: rgba(var(--us-warning-rgb), 0.06);
    border: 1px solid rgba(var(--us-warning-rgb), 0.15);
    margin-bottom: 20px;
    font-size: 13px;
    color: var(--text-secondary, #6b7280);
    line-height: 1.6;
}
.us-upload-warning i {
    font-size: 22px;
    color: rgb(var(--us-warning-rgb));
    flex-shrink: 0;
    margin-top: 2px;
}
.us-upload-warning strong { color: var(--text-primary, #1f2937); }

/* ===== MANUAL UPLOAD SECTION ===== */
.us-manual-section {
    border: 1px solid rgba(var(--us-primary-rgb), 0.12);
    border-radius: 12px;
    padding: 24px;
    background: rgba(var(--us-primary-rgb), 0.02);
    margin-bottom: 24px;
}
.us-manual-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary, #1f2937);
    margin-bottom: 16px;
}
.us-manual-title i {
    font-size: 22px;
    color: rgb(var(--us-primary-rgb));
}
.us-manual-steps {
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-bottom: 20px;
}
.us-manual-step {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-size: 13px;
    color: var(--text-secondary, #6b7280);
    line-height: 1.6;
}
.us-manual-step-num {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: rgb(var(--us-primary-rgb));
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 1px;
}
.us-manual-step code {
    font-size: 12px;
    background: rgba(var(--us-primary-rgb), 0.08);
    color: var(--color-primary, #6366f1);
    padding: 2px 6px;
    border-radius: 4px;
}

/* Scan Button */
.us-btn-scan {
    background: linear-gradient(135deg, rgba(var(--us-primary-rgb), 0.9), rgba(var(--us-primary-rgb), 1));
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 10px 22px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: var(--us-transition);
}
.us-btn-scan:hover { opacity: 0.9; }
.us-btn-scan:disabled { opacity: 0.5; cursor: not-allowed; }

/* Detected File Card */
.us-detected-file {
    display: none;
    margin-top: 16px;
    padding: 16px 20px;
    border-radius: 10px;
    background: rgba(var(--us-success-rgb), 0.05);
    border: 1px solid rgba(var(--us-success-rgb), 0.2);
}
.us-detected-file.active { display: block; }
.us-detected-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.us-detected-info {
    display: flex;
    align-items: center;
    gap: 12px;
}
.us-detected-info i {
    font-size: 28px;
    color: rgb(var(--us-success-rgb));
}
.us-detected-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary, #1f2937);
}
.us-detected-meta {
    font-size: 12px;
    color: var(--text-secondary, #6b7280);
    margin-top: 2px;
}
.us-btn-install-manual {
    background: linear-gradient(135deg, rgb(var(--us-success-rgb)), #16a34a);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 10px 22px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: var(--us-transition);
    box-shadow: 0 4px 12px rgba(var(--us-success-rgb), 0.25);
}
.us-btn-install-manual:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(var(--us-success-rgb), 0.35); }

/* Section Divider */
.us-section-divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 20px 0;
    color: var(--text-secondary, #9ca3af);
    font-size: 13px;
    font-weight: 500;
}
.us-section-divider::before,
.us-section-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--color-border-light, #e5e7eb);
}

/* Upload disabled state */
.us-upload-area.us-disabled {
    opacity: 0.4;
    pointer-events: none;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .us-env-grid { grid-template-columns: 1fr; }
    .us-detected-header { flex-direction: column; align-items: flex-start; }
}
</style>
@endpush

<main class="main-body">
    <div class="container-fluid px-0">

        <!-- Current Version & Status -->
        <div class="card mt-3">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                <h4 class="card-title mb-0">
                    {{ translate("System Update") }}
                </h4>
                <a href="{{ route('admin.system.database.health') }}" class="i-btn btn--primary outline btn--sm">
                    <i class="ri-stethoscope-line"></i> {{ translate("Database Health Check") }}
                </a>
            </div>
            <div class="card-body pt-1">
                <div class="us-version-banner">
                    <div class="us-version-left">
                        <div class="us-version-icon">
                            <i class="ri-shield-check-line"></i>
                        </div>
                        <div>
                            <div class="us-version-label">{{ translate("Current Version") }}</div>
                            <div class="us-version-value">
                                <span class="us-version-number">{{ translate("V") }}{{ site_settings("app_version", 1.1) }}</span>
                                <span class="us-version-dot"></span>
                                <span class="us-version-date">{{ translate("Installed") }}: {{ get_date_time(site_settings("system_installed_at", \Carbon\Carbon::now())) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Server Environment Check -->
                <div class="us-env-loading" id="envLoading">
                    <i class="ri-loader-4-line us-spin"></i> {{ translate("Checking server environment...") }}
                </div>
                <div class="us-env-grid" id="envGrid" style="display: none;"></div>

                <!-- Upload Size Warning (shown dynamically if server limit is too low) -->
                <div class="us-upload-warning" id="uploadWarning" style="display: none;">
                    <i class="ri-error-warning-fill"></i>
                    <div>
                        <strong>{{ translate("Upload Limit Too Small") }}</strong><br>
                        {{ translate("Your server allows a maximum upload of") }}
                        <strong id="warnMaxSize">-</strong>.
                        {{ translate("Update packages are typically 20-80MB. You can either increase your server's") }}
                        <code>upload_max_filesize</code> {{ translate("and") }} <code>post_max_size</code>
                        {{ translate("in php.ini, or use the Manual Upload method below.") }}
                    </div>
                </div>

                <!-- Step 1: Pre-Update Checklist -->
                <div class="us-checklist" style="margin-bottom: 24px;">
                    <div class="us-checklist-title">
                        <i class="ri-list-check-2"></i>
                        {{ translate("Step 1: Pre-Update Checklist") }}
                    </div>
                    <div class="us-checklist-items">
                        <label class="us-check-item">
                            <input type="checkbox" class="us-checkbox" data-check="backup">
                            <span class="us-check-mark"><i class="ri-check-line"></i></span>
                            <span>{{ translate("I have taken a full backup (files & database)") }}</span>
                        </label>
                        <label class="us-check-item">
                            <input type="checkbox" class="us-checkbox" data-check="tab">
                            <span class="us-check-mark"><i class="ri-check-line"></i></span>
                            <span>{{ translate("I will not close this tab during the update") }}</span>
                        </label>
                    </div>
                    <div id="checklistStatus" style="margin-top: 12px; display: none;">
                        <div style="display: flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 8px; background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.2); color: #059669; font-size: 13px; font-weight: 600;">
                            <i class="ri-checkbox-circle-line"></i>
                            {{ translate("Ready to update! Choose your update method below.") }}
                        </div>
                    </div>
                </div>

                <!-- Step 2: Choose Update Method -->
                <div id="updateMethods" style="opacity: 0.4; pointer-events: none; transition: all 0.3s;">
                    <h6 style="font-size: 14px; font-weight: 700; color: #374151; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                        <i class="ri-upload-cloud-2-line" style="color: #6366f1;"></i>
                        {{ translate("Step 2: Choose Update Method") }}
                    </h6>

                <!-- ======= TWO-COLUMN UPDATE METHODS ======= -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start;">

                    <!-- LEFT: Manual FTP Upload -->
                    <div style="background: var(--card-bg, #fff); border: 2px solid #e5e7eb; border-radius: 14px; padding: 28px; height: 100%;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #6366f1, #818cf8); display: flex; align-items: center; justify-content: center;">
                                <i class="ri-folder-upload-line" style="font-size: 18px; color: #fff;"></i>
                            </div>
                            <div>
                                <h6 style="margin: 0; font-size: 15px; font-weight: 700;">{{ translate("FTP / File Manager") }}</h6>
                                <small style="color: #9ca3af;">{{ translate("For cPanel & shared hosting") }}</small>
                            </div>
                        </div>

                        <div style="font-size: 13px; color: #6b7280; line-height: 1.7; margin-bottom: 20px;">
                            <div style="display: flex; gap: 10px; margin-bottom: 8px;">
                                <span style="width: 22px; height: 22px; border-radius: 50%; background: #eef2ff; color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0;">1</span>
                                <span>{{ translate("Upload ZIP to project root via FTP or File Manager") }}</span>
                            </div>
                            <div style="display: flex; gap: 10px; margin-bottom: 8px;">
                                <span style="width: 22px; height: 22px; border-radius: 50%; background: #eef2ff; color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0;">2</span>
                                <span>{{ translate("Place at same level as") }} <code style="background: #f3f4f6; padding: 1px 6px; border-radius: 4px;">src</code> {{ translate("folder") }}</span>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <span style="width: 22px; height: 22px; border-radius: 50%; background: #eef2ff; color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0;">3</span>
                                <span>{{ translate("Click scan — system detects and installs") }}</span>
                            </div>
                        </div>

                        <button type="button" class="us-btn-scan" id="scanBtn" onclick="scanForManualUpload()" style="width: 100%;">
                            <i class="ri-search-line"></i>
                            {{ translate("Scan for Updates") }}
                        </button>

                        <div class="us-detected-file" id="detectedFileCard">
                            <div class="us-detected-header">
                                <div class="us-detected-info">
                                    <i class="ri-file-zip-line"></i>
                                    <div>
                                        <div class="us-detected-name" id="detectedFileName">-</div>
                                        <div class="us-detected-meta" id="detectedFileMeta">-</div>
                                    </div>
                                </div>
                                <button type="button" class="us-btn-install-manual" id="installManualBtn" onclick="installManualUpdate()">
                                    <i class="ri-download-cloud-line"></i>
                                    {{ translate("Install") }}
                                </button>
                            </div>
                        </div>

                        <div style="margin-top: 12px; padding: 10px 14px; border-radius: 8px; background: #fef3c7; font-size: 12px; color: #92400e;">
                            <i class="ri-lightbulb-line"></i> {{ translate("Best for shared hosting with upload size limits") }}
                        </div>
                    </div>

                    <!-- RIGHT: Browser Upload -->
                    <div style="background: var(--card-bg, #fff); border: 2px solid #e5e7eb; border-radius: 14px; padding: 28px; height: 100%;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #10b981, #34d399); display: flex; align-items: center; justify-content: center;">
                                <i class="ri-upload-cloud-2-line" style="font-size: 18px; color: #fff;"></i>
                            </div>
                            <div>
                                <h6 style="margin: 0; font-size: 15px; font-weight: 700;">{{ translate("Browser Upload") }}</h6>
                                <small style="color: #9ca3af;">{{ translate("Quick & easy — drag and drop") }}</small>
                            </div>
                        </div>

                        <form action="{{ route('admin.system.update') }}" method="post" enctype="multipart/form-data" id="updateForm">
                            @csrf
                            <label for="updateFile" class="us-upload-area" id="uploadArea" style="margin-bottom: 16px; min-height: 160px;">
                                <div class="us-upload-default" id="uploadDefault">
                                    <i class="ri-upload-cloud-2-line us-upload-icon"></i>
                                    <div class="us-upload-text">{{ translate("Drop ZIP here") }}</div>
                                    <div class="us-upload-subtext">
                                        {{ translate("or click to browse") }} &mdash; .zip {{ translate("only") }}
                                        <span id="uploadLimitText"></span>
                                    </div>
                                </div>
                                <div class="us-upload-selected" id="uploadSelected" style="display:none;">
                                    <i class="ri-file-zip-line us-upload-icon us-upload-icon--file"></i>
                                    <div class="us-upload-text" id="uploadFileName"></div>
                                    <div class="us-upload-subtext" id="uploadFileSize"></div>
                                </div>
                                <input type="file" id="updateFile" name="updateFile" accept=".zip" hidden>
                            </label>

                    <!-- Progress Overlay (hidden by default) -->
                    <div class="us-progress-overlay" id="progressOverlay" style="display:none;">
                        <div class="us-progress-content">
                            <div class="us-progress-header">
                                <div class="us-progress-ring">
                                    <svg viewBox="0 0 50 50" class="us-progress-ring-svg">
                                        <circle cx="25" cy="25" r="22" fill="none" stroke-width="3" stroke="currentColor" opacity="0.12"/>
                                        <circle cx="25" cy="25" r="22" fill="none" stroke-width="3" stroke="currentColor" stroke-dasharray="40 200" stroke-linecap="round" class="us-progress-ring-arc"/>
                                    </svg>
                                    <i class="ri-download-cloud-line us-progress-ring-icon"></i>
                                </div>
                                <div class="us-progress-title" id="progressTitle">{{ translate("Updating System...") }}</div>
                                <div class="us-progress-desc">{{ translate("Please wait. This may take a few minutes. Do not close this tab or navigate away.") }}</div>
                            </div>
                            <div class="us-progress-timeline">
                                <div class="us-step active" id="step1">
                                    <div class="us-step-marker">
                                        <i class="us-step-marker-icon ri-upload-2-line"></i>
                                        <i class="us-step-marker-check ri-check-line"></i>
                                        <i class="us-step-marker-spinner ri-loader-4-line"></i>
                                    </div>
                                    <div class="us-step-info">
                                        <div class="us-step-name">{{ translate("Uploading") }}</div>
                                        <div class="us-step-status">{{ translate("Sending update package to server") }}</div>
                                    </div>
                                </div>
                                <div class="us-step" id="step2">
                                    <div class="us-step-marker">
                                        <i class="us-step-marker-icon ri-folder-zip-line"></i>
                                        <i class="us-step-marker-check ri-check-line"></i>
                                        <i class="us-step-marker-spinner ri-loader-4-line"></i>
                                    </div>
                                    <div class="us-step-info">
                                        <div class="us-step-name">{{ translate("Extracting") }}</div>
                                        <div class="us-step-status">{{ translate("Unpacking files into the application directory") }}</div>
                                    </div>
                                </div>
                                <div class="us-step" id="step3">
                                    <div class="us-step-marker">
                                        <i class="us-step-marker-icon ri-database-2-line"></i>
                                        <i class="us-step-marker-check ri-check-line"></i>
                                        <i class="us-step-marker-spinner ri-loader-4-line"></i>
                                    </div>
                                    <div class="us-step-info">
                                        <div class="us-step-name">{{ translate("Migrating") }}</div>
                                        <div class="us-step-status">{{ translate("Updating database schema and reconciling tables") }}</div>
                                    </div>
                                </div>
                                <div class="us-step" id="step4">
                                    <div class="us-step-marker">
                                        <i class="us-step-marker-icon ri-refresh-line"></i>
                                        <i class="us-step-marker-check ri-check-line"></i>
                                        <i class="us-step-marker-spinner ri-loader-4-line"></i>
                                    </div>
                                    <div class="us-step-info">
                                        <div class="us-step-name">{{ translate("Cache Clear") }}</div>
                                        <div class="us-step-status">{{ translate("Clearing routes, config, view and application caches") }}</div>
                                    </div>
                                </div>
                                <div class="us-step" id="step5">
                                    <div class="us-step-marker">
                                        <i class="us-step-marker-icon ri-check-double-line"></i>
                                        <i class="us-step-marker-check ri-check-line"></i>
                                        <i class="us-step-marker-spinner ri-loader-4-line"></i>
                                    </div>
                                    <div class="us-step-info">
                                        <div class="us-step-name">{{ translate("Done") }}</div>
                                        <div class="us-step-status">{{ translate("Update complete and ready to use") }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end" style="margin-top: 12px;">
                        <button type="submit" class="us-btn-update" id="updateBtn" disabled style="width: 100%;">
                            <i class="ri-download-cloud-line"></i>
                            {{ translate("Update Now") }}
                        </button>
                    </div>

                    <div style="margin-top: 12px; padding: 10px 14px; border-radius: 8px; background: #ecfdf5; font-size: 12px; color: #065f46;">
                        <i class="ri-lightning-charge-line"></i> {{ translate("Fastest method — upload directly from your browser") }}
                    </div>
                </form>
                    </div><!-- end right column -->
                </div><!-- end grid -->
                </div><!-- end #updateMethods -->
            </div>
        </div>

        <!-- Post-Update Instructions -->
        <div class="card mt-3">
            <div class="card-header">
                <h4 class="card-title">
                    {{ translate("Post-Update Instructions") }}
                </h4>
            </div>
            <div class="card-body pt-1">
                <div class="us-instructions">
                    <p class="us-instructions-intro">
                        {{ translate("Cache is cleared automatically after every update. If you still experience issues, follow these steps:") }}
                    </p>

                    <div class="us-instruction-group">
                        <div class="us-instruction-step">
                            <div class="us-step-number">1</div>
                            <div class="us-step-content">
                                <h6>{{ translate("Restart Node.js Services") }}</h6>
                                <p>{{ translate("If you are using PM2 for WhatsApp QR scanning or other Node.js services, restart them:") }}</p>
                                <div class="us-code-block">
                                    <code>pm2 restart all</code>
                                    <button type="button" class="us-copy-btn" data-copy="pm2 restart all"><i class="ri-file-copy-line"></i></button>
                                </div>
                            </div>
                        </div>

                        <div class="us-instruction-step">
                            <div class="us-step-number">2</div>
                            <div class="us-step-content">
                                <h6>{{ translate("Hard Refresh Your Browser") }}</h6>
                                <p>{{ translate("If styles or layouts appear broken after the update, perform a hard refresh:") }}</p>
                                <div class="us-key-combo">
                                    <kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>R</kbd>
                                    <span class="us-key-or">{{ translate("or") }}</span>
                                    <kbd>Cmd</kbd> + <kbd>Shift</kbd> + <kbd>R</kbd>
                                    <span class="us-key-label">(macOS)</span>
                                </div>
                            </div>
                        </div>

                        <div class="us-instruction-step">
                            <div class="us-step-number">3</div>
                            <div class="us-step-content">
                                <h6>{{ translate("Manual Cache Clear (If Needed)") }}</h6>
                                <p>{{ translate("If you still see old pages or errors, click the refresh button in the admin header, or run:") }}</p>
                                <div class="us-code-block">
                                    <code>php artisan optimize:clear</code>
                                    <button type="button" class="us-copy-btn" data-copy="php artisan optimize:clear"><i class="ri-file-copy-line"></i></button>
                                </div>
                            </div>
                        </div>

                        <div class="us-instruction-step">
                            <div class="us-step-number">4</div>
                            <div class="us-step-content">
                                <h6>{{ translate("Purge CDN Cache (If Applicable)") }}</h6>
                                <p>{{ translate("If you use Cloudflare or any other CDN, purge the cache from your CDN dashboard:") }}</p>
                                <div class="us-note-box">
                                    <i class="ri-cloud-line"></i>
                                    <span>{{ translate("Cloudflare Dashboard") }} &rarr; {{ translate("Caching") }} &rarr; {{ translate("Configuration") }} &rarr; <strong>{{ translate("Purge Everything") }}</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

@push('script-push')
<script>
"use strict";
(function($) {

    var formSubmitting = false;
    var allChecked = false;
    var uploadOk = true; // Whether server upload limits are adequate
    var detectedFilename = null;

    // ─────────────────────────────────────────────
    //  Server Environment Check
    // ─────────────────────────────────────────────

    function loadServerCheck() {
        $.ajax({
            url: "{{ route('admin.system.server.check') }}",
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    renderEnvGrid(response.data);
                } else {
                    $('#envLoading').html('<i class="ri-error-warning-line"></i> {{ translate("Could not check server environment") }}');
                }
            },
            error: function() {
                $('#envLoading').html('<i class="ri-error-warning-line"></i> {{ translate("Could not check server environment") }}');
            }
        });
    }

    function renderEnvGrid(data) {
        var minUploadBytes = 20 * 1024 * 1024; // 20MB minimum for updates
        var effectiveMax = data.effective_max_bytes || 0;
        uploadOk = effectiveMax >= minUploadBytes;

        var items = [
            {
                label: '{{ translate("Upload Limit") }}',
                value: data.effective_max_human || '-',
                icon: effectiveMax >= data.recommended_min_bytes ? 'ri-check-line' : (effectiveMax >= minUploadBytes ? 'ri-alert-line' : 'ri-close-line'),
                status: effectiveMax >= data.recommended_min_bytes ? 'ok' : (effectiveMax >= minUploadBytes ? 'warn' : 'danger')
            },
            {
                label: '{{ translate("PHP Version") }}',
                value: data.php_version || '-',
                icon: 'ri-code-s-slash-line',
                status: 'info'
            },
            {
                label: '{{ translate("Disk Space") }}',
                value: data.disk_free_human || '-',
                icon: data.disk_free_space > 500 * 1024 * 1024 ? 'ri-check-line' : 'ri-alert-line',
                status: data.disk_free_space > 500 * 1024 * 1024 ? 'ok' : 'warn'
            },
            {
                label: '{{ translate("Max Execution") }}',
                value: (data.max_execution_time || 0) + 's',
                icon: data.max_execution_time >= 120 || data.max_execution_time === 0 ? 'ri-check-line' : 'ri-alert-line',
                status: data.max_execution_time >= 120 || data.max_execution_time === 0 ? 'ok' : 'warn'
            },
            {
                label: '{{ translate("ZipArchive") }}',
                value: data.zip_available ? '{{ translate("Available") }}' : '{{ translate("Not Available") }}',
                icon: data.zip_available ? 'ri-check-line' : 'ri-close-line',
                status: data.zip_available ? 'ok' : 'danger'
            },
            {
                label: '{{ translate("Write Access") }}',
                value: data.writable ? '{{ translate("Writable") }}' : '{{ translate("Read Only") }}',
                icon: data.writable ? 'ri-check-line' : 'ri-close-line',
                status: data.writable ? 'ok' : 'danger'
            }
        ];

        var html = '';
        items.forEach(function(item) {
            html += '<div class="us-env-item">';
            html += '  <div class="us-env-icon us-env-icon--' + item.status + '"><i class="' + item.icon + '"></i></div>';
            html += '  <div><div class="us-env-label">' + item.label + '</div>';
            html += '  <div class="us-env-value">' + item.value + '</div></div>';
            html += '</div>';
        });

        $('#envGrid').html(html).show();
        $('#envLoading').hide();

        // Show upload limit text
        $('#uploadLimitText').text('({{ translate("max") }}: ' + data.effective_max_human + ')');

        // If upload limits too small, show warning and disable upload area
        if (!uploadOk) {
            $('#warnMaxSize').text(data.effective_max_human);
            $('#uploadWarning').show();
            $('#uploadArea').addClass('us-disabled');
            $('#updateBtn').prop('disabled', true);
        }

        validateChecklist();
    }

    // ─────────────────────────────────────────────
    //  Manual Upload Detection
    // ─────────────────────────────────────────────

    window.scanForManualUpload = function() {
        var $btn = $('#scanBtn');
        $btn.prop('disabled', true).html('<i class="ri-loader-4-line us-spin"></i> {{ translate("Scanning...") }}');

        $.ajax({
            url: "{{ route('admin.system.detect.manual.upload') }}",
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).html('<i class="ri-search-line"></i> {{ translate("Scan for Updates") }}');

                if (response.success && response.data && response.data.length > 0) {
                    var file = response.data[0]; // Show first found file
                    detectedFilename = file.filename;

                    $('#detectedFileName').text(file.filename);
                    $('#detectedFileMeta').text(file.size_human + ' — ' + file.modified);
                    $('#detectedFileCard').addClass('active');

                    notify('success', response.message);
                } else {
                    $('#detectedFileCard').removeClass('active');
                    detectedFilename = null;
                    notify('info', response.message || '{{ translate("No update files found") }}');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html('<i class="ri-search-line"></i> {{ translate("Scan for Updates") }}');
                notify('error', '{{ translate("Failed to scan for update files") }}');
            }
        });
    };

    window.installManualUpdate = function() {
        if (!detectedFilename) return;
        if (!allChecked) {
            notify('error', '{{ translate("Please complete the pre-update checklist first.") }}');
            return;
        }

        var $btn = $('#installManualBtn');
        $btn.prop('disabled', true).html('<i class="ri-loader-4-line us-spin"></i> {{ translate("Installing...") }}');

        // Show progress overlay
        showProgress('{{ translate("Installing Update...") }}');

        $.ajax({
            url: "{{ route('admin.system.install.manual.upload') }}",
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                filename: detectedFilename
            },
            dataType: 'json',
            timeout: 300000, // 5 minutes
            success: function(response) {
                completeProgress();
                if (response.success) {
                    notify('success', response.message);
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    hideProgress();
                    $btn.prop('disabled', false).html('<i class="ri-download-cloud-line"></i> {{ translate("Install Update") }}');
                    notify('error', response.message);
                }
            },
            error: function(xhr) {
                hideProgress();
                $btn.prop('disabled', false).html('<i class="ri-download-cloud-line"></i> {{ translate("Install Update") }}');
                var msg = '{{ translate("Update failed. Please check server logs.") }}';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                notify('error', msg);
            }
        });
    };

    // ─────────────────────────────────────────────
    //  Progress Overlay Helpers
    // ─────────────────────────────────────────────

    function showProgress(title) {
        $('#progressTitle').text(title || '{{ translate("Updating System...") }}');
        $('#step1, #step2, #step3, #step4, #step5').removeClass('active done');
        $('#step1').addClass('active');
        $('#progressOverlay').fadeIn(200);

        setTimeout(function() { $('#step1').addClass('done'); $('#step2').addClass('active'); }, 3000);
        setTimeout(function() { $('#step2').addClass('done'); $('#step3').addClass('active'); }, 8000);
        setTimeout(function() { $('#step3').addClass('done'); $('#step4').addClass('active'); }, 15000);
    }

    function completeProgress() {
        $('#step1, #step2, #step3, #step4').addClass('done').removeClass('active');
        $('#step5').addClass('active');
        setTimeout(function() { $('#step5').addClass('done'); }, 500);
    }

    function hideProgress() {
        $('#progressOverlay').fadeOut(200);
    }

    // ─────────────────────────────────────────────
    //  Checklist & File Upload
    // ─────────────────────────────────────────────

    function validateChecklist() {
        var checked = $('.us-checkbox:checked').length;
        var total = $('.us-checkbox').length;
        allChecked = checked === total;

        // Enable/disable update methods section based on checklist
        if (allChecked) {
            $('#updateMethods').css({'opacity': '1', 'pointer-events': 'auto'});
            $('#checklistStatus').slideDown(200);
        } else {
            $('#updateMethods').css({'opacity': '0.4', 'pointer-events': 'none'});
            $('#checklistStatus').slideUp(200);
        }

        var hasFile = $('#updateFile').val() !== '';
        $('#updateBtn').prop('disabled', !(allChecked && hasFile && uploadOk));
    }

    $('.us-checkbox').on('change', function() {
        var item = $(this).closest('.us-check-item');
        if ($(this).is(':checked')) {
            item.addClass('checked');
        } else {
            item.removeClass('checked');
        }
        validateChecklist();
    });

    // File upload
    $('#updateFile').on('change', function() {
        var file = this.files[0];
        if (file) {
            var sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            $('#uploadFileName').text(file.name);
            $('#uploadFileSize').text(sizeMB + ' MB');
            $('#uploadDefault').hide();
            $('#uploadSelected').show();
            $('#uploadArea').addClass('us-file-selected');
        } else {
            $('#uploadDefault').show();
            $('#uploadSelected').hide();
            $('#uploadArea').removeClass('us-file-selected');
        }
        validateChecklist();
    });

    // Form submit — show progress, prevent double submit
    $('#updateForm').on('submit', function(e) {
        if (formSubmitting) {
            e.preventDefault();
            return false;
        }

        if (!allChecked) {
            e.preventDefault();
            notify('error', '{{ translate("Please complete the pre-update checklist first.") }}');
            return false;
        }

        if (!$('#updateFile').val()) {
            e.preventDefault();
            notify('error', '{{ translate("Please select an update file.") }}');
            return false;
        }

        formSubmitting = true;

        // Show progress overlay
        showProgress();
        $('#updateBtn').prop('disabled', true).html('<i class="ri-loader-4-line us-spin"></i> {{ translate("Updating...") }}');
    });

    // Copy button for code blocks
    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;left:-9999px;top:-9999px';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try { document.execCommand('copy'); } catch(e) {}
        document.body.removeChild(ta);
        return Promise.resolve();
    }

    $('.us-copy-btn').on('click', function(e) {
        e.preventDefault();
        var text = $(this).data('copy');
        var $btn = $(this);
        copyText(text).then(function() {
            $btn.html('<i class="ri-check-line"></i>');
            setTimeout(function() { $btn.html('<i class="ri-file-copy-line"></i>'); }, 1500);
            notify('success', '{{ translate("Copied to clipboard") }}');
        });
    });

    // ─────────────────────────────────────────────
    //  Initialize
    // ─────────────────────────────────────────────
    loadServerCheck();

})(jQuery);
</script>
@endpush

@endsection

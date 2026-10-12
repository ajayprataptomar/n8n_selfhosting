@extends('admin.layouts.app')

@push('style-push')
<style>
/* v4.9 — mirrors partials/campaign/health_banner.blade.php's tokens
   so the admin System Health page inherits the exact same visual language
   as the campaign-scoped health banner. No new design system introduced. */
.health-icon { width:44px; height:44px; display:flex; align-items:center; justify-content:center; border-radius:10px; font-size:22px; flex-shrink:0; }
.health-icon--info    { background:rgba(59,130,246,0.12); color:#3b82f6; }
.health-icon--warning { background:rgba(245,158,11,0.12); color:#f59e0b; }
.health-icon--success { background:rgba(34,197,94,0.12);  color:#22c55e; }
.health-icon--danger  { background:rgba(239,68,68,0.12);  color:#ef4444; }

.health-check-row {
    display:flex; align-items:center; gap:10px; padding:12px 0;
    font-size:14px; border-bottom:1px solid rgba(0,0,0,0.06);
}
.health-check-row:last-child { border-bottom:0; }
.health-check-row .ri-checkbox-circle-fill { color:#22c55e; font-size:18px; }
.health-check-row .ri-error-warning-fill    { color:#ef4444; font-size:18px; }
.health-check-row .value-pill {
    margin-left:auto; padding:3px 10px; border-radius:4px;
    background:rgba(0,0,0,0.06); font-family:ui-monospace,monospace; font-size:12px;
}
.health-check-row .health-check-label { font-weight:600; min-width:180px; }
.health-check-row .health-check-msg   { color:#6b7280; flex:1; }
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
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Dashboard') }}</a></li>
                            <li class="breadcrumb-item active">{{ translate('System Health') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                <button type="button" id="refreshHealthBtn" class="i-btn btn--primary btn--md">
                    <i class="ri-refresh-line me-1"></i> {{ translate('Refresh') }}
                </button>
            </div>
        </div>

        {{-- Overall verdict banner --}}
        <div id="overallVerdict" class="card mb-4">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                    <div class="health-icon health-icon--info" id="overallIcon">
                        <i class="ri-pulse-line"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1" id="overallTitle">{{ translate('Running full diagnostic…') }}</h5>
                        <p class="text-muted mb-0" id="overallMessage">{{ translate('Please wait while we probe every subsystem.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Per-subsystem cards --}}
        <div class="row g-3" id="healthSections">
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary"></div>
            </div>
        </div>
    </div>
</main>
@endsection

@push('script-push')
<script>
(function () {
    const dataUrl = '{{ $diagnosticUrl }}';

    const sectionOrder = [
        { key: 'database',     title: '{{ translate("Database Connection") }}',      icon: 'ri-database-2-line' },
        { key: 'mysql_config', title: '{{ translate("MySQL Configuration") }}',     icon: 'ri-tools-line' },
        { key: 'schema',       title: '{{ translate("Database Schema") }}',         icon: 'ri-git-branch-line' },
        { key: 'cron',         title: '{{ translate("Campaign Cron Processor") }}', icon: 'ri-timer-line' },
        { key: 'queue',        title: '{{ translate("Queue Worker") }}',            icon: 'ri-inbox-line' },
        { key: 'cache',        title: '{{ translate("Cache Directories") }}',       icon: 'ri-file-copy-line' },
        { key: 'disk',         title: '{{ translate("Disk Space") }}',              icon: 'ri-hard-drive-2-line' },
        { key: 'permissions',  title: '{{ translate("File Permissions") }}',        icon: 'ri-lock-line' },
        { key: 'php',          title: '{{ translate("PHP Runtime") }}',             icon: 'ri-code-s-slash-line' },
        { key: 'wa_node',      title: '{{ translate("WhatsApp Node Service") }}',   icon: 'ri-whatsapp-line' },
        { key: 'wa_gateways',  title: '{{ translate("WhatsApp Gateways") }}',       icon: 'ri-plug-line' },
    ];

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c =>
            ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])
        );
    }

    function renderSection(cfg, section) {
        if (!section) return '';
        const healthy = !!section.healthy;
        const mod     = healthy ? 'success' : (section.value === 'not configured' ? 'info' : 'danger');
        const glyph   = healthy ? 'ri-checkbox-circle-fill' : 'ri-error-warning-fill';
        const value   = section.value ? '<span class="value-pill">' + esc(section.value) + '</span>' : '';

        return `<div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <div class="health-icon health-icon--${mod}">
                            <i class="${cfg.icon}"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h6 class="mb-0">${esc(cfg.title)}</h6>
                                <i class="${glyph}"></i>
                                ${value}
                            </div>
                            <p class="text-muted small mb-0">${esc(section.message || '')}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
    }

    function renderOverall(overall) {
        const icon = document.getElementById('overallIcon');
        const title = document.getElementById('overallTitle');
        const msg = document.getElementById('overallMessage');
        if (!overall) return;
        const healthy = !!overall.healthy;
        icon.className = 'health-icon health-icon--' + (healthy ? 'success' : 'warning');
        icon.innerHTML = '<i class="' + (healthy ? 'ri-checkbox-circle-fill' : 'ri-alert-line') + '"></i>';
        title.textContent = healthy
            ? '{{ translate("All systems healthy") }}'
            : '{{ translate("Attention required") }}';
        msg.textContent = overall.message || '';
    }

    async function loadHealth() {
        const container = document.getElementById('healthSections');
        container.innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border text-primary"></div></div>';
        try {
            const res = await fetch(dataUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            renderOverall(data.overall);
            container.innerHTML = sectionOrder.map(cfg => renderSection(cfg, data[cfg.key])).join('');
        } catch (e) {
            container.innerHTML = '<div class="col-12"><div class="alert alert-danger">{{ translate("Failed to load health report:") }} ' + esc(e.message) + '</div></div>';
            if (window.console) console.error('system-health load failed', e);
        }
    }

    document.getElementById('refreshHealthBtn')?.addEventListener('click', loadHealth);
    loadHealth();
})();
</script>
@endpush

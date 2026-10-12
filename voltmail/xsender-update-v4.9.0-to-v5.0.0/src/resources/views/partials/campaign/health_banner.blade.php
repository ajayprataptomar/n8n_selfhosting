{{--
    Campaign Health Banner
    ----------------------
    Detects "running but stuck at 0%" and surfaces an actionable warning + diagnostic.

    Required vars:
      - $campaign         : UnifiedCampaign model
      - $diagnosticUrl    : URL to fetch JSON diagnostic
      - $retryOrphansUrl  : POST URL to reset stuck dispatches
--}}

@php
    $isRunning      = $campaign->status === \App\Enums\Campaign\UnifiedCampaignStatus::RUNNING;
    $stuckAtZero    = $isRunning
        && $campaign->processed_contacts === 0
        && $campaign->started_at
        && $campaign->started_at->diffInMinutes(now()) >= 2;
@endphp

@if($isRunning)
<div class="card mb-4 campaign-health-banner @if($stuckAtZero) campaign-health-banner--warning @endif" id="campaignHealthBanner">
    <div class="card-body">
        <div class="d-flex align-items-start gap-3">
            <div class="flex-shrink-0">
                @if($stuckAtZero)
                    <div class="health-icon health-icon--warning">
                        <i class="ri-alert-line"></i>
                    </div>
                @else
                    <div class="health-icon health-icon--info">
                        <i class="ri-pulse-line"></i>
                    </div>
                @endif
            </div>
            <div class="flex-grow-1">
                <h6 class="mb-1">
                    @if($stuckAtZero)
                        {{ translate('Campaign appears stuck at 0%') }}
                    @else
                        {{ translate('Campaign Health') }}
                    @endif
                </h6>
                <p class="text-muted small mb-2" id="healthBannerSummary">
                    @if($stuckAtZero)
                        {{ translate('The campaign has been running for more than 2 minutes but no messages have been processed. Run a diagnostic to find out why.') }}
                    @else
                        {{ translate('Click "Run Diagnostic" to verify the cron, queue worker, dispatches and gateways are healthy.') }}
                    @endif
                </p>

                <div id="healthBannerDetails" class="d-none mt-2"></div>

                <div class="d-flex flex-wrap gap-2 mt-2">
                    <button type="button" class="i-btn btn--info btn--sm" id="runDiagnosticBtn" onclick="runCampaignDiagnostic()">
                        <i class="ri-stethoscope-line me-1"></i> {{ translate('Run Diagnostic') }}
                    </button>
                    <form action="{{ $retryOrphansUrl }}" method="POST" class="d-inline" onsubmit="return confirm('{{ translate('Reset all stuck dispatches back to PENDING so they retry on the next cron tick?') }}');">
                        @csrf
                        <button type="submit" class="i-btn btn--warning outline btn--sm">
                            <i class="ri-refresh-line me-1"></i> {{ translate('Retry Stuck Dispatches') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.campaign-health-banner { border-left: 4px solid #3b82f6; }
.campaign-health-banner--warning { border-left-color: #f59e0b; background: rgba(245, 158, 11, 0.04); }
.health-icon {
    width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;
    border-radius: 8px; font-size: 20px;
}
.health-icon--info { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
.health-icon--warning { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }
.health-check-row { display: flex; align-items: center; gap: 8px; padding: 6px 0; font-size: 13px; }
.health-check-row .ri-checkbox-circle-fill { color: #22c55e; }
.health-check-row .ri-error-warning-fill { color: #ef4444; }
.health-check-row .ri-information-fill { color: #3b82f6; }
.health-recent-error {
    background: rgba(239, 68, 68, 0.06); border-radius: 4px; padding: 6px 8px;
    margin-top: 4px; font-size: 12px; color: #991b1b;
}
</style>

<script>
(function () {
    if (window.__campaignHealthBannerWired) return;
    window.__campaignHealthBannerWired = true;

    window.runCampaignDiagnostic = async function () {
        const btn = document.getElementById('runDiagnosticBtn');
        const details = document.getElementById('healthBannerDetails');
        const summary = document.getElementById('healthBannerSummary');

        if (!btn || !details) return;

        btn.disabled = true;
        const originalLabel = btn.innerHTML;
        btn.innerHTML = '<i class="ri-loader-4-line ri-spin me-1"></i> {{ translate("Running…") }}';

        try {
            const res = await fetch('{{ $diagnosticUrl }}', { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();

            details.classList.remove('d-none');
            details.innerHTML = renderDiagnosticReport(data);

            if (data.overall && data.overall.healthy) {
                summary.textContent = '{{ translate("All checks passed. Campaign infrastructure is healthy.") }}';
            } else {
                summary.textContent = '{{ translate("Issues detected — see details below.") }}';
            }
        } catch (e) {
            details.classList.remove('d-none');
            details.innerHTML = '<div class="alert alert-danger small mb-0">{{ translate("Diagnostic failed:") }} ' + e.message + '</div>';
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalLabel;
        }
    };

    function renderDiagnosticReport(d) {
        let html = '';
        const sections = [
            { key: 'processor',  title: '{{ translate("Cron Processor") }}' },
            { key: 'queue',      title: '{{ translate("Queue") }}' },
            { key: 'dispatches', title: '{{ translate("Dispatches") }}' },
            { key: 'gateways',   title: '{{ translate("Gateways") }}' },
        ];

        sections.forEach(s => {
            const section = d[s.key];
            if (!section) return;
            const icon = section.healthy
                ? 'ri-checkbox-circle-fill'
                : 'ri-error-warning-fill';
            html += `<div class="health-check-row">
                <i class="${icon}"></i>
                <strong>${s.title}:</strong>
                <span>${escapeHtml(section.message || '')}</span>
            </div>`;
        });

        if (d.errors && d.errors.count > 0) {
            html += '<div class="mt-2"><strong style="font-size:13px;">{{ translate("Recent dispatch errors") }}:</strong></div>';
            d.errors.errors.forEach(err => {
                html += `<div class="health-recent-error">
                    <strong>${escapeHtml(err.channel)}</strong>
                    <span class="text-muted">(${escapeHtml(err.when)})</span>:
                    ${escapeHtml(err.error)}
                </div>`;
            });
        }

        return html;
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
})();
</script>
@endif

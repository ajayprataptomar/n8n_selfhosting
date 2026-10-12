@extends('install.layouts.master')
@section('content')
    <div class="installer-section">
        <div class="container ">
            <div class="row justify-content-center">
                <div class="col-lg-11">
                    @include('install.partials.progress_bar')
                    <form action="{{route('install.db.store')}}" method="post">
                        @csrf
                        <div class="installer-wrapper bg--light">
                            <div class="each-slide">
                                <div class="row g-3">
                                    <div class="col-lg-6">
                                        <div class="form-inner">
                                            <label for="db_host">
                                                Database Host
                                            </label>
                                            <input name="db_host" value="localhost" type="text" id="db_host" placeholder="Ex:localhost">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-inner">
                                            <label for="db_port">
                                                Database Port
                                            </label>
                                            <input name="db_port"  value="3306" type="number" id="db_port" placeholder="Database port">
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="form-inner">
                                            <label for="db_port">
                                                Database name
                                            </label>
                                            <input name="db_database" value="{{old('db_database')}}"  type="text" id="db_database" placeholder="Database Name">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-inner">
                                            <label for="db_username">
                                                Database Username
                                            </label>
                                            <input name="db_username" value="{{old('db_username')}}" type="text" id="db_username" placeholder="Database Username">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-inner auth-input">
                                        <span class="auth-input-icon toggle-password">
                                            <i class="bi bi-eye toggle-icon "></i>
                                        </span>

                                            <label for="db_password">
                                                Database Password
                                            </label>
                                            <input name="db_password" class="toggle-input" value="{{old('db_password')}}" type="password" id="db_password" placeholder="Database Password">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- v4.9 MySQL compatibility pre-flight results.
                             Populated by AJAX after "Run Compatibility Check".
                             Reuses the .health-icon / .health-check-row / health-recent-error
                             classes from partials/campaign/health_banner.blade.php so we don't
                             ship a new design system for this one screen. --}}
                        <div id="dbPreflightResult" class="mt-3 d-none"></div>

                        <div class="text-center mt-4">
                            <div class="d-flex gap-2 justify-content-end flex-wrap">
                                <button type="button" id="dbPreflightBtn" class="i-btn btn--md btn--info outline">
                                    <i class="bi bi-shield-check me-1"></i> {{ translate("Run Compatibility Check") }}
                                </button>
                                <button type="submit" class="ai--btn i-btn btn--md btn--primary">
                                    {{trans("default.btn_next")}} <i class="bi bi-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<style>
/* v4.9 — mirrors partials/campaign/health_banner.blade.php's design tokens
   so pre-flight results look consistent with the in-app health banner. */
.health-icon { width:40px; height:40px; display:flex; align-items:center; justify-content:center; border-radius:8px; font-size:20px; }
.health-icon--info { background:rgba(59,130,246,0.12); color:#3b82f6; }
.health-icon--warning { background:rgba(245,158,11,0.12); color:#f59e0b; }
.health-icon--success { background:rgba(34,197,94,0.12); color:#22c55e; }
.health-check-row { display:flex; align-items:center; gap:8px; padding:8px 0; font-size:13px; border-bottom:1px solid rgba(0,0,0,0.05); }
.health-check-row:last-child { border-bottom:0; }
.health-check-row .ri-checkbox-circle-fill { color:#22c55e; }
.health-check-row .ri-error-warning-fill { color:#ef4444; }
.health-check-row .value-pill { margin-left:auto; padding:2px 8px; border-radius:4px; background:rgba(0,0,0,0.05); font-family:ui-monospace,monospace; font-size:12px; }
.health-recent-error { background:rgba(239,68,68,0.06); border-radius:4px; padding:6px 8px; margin-top:4px; font-size:12px; color:#991b1b; }
</style>

<script>
(function () {
    var btn      = document.getElementById('dbPreflightBtn');
    var resultEl = document.getElementById('dbPreflightResult');
    if (!btn || !resultEl) return;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function renderReport(d) {
        var sections = [
            { key: 'server',            title: '{{ translate("Database Server Version") }}' },
            { key: 'engine',            title: '{{ translate("Default Storage Engine") }}' },
            { key: 'row_format',        title: '{{ translate("InnoDB Row Format") }}' },
            { key: 'charset',           title: '{{ translate("Server Character Set") }}' },
            { key: 'primary_key_rule',  title: '{{ translate("sql_require_primary_key") }}' },
        ];
        var overallOk = !!(d.overall && d.overall.healthy);
        var iconMod   = overallOk ? 'success' : 'warning';
        var iconGlyph = overallOk ? 'ri-checkbox-circle-fill' : 'ri-alert-line';

        var html = '<div class="card"><div class="card-body">'
                 + '<div class="d-flex align-items-start gap-3">'
                 + '<div class="health-icon health-icon--' + iconMod + '"><i class="' + iconGlyph + '"></i></div>'
                 + '<div class="flex-grow-1">'
                 + '<h6 class="mb-1">' + esc(overallOk ? '{{ translate("MySQL server is compatible") }}' : '{{ translate("MySQL configuration issues detected") }}') + '</h6>'
                 + '<p class="text-muted small mb-2">' + esc((d.overall && d.overall.message) || '') + '</p>';

        sections.forEach(function (s) {
            var row = d[s.key];
            if (!row) return;
            var glyph = row.healthy ? 'ri-checkbox-circle-fill' : 'ri-error-warning-fill';
            html += '<div class="health-check-row">'
                  + '<i class="' + glyph + '"></i>'
                  + '<strong>' + esc(s.title) + ':</strong>'
                  + '<span>' + esc(row.message || '') + '</span>'
                  + (row.value ? '<span class="value-pill">' + esc(row.value) + '</span>' : '')
                  + '</div>';
        });

        if (d.errors && d.errors.count > 0) {
            html += '<div class="mt-2"><strong style="font-size:13px;">{{ translate("Blockers to resolve before installation") }}:</strong></div>';
            d.errors.errors.forEach(function (er) {
                html += '<div class="health-recent-error"><strong>' + esc(er.section) + '</strong>: ' + esc(er.message) + '</div>';
            });
        }

        html += '</div></div></div></div>';
        return html;
    }

    btn.addEventListener('click', function () {
        var form = document.querySelector('form[action="{{ route("install.db.store") }}"]');
        if (!form) return;

        var fd = new FormData(form);
        btn.disabled = true;
        var originalLabel = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> {{ translate("Checking…") }}';
        resultEl.classList.remove('d-none');
        resultEl.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> <span class="text-muted small ms-2">{{ translate("Probing MySQL configuration…") }}</span></div>';

        fetch('{{ route("install.db.preflight") }}', {
            method: 'POST',
            body: fd,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
        .then(function (payload) {
            if (!payload.ok) {
                resultEl.innerHTML = '<div class="alert alert-danger small mb-0">' + esc((payload.body && payload.body.message) || '{{ translate("Pre-flight request failed.") }}') + '</div>';
                return;
            }
            resultEl.innerHTML = renderReport(payload.body);
        })
        .catch(function (err) {
            resultEl.innerHTML = '<div class="alert alert-danger small mb-0">{{ translate("Pre-flight failed:") }} ' + esc(err && err.message) + '</div>';
            if (window.console) console.error('db-preflight fetch failed', err);
        })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = originalLabel;
        });
    });
})();
</script>

@endsection
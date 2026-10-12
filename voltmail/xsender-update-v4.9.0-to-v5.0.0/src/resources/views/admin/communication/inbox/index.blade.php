@extends('admin.layouts.app')

@push('style-push')
<style>
/* v5.0 Sprint 1 — Unified Inbox styles.
   Design tokens copy from partials/campaign/health_banner.blade.php
   (health-icon) + lead-stat-styles (card, page-header). No new palette. */
.inbox-shell { display:grid; grid-template-columns: 340px 1fr; gap:16px; min-height: calc(100vh - 220px); }
@media (max-width: 991px) { .inbox-shell { grid-template-columns: 1fr; } .inbox-shell .inbox-thread { display: none; } .inbox-shell.thread-open .inbox-list { display: none; } .inbox-shell.thread-open .inbox-thread { display: flex; } }

.inbox-list { display:flex; flex-direction:column; background:var(--bs-body-bg); border:1px solid var(--bs-border-color); border-radius:12px; overflow:hidden; }
.inbox-list-header { padding:12px; border-bottom:1px solid var(--bs-border-color); }
.inbox-list-filters { display:flex; gap:8px; align-items:center; margin-top:8px; flex-wrap:wrap; }
.inbox-list-body { flex:1; overflow-y:auto; max-height: calc(100vh - 300px); }

.inbox-row { display:flex; gap:10px; padding:12px; border-bottom:1px solid var(--bs-border-color); cursor:pointer; transition:background 0.15s; }
.inbox-row:hover { background: rgba(0,0,0,0.03); }
.inbox-row.active { background: rgba(59,130,246,0.08); border-left: 3px solid #3b82f6; padding-left: 9px; }
.inbox-avatar { width:40px; height:40px; border-radius:50%; background:#e5e7eb; color:#374151; display:flex; align-items:center; justify-content:center; font-weight:600; font-size:14px; flex-shrink:0; position:relative; }
.inbox-avatar .channel-glyph { position:absolute; bottom:-2px; right:-2px; width:16px; height:16px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:10px; color:#fff; border:2px solid var(--bs-body-bg); }
.inbox-avatar .channel-glyph i { line-height: 1; }
.inbox-meta { flex:1; min-width:0; }
.inbox-name { display:flex; justify-content:space-between; align-items:center; gap:8px; }
.inbox-name-text { font-weight:600; font-size:14px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.inbox-time { font-size:11px; color:#6b7280; flex-shrink:0; }
.inbox-preview { color:#6b7280; font-size:13px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-top:2px; }
.inbox-unread-badge { background:#f25d6d; color:#fff; font-size:11px; font-weight:600; border-radius:10px; padding:1px 7px; margin-left:6px; }

.inbox-thread { display:flex; flex-direction:column; background:var(--bs-body-bg); border:1px solid var(--bs-border-color); border-radius:12px; overflow:hidden; }
.inbox-thread-header { padding:14px; border-bottom:1px solid var(--bs-border-color); display:flex; align-items:center; gap:12px; }
.inbox-thread-body { flex:1; overflow-y:auto; padding:16px; background:rgba(0,0,0,0.02); }
.inbox-thread-footer { padding:14px; border-top:1px solid var(--bs-border-color); background:rgba(0,0,0,0.02); }
.inbox-empty { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#9ca3af; padding:40px; text-align:center; }

/* v5.0 UI polish — WhatsApp-style bubbles with tail, media, status ticks */
.msg-row { display:flex; margin-bottom:10px; animation: msgFadeIn 0.18s ease-out; }
.msg-row.msg-outbound { justify-content:flex-end; }
.msg-cell { max-width: 72%; }
.msg-bubble {
    padding:8px 12px; border-radius:14px; background:var(--bs-body-bg);
    border:1px solid var(--bs-border-color); font-size:14px; line-height:1.45;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
    word-wrap: break-word; white-space: pre-wrap;
    position: relative; color: var(--bs-body-color);
}
.msg-row.msg-outbound .msg-bubble {
    background: linear-gradient(180deg, #dcf8c6 0%, #d0f2b6 100%);
    border-color:#c7edb0; color:#0f172a;
}
[data-bs-theme="dark"] .msg-row.msg-outbound .msg-bubble {
    background: linear-gradient(180deg, #1f5d3a 0%, #164d2e 100%);
    border-color:#0f3b23; color:#e2e8f0;
}
[data-bs-theme="dark"] .inbox-row:hover { background: rgba(255,255,255,0.04); }
[data-bs-theme="dark"] .inbox-row.active { background: rgba(59,130,246,0.16); }
[data-bs-theme="dark"] .inbox-thread-body,
[data-bs-theme="dark"] .inbox-thread-footer { background: rgba(255,255,255,0.02); }
.msg-time { font-size:11px; color:#9ca3af; margin-top:4px; display:flex; align-items:center; gap:2px; }
.msg-row.msg-outbound .msg-time { justify-content:flex-end; }
.msg-link { color:#2563eb; text-decoration:underline; word-break:break-all; }
.msg-media-img {
    max-width:100%; max-height:280px; border-radius:8px; margin-top:6px;
    display:block; cursor:zoom-in; border:1px solid rgba(0,0,0,0.06);
}
.msg-media-file {
    display:inline-flex; align-items:center; gap:4px;
    background:rgba(0,0,0,0.05); padding:6px 10px; border-radius:6px;
    margin-top:6px; font-size:13px; color:inherit; text-decoration:none;
}
.msg-media-file:hover { background:rgba(0,0,0,0.08); }
@keyframes msgFadeIn { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:none; } }

/* Reply textarea grows with content up to 5 rows */
#inboxReplyBody { max-height:120px; resize:vertical; }

.channel-chip { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:99px; font-size:12px; font-weight:500; background:rgba(0,0,0,0.05); color:#374151; }
.channel-chip .channel-dot { width:8px; height:8px; border-radius:50%; display:inline-block; }
.channel-chip.active { background:rgba(59,130,246,0.15); color:#1d4ed8; }
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
                            <li class="breadcrumb-item active">{{ translate('Unified Inbox') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <div class="inbox-shell" id="inboxShell">
            {{-- Left column: conversation list --}}
            <div class="inbox-list">
                <div class="inbox-list-header">
                    <input type="text" id="inboxSearch" class="form-control form-control-sm" placeholder="{{ translate('Search name, email, phone…') }}">
                    <div class="inbox-list-filters">
                        <button type="button" class="channel-chip active" data-channel="all">
                            <i class="ri-inbox-line"></i> {{ translate('All') }}
                        </button>
                        @foreach($channels as $key => $cfg)
                            <button type="button" class="channel-chip" data-channel="{{ $key }}" title="{{ $cfg['label'] }}">
                                <span class="channel-dot" style="background: {{ $cfg['color'] }};"></span>
                                <i class="{{ $cfg['icon'] }}"></i> {{ $cfg['label'] }}
                            </button>
                        @endforeach
                        <label class="ms-auto small text-muted d-inline-flex align-items-center gap-1" style="cursor:pointer;">
                            <input type="checkbox" id="inboxUnreadOnly"> {{ translate('Unread only') }}
                        </label>
                    </div>
                </div>
                <div class="inbox-list-body" id="inboxListBody">
                    <div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>
                </div>
            </div>

            {{-- Right column: conversation thread --}}
            <div class="inbox-thread" id="inboxThread">
                <div class="inbox-empty">
                    <i class="ri-inbox-line" style="font-size:48px; color:#d1d5db;"></i>
                    <h5 class="mt-3">{{ translate('Select a conversation') }}</h5>
                    <p class="mb-0">{{ translate('Pick any thread from the left to see the messages here.') }}</p>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection

@push('script-push')
<script>
(function () {
    const listUrl       = @json($listUrl);
    const threadUrlBase = @json($threadUrlBase);

    const channelCfg = @json($channels);
    let currentChannel   = 'all';
    let currentUnreadOnly = false;
    let currentSearch    = '';
    let currentConvId    = null;
    let searchTimer      = null;

    const listBody     = document.getElementById('inboxListBody');
    const threadEl     = document.getElementById('inboxThread');
    const shellEl      = document.getElementById('inboxShell');
    const searchInput  = document.getElementById('inboxSearch');
    const unreadToggle = document.getElementById('inboxUnreadOnly');
    const baseTitle    = document.title;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c =>
            ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])
        );
    }

    function contactName(c) {
        if (!c) return '{{ translate("Unknown contact") }}';
        const full = ((c.first_name || '') + ' ' + (c.last_name || '')).trim();
        return full || c.email_contact || c.whatsapp_contact || c.sms_contact || '{{ translate("Unknown") }}';
    }

    function contactAddress(c, channel) {
        if (!c) return '';
        if (channel === 'email')    return c.email_contact    || '';
        if (channel === 'sms')      return c.sms_contact      || '';
        if (channel === 'whatsapp') return c.whatsapp_contact || '';
        return c.email_contact || c.whatsapp_contact || c.sms_contact || '';
    }

    function initials(name) {
        return name.trim().split(/\s+/).slice(0,2).map(w => w[0]).join('').toUpperCase() || '?';
    }

    function formatTime(iso) {
        if (!iso) return '';
        try {
            const d = new Date(iso);
            const now = new Date();
            if (d.toDateString() === now.toDateString()) {
                return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }
            return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
        } catch (e) { return ''; }
    }

    function renderRow(conv) {
        const cfg = channelCfg[conv.channel] || { icon: 'ri-message-line', color: '#9ca3af' };
        const name = contactName(conv.contact);
        const preview = (conv.latest_message && (conv.latest_message.message || conv.latest_message.body)) || '{{ translate("No messages yet") }}';
        const time = formatTime(conv.last_message_at);
        const unread = Number(conv.unread_count || 0);
        const active = conv.id === currentConvId ? 'active' : '';

        return `<div class="inbox-row ${active}" data-id="${conv.id}">
            <div class="inbox-avatar">
                ${esc(initials(name))}
                <span class="channel-glyph" style="background: ${esc(cfg.color)};">
                    <i class="${esc(cfg.icon)}"></i>
                </span>
            </div>
            <div class="inbox-meta">
                <div class="inbox-name">
                    <span class="inbox-name-text">${esc(name)}</span>
                    <span class="inbox-time">${esc(time)}</span>
                </div>
                <div class="inbox-preview">
                    ${esc(preview)}
                    ${unread > 0 ? `<span class="inbox-unread-badge">${unread}</span>` : ''}
                </div>
            </div>
        </div>`;
    }

    async function loadList() {
        listBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
        const url = new URL(listUrl, window.location.origin);
        if (currentChannel !== 'all') url.searchParams.set('channel', currentChannel);
        if (currentUnreadOnly) url.searchParams.set('unread_only', '1');
        if (currentSearch) url.searchParams.set('search', currentSearch);
        url.searchParams.set('per_page', '30');

        try {
            const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            const rows = (data.conversations || []);
            if (!rows.length) {
                listBody.innerHTML = `<div class="text-center py-5 text-muted"><i class="ri-inbox-line" style="font-size:32px; color:#d1d5db;"></i><p class="mb-0 mt-2 small">{{ translate("No conversations match this filter") }}</p></div>`;
                return;
            }
            listBody.innerHTML = rows.map(renderRow).join('');
            listBody.querySelectorAll('.inbox-row').forEach(el => {
                el.addEventListener('click', () => openThread(Number(el.dataset.id)));
            });
            // Tab-title unread badge — sum of unread across visible rows.
            // Lets a user in another browser tab see "(3) Unified Inbox" without switching.
            const totalUnread = rows.reduce((n, c) => n + Number(c.unread_count || 0), 0);
            document.title = (totalUnread > 0 ? `(${totalUnread}) ` : '') + baseTitle;
        } catch (e) {
            listBody.innerHTML = `<div class="text-center py-4 text-danger small">{{ translate("Failed to load") }}: ${esc(e.message)}</div>`;
            if (window.console) console.error('inbox list', e);
        }
    }

    // v5.0 UI polish — WhatsApp-style bubble with status tick, media thumb, URL autolink.
    function renderMessage(m) {
        const outbound = (m.participants || []).some(p =>
            (p.role || '') === 'sender' &&
            (p.participantable_type || '').indexOf('User') !== -1
        ) || (m.direction === 'out');
        const time = formatTime(m.created_at);
        const body = String(m.message || m.body || '');

        // Media rendering — image inline, other files as a compact chip.
        let mediaHtml = '';
        const fileInfo = m.file_info || m.media || [];
        if (Array.isArray(fileInfo)) {
            fileInfo.forEach(url => {
                if (typeof url !== 'string' || !url) return;
                const isImg = /\.(png|jpe?g|gif|webp|bmp|svg)(\?|$)/i.test(url);
                if (isImg) {
                    mediaHtml += `<a href="${esc(url)}" target="_blank" rel="noopener"><img src="${esc(url)}" alt="" class="msg-media-img"></a>`;
                } else {
                    const name = url.split('/').pop().slice(0, 40);
                    mediaHtml += `<a href="${esc(url)}" target="_blank" rel="noopener" class="msg-media-file"><i class="ri-attachment-2 me-1"></i>${esc(name)}</a>`;
                }
            });
        }

        // Delivery status tick (outbound only) — read from last MessageStatus.
        let statusIcon = '';
        if (outbound && m.statuses && m.statuses.length) {
            const last = String(m.statuses[m.statuses.length - 1].status || '').toLowerCase();
            const map = {
                pending:  { i: 'ri-time-line',              c: '#9ca3af', t: '{{ translate("Sending") }}' },
                sent:     { i: 'ri-check-line',             c: '#9ca3af', t: '{{ translate("Sent") }}' },
                delivered:{ i: 'ri-check-double-line',      c: '#9ca3af', t: '{{ translate("Delivered") }}' },
                read:     { i: 'ri-check-double-line',      c: '#3b82f6', t: '{{ translate("Read") }}' },
                failed:   { i: 'ri-error-warning-line',     c: '#ef4444', t: '{{ translate("Failed") }}' },
            };
            const s = map[last] || map.sent;
            statusIcon = `<i class="${s.i} ms-1" style="color:${s.c}; font-size:14px;" title="${s.t}"></i>`;
        }

        // URL auto-link (very simple — matches http(s) tokens). XSS-safe: escape body first, then swap in anchors.
        const escBody = esc(body).replace(
            /(https?:\/\/[^\s<]+)/g,
            (u) => `<a href="${u}" target="_blank" rel="noopener" class="msg-link">${u}</a>`
        );

        return `<div class="msg-row ${outbound ? 'msg-outbound' : ''}">
            <div class="msg-cell">
                <div class="msg-bubble">
                    ${escBody}
                    ${mediaHtml}
                </div>
                <div class="msg-time ${outbound ? 'text-end' : ''}">
                    ${esc(time)}${statusIcon}
                </div>
            </div>
        </div>`;
    }

    async function openThread(id) {
        currentConvId = id;
        shellEl.classList.add('thread-open');
        listBody.querySelectorAll('.inbox-row').forEach(el => el.classList.toggle('active', Number(el.dataset.id) === id));
        threadEl.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';

        try {
            const res = await fetch(`${threadUrlBase}/${id}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            const conv = data.conversation;
            const messages = data.messages || [];
            const cfg = channelCfg[conv.channel] || { icon:'ri-message-line', color:'#9ca3af', label: conv.channel };
            const name = contactName(conv.contact);
            const addr = contactAddress(conv.contact, conv.channel);

            const gatewayLabel = conv.gateway ? (conv.gateway.name || conv.gateway.address || '') : '';

            threadEl.innerHTML = `
                <div class="inbox-thread-header">
                    <button type="button" class="i-btn btn--dark outline btn--sm d-lg-none me-2" id="backToListBtn"><i class="ri-arrow-left-line"></i></button>
                    <div class="inbox-avatar">
                        ${esc(initials(name))}
                        <span class="channel-glyph" style="background: ${esc(cfg.color)};"><i class="${esc(cfg.icon)}"></i></span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">${esc(name)}</div>
                        <div class="small text-muted">${esc(addr)} ${gatewayLabel ? '· <span title="{{ translate("Gateway") }}">' + esc(gatewayLabel) + '</span>' : ''}</div>
                    </div>
                    <span class="channel-chip"><span class="channel-dot" style="background:${esc(cfg.color)};"></span> ${esc(cfg.label)}</span>
                </div>
                <div class="inbox-thread-body" id="inboxThreadBody">
                    ${messages.length ? messages.map(renderMessage).join('') : `<div class="inbox-empty"><i class="ri-chat-3-line" style="font-size:32px; color:#d1d5db;"></i><p class="mb-0 mt-2">{{ translate("No messages in this conversation yet.") }}</p></div>`}
                </div>
                <div class="inbox-thread-footer">
                    {{-- v5.0-S5 — AI Reply Copilot. "Suggest reply" fetches 3 options
                         (formal / friendly / concise). Clicking any option fills the
                         textarea below. Uses the same site_settings('ai_models')
                         config the AI Text Generator does — no separate setup. --}}
                    <div class="d-flex align-items-center gap-2 mb-2 flex-wrap" id="inboxCopilotBar">
                        <button type="button" class="i-btn btn--info outline btn--sm" id="inboxCopilotBtn" title="{{ translate('Generate 3 reply suggestions with AI') }}">
                            <i class="ri-sparkling-2-line me-1"></i> {{ translate('Suggest reply') }}
                        </button>
                        <div class="d-flex gap-2 flex-wrap" id="inboxCopilotSuggestions"></div>
                    </div>
                    <form id="inboxReplyForm" enctype="multipart/form-data" data-conversation-id="${conv.id}" data-channel="${esc(conv.channel)}">
                        @csrf
                        <div class="d-flex gap-2 align-items-start">
                            <label class="i-btn btn--dark outline btn--md mb-0" style="cursor:pointer; padding:8px 12px;" title="{{ translate('Attach file') }}">
                                <i class="ri-attachment-2 fs-5"></i>
                                <input type="file" name="media" class="d-none" id="inboxReplyMedia" accept="image/*,application/pdf,.doc,.docx">
                            </label>
                            <textarea name="body" class="form-control" rows="2" placeholder="{{ translate('Type your reply…') }}" id="inboxReplyBody"></textarea>
                            <button type="submit" class="i-btn btn--primary btn--md" id="inboxReplySubmit">
                                <i class="ri-send-plane-2-line me-1"></i> {{ translate('Send') }}
                            </button>
                        </div>
                        <div class="small text-muted mt-1" id="inboxReplyMediaName"></div>
                    </form>
                </div>
            `;

            // Scroll to bottom of thread
            const body = document.getElementById('inboxThreadBody');
            body && (body.scrollTop = body.scrollHeight);

            document.getElementById('backToListBtn')?.addEventListener('click', () => shellEl.classList.remove('thread-open'));

            // v5.0-S4 wire the reply form.
            wireReplyForm(id);

            // v5.0 UI polish — auto-focus reply box on open (skip mobile — keyboard pop is jarring).
            if (window.matchMedia && window.matchMedia('(min-width: 992px)').matches) {
                setTimeout(() => document.getElementById('inboxReplyBody')?.focus(), 40);
            }

            // v5.0 UI polish — start auto-poll for new inbound messages every 15s while this thread is open.
            startThreadPoll(id);
        } catch (e) {
            threadEl.innerHTML = `<div class="inbox-empty text-danger">{{ translate("Failed to load conversation:") }} ${esc(e.message)}</div>`;
            if (window.console) console.error('inbox thread', e);
        }
    }

    // v5.0-S4 — cross-channel reply. Same submit handler for SMS/Email/WhatsApp.
    // Optimistic UI: message shown in thread as "Sending…" pill immediately;
    // real message + status replaces it on server response.
    function wireReplyForm(conversationId) {
        const form   = document.getElementById('inboxReplyForm');
        const body   = document.getElementById('inboxReplyBody');
        const submit = document.getElementById('inboxReplySubmit');
        const media  = document.getElementById('inboxReplyMedia');
        const mediaName = document.getElementById('inboxReplyMediaName');
        if (!form) return;

        media?.addEventListener('change', () => {
            mediaName.textContent = media.files.length ? media.files[0].name : '';
        });

        // v5.0 UI polish — Enter=send, Shift+Enter=newline (matches WhatsApp Web / Slack / Discord convention).
        body?.addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter' && !ev.shiftKey && !ev.isComposing) {
                ev.preventDefault();
                if (!submit.disabled) form.dispatchEvent(new Event('submit', { cancelable: true }));
            }
        });

        // v5.0-S5 — AI Reply Copilot. One request → 3 suggestions rendered
        // as clickable chips. Click a chip → fills the textarea (append if
        // there's already text, replace if empty). Rate limit: 20 req/min
        // per user (enforced by throttle middleware on the route).
        const copilotBtn  = document.getElementById('inboxCopilotBtn');
        const copilotOuts = document.getElementById('inboxCopilotSuggestions');
        copilotBtn?.addEventListener('click', async () => {
            copilotBtn.disabled = true;
            const originalHtml = copilotBtn.innerHTML;
            copilotBtn.innerHTML = '<i class="ri-loader-4-line ri-spin me-1"></i> {{ translate("Thinking…") }}';
            copilotOuts.innerHTML = '';

            try {
                const res = await fetch(`${threadUrlBase}/${conversationId}/copilot-suggest`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (!data.ok) throw new Error(data.reason || 'Suggest failed');

                (data.suggestions || []).forEach((s, i) => {
                    const chip = document.createElement('button');
                    chip.type = 'button';
                    chip.className = 'i-btn btn--dark outline btn--sm';
                    chip.style.textAlign = 'left';
                    chip.style.maxWidth = '360px';
                    chip.title = s.text;
                    chip.innerHTML = '<strong style="font-size:11px;">' + esc(s.label || ('Option ' + (i+1))) + ':</strong> '
                        + '<span style="font-weight:normal;">' + esc((s.text || '').slice(0, 70)) + (s.text.length > 70 ? '…' : '') + '</span>';
                    chip.addEventListener('click', () => {
                        // Replace if textarea empty, else append with a space
                        body.value = body.value.trim() ? (body.value.trim() + ' ' + s.text) : s.text;
                        body.focus();
                        // Auto-clear suggestions to reduce noise
                        copilotOuts.innerHTML = '';
                    });
                    copilotOuts.appendChild(chip);
                });
                if (!copilotOuts.children.length) {
                    copilotOuts.innerHTML = '<span class="small text-muted">{{ translate("No suggestions available.") }}</span>';
                }
            } catch (err) {
                copilotOuts.innerHTML = '<span class="small text-danger">' + esc(err.message || '{{ translate("AI suggestion failed") }}') + '</span>';
                if (window.console) console.error('copilot suggest', err);
            } finally {
                copilotBtn.disabled = false;
                copilotBtn.innerHTML = originalHtml;
            }
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const text = (body.value || '').trim();
            if (!text && !media.files.length) return;

            submit.disabled = true;
            const originalHtml = submit.innerHTML;
            submit.innerHTML = '<i class="ri-loader-4-line ri-spin me-1"></i> {{ translate("Sending…") }}';

            // Optimistic bubble
            const threadBody = document.getElementById('inboxThreadBody');
            const tempId = 'tmp-' + Date.now();
            if (threadBody) {
                threadBody.insertAdjacentHTML('beforeend',
                    `<div class="msg-row msg-outbound" data-tmp="${tempId}">
                        <div>
                            <div class="msg-bubble">${esc(text)}</div>
                            <div class="msg-time text-end">{{ translate("Sending…") }}</div>
                        </div>
                    </div>`);
                threadBody.scrollTop = threadBody.scrollHeight;
            }

            const fd = new FormData(form);
            try {
                const res = await fetch(`${threadUrlBase}/${conversationId}/reply`, {
                    method: 'POST',
                    body: fd,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    throw new Error(data.error || ('HTTP ' + res.status));
                }
                // Replace temp with real
                const tempEl = threadBody?.querySelector(`[data-tmp="${tempId}"]`);
                if (tempEl && data.message) {
                    tempEl.outerHTML = renderMessage(data.message);
                }
                body.value = '';
                media.value = '';
                mediaName.textContent = '';
                // Refresh the list so this conversation jumps to top.
                loadList();
            } catch (err) {
                const tempEl = threadBody?.querySelector(`[data-tmp="${tempId}"]`);
                if (tempEl) {
                    tempEl.querySelector('.msg-time').textContent = '{{ translate("Failed") }}: ' + (err.message || '');
                    tempEl.querySelector('.msg-time').classList.add('text-danger');
                }
                if (window.console) console.error('inbox reply', err);
                if (window.notify) notify('error', err.message || '{{ translate("Failed to send") }}');
            } finally {
                submit.disabled = false;
                submit.innerHTML = originalHtml;
            }
        });
    }

    // Filters
    document.querySelectorAll('.channel-chip[data-channel]').forEach(chip => {
        chip.addEventListener('click', () => {
            document.querySelectorAll('.channel-chip[data-channel]').forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            currentChannel = chip.dataset.channel;
            loadList();
        });
    });
    unreadToggle.addEventListener('change', () => { currentUnreadOnly = unreadToggle.checked; loadList(); });
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { currentSearch = searchInput.value.trim(); loadList(); }, 300);
    });

    // v5.0 UI polish — auto-poll open thread for new inbound messages.
    // Silent (no spinner) so it doesn't jitter the UI mid-read. Stops on
    // thread close, resumes on new open. Also silently refreshes the list
    // so unread counts stay accurate.
    let pollTimer = null;
    function startThreadPoll(convId) {
        stopThreadPoll();
        pollTimer = setInterval(async () => {
            if (currentConvId !== convId) { stopThreadPoll(); return; }
            try {
                const res = await fetch(`${threadUrlBase}/${convId}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!res.ok) return;
                const data = await res.json();
                const body = document.getElementById('inboxThreadBody');
                if (!body) return;
                const existing = body.querySelectorAll('.msg-row').length;
                const arrived  = (data.messages || []).length;
                if (arrived > existing) {
                    // Append only new messages (keeps user's scroll position on already-read ones).
                    const newOnes = (data.messages || []).slice(existing);
                    const wasBottom = (body.scrollHeight - body.scrollTop - body.clientHeight) < 40;
                    body.insertAdjacentHTML('beforeend', newOnes.map(renderMessage).join(''));
                    if (wasBottom) body.scrollTop = body.scrollHeight;
                }
            } catch (e) { /* silent */ }
            // Also silently refresh list every 3rd tick so unread counts stay accurate.
            if (pollTicks++ % 3 === 0) loadList();
        }, 15000);
    }
    function stopThreadPoll() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }
    let pollTicks = 0;

    loadList();
})();
</script>
@endpush

{{--
    Global Floating AI Assistant
    ----------------------------
    Single floating button on every page. Tracks the last-focused input/textarea
    via focusin listener. When the user generates AI text and clicks Insert,
    the generated content is pasted into that element (or appended in the case
    of contentEditable rich-text editors).

    Required vars:
      - $floatingAiRoute : URL to POST to (admin.ai.content.generate.text OR user.ai.content.generate.text)
--}}

@php
    $aiModels    = json_decode(site_settings('ai_models', '{}'), true);
    $aiEnabled   = !empty($aiModels['open_ai_text']['status'])
        && $aiModels['open_ai_text']['status'] === '1'
        && !empty($aiModels['open_ai_text']['key'])
        && $aiModels['open_ai_text']['key'] !== '###';
@endphp

@if($aiEnabled)
<button type="button" id="floatingAiBtn" class="floating-ai-btn"
        title="{{ translate('AI Writing Assistant') }}"
        onclick="openFloatingAi()">
    <i class="ri-sparkling-2-line"></i>
</button>

<div class="modal fade" id="floatingAiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ri-sparkling-2-line me-2"></i>{{ translate('AI Writing Assistant') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="floatingAiTargetHint" class="alert alert-info small py-2 mb-3">
                    <i class="ri-cursor-line me-1"></i>
                    <strong>{{ translate('Tip') }}:</strong>
                    {{ translate('Click into any text field on the page first, then open this assistant. The generated text will be inserted into that field.') }}
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ translate('What do you want to write?') }}</label>
                    <textarea id="floatingAiPrompt" class="form-control" rows="3"
                              placeholder="{{ translate('e.g. Write a polite follow-up email to a customer who has not replied in 3 days.') }}"></textarea>
                </div>

                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small">{{ translate('Tone (optional)') }}</label>
                        <select id="floatingAiTone" class="form-select form-select-sm">
                            <option value="">{{ translate('Default') }}</option>
                            <option value="friendly">{{ translate('Friendly') }}</option>
                            <option value="professional">{{ translate('Professional') }}</option>
                            <option value="casual">{{ translate('Casual') }}</option>
                            <option value="urgent">{{ translate('Urgent') }}</option>
                            <option value="empathetic">{{ translate('Empathetic') }}</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">{{ translate('Language (optional)') }}</label>
                        <input id="floatingAiLanguage" type="text" class="form-control form-control-sm"
                               placeholder="{{ translate('e.g. English, Spanish, Bengali') }}">
                    </div>
                </div>

                <div id="floatingAiResult" class="mt-3 d-none">
                    <label class="form-label">{{ translate('Generated text') }}</label>
                    <textarea id="floatingAiOutput" class="form-control" rows="6" readonly></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ translate('Close') }}</button>
                <button type="button" class="btn btn-outline-primary" id="floatingAiCopyBtn" onclick="copyFloatingAi()" disabled>
                    <i class="ri-clipboard-line me-1"></i>{{ translate('Copy') }}
                </button>
                <button type="button" class="btn btn-success" id="floatingAiInsertBtn" onclick="insertFloatingAi()" disabled>
                    <i class="ri-download-line me-1"></i>{{ translate('Insert into field') }}
                </button>
                <button type="button" class="btn btn-primary" id="floatingAiGenerateBtn" onclick="generateFloatingAi()">
                    <i class="ri-sparkling-line me-1"></i>{{ translate('Generate') }}
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.floating-ai-btn {
    position: fixed; right: 24px; bottom: 24px; z-index: 1040;
    width: 52px; height: 52px; border-radius: 50%; border: none;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #fff; font-size: 22px;
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
    display: flex; align-items: center; justify-content: center;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.floating-ai-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(99, 102, 241, 0.5); }
.floating-ai-btn:active { transform: translateY(0); }
@media (max-width: 576px) {
    .floating-ai-btn { right: 16px; bottom: 16px; width: 46px; height: 46px; font-size: 19px; }
}
</style>

<script>
(function () {
    if (window.__floatingAiWired) return;
    window.__floatingAiWired = true;

    let lastFocusedField = null;
    let modalInstance    = null;

    // Track last-focused text input / textarea / contenteditable element.
    document.addEventListener('focusin', function (e) {
        const t = e.target;
        if (!t || !t.tagName) return;
        const tag = t.tagName.toLowerCase();
        const isTextInput = (tag === 'input' && /^(text|email|url|search|tel)$/i.test(t.type || 'text'))
            || tag === 'textarea'
            || t.isContentEditable === true;
        if (isTextInput && t.id !== 'floatingAiPrompt' && t.id !== 'floatingAiOutput') {
            lastFocusedField = t;
        }
    });

    window.openFloatingAi = function () {
        const modal = document.getElementById('floatingAiModal');
        modalInstance = bootstrap.Modal.getOrCreateInstance(modal);
        // Reset
        document.getElementById('floatingAiOutput').value = '';
        document.getElementById('floatingAiResult').classList.add('d-none');
        document.getElementById('floatingAiInsertBtn').disabled = true;
        document.getElementById('floatingAiCopyBtn').disabled   = true;
        // Update target hint
        const hint = document.getElementById('floatingAiTargetHint');
        if (lastFocusedField) {
            const labelTxt = describeField(lastFocusedField);
            hint.classList.remove('alert-info');
            hint.classList.add('alert-success');
            hint.innerHTML = `<i class="ri-cursor-line me-1"></i> <strong>{{ translate("Target field") }}:</strong> ${escapeText(labelTxt)}`;
        } else {
            hint.classList.add('alert-info');
            hint.classList.remove('alert-success');
            hint.innerHTML = `<i class="ri-cursor-line me-1"></i> <strong>{{ translate("Tip") }}:</strong> {{ translate("Click into any text field on the page first, then open this assistant.") }}`;
        }
        modalInstance.show();
        setTimeout(() => document.getElementById('floatingAiPrompt').focus(), 200);
    };

    function describeField(el) {
        if (!el) return '(unknown)';
        // Prefer associated <label>, then placeholder, then name attr
        const id = el.id;
        if (id) {
            const lbl = document.querySelector(`label[for="${CSS.escape(id)}"]`);
            if (lbl) return lbl.textContent.trim();
        }
        if (el.getAttribute('placeholder')) return el.getAttribute('placeholder');
        if (el.getAttribute('name'))        return el.getAttribute('name');
        return el.tagName.toLowerCase();
    }

    function escapeText(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    window.generateFloatingAi = function () {
        const prompt   = document.getElementById('floatingAiPrompt').value.trim();
        const tone     = document.getElementById('floatingAiTone').value;
        const language = document.getElementById('floatingAiLanguage').value.trim();
        if (!prompt) {
            notify('error', '{{ translate("Please describe what you want to write") }}');
            return;
        }

        const btn = document.getElementById('floatingAiGenerateBtn');
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="ri-loader-4-line ri-spin me-1"></i>{{ translate("Generating…") }}';

        const fd = new FormData();
        fd.append('custom_prompt', prompt);
        fd.append('ai_tone', tone);
        if (language) fd.append('language', language);
        fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);

        fetch('{{ $floatingAiRoute }}', {
            method: 'POST',
            body: fd,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = original;
                if (!data || data.status === false) {
                    notify('error', (data && data.message) || '{{ translate("AI generation failed") }}');
                    return;
                }
                const text = data.message || data.content || data.text || '';
                document.getElementById('floatingAiOutput').value = text;
                document.getElementById('floatingAiResult').classList.remove('d-none');
                document.getElementById('floatingAiInsertBtn').disabled = !text || !lastFocusedField;
                document.getElementById('floatingAiCopyBtn').disabled   = !text;
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = original;
                notify('error', err.message || '{{ translate("Network error") }}');
            });
    };

    window.insertFloatingAi = function () {
        const text = document.getElementById('floatingAiOutput').value;
        if (!text) return;
        if (!lastFocusedField || !document.body.contains(lastFocusedField)) {
            notify('error', '{{ translate("No target field — click into one first.") }}');
            return;
        }
        const t = lastFocusedField;
        if (t.isContentEditable) {
            t.focus();
            document.execCommand('insertText', false, text);
        } else if ('value' in t) {
            const start = t.selectionStart ?? t.value.length;
            const end   = t.selectionEnd   ?? t.value.length;
            t.value = t.value.substring(0, start) + text + t.value.substring(end);
            t.dispatchEvent(new Event('input',  { bubbles: true }));
            t.dispatchEvent(new Event('change', { bubbles: true }));
        }
        modalInstance.hide();
        notify('success', '{{ translate("Inserted into field") }}');
    };

    window.copyFloatingAi = function () {
        const text = document.getElementById('floatingAiOutput').value;
        if (!text) return;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => notify('success', '{{ translate("Copied") }}'));
        } else {
            const ta = document.getElementById('floatingAiOutput');
            ta.removeAttribute('readonly');
            ta.select();
            document.execCommand('copy');
            ta.setAttribute('readonly', 'readonly');
            notify('success', '{{ translate("Copied") }}');
        }
    };
})();
</script>
@endif

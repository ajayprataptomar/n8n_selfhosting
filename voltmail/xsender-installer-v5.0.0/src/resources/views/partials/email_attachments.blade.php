{{-- Gmail-style Email Attachment UI --}}
<div class="email-attachment-section">
    <div class="attachment-trigger-bar">
        <button type="button" class="attachment-btn" id="attachmentTrigger" onclick="document.getElementById('emailAttachmentInput').click()">
            <i class="ri-attachment-2"></i>
            <span>{{ translate("Attach files") }}</span>
        </button>
        <span class="attachment-hint" id="attachmentHint">{{ translate("Max") }} {{ site_settings('email_attachment_max_files', 5) }} {{ translate("files") }}, {{ site_settings('email_attachment_max_size', 10) }} MB {{ translate("each") }}</span>
    </div>

    <input type="file"
        id="emailAttachmentInput"
        name="email_attachments[]"
        multiple
        accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg,.gif,.zip,.rar,.svg,.webp"
        style="display:none"
        onchange="handleEmailAttachments(this)">

    <div class="attachment-list" id="attachmentList"></div>
</div>

<style>
.email-attachment-section {
    margin-top: 12px;
}
.attachment-trigger-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
}
.attachment-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 8px;
    border: 1px dashed var(--border-color, #d1d5db);
    background: transparent;
    color: var(--text-secondary, #6b7280);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all .2s;
    font-family: inherit;
}
.attachment-btn:hover {
    border-color: var(--primary-color, #f25d6d);
    color: var(--primary-color, #f25d6d);
    background: rgba(242, 93, 109, .04);
}
.attachment-btn i {
    font-size: 16px;
}
.attachment-hint {
    font-size: 12px;
    color: var(--text-muted, #9ca3af);
}
.attachment-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 4px;
}
.attachment-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px 6px 8px;
    border-radius: 8px;
    background: var(--bg-soft, #f3f4f6);
    border: 1px solid var(--border-color, #e5e7eb);
    font-size: 12px;
    font-weight: 500;
    color: var(--text-primary, #374151);
    max-width: 260px;
    transition: all .15s;
}
.attachment-chip:hover {
    border-color: var(--border-hover, #d1d5db);
}
.attachment-chip-icon {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
}
.attachment-chip-icon.type-pdf { background: #fef2f2; color: #dc2626; }
.attachment-chip-icon.type-doc { background: #eff6ff; color: #2563eb; }
.attachment-chip-icon.type-xls { background: #f0fdf4; color: #16a34a; }
.attachment-chip-icon.type-img { background: #fefce8; color: #ca8a04; }
.attachment-chip-icon.type-zip { background: #faf5ff; color: #7c3aed; }
.attachment-chip-icon.type-default { background: #f3f4f6; color: #6b7280; }
.attachment-chip-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
    line-height: 1.3;
}
.attachment-chip-name {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 160px;
}
.attachment-chip-size {
    font-size: 10px;
    color: var(--text-muted, #9ca3af);
    font-weight: 400;
}
.attachment-chip-remove {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    color: var(--text-muted, #9ca3af);
    cursor: pointer;
    padding: 0;
    font-size: 14px;
    flex-shrink: 0;
    transition: all .15s;
}
.attachment-chip-remove:hover {
    background: #fee2e2;
    color: #dc2626;
}
.attachment-error {
    font-size: 12px;
    color: #dc2626;
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.attachment-error i {
    font-size: 14px;
}
</style>

<script>
var emailAttachmentFiles = [];
var EMAIL_ATTACH_MAX_FILES = {{ (int) site_settings('email_attachment_max_files', 5) }};
var EMAIL_ATTACH_MAX_SIZE = {{ (int) site_settings('email_attachment_max_size', 10) }} * 1024 * 1024;

function handleEmailAttachments(input) {
    var newFiles = Array.from(input.files);
    var errorEl = document.getElementById('attachmentError');
    if (errorEl) errorEl.remove();

    for (var i = 0; i < newFiles.length; i++) {
        var file = newFiles[i];

        if (emailAttachmentFiles.length >= EMAIL_ATTACH_MAX_FILES) {
            showAttachmentError("{{ translate('Maximum') }} {{ site_settings('email_attachment_max_files', 5) }} {{ translate('files allowed') }}");
            break;
        }

        if (file.size > EMAIL_ATTACH_MAX_SIZE) {
            showAttachmentError("{{ translate('File') }} \"" + file.name + "\" {{ translate('exceeds') }} {{ site_settings('email_attachment_max_size', 10) }} MB {{ translate('limit') }}");
            continue;
        }

        var duplicate = false;
        for (var j = 0; j < emailAttachmentFiles.length; j++) {
            if (emailAttachmentFiles[j].name === file.name && emailAttachmentFiles[j].size === file.size) {
                duplicate = true;
                break;
            }
        }
        if (duplicate) continue;

        emailAttachmentFiles.push(file);
    }

    renderAttachmentChips();
    syncAttachmentInput();
    input.value = '';
}

function removeEmailAttachment(index) {
    emailAttachmentFiles.splice(index, 1);
    renderAttachmentChips();
    syncAttachmentInput();
}

function renderAttachmentChips() {
    var container = document.getElementById('attachmentList');
    var hint = document.getElementById('attachmentHint');

    if (emailAttachmentFiles.length === 0) {
        container.innerHTML = '';
        hint.textContent = "{{ translate('Max') }} {{ site_settings('email_attachment_max_files', 5) }} {{ translate('files') }}, {{ site_settings('email_attachment_max_size', 10) }} MB {{ translate('each') }}";
        return;
    }

    var totalSize = 0;
    var html = '';

    for (var i = 0; i < emailAttachmentFiles.length; i++) {
        var file = emailAttachmentFiles[i];
        totalSize += file.size;
        var ext = file.name.split('.').pop().toLowerCase();
        var iconClass = getAttachmentIconClass(ext);
        var iconChar = getAttachmentIcon(ext);

        html += '<div class="attachment-chip">'
            + '<div class="attachment-chip-icon ' + iconClass + '"><i class="' + iconChar + '"></i></div>'
            + '<div class="attachment-chip-info">'
            + '<span class="attachment-chip-name" title="' + escapeHtml(file.name) + '">' + escapeHtml(file.name) + '</span>'
            + '<span class="attachment-chip-size">' + formatFileSize(file.size) + '</span>'
            + '</div>'
            + '<button type="button" class="attachment-chip-remove" onclick="removeEmailAttachment(' + i + ')" title="{{ translate("Remove") }}">'
            + '<i class="ri-close-line"></i></button>'
            + '</div>';
    }

    container.innerHTML = html;
    hint.textContent = emailAttachmentFiles.length + '/' + EMAIL_ATTACH_MAX_FILES + ' · ' + formatFileSize(totalSize);
}

function syncAttachmentInput() {
    var existingInputs = document.querySelectorAll('input.email-attachment-synced');
    existingInputs.forEach(function(el) { el.remove(); });

    var form = document.getElementById('email_send');
    if (!form) return;

    var dt = new DataTransfer();
    for (var i = 0; i < emailAttachmentFiles.length; i++) {
        dt.items.add(emailAttachmentFiles[i]);
    }

    var input = document.createElement('input');
    input.type = 'file';
    input.name = 'email_attachments[]';
    input.multiple = true;
    input.className = 'email-attachment-synced';
    input.style.display = 'none';
    input.files = dt.files;
    form.appendChild(input);
}

function showAttachmentError(msg) {
    var existing = document.getElementById('attachmentError');
    if (existing) existing.remove();

    var container = document.querySelector('.email-attachment-section');
    var div = document.createElement('div');
    div.id = 'attachmentError';
    div.className = 'attachment-error';
    div.innerHTML = '<i class="ri-error-warning-line"></i> ' + escapeHtml(msg);
    container.appendChild(div);

    setTimeout(function() {
        var el = document.getElementById('attachmentError');
        if (el) el.remove();
    }, 4000);
}

function getAttachmentIconClass(ext) {
    if (ext === 'pdf') return 'type-pdf';
    if (['doc', 'docx', 'txt', 'csv'].indexOf(ext) !== -1) return 'type-doc';
    if (['xls', 'xlsx'].indexOf(ext) !== -1) return 'type-xls';
    if (['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'].indexOf(ext) !== -1) return 'type-img';
    if (['zip', 'rar'].indexOf(ext) !== -1) return 'type-zip';
    return 'type-default';
}

function getAttachmentIcon(ext) {
    if (ext === 'pdf') return 'ri-file-pdf-2-line';
    if (['doc', 'docx'].indexOf(ext) !== -1) return 'ri-file-word-line';
    if (['xls', 'xlsx'].indexOf(ext) !== -1) return 'ri-file-excel-line';
    if (['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'].indexOf(ext) !== -1) return 'ri-image-line';
    if (['zip', 'rar'].indexOf(ext) !== -1) return 'ri-file-zip-line';
    if (ext === 'txt') return 'ri-file-text-line';
    if (ext === 'csv') return 'ri-file-list-line';
    return 'ri-file-line';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    var k = 1024;
    var sizes = ['B', 'KB', 'MB'];
    var i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>

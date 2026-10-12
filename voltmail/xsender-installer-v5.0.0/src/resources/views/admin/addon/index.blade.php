@extends('admin.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-xxl" style="min-height:calc(100vh - 180px);">
        <div class="page-header d-flex justify-content-between align-items-center">
            <div class="page-header-left">
                <h2>{{ translate("Add-ons") }}</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate("Dashboard") }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.system.update.init') }}">{{ translate("System") }}</a></li>
                        <li class="breadcrumb-item active">{{ translate("Add-ons") }}</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.addon.marketplace') }}" class="i-btn btn--info outline btn--md">
                    <i class="ri-store-line me-1"></i>{{ translate("Marketplace") }}
                </a>
                <button type="button" class="i-btn btn--primary btn--md" onclick="document.getElementById('addon_upload_input').click();">
                    <i class="ri-upload-cloud-line me-1"></i>{{ translate("Upload Add-on") }}
                </button>
                <input type="file" id="addon_upload_input" accept=".zip" class="d-none" onchange="uploadAddon(this)">
            </div>
        </div>
    
        {{-- Upload Progress --}}
        <div id="upload_progress" class="d-none mt-3">
            <div class="card">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="spinner-border spinner-border-sm" style="color:var(--color-primary);" role="status"></div>
                    <span id="upload_status_text" class="fw-medium">{{ translate("Uploading add-on...") }}</span>
                </div>
            </div>
        </div>
    
        {{-- Installed Add-ons --}}
        <div class="mt-4">
            @if(count($addons) > 0)
                <div class="row g-4">
                    @foreach($addons as $addon)
                        @php
                            $isActive = ($addon['status'] ?? 'inactive') === 'active';
                            $isVerified = !empty($addon['purchase_code']);
                            $needsVerification = !empty($addon['verification_required']) && !$isVerified;
                        @endphp
                        <div class="col-lg-4 col-md-6" id="addon_card_{{ $addon['name'] }}">
                            <div class="card h-100 position-relative overflow-hidden">
                                {{-- Status indicator strip --}}
                                <div style="height:3px;background:{{ $isActive ? 'var(--color-primary)' : '#e2e8f0' }};"></div>
    
                                <div class="card-body pb-2">
                                    {{-- Header --}}
                                    <div class="d-flex align-items-start justify-content-between mb-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="d-flex align-items-center justify-content-center rounded-3"
                                                 style="width:44px;height:44px;background:{{ $isActive ? 'rgba(var(--color-primary-rgb, 115,103,240),0.12)' : '#f1f5f9' }};">
                                                <i class="ri-puzzle-line fs-4" style="color:{{ $isActive ? 'var(--color-primary)' : '#94a3b8' }};"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-semibold">{{ $addon['display_name'] ?? $addon['name'] }}</h6>
                                                <small class="text-muted">v{{ $addon['version'] ?? '1.0.0' }}</small>
                                            </div>
                                        </div>
                                        @if($isActive)
                                            <span class="i-badge success-soft pill">{{ translate("Active") }}</span>
                                        @else
                                            <span class="i-badge secondary-soft pill">{{ translate("Inactive") }}</span>
                                        @endif
                                    </div>
    
                                    {{-- Description --}}
                                    @if(!empty($addon['description']))
                                        <p class="text-muted small mb-3 lh-base">{{ Str::limit($addon['description'], 120) }}</p>
                                    @endif
    
                                    {{-- License & Author --}}
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        @if(!empty($addon['author']))
                                            <span class="d-inline-flex align-items-center gap-1 small text-muted">
                                                <i class="ri-user-line" style="font-size:13px;"></i> {{ $addon['author'] }}
                                            </span>
                                        @endif
                                        @if(!empty($addon['verification_required']))
                                            @if($isVerified)
                                                <span class="d-inline-flex align-items-center gap-1 small" style="color:var(--color-primary);">
                                                    <i class="ri-shield-check-line" style="font-size:13px;"></i> {{ translate("Licensed") }}
                                                </span>
                                            @else
                                                <span class="d-inline-flex align-items-center gap-1 small text-warning">
                                                    <i class="ri-shield-keyhole-line" style="font-size:13px;"></i> {{ translate("Unverified") }}
                                                </span>
                                            @endif
                                        @endif
                                    </div>
                                </div>
    
                                {{-- Actions Footer --}}
                                <div class="card-footer bg-transparent border-top pt-0 pb-3">
                                    <div class="d-flex gap-2">
                                        @if($isActive)
                                            <button type="button" class="i-btn btn--dark outline btn--sm flex-grow-1"
                                                    onclick="addonAction('{{ $addon['name'] }}', 'deactivate')">
                                                <i class="ri-pause-circle-line me-1"></i>{{ translate("Deactivate") }}
                                            </button>
                                        @elseif($needsVerification)
                                            <button type="button" class="i-btn btn--primary outline btn--sm flex-grow-1"
                                                    onclick="showVerifyModal('{{ $addon['name'] }}', '{{ addslashes($addon['display_name'] ?? $addon['name']) }}')">
                                                <i class="ri-key-line me-1"></i>{{ translate("Verify & Activate") }}
                                            </button>
                                        @else
                                            <button type="button" class="i-btn btn--primary btn--sm flex-grow-1"
                                                    onclick="addonAction('{{ $addon['name'] }}', 'activate')">
                                                <i class="ri-play-circle-line me-1"></i>{{ translate("Activate") }}
                                            </button>
                                        @endif
                                        <button type="button" class="i-btn btn--danger outline btn--sm"
                                                onclick="addonAction('{{ $addon['name'] }}', 'uninstall')"
                                                title="{{ translate('Uninstall') }}">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">{{ translate("Installed") }} {{ \Carbon\Carbon::parse($addon['installed_at'] ?? now())->diffForHumans() }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                {{-- Empty State --}}
                <div class="card">
                    <div class="card-body text-center py-5">
                        <div class="mx-auto rounded-circle d-flex align-items-center justify-content-center mb-3"
                             style="width:72px;height:72px;background:rgba(var(--color-primary-rgb, 115,103,240),0.1);">
                            <i class="ri-puzzle-2-line" style="font-size:32px;color:var(--color-primary);"></i>
                        </div>
                        <h5 class="fw-semibold mb-2">{{ translate("No Add-ons Installed") }}</h5>
                        <p class="text-muted mb-4 mx-auto" style="max-width:400px;">
                            {{ translate("Extend your platform with powerful add-ons. Upload an add-on ZIP file to get started.") }}
                        </p>
                        <button type="button" class="i-btn btn--primary btn--md" onclick="document.getElementById('addon_upload_input').click();">
                            <i class="ri-upload-cloud-line me-1"></i>{{ translate("Upload Your First Add-on") }}
                        </button>
                    </div>
                </div>
            @endif
        </div>
    
        {{-- How It Works --}}
        <div class="card mt-4">
            <div class="card-body py-4">
                <h6 class="fw-semibold mb-4">
                    <i class="ri-question-line me-1" style="color:var(--color-primary);"></i>{{ translate("How Add-ons Work") }}
                </h6>
                <div class="row g-4">
                    @php
                        $steps = [
                            ['icon' => 'ri-shopping-cart-line', 'title' => 'Purchase', 'desc' => 'Buy an add-on from CodeCanyon or our marketplace'],
                            ['icon' => 'ri-upload-cloud-line', 'title' => 'Upload ZIP', 'desc' => 'Upload the add-on ZIP file using the button above'],
                            ['icon' => 'ri-key-2-line', 'title' => 'Verify License', 'desc' => 'Enter your purchase code to verify ownership'],
                            ['icon' => 'ri-check-double-line', 'title' => 'Activate', 'desc' => 'Click activate and the add-on features become available'],
                        ];
                    @endphp
                    @foreach($steps as $i => $step)
                        <div class="col-md-3 col-sm-6">
                            <div class="text-center">
                                <div class="mx-auto rounded-circle d-flex align-items-center justify-content-center mb-2"
                                     style="width:40px;height:40px;background:rgba(var(--color-primary-rgb, 115,103,240),0.1);">
                                    <span class="fw-bold" style="color:var(--color-primary);font-size:14px;">{{ $i + 1 }}</span>
                                </div>
                                <h6 class="fw-semibold mb-1 small">{{ translate($step['title']) }}</h6>
                                <p class="text-muted mb-0" style="font-size:12px;">{{ translate($step['desc']) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</main>

@endsection

@section('modal')
{{-- Purchase Verification Modal --}}
<div class="modal fade" id="verifyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold">
                    <i class="ri-key-line me-1" style="color:var(--color-primary);"></i>{{ translate("Verify Purchase Code") }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="verify_addon_name">
                <p class="text-muted small mb-3" id="verify_addon_label"></p>
                <div class="mb-3">
                    <label class="form-label fw-medium">{{ translate("Purchase Code") }} <span class="text-danger">*</span></label>
                    <input type="text" id="verify_purchase_code" class="form-control"
                           placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                    <small class="form-text text-muted mt-1 d-block">
                        {{ translate("Find your purchase code in your CodeCanyon downloads page.") }}
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
                <button type="button" class="i-btn btn--primary btn--md" onclick="verifyAndActivate()">
                    <i class="ri-shield-check-line me-1"></i>{{ translate("Verify & Activate") }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Confirm Action Modal --}}
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold" id="confirm_title">{{ translate("Confirm") }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0 text-muted" id="confirm_message"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--sm" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
                <button type="button" class="i-btn btn--sm" id="confirm_btn" onclick="confirmAction()">{{ translate("Confirm") }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script-push')
<script>
    "use strict";

    var pendingAction = null;

    function uploadAddon(input) {
        if (!input.files || !input.files[0]) return;

        var file = input.files[0];
        if (!file.name.endsWith('.zip')) {
            notify('error', '{{ translate("Please select a valid ZIP file") }}');
            return;
        }

        var formData = new FormData();
        formData.append('addon_zip', file);
        formData.append('_token', '{{ csrf_token() }}');

        document.getElementById('upload_progress').classList.remove('d-none');
        document.getElementById('upload_status_text').textContent = '{{ translate("Uploading and installing...") }}';

        fetch('{{ route("admin.addon.install") }}', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            document.getElementById('upload_progress').classList.add('d-none');
            input.value = '';
            if (res.status) {
                notify('success', res.message);
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                notify('error', res.message);
            }
        })
        .catch(function() {
            document.getElementById('upload_progress').classList.add('d-none');
            input.value = '';
            notify('error', '{{ translate("Upload failed. Please try again.") }}');
        });
    }

    function addonAction(name, action) {
        var messages = {
            activate: '{{ translate("Are you sure you want to activate this add-on?") }}',
            deactivate: '{{ translate("Deactivating will temporarily disable this add-on features. Continue?") }}',
            uninstall: '{{ translate("This will permanently remove this add-on and its files. Continue?") }}'
        };
        var btnClasses = {
            activate: 'i-btn btn--primary btn--sm',
            deactivate: 'i-btn btn--dark btn--sm',
            uninstall: 'i-btn btn--danger btn--sm'
        };

        pendingAction = { name: name, action: action };
        document.getElementById('confirm_title').textContent = action.charAt(0).toUpperCase() + action.slice(1) + ' Add-on';
        document.getElementById('confirm_message').textContent = messages[action];
        document.getElementById('confirm_btn').className = btnClasses[action];
        document.getElementById('confirm_btn').textContent = action.charAt(0).toUpperCase() + action.slice(1);

        new bootstrap.Modal(document.getElementById('confirmModal')).show();
    }

    function confirmAction() {
        if (!pendingAction) return;
        bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();

        fetch('{{ url("admin/addons") }}/' + pendingAction.action, {
            method: 'POST',
            body: JSON.stringify({ name: pendingAction.name, _token: '{{ csrf_token() }}' }),
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.status) {
                notify('success', res.message);
                setTimeout(function() { location.reload(); }, 1500);
            } else if (res.requires_verification) {
                showVerifyModal(pendingAction.name, pendingAction.name);
            } else {
                notify('error', res.message);
            }
        })
        .catch(function() { notify('error', '{{ translate("Action failed. Please try again.") }}'); });

        pendingAction = null;
    }

    function showVerifyModal(name, displayName) {
        document.getElementById('verify_addon_name').value = name;
        document.getElementById('verify_addon_label').textContent = '{{ translate("Enter the purchase code for") }} ' + displayName;
        document.getElementById('verify_purchase_code').value = '';
        new bootstrap.Modal(document.getElementById('verifyModal')).show();
    }

    function verifyAndActivate() {
        var name = document.getElementById('verify_addon_name').value;
        var code = document.getElementById('verify_purchase_code').value.trim();

        if (!code || code.length < 10) {
            notify('error', '{{ translate("Please enter a valid purchase code") }}');
            return;
        }

        fetch('{{ route("admin.addon.verify") }}', {
            method: 'POST',
            body: JSON.stringify({ name: name, purchase_code: code, _token: '{{ csrf_token() }}' }),
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.status) {
                bootstrap.Modal.getInstance(document.getElementById('verifyModal')).hide();
                notify('success', res.message);
                // Auto-activate after verification
                fetch('{{ url("admin/addons") }}/activate', {
                    method: 'POST',
                    body: JSON.stringify({ name: name, _token: '{{ csrf_token() }}' }),
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                .then(function(r) { return r.json(); })
                .then(function(r2) {
                    if (r2.status) notify('success', r2.message);
                    setTimeout(function() { location.reload(); }, 1500);
                });
            } else {
                notify('error', res.message);
            }
        })
        .catch(function() { notify('error', '{{ translate("Verification failed. Please try again.") }}'); });
    }
</script>
@endpush

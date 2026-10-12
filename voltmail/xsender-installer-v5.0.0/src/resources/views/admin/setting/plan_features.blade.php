@push('style-include')
<style>
    .feature-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .feature-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.25rem;
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        margin-bottom: 0.75rem;
        transition: all 0.2s ease;
    }
    .feature-item:hover {
        border-color: var(--primary-color);
        box-shadow: 0 4px 15px rgba(var(--primary-rgb), 0.1);
    }
    .feature-item.ui-sortable-helper {
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        transform: rotate(2deg);
    }
    .feature-item .drag-handle {
        cursor: grab;
        color: var(--text-muted);
        font-size: 18px;
    }
    .feature-item .drag-handle:active {
        cursor: grabbing;
    }
    .feature-icon-preview {
        width: 40px;
        height: 40px;
        min-width: 40px;
        flex-shrink: 0;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--primary-color, #6366f1), rgba(var(--primary-rgb, 99, 102, 241), 0.7));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }
    .feature-info {
        flex: 1;
    }
    .feature-info h6 {
        margin: 0 0 0.25rem 0;
        font-weight: 600;
    }
    .feature-info p {
        margin: 0;
        font-size: 13px;
        color: var(--text-muted);
    }
    .feature-actions {
        display: flex;
        gap: 0.5rem;
    }
    .feature-status {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }
    .feature-status.active {
        background: rgba(var(--success-rgb), 0.1);
        color: var(--success-color);
    }
    .feature-status.inactive {
        background: rgba(var(--danger-rgb), 0.1);
        color: var(--danger-color);
    }

    /* Add Feature Card */
    .add-feature-card {
        background: var(--card-bg);
        border: 2px dashed var(--border-color);
        border-radius: 12px;
        padding: 2rem;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .add-feature-card:hover {
        border-color: var(--primary-color);
        background: rgba(var(--primary-rgb), 0.02);
    }
    .add-feature-card i {
        font-size: 40px;
        color: var(--primary-color);
        margin-bottom: 1rem;
    }

    /* Icon Picker */
    .icon-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(50px, 1fr));
        gap: 0.5rem;
        max-height: 200px;
        overflow-y: auto;
        padding: 1rem;
        background: var(--bg-light);
        border-radius: 8px;
    }
    .icon-option {
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        cursor: pointer;
        font-size: 20px;
        transition: all 0.2s;
    }
    .icon-option:hover, .icon-option.selected {
        border-color: var(--primary-color);
        background: rgba(var(--primary-rgb), 0.1);
        color: var(--primary-color);
    }

    /* Info Header */
    .info-header {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 2rem;

    }
    .info-header h5 {
        margin: 0 0 0.5rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 16px
    }
    .info-header p {
        margin: 0;
        color: var(--text-secondary);
        font-size: 14px;
    }
</style>
@endpush

@extends('admin.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ $title }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.dashboard') }}">{{ translate("Dashboard") }}</a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="#">{{ translate("Settings") }}</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">{{ translate("Plan Features") }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                <button type="button" class="i-btn btn--primary btn--md" data-bs-toggle="modal" data-bs-target="#addFeatureModal">
                    <i class="ri-add-line"></i> {{ translate("Add Feature") }}
                </button>
            </div>
        </div>

        <!-- Info Header -->
        <div class="info-header shadow-sm">
            <h5><i class="ri-information-line"></i> {{ translate("About Plan Display Features") }}</h5>
            <p>{{ translate("Create marketing-style features that display on pricing pages. These features are shown with checkmarks for included plans and strikethroughs for excluded plans. Technical details (credits, gateways, etc.) appear in the info modal when users click the info icon.") }}</p>
            <p class="mt-2 mb-0">
                <i class="ri-translate-2"></i>
                <strong>{{ translate("Multilanguage Support:") }}</strong>
                {{ translate("Feature names are automatically translated if translations exist.") }}
                <a href="{{ route('admin.system.language.translate', ['code' => app()->getLocale()]) }}" class="text-primary fw-semibold">{{ translate("Add translations here") }}</a>.
            </p>
        </div>

        <div class="row">
            <div class="col-lg-7">
                <!-- Features List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ translate("Features List") }}</h5>
                    </div>
                    <div class="card-body">
                        @if($features->count() > 0)
                            <ul class="feature-list" id="sortableFeatures">
                                @foreach($features as $feature)
                                    <li class="feature-item" data-id="{{ $feature->id }}">
                                        <div class="drag-handle">
                                            <i class="ri-draggable"></i>
                                        </div>
                                        <div class="feature-icon-preview">
                                            <i class="{{ $feature->icon ?? 'ri-checkbox-circle-line' }}"></i>
                                        </div>
                                        <div class="feature-info">
                                            <h6>{{ $feature->name }}</h6>
                                            @if($feature->description)
                                                <p>{{ $feature->description }}</p>
                                            @endif
                                        </div>
                                        <span class="feature-status {{ $feature->status }}">
                                            {{ $feature->status === 'active' ? translate('Active') : translate('Inactive') }}
                                        </span>
                                        <div class="feature-actions">
                                            <button type="button" class="i-btn btn--info btn--sm edit-feature"
                                                data-uid="{{ $feature->uid }}"
                                                data-name="{{ $feature->name }}"
                                                data-icon="{{ $feature->icon }}"
                                                data-description="{{ $feature->description }}">
                                                <i class="ri-edit-line"></i>
                                            </button>
                                            <button type="button" class="i-btn btn--{{ $feature->status === 'active' ? 'warning' : 'success' }} btn--sm toggle-status"
                                                data-id="{{ $feature->id }}">
                                                <i class="ri-{{ $feature->status === 'active' ? 'eye-off-line' : 'eye-line' }}"></i>
                                            </button>
                                            <button type="button" class="i-btn btn--danger btn--sm delete-feature"
                                                data-uid="{{ $feature->uid }}"
                                                data-name="{{ $feature->name }}">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="text-center py-5">
                                <i class="ri-list-check fs-50 text-muted"></i>
                                <p class="mt-3 text-muted">{{ translate("No features added yet") }}</p>
                                <button type="button" class="i-btn btn--primary btn--md" data-bs-toggle="modal" data-bs-target="#addFeatureModal">
                                    <i class="ri-add-line"></i> {{ translate("Add First Feature") }}
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <!-- Preview Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ translate("Preview") }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center p-4 border rounded-3">
                            <h5 class="mb-1">{{ translate("Sample Plan") }}</h5>
                            <h3 class="text-primary mb-3">$29.99<small class="text-muted fs-14">/{{ translate("month") }}</small></h3>
                            <div class="d-flex align-items-center justify-content-between mb-2 px-2">
                                <small class="fw-semibold">{{ translate("Credits Included") }}</small>
                                <i class="ri-information-line text-primary" title="{{ translate("Opens detailed info modal") }}"></i>
                            </div>
                            <div class="row g-2 mb-3 px-2">
                                <div class="col-4">
                                    <div class="text-center p-2 border rounded">
                                        <i class="ri-mail-line text-primary d-block"></i>
                                        <strong>100</strong>
                                        <small class="d-block text-muted">{{ translate("Email") }}</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-center p-2 border rounded">
                                        <i class="ri-chat-3-line text-success d-block"></i>
                                        <strong>50</strong>
                                        <small class="d-block text-muted">{{ translate("SMS") }}</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-center p-2 border rounded">
                                        <i class="ri-whatsapp-line text-success d-block" style="color: #25D366 !important;"></i>
                                        <strong>25</strong>
                                        <small class="d-block text-muted">{{ translate("WhatsApp") }}</small>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <ul class="list-unstyled text-start mb-4">
                                @forelse($features->take(5) as $feature)
                                    <li class="py-2 d-flex align-items-center gap-2">
                                        @if($feature->status === 'active')
                                            <i class="{{ $feature->icon ?? 'ri-checkbox-circle-line' }} text-success"></i>
                                            <span>{{ translate($feature->name) }}</span>
                                        @else
                                            <i class="{{ $feature->icon ?? 'ri-checkbox-circle-line' }} text-danger"></i>
                                            <span class="text-decoration-line-through text-muted">{{ translate($feature->name) }}</span>
                                        @endif
                                    </li>
                                @empty
                                    <li class="py-2 d-flex align-items-center gap-2">
                                        <i class="ri-check-line text-success"></i>
                                        <span class="text-muted">{{ translate("Your features will appear here") }}</span>
                                    </li>
                                @endforelse
                            </ul>
                            <button class="i-btn btn--primary btn--md w-100">{{ translate("Choose Plan") }}</button>
                        </div>
                        <p class="text-muted text-center mt-3 small">
                            {{ translate("This is how features appear on the pricing page") }}
                        </p>
                    </div>
                </div>

                <!-- Help Card -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h6 class="mb-3"><i class="ri-question-line"></i> {{ translate("How it works") }}</h6>
                        <ul class="small text-muted mb-0" style="padding-left: 1.25rem;">
                            <li class="mb-2">{{ translate("Add features here for marketing display") }}</li>
                            <li class="mb-2">{{ translate("Assign features to plans when creating/editing plans") }}</li>
                            <li class="mb-2">{{ translate("Included features show with checkmarks") }}</li>
                            <li class="mb-2">{{ translate("Excluded features show with strikethrough") }}</li>
                            <li>{{ translate("Technical details (credits, gateways) show in info modal") }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Add Feature Modal -->
<div class="modal fade" id="addFeatureModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.system.settings.plan-features.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate("Add New Feature") }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-inner mb-3">
                        <label class="form-label">{{ translate("Feature Name") }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="{{ translate('e.g., SMS Messaging') }}" required>
                    </div>
                    <div class="form-inner mb-3">
                        <label class="form-label">{{ translate("Icon") }}</label>
                        <input type="hidden" name="icon" id="selectedIcon" value="ri-checkbox-circle-line">
                        <div class="icon-grid">
                            @php
                                $icons = [
                                    'ri-checkbox-circle-line', 'ri-message-2-line', 'ri-mail-line', 'ri-whatsapp-line',
                                    'ri-contacts-line', 'ri-calendar-line', 'ri-search-eye-line', 'ri-flow-chart',
                                    'ri-bar-chart-line', 'ri-code-s-slash-line', 'ri-customer-service-2-line',
                                    'ri-shield-check-line', 'ri-rocket-line', 'ri-database-2-line', 'ri-cloud-line',
                                    'ri-smartphone-line', 'ri-global-line', 'ri-lock-line', 'ri-time-line',
                                    'ri-user-star-line', 'ri-vip-crown-line', 'ri-star-line', 'ri-heart-line',
                                    'ri-thumb-up-line', 'ri-send-plane-line', 'ri-chat-3-line', 'ri-file-list-line',
                                    'ri-folder-line', 'ri-image-line', 'ri-video-line', 'ri-attachment-line'
                                ];
                            @endphp
                            @foreach($icons as $icon)
                                <div class="icon-option {{ $icon === 'ri-checkbox-circle-line' ? 'selected' : '' }}" data-icon="{{ $icon }}">
                                    <i class="{{ $icon }}"></i>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="form-inner">
                        <label class="form-label">{{ translate("Description") }} <small class="text-muted">({{ translate("Optional") }})</small></label>
                        <input type="text" name="description" class="form-control" placeholder="{{ translate('Brief description...') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <!-- <button type="button" class="i-btn btn--dark btn--md" data-bs-dismiss="modal">{{ translate("Cancel") }}</button> -->
                    <button type="submit" class="i-btn btn--primary btn--md">{{ translate("Add Feature") }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Feature Modal -->
<div class="modal fade" id="editFeatureModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editFeatureForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate("Edit Feature") }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-inner mb-3">
                        <label class="form-label">{{ translate("Feature Name") }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>
                    <div class="form-inner mb-3">
                        <label class="form-label">{{ translate("Icon") }}</label>
                        <input type="hidden" name="icon" id="editSelectedIcon" value="">
                        <div class="icon-grid" id="editIconGrid">
                            @foreach($icons as $icon)
                                <div class="icon-option" data-icon="{{ $icon }}">
                                    <i class="{{ $icon }}"></i>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="form-inner">
                        <label class="form-label">{{ translate("Description") }}</label>
                        <input type="text" name="description" id="editDescription" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="i-btn btn--dark btn--md" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
                    <button type="submit" class="i-btn btn--primary btn--md">{{ translate("Update Feature") }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade confirm-modal" id="deleteFeatureModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <form id="deleteFeatureForm" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <div class="confirm-modal-icon danger">
                        <i class="ri-delete-bin-line"></i>
                    </div>
                    <h5 class="confirm-modal-title">{{ translate("Delete Feature?") }}</h5>
                    <p class="confirm-modal-text">{{ translate("Are you sure you want to delete") }} "<span id="deleteFeatureName"></span>"?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="i-btn btn--dark btn--md" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
                    <button type="submit" class="i-btn btn--danger btn--md">{{ translate("Delete") }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('script-include')
<script src="{{ asset('assets/theme/global/js/jquery-ui.min.js') }}"></script>
@endpush

@push('script-push')
<script>
(function($) {
    "use strict";

    // Icon Selection - Add Modal
    $('#addFeatureModal .icon-option').on('click', function() {
        $('#addFeatureModal .icon-option').removeClass('selected');
        $(this).addClass('selected');
        $('#selectedIcon').val($(this).data('icon'));
    });

    // Icon Selection - Edit Modal
    $('#editIconGrid').on('click', '.icon-option', function() {
        $('#editIconGrid .icon-option').removeClass('selected');
        $(this).addClass('selected');
        $('#editSelectedIcon').val($(this).data('icon'));
    });

    // Edit Feature
    $('.edit-feature').on('click', function() {
        const uid = $(this).data('uid');
        const name = $(this).data('name');
        const icon = $(this).data('icon');
        const description = $(this).data('description');

        $('#editFeatureForm').attr('action', '{{ route("admin.system.settings.plan-features.update", "") }}/' + uid);
        $('#editName').val(name);
        $('#editDescription').val(description);
        $('#editSelectedIcon').val(icon);

        $('#editIconGrid .icon-option').removeClass('selected');
        $('#editIconGrid .icon-option[data-icon="' + icon + '"]').addClass('selected');

        $('#editFeatureModal').modal('show');
    });

    // Delete Feature
    $('.delete-feature').on('click', function() {
        const uid = $(this).data('uid');
        const name = $(this).data('name');

        $('#deleteFeatureForm').attr('action', '{{ route("admin.system.settings.plan-features.destroy", "") }}/' + uid);
        $('#deleteFeatureName').text(name);

        $('#deleteFeatureModal').modal('show');
    });

    // Toggle Status
    $('.toggle-status').on('click', function() {
        const id = $(this).data('id');
        const btn = $(this);

        $.ajax({
            url: '{{ route("admin.system.settings.plan-features.status") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                id: id
            },
            success: function(response) {
                if (response.status && response.reload) {
                    location.reload();
                }
            },
            error: function() {
                notify('error', '{{ translate("Failed to update status") }}');
            }
        });
    });

    // Sortable
    $('#sortableFeatures').sortable({
        handle: '.drag-handle',
        placeholder: 'feature-item ui-sortable-placeholder',
        update: function(event, ui) {
            const order = $(this).sortable('toArray', { attribute: 'data-id' });

            $.ajax({
                url: '{{ route("admin.system.settings.plan-features.order") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    order: order
                },
                success: function(response) {
                    if (response.status) {
                        notify('success', '{{ translate("Order updated") }}');
                    }
                },
                error: function() {
                    notify('error', '{{ translate("Failed to update order") }}');
                }
            });
        }
    });

})(jQuery);
</script>
@endpush

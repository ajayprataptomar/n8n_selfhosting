@push("style-include")
  <link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
  <style>
    .subscribers-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        background: rgba(99, 102, 241, 0.1);
        color: #6366f1;
        border-radius: 20px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .subscribers-badge:hover {
        background: rgba(99, 102, 241, 0.2);
    }
    .sync-icon-wrapper {
        display: flex;
        justify-content: center;
        margin-bottom: 1rem;
    }
    .sync-icon {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: rgba(99, 102, 241, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .sync-icon i {
        font-size: 1.75rem;
        color: #6366f1;
    }
    .sync-option {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 1rem;
        border: 1px solid var(--color-border-light);
        border-radius: 10px;
        margin-bottom: 0.75rem;
    }
    .sync-option.selected {
        border-color: var(--color-primary);
        background: rgba(99, 102, 241, 0.05);
    }
    .sync-option .form-check-input {
        margin-top: 0.25rem;
    }
    .sync-option-content h6 {
        margin: 0 0 0.25rem;
        font-weight: 600;
    }
    .sync-option-content p {
        margin: 0;
        font-size: 0.8125rem;
        color: var(--text-muted);
    }
    .sync-warning {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        padding: 0.75rem;
        background: rgba(245, 158, 11, 0.1);
        border-radius: 8px;
        font-size: 0.8125rem;
        color: #92400e;
        margin-top: 1rem;
    }
    .sync-warning i {
        color: #f59e0b;
        flex-shrink: 0;
        margin-top: 0.125rem;
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
                    <a href="{{ route("admin.dashboard") }}">{{ translate("Dashboard") }}</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page"> {{ $title }} </li>
                </ol>
                </nav>
            </div>
            </div>
        </div>
        <div class="table-filter mb-4">
            <form action="{{route(Route::currentRouteName())}}" class="filter-form">
               
                <div class="row g-3">
                    <div class="col-xl-3 col-lg-3">
                        <div class="filter-search">
                            <input type="search" value="{{request()->search}}" name="search" class="form-control" id="filter-search" placeholder="{{ translate("Search by name") }}" />
                            <span><i class="ri-search-line"></i></span>
                        </div>
                    </div>
                    <div class="col-xl-7 col-lg-9 offset-xl-2">
                        <div class="filter-action">
                            <select data-placeholder="{{translate('Select A Status')}}" class="form-select select2-search" name="status" aria-label="Default select example">
                                <option value=""></option>
                                <option {{ request()->status == \App\Enums\StatusEnum::TRUE->status() ? 'selected' : ''  }} value="{{ \App\Enums\StatusEnum::TRUE->status() }}">{{ translate("Active") }}</option>
                                <option {{ request()->status == \App\Enums\StatusEnum::FALSE->status() ? 'selected' : ''  }} value="{{ \App\Enums\StatusEnum::FALSE->status() }}">{{ translate("Inactive") }}</option>
                            </select>
                            <div class="input-group">
                                <input type="text" class="form-control" id="datePicker" name="date" value="{{request()->input('date')}}"  placeholder="{{translate('Filter by date')}}"  aria-describedby="filterByDate">
                                <span class="input-group-text" id="filterByDate">
                                    <i class="ri-calendar-2-line"></i>
                                </span>
                            </div>
                            <button type="submit" class="filter-action-btn ">
                                <i class="ri-menu-search-line"></i> {{ translate("Filter") }} 
                            </button>
                            <a class="filter-action-btn bg-danger text-white" href="{{route(Route::currentRouteName())}}">
                                <i class="ri-refresh-line"></i> {{ translate("Reset") }} 
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="card-header-left">
                    <h4 class="card-title">{{ translate("Membership Plans") }}</h4>
                </div>
                <div class="card-header-right">
                    <div class="d-flex gap-3 align-item-center">
                        <button class="bulk-action i-btn btn--danger btn--sm bulk-delete-btn d-none">
                            <i class="ri-delete-bin-6-line"></i>
                        </button>

                        <div class="bulk-action form-inner d-none">
                            <select class="form-select" data-show="5" id="bulk_status" name="status">
                                <option disabled selected>{{ translate("Select a status") }}</option>
                                <option value="{{ \App\Enums\StatusEnum::TRUE->status() }}">{{ translate("Active") }}</option>
                                <option value="{{ \App\Enums\StatusEnum::FALSE->status() }}">{{ translate("Inactive") }}</option>
                            </select>
                        </div>
                        
                        <a class="i-btn btn--primary btn--sm" href="{{ route("admin.membership.plan.create") }}">
                            <i class="ri-add-fill fs-16"></i> {{ translate("Create Plan") }} 
                        </a>
                    </div>
                </div>
            </div>
        
            <div class="card-body px-0 pt-0">
            <div class="table-container">
                <table>
                <thead>
                    <tr>
                    <th scope="col">
                        <div class="form-check">
                        <input class="check-all form-check-input" type="checkbox" value="" id="checkAll" />
                        <label class="form-check-label" for="checkedAll"> {{ translate("SL No.") }} </label>
                        </div>
                    </th>
                    <th scope="col">{{ translate("Name") }}</th>
                    <th scope="col">{{ translate("Amount") }}</th>
                    <th scope="col">{{ translate("Credit & Duration") }}</th>
                    <th scope="col">{{ translate("Status") }}</th>
                    <th scope="col">{{ translate("Recommended Status") }}</th>
                    <th scope="col">{{ translate("Option") }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($plans as $plan)
                        <tr>
                            <td>
                                <div class="form-check">
                                    <input type="checkbox" value="{{$plan->id}}" name="ids[]" class="data-checkbox form-check-input" id="{{$plan->id}}" />
                                    <label class="form-check-label fw-semibold text-dark" for="bulk-{{$loop->iteration}}">{{$loop->iteration}}</label>
                                </div>
                            </td>
                            <td>
                                <p class="text-dark">{{ $plan->name }}</p>
                            </td>
                            <td data-label="{{ translate('Amount')}}">
                                <span>{{shortAmount($plan->amount)}} {{getDefaultCurrencyCode(json_decode(site_settings('currencies'), true))}}</span>
                                <p>{{ translate("Affiliate Commission: ") }} {{ $plan->affiliate_commission }} {{ translate("%") }}</p>
                               
                            </td>

                            <td data-label="{{ translate('Credits')}}">
                                <div class="d-flex align-items-center gap-2 ">
                                    <div class="lh-1">
                                        <p class="fs-12 fw-medium mb-2">{{ translate('Email Credit: ')}} {{$plan->email?->credits == -1 ? translate("Unlimited") : $plan->email?->credits}} / {{@$plan->email?->credits_per_day == 0 ? translate("Unlimited") : @$plan->email?->credits_per_day }} {{ translate(" Per Day") }}</p>
                                        <p class="fs-12 fw-medium mb-2">{{ translate('SMS Credit:')}} {{$plan->sms?->credits == -1 ? translate("Unlimited") : $plan->sms?->credits}} / {{@$plan->sms?->credits_per_day == 0 ? translate("Unlimited") : @$plan->sms?->credits_per_day}} {{ translate(" Per Day") }} </p>
                                        <p class="fs-12 fw-medium mb-2">{{ translate('WhatsApp Credit:')}} {{$plan->whatsapp?->credits == -1 ? translate("Unlimited") : $plan->whatsapp?->credits}} / {{@$plan->whatsapp?->credits_per_day == 0 ? translate("Unlimited") : @$plan->whatsapp?->credits_per_day}} {{ translate(" Per Day") }}</p>
                                        <p class="fs-12 fw-medium mb-2">{{ translate('Duration: ')}}{{$plan->duration.translate(" Days")}} </p>
                                    </div>
                                </div>
                            </td>
                            <td data-label="{{ translate('Status')}}">
                                <div class="switch-wrapper checkbox-data">
                                    <input {{ $plan->status == \App\Enums\StatusEnum::TRUE->status() ? 'checked' : '' }} 
                                            type="checkbox" 
                                            class="switch-input statusUpdate" 
                                            data-id="{{ $plan->id }}"
                                            data-column="status"
                                            data-value="{{ $plan->status }}"
                                            data-route="{{route('admin.membership.plan.status.update')}}"
                                            id="{{ 'status_'.$plan->id }}" 
                                            name="is_default"/>
                                    <label for="{{ 'status_'.$plan->id }}" class="toggle">
                                        <span></span>
                                    </label>
                                </div>
                            </td>

                            <td data-label="{{ translate('Recommended Status') }}">
                                @if($plan->recommended_status == \App\Enums\StatusEnum::TRUE->status())
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="i-badge dot success-soft pill">{{ translate("Recommended") }}</span>
                                    </div>
                                @else
                                    <div class="switch-wrapper checkbox-data">
                                        <input {{ $plan->recommended_status == \App\Enums\StatusEnum::TRUE->status() ? 'checked' : '' }} 
                                                type="checkbox" 
                                                class="switch-input statusUpdate" 
                                                data-id="{{ $plan->id }}"
                                                data-column="recommended_status"
                                                data-value="{{ \App\Enums\StatusEnum::TRUE->status() }}"
                                                data-route="{{route('admin.membership.plan.status.update')}}"
                                                id="{{ 'recommended_status_'.$plan->id }}" 
                                                name="is_default"/>
                                        <label for="{{ 'recommended_status_'.$plan->id }}" class="toggle">
                                            <span></span>
                                        </label>
                                    </div>
                                @endif
                            </td>
                        
                            <td data-label={{ translate('Option')}}>
                                <div class="d-flex align-items-center gap-1">
                                    <button class="icon-btn btn-ghost btn-sm primary-soft circle sync-plan-btn"
                                            type="button"
                                            data-plan-id="{{ $plan->id }}"
                                            data-plan-name="{{ $plan->name }}">
                                        <i class="ri-refresh-line"></i>
                                        <span class="tooltiptext"> {{ translate("Sync Subscribers") }} </span>
                                    </button>
                                    <a class="icon-btn btn-ghost btn-sm success-soft circle" href="{{ route("admin.membership.plan.edit", $plan->id) }}">
                                        <i class="ri-edit-line"></i>
                                        <span class="tooltiptext"> {{ translate("Edit Membership Plan") }} </span>
                                    </a>
                                    <button class="icon-btn btn-ghost btn-sm danger-soft circle text-danger delete-membership-plan"
                                            type="button"
                                            data-plan-id="{{ $plan->id }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteMembershipPlan">
                                        <i class="ri-delete-bin-line"></i>
                                        <span class="tooltiptext"> {{ translate("Delete Membership Plan") }} </span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-muted text-center" colspan="100%">{{ translate('No Data Found')}}</td>
                        </tr>
                    @endforelse
                </tbody>
                </table>
            </div>
            @include('admin.partials.pagination', ['paginator' => $plans])
            </div>
        </div>
        </div>
    </main>

@endsection
@section('modal')
    <div class="modal fade actionModal" id="deleteMembershipPlan" tabindex="-1" aria-labelledby="deleteMembershipPlan" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered ">
            <div class="modal-content">
            <div class="modal-header text-start">
                <span class="action-icon danger">
                <i class="bi bi-exclamation-circle"></i>
                </span>
            </div>
            <form action="{{route('admin.membership.plan.delete')}}" method="POST">
                @csrf
                <div class="modal-body">
                    
                    <input type="hidden" name="id">
                    <div class="action-message">
                        <h5>{{ translate("Are you sure to delete this membership plan?") }}</h5>
                        
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="i-btn btn--dark outline btn--lg" data-bs-dismiss="modal"> {{ translate("Cancel") }} </button>
                    <button type="submit" class="i-btn btn--danger btn--lg" data-bs-dismiss="modal"> {{ translate("Delete") }} </button>
                </div>
            </form>
            </div>
        </div>
    </div>

    <div class="modal fade actionModal" id="bulkAction" tabindex="-1" aria-labelledby="bulkAction" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered ">
            <div class="modal-content">
            <div class="modal-header text-start">
                <span class="action-icon danger">
                <i class="bi bi-exclamation-circle"></i>
                </span>
            </div>
            <form action="{{route('admin.membership.plan.bulk')}}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">

                    <input type="hidden" name="id" value="">
                    <div class="action-message">
                        <h5>{{ translate("Are you sure to change the status for the selected data?") }}</h5>
                        <p>{{ translate("This action is irreversable") }}</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="i-btn btn--dark outline btn--lg" data-bs-dismiss="modal"> {{ translate("Cancel") }} </button>
                    <button type="submit" class="i-btn btn--danger btn--lg" data-bs-dismiss="modal"> {{ translate("Proceed") }} </button>
                </div>
            </form>
            </div>
        </div>
    </div>

    {{-- Sync Subscribers Modal --}}
    <div class="modal fade" id="syncSubscribersModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <div class="sync-icon-wrapper">
                        <div class="sync-icon">
                            <i class="ri-refresh-line"></i>
                        </div>
                    </div>
                    <h5 class="mb-2">{{ translate('Sync Plan Updates') }}</h5>
                    <p class="text-muted mb-1">{{ translate('Plan:') }} <strong id="syncPlanName"></strong></p>
                    <p class="text-muted mb-3">
                        <span id="syncSubscriberCount">0</span> {{ translate('active subscribers') }}
                    </p>

                    <div class="text-start">
                        <label class="sync-option" id="syncFeaturesOption">
                            <input type="checkbox" class="form-check-input" id="syncFeatures" checked disabled>
                            <div class="sync-option-content">
                                <h6><i class="ri-check-line text-success me-1"></i>{{ translate('Sync Features') }}</h6>
                                <p>{{ translate('Lead Generation, Workflow Automation, AI Intelligence features are automatically synced when you update the plan.') }}</p>
                            </div>
                        </label>

                        <label class="sync-option" for="syncCredits">
                            <input type="checkbox" class="form-check-input" id="syncCredits">
                            <div class="sync-option-content">
                                <h6>{{ translate('Sync Credits (Optional)') }}</h6>
                                <p>{{ translate('Update subscriber credits to match the plan. Only increases credits, never decreases to protect used credits.') }}</p>
                            </div>
                        </label>

                        <div class="sync-warning">
                            <i class="ri-information-line"></i>
                            <span>{{ translate('Credit sync only increases credits if the new plan limit is higher than the user\'s current balance. This protects revenue from used credits.') }}</span>
                        </div>
                    </div>

                    <input type="hidden" id="syncPlanId" value="">

                    <div class="d-flex gap-2 justify-content-center mt-4">
                        <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">
                            {{ translate('Cancel') }}
                        </button>
                        <button type="button" class="i-btn btn--primary btn--md" id="confirmSyncBtn">
                            <i class="ri-refresh-line me-1"></i> {{ translate('Sync Now') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push("script-include")
  <script src="{{asset('assets/theme/global/js/select2.min.js')}}"></script>  
@endpush

@push('script-push')
<script>
    (function($){
        "use strict";

        select2_search($('.select2-search').data('placeholder'));
        flatpickr("#datePicker", {
            dateFormat: "Y-m-d",
            mode: "range",
        });

        $('.delete-membership-plan').on('click', function() {
            const modal = $('#deleteMembershipPlan');
            modal.find('input[name=id]').val($(this).data('plan-id'));
            modal.modal('show');
        });

        // Sync Subscribers functionality
        var syncModal = new bootstrap.Modal(document.getElementById('syncSubscribersModal'));

        $('.sync-plan-btn').on('click', function() {
            var planId = $(this).data('plan-id');
            var planName = $(this).data('plan-name');

            $('#syncPlanId').val(planId);
            $('#syncPlanName').text(planName);
            $('#syncCredits').prop('checked', false);
            $('#syncSubscriberCount').text('...');

            syncModal.show();

            // Fetch subscriber count
            $.ajax({
                url: '{{ url("admin/membership/plan") }}/' + planId + '/subscribers/count',
                type: 'GET',
                success: function(response) {
                    $('#syncSubscriberCount').text(response.count);
                },
                error: function() {
                    $('#syncSubscriberCount').text('0');
                }
            });
        });

        // Toggle sync option styling
        $('#syncCredits').on('change', function() {
            $(this).closest('.sync-option').toggleClass('selected', $(this).is(':checked'));
        });

        // Confirm sync
        $('#confirmSyncBtn').on('click', function() {
            var btn = $(this);
            var planId = $('#syncPlanId').val();
            var syncCredits = $('#syncCredits').is(':checked');

            btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin me-1"></i> {{ translate("Syncing...") }}');

            $.ajax({
                url: '{{ url("admin/membership/plan") }}/' + planId + '/sync',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    sync_credits: syncCredits
                },
                success: function(response) {
                    syncModal.hide();
                    if (response.success) {
                        notify('success', response.message);
                    } else {
                        notify('error', response.message);
                    }
                },
                error: function(xhr) {
                    var message = '{{ translate("Sync failed. Please try again.") }}';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    notify('error', message);
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="ri-refresh-line me-1"></i> {{ translate("Sync Now") }}');
                }
            });
        });

        // Reset modal on hide
        $('#syncSubscribersModal').on('hidden.bs.modal', function() {
            $('#confirmSyncBtn').prop('disabled', false).html('<i class="ri-refresh-line me-1"></i> {{ translate("Sync Now") }}');
        });

    })(jQuery);
</script>
@endpush


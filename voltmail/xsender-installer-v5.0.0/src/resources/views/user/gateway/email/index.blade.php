@push("style-include")
  <link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
@endpush
@extends('user.gateway.index')
@section('tab-content')
@php
    $jsonArray   = json_encode($credentials);
    $plan_access = $allowedAccess->type == App\Enums\StatusENum::FALSE->status();
@endphp
<div class="tab-pane active fade show" id="{{url()->current()}}" role="tabpanel">
    <div class="table-filter mb-4">
        <form action="{{route(Route::currentRouteName())}}" class="filter-form">

            <div class="row g-3">
                <div class="col-xxl-3 col-xl-4 col-lg-4">
                    <div class="filter-search">
                        <input type="search" value="{{request()->search}}" name="search" class="form-control" id="filter-search" placeholder="{{ translate("Search by name") }}" />
                        <span><i class="ri-search-line"></i></span>
                    </div>
                </div>
                <div class="col-xxl-5 col-xl-6 col-lg-7 offset-xxl-4 offset-xl-2">
                    <div class="filter-action">

                        <div class="input-group">
                            <input type="text" class="form-control" id="datePicker" name="date" value="{{request()->input('date')}}"  placeholder="{{translate('Filter by date')}}"  aria-describedby="filterByDate">
                            <span class="input-group-text" id="filterByDate">
                                <i class="ri-calendar-2-line"></i>
                            </span>
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <button type="submit" class="filter-action-btn ">
                                <i class="ri-menu-search-line"></i> {{ translate("Filter") }}
                            </button>
                            <a class="filter-action-btn bg-danger text-white" href="{{route(Route::currentRouteName())}}">
                                <i class="ri-refresh-line"></i> {{ translate("Reset") }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <h4 class="card-title">{{$title}}</h4>
            </div>
            <div class="card-header-right d-flex align-content-center flex-wrap flex-sm-nowrap gap-2">
                @if($plan_access)
                    <button class="i-btn btn--primary btn--sm add-email-gateway space-nowrap" type="button" data-bs-toggle="modal" data-bs-target="#addEmailGateway">
                        <i class="ri-add-fill fs-16"></i> {{ translate("Add Gateway") }}
                    </button>
                @endif
            </div>
        </div>
        <div class="card-body px-0 pt-0">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">{{ translate("Gateway Name") }}</th>
                            <th scope="col">{{ translate("Gateway Type") }}</th>
                            @if($plan_access)<th scope="col">{{ translate("Default") }}</th>@endif
                            <th scope="col">{{ translate("Status") }}</th>
                            @if($plan_access)<th scope="col">{{ translate("Option") }}</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($gateways as $gateway)
                            @php
                                $driver_info = json_encode($gateway->meta_data);
                            @endphp
                            <tr class="@if($loop->even)@endif">

                                <td data-label="{{ translate('Gateway Name')}}"><span class="text-dark">{{ucfirst($gateway->name)}}</span></td>
                                <td data-label="{{ translate('Gateway Type')}}"><span class="text-dark">{{preg_replace('/[[:digit:]]/','', setInputLabel($gateway->type))}}</span></td>
                                @if($plan_access)
                                <td data-label="{{ translate('Default') }}">
                                    @if($gateway->is_default)
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="i-badge dot success-soft pill">{{ translate("Default") }}</span>
                                        </div>
                                    @else
                                        <div class="switch-wrapper checkbox-data">
                                            <input {{ $gateway->is_default ? 'checked' : '' }}
                                                    type="checkbox"
                                                    class="switch-input statusUpdate"
                                                    data-id="{{ $gateway->id }}"
                                                    data-column="is_default"
                                                    data-value="{{ $gateway->is_default ? 0 : 1 }}"
                                                    data-route="{{route('user.gateway.email.status.update')}}"
                                                    id="{{ 'default_'.$gateway->id }}"
                                                    name="is_default"/>
                                            <label for="{{ 'default_'.$gateway->id }}" class="toggle">
                                                <span></span>
                                            </label>
                                        </div>
                                    @endif
                                </td>
                                @endif
                                <td data-label="{{ translate('Status')}}">
                                    @if($plan_access)
                                    <div class="switch-wrapper checkbox-data">
                                        <input {{ empty($gateway->getRawOriginal('status')) || $gateway->getRawOriginal('status') == ''
                                                    ? ''
                                                    : ($gateway->getRawOriginal('status') == \App\Enums\Common\Status::ACTIVE->value ? 'checked' : '') }}
                                                type="checkbox"
                                                class="switch-input statusUpdate"
                                                data-id="{{ $gateway->id }}"
                                                data-column="status"
                                                data-value="{{ empty($gateway->getRawOriginal('status')) || $gateway->getRawOriginal('status') == ''
                                                            ? \App\Enums\Common\Status::ACTIVE->value
                                                            : ($gateway->getRawOriginal('status') == \App\Enums\Common\Status::ACTIVE->value
                                                                ? \App\Enums\Common\Status::INACTIVE->value
                                                                : \App\Enums\Common\Status::ACTIVE->value) }}"
                                                data-route="{{ route('user.gateway.email.status.update') }}"
                                                id="{{ 'status_' . $gateway->id }}"
                                                name="is_default"/>
                                        <label for="{{ 'status_'.$gateway->id }}" class="toggle">
                                            <span></span>
                                        </label>
                                    </div>
                                    @else
                                        <div class="d-flex align-items-center gap-2">
                                            {{ $gateway->status->badge() }}
                                        </div>
                                    @endif
                                </td>
                                @if($plan_access)
                                <td data-label={{ translate('Option')}}>
                                    <div class="d-flex align-items-center gap-1">
                                        <button class="icon-btn btn-ghost btn-sm success-soft circle update-email-gateway"
                                                type="button"
                                                data-url="{{ route('user.gateway.email.update', ['id' => $gateway->id])}}"
                                                    data-gateway_type="{{$gateway?->type}}"
                                                    data-gateway_name="{{$gateway?->name}}"
                                                    data-gateway_address="{{$gateway?->address}}"
                                                    data-bulk_contact_limit="{{$gateway?->bulk_contact_limit}}"
                                                    data-per_message_min_delay="{{$gateway?->per_message_min_delay}}"
                                                    data-per_message_max_delay="{{$gateway?->per_message_max_delay}}"
                                                    data-delay_after_count="{{$gateway?->delay_after_count}}"
                                                    data-reset_after_count="{{$gateway?->reset_after_count}}"
                                                    data-delay_after_duration="{{$gateway?->delay_after_duration}}"
                                                    data-gateway_driver_information="{{$driver_info}}"
                                                data-bs-toggle="tooltip"
                                                data-bs-title="{{ translate('Edit') }}">
                                            <i class="ri-edit-line"></i>
                                        </button>
                                        <button class="icon-btn btn-ghost btn-sm info-soft circle text-info quick-view"
                                                type="button"
                                                data-meta_data="{{$driver_info}}"
                                                data-uid="{{$gateway->uid}}"
                                                data-bs-toggle="tooltip"
                                                data-bs-title="{{ translate('Quick View') }}">
                                                <i class="ri-information-line"></i>
                                        </button>
                                        <button class="icon-btn btn-ghost btn-sm primary-soft circle text-primary test-individual-gateway"
                                                type="button"
                                                data-gateway-id="{{$gateway->id}}"
                                                data-gateway-name="{{$gateway->name}}"
                                                data-bs-toggle="tooltip"
                                                data-bs-title="{{ translate('Send Test Email') }}">
                                            <i class="ri-send-plane-line"></i>
                                        </button>
                                        <button class="icon-btn btn-ghost btn-sm danger-soft circle text-danger delete-email-gateway"
                                                type="button"
                                                data-gateway-id="{{$gateway->id}}"
                                                data-url="{{route('user.gateway.email.delete', ['id' => $gateway->id ])}}"
                                                data-bs-toggle="tooltip"
                                                data-bs-title="{{ translate('Delete') }}">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td class="text-muted text-center" colspan="100%">{{ translate('No Data Found')}}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('user.partials.pagination', ['paginator' => $gateways])
        </div>
    </div>
</div>

@endsection

@section('modal')
@if($plan_access)
<div class="modal fade" id="addEmailGateway" tabindex="-1" aria-labelledby="addEmailGateway" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered ">
        <div class="modal-content">
            <form action="{{route('user.gateway.email.store')}}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Add Email Gateway") }} </h5>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                        <i class="ri-close-large-line"></i>
                    </button>
                </div>
                <div class="modal-body modal-lg-custom-height">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="form-inner">
                                <label for="name" class="form-label"> {{ translate('Gateway Name')}} <sup class="text--danger">*</sup></label>
                                <input type="text" id="name" name="name" placeholder="{{ translate('e.g., My SMTP Server')}}" class="form-control" aria-label="name"/>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-inner">
                                <label for="address" class="form-label"> {{ translate('Sender Email Address')}} <sup class="text--danger">*</sup></label>
                                <input type="email" id="address" name="address" placeholder="{{ translate('e.g., noreply@yourdomain.com')}}" class="form-control" aria-label="address"/>
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="form-inner">
                                <label for="add_gateway_type" class="form-label">{{ translate("Gateway Type") }} <sup class="text--danger">*</sup></label>

                                <select data-placeholder="{{translate('Select a gateway type')}}" class="form-select select2-search gateway_type_add_modal" data-show="5" id="add_gateway_type" name="type">
                                    <option value=""></option>
                                    @foreach($credentials as $credential_key => $credential)
                                    @if(array_key_exists('allowed_gateways', (array)$user->runningSubscription()->currentPlan()->email) && $user->runningSubscription()->currentPlan()->email->allowed_gateways != null)
                                        @foreach($user->runningSubscription()->currentPlan()->email->allowed_gateways as $key => $value)

                                            @php
                                                $remaining = isset($gatewayCount[$key]) ? $value - $gatewayCount[$key] : $value;
                                            @endphp

                                            @if(preg_replace('/_/','',$key) == strtolower($credential_key) && $remaining > 0)

                                                <option value="{{strToLower($credential_key)}}">{{strtoupper($credential_key)}} ({{translate("Remaining Gateway: ").$remaining  }})</option>
                                            @endif
                                        @endforeach
                                    @endif
                                @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="row g-4 new-data-add-modal"></div>
                        </div>
                        {{-- Sending Limits - Collapsible --}}
                        <div class="col-12">
                            <div class="accordion" id="addSendingLimitsAccordion">
                                <div class="accordion-item border rounded">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-2 px-3" type="button" data-bs-toggle="collapse" data-bs-target="#addSendingLimitsBody">
                                            <i class="ri-speed-line me-2"></i> {{ translate("Sending Limits") }}
                                            <span class="text-muted ms-2 fs-12">({{ translate("Optional") }})</span>
                                        </button>
                                    </h2>
                                    <div id="addSendingLimitsBody" class="accordion-collapse collapse" data-bs-parent="#addSendingLimitsAccordion">
                                        <div class="accordion-body">
                                            <div class="row g-3">
                                                <div class="col-lg-6">
                                                    <div class="form-inner">
                                                        <label for="add_per_message_min_delay" class="form-label">{{ translate('Min Delay Per Message (Sec)') }}</label>
                                                        <input type="number" min="0" step="0.1" id="add_per_message_min_delay" name="per_message_min_delay" placeholder="0" class="form-control" value="0" />
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-inner">
                                                        <label for="add_per_message_max_delay" class="form-label">{{ translate('Max Delay Per Message (Sec)') }}</label>
                                                        <input type="number" min="0" step="0.1" id="add_per_message_max_delay" name="per_message_max_delay" placeholder="0" class="form-control" value="0" />
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-inner">
                                                        <label for="add_delay_after_count" class="form-label">{{ translate('Pause After N Messages') }}</label>
                                                        <input type="number" min="0" step="1" id="add_delay_after_count" name="delay_after_count" placeholder="0" class="form-control" value="0" />
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-inner">
                                                        <label for="add_delay_after_duration" class="form-label">{{ translate('Pause Duration (Sec)') }}</label>
                                                        <input type="number" min="0" step="0.1" id="add_delay_after_duration" name="delay_after_duration" placeholder="0" class="form-control" value="0" />
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="form-inner">
                                                        <label for="add_reset_after_count" class="form-label">{{ translate('Reset Counter After N Messages') }}</label>
                                                        <input type="number" min="0" step="1" id="add_reset_after_count" name="reset_after_count" placeholder="0" class="form-control" value="0" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="i-btn btn--danger outline btn--md" data-bs-dismiss="modal"> {{ translate("Close") }} </button>
                    <button type="submit" class="i-btn btn--primary btn--md"> {{ translate("Save") }} </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="updateEmailGateway" tabindex="-1" aria-labelledby="updateEmailGateway" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered ">
        <div class="modal-content">
            <form id="updateEmailGatewayForm" method="post">
                @csrf
                <input type="hidden" name="_method" value="PATCH">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Update Email Gateway") }} </h5>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                        <i class="ri-close-large-line"></i>
                    </button>
                </div>
                <div class="modal-body modal-lg-custom-height">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="form-inner">
                                <label for="edit_name" class="form-label"> {{ translate('Gateway Name')}} <sup class="text--danger">*</sup></label>
                                <input type="text" id="edit_name" name="name" placeholder="{{ translate('Enter Gateway Name')}}" class="form-control" aria-label="name"/>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-inner">
                                <label for="edit_address" class="form-label"> {{ translate('Sender Email Address')}} <sup class="text--danger">*</sup></label>
                                <input type="email" id="edit_address" name="address" placeholder="{{ translate('Enter Sender Email')}}" class="form-control" aria-label="address"/>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-inner">
                                <label for="gateway_type_edit" class="form-label">{{ translate("Gateway Type") }} <sup class="text--danger">*</sup></label>
                                <select data-placeholder="{{translate('Select a gateway type')}}" class="form-select select-gateway-type gateway_type_update_modal" data-show="5" id="gateway_type_edit" name="type">
                                    <option value=""></option>
                                </select>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="row g-4 new-data-edit-modal"></div>
                        </div>
                        <div class="col-12">
                            <div class="row g-4 oldData"></div>
                        </div>
                        {{-- Sending Limits - Collapsible --}}
                        <div class="col-12">
                            <div class="accordion" id="editSendingLimitsAccordion">
                                <div class="accordion-item border rounded">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-2 px-3" type="button" data-bs-toggle="collapse" data-bs-target="#editSendingLimitsBody">
                                            <i class="ri-speed-line me-2"></i> {{ translate("Sending Limits") }}
                                            <span class="text-muted ms-2 fs-12">({{ translate("Optional") }})</span>
                                        </button>
                                    </h2>
                                    <div id="editSendingLimitsBody" class="accordion-collapse collapse" data-bs-parent="#editSendingLimitsAccordion">
                                        <div class="accordion-body">
                                            <div class="row g-3">
                                                <div class="col-lg-6">
                                                    <div class="form-inner">
                                                        <label for="edit_per_message_min_delay" class="form-label">{{ translate('Min Delay Per Message (Sec)') }}</label>
                                                        <input type="number" min="0" step="0.1" id="edit_per_message_min_delay" name="per_message_min_delay" placeholder="0" class="form-control" value="0" />
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-inner">
                                                        <label for="edit_per_message_max_delay" class="form-label">{{ translate('Max Delay Per Message (Sec)') }}</label>
                                                        <input type="number" min="0" step="0.1" id="edit_per_message_max_delay" name="per_message_max_delay" placeholder="0" class="form-control" value="0" />
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-inner">
                                                        <label for="edit_delay_after_count" class="form-label">{{ translate('Pause After N Messages') }}</label>
                                                        <input type="number" min="0" step="1" id="edit_delay_after_count" name="delay_after_count" placeholder="0" class="form-control" value="0" />
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-inner">
                                                        <label for="edit_delay_after_duration" class="form-label">{{ translate('Pause Duration (Sec)') }}</label>
                                                        <input type="number" min="0" step="0.1" id="edit_delay_after_duration" name="delay_after_duration" placeholder="0" class="form-control" value="0" />
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="form-inner">
                                                        <label for="edit_reset_after_count" class="form-label">{{ translate('Reset Counter After N Messages') }}</label>
                                                        <input type="number" min="0" step="1" id="edit_reset_after_count" name="reset_after_count" placeholder="0" class="form-control" value="0" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="i-btn btn--danger outline btn--md" data-bs-dismiss="modal"> {{ translate("Close") }} </button>
                    <button type="submit" class="i-btn btn--primary btn--md"> {{ translate("Save") }} </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="quick_view" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">{{ translate("Email Gateway Information") }}</h5>
                <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <ul class="information-list"></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--primary outline btn--md" data-bs-dismiss="modal">{{ translate("Close") }}</button>
            </div>
        </div>
    </div>
</div>

{{-- Test Individual Gateway Modal --}}
<div class="modal fade actionModal" id="testGatewayModal" tabindex="-1" aria-labelledby="testGatewayModal" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header text-start">
                <span class="action-icon success">
                    <i class="ri-send-plane-line"></i>
                </span>
            </div>
            <div class="modal-body">
                <div class="action-message">
                    <h5>{{ translate("Send Test Email") }}</h5>
                    <p class="test-gateway-name mb-3"></p>
                </div>
                <div class="form-inner">
                    <label for="test_email_address" class="form-label">{{ translate("Recipient Email") }} <sup class="text--danger">*</sup></label>
                    <input type="email" class="form-control" id="test_email_address" name="test_email" placeholder="{{ translate('Enter email address to receive test') }}" />
                </div>
                <div class="test-result-message mt-3 d-none">
                    <div class="alert mb-0"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--lg" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
                <button type="button" class="i-btn btn--primary btn--lg send-test-email-btn" id="sendTestEmailBtn">
                    <i class="ri-send-plane-line me-1"></i> {{ translate("Send Test") }}
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade actionModal" id="deleteEmailGateway" tabindex="-1" aria-labelledby="deleteEmailGateway" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
        <div class="modal-header text-start">
            <span class="action-icon danger">
            <i class="bi bi-exclamation-circle"></i>
            </span>
        </div>
        <form method="POST" id="deleteEmailGateway">
            @csrf
            <div class="modal-body">
                <input type="hidden" name="_method" value="DELETE">
                <div class="action-message">
                    <h5>{{ translate("Are you sure to delete this gateway?") }}</h5>
                    <p>{{ translate("By clicking on 'Delete', you will permanently remove the gateway from the application") }}</p>
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
@endif
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

        var smtpPortMap = {
            'ssl': '465',
            'tls': '587',
            'starttls': '587',
            'pwmta': '25',
            'none': '25'
        };

        function buildCredentialFields(container, credData, oldValues, isSmtp) {
            container.empty();
            $.each(credData, function(key, v) {
                var filterkey = key.replace(/_/g, " ");
                var div = $('<div class="col-lg-6"></div>');

                if (key === 'encryption') {
                    var label = $('<label for="' + key + '" class="form-label text-capitalize">' + filterkey + ' <sup class="text--danger">*</sup></label>');
                    var select = $('<select class="form-select encryption-select" name="meta_data[' + key + ']" id="' + key + '"></select>');
                    $.each(v, function(name, method) {
                        var option = $('<option value="' + method + '">' + name + '</option>');
                        if (oldValues && oldValues[key] == method) {
                            option.attr("selected", true);
                        }
                        select.append(option);
                    });
                    div.append(label, select);
                    container.append(div);

                    select.on('change', function() {
                        var portField = container.find('input[name="meta_data[port]"]');
                        if (portField.length && smtpPortMap[$(this).val()]) {
                            portField.val(smtpPortMap[$(this).val()]);
                        }
                    });
                } else if (key === 'driver' && isSmtp) {
                    var hidden = $('<input type="hidden" name="meta_data[' + key + ']" value="SMTP">');
                    container.append(hidden);
                } else if (key === 'password') {
                    var label = $('<label for="' + key + '" class="form-label text-capitalize">' + filterkey + ' <sup class="text--danger">*</sup></label>');
                    var inputGroup = $('<div class="input-group"></div>');
                    var input = $('<input type="password" class="form-control" id="' + key + '" name="meta_data[' + key + ']" placeholder="Enter ' + filterkey + '" required>');
                    if (oldValues && oldValues[key]) input.val(oldValues[key]);
                    var toggleBtn = $('<button class="input-group-text toggle-password" type="button"><i class="ri-eye-off-line"></i></button>');
                    inputGroup.append(input, toggleBtn);
                    div.append(label, inputGroup);
                    container.append(div);
                } else if (key === 'port') {
                    var label = $('<label for="' + key + '" class="form-label text-capitalize">' + filterkey + ' <sup class="text--danger">*</sup></label>');
                    var input = $('<input type="number" min="1" max="65535" class="form-control" id="' + key + '" name="meta_data[' + key + ']" placeholder="e.g., 465 or 587" required>');
                    if (oldValues && oldValues[key]) {
                        input.val(oldValues[key]);
                    }
                    div.append(label, input);
                    container.append(div);
                } else {
                    var label = $('<label for="' + key + '" class="form-label text-capitalize">' + filterkey + ' <sup class="text--danger">*</sup></label>');
                    var input = $('<input type="text" class="form-control" id="' + key + '" name="meta_data[' + key + ']" placeholder="Enter ' + filterkey + '" required>');
                    if (oldValues && oldValues[key]) input.val(oldValues[key]);
                    div.append(label, input);
                    container.append(div);
                }
            });
        }

        $(document).ready(function() {

            var oldType = '';
            var oldInfo = [];
            var bulkContactLimit = 1;

            // Toggle password visibility
            $(document).on('click', '.toggle-password', function() {
                var input = $(this).siblings('input');
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    $(this).find('i').removeClass('ri-eye-off-line').addClass('ri-eye-line');
                } else {
                    input.attr('type', 'password');
                    $(this).find('i').removeClass('ri-eye-line').addClass('ri-eye-off-line');
                }
            });

            // Test individual gateway
            var currentTestGatewayId = null;
            $('.test-individual-gateway').on('click', function() {
                currentTestGatewayId = $(this).data('gateway-id');
                var gatewayName = $(this).data('gateway-name');
                var modal = $('#testGatewayModal');
                modal.find('.test-gateway-name').text('{{ translate("Test gateway:") }} ' + gatewayName);
                modal.find('#test_email_address').val('');
                modal.find('.test-result-message').addClass('d-none');
                modal.modal('show');
            });

            $('#sendTestEmailBtn').on('click', function() {
                var btn = $(this);
                var email = $('#test_email_address').val();
                var resultDiv = $('#testGatewayModal .test-result-message');
                var alertDiv = resultDiv.find('.alert');

                if (!email) {
                    notify('error', '{{ translate("Please enter a test email address") }}');
                    return;
                }
                if (btn.hasClass('disabled')) return;

                btn.addClass('disabled').prepend('<span class="loading-spinner spinner-border spinner-border-sm me-1" aria-hidden="true"></span>');

                $.ajax({
                    method: 'post',
                    url: "{{ route('user.gateway.email.test') }}",
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    data: {
                        'email': email,
                        'gateway_id': currentTestGatewayId
                    },
                    dataType: 'json'
                }).then(function(response) {
                    btn.find('.loading-spinner').remove();
                    btn.removeClass('disabled');
                    resultDiv.removeClass('d-none');
                    if (response.status) {
                        alertDiv.removeClass('alert-danger').addClass('alert-success').html('<i class="ri-check-line me-1"></i> ' + response.message);
                        notify('success', response.message);
                    } else {
                        alertDiv.removeClass('alert-success').addClass('alert-danger').html('<i class="ri-error-warning-line me-1"></i> ' + response.message);
                        notify('error', response.message);
                    }
                }).fail(function() {
                    btn.find('.loading-spinner').remove();
                    btn.removeClass('disabled');
                    notify('error', '{{ translate("Request failed. Please try again.") }}');
                });
            });

            // Add gateway modal
            $('.add-email-gateway').on('click', function() {
                const modal = $('#addEmailGateway');
                modal.modal('show');
            });

            // Update gateway modal
            $('.update-email-gateway').on('click', function() {

                $('.new-data-edit-modal').empty();
                $('.oldData').empty();
                $('.select-gateway-type').empty();

                var modal = $('#updateEmailGateway');
                modal.find('form[id=updateEmailGatewayForm]').attr('action', $(this).data('url'));
                modal.find('input[name=name]').val($(this).data('gateway_name'));
                modal.find('input[name=address]').val($(this).data('gateway_address'));

                bulkContactLimit = $(this).data('bulk_contact_limit');
                modal.find('input[name=per_message_min_delay]').val($(this).data('per_message_min_delay'));
                modal.find('input[name=per_message_max_delay]').val($(this).data('per_message_max_delay'));
                modal.find('input[name=delay_after_count]').val($(this).data('delay_after_count'));
                modal.find('input[name=reset_after_count]').val($(this).data('reset_after_count'));
                modal.find('input[name=delay_after_duration]').val($(this).data('delay_after_duration'));

                // Auto-expand sending limits if any value is non-zero
                var hasLimits = $(this).data('per_message_min_delay') > 0 || $(this).data('per_message_max_delay') > 0 ||
                    $(this).data('delay_after_count') > 0 || $(this).data('reset_after_count') > 0 || $(this).data('delay_after_duration') > 0;
                if (hasLimits) {
                    $('#editSendingLimitsBody').addClass('show');
                } else {
                    $('#editSendingLimitsBody').removeClass('show');
                }

                var previousType = $(this).data('gateway_type');
                oldType = $(this).data('gateway_type');

                var user = <?php echo json_encode(@$user->runningSubscription()->currentPlan()->email->allowed_gateways) ?>;
                var data = Object.keys(<?php echo $jsonArray ?>);
                var creds = <?php echo $jsonArray ?>;
                $.each(data, function(key, value) {

                    $.each(user, function(u_key, u_value) {

                        if(u_key == value) {
                            var option = $('<option class="text-uppercase gatewayType" value="'+ value +'">'+ value.toUpperCase() +'</option>');
                            $('.select-gateway-type').append(option);
                            if(oldType == value){
                                if (creds[value].native_bulk_support == true) {
                                    var bulkLimitBlock = '<div class="col-12" id="bulk_contact_limit_wrapper"><div class="form-inner"><label for="bulk_contact_limit" class="form-label">{{ translate("Bulk Contact Limit") }}</label><input value="1" type="number" min="1" id="bulk_contact_limit" name="bulk_contact_limit" placeholder="{{ translate("Enter Bulk Contact Limit") }}" class="form-control" /></div></div>';
                                    $('.oldData').append(bulkLimitBlock);
                                }
                                option.attr("selected", true);
                            }
                        }
                    });
                });
                modal.find('input[name=bulk_contact_limit]').val(bulkContactLimit);
                oldInfo = $(this).data('gateway_driver_information');
                var isSmtp = (oldType === 'smtp');

                buildCredentialFields($('.oldData'), creds[oldType].meta_data, oldInfo, isSmtp);

                // Set initial encryption value
                if (oldInfo && oldInfo.encryption) {
                    $('.oldData').find('select[name="meta_data[encryption]"]').val(oldInfo.encryption);
                }
                // Set all old values
                $.each(oldInfo, function(key, value) {
                    var field = $('.oldData').find('[name="meta_data[' + key + ']"]');
                    if (field.length) field.val(value);
                });

                modal.modal('show');
            });

            // Add modal - gateway type change
            $('.gateway_type_add_modal').on('change', function(){

                var container = $('.new-data-add-modal');
                container.empty();
                var selectedType = this.value;
                var typeConfig = <?php echo $jsonArray ?>[selectedType];
                var data = typeConfig.meta_data;
                var native_bulk_support = typeConfig.native_bulk_support;
                var isSmtp = (selectedType === 'smtp');

                if (native_bulk_support == true) {
                    var bulkLimitBlock = '<div class="col-12" id="bulk_contact_limit_wrapper"><div class="form-inner"><label for="bulk_contact_limit" class="form-label">{{ translate("Bulk Contact Limit") }}</label><input value="1" type="number" min="1" id="bulk_contact_limit" name="bulk_contact_limit" placeholder="{{ translate("Enter Bulk Contact Limit") }}" class="form-control" /></div></div>';
                    container.append(bulkLimitBlock);
                }
                buildCredentialFields(container, data, null, isSmtp);

                // Auto-set port based on default encryption
                if (isSmtp) {
                    var encSelect = container.find('.encryption-select');
                    if (encSelect.length) {
                        var defaultEnc = encSelect.val();
                        var portField = container.find('input[name="meta_data[port]"]');
                        if (portField.length && smtpPortMap[defaultEnc]) {
                            portField.val(smtpPortMap[defaultEnc]);
                        }
                    }
                }
            });

            // Update modal - gateway type change
            $('.gateway_type_update_modal').on('change', function(){

                var editContainer = $('.new-data-edit-modal');
                editContainer.empty();
                $('.oldData').empty();

                var selectedType = this.value;
                var typeConfig = <?php echo $jsonArray ?>[selectedType];
                var data = typeConfig.meta_data;
                var native_bulk_support = typeConfig.native_bulk_support;
                var isSmtp = (selectedType === 'smtp');

                if(selectedType != oldType){
                    if (native_bulk_support == true) {
                        var bulkLimitBlock = '<div class="col-12" id="bulk_contact_limit_wrapper"><div class="form-inner"><label for="bulk_contact_limit" class="form-label">{{ translate("Bulk Contact Limit") }}</label><input value="1" type="number" min="1" id="bulk_contact_limit" name="bulk_contact_limit" placeholder="{{ translate("Enter Bulk Contact Limit") }}" class="form-control" /></div></div>';
                        editContainer.append(bulkLimitBlock);
                    }
                    buildCredentialFields(editContainer, data, null, isSmtp);

                    // Auto-set port
                    if (isSmtp) {
                        var encSelect = editContainer.find('.encryption-select');
                        if (encSelect.length) {
                            var defaultEnc = encSelect.val();
                            var portField = editContainer.find('input[name="meta_data[port]"]');
                            if (portField.length && smtpPortMap[defaultEnc]) {
                                portField.val(smtpPortMap[defaultEnc]);
                            }
                        }
                    }
                }
                else {
                    if (native_bulk_support == true) {
                        var bulkLimitBlock = '<div class="col-12" id="bulk_contact_limit_wrapper"><div class="form-inner"><label for="bulk_contact_limit" class="form-label">{{ translate("Bulk Contact Limit") }}</label><input value="1" type="number" min="1" id="bulk_contact_limit" name="bulk_contact_limit" placeholder="{{ translate("Enter Bulk Contact Limit") }}" class="form-control" /></div></div>';
                        $('.oldData').append(bulkLimitBlock);
                    }
                    $('#updateEmailGateway').find('input[name=bulk_contact_limit]').val(bulkContactLimit);

                    buildCredentialFields($('.oldData'), data, oldInfo, isSmtp);
                    // Restore old values
                    $.each(oldInfo, function(key, value) {
                        var field = $('.oldData').find('[name="meta_data[' + key + ']"]');
                        if (field.length) field.val(value);
                    });
                }
            });

            // Quick view modal
            $('.quick-view').on('click', function() {
                const modal = $('#quick_view');
                const modalBodyInformation = modal.find('.modal-body .information-list');
                modalBodyInformation.empty();

                var driver = $(this).data('meta_data');
                var uid = $(this).data('uid');

                $.each(driver, function(key, value) {
                    if (key === 'password') value = '********';
                    const listItem = $('<li>');
                    const paramKeySpan = $('<span>').text(textFormat(['_'], key, ' '));
                    const arrowIcon = $('<i>').addClass('bi bi-arrow-right');
                    const paramValueSpan = $('<span>').addClass('text-break text-muted').text(value);

                    listItem.append(paramKeySpan).append(arrowIcon).append(paramValueSpan);
                    modalBodyInformation.append(listItem);
                });
                if(uid) {
                    var title = 'gateway_identifier';
                    const listItem = $('<li>');
                    const paramKeySpan = $('<span>').text(textFormat(['_'], title, ' '));
                    const arrowIcon = $('<i>').addClass('bi bi-arrow-right');
                    const paramValueSpan = $(`<span title='${title}'>`).addClass('text-break text-muted').text(uid);

                    listItem.append(paramKeySpan).append(arrowIcon).append(paramValueSpan);
                    modalBodyInformation.append(listItem);
                }
                modal.modal('show');
            });

            // Delete gateway
            $('.delete-email-gateway').on('click', function() {

                var modal = $('#deleteEmailGateway');
                modal.find('form[id=deleteEmailGateway]').attr('action', $(this).data('url'));
                modal.modal('show');
            });
        });
	})(jQuery);
</script>
@endpush

@extends('admin.gateway.index')
@section('tab-content')
@php
    $jsonArray = json_encode($credentials);
    $customApiTranslationsJson = json_encode($customApiTranslations);
@endphp

<div class="tab-pane active fade show" id="{{url()->current()}}" role="tabpanel">
    <div class="table-filter mb-4">
        <form action="{{route(Route::currentRouteName())}}" class="filter-form">
            <div class="row g-3">
                <div class="col-xxl-3 col-xl-4 col-lg-4">
                    <div class="filter-search">
                        <input type="search" value="{{request()->search}}" name="search" class="form-control" id="filter-search" placeholder="{{ translate('Search by name') }}" />
                        <span><i class="ri-search-line"></i></span>
                    </div>
                </div>
                <div class="col-xxl-5 col-xl-6 col-lg-7 offset-xxl-4 offset-xl-2">
                    <div class="filter-action">
                        <div class="input-group">
                            <input type="text" class="form-control" id="datePicker" name="date" value="{{request()->input('date')}}" placeholder="{{translate('Filter by date')}}" aria-describedby="filterByDate">
                            <span class="input-group-text" id="filterByDate">
                                <i class="ri-calendar-2-line"></i>
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <button type="submit" class="filter-action-btn">
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
                <h4 class="card-title">{{ translate("Contacts") }}</h4>
            </div>
            <div class="card-header-right">
                <div class="d-flex gap-3 align-item-center">
                    <button class="bulk-action i-btn btn--danger btn--sm bulk-delete-btn d-none">
                        <i class="ri-delete-bin-6-line"></i>
                    </button>
                    <div class="bulk-action form-inner d-none">
                        <select class="form-select" data-show="5" id="bulk_status" name="status">
                            <option disabled selected>{{ translate("Select a status") }}</option>
                            <option value="{{ \App\Enums\Common\Status::ACTIVE->value }}">{{ translate("Active") }}</option>
                            <option value="{{ \App\Enums\Common\Status::INACTIVE->value  }}">{{ translate("Inactive") }}</option>
                        </select>
                    </div>
                    <button class="i-btn btn--primary btn--sm add-sms-gateway" type="button" data-bs-toggle="modal" data-bs-target="#addSmsGateway">
                        <i class="ri-add-fill fs-16"></i> {{ translate("Add Gateway") }}
                    </button>
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
                            <th scope="col">{{ translate("Gateway Name") }}</th>
                            <th scope="col">{{ translate("Gateway Type") }}</th>
                            <th scope="col">{{ translate("Default") }}</th>
                            <th scope="col">{{ translate("Status") }}</th>
                            <th scope="col">{{ translate("Option") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($gateways as $sms_gateway)
                            @php
                                $driver_info = json_encode($sms_gateway->meta_data);
                            @endphp
                            <tr class="@if($loop->even)@endif">
                                <td>
                                    <div class="form-check">
                                        <input type="checkbox" value="{{$sms_gateway->id}}" name="ids[]" class="data-checkbox form-check-input" id="{{$sms_gateway->id}}" />
                                        <label class="form-check-label fw-semibold text-dark" for="bulk-{{$loop->iteration}}">{{$loop->iteration}}</label>
                                    </div>
                                </td>
                                <td data-label="{{ translate('Gateway Name')}}"><span class="text-dark">{{ucfirst($sms_gateway->name)}}</span></td>
                                <td data-label="{{ translate('Gateway Type')}}"><span class="text-dark">{{preg_replace('/[[:digit:]]/','', setInputLabel($sms_gateway->type))}}</span></td>
                                <td data-label="{{ translate('Default') }}">
                                    @if($sms_gateway->is_default == \App\Enums\StatusEnum::TRUE->status())
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="i-badge dot success-soft pill">{{ translate("Default") }}</span>
                                        </div>
                                    @else
                                        <div class="switch-wrapper checkbox-data">
                                            <input {{ $sms_gateway->is_default == App\Enums\StatusEnum::TRUE->status() ? 'checked' : '' }}
                                                type="checkbox"
                                                class="switch-input statusUpdate"
                                                data-id="{{ $sms_gateway->id }}"
                                                data-column="is_default"
                                                data-value="{{ $sms_gateway->is_default ? 0 : 1 }}"
                                                data-route="{{route('admin.gateway.sms.api.status.update')}}"
                                                id="{{ 'default_'.$sms_gateway->id }}"
                                                name="is_default"/>
                                            <label for="{{ 'default_'.$sms_gateway->id }}" class="toggle">
                                                <span></span>
                                            </label>
                                        </div>
                                    @endif
                                </td>
                                <td data-label="{{ translate('Status')}}">
                                    <div class="switch-wrapper checkbox-data">
                                        <input {{ empty($sms_gateway->getRawOriginal('status')) 
                                                    || $sms_gateway->getRawOriginal('status') == '' 
                                                        ? '' 
                                                        : ($sms_gateway->getRawOriginal('status') == \App\Enums\Common\Status::ACTIVE->value ? 'checked' : '') }}
                                                type="checkbox"
                                                class="switch-input statusUpdate"
                                                data-id="{{ $sms_gateway->id }}"
                                                data-column="status"
                                                data-value="{{ empty($sms_gateway->getRawOriginal('status')) || $sms_gateway->getRawOriginal('status') == '' 
                                                            ? \App\Enums\Common\Status::ACTIVE->value 
                                                            : ($sms_gateway->getRawOriginal('status') == \App\Enums\Common\Status::ACTIVE->value 
                                                                ? \App\Enums\Common\Status::INACTIVE->value 
                                                                : \App\Enums\Common\Status::ACTIVE->value) }}"
                                                data-route="{{ route('admin.gateway.sms.api.status.update') }}"
                                                id="{{ 'status_' . $sms_gateway->id }}"
                                                name="is_default"/>
                                        <label for="{{ 'status_'.$sms_gateway->id }}" class="toggle">
                                            <span></span>
                                        </label>
                                    </div>
                                </td>
                                <td data-label={{ translate('Option')}}>
                                    <div class="d-flex align-items-center gap-1">
                                        <button class="icon-btn btn-ghost btn-sm success-soft circle update-sms-gateway"
                                            type="button"
                                            data-url="{{ route('admin.gateway.sms.api.update', ['id' => $sms_gateway->id])}}"
                                            data-gateway_type="{{$sms_gateway?->type}}"
                                            data-gateway_name="{{$sms_gateway?->name}}"
                                            data-bulk_contact_limit="{{$sms_gateway?->bulk_contact_limit}}"
                                            data-per_message_min_delay="{{$sms_gateway?->per_message_min_delay}}"
                                            data-per_message_max_delay="{{$sms_gateway?->per_message_max_delay}}"
                                            data-delay_after_count="{{$sms_gateway?->delay_after_count}}"
                                            data-reset_after_count="{{$sms_gateway?->reset_after_count}}"
                                            data-delay_after_duration="{{$sms_gateway?->delay_after_duration}}"
                                            data-meta_data="{{$driver_info}}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#updateSmsGateway">
                                            <i class="ri-edit-line"></i>
                                            <span class="tooltiptext"> {{ translate("Update") }} </span>
                                        </button>
                                        <button class="icon-btn btn-ghost btn-sm info-soft circle text-info quick-view"
                                            type="button"
                                            data-sms_gateways="{{ $driver_info }}"
                                            data-id="{{ $sms_gateway->id }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#quick_view">
                                            <i class="ri-information-line"></i>
                                            <span class="tooltiptext"> {{ translate("Quick View") }} </span>
                                        </button>
                                        <button class="icon-btn btn-ghost btn-sm danger-soft circle text-danger delete-sms-gateway"
                                            type="button"
                                            data-url="{{route('admin.gateway.sms.api.delete', ['id' => $sms_gateway->id ])}}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteSmsGateway">
                                            <i class="ri-delete-bin-line"></i>
                                            <span class="tooltiptext"> {{ translate("Delete SMS Gateway") }} </span>
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
            @include('admin.partials.pagination', ['paginator' => $gateways])
        </div>
    </div>
</div>
@endsection

@section('modal')
    <div class="modal fade actionModal" id="bulkAction" tabindex="-1" aria-labelledby="bulkAction" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header text-start">
                    <span class="action-icon danger">
                        <i class="bi bi-exclamation-circle"></i>
                    </span>
                </div>
                <form action="{{route('admin.gateway.sms.api.bulk')}}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" name="id" value="">
                        <div class="action-message">
                            <h5>{{ translate("Do you want to proceed?") }}</h5>
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

    <div class="modal fade" id="quick_view" tabindex="-1" aria-labelledby="quick_view" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{ translate("SMS Gateway Information") }}</h5>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <ul class="information-list"></ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="i-btn btn--danger outline btn--md" data-bs-dismiss="modal">{{ translate("Close") }}</button>
                    <button type="button" class="i-btn btn--primary btn--md">{{ translate("Save") }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade actionModal" id="deleteSmsGateway" tabindex="-1" aria-labelledby="deleteSmsGateway" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header text-start">
                    <span class="action-icon danger">
                        <i class="bi bi-exclamation-circle"></i>
                    </span>
                </div>
                <form method="POST" id="deleteSmsGateway">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" name="_method" value="DELETE">
                        <div class="action-message">
                            <h5>{{ translate("Are you sure to delete this sms_gateway?") }}</h5>
                            <p>{{ translate("By clicking on 'Delete', you will permanently remove the sms_gateway from the application") }}</p>
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

    <div class="modal fade modal-select2" id="addSmsGateway" tabindex="-1" aria-labelledby="addSmsGateway" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <form id="add-sms-gateway-form" action="{{route('admin.gateway.sms.api.store')}}" method="POST">
                    @csrf
                    <input type="hidden" name="gateway_mode" id="add_gateway_mode" value="built-in">
                    <input type="hidden" name="is_custom_api" id="add_is_custom_api" value="0">
                    <input type="hidden" name="meta_data" id="add_meta_data_configuration">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">{{ translate("Add SMS Gateway") }}</h5>
                        <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                            <i class="ri-close-large-line"></i>
                        </button>
                    </div>
                    <div class="modal-body modal-lg-custom-height">
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="form-inner">
                                    <label for="add_name" class="form-label">{{ translate("Gateway Name") }}</label>
                                    <input type="text" id="add_name" name="name" placeholder="{{ translate("Enter Gateway Name") }}" class="form-control" aria-label="name"/>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-inner">
                                    <label for="add_per_message_min_delay" class="form-label">{{ translate('Per Message Minimum Delay (Seconds)') }}</label>
                                    <input type="number" min="0" step="0.1" id="add_per_message_min_delay" name="per_message_min_delay" placeholder="{{ translate('e.g., 0.5 seconds minimum delay per message') }}" class="form-control" aria-label="Per Message Minimum Delay" value="{{ old('per_message_min_delay', $gateway->per_message_min_delay ?? 0) }}" />
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-inner">
                                    <label for="add_per_message_max_delay" class="form-label">{{ translate('Per Message Maximum Delay (Seconds)') }}</label>
                                    <input type="number" min="0" step="0.1" id="add_per_message_max_delay" name="per_message_max_delay" placeholder="{{ translate('e.g., 0.5 seconds maximum delay per message') }}" class="form-control" aria-label="Per Message Maximum Delay" value="{{ old('per_message_max_delay', $gateway->per_message_max_delay ?? 0) }}" />
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-inner">
                                    <label for="add_delay_after_count" class="form-label">{{ translate('Delay After Count') }}</label>
                                    <input type="number" min="0" step="1" id="add_delay_after_count" name="delay_after_count" placeholder="{{ translate('e.g., pause after 50 messages') }}" class="form-control" aria-label="Delay After Count" value="{{ old('delay_after_count', $gateway->delay_after_count ?? 0) }}" />
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-inner">
                                    <label for="add_delay_after_duration" class="form-label">{{ translate('Delay After Duration (Seconds)') }}</label>
                                    <input type="number" min="0" step="0.1" id="add_delay_after_duration" name="delay_after_duration" placeholder="{{ translate('e.g., pause for 5 seconds') }}" class="form-control" aria-label="Delay After Duration" value="{{ old('delay_after_duration', $gateway->delay_after_duration ?? 0) }}" />
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-inner">
                                    <label for="add_reset_after_count" class="form-label">{{ translate('Reset After Count') }}</label>
                                    <input type="number" min="0" step="1" id="add_reset_after_count" name="reset_after_count" placeholder="{{ translate('e.g., reset after 200 messages') }}" class="form-control" aria-label="Reset After Count" value="{{ old('reset_after_count', $gateway->reset_after_count ?? 0) }}" />
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="gw-tabs-container">
                                    <div class="gw-tabs">
                                        <button type="button" class="gw-tab" id="add-built-in-tab" data-tab="add-built-in">{{ translate("Built-in API") }}</button>
                                        <button type="button" class="gw-tab active" id="add-custom-tab" data-tab="add-custom">{{ translate("Custom API") }}</button>
                                    </div>
                                    <div class="gw-tab-content">
                                        <div class="gw-tab-pane" id="add-built-in">
                                            <div class="row g-4 mt-1">
                                                <div class="col-lg-12">
                                                    <div class="form-inner">
                                                        <label for="add_gateway_type" class="form-label">{{ translate("Gateway Type") }}</label>
                                                        <select data-placeholder="Select a gateway type" class="form-select gateway_type" data-show="5" id="add_gateway_type" name="type">
                                                            <option value="" disabled selected>{{ translate("Select a Gateway") }}</option>
                                                            @foreach($credentials as $name => $credential)
                                                                <option value="{{$name}}">{{textFormat(['_'], $name, ' ')}}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="row newdataadd"></div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="info-note">
                                                        <i class="ri-information-line"></i>
                                                        <span>{{ translate("Hitting save while this tab on will save Built-in gateway data") }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="gw-tab-pane active" id="add-custom">
                                            <div id="add-custom-api-content"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="i-btn btn--danger outline btn--md" data-bs-dismiss="modal">
                            {{ translate("Close") }}
                        </button>
                        <button type="submit" class="i-btn btn--primary btn--md">
                            {{ translate("Save") }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade modal-select2" id="updateSmsGateway" tabindex="-1" aria-labelledby="updateSmsGateway" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <form id="update-sms-gateway-form" action="" method="POST">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="gateway_mode" id="update_gateway_mode" value="built-in">
                    <input type="hidden" name="is_custom_api" id="update_is_custom_api" value="0">
                    <input type="hidden" name="meta_data" id="update_meta_data_configuration">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">{{ translate("Update SMS Gateway") }}</h5>
                        <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                            <i class="ri-close-large-line"></i>
                        </button>
                    </div>
                    <div class="modal-body modal-lg-custom-height">
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="form-inner">
                                    <label for="update_name" class="form-label">{{ translate("Gateway Name") }}</label>
                                    <input type="text" id="update_name" name="name" placeholder="{{ translate("Enter Gateway Name") }}" class="form-control" aria-label="name"/>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-inner">
                                    <label for="update_per_message_min_delay" class="form-label">{{ translate('Per Message Minimum Delay (Seconds)') }}</label>
                                    <input type="number" min="0" step="0.1" id="update_per_message_min_delay" name="per_message_min_delay" placeholder="{{ translate('e.g., 0.5 seconds minimum delay per message') }}" class="form-control" aria-label="Per Message Minimum Delay"/>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-inner">
                                    <label for="update_per_message_max_delay" class="form-label">{{ translate('Per Message Maximum Delay (Seconds)') }}</label>
                                    <input type="number" min="0" step="0.1" id="update_per_message_max_delay" name="per_message_max_delay" placeholder="{{ translate('e.g., 0.5 seconds maximum delay per message') }}" class="form-control" aria-label="Per Message Maximum Delay"/>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-inner">
                                    <label for="update_delay_after_count" class="form-label">{{ translate('Delay After Count') }}</label>
                                    <input type="number" min="0" step="1" id="update_delay_after_count" name="delay_after_count" placeholder="{{ translate('e.g., pause after 50 messages') }}" class="form-control" aria-label="Delay After Count"/>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-inner">
                                    <label for="update_delay_after_duration" class="form-label">{{ translate('Delay After Duration (Seconds)') }}</label>
                                    <input type="number" min="0" step="0.1" id="update_delay_after_duration" name="delay_after_duration" placeholder="{{ translate('e.g., pause for 5 seconds') }}" class="form-control" aria-label="Delay After Duration"/>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-inner">
                                    <label for="update_reset_after_count" class="form-label">{{ translate('Reset After Count') }}</label>
                                    <input type="number" min="0" step="1" id="update_reset_after_count" name="reset_after_count" placeholder="{{ translate('e.g., reset after 200 messages') }}" class="form-control" aria-label="Reset After Count"/>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="gw-tabs-container">
                                    <div class="gw-tabs">
                                        <button type="button" class="gw-tab" id="update-built-in-tab" data-tab="update-built-in">{{ translate("Built-in API") }}</button>
                                        <button type="button" class="gw-tab" id="update-custom-tab" data-tab="update-custom">{{ translate("Custom API") }}</button>
                                    </div>
                                    <div class="gw-tab-content">
                                        <div class="gw-tab-pane" id="update-built-in">
                                            <div class="row g-4 mt-1">
                                                <div class="col-lg-12">
                                                    <div class="form-inner">
                                                        <label for="update_gateway_type" class="form-label">{{ translate("Gateway Type") }}</label>
                                                        <select data-placeholder="Select a gateway type" class="form-select gateway_type" data-show="5" id="update_gateway_type" name="type">
                                                            <option value="" disabled>{{ translate("Select a Gateway") }}</option>
                                                            @foreach($credentials as $name => $credential)
                                                                <option value="{{$name}}">{{textFormat(['_'], $name, ' ')}}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="row oldData"></div>
                                                </div>
                                                <div class="col-12 newdataadd"></div>
                                                <div class="col-12">
                                                    <div class="info-note">
                                                        <i class="ri-information-line"></i>
                                                        <span>{{ translate("Hitting save while this tab on will save Built-in gateway data") }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="gw-tab-pane" id="update-custom">
                                            <div id="update-custom-api-content"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="i-btn btn--danger outline btn--md" data-bs-dismiss="modal">
                                {{ translate("Close") }}
                            </button>
                            <button type="submit" class="i-btn btn--primary btn--md">
                                {{ translate("Update") }}
                            </button>
                        </div>
                </form>
            </div>
        </div>
    </div>

    
@endsection

@push('script-push')

<script>
    window.translations = <?php echo $customApiTranslationsJson; ?>;
    window.credentials  = <?php echo $jsonArray; ?>;
</script>
@include("partials.gateway.sms.script")
@endpush
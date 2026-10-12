@push("style-include")
  <link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
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
        <form action="{{route(Route::currentRouteName(), ['uid' => $uid])}}" class="filter-form">
            
            <div class="row g-3">
                <div class="col-lg-3">
                    <div class="filter-search">
                        <input type="search" value="{{request()->search}}" name="search" class="form-control" id="filter-search" placeholder="{{ translate("Search Withdraw Methods") }}" />
                        <span><i class="ri-search-line"></i></span>
                    </div>
                </div>

                <div class="col-xxl-8 col-lg-9 offset-xxl-1">
                    <div class="filter-action">
                        <select data-placeholder="{{translate('Select A Status')}}" class="form-select select2-search" name="status" aria-label="Default select example">
                            <option value=""></option>
                            <option {{ request()->status == \App\Enums\Common\Status::ACTIVE->value ? 'selected' : ''  }} value="{{ \App\Enums\Common\Status::ACTIVE->value }}">{{ translate("Active") }}</option>
                            <option {{ request()->status == \App\Enums\Common\Status::INACTIVE->value ? 'selected' : ''  }} value="{{ \App\Enums\Common\Status::INACTIVE->value }}">{{ translate("Inactive") }}</option>
                        </select>
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
                            <a class="filter-action-btn bg-danger text-white" href="{{route(Route::currentRouteName(), ['uid' => $uid])}}">
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
                <h4 class="card-title">{{ $title }}</h4>
            </div>
            <div class="card-header-right">
                <a href="{{route('admin.payment.withdraw.create')}}" class="i-btn btn--primary btn--sm">
                    <i class="ri-add-fill fs-16"></i> {{ translate("Add Withdraw Method") }}
                </a>
            </div>
        </div>

        <div class="card-body px-0 pt-0">
          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th scope="col">{{ translate("SL No.") }}</th>
                  <th scope="col">{{ translate("Name") }}</th>
                  <th scope="col">{{ translate("Currency Code") }}</th>
                  <th scope="col">{{ translate("Amount") }}</th>
                  <th scope="col">{{ translate("Charge") }}</th>
                  <th scope="col">{{ translate("Status") }}</th>
                  <th scope="col">{{ translate("Option") }}</th>
                </tr>
              </thead>
              <tbody>
                @forelse($logs as $log)
                
                    <tr>
                        <td data-label="{{ translate('SL No.') }}">{{$loop->iteration}} </td>
                        <td>
                            <div class="d-flex align-items-center gap-2 ">
                                <span class="user-logo flex-shrink-0">
                                    <img src="{{showImage(filePath()['withdraw_method']['path'].'/'.$log->image, filePath()['withdraw_method']['size'])}}" alt="{{ $log->username }}">
                                </span>
                                <div class="lh-1">
                                    <p class="text-dark fs-14 fw-semibold mb-1">{{ $log->name }}</p>
                                    <p class="text-dark fs-14 mb-1">
                                        {{ translate("Duration: ") }} {{ translate("Within ") }} {{ \Illuminate\Support\Arr::get($log->duration, "value", "--") }} {{ \Illuminate\Support\Arr::get($log->duration, "unit", "--") }}
                                    </p>
                                    <p class="text-primary fs-12" >{{ translate("Updated At: "). $log->updated_at->toDayDateTimeString() }}</a>
                                </div>
                            </div>
                        </td>
                        <td data-label="{{ translate('Currency Code') }}">{{ $log->currency_code ?? translate("N\A") }}</td>
                        <td data-label="{{ translate('Amount') }}">
                            <div class="d-flex flex-column gap-1 align-items-start">
                                <span>{{ translate("Minimum Amount: ") }}{{ $log->minimum_amount ?? translate("N/A") }}{{ getCurrencySymbol($log->currency_code) }}</span>
                                <span>{{ translate("Maximum Amount: ") }}{{ $log->maximum_amount ?? translate("N/A") }}{{ getCurrencySymbol($log->currency_code) }}</span>
                            </div>
                        </td>
                        <td data-label="{{ translate('Charge') }}">
                            <div class="d-flex flex-column gap-1 align-items-start">
                                <span>{{ translate("Fixed Charge: ") }}{{ $log->fixed_charge ?? translate("N/A") }}{{ getCurrencySymbol($log->currency_code) }}</span>
                                <span>{{ translate("Percentage Charge: ") }}{{ $log->percent_charge ?? translate("N/A") }}%</span>
                            </div>
                        </td>
                        <td data-label="{{ translate('Status')}}">
                            <div class="switch-wrapper checkbox-data">
                                 <input {{ $log->status == \App\Enums\Common\Status::ACTIVE->value ? 'checked' : '' }}
                                        type="checkbox"
                                        class="switch-input statusUpdateByUID"
                                        data-uid="{{ $log->uid }}"
                                        data-column="status"
                                        data-value="{{ 
                                            $log->status == 1 || @$log?->status == \App\Enums\Common\Status::ACTIVE->value
                                            ? \App\Enums\Common\Status::INACTIVE->value
                                            : \App\Enums\Common\Status::ACTIVE->value}}"
                                        data-route="{{route('admin.payment.withdraw.status.update')}}"
                                        id="{{ 'status_'.$log->uid }}"
                                        name="status"/>
                                <label for="{{ 'status_'.$log->uid }}" class="toggle">
                                    <span></span>
                                </label>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                @php
                                    $data = [
                                        "name" => $log->name ?? translate("N/A"),
                                        "currency_code" => $log->currency_code ?? translate("N/A"),
                                        "minimum_amount" => isset($log->minimum_amount) ? $log->minimum_amount . getCurrencySymbol($log->currency_code ?? '') : translate("N/A"),
                                        "maximum_amount" => isset($log->maximum_amount) ? $log->maximum_amount . getCurrencySymbol($log->currency_code ?? '') : translate("N/A"),
                                        "fixed_charge" => isset($log->fixed_charge) ? $log->fixed_charge . getCurrencySymbol($log->currency_code ?? '') : translate("N/A"),
                                        "percent_charge" => isset($log->percent_charge) ? $log->percent_charge . "%" : translate("N/A"),
                                        "note" => $log->note ?? translate("N/A"),
                                        "status" => $log->status ?? translate("N/A"),
                                    ];

                                @endphp
                                <button class="icon-btn btn-ghost btn-sm info-soft circle text-info quick-view"
                                        type="button"
                                        data-withdraw_method_information="{{json_encode($data)}}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#quick_view">
                                        <i class="ri-information-line"></i>
                                    <span class="tooltiptext"> {{ translate("Quick View") }} </span>
                                </button>
                                <a  class = "icon-btn btn-ghost btn-sm success-soft circle"
                                    type  = "button"
                                    href  = "{{route('admin.payment.withdraw.edit', [$log->uid])}}">
                                    <i class="ri-edit-line"></i>
                                    <span class="tooltiptext"> {{ translate("Edit Payment Withdraw Method") }} </span>
                                </a>
                                 <button class="icon-btn btn-ghost btn-sm danger-soft circle text-danger delete-withdraw-method"
                                        type="button"
                                        data-url        = "{{route('admin.payment.withdraw.destroy', ['withdraw' => $log->uid])}}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteContactGroup">
                                    <i class="ri-delete-bin-line"></i>
                                    <span class="tooltiptext"> {{ translate("Delete Withdraw Method") }} </span>
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
          @include('admin.partials.pagination', ['paginator' => $logs])
        </div>
      </div>
    </div>
</main>

@endsection
@section('modal')
<div class="modal fade" id="quick_view" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered ">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">{{ translate("Withdraw Method Information") }}</h5>
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

@php
    $deleteModalData = [
        "message"   => translate("Are you sure to delete this withdraw method?"),
    ]
@endphp

@include('v321.common.delete', $deleteModalData)

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

            $(document).ready(function() {
                $('.delete-withdraw-method').on('click', function(){
                    var modal = $('#deleteContactGroup');
                    modal.find('form[id=singleDeleteModal]').attr('action', $(this).data('url'));
                });
            });
           

            $('.quick-view').on('click', function() {
                const modal = $('#quick_view');
                const modalBody = modal.find('.modal-body .information-list');
                modalBody.empty();

                var driver = $(this).data('withdraw_method_information');

                $.each(driver, function(key, value) {

                    const listItem = $('<li>');
                    const paramKeySpan = $('<span>').text(textFormat(['_'], key, ' '));
                    const arrowIcon = $('<i>').addClass('bi bi-arrow-right');
                    var paramValueSpan = '';
                    if(jQuery.type(value) === "object") {

                        paramValueSpan = $('<span>').addClass('text-break text-muted').text((value.value === "true" ? "Yes" : (value.value === "false" ? "No" : value.value)));

                    } else {

                        paramValueSpan = $('<span>').addClass('text-break text-muted').text(value);
                    }


                    listItem.append(paramKeySpan).append(arrowIcon).append(paramValueSpan);
                    modalBody.append(listItem);
                });

                modal.modal('show');
            });
        })(jQuery);


    </script>
@endpush


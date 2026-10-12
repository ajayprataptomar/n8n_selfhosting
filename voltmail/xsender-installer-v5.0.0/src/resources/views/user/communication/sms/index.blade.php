@push("style-include")
  <link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
@endpush
@extends('user.layouts.app')
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
                                    <a href="{{ route("user.dashboard") }}">{{ translate("Dashboard") }}</a>
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
                        <div class="col-xxl-3 col-lg-3">
                            <div class="filter-search">
                                <input type="search" value="{{request()->search}}" name="search" class="form-control" id="filter-search" placeholder="{{ translate("Filter by receiver's number") }}" />
                                <span><i class="ri-search-line"></i></span>
                            </div>
                        </div>
                        <div class="col-xxl-8 col-lg-9 offset-xxl-1">
                            <div class="filter-action">
                                <select data-placeholder="{{translate('Select A Delivery Status')}}" class="form-select select2-search" name="status" aria-label="{{translate('Select A Delivery Status')}}">
                                    <option value=""></option>
                                    @foreach(\App\Enums\System\CommunicationStatusEnum::getValues() as $value)
                                        <option {{ (request()->query("status") && request()->query("status") == $value) || (request()->status && request()->status == $value) ? "selected" : ""}} value="{{ $value }}">{{ ucfirst($value) }}</option>
                                    @endforeach
                                </select>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="datePicker" name="date" value="{{request()->input('date')}}"  placeholder="{{translate('Filter by date')}}"  aria-describedby="filterByDate">
                                    <span class="input-group-text" id="filterByDate">
                                        <i class="ri-calendar-2-line"></i>
                                    </span>
                                </div>

                                <div class="d-flex align-items-center gap-3">
                                    <button type="submit" class="filter-action-btn ">
                                        <i class="ri-menu-search-line"></i> {{ translate("Search") }}
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
                    <h4 class="card-title">{{ translate("Communication Logs") }}</h4>
                </div>
            </div>
            <div class="card-body px-0 pt-0">
                <div class="table-container">
                    <table>
                        <thead>
                                <tr>
                                    <th scope="col">
                                        {{ translate("SL No.") }} 
                                    </th>
                                    <th scope="col">{{ translate("Sender") }}</th>
                                    <th scope="col">{{ translate("To") }}</th>
                                    <th scope="col">{{ translate("Date & Time") }}</th>
                                    <th scope="col">{{ translate("Status") }}</th>
                                    <th scope="col">{{ translate("Options") }}</th>
                                </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                <tr>
                                    <td data-label="{{ translate('Index') }}">
                                        {{$loop->iteration}}
                                    </td>
                                    
                                    <td data-label="{{ translate('To') }}">
                                        <p> 
                                            @if($log?->gatewayable?->user_id == null) 

                                                {{ translate("Admin Gateway") }}
                                            @else
                                                {{ @$log?->gatewayable && $log->gatewayable instanceof \App\Models\Gateway 
                                                        ? $log->gatewayable->type
                                                        : (@$log?->gatewayable?->androidSession 
                                                            ? $log->gatewayable->androidSession->name
                                                            : translate("N/A"))}}
                                                            
                                                <span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ @$log?->gatewayable && $log->gatewayable instanceof \App\Models\Gateway 
                                                        ? $log->gatewayable->name
                                                        : (@$log?->gatewayable?->sim_number 
                                                            ? $log->gatewayable->sim_number 
                                                            : translate("N/A")) }}">
                                                    <i class="ri-error-warning-line"></i>
                                                </span>
                                            @endif
                                        </p>
                                    </td>
                                    <td>
                                        @if($log->campaign_id)
                                            <span class="i-badge pill primary-soft me-2" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ translate("Campaign Message") }}">
                                                <i class="ri-megaphone-line"></i>
                                            </span>
                                        @endif
                                        {{ @$log?->contact ? $log->contact->sms_contact : translate("N/A") }} 
                                       
                                    </td>
                                    <td data-label="{{ translate('Date & Time') }}">
                                        <div class="d-flex flex-column gap-1 align-items-start">
                                            <span>{{ translate("Initiated At: ") }}{{ $log->created_at ?? translate("N/A") }}</span>
                                            <span>{{ translate("Scheduled For: ") }}{{ $log->scheduled_at ?? translate("N/A") }}</span>
                                            <span>{{ translate("Sent At: ") }}{{ $log->sent_at ?? translate("N/A") }}</span>
                                            <span>{{ translate("Processed At: ") }}{{ $log->processed_at ?? translate("N/A") }}</span>
                                        </div>
                                    </td>
                                    <td data-label="{{ translate('Status') }}">
                                    <div class="d-flex align-items-center gap-2">
                                        {{ $log->status->badge() }}
                                        @if(\App\Enums\System\CommunicationStatusEnum::FAIL->value == $log->status->value)
                                        
                                            <button data-response-message="{{ $log->response_message }}" class="text-success bg-transparent fs-5 fail-reason">
                                                <i class="ri-file-info-line"></i>
                                            </button>
                                        @endif
                                    </div>
                                    </td>
                                    <td data-label="{{ translate('Options') }}">
                                    <div class="d-flex align-items-center gap-1">
                                        <button 
                                            data-message = "{{ @$log?->message?->message 
                                                                    ?  replaceContactVariables($log->contact, $log->message->message) 
                                                                    : translate("N/A") }}"
                                            class="icon-btn btn-ghost btn-sm info-soft circle view-log">
                                            <i class="ri-eye-line"></i>
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
                @include('user.partials.pagination', ['paginator' => $logs])
            </div>
        </div>
        </div>
    </main>

@endsection
@section('modal')
    <div class="modal fade" id="failReason" tabindex="-1" aria-labelledby="failReason" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered ">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"> {{ translate("SMS Failed") }} </h5>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                        <i class="ri-close-large-line"></i>
                    </button>
                </div>
                <div class="modal-body modal-md-custom-height">
                    <div class="row g-4">
                        <div class="col-md-12">
                            <p class="text-danger text-center response-message text-break"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="viewLog" tabindex="-1" aria-labelledby="viewLog" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered ">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"> {{ translate("SMS Message") }} </h5>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                        <i class="ri-close-large-line"></i>
                    </button>
                </div>
                <div class="modal-body modal-md-custom-height">
                    <div class="row g-4">
                        <div class="col-lg-12">
                            <ul class="information-list">
                                <li>
                                    <span>{{ translate("SMS Message: ") }}</span>
                                    <i class="bi bi-arrow-right"></i>
                                    <span class="text-break text-muted log-message"></span>
                                </li>
                            </ul>
                        </div>
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
	"use strict";
        select2_search($('.select2-search').data('placeholder'));
		flatpickr("#datePicker", {
            dateFormat: "Y-m-d",
            mode: "range",
        });

        $(document).ready(function() {
            $(document).on('click', '.fail-reason', function() {
    
                const modal = $('#failReason');
                modal.find('.response-message').text($(this).data('response-message'));
                modal.modal('show');
            });
            $(document).on('click', '.view-log', function() {
                const modal = $('#viewLog');
                modal.find('.log-message').text($(this).data('message'));
                modal.modal('show');
            });
        });
</script>
@endpush

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
            <div class="card-header-right">
                <div class="d-flex gap-3 align-item-center">
                    <button class="bulk-action i-btn btn--danger btn--sm bulk-delete-btn d-none">
                        <i class="ri-delete-bin-6-line"></i>
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
                                <th scope="col">{{ translate("User") }}</th>
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
                                <td>
                                    <div class="form-check">
                                        <input type="checkbox" value="{{$log->id}}" name="ids[]" class="data-checkbox form-check-input" id="{{$log->id}}" />
                                        <label class="form-check-label fw-semibold text-dark" for="bulk-{{$loop->iteration}}">{{$loop->iteration}}</label>
                                    </div>
                                </td>
                                <td>
                                    @if($log->user_id)
                                        <a href="{{route('admin.user.details', $log->user_id)}}" class="fw-bold text-dark">{{$log->user?->name}}</a>
                                    @else
                                        <span>{{ translate('Admin')}}</span>
                                    @endif
                                </td>
                                <td>
                                    <p> 
                                        @if($log->gatewayable)
                                            {{ ucfirst($log->gatewayable->type) }}
                                            <span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ $log->gatewayable->name }}">
                                                <i class="ri-error-warning-line"></i>
                                            </span>
                                        @else
                                            {{ translate('N\A') }}
                                        @endif
                                    </p>
                                </td>
                                <td>
                                    @if($log?->campaign)
                                        <span 
                                            class="i-badge pill primary-soft me-2" 
                                            data-bs-toggle="tooltip" 
                                            data-bs-placement="top" 
                                            data-bs-title="{{ translate("Whatsapp Campaign: ").$log?->campaign?->name }}">
                                            <i class="ri-megaphone-line"></i>
                                        </span>
                                    @endif
                                    @if(@$log->contact)
                                        {{ $log->contact->whatsapp_contact }}
                                        
                                        {{-- {{ array_key_exists('contact', $log->meta_data) ? $log->meta_data['contact'] : translate("N\A") }}  --}}
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1 align-items-start ">
                                        <span>{{ translate("Initiated At: ") }}{{ $log->created_at ?? 'N\A' }}</span>
                                        <span>{{ translate("Scheduled For: ") }}{{ $log->scheduled_at ?? 'N\A' }}</span>
                                        <span>{{ translate("Sent At: ") }}{{ $log->sent_at ?? 'N\A' }}</span>
                                        <span>{{ translate("Processeed At: ") }}{{ $log->processed_at ?? 'N\A' }}</span>
                                        <span>{{ translate("Updated At: ") }}{{ $log->updated_at ?? 'N\A' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        {{ $log->status->badge() }}
                                        @if(\App\Enums\System\CommunicationStatusEnum::FAIL->value == $log->status->value)
                                            <button data-response-message="{{ $log->response_message }}" class="text-success bg-transparent fs-5 fail-reason">
                                                <i class="ri-file-info-line"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                <div class="d-flex align-items-center gap-1">
                                    @if($log->contact_id)
                                    <a href="{{ route('admin.communication.whatsapp.chats.start', [
                                        'contact_id' => $log->contact_id,
                                        'gateway_id' => $log->gatewayable_id,
                                        'gateway_type' => $log->gatewayable?->type ?? 'node'
                                    ]) }}"
                                       class="icon-btn btn-ghost btn-sm success-soft circle"
                                       data-bs-toggle="tooltip"
                                       data-bs-placement="top"
                                       data-bs-title="{{ translate('Open Chat') }}">
                                        <i class="ri-chat-3-line"></i>
                                    </a>
                                    @endif
                                    <button
                                    data-log-id="{{ $log->id }}"
                                    data-log-status="{{ $log->status->value }}"
                                    data-response_message="{{ $log->response_message }}"
                                    data-log-status-message="{{ translate("Status: ").$log->status->value }}"
                                    data-message="{{ @$log?->message?->message
                                                        ? replaceContactVariables($log->contact, $log->message->message)
                                                        : translate("N/A") }}"
                                    data-updated-at="{{ Carbon\Carbon::parse($log->updated_at)->toDayDateTimeString() }}" class="icon-btn btn-ghost btn-sm info-soft circle update-log">
                                        <i class="ri-eye-line"></i>
                                    </button>
                                    <button data-url = "{{route('admin.communication.whatsapp.delete',['id' => $log->id])}}"
                                        class="icon-btn btn-ghost btn-sm danger-soft circle text-danger delete-whatsapp-log" type="button" data-bs-toggle="modal" data-bs-target="#deleteWhatsappLog">
                                        <i class="ri-delete-bin-line"></i>
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
<div class="modal fade actionModal" id="bulkAction" tabindex="-1" aria-labelledby="bulkAction" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
        <div class="modal-header text-start">
            <span class="action-icon danger">
            <i class="bi bi-exclamation-circle"></i>
            </span>
        </div>
        <form action="{{route('admin.communication.whatsapp.bulk', ['type' => \App\Enums\ServiceType::WHATSAPP->value])}}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-body">

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
<div class="modal fade" id="failReason" tabindex="-1" aria-labelledby="failReason" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered ">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Whatsapp Failed") }} </h5>
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
<div class="modal fade" id="updateLog" tabindex="-1" aria-labelledby="updateLog" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered ">
        <div class="modal-content">
            <form action="{{route('admin.communication.whatsapp.status.update')}}" method="POST" enctype="multipart/form-data">
				@csrf
                <input type="text" hidden name="id">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Update WhatsApp Log Status") }} </h5>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                        <i class="ri-close-large-line"></i>
                    </button>
                </div>
                <div class="modal-body modal-md-custom-height ">
                    <div class="row g-4">
                        <div class="col-lg-12">
                            <ul class="information-list">
                                <li>
                                    <span>{{ translate("Whatsapp Dispatched At: ") }}</span>
                                    <i class="bi bi-arrow-right"></i>
                                    <span class="text-break text-muted log-updated-at"></span>
                                </li>
                                <li>
                                    <span>{{ translate("Whatsapp Message: ") }}</span>
                                    <i class="bi bi-arrow-right"></i>
                                    <span class="text-break text-muted log-message"></span>
                                </li>
                            </ul>
                        </div>
                        <div class="col-lg-12">
                            <div class="form-inner">
                                <label for="name" class="form-label"> {{ translate('Update Status')}} </label>
                                <select data-placeholder="{{translate('Select A Delivery Status')}}" class="form-select select2-search" name="status" id="status" aria-label="{{translate('Select A Delivery Status')}}">
                                    <option value=""></option>
                                    @foreach(\App\Enums\System\CommunicationStatusEnum::getValues() as $value)
                                        @if(!in_array($value, [\App\Enums\System\CommunicationStatusEnum::SCHEDULE->value, \App\Enums\System\CommunicationStatusEnum::PENDING->value]))
                                            <option value="{{ $value }}">{{ ucfirst(strtolower($value)) }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-12" id="fail-reason-container" style="display: none;">
                            <div class="form-inner">
                                <label for="fail_reason" class="form-label">{{ translate('Reason for Failure') }}</label>
                                <textarea class="form-control" name="response_message" id="fail_reason" rows="4" placeholder="{{ translate('Enter reason for failure') }}" aria-label="{{ translate('Reason for failure') }}"></textarea>
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
<div class="modal fade actionModal" id="deleteWhatsappLog" tabindex="-1" aria-labelledby="deleteWhatsappLog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
        <div class="modal-header text-start">
            <span class="action-icon danger">
            <i class="bi bi-exclamation-circle"></i>
            </span>
        </div>
        <form  id="dispatchLogDelete" method="POST">
            @csrf
            <div class="modal-body">

                <input type="hidden" name="_method" value="DELETE">
                <div class="action-message">
                    <h5>{{ translate("Are you sure to delete this Whatsapp log") }}</h5>
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

        document.addEventListener('DOMContentLoaded', function () {

            const selectElement = document.getElementById('bulk_status');
            const bulkActionDiv = document.getElementById('bulkActionDiv');

            $('.bulk-delete-btn').on('click', function () {
                bulkActionDiv.classList.add('d-none');
            });
        });

        $('.fail-reason').on('click', function() {

            const modal = $('#failReason');
            modal.find('.response-message').text($(this).data('response-message'));
            modal.modal('show');
        });
        $('.delete-whatsapp-log').on('click', function() {

            const modal = $('#deleteWhatsappLog');
            var form = modal.find('form[id=dispatchLogDelete]');

            var actionUrl = $(this).data('url');
            if (actionUrl) {
                form.attr('action', actionUrl);
            } else {
                form.removeAttr('action'); 
            }
            modal.modal('show');
        });
        $('.update-log').on('click', function() {

            const modal = $('#updateLog');
            modal.find('.log-message').text($(this).data('message'));
            modal.find('.log-updated-at').text($(this).data('updated-at'));
            modal.find('input[name=id]').val($(this).data('log-id'));
            modal.find('textarea[name=response_message]').val($(this).data('response_message'));
            modal.find('select[name=status]').val($(this).data('log-status')).trigger('change');
            modal.find('.log-status').text($(this).data('log-status-message'));
            modal.modal('show');
        });
</script>
<script>
    $(document).ready(function () {
        $('#status').on('change', function () {
            var selectedStatus = $(this).val();
            if (selectedStatus === 'fail') {
                $('#fail-reason-container').show();
            } else {
                $('#fail-reason-container').hide();
            }
        });
        if ($('#status').val() !== 'fail') {
            $('#fail-reason-container').hide();
        }
    });
</script>
@endpush

@push("style-include")
  <link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
@endpush
@extends('admin.layouts.app')
@section('panel')
    <main class="main-body">
        <div class="container-fluid px-0 main-content">
            <div class="page-header">
                <div class="page-header-left">
                    <h2>{{ translate("$title") }}</h2>
                    <div class="breadcrumb-wrapper">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="{{ route('admin.dashboard') }}">{{ translate("Dashboard") }}</a>
                                </li>
                                <li class="breadcrumb-item active" aria-current="page"> {{ translate("$title") }} </li>
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
                                <input type="search" value="{{request()->search}}" name="search" class="form-control" id="filter-search" placeholder="{{ translate("Filter by receiver's email") }}" />
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
                                    <th scope="col">{{ translate("Date") }}</th>
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
                                        @if(@$log?->user)
                                            <a href="{{route('admin.user.details', $log->user->id)}}" class="fw-bold text-dark">{{$log->user->name}}</a>
                                        @else
                                            <span>{{ translate('Admin')}}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <p> 
                                            @if($log->gatewayable)
                                                {{ $log->gatewayable->name }}
                                                <span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ ucfirst($log->gatewayable->type) }}">
                                                    <i class="ri-error-warning-line"></i>
                                                </span>
                                            @else
                                                {{ translate('N\A') }}
                                            @endif
                                        </p>
                                    </td>
                                    <td>
                                        <div>
                                            @if($log?->campaign)
                                                <span
                                                    class="i-badge pill primary-soft me-2"
                                                    data-bs-toggle="tooltip"
                                                    data-bs-placement="top"
                                                    data-bs-title="{{ translate("Email Campaign: ").$log?->campaign?->name }}">
                                                    <i class="ri-megaphone-line"></i>
                                                </span>
                                            @endif
                                            @if(@$log->contact)
                                                {{ $log->contact->email_contact }}
                                            @endif
                                            @if(@$log->message->subject)
                                                <br><small class="text-muted">{{ plain_text_preview(replaceContactVariables($log->contact, $log->message->subject), 50) }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span data-bs-toggle="tooltip"
                                              data-bs-placement="top"
                                              data-bs-title="{{ translate('Created') }}: {{ $log->created_at ?? 'N/A' }}&#10;{{ translate('Scheduled') }}: {{ $log->scheduled_at ?? 'N/A' }}&#10;{{ translate('Processed') }}: {{ $log->processed_at ?? 'N/A' }}&#10;{{ translate('Updated') }}: {{ $log->updated_at ?? 'N/A' }}">
                                            {{ $log->sent_at ? \Carbon\Carbon::parse($log->sent_at)->diffForHumans() : ($log->created_at ? \Carbon\Carbon::parse($log->created_at)->diffForHumans() : 'N/A') }}
                                        </span>
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
                                        @if(@$log?->message)
                                            <a href="{{ route('admin.communication.email.show', $log->id) }}"
                                               class="icon-btn btn-ghost btn-sm info-soft circle"
                                               data-bs-toggle="tooltip" data-bs-title="{{ translate('View') }}">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            <button data-url="{{ route('admin.communication.email.resend', $log->id) }}"
                                                    class="icon-btn btn-ghost btn-sm success-soft circle resend-email-log" type="button"
                                                    data-bs-toggle="tooltip" data-bs-title="{{ translate('Resend') }}">
                                                <i class="ri-mail-send-line"></i>
                                            </button>
                                        @endif
                                        <button data-url="{{route('admin.communication.email.delete',['id' => $log->id])}}"
                                                class="icon-btn btn-ghost btn-sm danger-soft circle text-danger delete-email-log" type="button"
                                                data-bs-toggle="tooltip" data-bs-title="{{ translate('Delete') }}">
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
        <form action="{{route('admin.communication.email.bulk')}}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-body">
                <div class="action-message">
                    <h5>{{ translate("Are you sure to change the status for the selected data?") }}</h5>
                    <p>{{ translate("This action is irreversable") }}</p>
                </div>
                <div class="row mt-2 pending d-none" id="bulkActionDiv">
                    <div class="col-12 mt-3">
                        <div class="form-inner">
                          <label for="gateway_id" class="form-label">{{ translate("Choose Gateway") }}</label>
                          <select class="form-select select2-search" id="gateway_id" data-placeholder="{{ translate("Select a gateway") }}" data-show="5" aria-label="gateway_id" name="gateway_id">
                            
                            <option value="0">{{ translate("Random Rotation") }}</option>
                            <option value="-1">{{ translate("Automatic") }}</option>
                            @foreach($gateways as $type => $gateway)
                                <optgroup label="{{ ucfirst($type) }}">
                                    @foreach($gateway as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                          </select>
                        </div>
                    </div>
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
                <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Email Failed") }} </h5>
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
<div class="modal fade actionModal" id="deleteEmailLog" tabindex="-1" aria-labelledby="deleteEmailLog" aria-hidden="true">
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
                    <h5>{{ translate("Are you sure to delete this Email log") }}</h5>
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
<div class="modal fade actionModal" id="resendEmailLog" tabindex="-1" aria-labelledby="resendEmailLog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header text-start">
                <span class="action-icon success">
                    <i class="bi bi-send-check"></i>
                </span>
            </div>
            <form id="dispatchLogResend" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="action-message">
                        <h5>{{ translate("Are you sure you want to resend this email?") }}</h5>
                        <p>{{ translate("A new email will be dispatched to the same recipient with the same content.") }}</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="i-btn btn--dark outline btn--lg" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
                    <button type="submit" class="i-btn btn--primary btn--lg" data-bs-dismiss="modal">{{ translate("Resend") }}</button>
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


            selectElement.addEventListener('change', function () {
                if (selectElement.value == "{{ \App\Enums\System\CommunicationStatusEnum::PENDING->value }}") {
                    bulkActionDiv.classList.remove('d-none');
                } else {
                    bulkActionDiv.classList.add('d-none');
                }
            });
            $('.bulk-delete-btn').on('click', function () {
                bulkActionDiv.classList.add('d-none');
            });
        });

        $('.fail-reason').on('click', function() {

            const modal = $('#failReason');
            modal.find('.response-message').text($(this).data('response-message'));
            modal.modal('show');
        });
        $('.delete-email-log').on('click', function() {

            const modal = $('#deleteEmailLog');
            var form = modal.find('form[id=dispatchLogDelete]');
            var actionUrl = $(this).data('url');
            if (actionUrl) {
                form.attr('action', actionUrl);
            } else {
                form.removeAttr('action');
            }
            modal.modal('show');
        });
        $('.resend-email-log').on('click', function() {

            const modal = $('#resendEmailLog');
            var form = modal.find('form[id=dispatchLogResend]');
            var actionUrl = $(this).data('url');
            if (actionUrl) {
                form.attr('action', actionUrl);
            } else {
                form.removeAttr('action');
            }
            modal.modal('show');
        });

</script>
@endpush

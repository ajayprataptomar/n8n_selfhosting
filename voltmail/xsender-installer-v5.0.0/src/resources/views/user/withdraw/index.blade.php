@include('v321.common.css')
@extends('user.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-fluid px-0 main-content">
        @include('v321.common.header', ['title' => $title])
        @include('v321.common.search')
       
        <div class="card">
          <div class="card-header">
               <div class="card-header-left">
                    <h4 class="card-title">{{ $title }}</h4>
               </div>
          </div>
            <div class="card-body px-0 pt-0">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col"> {{ translate("SL No.") }}</th>
                                <th scope="col">{{ translate("TRX Code") }}</th>
                                <th scope="col">{{ translate("Method") }}</th>
                                <th scope="col">{{ translate("Final Amount") }}</th>
                                <th scope="col">{{ translate("Status") }}</th>
                                <th scope="col">{{ translate("Requested At") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                <tr>
                                    <td data-label="{{ translate('SL No.')}}">{{$loop->iteration}}</td>
                                    <td data-label="{{ translate('TRX Code')}}">{{$log->trx_code}}</td>
                                    <td data-label="{{ translate('Method')}}">{{@$log?->method?->name ? $log->method->name : translate("N/A") }}</td>
                                    <td data-label="{{ translate('Final Amount')}}">{{$log->final_amount !== null ? convertCurrency($log->final_amount, "USD", $log->currency_code ) : "--"}} {{ getCurrencySymbol($log->currency_code) }}</td>
                                    <td data-label="{{ translate(keyWord: 'Status')}}">
                                        @if($log->status != \App\Enums\WithdrawLogEnum::PENDING->value)
                                            <i class="ri-information-line text-primary cursor-pointer" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#rejectedNote" 
                                            data-note="{{ $log->notes ?? translate("N/A") }}"></i>
                                        @endif
                                        @php echo withdraw_log_status($log->status) @endphp
                                    </td>
                                   <td data-label="{{ translate('Requested At')}}">{{$log->created_at->toDayDateTimeString()}}</td>
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

<div class="modal fade" id="rejectedNote" tabindex="-1" aria-labelledby="rejectedNote" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Note") }} </h5>
                <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                    <i class="ri-close-large-line"></i>
                </button>
            </div>
            <div class="modal-body modal-md-custom-height">
                <div class="row g-4">
                    <div class="col">
                        <p class="text-center" id="noteContent"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('v321.common.js.select2')

@push('script-push')
<script>
    (function($){
        "use strict";
        select2_search($('.select2-search').data('placeholder'));
        flatpickr("#datePicker", {
            dateFormat: "Y-m-d",
            mode: "range",
        });

        document.addEventListener('DOMContentLoaded', function () {
            const rejectedNoteModal = document.getElementById('rejectedNote');
            rejectedNoteModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const note = button.getAttribute('data-note');
                const noteContent = document.getElementById('noteContent');
                noteContent.textContent = note;
            });
        });

    })(jQuery);
</script>
@endpush
@endsection

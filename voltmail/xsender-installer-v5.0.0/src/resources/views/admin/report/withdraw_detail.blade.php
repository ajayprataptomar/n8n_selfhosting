@extends('admin.layouts.app')
@push("style-include")
  <link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
@endpush 
@section('panel')

	<main class="main-body">
		<div class="container-fluid px-0 main-content">
		<div class="page-header">
			<div class="page-header-left">
			<h2>{{ translate("Withdraw Information") }}</h2>
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

		<div class="row g-4">
			<div class="col-xl-4 col-lg-6">
			<div class="card h-100">
				<div class="form-header">
				<h4 class="card-title">{{ translate('Member information')}}</h4>
				</div>
				<div class="card-body">
				<div class="ul-list">
					<ul>
					<li class="fs-14">
						<span class="text-muted">{{ translate('Member Name')}}</span>
						<span class="fw-medium">{{$log->user?->name}}</span>
					</li>
					<li class="fs-14">
						<span class="text-muted">{{ translate('Payment Method')}}</span>
						<span class="fw-medium">{{$log->method?->name}}</span>
					</li>
					<li class="fs-14">
						<span class="text-muted">{{ translate('Date')}}</span>
						<span class="fw-medium">{{getDateTime($log->created_at)}}</span>
					</li>
					<li class="fs-14">
						<span class="text-muted">{{ translate('Amount')}}</span>
						<span class="fw-semi-bold">{{convertCurrency($log->amount, "USD", $log->currency_code)}} {{$log->currency_code}}</span>
					</li>
					<li class="fs-14">
						<span class="text-muted">{{ translate('Charge')}}</span>
						<span class="fw-medium">{{convertCurrency($log->charge, "USD", $log->currency_code)}} {{$log->currency_code}}</span>
					</li>
					<li class="fs-14">
						<span class="text-muted">{{ translate('Final Amount')}}</span>
						<span class="fw-medium">{{convertCurrency($log->final_amount, "USD", $log->currency_code)}} {{$log->currency_code}}</span>
					</li>
					<li class="fs-14">
						<span class="text-muted"> {{ translate('Status')}}</span>
						@php echo withdraw_log_status($log->status) @endphp
					</li>
					</ul>
				</div>
				</div>
			</div>
			</div>
			@if($log->custom_data != null)
				<div class="col-xl-4 col-lg-6">
				<div class="card h-100">
					<div class="form-header">
					<h4 class="card-title">{{ translate("Member Data") }}</h4>
					</div>
					<div class="card-body">
						@foreach($log->custom_data as $k => $val)
							@if($val['field_type'] != 'file')
								<div class="mb-4">
									<label class="form-label">{{labelName($k)}}</label>
									<div class="bg-light rounded-2 p-2 fs-14 text-muted border">
										<p>{{$val['field_name']}}</p>
									</div>
								</div>
							@endif
						@endforeach
					</div>
				</div>
				</div>
				
				<div class="col-xl-4 col-lg-6">
					<div class="card h-100">
						<div class="form-header">
							<h4 class="card-title">{{ translate("User Files") }}</h4>
						</div>
						<div class="card-body">
							<div class="row g-4">
								@foreach($log->custom_data as $k => $val)
									@if($val['field_type'] == 'file')
									<label class="form-label">{{labelName($k)}}</label>
										<div class="col-lg-4">
											<img src="{{showImage('assets/file/images/user/withdraw_request/'.$val['field_name'])}}" class="mt-1" alt="{{ translate('Image')}}">
									</div>
									@endif
								@endforeach
								@if($log->status == \App\Enums\WithdrawLogEnum::PENDING->value)
								<div class="col-12">
									<div class="form-action">
										<button type="submit" class="i-btn btn--primary btn--md update-status"> {{ translate("Update Status") }} </button>
									</div>
								</div>
								@endif
							</div>
						</div>
					</div>
				</div>
			@endif
		</div>
		</div>
	</main>

@endsection
@section("modal")
<div class="modal fade" id="statusUpdateModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-md modal-dialog-centered ">
		<div class="modal-content">
			<form action="{{route('admin.report.withdraw.status.update', ['trx_code' => $log->trx_code])}}" method="POST">
				@csrf
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel"> {{ translate("Update Status") }} </h5>
					<button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
						<i class="ri-close-large-line"></i>
					</button>
				</div>
				<div class="modal-body modal-md-custom-height ">
					<div class="row g-4">
						<div class="action-message">
							<h6>{{ translate('Are you sure you want to update this application?')}}</h6>
						</div>
						<div class="col-lg-12">
							<div class="form-inner">
								<label for="status" 
									class="form-label">
									{{ translate("Select Status") }}
									<span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ translate("Selected status will be applied to this withdraw request")}}">
										<i class="ri-information-line"></i>
									</span>
									<div data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Suggestions Note">
										<button class="i-btn info--btn btn--sm d-xl-none info-note-btn"><i class="las la-info-circle"></i></button>
									</div>
								</label>
								<select data-placeholder="{{translate('Select a status')}}" class="form-select" name="status" id="status">
									<option selected disabled value="">{{ translate("Select a status") }}</option>
									<option value="{{\App\Enums\WithdrawLogEnum::APPROVED->value}}">{{ translate("Approved") }}</option>
									<option value="{{\App\Enums\WithdrawLogEnum::REJECTED->value}}">{{ translate("Rejected") }}</option>
								</select>
							</div>
							<div class="form-inner mt-4">
								<label for="note" class="form-label">{{translate('note')}}<sup class="text-danger">*</sup></label>
								<textarea required class="form-control" name="notes" id="notes" rows="2"></textarea>
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

@endsection
@push("script-include")
  <script src="{{asset('assets/theme/global/js/select2.min.js')}}"></script>  
@endpush
@push('script-push')
<script>
	"use strict";
 		select2_search("{{ translate('Select a status') }}", "#statusUpdateModal");

		$('.update-status').on('click', function() {

			const modal = $('#statusUpdateModal');
			modal.modal('show');
		});


</script>
@endpush


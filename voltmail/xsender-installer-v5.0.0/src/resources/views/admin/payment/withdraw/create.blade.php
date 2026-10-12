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
                                <a href="{{ route('admin.dashboard') }}">{{ translate("Dashboard") }}</a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.payment.withdraw.index') }}">{{ translate("Withdraw Methods") }}</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page"> {{ $title }} </li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
        <div class="card">
			<div class="form-header">
				<h4 class="card-title">{{ $title }}</h4>
			</div>
            <div class="card-body pt-0">
                <form action="{{ route('admin.payment.withdraw.store') }}" method="POST" enctype="multipart/form-data">
					@csrf

                    <div class="form-element">
                        <div class="row gy-4">
                            <div class="col-xxl-2 col-xl-3">
                                <h5 class="form-element-title">{{ translate("Basic Information") }}</h5>
                            </div>
                            <div class="col-xxl-8 col-xl-9">
                                <div class="row gy-4">
									<div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="name" class="form-label"> 
                                                {{ translate("Name") }} 
                                                <small class="text-danger">*</small>
                                            </label>
                                            <input 
                                                type="text" 
                                                id="name" 
                                                name="name" 
                                                class="form-control" 
                                                placeholder="{{ translate('Enter withdraw method name') }}" 
                                                aria-label="{{ translate('Name') }}"
                                                data-bs-toggle="tooltip" 
                                                data-bs-placement="top" 
                                                data-bs-title="{{ translate('Enter the name of the withdrawal method (e.g., Bank Transfer, PayPal)') }}"/>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="image" class="form-label">
                                                {{ translate("Image") }} <small class="text-danger" >* ({{ filePath()['withdraw_method']['size'] }})</small>
                                            </label>
                                            <input class="form-control" type="file" name="image" class="preview" data-size="150x150"
                                                data-bs-toggle="tooltip" 
                                                data-bs-placement="top" 
                                                data-bs-title="{{ translate('Upload an image for the withdrawal method (must match specified size and accepted file types)') }}"/>
                                            <p class="form-element-note">{{ translate("Accepted Image Type: ") . implode(', ', json_decode(site_settings("mime_types"), true)) }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-inner">
                                            <label for="duration_value" class="form-label">
                                                {{ translate("Duration") }}
                                               
                                                <small class="text-danger">*</small>
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text">{{ translate("Within") }}</span>
                                                <input type="number" 
                                                    name="duration[value]" 
                                                    id="duration_value" 
                                                    aria-label="{{ translate('Duration Value') }}" 
                                                    class="form-control" 
                                                    placeholder="{{ translate('Withdraw duration') }}" 
                                                    min="0" 
                                                    value="0"
                                                    data-bs-toggle="tooltip" 
                                                    data-bs-placement="top" 
                                                    data-bs-title="{{ translate('Enter the duration value for processing withdrawals (e.g., 3)') }}"/>
                                                <select id="duration_unit" 
                                                    class="form-select" 
                                                    aria-label="{{ translate('Duration Unit') }}" 
                                                    name="duration[unit]"
                                                    data-bs-toggle="tooltip" 
                                                    data-bs-placement="top" 
                                                    data-bs-title="{{ translate('Select the unit of time for the duration (e.g., days, hours)') }}">
                                                    <option value="" disabled selected>{{ translate("Select a duration unit") }}</option>
                                                    @foreach(\App\Enums\WithdrawDurationEnum::toArray() as $value)
                                                        <option value="{{ $value }}">{{ ucfirst(str_replace('_', ' ', $value)) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="info-note">
                                                <i class="ri-information-line"></i>
                                                <span>{{ translate('Represents the time window for processing withdraw requests') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-element">
                        <div class="row gy-4">
                            <div class="col-xxl-2 col-xl-3">
                                <h5 class="form-element-title">{{ translate("Amount Configurations") }}</h5>
                            </div>
                            <div class="col-xxl-8 col-xl-9">
                                <div class="row gy-4">
                                    <div class="col-md-12">
                                        <div class="form-inner">
                                            <label for="currency_code" class="form-label">
                                                {{ translate("Currency") }}
                                                <small class="text-danger">*</small>
                                            </label>
                                            <select data-placeholder="{{ translate('Select a currency') }}" class="form-select" data-show="5" id="currency_code" name="currency_code"
                                                data-bs-toggle="tooltip" 
                                                data-bs-placement="top" 
                                                data-bs-title="{{ translate('Select the currency for the withdrawal method (e.g., USD, EUR)') }}">
                                                <option selected disabled value="">{{ translate("Select a currency") }}</option>
                                                @foreach($currencies as $key => $currency)
                                                    <option data-rate_value="{{ shortAmount($currency['rate']) }}" value="{{ $key }}">{{ $key }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="minimum_amount" class="form-label"> 
                                                {{ translate('Minimum Amount') }}
                                                <sup class="text-danger">*</sup>
                                            </label>
                                            <div class="input-group mb-3">
                                                <span class="input-group-text currency-display">{{ translate('1 ') . getDefaultCurrencyCode($currencies) }}</span>
                                                <input type="number" name="minimum_amount" class="method-rate form-control"
                                                    min="0"
                                                    step="any"
                                                    data-bs-toggle="tooltip" 
                                                    data-bs-placement="top" 
                                                    data-bs-title="{{ translate('Enter the minimum amount allowed for withdrawal') }}"/>
                                                <span class="input-group-text limittext"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="maximum_amount" class="form-label"> 
                                                {{ translate('Maximum Amount') }}
                                                <sup class="text-danger">*</sup>
                                            </label>
                                            <div class="input-group mb-3">
                                                <span class="input-group-text currency-display">{{ translate('1 ') . getDefaultCurrencyCode($currencies) }}</span>
                                                <input type="number" name="maximum_amount" class="method-rate form-control"
                                                    min="0"
                                                    step="any"
                                                    data-bs-toggle="tooltip" 
                                                    data-bs-placement="top" 
                                                    data-bs-title="{{ translate('Enter the maximum amount allowed for withdrawal') }}"/>
                                                <span class="input-group-text limittext"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="fixed_charge" class="form-label"> 
                                                {{ translate('Fixed Charge') }}
                                                <sup class="text-danger">*</sup>
                                            </label>
                                            <div class="input-group mb-3">
                                                <span class="input-group-text currency-display">{{ translate('1 ') . getDefaultCurrencyCode($currencies) }}</span>
                                                <input type="number" name="fixed_charge" class="method-rate form-control"
                                                    min="0"
                                                    step="any"
                                                    data-bs-toggle="tooltip" 
                                                    data-bs-placement="top" 
                                                    data-bs-title="{{ translate('Enter the fixed fee charged for each withdrawal') }}"/>
                                                <span class="input-group-text limittext"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="percent_charge" class="form-label"> 
                                                {{ translate('Percent Charge') }}
                                                <sup class="text-danger">*</sup>
                                            </label>
                                            <div class="input-group mb-3">
                                                <input type="number" step="any" min="0" name="percent_charge" class="form-control"
                                                    data-bs-toggle="tooltip" 
                                                    data-bs-placement="top" 
                                                    data-bs-title="{{ translate('Enter the percentage fee charged for each withdrawal') }}"/>
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
					<div class="form-element child">
						<div class="row gy-4">
							<div class="col-xxl-2 col-xl-3">
								<h5 class="form-element-title">{{ translate("Additional Information") }}</h5>
							</div>
							<div class="col-xxl-8 col-xl-9">
								<div class="row gy-4">
									<div class="col-md-12">
										<label for="note" class="form-label">{{ translate("Note") }}<sup class="text-danger">*</sup></label>
										<textarea name="note" id="note" class="form-control" placeholder="{{ translate('Note for users') }}"
                                            data-bs-toggle="tooltip" 
                                            data-bs-placement="top" 
                                            data-bs-title="{{ translate('Enter additional instructions or information for users about the withdrawal method') }}"></textarea>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="form-element">
						<div class="row gy-4">
							<div class="col-xxl-2 col-xl-3">
								<h5 class="form-element-title">{{ translate("Custom Information") }}

                                    <span data-bs-toggle="tooltip" 
                                    data-bs-placement="top" 
                                    data-bs-title="{{ translate('Add additional method specific field at least one information is required') }}">
                                    <i class="ri-question-line"></i>
                                </span>
                                </h5>
                                
							</div>
							<div class="col-xxl-8 col-xl-9">
								<div class="row newdata-row gy-4 align-items-end">
									<div class="col-md-5">
										<div class="form-inner">
											<label for="field_name" class="form-label"> {{ translate('Field Name') }} </label>
											<input name="field_name[]" class="form-control" type="text" placeholder="{{ translate('Field Name') }}"/>
										</div>
									</div>
									<div class="col-md-5">
										<div class="form-inner">
											<label for="typeSelect" class="form-label"> {{ translate("Field Type") }} </label>
											<select data-placeholder="{{ translate('Select a type') }}" class="form-select select2-search" data-show="5" id="typeSelect" name="field_type[]">
												<option value="text">{{ translate('Input Text') }}</option>
												<option value="file">{{ translate('File') }}</option>
												<option value="textarea">{{ translate('Textarea') }}</option>
											</select>
										</div>
									</div>
									<div class="col-md-2 ">
                                        <div class="d-flex align-items-center gap-2 ms-auto justify-content-end">
										</div>
									</div>
								</div>
								<div class="newdataadd"></div>
							</div>
						</div>
					</div>

                    <div class="row">
                        <div class="col-xxl-10">
                            <div class="form-action justify-content-end">
                                <button type="reset" class="i-btn btn--danger outline btn--md"> {{ translate("Reset") }} </button>
                                <button type="submit" class="i-btn btn--primary btn--md"> {{ translate("Save") }} </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
@endsection
@push("script-include")
  <script src="{{ asset('assets/theme/global/js/select2.min.js') }}"></script>  
@endpush
@push('script-push')
<script>
	(function($){
		"use strict";
		select2_search($('.select2-search').data('placeholder'));
		$("#currency_code").on('change', function(){
			var value = $(this).find("option:selected").text();
			if(value != '') {
				$(".limittext").text(value);
			} else {
				$(".limittext").text("{{ translate('Select A Currency') }}");
			}
			
			$(".method-rate").val($('select[name=currency_code] :selected').data('rate_value'));
		}).change();
	})(jQuery);
</script>
<script>
    (function($) {
		"use strict";

		let initialFieldsHtml = '';
		let dynamicFieldsHtml = '';

		function updatePlusIcon() {
			$('.newdata-row').each(function(index) {
				$(this).find('.newData').remove();
			});
			const lastRow = $('.newdata-row').last();
			if (lastRow.length > 0) {
				lastRow.find('.d-flex').append('<button type="button" class="icon-btn btn-md primary-soft hover newData"><i class="ri-add-line"></i><span class="tooltiptext"> {{ translate("Add new") }} </span></button>');
			}
		}

		function updateDeleteIcons() {
			$('.removeBtn').show();

			if ($('.newdata-row').length <= 1) {
				$('.removeBtn').hide();
			}
		}

        function updateCurrencyDisplay() {
            $('.method-rate').each(function() {
                const $input = $(this);
                const $inputGroup = $input.closest('.input-group');
                const $currencyDisplay = $inputGroup.find('.currency-display');
                const rate = parseFloat($('#currency_code').find('option:selected').data('rate_value')) || 1;
                const inputValue = parseFloat($input.val()) || 0;
                const defaultCurrencyValue = (inputValue / rate).toFixed(2);
                
                $currencyDisplay.text(defaultCurrencyValue + ' {{ getDefaultCurrencyCode($currencies) }}');
            });
        }

    	function bindEvents() {
			$(document).on('click', '.newData', function() {
				var html = `
					<div class="row newdata-row gy-4 align-items-end mt-1">
						<div class="col-xxl-5 col-md-5">
							<div class="form-inner">
								<label for="field_name" class="form-label"> {{ translate('Field Name') }} </label>
								<input name="field_name[]" class="form-control" type="text" placeholder="{{ translate('Field Name') }}">
							</div>
						</div>
						<div class="col-xxl-5 col-md-5 col-sm-9">
							<div class="form-inner">
								<label for="typeSelect" class="form-label"> {{ translate("Field Type") }} </label>
								<select data-placeholder="{{ translate('Select a type') }}" class="form-select select2-search" data-show="5" id="typeSelect" name="field_type[]">
									<option value="text">{{ translate('Input Text') }}</option>
									<option value="file">{{ translate('File') }}</option>
									<option value="textarea">{{ translate('Textarea') }}</option>
								</select>
							</div>
						</div>
						<div class="col-xxl-2 col-md-2 col-sm-3">
							<div class="d-flex align-items-center gap-2 ms-auto justify-content-end">
								<button type="button" class="icon-btn btn-md danger-soft hover removeBtn">
									<i class="ri-delete-bin-line"></i>
									<span class="tooltiptext"> {{ translate("Delete") }} </span>
								</button>
							</div>
						</div>
					</div>`;

				$('.newdataadd').append(html);
				updatePlusIcon();
				updateDeleteIcons();
			});

			$(document).on('click', '.removeBtn', function() {
				$(this).closest('.newdata-row').remove();
				updatePlusIcon();
				updateDeleteIcons();
			});

			$(document).on('click', 'button[type="reset"]', function() {
				$('.newdataadd').html(dynamicFieldsHtml);
				$('.existing-fields').html(initialFieldsHtml);
				updatePlusIcon();
				updateDeleteIcons();
			});
		}

		$(document).ready(function() {

            $('#currency_code').on('change', updateCurrencyDisplay);
            $('.method-rate').on('input', updateCurrencyDisplay);
            updateCurrencyDisplay();

			initialFieldsHtml = $('.existing-fields').html();
			dynamicFieldsHtml = $('.newdataadd').html();

			bindEvents();
			updatePlusIcon();
			updateDeleteIcons();
		});
	})(jQuery);
</script>
@endpush
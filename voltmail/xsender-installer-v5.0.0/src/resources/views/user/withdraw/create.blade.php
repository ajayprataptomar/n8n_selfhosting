@extends('user.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-fluid px-0 main-content">
      <div class="page-header">
        <div class="row gy-4">
          <div class="col-md-5">
            <div class="page-header-left">
              <h2>{{ $title }}</h2>
              <div class="breadcrumb-wrapper">
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                      <a href="{{ route('user.dashboard') }}">{{ translate("Dashboard") }}</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="{{ route('user.withdraw.index') }}">{{ translate("Withdraw") }}</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page"> {{ translate("Create Withdraw Request") }} </li>
                  </ol>
                </nav>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <div class="card">
            <div class="card-header">
              <h4 class="card-title">{{ translate("Create Withdraw Request") }}</h4>
            </div>
            <div class="card-body p-4">
              <div class="step-wrapper step-full-width justify-content-start">
                <ul class="progress-steps">
                  <li class="step-item activated active">
                    <span>{{ translate("01") }}</span> {{ translate("Method") }}
                  </li>
                  <li class="step-item">
                    <span>{{ translate("02") }}</span> {{ translate("Details") }}
                  </li>
                  <li class="step-item">
                    <span>{{ translate("03") }}</span> {{ translate("Additional Information") }}
                  </li>
                </ul>
              </div>
              <div class="step-content">
                <div class="step-content-item active">
                  <div class="gateways">
                    <ul class="gateway-list">
                      @php 
                        $method_count = $methods->count();
                      @endphp
                      @foreach($methods as $method)
                        <li class="gateway-item">
                          <div class="form-check card-radio" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ $method->name }}">
                            <input id="method-{{$method->id}}" type="radio" class="form-check-input" name="withdraw_method" value="{{ $method->uid }}" />
                            <label class="form-check-label w-100 text-center" for="method-{{$method->id}}">
                              <span class="gateway-img">
                                <img src="{{showImage(filePath()['withdraw_method']['path'].'/'.$method->image, filePath()['withdraw_method']['size'])}}" class="withdraw-method-logo">
                              </span>
                            </label>
                          </div>
                        </li>
                      @endforeach
                      @if($method_count == 0) 
                        <p class="fw-medium fs-14">{{ translate("No Withdraw Methods Available") }}</p>
                      @endif
                    </ul>
                  </div>
                  <div class="form-action justify-content-between">
                    <button type="button" class="i-btn btn--dark outline btn--md step-back-btn"> {{ translate("Previous") }} </button>
                    <button type="button" class="i-btn btn--primary btn--md step-next-btn withdraw-method-selection-next d-none"> {{ translate("Next") }} </button>
                  </div>
                </div>
                <div class="step-content-item">
                  <div class="payment-details">
                    @php
                      $symbol = getDefaultCurrencySymbol(json_decode(site_settings("currencies"), true));
                    @endphp
                    <ul class="list-group list-group-flush">
                      <li class="list-group-item withdraw-method-name">{{ translate("Selected Method Name") }} <span></span></li>
                      <li class="list-group-item withdraw-duration">{{ translate("Processing Duration") }} <span></span></li>
                      <li class="list-group-item withdraw-note">{{ translate("Note") }} <span></span></li>
                      <li class="list-group-item withdraw-minimum-amount">{{ translate("Minimum Amount") }} <span></span></li>
                      <li class="list-group-item withdraw-maximum-amount">{{ translate("Maximum Amount") }} <span></span></li>
                      <li class="list-group-item withdraw-amount">{{ translate("Withdraw Amount") }} <span></span></li>
                      <li class="list-group-item withdraw-fixed-charge">{{ translate("Fixed Charge") }} <span></span></li>
                      <li class="list-group-item withdraw-percent-charge">{{ translate("Percent Charge") }} <span></span></li>
                      <li class="list-group-item withdraw-total">{{ translate("Total") }} <span></span></li>
                      <li class="list-group-item withdraw-final-amount">{{ translate("Payable Amount") }} <span></span></li>
                    </ul>
                    <div class="form-inner mt-4">
                      <label for="withdraw_amount" class="form-label">{{ translate("Enter Amount") }}</label>
                      <input type="number" id="withdraw_amount" class="form-control" name="withdraw_amount" placeholder="{{ translate('Enter amount') }}" min="0" step="0.01" />
                      <small class="text-muted">
                          <div class="d-flex gap-1">
                                {{ translate("Available Balance: ") }} 
                                <span class="ps-3 text-bold">
                                    {{ getDefaultCurrencySymbol() }} 
                                </span>
                                <span>
                                    {{ formatNumber($usableBalance) }}
                                </span>
                            </div>
                        </small>
                    </div>
                  </div>
                  <div class="form-action justify-content-between">
                    <button type="button" class="i-btn btn--dark outline border btn--md step-back-btn"> {{ translate("Previous") }} </button>
                    <button type="button" class="i-btn btn--primary btn--md step-next-btn withdraw-details-next d-none"> {{ translate("Next") }} </button>
                  </div>
                </div>
                <div class="step-content-item">
                  <div class="add-manual-fields">
                    <form id="withdraw-request-form" action="{{ route('user.withdraw.store') }}" method="POST" enctype="multipart/form-data">
                      @csrf
                      <div class="row g-4">
                      </div>
                    </form>
                  </div>
                  <div class="form-action justify-content-between last-step">
                    <button type="button" class="i-btn btn--dark outline border btn--md step-back-btn"> {{ translate("Previous") }} </button>
                    <button type="button" class="i-btn btn--primary btn--md withdraw-custom-informations">{{ translate("Submit Request") }}</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
</main>
@endsection
@push('script-include')
    <script src="{{asset('assets/theme/user/js/stage-step.js')}}"></script>
@endpush
@push('script-push')
<script>
    (function($){
        "use strict";
        var currencies = {!! json_encode(json_decode(site_settings('currencies'), true)) !!};
        var defaultCurrencyCode = "{{ getDefaultCurrencyCode(json_decode(site_settings('currencies'), true)) }}";
        var methods = {!! json_encode($methods->map(function($method) {
            return [
                'uid' => $method->uid,
                'name' => $method->name,
                'currency_code' => $method->currency_code,
                'fixed_charge' => $method->fixed_charge,
                'percent_charge' => $method->percent_charge,
                'minimum_amount' => $method->minimum_amount,
                'maximum_amount' => $method->maximum_amount,
                'parameters' => $method->parameters,
                'note' => $method->note,
                'duration' => $method->duration
            ];
        })->toArray()) !!};
        
        // Store form values in constants
        let WITHDRAW_FORM_DATA = {
            method_uid: null,
            amount: null,
            fixed_charge: null,
            percent_charge: null,
            total_charge: null,
            total: null,
            final_amount: null,
            currency_code: null
        };


        function getCurrencySymbol(code) {
            return currencies[code] ? currencies[code].symbol : '--';
        }

        function convertCurrency(amount, fromCurrency, toCurrency) {
            if (!currencies[fromCurrency] || !currencies[toCurrency]) return 0;
            var fromRate = parseFloat(currencies[fromCurrency].rate);
            var toRate = parseFloat(currencies[toCurrency].rate);
            var usdAmount = amount / fromRate;
            var convertedAmount = usdAmount * toRate;
            return parseFloat(convertedAmount.toFixed(2));
        }

        function convertToDefaultCurrency(amount, currencyCode) {
            var defaultCurrency = Object.keys(currencies).find(code => currencies[code].is_default == 1);
            if (!defaultCurrency || !currencies[currencyCode]) return 0;
            var inputRate = parseFloat(currencies[currencyCode].rate);
            var defaultRate = parseFloat(currencies[defaultCurrency].rate);
            var convertedAmount = (amount * defaultRate) / inputRate;
            return parseFloat(convertedAmount.toFixed(2));
        }

        // Reset form data when method changes
        $(document).on('click', '.form-check-input', function() {
            $('.withdraw-method-selection-next').removeClass('d-none');
            var method_uid = $('input[name="withdraw_method"]:checked').val();
            
            // Reset form data
            WITHDRAW_FORM_DATA = {
                method_uid: null,
                amount: null,
                fixed_charge: null,
                percent_charge: null,
                total_charge: null,
                total: null,
                final_amount: null,
                currency_code: null
            };
            
            
            var default_currency = "{{ getDefaultCurrencySymbol(json_decode(site_settings('currencies'), true)) }}";
            
            methods.forEach(function(method) {
                if (method_uid == method.uid) {
                    $('.withdraw-method-name span').text(method.name);
                    $('.withdraw-duration span').text(method.duration ? method.duration.value + ' ' + method.duration.unit : 'N/A');
                    $('.withdraw-note span').text(method.note || 'N/A');
                    $('.withdraw-minimum-amount span').text(default_currency + ' ' + method.minimum_amount);
                    $('.withdraw-maximum-amount span').text(default_currency + ' ' + method.maximum_amount);
                    $('.withdraw-amount span').text(default_currency + ' 0.00');
                    $('.withdraw-fixed-charge span').text(default_currency + ' ' + parseFloat(method.fixed_charge).toFixed(2));
                    $('.withdraw-percent-charge span').text(method.percent_charge + '%');
                    $('.withdraw-total span').text(default_currency + ' 0.00');
                    $('.withdraw-final-amount span').text(getCurrencySymbol(method.currency_code) + ' 0.00');
                    $('#withdraw_amount').attr('min', method.minimum_amount).attr('max', method.maximum_amount);
                    var fieldsHtml = `
                        <form id="withdraw-request-form" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="row g-4">
                    `;
                    if (method.parameters && Object.keys(method.parameters).length > 0) {
                        $.each(method.parameters, function(key, parameter) {
                            if (parameter.field_type) {
                                var fieldLabel = parameter.field_label;
                                var fieldName = parameter.field_name;
                                var fieldType = parameter.field_type;
                                if (fieldType === 'text') {
                                    fieldsHtml += `
                                        <div class="col-12">
                                            <div class="form-inner">
                                                <label for="${fieldName}" class="form-label">${fieldLabel}</label>
                                                <input type="text" id="${fieldName}" class="form-control" name="${fieldName}" aria-label="${fieldLabel}" placeholder="${fieldLabel}" required />
                                            </div>
                                        </div>
                                    `;
                                } else if (fieldType === 'textarea') {
                                    fieldsHtml += `
                                        <div class="col-12">
                                            <div class="form-inner">
                                                <label for="${fieldName}" class="form-label">${fieldLabel}</label>
                                                <textarea id="${fieldName}" class="form-control" name="${fieldName}" aria-label="${fieldLabel}" placeholder="${fieldLabel}" required></textarea>
                                            </div>
                                        </div>
                                    `;
                                } else if (fieldType === 'file') {
                                    fieldsHtml += `
                                        <div class="col-12">
                                            <div class="form-inner">
                                                <label for="${fieldName}" class="form-label">${fieldLabel}</label>
                                                <input type="file" id="${fieldName}" class="form-control" name="${fieldName}" aria-label="${fieldLabel}" required />
                                                <p class="form-element-note">{{ translate("Accepted File Types: ").implode(', ', json_decode(site_settings("mime_types"), true)) }}</p>
                                            </div>
                                        </div>
                                    `;
                                }
                            }
                        });
                    } else {
                        fieldsHtml += `
                            <div class="col-12">
                                <p class="fs-14 text-muted">{{ translate("No additional fields required for this withdrawal method") }}</p>
                            </div>
                        `;
                    }
                    fieldsHtml += `</div></form>`;
                    $('.add-manual-fields').html(fieldsHtml).removeClass('d-none');
                }
            });
        });

        $(document).on('input', '#withdraw_amount', function() {
            var amount = parseFloat($(this).val()) || 0;
            var method_uid = $('input[name="withdraw_method"]:checked').val();
            var available_balance = {{ $usableBalance }};
            var default_currency = "{{ getDefaultCurrencySymbol(json_decode(site_settings('currencies'), true)) }}";

            if (amount <= 0) {
                notify('error', '{{ translate("Amount must be greater than zero") }}');
                $(this).val('');
                return;
            }

            if (amount > available_balance) {
                notify('error', '{{ translate("Insufficient available balance including pending withdrawals") }}');
                $(this).val('');
                return;
            }

            methods.forEach(function(method) {
                if (method_uid == method.uid) {
                    var fixed_charge = convertCurrency(method.fixed_charge, method.currency_code, defaultCurrencyCode);

                    var minimum_amount = convertCurrency(method.minimum_amount, method.currency_code, defaultCurrencyCode);
                    var maximum_amount = convertCurrency(method.maximum_amount, method.currency_code, defaultCurrencyCode);

                    var percent_charge = parseFloat(method.percent_charge);
                    var total_charge = fixed_charge + (amount * percent_charge / 100);
                    var total = amount + total_charge;
                    var final_amount = convertCurrency(total, defaultCurrencyCode, method.currency_code);
                    
                    // Update constants
                    WITHDRAW_FORM_DATA = {
                        method_uid: method_uid,
                        amount: amount,
                        fixed_charge: fixed_charge.toFixed(2),
                        percent_charge: percent_charge,
                        total_charge: total_charge.toFixed(2),
                        total: total.toFixed(2),
                        final_amount: final_amount.toFixed(2),
                        currency_code: method.currency_code
                    };

                    
                    
                    $('.withdraw-amount span').text(default_currency + ' ' + amount.toFixed(2));
                    $('.withdraw-fixed-charge span').text(default_currency + ' ' + fixed_charge.toFixed(2));
                    $('.withdraw-minimum-amount span').text(default_currency + ' ' + minimum_amount.toFixed(2));
                    $('.withdraw-maximum-amount span').text(default_currency + ' ' + maximum_amount.toFixed(2));
                    $('.withdraw-percent-charge span').text(percent_charge + '%');
                    $('.withdraw-total span').text(default_currency + ' ' + total.toFixed(2));
                    $('.withdraw-final-amount span').text(getCurrencySymbol(method.currency_code) + ' ' + final_amount.toFixed(2));
                    $('.withdraw-details-next').removeClass('d-none');
                }
            });
        });

        $(document).on('click', '.withdraw-details-next', function() {
            var method_uid = $('input[name="withdraw_method"]:checked').val();
            var amount = $('#withdraw_amount').val();
            var default_currency = "{{ getDefaultCurrencySymbol(json_decode(site_settings('currencies'), true)) }}";
            var selected_method = methods.find(function(method) {
                return method.uid === method_uid;
            });

            if (!selected_method) {
                notify('error', '{{ translate("Selected method not found") }}');
                return;
            }
            Method = selected_method;
            if (!amount || amount <= 0) {
                notify('error', '{{ translate("Please enter a valid amount") }}');
                return;
            }

            var fieldsHtml = `
                <form id="withdraw-request-form" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="bg-light rounded-2 p-3 fs-15 text-muted border h-100">
                                <p class="fs-16 fw-semibold mb-3 withdraw-title">${selected_method.name} withdrawal of ${parseFloat(WITHDRAW_FORM_DATA.final_amount).toFixed(2)} ${getCurrencySymbol(selected_method.currency_code)}</p>
                                 
                                <p class="fs-14 withdraw-note">${selected_method.note || 'N/A'}</p>
                                <div class="mt-3">
                                    <small class="text-muted d-block">Amount: ${default_currency} ${parseFloat(WITHDRAW_FORM_DATA.amount).toFixed(2)}</small>
                                    <small class="text-muted d-block">Fixed Charge: ${default_currency} ${WITHDRAW_FORM_DATA.fixed_charge}</small>
                                    <small class="text-muted d-block">Percent Charge: ${WITHDRAW_FORM_DATA.percent_charge}%</small>
                                    <small class="text-muted d-block">Total: ${default_currency} ${WITHDRAW_FORM_DATA.total}</small>
                                    <small class="text-success d-block fw-medium">Final Amount: ${getCurrencySymbol(selected_method.currency_code)} ${parseFloat(WITHDRAW_FORM_DATA.final_amount).toFixed(2)}</small>
                                </div>
                            </div>
                        </div>
            `;
            
            if (selected_method.parameters && Object.keys(selected_method.parameters).length > 0) {
                $.each(selected_method.parameters, function(key, parameter) {
                    if (parameter.field_type) {
                        var fieldLabel = parameter.field_label;
                        var fieldName = parameter.field_name;
                        var fieldType = parameter.field_type;
                        var fieldRequired = parameter.required !== false;
                        var requiredAttr = fieldRequired ? 'required' : '';
                        
                        if (fieldType === 'text') {
                            fieldsHtml += `
                                <div class="col-12">
                                    <div class="form-inner">
                                        <label for="param_${fieldName}" class="form-label">${fieldLabel} ${fieldRequired ? '<span class="text-danger">*</span>' : ''}</label>
                                        <input type="text" id="param_${fieldName}" class="form-control" name="parameters[${fieldName}]" aria-label="${fieldLabel}" placeholder="${fieldLabel}" ${requiredAttr} />
                                    </div>
                                </div>
                            `;
                        } else if (fieldType === 'textarea') {
                            fieldsHtml += `
                                <div class="col-12">
                                    <div class="form-inner">
                                        <label for="param_${fieldName}" class="form-label">${fieldLabel} ${fieldRequired ? '<span class="text-danger">*</span>' : ''}</label>
                                        <textarea id="param_${fieldName}" class="form-control" name="parameters[${fieldName}]" aria-label="${fieldLabel}" placeholder="${fieldLabel}" rows="3" ${requiredAttr}></textarea>
                                    </div>
                                </div>
                            `;
                        } else if (fieldType === 'file') {
                            fieldsHtml += `
                                <div class="col-12">
                                    <div class="form-inner">
                                        <label for="param_${fieldName}" class="form-label">${fieldLabel} ${fieldRequired ? '<span class="text-danger">*</span>' : ''}</label>
                                        <input type="file" id="param_${fieldName}" class="form-control" name="parameters[${fieldName}]" aria-label="${fieldLabel}" ${requiredAttr} />
                                        <small class="text-muted">Supported formats: JPG, PNG, PDF (Max: 2MB)</small>
                                    </div>
                                </div>
                            `;
                        } else if (fieldType === 'select' && parameter.options) {
                            fieldsHtml += `
                                <div class="col-12">
                                    <div class="form-inner">
                                        <label for="param_${fieldName}" class="form-label">${fieldLabel} ${fieldRequired ? '<span class="text-danger">*</span>' : ''}</label>
                                        <select id="param_${fieldName}" class="form-control" name="parameters[${fieldName}]" aria-label="${fieldLabel}" ${requiredAttr}>
                                            <option value="">Select ${fieldLabel}</option>
                            `;
                            if (Array.isArray(parameter.options)) {
                                parameter.options.forEach(function(option) {
                                    fieldsHtml += `<option value="${option}">${option}</option>`;
                                });
                            }
                            fieldsHtml += `
                                        </select>
                                    </div>
                                </div>
                            `;
                        }
                    }
                });
            } else {
                fieldsHtml += `
                    <div class="col-12">
                        <p class="fs-14 text-muted">{{ translate("No additional fields required for this withdrawal method") }}</p>
                    </div>
                `;
            }
            
            fieldsHtml += `</div></form>`;
            $('.add-manual-fields').html(fieldsHtml);
            $('.step-wrapper').data('step').next();
        });

        $(document).on('click', '.withdraw-custom-informations', function(e) {
          
            e.preventDefault();
            var form = $('#withdraw-request-form');
            var $submitBtn = $(this);
            var originalText = $submitBtn.text();
            
            // Validate form
            var isValid = true;
            var errorMessages = [];

            form.find('input[required], textarea[required], select[required]').each(function() {
                var $field = $(this);
                var fieldValue = $field.val().trim();
                var fieldLabel = $field.closest('.form-inner').find('label').text().replace('*', '').trim();

                if (!fieldValue) {
                    isValid = false;
                    $field.addClass('is-invalid');
                    errorMessages.push(`${fieldLabel} is required`);
                } else {
                    $field.removeClass('is-invalid');
                }

                if ($field.attr('type') === 'file' && fieldValue) {
                    var file = $field[0].files[0];
                    if (file && file.size > 2 * 1024 * 1024) { // 2MB limit
                        isValid = false;
                        $field.addClass('is-invalid');
                        errorMessages.push(`${fieldLabel} file size exceeds 2MB`);
                    }
                }
            });

            if (!isValid) {
                notify('error', errorMessages.join('<br>'));
                return;
            }

            // Prepare form data for AJAX
            var formData = new FormData(form[0]);
            formData.append('method_uid', WITHDRAW_FORM_DATA.method_uid);
            formData.append('withdraw_amount', WITHDRAW_FORM_DATA.amount);
            formData.append('withdraw_fixed_charge', WITHDRAW_FORM_DATA.fixed_charge);
            formData.append('withdraw_percent_charge', WITHDRAW_FORM_DATA.percent_charge);
            formData.append('withdraw_total_charge', WITHDRAW_FORM_DATA.total_charge);
            formData.append('withdraw_total', WITHDRAW_FORM_DATA.total);
            formData.append('withdraw_final_amount', WITHDRAW_FORM_DATA.final_amount);
            formData.append('withdraw_currency_code', WITHDRAW_FORM_DATA.currency_code);

            $submitBtn.prop('disabled', true).text('{{ translate("Processing...") }}');

            $.ajax({
                url: '{{ route("user.withdraw.store") }}',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.status) {
                        notify('success', response.message || '{{ translate("Withdrawal request submitted successfully") }}');
                        setTimeout(function() {
                            window.location.href = '{{ route("user.withdraw.index") }}';
                        }, 2000);
                    } else {
                        console.log(response);
                        notify('error', response.message || '{{ translate("Failed to submit withdrawal request") }}');
                    }
                },
                error: function(xhr) {
                    let errorMsg = '';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        
                        errorMsg = Object.values(xhr.responseJSON.errors).map(function(errArr) {
                            return errArr.join('<br>');
                        }).join('<br>');
                    } else {
                        errorMsg = xhr.responseJSON?.message || '{{ translate("An error occurred while processing your request") }}';
                    }
                    notify('error', errorMsg);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        });
    })(jQuery);
</script>
@endpush
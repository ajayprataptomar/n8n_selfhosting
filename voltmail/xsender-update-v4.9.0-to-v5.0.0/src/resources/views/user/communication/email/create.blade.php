@push("style-include")
  <link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
@endpush
@extends('user.layouts.app')
@section('panel')
@php
    $jsonArray = json_encode($credentials);
@endphp
<main class="main-body">
    <div class="container-fluid px-0 main-content">
      <div class="page-header">
        <div class="page-header-left">
          <h2>{{ translate("Send "). $title }}</h2>
          <div class="breadcrumb-wrapper">
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb">
                <li class="breadcrumb-item">
                  <a href="{{ route("user.dashboard") }}">{{ translate("Dashboard") }}</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"> {{ translate("Send "). $title }} </li>
              </ol>
            </nav>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="form-header">
          <div class="row gy-4 align-items-center">
            <div class="col-xxl-2 col-xl-3">
              <h4 class="card-title">{{ translate("Choose audience") }}</h4>
            </div>
            <div class="col-xxl-10 col-xl-9">
              <div class="form-tab">
                <ul class="nav" role="tablist">
                  <li class="nav-item" role="presentation">
                    <a class="nav-link {{ old('active_tab', 'singleAudience') == 'singleAudience' ? 'active' : '' }}" data-bs-toggle="tab" href="#singleAudience" role="tab" aria-selected="{{ old('active_tab', 'singleAudience') == 'singleAudience' ? 'true' : 'false' }}">
                      <i class="bi bi-person-fill"></i>{{ translate("Single audience") }} </a>
                  </li>
                  <li class="nav-item" role="presentation">
                    <a class="nav-link {{ old('active_tab') == 'groupAudience' ? 'active' : '' }}" data-bs-toggle="tab" href="#groupAudience" role="tab" aria-selected="{{ old('active_tab') == 'groupAudience' ? 'true' : 'false' }}">
                      <i class="bi bi-people-fill"></i> {{ translate("Group audience") }} </a>
                  </li>
                  <li class="nav-item" role="presentation">
                    <a class="nav-link {{ old('active_tab') == 'importFile' ? 'active' : '' }}" data-bs-toggle="tab" href="#importFile" role="tab" aria-selected="{{ old('active_tab') == 'importFile' ? 'true' : 'false' }}">
                      <i class="bi bi-file-earmark-plus-fill"></i> {{ translate("Import file") }} </a>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="card-body pt-0">
          <form action="{{route('user.communication.email.store', ['type' => 'email'])}}" method="POST" enctype="multipart/form-data" id="email_send">
            @csrf
            <input type="hidden" name="active_tab" id="active_tab" value="{{ old('active_tab', 'singleAudience') }}">
            <div class="tab-content">
              <div class="tab-pane fade {{ old('active_tab', 'singleAudience') == 'singleAudience' ? 'show active' : '' }}" id="singleAudience" role="tabpanel">
                  <div class="form-element">
                      <div class="row gy-3">
                        <div class="col-xxl-2 col-xl-3">
                            <h5 class="form-element-title">{{ translate("Recipient Email") }}</h5>
                        </div>
                        <div class="col-xxl-7 col-lg-9">
                            <div class="form-inner">
                                <label for="email_contacts" class="form-label">{{ translate("Email Address") }}
                                  <span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ translate("Provide receiver's email address, it will be saved under "). \App\Enums\SettingKey::SINGLE_CONTACT_GROUP_NAME->value. translate(" group") }}">
                                    <i class="ri-question-line"></i>
                                  </span>
                                  <sup>*</sup>
                                </label>
                                @if(site_settings('email_contact_verification') == \App\Enums\StatusEnum::TRUE->status())
                                    <div class="input-group">
                                      <input type="text"
                                        class="form-control check-email-address"
                                        id="email_contacts"
                                        name="contacts"
                                        value="{{ old('active_tab') == 'singleAudience' ? old('contacts') : '' }}"
                                        placeholder="{{ translate('Enter recipient email address') }}"
                                        data-url = "{{ route('user.verify.email') }}">
                                      <span class="input-group-text" 
                                        role="button"
                                        data-verified-button-text="{{ translate('Verified') }}"
                                        data-verified-button-icon="ri-check-double-fill"
                                        data-unverified-button-text="{{ translate('Unverified') }}"
                                        data-unverified-button-icon="ri-close-line"
                                        id="verify_email_button"
                                        onclick="verifyEmailHandler()">
                                        <span id="verify_email_text">{{ translate('Verify') }}</span>
                                        <span id="loading_spinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                      </span>
                                    </div>
                                @else
                                  <input type="text" class="form-control check-email-address" id="email_contacts" name="contacts" value="{{ old('active_tab') == 'singleAudience' ? old('contacts') : '' }}" placeholder="{{ translate('Enter recipient email address') }}" >
                                @endif
                            </div>
                        </div>
                      </div>
                  </div>
              </div>

              <div class="tab-pane fade {{ old('active_tab') == 'groupAudience' ? 'show active' : '' }}" id="groupAudience" role="tabpanel">
                <div class="form-element">
                  <div class="row gy-3">
                    <div class="col-xxl-2 col-xl-3">
                      <h5 class="form-element-title">{{ translate('From Group')}}</h5>
                      </div>
                        <div class="col-xxl-7 col-lg-9">
                          <div class="row gy-3 align-items-end">
                            <div class="col-12">
                              <div class="form-inner">
                                <label for="contacts" class="form-label">{{ translate("Choose Group") }}
                                  <span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ translate("Contact groups that contain emails are available to select") }}">
                                    <i class="ri-question-line"></i>
                                  </span>
                                  <sup>*</sup>
                                </label>
                                <select class="form-select select2-search" id="contacts" data-placeholder="{{ translate("Choose groups") }}" aria-label="contacts" name="contacts[]" multiple>
                                  <option value=""></option>
                                    @foreach($groups as $group)
                                        <option value="{{$group->id}}" {{ old('active_tab') == 'groupAudience' && is_array(old('contacts')) && in_array($group->id, old('contacts', [])) ? 'selected' : '' }}>{{$group->name}}</option>
                                    @endforeach
                                </select>
                              </div>
                            </div>
                            <div class="col-12 group-logic d-none">
                              <div class="form-inner">
                                <label class="form-label"> {{ translate("Add Logic") }} </label>
                                <div class="form-inner-switch">
                                  <label class="pointer" for="group_logic" >{{translate('Add Logic to Groups to select specific contacts based on attrbitues')}}</label for="group_logic" >
                                  <div class="switch-wrapper mb-1 checkbox-data">
                                    <input type="checkbox" value="true" name="group_logic" id="group_logic" class="switch-input">
                                    <label for="group_logic" class="toggle">
                                      <span></span>
                                    </label>
                                  </div>
                                </div>
                              </div>
                            </div>
                            <div class="form-item group-logic-items mt-3"></div>
                        </div>
                      </div>
                    </div>
                </div>
              </div>
                
              <div class="tab-pane fade {{ old('active_tab') == 'importFile' ? 'show active' : '' }}" id="importFile" role="tabpanel">
                <div class="form-element">
                  <div class="row gy-3">
                    <div class="col-xxl-2 col-xl-3">
                      <h5 class="form-element-title">{{ translate("Import file") }}</h5>
                    </div>
                    <div class="col-xxl-7 col-lg-9">
                      <div class="form-inner">
                        <label for="file" class="form-label"> {{ translate("Import File") }}
                          <span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ translate("Imported file will create a new group containing it's contacts") }}">
                            <i class="ri-question-line"></i>
                          </span>
                          <sup>*</sup>
                        </label>
                        <input type="file" name="contacts" id="file" class="form-control" aria-label="file" />
                        <p class="form-element-note">{{ translate("Download a demo csv file from this link: ") }} 
                          <a href="{{route('demo.file.download', ['extension' => 'csv' , 'type' => $type])}}">{{ translate("demo.csv") }}</a>
                        </p>
                        <div class="info-note">
                          <i class="ri-information-line"></i>
                            <span>{{ translate("Consider making groups for larger CSV files") }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            @if($planAccess->type == App\Enums\StatusEnum::FALSE->status())
              <div class="form-element">
                  <div class="row gy-3">
                    <div class="col-xxl-2 col-xl-3">
                      <h5 class="form-element-title">{{ translate("Choose Gateway") }}</h5>
                    </div>
                    <div class="col-xxl-7 col-lg-9">
                      <div class="row gy-3 align-items-end">
                        <div class="col-12">
                          <div class="form-inner">
                            <label for="gateway_id" class="form-label">{{ translate("Select Gateway") }}<sup class="text-danger">*</sup></label>
                            <select class="form-select select2-search" id="gateway_id" data-placeholder="{{ translate("Select a gateway") }}" data-show="5" aria-label="gateway_id" name="gateway_id">
                              <option value=""></option>
                              <option value="0" {{ old('gateway_id') == '0' ? 'selected' : '' }}>{{ translate("Random Rotation") }}</option>
                              <option value="-1" {{ old('gateway_id') == '-1' ? 'selected' : '' }}>{{ translate("Automatic") }}</option>
                              <option value="-2" {{ old('gateway_id') == '-2' ? 'selected' : '' }}>{{ translate("Custom Gateway") }}</option>
                              @foreach($gateways as $type => $gateway)

                                  <optgroup label="{{ ucfirst($type) }}">
                                      @foreach($gateway as $id => $name)
                                          <option value="{{ $id }}" {{ old('gateway_id') == (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                                      @endforeach
                                  </optgroup>
                              @endforeach
                            </select>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
              </div>
            @endif

            <div class="form-element custom-gateway-parameter d-none">
              <div class="row gy-3">
                <div class="col-xxl-2 col-xl-3">
                  <h5 class="form-element-title">{{ translate("Gateway Parameter") }}
                    <span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ translate("This custom gateway will be saved & used to dispatch the messages") }}">
                      <i class="ri-question-line"></i>
                    </span>
                  </h5>
                </div>
                <div class="col-xxl-7 col-lg-9">
                  <div class="row gy-4">
                    <div class="col-lg-6">
                        <div class="form-inner">
                            <label for="name" class="form-label"> {{ translate('Gateway Name')}}<sup class="text-danger">*</sup></label>
                            <input type="text" id="name" name="custom_gateway_parameter[name]" placeholder="{{ translate('Enter Gateway Name')}}" class="form-control" aria-label="name"/>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-inner">
                            <label for="address" class="form-label"> {{ translate('Gateway Email Address')}}<sup class="text-danger">*</sup></label>
                            <input type="email" id="address" name="custom_gateway_parameter[address]" placeholder="{{ translate('Enter Gateway Name')}}" class="form-control" aria-label="address"/>
                        </div>
                    </div>
                    <div class="col-lg-12">
                        <div class="form-inner">
                            <label for="country-code" class="form-label">{{ translate("Gateway Type") }}<sup class="text-danger">*</sup></label>
                            <select data-placeholder="{{translate('Select a gateway type')}}" 
                            class="form-select select2-search gateway_type" data-show="5" id="add_gateway_type" name="custom_gateway_parameter[type]">
                                <option value=""></option>
                                @foreach($credentials as $key=>$credential)
                                    <option value="{{strToLower($key)}}">{{strtoupper($key)}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                      <div class="row newdataadd"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="form-element">
              <div class="row gy-3">
                <div class="col-xxl-2 col-xl-3">
                  <h5 class="form-element-title">{{ translate("Schedule At") }}</h5>
                </div>
                <div class="col-xxl-7 col-lg-9">
                  <div class="form-inner">
                    <label for="schedule_at" class="form-label">{{ translate("Choose Date & Time") }}<span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ translate("You can select date and time for schedule operation. Or leave it empty to send normally") }}">
                      <i class="ri-question-line"></i>
                      </span>
                    </label>
                    <div class="input-group">
                      <input type="datetime-local" class="form-control singleDateTimePicker singleDate" placeholder="{{ translate("Select schedule time") }}" name="schedule_at" id="schedule_at" value="{{ old('schedule_at') }}"/>
                      <span class="input-group-text calendar-icon" id="filterByDate">
                        <i class="ri-calendar-2-line"></i>
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="form-element">
              <div class="row gy-3">
                <div class="col-xxl-2 col-xl-3">
                  <h5 class="form-element-title">{{ translate("Sender Information") }}</h5>
                </div>
                <div class="col-xxl-7 col-lg-9">

                  <div class="form-inner mb-3">
                    <label for="email_from_name" class="form-label">{{ translate("Email From Name") }}
                      <span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ translate("This name will be used as 'From Name' for the mails") }}">
                        <i class="ri-question-line"></i>
                      </span>
                    </label>
                    <input type="text" class="form-control" name="email_from_name" id="email_from_name" placeholder="{{ translate("Enter email from name") }}" aria-label="email_from_name" autocomplete="" value="{{ old('email_from_name') }}"/>
                  </div>

                  <div class="form-inner mb-3">
                    <label for="reply_to_address" class="form-label">{{ translate("Reply To Address") }}<span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ translate('Reply to email address helps recipient to communcate with you') }}">
                        <i class="ri-question-line"></i>
                        </span>
                    </label>
                    <input type="email" class="form-control" name="reply_to_address" id="reply_to_address" placeholder="{{ translate("Enter reply to email address") }}" aria-label="reply_to_address" autocomplete="" value="{{ old('reply_to_address') }}"/>
                  </div>
                </div>
              </div>
            </div>

            <div class="form-element">
              <div class="row gy-3">
                <div class="col-xxl-2 col-xl-3">
                  <h5 class="form-element-title">{{ translate("Message body") }}</h5>
                </div>
                <div class="col-xxl-7 col-lg-9">
                  <div class="form-inner mb-3">
                    <label for="subject" class="form-label">{{ translate("Subject") }}<span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ translate('Provide a subject for the mail so it doesn\'t go into the spam') }}">
                        <i class="ri-question-line"></i>
                        </span>
                        <sup class="text-danger">*</sup>
                    </label>
                    <input type="text" class="form-control" name="message[subject]" id="subject" placeholder="{{ translate("Enter email subject") }}" aria-label="subject" autocomplete="" value="{{ old('message.subject') }}"/>
                </div>
                <div class="form-inner position-relative speech-to-text" id="messageBox">
                  <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-2 mb-2">
                    <label for="message" class="form-label mb-0">{{ translate("Write message") }}<sup class="text-danger">*</sup></label>
                    <button class="i-btn btn--sm p-0 bg-transparent text-primary available-template" id="selectEmailTemplate" type="button">
                      <i class="ri-layout-fill fs-5"></i>{{ translate("Use Email Template") }}</button>
                  </div>
                  <textarea class="form-control" name="message[main_body]" id="message" rows="5" placeholder="{{translate('Enter Email Content')}}  @php echo "\nIf Contact is being selected from a group then to mention First Name Use {{". 'first_name' ."}} \nTo initiate text spinner type {Hello|Hi|Hola} to you, {Mr.|Mrs.|Ms.} {Lucia|Jimmy|Arnold}\nAttach {{unsubscribe_link}} to add an opt-out/unsubscribe link for recipients"@endphp">{{ old('message.main_body') }}</textarea>
                  <div class="voice-icon">
                    <button type="button" class="icon-btn btn-sm primary-soft circle hover" id="text-to-speech-icon">
                      <i class="ri-mic-fill"></i>
                      <span class="tooltiptext"> {{ translate("Voice") }} </span>
                    </button>
                  </div>
                </div>
                @include('partials.email_attachments')
                </div>

              </div>
            </div>

            <div class="row">
              <div class="col-xxl-9">
                <div class="form-action justify-content-end">
                  <button type="submit" class="i-btn btn--primary btn--md"> {{ translate("Send") }} </button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
</main>


@endsection
@section('modal')

<div class="modal fade" id="availableTemplate" tabindex="-1" aria-labelledby="availableTemplate" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered ">
      <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Email Templates") }} </h5>
            <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                <i class="ri-close-large-line"></i>
            </button>
        </div>
        <div class="modal-body modal-md-custom-height">
            <div class="row g-4">
              <div class="col-12">
                <div class="form-inner">
                  <label for="chooseTemplate" class="form-label">{{ translate("Available Templates") }}</label>
                  <select class="form-select select2-search" id="chooseTemplate" data-placeholder="{{ translate("Choose an Email template") }}" aria-label="chooseTemplate">
                    <option value=""></option>
                    @foreach($templates as $template)
                      <option value="{{ json_encode($template->template_data) }}">{{ $template->name }}</option>
                    @endforeach
                  </select>
                </div>
              </div>

              <div class="template-data"></div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="i-btn btn--danger outline btn--md" data-bs-dismiss="modal"> {{ translate("Close") }} </button>
            <button type="submit" id="saveTemplateButton" class="i-btn btn--primary btn--md"> {{ translate("Save") }} </button>
        </div>
      </div>
  </div>
</div>

<div class="modal fade" id="globalModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div id="modal-size" class="modal-dialog modal-fullscreen">
      <div class="modal-content">
          <div class="modal-header">
              <h3 id="modal-title"></h3>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div id="modal-body">

          </div>
      </div>
  </div>
</div>
@endsection
@push("script-include")
  <script src="{{asset('assets/theme/global/js/select2.min.js')}}"></script>
  <script src="{{asset('assets/theme/global/js/template.js') }}"></script>
  <script src="{{asset('assets/theme/global/js/BeePlugin.js') }}"></script>
@endpush
@push('script-push')
@include('partials.email_verify')
@include('partials.email_check')
<script>
	"use strict";
    select2_search($('.select2-search').data('placeholder'));
    ck_editor("#message");

    $(document).ready(function() {

      const modal = $('#globalModal');
      $(document).on('click','#use-template',function(e) {
        
          var html                  = $(this).attr('data-html')
          const domElement          = document.querySelector( '.ck-editor__editable' );
          const emailEditorInstance = domElement.ckeditorInstance;
          emailEditorInstance.setData( html );
          modal.modal('hide');
      })

      $(document).on('click','#selectEmailTemplate',function(e) {

        $("#selectEmailTemplate").html('{{translate("Template Loading...")}}');
        appendTemplate()
        e.preventDefault()
      })

      function appendTemplate() {
        $.ajax({
          method:"GET",
          url:"{{ route('user.template.email.templates') }}",
          dataType:'json'
        }).then(response=>{

          $("#selectEmailTemplate").html('{{translate("Use Email Template")}}');
          appendModalData(response.view)
        })
      }
      function appendModalData(view) {

        $('#modal-title').html(`{{translate('Pre Build Template')}}`)
        var html = `
          <div class="modal-body">
            ${view}
          </div>
        `
        $('#modal-body').html(html)
        modal.modal('show');
      }

      function handleMergedAttributes(attributes) {

        var initialAttributeOptions = $(".group-logic-items").html();
        $('#group_id').on('select2:unselect', function (e) {

            $('.group-logic').addClass('d-none');
            resetAttributeOptions();
            function resetAttributeOptions() {

                $(".group-logic-items").html(initialAttributeOptions);
            }
        });
        $(".group-logic").removeClass("d-none");
        $('#group_logic').change(function() {

            var selectedAttributeValue;
            if ($(this).is(':checked')) {

                $(".group-logic-items").html(`
                    <div class="row">
                        <div class="col-md-6">
                            <label for="attribute_name" class="form-label">Attributes<sup class="text-danger">*</sup></label>
                            <select class="form-select repeat-scale" required name="attribute_name" id="attribute_name">
                                <option selected disabled>-- Select an Attribute --</option>
                                <option value="sms_contact">SMS Contact Number</option>
                                <option value="whatsapp_contact">WhatsApp Contact Number</option>
                                <option value="email_contact">Email Address</option>
                                <option value="first_name">First Name</option>
                                <option value="last_name">Last Name</option>
                                ${getAttributesOptionsHTML(attributes)}
                            </select>
                        </div>
                        <div class="col-md-6" id="logic-input-container"></div>
                    </div>
                `);

                $('#attribute_name').change(function() {

                    selectedAttributeValue = $(this).val();
                    $('#logic-input-container').html(`
                        ${getLogicInputHTML(selectedAttributeValue)}
                    `);
                });
            } else {

                $(".group-logic-items").html('');
            }
        });

        function getAttributesOptionsHTML(attributes) {
            return Object.keys(attributes)
                .map(attribute => `<option value="${attribute}::${attributes[attribute]}">${formatAttributeName(attribute)}</option>`)
                .join('');
        }

        function formatAttributeName(attribute) {

            return attribute.replace(/_/g, ' ').replace(/\b\w/g, firstLetter => firstLetter.toUpperCase());
        }

        function getLogicInputHTML(attribute) {

          var value = attributes[attribute.split("::")[0]];
          if (value && value != undefined) {

            if (value == {{\App\Models\GeneralSetting::DATE}}) {

                return `<div class="row"><div title="Only the contacts with this date in the '${attribute.split("::")[0]}' attribute will be selected for this Campaign" class="col-md-6"><label for="attribute_name" class="form-label">{{ translate("Select a Date") }}<sup class="text--danger">*</sup></label>
                              <input type="datetime-local" class="date-picker form-control" name="logic" id="logic"></div>
                              <div title="Only the contacts within this range in the '${attribute.split("::")[0]}' attribute will be selected for this Campaign" class="col-md-6"><label for="attribute_name" class="form-label">{{ translate("Select The Range") }}<sup class="text-danger">*</sup></label>
                              <input type="datetime-local" class="date-picker form-control" name="logic_range" id="logic_range"></div></div>`;

            } else if (value == {{\App\Models\GeneralSetting::BOOLEAN}}) {

                  return `<label for="attribute_name" class="form-label">{{ translate("Conditions") }}<sup class="text-danger">*</sup></label>
                          <select class="form-select repeat-scale" required name="logic" id="logic">
                              <option selected disabled>${('-- Select a Logic --')}</option>
                              <option value="true">Yes</option>
                              <option value="false">No</option>
                          </select>`;
            } else if (value == {{\App\Models\GeneralSetting::NUMBER}}) {

                return `<div class="row"><div title="Only the contacts with this number in the '${attribute.split("::")[0]}' attribute will be selected for this Campaign" class="col-md-6"><label for="attribute_name" class="form-label">{{ translate("Contains") }}<sup class="text-danger">*</sup></label>
                        <input type="number" class="form-control" name="logic" id="logic" placeholder="Enter a number"></div
                        <div class="row"><div title="Only the contacts within this range in the '${attribute.split("::")[0]}' attribute will be selected for this Campaign" class="col-md-6"><label for="attribute_name" class="form-label">{{ translate("Range") }}<sup class="text-danger">*</sup></label>
                        <input type="number" class="form-control" name="logic_range" id="logic_range" placeholder="Enter a number"></div`;
            } else if (value == {{\App\Models\GeneralSetting::TEXT}}) {

                  return `<label for="attribute_name" class="form-label">{{ translate("Contains") }}<sup class="text-danger">*</sup></label>
                          <input type="text" class="form-control" name="logic" id="logic" placeholder="Enter text">`;
            }
          } else {

              return `<label for="attribute_name" class="form-label">{{ translate("Contains") }}<sup class="text-danger">*</sup></label>
                  <input type="text" class="form-control" name="logic" id="logic" placeholder="Enter text">`;
          }
        }
      }

      function handleEmptyContacts(message) {

        $('.group-logic').addClass("d-none");
        $('.group-logic-items').addClass("d-none");
        $('input[name=logic]').removeAttr('name');
        $('input[name=group_logic]').removeAttr('name');
        $('select[name=attribute_name]').removeAttr('name');
        $('select[name=attribute_name]').prop('disabled', true);
        notify('info', message);
      }

      $('#contacts').change(function() {

          var selectedValues = $(this).val();
          var channelValue = '{{ $type }}';
          var csrfToken = $('meta[name="csrf-token"]').attr('content');
          if (selectedValues) {

              $.ajax({
                  url: '{{ route("user.contact.group.fetch", ["type" => "meta_data"]) }}',
                  type: 'POST',
                  data: {
                      group_ids: selectedValues,
                      channel: "email",
                  },
                  headers: {
                      'X-CSRF-TOKEN': csrfToken,
                  },
                  success: function(response) {

                      if (response.status == true) {

                          handleMergedAttributes(response.merged_attributes);
                      } else {

                          handleEmptyContacts(response.message);
                      }
                  },
                  error: function(error) {
                      console.error(error);
                  }
              });
          }
      });

      // Track active tab changes
      $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
          var tabId = $(e.target).attr('href').replace('#', '');
          $('#active_tab').val(tabId);
      });

      $('#email_send').on('submit', function(event) {

          var activeTabId = $('.tab-content .tab-pane.active').attr('id');
          $('#active_tab').val(activeTabId);
          if (activeTabId !== 'singleAudience') {

              $('#singleAudience input:not([name="active_tab"])').val('');
          }
          if (activeTabId !== 'groupAudience') {

              $('#groupAudience input').val('');
          }
          if (activeTabId !== 'importFile') {

              $('#importFile input').val('');
          }
      });
    });
</script>

<script>
  (function($) {

      "use strict";
      $(document).ready(function() {

        const $gatewayType            = $('.gateway_type');
        const $newDataAdd             = $('.newdataadd');
        const $customGatewayParameter = $('.custom-gateway-parameter');
        const $gatewaySelect          = $('#gateway_id');
        const $form                   = $('#email_send');
        let oldType                   = '';
        let oldInfo                   = {};
        let customGatewayData         = {};
        $customGatewayParameter.addClass('d-none');
        $gatewaySelect.on('change', function() {

            if ($(this).val() === '-2') {

                $customGatewayParameter.removeClass('d-none');
                updateCustomGatewayFields();
            } else {

                $customGatewayParameter.addClass('d-none');
                removeCustomGatewayFields(); 
            }
        });

        $gatewayType.on('change', function() {

            const newType = this.value;
            const data    = <?php echo $jsonArray ?>[newType].meta_data;
            const native_bulk_support    = <?php echo $jsonArray ?>[newType].native_bulk_support;
            $newDataAdd.empty();
            if (newType !== oldType) {
              if (native_bulk_support == true) {
                    var bulkLimitBlock = `
                        <div class="col-12 mb-4" id="bulk_contact_limit_wrapper">
                            <div class="form-inner">
                                <label for="bulk_contact_limit" class="form-label">Bulk Contact Limit</label>
                                <input value="1" type="number" min="1" id="bulk_contact_limit" name="custom_gateway_parameter[bulk_contact_limit]" placeholder="Enter Bulk Contact Limit" class="form-control" aria-label="bulk_contact_limit"/>
                            </div>
                        </div>
                    `;
                    
                    $newDataAdd.append(bulkLimitBlock); 
                }
                oldInfo = data;  
                renderNewFields(data);
            } else {

                renderOldFields();
            }

            oldType = newType;
        });

        function renderNewFields(data) {

            const totalItems = Object.keys(data).length;
            $.each(data, function(key, v) {

                const filterKey     = key.replace("_", " ");
                let colClass        = 'col-lg-6';
                const isLastElement = (Object.keys(data).indexOf(key) === totalItems - 1);
                const isOdd         = totalItems % 2 !== 0;
                if (isLastElement && isOdd) {

                    colClass = 'col-12';
                }
                const $div   = $(`<div class="mb-4 ${colClass}"></div>`);
                const $label = $(`<label for="${key}" class="form-label text-capitalize">${filterKey}<sup class="text-danger">*</sup></label>`);
                if (key !== 'encryption') {

                    const $input = $(`<input type="text" class="form-control" id="${key}" name="custom_gateway_parameter[meta_data][${key}]" placeholder="Enter ${filterKey}" required>`);
                    $div.append($label, $input);
                } else {

                    const $select = $(`<select class="form-select select2-search" data-placeholder="{{ translate("Select An Option") }}" data-show="5" name="custom_gateway_parameter[meta_data][${key}]" id="${key}"></select>`);
                    $.each(v, function(name, method) {
                        $select.append(`<option value="${method}">${name}</option>`);
                    });
                    $div.append($label, $select);
                }
                select2_search($('.select2-search').data('placeholder'));
                $newDataAdd.append($div);
            });
        }
        function renderOldFields() {

            $newDataAdd.empty();
            $.each(oldInfo, function(key, value) {

                const filterKey = key.replace("_", " ");
                const $div      = $('<div class="mb-4 col-lg-6"></div>');
                const $label    = $(`<label for="${key}" class="form-label text-capitalize">${filterKey}<sup class="text--danger">*</sup></label>`);
                const $input    = $(`<input type="text" class="form-control" id="${key}" value="${value}" name="custom_gateway_parameter[meta_data][${key}]" placeholder="Enter ${filterKey}" required>`);
                $div.append($label, $input);
                $newDataAdd.append($div);
            });
        }
        function updateCustomGatewayFields() {

            removeCustomGatewayFields();
            if ($gatewaySelect.val() === '-2' && Object.keys(customGatewayData).length > 0) {
                $.each(customGatewayData, function(key, value) {
                    $form.append(`<input type="hidden" class="custom-hidden-field" name="custom_gateway_parameter[${key}]" value="${value}">`);
                });
            }
        }
        function removeCustomGatewayFields() {
          
            $form.find('input.custom-hidden-field').remove();
        }
      });
  })(jQuery);
</script>

@endpush





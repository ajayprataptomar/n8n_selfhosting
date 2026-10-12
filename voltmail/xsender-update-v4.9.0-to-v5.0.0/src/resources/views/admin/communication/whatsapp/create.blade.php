@push("style-include")
  <link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
@endpush 
@extends('admin.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-fluid px-0 main-content">
      <div class="page-header">
        <div class="page-header-left">
          <h2>{{ translate("Send "). $title }}</h2>
          <div class="breadcrumb-wrapper">
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb">
                <li class="breadcrumb-item">
                  <a href="{{ route("admin.dashboard") }}">{{ translate("Dashboard") }}</a>
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
                    <a class="nav-link {{ old('active_tab', 'singleAudience') == 'singleAudience' ? 'active' : '' }}" data-bs-toggle="tab" href="#singleAudience" role="tab" aria-selected="true">
                      <i class="bi bi-person-fill"></i>{{ translate("Single audience") }} </a>
                  </li>
                  <li class="nav-item" role="presentation">
                    <a class="nav-link {{ old('active_tab', 'singleAudience') == 'groupAudience' ? 'active' : '' }}" data-bs-toggle="tab" href="#groupAudience" role="tab" aria-selected="false" tabindex="-1">
                      <i class="bi bi-people-fill"></i> {{ translate("Group audience") }} </a>
                  </li>
                  <li class="nav-item" role="presentation">
                    <a class="nav-link {{ old('active_tab', 'singleAudience') == 'importFile' ? 'active' : '' }}" data-bs-toggle="tab" href="#importFile" role="tab" aria-selected="false" tabindex="-1">
                      <i class="bi bi-file-earmark-plus-fill"></i> {{ translate("Import file") }} </a>
                  </li> 
                </ul>
              </div>
            </div>
          </div>
        </div>
        <div class="card-body pt-0">
          <form action="{{route('admin.communication.whatsapp.store', ['type' => 'whatsapp'])}}" method="POST" enctype="multipart/form-data" id="sms_send">
            @csrf
            <input type="hidden" name="active_tab" id="active_tab" value="{{ old('active_tab', 'singleAudience') }}">
            <div class="tab-content">
              <div class="tab-pane fade {{ old('active_tab', 'singleAudience') == 'singleAudience' ? 'show active' : '' }}" id="singleAudience" role="tabpanel">
                  <div class="form-element">
                      <div class="row gy-3">
                      <div class="col-xxl-2 col-xl-3">
                          <h5 class="form-element-title">{{ translate("Recipient Number") }}</h5>
                      </div>
                      <div class="col-xxl-8 col-lg-9">
                          <div class="form-inner">
                              <label for="whatsapp_contact" class="form-label">{{ translate("Phone number") }}<span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ translate("Provide the whatsapp contact number along with country code") }}">
                                  <i class="ri-question-line"></i>
                                  </span>
                              </label>
                              <input type="number" class="form-control" name="contacts" id="whatsapp_contact" placeholder="{{ translate("Enter recipient whatsapp contact number") }}" aria-label="whatsapp_contact" autocomplete="whatsapp_number" value="{{ old('active_tab') == 'singleAudience' ? old('contacts') : '' }}"/>
                          </div>
                      </div>
                      </div>
                  </div>
              </div>
              
              <div class="tab-pane fade {{ old('active_tab', 'singleAudience') == 'groupAudience' ? 'show active' : '' }}" id="groupAudience" role="tabpanel">
                <div class="form-element">
                  <div class="row gy-3">
                    <div class="col-xxl-2 col-xl-3">
                      <h5 class="form-element-title">{{ translate('From Group')}}</h5>
                      </div>
                        <div class="col-xxl-8 col-lg-9">
                          <div class="row gy-3 align-items-end">
                            <div class="col-12">
                              <div class="form-inner">
                                
                                <label for="contacts" class="form-label">{{ translate("Choose Group") }}<span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ translate("Only the contact groups with sms contact are available") }}">
                                    <i class="ri-question-line"></i>
                                    </span>
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
              <div class="tab-pane fade {{ old('active_tab', 'singleAudience') == 'importFile' ? 'show active' : '' }}" id="importFile" role="tabpanel">
                <div class="form-element">
                  <div class="row gy-3">
                    <div class="col-xxl-2 col-xl-3">
                      <h5 class="form-element-title">{{ translate("Import file") }}</h5>
                    </div>
                    <div class="col-xxl-8 col-lg-9">
                      <div class="form-inner">
                        <label for="file" class="form-label"> {{ translate("Import File") }}
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
            <div class="form-element">
              <div class="row gy-3">
                <div class="col-xxl-2 col-xl-3">
                  <h5 class="form-element-title">{{ translate("Schedule At") }}</h5>
                </div>
                <div class="col-xxl-8 col-lg-9">
                  <div class="form-inner">
                    <label for="ChooseGateway" class="form-label">{{ translate("Choose Date & Time") }}<span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ translate("You can select date and time for schedule operation. Or leave it empty to send normally") }}">
                      <i class="ri-question-line"></i>
                      </span>
                    </label>
                    <div class="input-group">
                      <input type="datetime-local" class="form-control singleDateTimePicker" placeholder="{{ translate("Select schedule time") }}" name="schedule_at" value="{{ old('schedule_at') }}"/>
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
                  <h5 class="form-element-title">{{ translate("Sending Method") }}</h5>
                </div>
                <div class="col-xxl-8 col-lg-9 whatsapp_device">
                  <div class="row gy-3 align-items-end">
                    <div class="col-12 whatsapp_sending_mode">
                      <div class="form-inner">
                        <label for="whatsapp_sending_mode" class="form-label">{{ translate("Choose Method") }}</label>
                        <select class="form-select select2-search" id="whatsapp_sending_mode" data-placeholder="{{ translate("Choose a sending method") }}" aria-label="whatsapp_sending_mode" name="method">
                          <option value=""></option>
                          <option value="without_cloud_api" {{ old('method', 'without_cloud_api') == 'without_cloud_api' ? 'selected' : '' }}>{{ translate('Without Cloud API') }}</option>
                          <option value="cloud_api" {{ old('method') == 'cloud_api' ? 'selected' : '' }}>{{ translate('Cloud API') }}</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-12 whatsapp_device_option d-none">
                      <div class="form-inner">
                        <label for="whatsapp_device_id" class="form-label">{{ translate("Choose A Node Device") }}</label>
                        <select class="form-select select2-search" id="whatsapp_device_id" data-placeholder="{{ translate("Choose a node device") }}" data-show="5" aria-label="whatsapp_device_id">
                          <option value=""></option>
                          <option selected value="0">{{ translate("Random Rotation") }}</option>
                          <option value="-1">{{ translate("Automatic") }}</option>
                          @foreach(\Illuminate\Support\Arr::get($gateways, "node", []) as $device_key => $device_name)
                            <option value="{{$device_key}}">{{($device_name)}}</option>
                          @endforeach
                        </select>
                      </div>
                    </div>
                    <div class="col-12 whatsapp_cloud_api_option d-none">
                      <div class="form-inner">
                        <label for="whatsapp_cloud_api_id" class="whatsapp_device_select_label form-label">{{ translate("Choose Cloud API Account") }}</label>
                        <select class="form-select select2-search repeat-scale whatsapp_business_api" data-placeholder="{{ translate("Choose a cloud api") }}" data-show="5" id="whatsapp_cloud_api_id">
                          <option value=""></option>
                          @foreach(\Illuminate\Support\Arr::get($gateways, "cloud", []) as $cloud_api_account_key => $cloud_api_account_name)
                            <option value="{{$cloud_api_account_key}}">{{($cloud_api_account_name)}}</option>
                          @endforeach
                        </select>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="form-element select-cloud-templates d-none">
              <div class="row gy-3">
                <div class="col-xxl-2 col-xl-3">
                  <h5 class="form-element-title">{{ translate("Select WhatsApp Templates") }}</h5>
                </div>
                <div class="col-xxl-8 col-lg-9 whatsapp_templates">
                  <div class="row gy-3 align-items-end">
                    <div class="col-12">
                      <div class="form-inner">
                        <label for="whatsapp_template_id" class="form-label">{{ translate("Choose Template") }}</label>
                        <select class="form-select select2-search repeat-scale whatsapp-template-id" data-placeholder="{{ translate("Choose a template for api") }}" data-show="5" name="whatsapp_template_id" id="whatsapp_template_id"></select>
                        </select>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
           
            <div class="form-element whatsapp_device_option d-none">
              <div class="row gy-3">
                <div class="col-xxl-2 col-xl-3">
                  <h5 class="form-element-title">{{ translate("Message body") }}</h5>
                </div>
                <div class="col-xxl-8 col-lg-9">
                  <div class="form-inner">
                    <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-2 mb-2">
                      <label for="message" class="form-label mb-0">{{ translate("Write message") }} </label>
                      <button class="i-btn btn--sm p-0 bg-transparent text-primary available-whatsapp-template" type="button">
                        <i class="ri-layout-fill fs-5"></i>{{ translate("Use Template") }}
                      </button>
                    </div>
                    <div class="ig-text-editor speech-to-text without-cloud-message d-none" id="messageBox">
                      <input type="text" name="without_cloud_api" hidden>
                      <textarea class="form-control" name="message[message_body]" id="message" rows="5">{{ old('message.message_body') }}</textarea>
                      <div class="text-editor">
                        <ul class="editor-actions">
                          <li class="action-item">
                            <button class="style-link" data-style="bold" type="button">
                              <i class="ri-bold"></i>
                              <span class="tooltiptext">{{ translate("Bold") }} </span>
                            </button>
                          </li>
                          <li class="action-item">
                            <button class="style-link" data-style="italic" type="button">
                              <i class="ri-italic"></i>
                              <span class="tooltiptext">{{ translate("Italic") }} </span>
                            </button>
                          </li>
                          <li class="action-item">
                            <button class="style-link" data-style="mono" type="button">
                              <i class="ri-space"></i>
                              <span class="tooltiptext">{{ translate("Monospace") }} </span>
                            </button>
                          </li>
                          <li class="action-item">
                            <button class="style-link" data-style="strike" type="button">
                              <i class="ri-format-clear"></i>
                              <span class="tooltiptext">{{ translate("Strikethrough") }} </span>
                            </button>
                          </li>
                        </ul>
                        <ul class="editor-actions">
                          <li class="action-item">
                            <button type="button">
                              <label for="media_upload" class="media_upload_label cursor-pointer">
                                <i class="ri-image-add-fill"></i>
                                <div id="uploadfile">
                                    <input type="file" id="media_upload" hidden>
                                </div>
                              </label>
                              <span class="tooltiptext">{{ translate("Media") }} </span>
                             
                            </button>
                          </li>
                         
                          <li class="action-item">
                            <button id="text-to-speech-icon" type="button">
                              <i class="ri-mic-line"></i>
                              <span class="tooltiptext">{{ translate("Speech-to-text") }} </span>
                            </button>
                          </li>
                        </ul>
                      </div>
                    </div>
                    @if(check_open_ai_image_availability())
                
                      <button type="button" class="ai-image-generator-btn mt-3 ai-image-modal-btn">
                          <div class="ai-image-icon">
                            <span class="spinner-border d-none" aria-hidden="true"></span>
                              <i class="ri-image-line"></i>
                          </div>
                          <span class="ai-image">{{translate('Generate AI Images')}}</span>
                      </button>
                    @endif
                    @if(check_open_ai_text_availability())
                      <button type="button" class="ai-text-generator-btn mt-3 ai-text-modal-btn">
                          <span class="ai-icon">
                              <span class="spinner-border d-none" aria-hidden="true"></span>
                              <i class="ri-bard-line"></i>
                          </span>
                          <span class="ai-text">
                              {{translate('Generate Text With AI')}}
                          </span>
                      </button>
                    @endif
                  </div>
                  <div class="file-preview mt-4 d-none" id="add_media"></div>

                  {{-- Multi-media attachments --}}
                  <div class="mt-3">
                      <label class="form-label d-flex align-items-center gap-2 cursor-pointer text-muted" onclick="document.getElementById('multi_media_input').click();">
                          <i class="ri-attachment-2"></i>
                          <small>{{ translate("Attach additional files (images, PDF, documents)") }}</small>
                      </label>
                      <input type="file" name="media_files[]" id="multi_media_input" multiple
                             accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.mp4,.mp3" class="d-none"
                             onchange="previewMultiMedia(this)">
                      <div id="multi_media_preview" class="d-flex flex-wrap gap-2 mt-2"></div>
                      <small class="text-muted d-none" id="multi_media_note">
                          <i class="ri-information-line"></i>
                          {{ translate("Each file will be sent as a separate message in sequence") }}
                      </small>
                  </div>
                </div>
              </div>
            </div>

            <div class="form-element whatsapp-cloud-steps d-none">
              <div class="row gy-3">
                <div class="col-xxl-2 col-xl-3">
                  <h5 class="form-element-title">{{ translate("Cloud API Template Data") }}</h5>
                </div>
                <div class="col-xxl-8 col-lg-9">
                  <div class="form-inner">
                    <input type="text" name="cloud_api" hidden>
                    <input type="text" name="cloud_api_data" hidden>
        
                    <div class="form-tab">
                        <ul class="nav whatsapp-tabs mb-3 gap-2" id="whatsapp_fields" role="tablist"></ul>
                    </div>
                    <div class="tab-content whatsapp-tab-content" id="whatsapp_fields"></div>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="row">
              <div class="col-xxl-10">
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
<div class="modal fade" id="availableWhatsappTemplate" tabindex="-1" aria-labelledby="availableWhatsappTemplate" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">{{ translate("WhatsApp Node Templates") }}</h5>
            <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                <i class="ri-close-large-line"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="row g-4">
              <div class="col-12">
                <div class="form-inner">
                  <label for="chooseWhatsappTemplate" class="form-label">{{ translate("Available Templates") }}</label>
                  <select class="form-select select2-search" id="chooseWhatsappTemplate" data-placeholder="{{ translate("Choose a WhatsApp template") }}" aria-label="chooseWhatsappTemplate">
                    <option value=""></option>
                    @foreach($templates as $template)
                      <option value="{{ json_encode($template->template_data) }}" data-name="{{ $template->name }}">{{ $template->name }}</option>
                    @endforeach
                  </select>
                </div>
              </div>

              {{-- Template Preview Section --}}
              <div class="col-12 whatsapp-template-preview d-none">
                <div class="card border">
                  <div class="card-header py-2">
                    <h6 class="mb-0"><i class="ri-eye-line me-1"></i>{{ translate("Template Preview") }}</h6>
                  </div>
                  <div class="card-body">
                    {{-- Media Preview --}}
                    <div class="template-media-preview mb-3 d-none">
                      <div class="alert alert-info py-2 d-flex align-items-center gap-2">
                        <i class="ri-image-line fs-5"></i>
                        <div>
                          <strong>{{ translate("Media Attached") }}</strong>
                          <p class="mb-0 small template-media-url text-truncate" style="max-width: 300px;"></p>
                        </div>
                      </div>
                    </div>

                    {{-- Message Preview --}}
                    <div class="template-message-preview p-3 rounded" style="background: #dcf8c6; max-width: 80%;">
                      <p class="mb-1 template-message-text" style="white-space: pre-wrap;"></p>
                      <small class="text-muted">{{ now()->format('h:i A') }}</small>
                    </div>

                    {{-- Buttons Preview --}}
                    <div class="template-buttons-preview mt-3 d-none">
                      <label class="form-label small text-muted mb-2">
                        <i class="ri-list-check me-1"></i>{{ translate("Quick Reply Options") }}
                      </label>
                      <div class="d-flex flex-wrap gap-2 template-buttons-list"></div>
                      <div class="alert alert-warning py-2 mt-2">
                        <small><i class="ri-information-line me-1"></i>{{ translate("Options will be sent as numbered text choices that recipients can reply to.") }}</small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="i-btn btn--danger outline btn--md" data-bs-dismiss="modal">{{ translate("Close") }}</button>
            <button type="button" id="saveWhatsappTemplateButton" class="i-btn btn--primary btn--md" disabled>{{ translate("Apply Template") }}</button>
        </div>
      </div>
  </div>
</div>
@endsection

@push("script-include")
  <script src="{{asset('assets/theme/global/js/select2.min.js')}}"></script>
  <script src="{{asset('assets/theme/global/js/whatsapp/whatsapp.js')}}"></script> 
  <script src="{{asset('assets/theme/global/js/whatsapp/stage-step.js')}}"></script> 
@endpush
@push('script-push')
<script>
	"use strict";
    select2_search($('.select2-search').data('placeholder'));

    // Global variable to store loaded templates
    var allTemplates = [];

    $(document).ready(function() {
      
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

                  return `<div class="row"><div title="Only the contacts with this date in the '${attribute.split("::")[0]}' attribute will be selected for this Campaign" class="col-md-6"><label for="attribute_name" class="form-label">{{ translate("Select a Date") }}<sup class="text-danger">*</sup></label>
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
                url: '{{ route("admin.contact.group.fetch", ["type" => "meta_data"]) }}',
                type: 'POST',
                data: {
                    group_ids: selectedValues,
                    channel: "whatsapp",
                },
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                },
                success: function(response) {

                    if (response.status == true) {

                        $('.group-logic').removeClass("d-none");
                        $('.group-logic-items').removeClass("d-none");
                        $('input[id=logic]').attr('name', 'logic');
                        $('input[id=group_logic]').attr('name', 'group_logic');
                        $('select[id=attribute_name]').attr('name', 'attribute_name');
                        $('select[name=attribute_name]').prop('disabled', false);
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


      // Track active tab in hidden input
      $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
          $('#active_tab').val($(e.target).attr('href').replace('#', ''));
      });

      $('#sms_send').on('submit', function(event) {

          var activeTabId = $('#active_tab').val() || $('.tab-content .tab-pane.active').attr('id');
          $('#active_tab').val(activeTabId);

          // Clear data from inactive tabs
          if (activeTabId !== 'singleAudience') {
              $('#singleAudience input').not('#active_tab').val('');
          }
          if (activeTabId !== 'groupAudience') {
              $('#groupAudience input').val('');
          }
          if (activeTabId !== 'importFile') {
              $('#importFile input').val('');
          }
      });

      $(document).ready(function() {
        var $templateSelect = $('#whatsapp_template_id');
        var $cloudTemplatesDiv = $('.select-cloud-templates');
        var $whatsappCloudApiOption = $('.whatsapp_cloud_api_option');

        function updateTemplateOptions(cloudId) {
          $templateSelect.empty();
          $cloudTemplatesDiv.addClass('d-none');

          if (cloudId !== '-1') {
            $.ajax({
              url: "{{ route('admin.template.fetch', ['channel' => 'whatsapp']) }}",
              type: 'GET',
              data: { cloud_id: cloudId },
              success: function(response) {
                if (response.templates.length > 0) {
                  $templateSelect.append('<option value="" selected disabled>Select A Template</option>');
                  $.each(response.templates, function(index, template) {
                    
                    $templateSelect.append('<option value="' + template.id + '">' + template.name + '(' + template.template_data.language + ')' + '</option>');
                  });
                  $cloudTemplatesDiv.removeClass('d-none');
                  allTemplates = response.templates;
                } else {
                  $templateSelect.append('<option value="" selected disabled>No templates available</option>');
                  allTemplates = [];
                }
              },
              error: function(xhr, status, error) {
                console.error(xhr.responseText);
              }
            });
          }
        }

        $('#whatsapp_sending_mode').on('change', function() {
          var selectedValue = $(this).val();

          if (selectedValue === 'without_cloud_api') {
            $whatsappCloudApiOption.addClass('d-none');
          } else if (selectedValue === 'cloud_api') {
            $whatsappCloudApiOption.removeClass('d-none');
          }
        });

        $('#whatsapp_cloud_api_id').on('change', function() {
          var cloudId = $(this).val();
          updateTemplateOptions(cloudId);
        });

        // Handle template selection to show template fields
        $('#whatsapp_template_id').on('change', function() {
          var templateId = $(this).val();
          buildTemplateFields(templateId);
        });

        // Build template input fields for Cloud API
        function buildTemplateFields(templateId) {
          var $cloudSteps = $('.whatsapp-cloud-steps');
          var $tabsNav = $cloudSteps.find('.whatsapp-tabs');
          var $tabContent = $cloudSteps.find('.whatsapp-tab-content');

          // Clear existing content
          $tabsNav.empty();
          $tabContent.empty();
          $cloudSteps.addClass('d-none');

          if (!templateId || !allTemplates || allTemplates.length === 0) {
            return;
          }

          // Find the selected template
          var template = allTemplates.find(function(t) {
            return t.id == templateId;
          });

          if (!template || !template.template_data) {
            return;
          }

          var templateData = template.template_data;
          var components = templateData.components || [];

          if (components.length === 0) {
            // Template has no variable components - show simple confirmation
            $cloudSteps.removeClass('d-none');
            $tabContent.html('<div class="alert alert-info">{{ translate("This template has no variable fields. Click Send to dispatch the message.") }}</div>');
            // Set hidden input values
            $('input[name="cloud_api"]').val('true');
            $('input[name="cloud_api_data"]').val(JSON.stringify([]));
            return;
          }

          // Build tabs and content for each component
          var componentIndex = 0;
          var componentData = [];

          components.forEach(function(component, index) {
            var type = component.type;
            var hasVariables = false;

            // Check if component has variables (example params or text with variables like 1, 2, etc)
            if (component.example && component.example.body_text) {
              hasVariables = true;
            }
            if (component.text && component.text.match(/\{\{\d+\}\}/)) {
              hasVariables = true;
            }
            if (type === 'HEADER' && component.format === 'IMAGE') {
              hasVariables = true;
            }
            if (type === 'HEADER' && component.format === 'DOCUMENT') {
              hasVariables = true;
            }
            if (type === 'HEADER' && component.format === 'VIDEO') {
              hasVariables = true;
            }

            if (hasVariables || type === 'BODY') {
              var tabId = 'tab_' + type.toLowerCase() + '_' + index;
              var isActive = componentIndex === 0 ? 'active' : '';
              var isShow = componentIndex === 0 ? 'show active' : '';

              // Add tab navigation
              $tabsNav.append(
                '<li class="nav-item" role="presentation">' +
                  '<button class="nav-link ' + isActive + '" id="' + tabId + '-tab" data-bs-toggle="tab" data-bs-target="#' + tabId + '" type="button" role="tab">' +
                    type +
                  '</button>' +
                '</li>'
              );

              // Build content based on component type
              var contentHtml = '<div class="tab-pane fade ' + isShow + '" id="' + tabId + '" role="tabpanel">';
              contentHtml += '<div class="row g-3">';

              if (type === 'HEADER') {
                if (component.format === 'IMAGE' || component.format === 'VIDEO' || component.format === 'DOCUMENT') {
                  contentHtml += '<div class="col-12">';
                  contentHtml += '<label class="form-label">{{ translate("Media URL") }} (' + component.format + ')</label>';
                  contentHtml += '<input type="url" class="form-control template-var" name="template_header_url" placeholder="{{ translate("Enter media URL") }}" data-component="HEADER" data-type="' + component.format + '">';
                  contentHtml += '</div>';
                } else if (component.format === 'TEXT' && component.text && component.text.match(/\{\{\d+\}\}/)) {
                  var matches = component.text.match(/\{\{(\d+)\}\}/g) || [];
                  matches.forEach(function(match, i) {
                    var varNum = match.replace(/[{}]/g, '');
                    contentHtml += '<div class="col-md-6">';
                    contentHtml += '<label class="form-label">{{ translate("Header Variable") }} ' + varNum + '</label>';
                    contentHtml += '<input type="text" class="form-control template-var" name="template_header_var_' + varNum + '" placeholder="{{ translate("Enter value for variable") }} ' + varNum + '" data-component="HEADER" data-var="' + varNum + '">';
                    contentHtml += '</div>';
                  });
                }
              } else if (type === 'BODY') {
                var bodyText = component.text || '';
                var bodyMatches = bodyText.match(/\{\{(\d+)\}\}/g) || [];

                if (bodyMatches.length > 0) {
                  // Display template body with highlighted variables
                  var displayText = bodyText.replace(/\{\{(\d+)\}\}/g, function(match, num) {
                    return '<span class="badge bg-primary">' + match + '</span>';
                  });
                  contentHtml += '<div class="col-12"><p class="text-muted small mb-2">' + displayText + '</p></div>';
                  bodyMatches.forEach(function(match, i) {
                    var varNum = match.replace(/[{}]/g, '');
                    contentHtml += '<div class="col-md-6">';
                    contentHtml += '<label class="form-label">{{ translate("Body Variable") }} ' + varNum + '</label>';
                    contentHtml += '<input type="text" class="form-control template-var" name="template_body_var_' + varNum + '" placeholder="{{ translate("Enter value for variable") }} ' + varNum + '" data-component="BODY" data-var="' + varNum + '">';
                    contentHtml += '</div>';
                  });
                } else {
                  contentHtml += '<div class="col-12"><p class="text-muted">{{ translate("No variables in body") }}</p></div>';
                }
              }

              contentHtml += '</div></div>';
              $tabContent.append(contentHtml);
              componentIndex++;
            }
          });

          if (componentIndex > 0) {
            $cloudSteps.removeClass('d-none');
            // Set hidden input to indicate cloud API
            $('input[name="cloud_api"]').val('true');
          }

          // Add listener to collect variable values
          $('.template-var').on('input', function() {
            collectTemplateData();
          });
        }

        // Collect template variable data for submission
        function collectTemplateData() {
          var components = [];
          var headerParams = [];
          var bodyParams = [];

          $('.template-var').each(function() {
            var $input = $(this);
            var componentType = $input.data('component');
            var value = $input.val();

            if (componentType === 'HEADER') {
              var mediaType = $input.data('type');
              if (mediaType) {
                // Media header (IMAGE, VIDEO, DOCUMENT)
                if (value) {
                  var headerComponent = {
                    type: 'header',
                    parameters: [{
                      type: mediaType.toLowerCase(),
                    }]
                  };
                  headerComponent.parameters[0][mediaType.toLowerCase()] = { link: value };
                  components.push(headerComponent);
                }
              } else {
                // Text header variable
                if (value) {
                  headerParams.push({ type: 'text', text: value });
                }
              }
            } else if (componentType === 'BODY') {
              if (value) {
                bodyParams.push({ type: 'text', text: value });
              }
            }
          });

          // Add header component if has text params
          if (headerParams.length > 0) {
            components.push({
              type: 'header',
              parameters: headerParams
            });
          }

          // Add body component if has params
          if (bodyParams.length > 0) {
            components.push({
              type: 'body',
              parameters: bodyParams
            });
          }

          $('input[name="cloud_api_data"]').val(JSON.stringify(components));
        }
      });

      $(document).ready(function() {
          function appendGatewayIdInput(value) {
              $('input[name="gateway_id"]').remove();
              var $gatewayIdInput = $('<input>').attr({
                  type: 'hidden',
                  name: 'gateway_id',
                  value: value || '-1'
              });
              $('form').append($gatewayIdInput);
          }

          function showHideOptions(selectedValue) {
              // Hide all option sections first
              $('.whatsapp_device_option, .whatsapp_cloud_api_option').addClass('d-none');
              $('.select-cloud-templates, .whatsapp-cloud-steps').addClass('d-none');
              $('.without-cloud-message').addClass('d-none');

              if (selectedValue === 'without_cloud_api') {
                  // Show Node device options and message input
                  $('.whatsapp_device_option').removeClass('d-none');
                  $('.without-cloud-message').removeClass('d-none');
                  appendGatewayIdInput($('#whatsapp_device_id').val());
              } else if (selectedValue === 'cloud_api') {
                  // Show Cloud API options (template will show after selecting account)
                  $('.whatsapp_cloud_api_option').removeClass('d-none');
                  appendGatewayIdInput($('#whatsapp_cloud_api_id').val());
              } else {
                  appendGatewayIdInput('-1');
              }
          }

          function handleDeviceIdChange() {
              appendGatewayIdInput($('#whatsapp_device_id').val());
          }

          function handleCloudApiIdChange() {
              appendGatewayIdInput($('#whatsapp_cloud_api_id').val());
          }

          $('#whatsapp_sending_mode').on('change', function() {
              var selectedValue = $(this).val();
              showHideOptions(selectedValue);
          }).trigger('change');

          $('#whatsapp_device_id').on('change', handleDeviceIdChange);

          $('#whatsapp_cloud_api_id').on('change', handleCloudApiIdChange);

          $('form').on('submit', function(event) {
              var currentGatewayId = $('input[name="gateway_id"]').val();
          });
      });

      $('.available-template').on('click', function() {

const modal = $('#availableTemplate');
modal.modal('show');

$('#chooseTemplate').change(function() {

    var selectedTemplate = $(this).val();
    
    if (selectedTemplate) {

        var templateData = JSON.parse(selectedTemplate);
        var templateMessage = templateData.message;
        $('.template-data').empty();
        var templateTextArea = $('<textarea>', {

            rows: '5',
            readonly: true,
            class: 'form-control',
            id: 'sms_template_message',
            required: ''
        }).val(templateMessage);

        $('.template-data').append('<div class="col-lg-12"><div class="form-inner"><label for="sms_template_add_message" class="form-label">{{translate('Template Body')}}<span class="text-danger">*</span></label></div></div>').find('.form-inner').append(templateTextArea);
    } else {

        $('.template-data').empty();
    }
});
});

var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
var speechElements = document.querySelectorAll(".speech-to-text");
var selectedTemplateMessage = '';
$('#chooseTemplate').change(function() {

  var selectedTemplate = $(this).val();
  if (selectedTemplate) {

      var templateData = JSON.parse(selectedTemplate);
      $('#sms_template_message').val(templateData.message);
      selectedTemplateMessage = templateData.message;
  } else {

      $('#sms_template_message').val('');
      selectedTemplateMessage = '';
  }
});
$('#saveTemplateButton').click(function() {

  var mainTextArea = $('#message');
  insertAtCursor(mainTextArea[0], selectedTemplateMessage);
  $('#availableTemplate').modal('hide');
});
function insertAtCursor(textArea, text) {

  if (document.selection) {
  
      textArea.focus();
      var sel = document.selection.createRange();
      sel.text = text;
  } else if (textArea.selectionStart || textArea.selectionStart === 0) {
  
      var startPos = textArea.selectionStart;
      var endPos = textArea.selectionEnd;
      var scrollTop = textArea.scrollTop;
      textArea.value = textArea.value.substring(0, startPos) + text + textArea.value.substring(endPos, textArea.value.length);
      textArea.focus();
      textArea.selectionStart = startPos + text.length;
      textArea.selectionEnd = startPos + text.length;
      textArea.scrollTop = scrollTop;
  } else {

      textArea.value += text;
      textArea.focus();
  }
}
if (SpeechRecognition && speechElements.length > 0) {

  var recognition = new SpeechRecognition();
  var isListening = false;

  $('#text-to-speech-icon').on('click', function() {

      var messageBox = document.getElementById('messageBox');
      var textArea = messageBox.querySelector(".form-control");
      textArea.focus();

      recognition.onspeechstart = function() {

          isListening = true;
      };

      if (!isListening) {

          recognition.start();
      }

      recognition.onerror = function() {

          isListening = false;
      };

      recognition.onresult = function(event) {

          var currentText = textArea.value;
          var newText = event.results[0][0].transcript;
          textArea.value = currentText + " " + newText;
      };

      recognition.onspeechend = function() {
          isListening = false;
          recognition.stop();
      };
  });
}

      // WhatsApp Node Template functionality - Professional Implementation
      var currentTemplateData = null;

      $('.available-whatsapp-template').on('click', function() {
        const modal = $('#availableWhatsappTemplate');
        // Reset modal state
        $('#chooseWhatsappTemplate').val('').trigger('change.select2');
        resetTemplatePreview();
        modal.modal('show');
      });

      // Handle template selection and show preview
      $('#chooseWhatsappTemplate').on('change', function() {
        var selectedValue = $(this).val();
        resetTemplatePreview();

        if (selectedValue) {
          try {
            currentTemplateData = JSON.parse(selectedValue);
            showTemplatePreview(currentTemplateData);
            $('#saveWhatsappTemplateButton').prop('disabled', false);
          } catch (e) {
            console.error('Error parsing template data:', e);
            currentTemplateData = null;
            $('#saveWhatsappTemplateButton').prop('disabled', true);
          }
        } else {
          currentTemplateData = null;
          $('#saveWhatsappTemplateButton').prop('disabled', true);
        }
      });

      function resetTemplatePreview() {
        $('.whatsapp-template-preview').addClass('d-none');
        $('.template-media-preview').addClass('d-none');
        $('.template-buttons-preview').addClass('d-none');
        $('.template-message-text').text('');
        $('.template-media-url').text('');
        $('.template-buttons-list').empty();
      }

      function showTemplatePreview(templateData) {
        if (!templateData) return;

        // Show preview section
        $('.whatsapp-template-preview').removeClass('d-none');

        // Show message
        if (templateData.message) {
          $('.template-message-text').text(templateData.message);
        }

        // Show media if exists
        if (templateData.image_url) {
          $('.template-media-preview').removeClass('d-none');
          $('.template-media-url').text(templateData.image_url);
        }

        // Show buttons if exist
        if (templateData.buttons && templateData.buttons.length > 0) {
          $('.template-buttons-preview').removeClass('d-none');
          var buttonsList = $('.template-buttons-list');
          buttonsList.empty();

          templateData.buttons.forEach(function(button, index) {
            var buttonText = typeof button === 'object' ? (button.title || button.text || button) : button;
            buttonsList.append(
              '<span class="badge bg-primary rounded-pill px-3 py-2">' +
                '<i class="ri-reply-line me-1"></i>' + (index + 1) + '. ' + buttonText +
              '</span>'
            );
          });
        }
      }

      // Apply template to message form
      $('#saveWhatsappTemplateButton').on('click', function() {
        if (!currentTemplateData) {
          notify('error', '{{ translate("Please select a template first") }}');
          return;
        }

        // Build the full message with options if buttons exist
        var fullMessage = currentTemplateData.message || '';

        if (currentTemplateData.buttons && currentTemplateData.buttons.length > 0) {
          fullMessage += '\n\n{{ translate("Reply with number to select:") }}';
          currentTemplateData.buttons.forEach(function(button, index) {
            var buttonText = typeof button === 'object' ? (button.title || button.text || button) : button;
            fullMessage += '\n' + (index + 1) + '. ' + buttonText;
          });
        }

        // Set message in textarea
        var mainTextArea = $('#message');
        mainTextArea.val(fullMessage);

        // Handle media/image URL - add to media upload section or hidden input
        if (currentTemplateData.image_url) {
          // Add hidden input for media URL
          $('input[name="message[media_url]"]').remove();
          $('<input>').attr({
            type: 'hidden',
            name: 'message[media_url]',
            value: currentTemplateData.image_url
          }).appendTo('#sms_send');

          // Show visual indicator of attached media
          var mediaPreview = $('#add_media');
          mediaPreview.removeClass('d-none');
          mediaPreview.html(
            '<div class="alert alert-success d-flex align-items-center gap-2 py-2">' +
              '<i class="ri-image-line fs-4"></i>' +
              '<div>' +
                '<strong>{{ translate("Media from template") }}</strong>' +
                '<p class="mb-0 small text-truncate" style="max-width: 300px;">' + currentTemplateData.image_url + '</p>' +
              '</div>' +
              '<button type="button" class="btn-close ms-auto" onclick="removeTemplateMedia()"></button>' +
            '</div>'
          );
        }

        // Store buttons data for backend processing
        if (currentTemplateData.buttons && currentTemplateData.buttons.length > 0) {
          $('input[name="message[buttons]"]').remove();
          $('<input>').attr({
            type: 'hidden',
            name: 'message[buttons]',
            value: JSON.stringify(currentTemplateData.buttons)
          }).appendTo('#sms_send');
        }

        // Close modal and show success
        $('#availableWhatsappTemplate').modal('hide');
        notify('success', '{{ translate("Template applied successfully") }}');
      });

      // Function to remove template media
      window.removeTemplateMedia = function() {
        $('input[name="message[media_url]"]').remove();
        $('#add_media').addClass('d-none').empty();
      };

    });

    // Multi-media preview with drag-reorder + remove (parity with user-side builder).
    let multiMediaFiles = [];

    function previewMultiMedia(input) {
        multiMediaFiles = Array.from(input.files || []);
        renderMultiMediaPreview();
    }

    function renderMultiMediaPreview() {
        const container = document.getElementById('multi_media_preview');
        const note      = document.getElementById('multi_media_note');
        const input     = document.getElementById('multi_media_input');
        container.innerHTML = '';

        if (multiMediaFiles.length === 0) {
            note.classList.add('d-none');
            try { input.value = ''; } catch (e) {}
            return;
        }
        note.classList.remove('d-none');

        multiMediaFiles.forEach((file, index) => {
            const isImage = file.type.startsWith('image/');
            const badge   = document.createElement('div');
            badge.className     = 'multi-media-badge border rounded px-2 py-1 d-flex align-items-center gap-2 small';
            badge.style.cssText = 'background:#f8f9fa;cursor:grab;user-select:none;';
            badge.draggable     = true;
            badge.dataset.index = String(index);

            const handle = document.createElement('i');
            handle.className = 'ri-drag-move-line text-muted';
            handle.title     = '{{ translate("Drag to reorder") }}';
            badge.appendChild(handle);

            const order = document.createElement('span');
            order.className   = 'badge bg-secondary';
            order.textContent = (index + 1);
            badge.appendChild(order);

            if (isImage) {
                const img = document.createElement('img');
                img.style.cssText = 'width:28px;height:28px;object-fit:cover;border-radius:4px;';
                const reader = new FileReader();
                reader.onload = (function (imgEl) { return function (e) { imgEl.src = e.target.result; }; })(img);
                reader.readAsDataURL(file);
                badge.appendChild(img);
            } else {
                const icon = document.createElement('i');
                icon.className = file.type.includes('pdf')   ? 'ri-file-pdf-line text-danger' :
                                 file.type.includes('video') ? 'ri-video-line text-primary'  :
                                 file.type.includes('audio') ? 'ri-music-line text-success'  :
                                                               'ri-file-line text-secondary';
                badge.appendChild(icon);
            }

            const name = document.createElement('span');
            name.className     = 'text-truncate';
            name.style.maxWidth = '140px';
            name.textContent   = file.name;
            badge.appendChild(name);

            const size = document.createElement('span');
            size.className   = 'text-muted';
            size.textContent = '(' + (file.size / 1024 / 1024).toFixed(1) + 'MB)';
            badge.appendChild(size);

            const removeBtn = document.createElement('button');
            removeBtn.type      = 'button';
            removeBtn.className = 'btn-close btn-sm';
            removeBtn.style.cssText = 'font-size:9px;margin-left:4px;';
            removeBtn.setAttribute('aria-label', '{{ translate("Remove file") }}');
            removeBtn.onclick = function (e) {
                e.stopPropagation();
                multiMediaFiles.splice(index, 1);
                syncMultiMediaInput();
                renderMultiMediaPreview();
            };
            badge.appendChild(removeBtn);

            wireMultiMediaDnd(badge);
            container.appendChild(badge);
        });

        syncMultiMediaInput();
    }

    function syncMultiMediaInput() {
        const input = document.getElementById('multi_media_input');
        try {
            const dt = new DataTransfer();
            multiMediaFiles.forEach(f => dt.items.add(f));
            input.files = dt.files;
        } catch (e) { /* older browser */ }
    }

    function wireMultiMediaDnd(badge) {
        badge.addEventListener('dragstart', function (e) {
            this.style.opacity = '0.45';
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', this.dataset.index);
        });
        badge.addEventListener('dragend',  function () { this.style.opacity = ''; });
        badge.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.style.outline = '2px dashed #6366f1';
        });
        badge.addEventListener('dragleave', function () { this.style.outline = ''; });
        badge.addEventListener('drop', function (e) {
            e.preventDefault();
            this.style.outline = '';
            const fromIdx = parseInt(e.dataTransfer.getData('text/plain'), 10);
            const toIdx   = parseInt(this.dataset.index, 10);
            if (isNaN(fromIdx) || isNaN(toIdx) || fromIdx === toIdx) return;
            const [moved] = multiMediaFiles.splice(fromIdx, 1);
            multiMediaFiles.splice(toIdx, 0, moved);
            renderMultiMediaPreview();
        });
    }
</script>
@endpush

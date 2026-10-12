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

        <div class="card">
            <div class="card-body pt-0">
                <form action="{{ route("admin.template.update", ["uid" => $template->uid]) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="_method" value="PATCH">
                    <input type="text" name="channel" value="{{ $channel->value }}" hidden>
                    <div class="form-element">
                        <div class="row gy-4">
                            <div class="col-xxl-2 col-xl-3">
                                <h5 class="form-element-title">{{ translate("Template Basic Information") }}</h5>
                                </div>
                                <div class="col-xxl-8 col-xl-9">
                                <div class="row gy-4">
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="admin_email_template_name" class="form-label"> {{ translate("Template Name") }} <small class="text-danger">*</small></label>
                                            <div class="input-group">
                                                <input type="text" required name="name" id="admin_email_template_name" class="form-control" placeholder="{{ translate('Write Template Name')}}" value="{{ $template->name }}">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-inner">
                                            <label for="admin_email_template_provider" class="form-label">{{ translate("Select Provider") }}</label>
                                            <select data-placeholder="{{ translate('Select A Provider') }}" class="form-select select2-search" data-show="5" id="admin_email_template_provider" name="provider">
                                                <option value=""></option>
                                                @php $available_providers = \App\Enums\System\TemplateProviderEnum::getValues() @endphp
                                                @foreach(array_slice($available_providers, 1, null, true) as $provider_value)
                                                    @if($template->provider->value == \App\Enums\System\TemplateProviderEnum::CK_EDITOR->value && $provider_value != \App\Enums\System\TemplateProviderEnum::BEE_FREE->value)
                                                        <option {{ $template->provider->value == $provider_value ? 'selected' : ''}} value="{{ $provider_value }}">{{ textFormat(['_'], $provider_value, ' ') }}</option>
                                                    @endif
                                                    @if($template->provider->value == \App\Enums\System\TemplateProviderEnum::BEE_FREE->value)
                                                        <option {{ $template->provider->value == $provider_value ? 'selected' : ''}} value="{{ $provider_value }}">{{ textFormat(['_'], $provider_value, ' ') }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-12 d-none bee-free">
                                        <div class="form-inner">
                                            <label for="choose-template" class="form-label">{{ translate("Select A Different Template") }}</label>
                                            <select aria-label="{{translate('Select Template')}}" class="form-control"  id="choose-template">
                                                <option value="">-- {{ translate('Select Template') }}</option>
                                                @foreach($pluginTemplates as $plugin_template)
                                                    <option value="{{ $plugin_template->uid }}">
                                                       {{ $plugin_template->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="bee_template_json" name="template_json">
                    <input type="hidden" id="bee_template_html" name="template_html">
                    <div class="form-element d-none" id="text-editor">
                        <div class="row gy-4">
                            <div class="col-xxl-2 col-xl-3">
                                <h5 class="form-element-title">{{ translate("Template Body") }}</h5>
                                </div>
                                <div class="col-xxl-8 col-xl-9">
                                <div class="row gy-4">
                                    <div class="col-md-12">
                                        <div class="form-inner">
                                          <label for="admin_email_template_body" class="form-label"> {{ translate("Mail Body") }} </label>
                                          <textarea class="form-control" name="template_data[mail_body]" id="admin_email_template_body" rows="2" placeholder="{{ translate('Enter mail body') }}" aria-label="{{ translate('Enter mail body') }}">{{ $template->template_data["mail_body"] }}</textarea>
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
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($template->provider->value == \App\Enums\System\TemplateProviderEnum::BEE_FREE->value)
                        <div class="bee-plugin-preview mt-5">
                            <div id="bee-plugin-container" class="h-100">
                            </div>

                            <div class="mt-3 d-flex align-items-center">
                                <div id="html-image-data">
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-xxl-10">
                            {{-- @if($template->provider == \App\Enums\System\TemplateProviderEnum::BEE_FREE->value )
                                <div class="form-action justify-content-end">
                                    <span class="" id="preview">
                                        <button type="button" id="edit-template" class="i-btn btn--info btn--md">{{ translate('Edit template') }}</button>
                                    </span>
                                </div>
                            @endif --}}
                            <div class="form-action justify-content-end">
                                <button type="submit" id="save-button" class="i-btn btn--primary btn--md {{ $template->provider->value == \App\Enums\System\TemplateProviderEnum::BEE_FREE->value ? 'd-none' : '' }} "> {{ translate("Save") }} </button>
                            </div>
                           
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
@endsection

@push('script-include')
    <script src="{{asset('assets/theme/global/js/select2.min.js')}}"></script>
    <script src="{{asset('assets/theme/global/js/template.js') }}"></script>
    <script src="{{asset('assets/theme/global/js/BeePlugin.js') }}"></script>
@endpush


@push('script-push')
<script>
	(function($){
		"use strict";

        select2_search($('.select2-search').data('placeholder'));
        ck_editor("#admin_email_template_body");

        if("{{ $template->provider->value }}" == "{{ \App\Enums\System\TemplateProviderEnum::BEE_FREE->value }}") {

            $('.bee-free').removeClass('d-none')
            $('#preview').removeClass('d-none')
            $('#text-editor').addClass('d-none')
            loadTemplate("{{ $template->uid }}")
        }

        if("{{ $template->provider->value }}" == "{{ \App\Enums\System\TemplateProviderEnum::CK_EDITOR->value }}") {

            $('.bee-free').addClass('d-none')
            $('#preview').addClass('d-none')
            $('.save-button').removeClass('d-none')
            $('#text-editor').removeClass('d-none')
        }
        $(document).on('change','#admin_email_template_provider',function(e){
            if($(this).val() == "{{ \App\Enums\System\TemplateProviderEnum::BEE_FREE->value }}"){
                $('.bee-free').removeClass('d-none')
                $('#preview').removeClass('d-none')
                $('#text-editor').addClass('d-none')
                $('.bee-plugin-preview').removeClass('d-none')
                loadTemplate("{{ $template->uid }}")
            }
            else if($(this).val() == "{{ \App\Enums\System\TemplateProviderEnum::CK_EDITOR->value }}"){
                $('.bee-free').addClass('d-none')
                $('#preview').addClass('d-none')
                $('.save-button').removeClass('d-none')
                $('#text-editor').removeClass('d-none')
                $('#save-button').removeClass('d-none')
                $('.bee-plugin-preview').addClass('d-none')
            }
        });

        function loadTemplate(templateId = null) {

            let baseUrl = $("meta[name=base-url]").attr("content");
            $("#bee-plugin-container").html("");
            $("#preview").hide(200);
            var bee;
            var endpoint = $("meta[name=bee-endpoint]").attr("content");
            var config = {
                uid: "demo_id_1",
                container: "bee-plugin-container",
                onSave: function (jsonFile, htmlFile) {
                    $("#bee_template_json").val(jsonFile);
                    $("#bee_template_html").val(htmlFile);
                   
                    $(".bee-plugin").hide();
                    $("#template-editor").hide();
                    $(".bee-plugin-preview").addClass('d-none');
                    $("#save-button").removeClass('d-none');
                },
                onAutoSave: function (jsonFile, htmlFile) {
                    $("#bee_template_json").val(jsonFile);
                    $("#bee_template_html").val(htmlFile);
                },
                onSaveAsTemplate: function (jsonFile) {
                    saveAs(
                        new Blob([jsonFile], {
                            type: "text/plain;charset=utf-8",
                        }),
                        "test.json"
                    );
                },
                onSend: function (htmlFile) {

                },
            };
            var payload = {
                client_id:  $("meta[name=bee-client-id]").attr("content"),
                client_secret: $("meta[name=bee-client-secret]").attr("content"),
                uid: config.uid || "xsender_user_1",
            };

            $.ajax({
                url: endpoint,
                type: "POST",
                contentType: "application/json",
                data: JSON.stringify(payload),
                dataType: "json",
            }).done(function (data) {
                var token = data;
                window.BeePlugin.create(token, config, function (instance) {
                    bee = instance;
                    $.get(
                        `${baseUrl}/admin/template/edit/json/${templateId}`,
                        function (template) {
                            bee.start(template);
                        }
                    );
                });
            }).fail(function (xhr) {
                console.error("Bee Free auth failed:", xhr.status, xhr.responseText);
                $("#bee-plugin-container").html(
                    '<div class="d-flex align-items-center justify-content-center h-100">' +
                    '<div class="text-center text-muted">' +
                    '<i class="ri-error-warning-line fs-1"></i>' +
                    '<p class="mt-2">Failed to load email editor. Please check your Bee Free credentials.</p>' +
                    '</div></div>'
                );
            });
        }
        //edit a  template
        $(document).on("click", "#edit-template", function () {


            $(".bee-plugin").show(200);
            $("#template-editor").show(200);

            $("#preview-title").hide();
            $("#html-image-data").html("");
        });
	})(jQuery);
</script>
@endpush


@extends('admin.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ textFormat(['_'], $title, ' ') }}</h2>
                <div class="breadcrumb-wrapper">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route("admin.dashboard") }}">{{ translate("Dashboard") }}</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page"> {{ textFormat(['_'], $title, ' ') }} </li>
                    </ol>
                </nav>
                </div>
            </div>
        </div>
        <div class="card">
        
            <div class="card-body pt-0">
                <form action="{{ route("admin.system.setting.store") }}" method="POST" enctype="multipart/form-data" class="settingsForm">
                    @csrf
                    <div class="form-element">
                        <div class="row gy-4">
                            <div class="col-xxl-2 col-xl-3">
                                <h5 class="form-element-title">{{ translate("AI Functions") }}</h5>
                                </div>
                                <div class="col-xxl-8 col-xl-9">
                                <div class="row gy-4">
                                    <div class="col-md-12">
                                        <div class="form-inner parent">
                                            <label class="form-label"> {{ translate("Allow AI Functionality") }} </label>
                                            <div class="form-inner-switch">
                                                <label class="pointer" for="ai_functions">{{ translate("Turn on/off AI functions") }}</label>
                                                <div class="switch-wrapper mb-1 checkbox-data">
                                                    <input {{ site_settings(\App\Enums\SettingKey::AI_FUNCTIONS->value, \App\Enums\StatusEnum::FALSE->status()) == \App\Enums\StatusEnum::TRUE->status() ? 'checked' : '' }} type="checkbox" class="switch-input" id="ai_functions" name="site_settings[{{ \App\Enums\SettingKey::AI_FUNCTIONS->value }}]"/>
                                                    <label for="ai_functions" class="toggle">
                                                    <span></span>
                                                    </label>
                                                </div>
                                            </div>
                                            <p class="form-element-note text-danger">{{ translate("Enables/disables AI functionalities") }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @php
                        $aiModelsRaw = json_decode(site_settings(\App\Enums\SettingKey::AI_MODELS->value), true) ?? [];
                        $providerPresets = [
                            'openai'    => ['label' => 'OpenAI', 'url' => '', 'models' => 'gpt-4o-mini, gpt-4o, gpt-3.5-turbo'],
                            'deepseek'  => ['label' => 'DeepSeek', 'url' => 'https://api.deepseek.com/v1', 'models' => 'deepseek-chat, deepseek-reasoner'],
                            'groq'      => ['label' => 'Groq', 'url' => 'https://api.groq.com/openai/v1', 'models' => 'llama-3.3-70b-versatile, mixtral-8x7b-32768'],
                            'together'  => ['label' => 'Together AI', 'url' => 'https://api.together.xyz/v1', 'models' => 'meta-llama/Llama-3-70b-chat-hf'],
                        ];
                    @endphp

                    @foreach($aiModelsRaw as $modelKey => $modelCreds)
                        @php
                            $isText = str_contains($modelKey, 'text');
                            $currentProvider = $modelCreds['provider'] ?? 'openai';
                            $currentBaseUrl = $modelCreds['base_url'] ?? '';
                            $currentModel = $modelCreds['model'] ?? ($isText ? 'gpt-4o-mini' : 'dall-e-3');
                            $currentStatus = $modelCreds['status'] ?? \App\Enums\StatusEnum::FALSE->status();
                            $currentKey = $modelCreds['key'] ?? '';
                            $sectionTitle = $isText ? translate('AI Text Generation') : translate('AI Image Generation');
                        @endphp
                        <div class="form-element child">
                            <div class="row gy-4">
                                <div class="col-xxl-2 col-xl-3">
                                    <h5 class="form-element-title">{{ $sectionTitle }}</h5>
                                </div>
                                <div class="col-xxl-8 col-xl-9">
                                    <div class="row gy-4">
                                        {{-- Status Toggle --}}
                                        <div class="col-md-12">
                                            <div class="form-inner child">
                                                <label class="form-label">{{ $sectionTitle }}</label>
                                                <div class="form-inner-switch">
                                                    <label class="pointer" for="{{ $modelKey }}_status">{{ translate("Enable/Disable") }}</label>
                                                    <div class="switch-wrapper mb-1 checkbox-data">
                                                        <input {{ $currentStatus == \App\Enums\StatusEnum::TRUE->status() ? 'checked' : '' }}
                                                               type="checkbox" class="switch-input"
                                                               id="{{ $modelKey }}_status"
                                                               name="site_settings[{{ \App\Enums\SettingKey::AI_MODELS->value }}][{{ $modelKey }}][status]"/>
                                                        <label for="{{ $modelKey }}_status" class="toggle"><span></span></label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        @if($isText)
                                        {{-- Provider Quick Select (Text only) --}}
                                        <div class="col-md-12">
                                            <div class="form-inner child">
                                                <label class="form-label">{{ translate("Provider") }}</label>
                                                <select class="form-select provider-select" data-target="{{ $modelKey }}">
                                                    @foreach($providerPresets as $pKey => $preset)
                                                        <option value="{{ $pKey }}"
                                                                data-url="{{ $preset['url'] }}"
                                                                data-models="{{ $preset['models'] }}"
                                                                {{ $currentProvider == $pKey ? 'selected' : '' }}>
                                                            {{ $preset['label'] }}
                                                        </option>
                                                    @endforeach
                                                    <option value="custom" {{ !array_key_exists($currentProvider, $providerPresets) ? 'selected' : '' }}>
                                                        {{ translate('Custom (OpenAI Compatible)') }}
                                                    </option>
                                                </select>
                                                <input type="hidden" name="site_settings[{{ \App\Enums\SettingKey::AI_MODELS->value }}][{{ $modelKey }}][provider]"
                                                       id="{{ $modelKey }}_provider" value="{{ $currentProvider }}">
                                                <small class="text-muted">{{ translate('DeepSeek, Groq, Together AI all use OpenAI-compatible API format') }}</small>
                                            </div>
                                        </div>

                                        {{-- Base URL --}}
                                        <div class="col-md-12" id="{{ $modelKey }}_base_url_wrapper">
                                            <div class="form-inner child">
                                                <label for="{{ $modelKey }}_base_url" class="form-label">
                                                    {{ translate("API Base URL") }}
                                                    <small class="text-muted">({{ translate("Leave empty for OpenAI default") }})</small>
                                                </label>
                                                <input type="text" id="{{ $modelKey }}_base_url"
                                                       name="site_settings[{{ \App\Enums\SettingKey::AI_MODELS->value }}][{{ $modelKey }}][base_url]"
                                                       class="form-control" value="{{ $currentBaseUrl }}"
                                                       placeholder="https://api.deepseek.com/v1">
                                            </div>
                                        </div>

                                        {{-- Model Name --}}
                                        <div class="col-md-12">
                                            <div class="form-inner child">
                                                <label for="{{ $modelKey }}_model" class="form-label">{{ translate("Model") }}</label>
                                                <input type="text" id="{{ $modelKey }}_model"
                                                       name="site_settings[{{ \App\Enums\SettingKey::AI_MODELS->value }}][{{ $modelKey }}][model]"
                                                       class="form-control" value="{{ $currentModel }}"
                                                       placeholder="gpt-4o-mini">
                                                <small class="text-muted" id="{{ $modelKey }}_model_hint">
                                                    {{ translate("Available models") }}: {{ $providerPresets[$currentProvider]['models'] ?? 'gpt-4o-mini, gpt-4o, gpt-3.5-turbo' }}
                                                </small>
                                            </div>
                                        </div>
                                        @endif

                                        {{-- API Key --}}
                                        <div class="col-md-12">
                                            <div class="form-inner child">
                                                <label for="{{ $modelKey }}_key" class="form-label">{{ translate("API Key") }}</label>
                                                <input type="password" id="{{ $modelKey }}_key"
                                                       name="site_settings[{{ \App\Enums\SettingKey::AI_MODELS->value }}][{{ $modelKey }}][key]"
                                                       class="form-control" value="{{ $currentKey }}"
                                                       placeholder="{{ translate('Enter your API key') }}">
                                                <div class="form-text">
                                                    <a href="#" class="toggle-key-visibility" data-target="{{ $modelKey }}_key" style="font-size:12px;">
                                                        <i class="ri-eye-line"></i> {{ translate("Show") }}
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach

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

@push('script-push')
    <script>
        "use strict";
        $(document).ready(function() {

            setInitialVisibility();
            $('.parent input[type="checkbox"]').change(function() {
                toggleChildren();
            });
            $('.switch-input').on('change', function() {
                updateBackgroundClass();
            });

            // Provider quick-select: auto-fill base_url and model hint
            $('.provider-select').on('change', function() {
                var target = $(this).data('target');
                var selected = $(this).find(':selected');
                var url = selected.data('url') || '';
                var models = selected.data('models') || '';
                var provider = $(this).val();

                $('#' + target + '_provider').val(provider === 'custom' ? 'custom' : provider);
                $('#' + target + '_base_url').val(url);
                $('#' + target + '_model_hint').text(models ? '{{ translate("Available models") }}: ' + models : '');

                // Show/hide base URL field
                if (provider === 'openai') {
                    $('#' + target + '_base_url_wrapper').hide();
                    $('#' + target + '_base_url').val('');
                } else {
                    $('#' + target + '_base_url_wrapper').show();
                }

                // Auto-set first model as default
                if (models && provider !== 'custom') {
                    var firstModel = models.split(',')[0].trim();
                    $('#' + target + '_model').val(firstModel);
                }
            });

            // Initialize: hide base_url for OpenAI
            $('.provider-select').each(function() {
                var target = $(this).data('target');
                if ($(this).val() === 'openai') {
                    $('#' + target + '_base_url_wrapper').hide();
                }
            });

            // Toggle API key visibility
            $(document).on('click', '.toggle-key-visibility', function(e) {
                e.preventDefault();
                var targetId = $(this).data('target');
                var input = $('#' + targetId);
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    $(this).html('<i class="ri-eye-off-line"></i> {{ translate("Hide") }}');
                } else {
                    input.attr('type', 'password');
                    $(this).html('<i class="ri-eye-line"></i> {{ translate("Show") }}');
                }
            });
            $('form').on('submit', function(e) {
                $('.checkbox-data').each(function() {
                    var $checkbox = $(this).find('.switch-input');
                    var $hiddenInput = $(this).find('input[type="hidden"]');

                    if ($checkbox.is(':checked')) {
                        if ($hiddenInput.length === 0) {
                            $(this).append('<input type="hidden" name="' + $checkbox.attr('name') + '" value="{{ \App\Enums\StatusEnum::TRUE->status() }}">');
                        } else {
                            $hiddenInput.val('{{ \App\Enums\StatusEnum::TRUE->status() }}');
                        }
                    } else {
                        if ($hiddenInput.length === 0) {
                            $(this).append('<input type="hidden" name="' + $checkbox.attr('name') + '" value="{{ \App\Enums\StatusEnum::FALSE->status() }}">');
                        } else {
                            $hiddenInput.val('{{ \App\Enums\StatusEnum::FALSE->status() }}');
                        }
                    }
                });
            });
        });
    </script>
@endpush

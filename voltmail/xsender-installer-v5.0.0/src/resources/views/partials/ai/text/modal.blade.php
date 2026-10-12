@if(check_open_ai_text_availability())
<div class="modal fade" id="aiTextModal" tabindex="-1" aria-labelledby="aiTextModal" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-xl">
        <form id="AiForm" method="post">
            @csrf
            @php
                $countries = json_decode(file_get_contents(resource_path('country_code/') . 'languages.json'),true);
            @endphp
            
            <div class="modal-content ai-modal-content">
                <!-- Header -->
                <div class="modal-header ai-modal-header">
                    <div class="ai-modal-title-wrapper flex-grow-1 pb-4">
                        <div class="d-flex flex-column justify-content-start">
                            
                            <h5 class="modal-title fw-bold mb-1">{{translate('AI Assistance')}}</h5>
                            <p class="mb-0 text-muted">{{translate('Enhance your content with AI')}}</p>
                        </div>
                    </div>
                </div>

                <div class="modal-body">
                    <!-- Loading Overlay -->
                    <div class="ai-content-loader d-none">
                        <div class="ai-loading-content">
                            <div class="ai-spinner">
                                <div class="ai-spinner-ring"></div>
                                <div class="ai-spinner-ring-active"></div>
                            </div>
                            <div class="ai-loading-text">
                                <p class="ai-loading-title">{{translate('Generating content...')}}</p>
                                <p class="ai-loading-subtitle">{{translate('AI is working on your request')}}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Content Input Section -->
                    <div class="ai-content-input-section">
                        <label class="ai-form-label" for="custom_prompt">
                            <i class="ri-file-text-line ai-label-icon"></i>
                            {{translate("Your Content")}} <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control ai-prompt-input custom-prompt" 
                                  placeholder="{{translate("Enter your content here...")}}" 
                                  name="custom_prompt" 
                                  id="custom_prompt" 
                                  cols="30" 
                                  rows="2"></textarea>
                        <div class="ai-content-stats">
                            <span class="ai-char-count">0 {{translate('characters')}}</span>
                            <span class="ai-word-count">0 {{translate('words')}}</span>
                        </div>
                    </div>

                    <!-- Result Section -->
                    <div class="result-section d-none">
                        <div class="ai-result-card">
                            <div class="ai-result-header">
                                <div class="ai-result-title-wrapper">
                                    <div class="ai-result-icon">
                                        <i class="ri-bard-line"></i>
                                    </div>
                                    <h6 class="ai-result-title">{{translate("AI Result")}}</h6>
                                </div>
                                <div class="ai-result-actions">
                                    <button type="button" class="btn ai-action-btn ai-copy-btn copy-ai-content" 
                                            title="{{translate('Copy')}}" data-bs-toggle="tooltip">
                                        <i class="ri-file-copy-line"></i>
                                        <span class="ai-btn-text">{{translate('Copy')}}</span>
                                    </button>
                                    <button type="button" class="btn ai-action-btn ai-download-btn download-text" 
                                            title="{{translate('Download')}}" data-bs-toggle="tooltip">
                                        <i class="ri-download-2-line"></i>
                                        <span class="ai-btn-text">{{translate('Download')}}</span>
                                    </button>
                                </div>
                            </div>
                            <div class="ai-result-content">
                                <textarea readonly class="ai-result form-control" rows="10"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Main Content -->
                    <div class="ai-content-generate">
                        <!-- Default View -->
                        <div class="default-section fade-in">
                            <div class="ai-section-header">
                                <h3 class="ai-section-title">{{translate('What do you want to do?')}}</h3>
                                <p class="ai-section-subtitle">{{translate('Here are some ideas to get you started')}}</p>
                            </div>

                            <!-- Quick Actions -->
                            <div class="row">

                                @php
                                    $quickActions = [
                                        'improve_it' => ['icon' => 'ri-magic-line', 'color' => 'primary', 'desc' => 'Enhance overall quality and clarity'],
                                        'grammar_correction' => ['icon' => 'ri-check-double-line', 'color' => 'secondary', 'desc' => 'Fix grammatical errors and typos'],
                                        'make_it_more_detailed' => ['icon' => 'ri-file-text-line', 'color' => 'trinary', 'desc' => 'Add more depth and information']
                                    ];
                                @endphp

                                @foreach($quickActions as $key => $action)
                                <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-3">
                                    <button name="ai_option" value="{{$key}}" type="submit" class="ai-quick-action-btn option-btn w-100">
                                        <div class="ai-action-icon ai-action-icon-{{$action['color']}}">
                                            <i class="{{$action['icon']}}"></i>
                                        </div>
                                        <div class="ai-action-content">
                                            <div class="ai-action-title">{{k2t($key)}}</div>
                                            <div class="ai-action-desc">{{translate($action['desc'])}}</div>
                                        </div>
                                    </button>
                                </div>
                                @endforeach

                                <!-- More Options -->
                                <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-3">
                                    <button type="button" id="more-option" class="ai-expandable-btn w-100">
                                        <div class="ai-expandable-content">
                                            <div class="ai-action-icon ai-action-icon-gradient">
                                                <i class="ri-more-line"></i>
                                            </div>
                                            <div class="ai-action-content">
                                                <div class="ai-action-title">{{translate('More Options')}}</div>
                                                <div class="ai-action-desc">{{translate('Explore all rewriting and tone options')}}</div>
                                            </div>
                                        </div>
                                        <i class="ri-arrow-down-s-line ai-expandable-arrow"></i>
                                    </button>
                                </div>

                                <!-- Translate -->
                                <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-3">
                                    <button type="button" id="translate-option" class="ai-expandable-btn w-100">
                                        <div class="ai-expandable-content">
                                            <div class="ai-action-icon ai-action-icon-gradient">
                                                <i class="ri-translate"></i>
                                            </div>
                                            <div class="ai-action-content">
                                                <div class="ai-action-title">{{translate('Translate')}}</div>
                                                <div class="ai-action-desc">{{translate('Translate to different languages')}}</div>
                                            </div>
                                        </div>
                                        <i class="ri-arrow-down-s-line ai-expandable-arrow"></i>
                                    </button>
                                </div>

                            </div>


                            <!-- Divider -->
                            <div class="ai-divider">
                                <span class="ai-divider-text">{{translate('OR')}}</span>
                            </div>

                            <!-- Custom Prompt -->
                            <div class="ai-custom-prompt-card">
                                <div class="ai-custom-prompt-header">
                                    <h6 class="ai-custom-prompt-title">
                                        <i class="ri-magic-line"></i>
                                        {{translate("Make Your Own Prompt")}}
                                    </h6>
                                    <p class="ai-custom-prompt-desc">{{translate("Describe exactly what you want the AI to do with your content")}}</p>
                                </div>
                                <div class="ai-custom-prompt-content">
                                    <div class="ai-prompt-input-group">
                                        <input name="custom_prompt_option" id="custom_prompt_option" 
                                               class="form-control custom-prompt-option" type="text"  
                                               placeholder="{{translate('Ex: Make it more friendly and conversational...')}}">
                                        <button type="submit" class="btn ai-prompt-submit-btn">
                                            <i class="ri-send-plane-fill"></i>
                                        </button>
                                    </div>
                                    <div class="ai-prompt-suggestions">
                                        @foreach(config("setting.ai.manual_suggestions") as $suggestion)
                                        <span class="ai-suggestion-badge" data-suggestion="{{$suggestion}}">{{translate($suggestion)}}</span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- More Options View -->
                        <div class="ai-options fade-in d-none">
                            <div class="ai-back-header">
                                <button type="button" class="ai-back-btn ai-option-closer">
                                    <i class="ri-arrow-left-line"></i>
                                    {{translate("Back to main options")}}
                                </button>
                            </div>
                            
                            <div class="ai-option-wrapper" data-simplebar>
                                <!-- Rewrite Options -->
                                <div class="ai-option-card">
                                    <div class="ai-option-card-header">
                                        <h6 class="ai-option-card-title">
                                            <i class="ri-file-text-line"></i>
                                            {{translate("Rewrite It")}}
                                        </h6>
                                        <p class="ai-option-card-desc">{{translate("Choose how you want to improve your content")}}</p>
                                    </div>
                                    <div class="ai-option-list">
                                        @foreach (collect(get_ai_option()) as $key => $option)
                                        <button name="ai_option" value="{{$key}}" type="submit" class="ai-option-item option-btn">
                                            <i class="ri-arrow-right-line ai-option-icon"></i>
                                            {{k2t($key)}}
                                        </button>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Tone Options -->
                                <div class="ai-option-card">
                                    <div class="ai-option-card-header">
                                        <h6 class="ai-option-card-title">
                                            <i class="ri-message-3-line"></i>
                                            {{translate('Adjust Tone')}}
                                        </h6>
                                        <p class="ai-option-card-desc">{{translate("Change the tone and style of your content")}}</p>
                                    </div>
                                    <div class="ai-option-list">
                                        @foreach (collect(get_ai_tone()) as $key => $tone)
                                        <button name="ai_tone" value="{{$key}}" type="submit" class="ai-option-item option-btn">
                                            <i class="ri-arrow-right-line ai-option-icon"></i>
                                            {{Arr::get($tone,'display_name')}}
                                        </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Translate Section -->
                        <div class="translate-section fade-in d-none">
                            <div class="ai-back-header">
                                <button type="button" class="ai-back-btn ai-option-closer">
                                    <i class="ri-arrow-left-line"></i>
                                    {{translate("Back to main options")}}
                                </button>
                            </div>

                            <div class="ai-translate-card">
                                <div class="ai-translate-header">
                                    <h6 class="ai-translate-title">
                                        <i class="ri-translate"></i>
                                        {{translate("Choose Language")}}
                                    </h6>
                                    <p class="ai-translate-desc">{{translate("Select the language you want to translate your content to")}}</p>
                                </div>
                                <div class="ai-translate-content">
                                    <select class="form-select ai-lang" name="language" id="ai-language" data-placeholder="{{translate('Select a language...')}}">
                                        <option select disabled value="">{{ translate("Select a language") }}</option>
                                        @foreach ($countries as $code => $country)
                                        <option value="{{$country['name']}}">
                                            {{limit_words($country['name'],45)}}
                                        </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="ai-translate-btn mt-4 d-none" id="ai-translate-submit">
                                        <i class="ri-translate"></i>
                                        <span class="ai-translate-text">{{translate('Translate to')}}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <input hidden type="text" class="ai-content-option">
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

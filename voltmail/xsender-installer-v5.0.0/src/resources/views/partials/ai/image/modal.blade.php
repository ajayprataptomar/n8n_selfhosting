@if(check_open_ai_image_availability())
<!-- Image Generation Modal -->
<div class="modal fade" id="aiImageModal" tabindex="-1" aria-labelledby="aiImageModal" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-xl">
        <form id="AiImageForm" method="post">
            @csrf
            
            <div class="modal-content ai-modal-content">
                <!-- Header -->
                <div class="modal-header ai-modal-header">
                    <div class="ai-modal-title-wrapper flex-grow-1 pb-4">
                        <div class="d-flex flex-column justify-content-start">
                            <h5 class="modal-title fw-bold mb-1">{{translate('AI Image Generation')}}</h5>
                            <p class="mb-0 text-muted">{{translate('Create stunning images with artificial intelligence')}}</p>
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
                                <p class="ai-loading-title">{{translate('Generating images...')}}</p>
                                <p class="ai-loading-subtitle">{{translate('AI is creating your images')}}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Error Alert -->
                    <div class="ai-error-alert alert alert-danger d-none" role="alert">
                        <i class="ri-error-warning-line me-2"></i>
                        <span class="ai-error-message"></span>
                    </div>

                    <!-- Configuration Section -->
                    <div class="ai-content-generate">
                        <!-- Image Prompt Section -->
                        <div class="ai-content-input-section mb-4">
                            <label class="ai-form-label" for="image_prompt">
                                <i class="ri-image-line ai-label-icon"></i>
                                {{translate("Image Prompt")}} <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control ai-prompt-input" 
                                      placeholder="{{translate("Ex: A beautiful sunset over mountains with vibrant colors, photorealistic, 4K quality...")}}" 
                                      name="image_prompt" 
                                      id="image_prompt" 
                                      cols="30" 
                                      rows="4"></textarea>
                            <small class="text-muted">{{translate('Describe the image you want to generate in detail')}}</small>
                        </div>

                        <!-- Configuration Grid -->
                        <div class="row g-3 mb-4">
                            <!-- AI Model -->
                            <div class="col-md-6">
                                <label class="ai-form-label" for="ai_model">
                                    {{translate("AI Model")}} <span class="text-danger">*</span>
                                </label>
                                <select class="form-select ai-model-select" name="ai_model" id="ai_model">
                                    <option value="">{{translate('Select AI model...')}}</option>
                                    @foreach(config('setting.ai_image_generation_model', []) as $key => $value)
                                        <option value="{{$key}}">{{$value}}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Provider -->
                            <div class="col-md-6">
                                <label class="ai-form-label" for="ai_provider">
                                    {{translate("Provider")}}
                                </label>
                                <select class="form-select" name="ai_provider" id="ai_provider">
                                    @foreach(config('setting.ai_image_secret', []) as $key => $value)
                                        <option value="{{$key}}" {{$key == 'open_ai' ? 'selected' : ''}}>{{$value}}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Resolution -->
                            <div class="col-md-6">
                                <label class="ai-form-label" for="ai_resolution">
                                    {{translate("Resolution")}} <span class="text-danger">*</span>
                                </label>
                                <select class="form-select ai-resolution-select" name="ai_resolution" id="ai_resolution" disabled>
                                    <option value="">{{translate('Select resolution...')}}</option>
                                </select>
                            </div>

                            <!-- Number of Images -->
                            <div class="col-md-6">
                                <label class="ai-form-label" for="number_of_images">
                                    {{translate("Number of Images")}} <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control" name="number_of_images" id="number_of_images" 
                                       min="1" max="10" value="1" placeholder="1-10">
                            </div>

                            <!-- Generate Button -->
                            <div class="col-md-12 mt-4 d-flex align-items-end">
                                <button type="submit" class="btn ai-image-generate-btn w-100">
                                    <i class="ri-send-plane-fill me-2"></i>
                                    {{translate('Generate Images')}}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Result Section -->
                    <div class="result-section d-none">
                        <div class="ai-result-header d-flex justify-content-between align-items-center mb-4">
                            <div class="d-flex align-items-center">
                                <div class="ai-result-icon me-3">
                                    <i class="ri-image-fill"></i>
                                </div>
                                <div>
                                    <h6 class="ai-result-title mb-1">{{translate("Generated Images")}}</h6>
                                    <p class="ai-result-subtitle mb-0">
                                        <span class="ai-image-count">0</span> {{translate('images created')}}
                                    </p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm ai-generate-new">
                                {{translate('Generate New')}}
                            </button>
                        </div>

                        <!-- Images Grid -->
                        <div class="ai-results-grid row g-3 mb-4">
                            <!-- Images will be populated here -->
                        </div>

                        <!-- Disclaimer -->
                        <div class="alert alert-info ai-disclaimer">
                            <i class="ri-information-line me-2"></i>
                            <strong>{{translate('Note:')}}</strong> 
                            {{translate('Generated images are saved in')}} 
                            <code>assets/file/images/ai/</code> 
                            {{translate('directory. Downloaded files are named with unique IDs to prevent overwriting.')}}
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Image Preview Modal -->
<div class="modal fade" id="aiImagePreviewModal" tabindex="-1" aria-labelledby="aiImagePreviewModal" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{translate('Image Preview')}}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img src="/placeholder.svg" alt="Preview" class="img-fluid ai-image-preview-img">
            </div>
        </div>
    </div>
</div>
@endif
<script type="text/javascript">
$(document).ready(function() {
    // Configuration for model resolutions
    const aiImageResolutions = @json(config('setting.ai.image_resolution'));

    // Generate datetime string for unique filenames
    function generateDateTime() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        return `${year}${month}${day}_${hours}${minutes}${seconds}`;
    }

    // Update resolution options based on selected model
    $(document).on('change', '.ai-model-select', function() {
        const selectedModel = $(this).val();
        const resolutionSelect = $('.ai-resolution-select');
        
        resolutionSelect.empty().append('<option value="">{{translate("Select resolution...")}}</option>');
        
        if (selectedModel && aiImageResolutions[selectedModel]) {
            resolutionSelect.prop('disabled', false);
            aiImageResolutions[selectedModel].forEach(function(resolution) {
                resolutionSelect.append(`<option value="${resolution}">${resolution}</option>`);
            });
        } else {
            resolutionSelect.prop('disabled', true);
        }
    });

    // Form validation for image
    function validateImageForm() {
        const prompt = $('#image_prompt').val().trim();
        const model = $('#ai_model').val();
        const resolution = $('#ai_resolution').val();
        const numberOfImages = parseInt($('#number_of_images').val());

        if (!prompt) {
            showImageError('{{translate("Prompt is required")}}');
            return false;
        }
        if (!model) {
            showImageError('{{translate("Please select a model")}}');
            return false;
        }
        if (!resolution) {
            showImageError('{{translate("Please select a resolution")}}');
            return false;
        }
        if (!numberOfImages || numberOfImages < 1 || numberOfImages > 10) {
            showImageError('{{translate("Number of images must be between 1 and 10")}}');
            return false;
        }

        hideImageError();
        return true;
    }

    // Show error message
    function showImageError(message) {
        notify('error', message);
    }

    // Hide error message
    function hideImageError() {
        $('.ai-error-alert').addClass('d-none');
    }

    // Form submission handler for image
    $(document).on('submit', '#AiImageForm', function(e) {
        e.preventDefault();
        
        if (!validateImageForm()) {
            return;
        }
        
        var formData = $(this).serialize();
        var modal = $('#aiImageModal');
        var numberOfImages = parseInt($('#number_of_images').val());
        
        $.ajax({
            url: "{{route('admin.ai.content.generate.image')}}",
            type: "post",
            data: formData,
            dataType: 'json',
            beforeSend: function() {
                modal.find('.ai-content-generate').addClass("d-none");
                modal.find('.result-section').addClass("d-none");
                modal.find('.ai-content-loader').removeClass("d-none");
                
                const imageText = numberOfImages > 1 ? '{{translate("images...")}}' : '{{translate("image...")}}';
                modal.find('.ai-loading-title').text(
                    '{{translate("Generating")}} ' + numberOfImages + ' ' + imageText
                );
            },
            success: function(response) {
                if (response.status && response.image_content) {
                    displayGeneratedImages(response.image_content);
                    modal.find('.result-section').removeClass("d-none");
                    modal.find('.ai-content-generate').addClass("d-none");
                } else {
                    modal.find('.ai-content-generate').removeClass("d-none");
                    showImageError(response.message || '{{translate("Failed to generate images")}}');
                }
            },
            error: function(error) {
                modal.find('.ai-content-generate').removeClass("d-none");
                if (error && error.responseJSON) {
                    if (error.responseJSON.message) {
                        showImageError(error.responseJSON.message);
                    } else {
                        for (let i in error.responseJSON.errors) {
                            showImageError(error.responseJSON.errors[i][0]);
                            break;
                        }
                    }
                }
            },
            complete: function() {
                modal.find('.ai-content-loader').addClass("d-none");
            }
        });
    });

    // Display generated images
    function displayGeneratedImages(imageUrls) {
        const grid = $('.ai-results-grid');
        const imageCount = imageUrls.length;
        
        grid.empty();
        $('.ai-image-count').text(imageCount);
        
        imageUrls.forEach(function(imageUrl, index) {
            const imageId = `img_${Date.now()}_${index + 1}`;
            const imageHtml = `
                <div class="col-md-4 col-sm-6">
                    <div class="ai-image-item">
                        <div class="ai-image-item-preview" data-bs-toggle="modal" data-bs-target="#aiImagePreviewModal">
                            <img src="${imageUrl}" alt="{{translate('Generated image')}} ${index + 1}" class="ai-image-preview" data-image-url="${imageUrl}">
                        </div>
                        <div class="ai-image-item-actions">
                            <button type="button" class="ai-action-btn ai-copy-btn" data-image-url="${imageUrl}" data-image-id="${imageId}" onclick="window.open('${imageUrl}', '_blank')">
                                <i class="ri-external-link-line"></i>
                                <span class="ai-btn-text">{{translate('Open in New Tab')}}</span>
                            </button>
                            <button type="button" class="ai-action-btn ai-download-btn" data-image-url="${imageUrl}" data-image-id="${imageId}">
                                <i class="ri-download-2-line"></i>
                                <span class="ai-btn-text">{{translate('Download')}}</span>
                            </button>
                        </div>
                        <div class="ai-image-item-number">{{translate('Image')}} ${index + 1}</div>
                    </div>
                </div>
            `;
            grid.append(imageHtml);
        });
    }

    // Image preview modal
    $(document).on('click', '.ai-image-item-preview', function() {
        const imageUrl = $(this).find('img').data('image-url');
        $('#aiImagePreviewModal .ai-image-preview-img').attr('src', imageUrl);
    });

    // Copy image URL
    // Download image
    $(document).on('click', '.ai-download-btn', function(e) {
        e.preventDefault();
        const imageUrl = $(this).data('image-url');
        const imageId = $(this).data('image-id');
        const btn = $(this);
        
        const dateTime = generateDateTime();
        const imageIndex = imageId.split('_')[2] || '1';
        const filename = `ai_generated_${dateTime}_${imageIndex}.png`;
        
        fetch(imageUrl)
            .then(response => response.blob())
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                const originalText = btn.find('.ai-btn-text').text();
                const originalIcon = btn.find('i').attr('class');
                
                btn.find('i').attr('class', 'ri-check-line');
                btn.find('.ai-btn-text').text('{{translate("Downloaded!")}}');
                
                setTimeout(function() {
                    btn.find('i').attr('class', originalIcon);
                    btn.find('.ai-btn-text').text(originalText);
                }, 2000);
                
                notify('success', "{{translate('Image downloaded successfully!')}}");
            })
            .catch(error => {
                notify('error', "{{translate('Failed to download image')}}");
            });
    });

    // AI Image Modal button click handler
    $(document).on("click", '.ai-image-modal-btn', function(e) {
        var modal = $('#aiImageModal');
        
        // Reset form
        modal.find('#AiImageForm')[0].reset();
        modal.find('#ai_provider').val('open_ai');
        modal.find('#ai_quality').val('standard');
        modal.find('#number_of_images').val('1');
        modal.find('.ai-resolution-select').prop('disabled', true).empty()
            .append('<option value="">{{translate("Select resolution...")}}</option>');
        
        // Reset modal state
        modal.find('.result-section').addClass("d-none");
        modal.find('.ai-content-generate').removeClass("d-none");
        modal.find('.ai-content-loader').addClass("d-none");
        hideImageError();
        
        modal.modal('show');
    });

    // Generate new images button
    $(document).on('click', '.ai-generate-new', function() {
        const modal = $('#aiImageModal');
        modal.find('.result-section').addClass("d-none");
        modal.find('.ai-content-generate').removeClass("d-none");
        hideImageError();
    });

    // Reset image form when modal is closed
    $('#aiImageModal').on('hidden.bs.modal', function() {
        $(this).find('#AiImageForm')[0].reset();
        $(this).find('.result-section').addClass("d-none");
        $(this).find('.ai-content-generate').removeClass("d-none");
        $(this).find('.ai-resolution-select').prop('disabled', true);
        hideImageError();
    });
});
</script>
<script type="text/javascript">
$(document).ready(function() {

   

    var aiTexarea = '';
    var textEditor = '';

    // Character and word count functionality
    function updateContentStats() {
        const content = $('#custom_prompt').val() || '';
        const charCount = content.length;
        const wordCount = content.trim() ? content.trim().split(/\s+/).length : 0;
        
        $('.ai-char-count').text(charCount + ' {{translate("characters")}}');
        $('.ai-word-count').text(wordCount + ' {{translate("words")}}');
    }

    // Update stats on input
    $(document).on('input', '#custom_prompt', updateContentStats);

    // Form submission handler
    $(document).on('submit', '#AiForm', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        var modal = $('#aiTextModal');
        
        $.ajax({
            url: "{{route('admin.ai.content.generate.text')}}",
            type: "post",
            data: formData,
            dataType: 'json',
            beforeSend: function() {
                modal.find('.ai-content-generate').addClass("d-none");
                modal.find('.result-section').addClass("d-none");
                modal.find('.ai-content-loader').removeClass("d-none");
            },
            success: function(response) {
                if (response.status) {
                    modal.find('.ai-result').val(response.message);
                    modal.find('.result-section').removeClass("d-none");
                    modal.find('.ai-content-generate').addClass("d-none");
                } else {
                    modal.find('.ai-content-generate').removeClass("d-none");
                    notify('error', response.message);
                }
            },
            error: function(error) {
                modal.find('.ai-content-generate').removeClass("d-none");
                if (error && error.responseJSON) {
                    if (error.responseJSON.message) {
                        notify('error', error.responseJSON.message);
                    } else {
                        for (let i in error.responseJSON.errors) {
                            notify('error', error.responseJSON.errors[i][0]);
                        }
                    }
                } 
            },
            complete: function() {
                modal.find('.ai-content-loader').addClass("d-none");
            }
        });
    });

    // AI Generator Button functionality
    // @if(check_open_ai_text_availability())
    // $('textarea.form-control').on('input', function() {
    //     if (!$(this).hasClass("ai-prompt-input")) {
    //         var words = $(this).val().trim().split(/\s+/).length;

    //         if ($(this).next('.ai-text-generator-btn').length === 0) {
    //             if (words >= 2) {
    //                 $(this).after(`
    //                     <button type="button" class="ai-text-generator-btn mt-3 ai-text-modal-btn">
    //                         <span class="ai-icon">
    //                             <span class="spinner-border d-none" aria-hidden="true"></span>
    //                             <i class="ri-bard-line"></i>
    //                         </span>
    //                         <span class="ai-text">
    //                             {{translate('Generate With AI')}}
    //                         </span>
    //                     </button>
    //                 `);
    //             }
    //         } else {
    //             if (words < 2) {
    //                 $(this).next('.ai-text-generator-btn').remove();
    //             }
    //         }
    //     }
    // });
    // @endif

    // Remove HTML tags function
    function removeTags(str) {
        if ((str === null) || (str === ''))
            return false;
        else
            str = str.toString();
            str = str.replace(/^[\s\n]+/, '');
            str = str.replace(/(<([^>]+)>|&nbsp;|)/ig, '');
            return str.trim();
    }

    // Custom prompt option handler
    $(document).on("input", '.custom-prompt-option', function(e) {
        var modal = $('#aiTextModal');
        var prompt = modal.find('.custom-prompt').val();
        var inputText = $(this).val().trim();

        if (prompt && inputText) {
            var lines = prompt.split('\n');
            var basePrompt = lines[0];
            var updatedPrompt = basePrompt + '\n' + inputText;
            modal.find('.custom-prompt').val(updatedPrompt);
        } else if (inputText) {
            modal.find('.custom-prompt').val(inputText);
        }
    });

    // Suggestion badge click handler
    $(document).on('click', '.ai-suggestion-badge', function() {
        var suggestion = $(this).data('suggestion');
        $('.custom-prompt-option').val(suggestion);
    });

    // AI Modal button click handler
    $(document).on("click", '.ai-text-modal-btn', function(e) {
    var modal = $('#aiTextModal');
    modal.find('.custom-prompt-option').val('');
    modal.find('#custom_prompt').val(''); // Clear the textarea

    // Reset modal state
    modal.find(".translate-section").addClass('d-none');
    modal.find(".ai-options").addClass('d-none');
    modal.find(".default-section").removeClass('d-none');

    // Find the closest form-element container
    var formInner = $(this).closest('.form-element');
    aiTexarea = formInner.find('textarea.form-control#message'); // Target specific textarea
    textEditor = '';

    // Get textarea value with fallback
    var textareaValue = '';
    if (aiTexarea.length > 0) {
        textareaValue = aiTexarea.val() || '';
    } else {
        console.error('Textarea not found within .form-element');
    }

    // Show modal and set value after it's fully visible
    modal.on('shown.bs.modal', function() {
        if (textareaValue && textareaValue.trim() !== "") {
            modal.find('#custom_prompt').val(textareaValue); // Set value
            modal.find('#custom_prompt').attr('data-value', textareaValue);
            updateContentStats(); // Update character/word count
            modal.find('#custom_prompt').trigger('input').focus(); // Force UI refresh and focus
            console.log('custom_prompt value set to:', modal.find('#custom_prompt').val());
        }
        // Unbind to prevent multiple triggers
        modal.off('shown.bs.modal');
    });

    modal.find('.result-section').addClass("d-none");
    modal.find('.ai-content-generate').removeClass("d-none");
    modal.find('.ai-content-loader').addClass("d-none");

    $('#ai-language').val('').trigger('change');
    modal.modal('show'); // Show modal
});

    // Modal navigation handlers
    const aiTextModal = document.querySelector("#aiTextModal");
    if (aiTextModal) {
        const moreOption = aiTextModal.querySelector("#more-option");
        const translateOption = aiTextModal.querySelector("#translate-option");
        const aiOptions = aiTextModal.querySelector(".ai-options");
        const translateSection = aiTextModal.querySelector(".translate-section");
        const defaultSection = aiTextModal.querySelector(".default-section");
        const btnClose = aiTextModal.querySelector(".ai-modal-close");

        // More options click
        if (moreOption) {
            moreOption.addEventListener("click", () => {
                defaultSection.classList.add("d-none");
                aiOptions.classList.remove("d-none");
            });
        }

        // Translate option click
        if (translateOption) {
            translateOption.addEventListener("click", () => {
                defaultSection.classList.add("d-none");
                translateSection.classList.remove("d-none");
            });
        }

        // Close button click
        if (btnClose) {
            btnClose.addEventListener("click", () => {
                defaultSection.classList.remove("d-none");
                aiOptions.classList.add("d-none");
                translateSection.classList.add("d-none");
            });
        }

        // Back button handlers
        const optionClosers = document.querySelectorAll(".ai-option-closer");
        optionClosers.forEach((closer) => {
            closer.addEventListener("click", () => {
                defaultSection.classList.remove("d-none");
                aiOptions.classList.add("d-none");
                translateSection.classList.add("d-none");
            });
        });
    }

    // Copy content handler
    $(document).on('click', '.copy-ai-content', function() {
        var modal = $('#aiTextModal');
        var copyText = modal.find('.ai-result')[0];
        copyText.select();
        copyText.setSelectionRange(0, 99999); 
        document.execCommand("copy");
        notify('success', 'Copied the AI Result: ');
    });

    // Download text handler
    $(document).on('click', '.download-text', function(e) {
        var modal = $('#aiTextModal');
        var content = modal.find('.ai-result').val();

        var blob = new Blob([content], { type: 'text/plain' });
        var link = document.createElement('a');
        link.href = window.URL.createObjectURL(blob);
        link.download = 'ai-enhanced-content.txt';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        // Update button state
        var btn = $(e.currentTarget);
        var originalText = btn.find('.ai-btn-text').text();
        var originalIcon = btn.find('i').attr('class');
        
        btn.find('i').attr('class', 'ri-check-line');
        btn.find('.ai-btn-text').text('{{translate("Downloaded!")}}');
        
        setTimeout(function() {
            btn.find('i').attr('class', originalIcon);
            btn.find('.ai-btn-text').text(originalText);
        }, 2000);
    });

    // Option button click handler
    $(document).on('click', '.option-btn', function(e) {
        var key = $(this).attr('name');
        var value = $(this).attr('value');
        var modal = $('#aiTextModal');
        modal.find('.ai-content-option').attr('name', key);
        modal.find('.ai-content-option').val(value);
    });

    // Insert result handler
    $(document).on('click', '.insert-result', function(e) {
        var modal = $('#aiTextModal');
        var result = modal.find('.ai-result').val();
        
        if (textEditor != '') {
            // CKEditor instance handling
            if (typeof textEditor === 'object' && textEditor.setData) {
                textEditor.setData(result);
            } else {
                // Try to get CKEditor instance from DOM
                var editorElement = textEditor[0] || textEditor;
                if (editorElement && editorElement.ckeditorInstance) {
                    editorElement.ckeditorInstance.setData(result);
                } else {
                    aiTexarea.val(result);
                }
            }
        } else {
            aiTexarea.val(result);
        }
    });

    // Language change handler
    $(document).on('change', '.ai-lang', function(e) {
        e.preventDefault();
        var selectedLang = $(this).val();
        
        if (selectedLang && selectedLang !== '') {
            $('#ai-translate-submit').removeClass('d-none');
            $('.ai-translate-text').text('{{translate("Translate to")}} ' + $(this).find('option:selected').text());
        } else {
            $('#ai-translate-submit').addClass('d-none');
        }
    });

    // Translate submit handler
    $(document).on('click', '#ai-translate-submit', function(e) {
        e.preventDefault();
        $('#AiForm').submit();
    });

    // Initialize content stats on page load
    updateContentStats();
});

</script>

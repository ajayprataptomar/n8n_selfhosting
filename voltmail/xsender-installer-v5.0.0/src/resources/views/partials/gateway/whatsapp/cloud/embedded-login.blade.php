<script async defer crossorigin="anonymous" src="{{ \App\Enums\MetaApiEndpoints::JS_SDK->value }}"></script>

<script>
     (function($) {
        "use strict";
 
        $('#embeddedSignupBtn').on('click', function() {

            var button          = $(this);
            var originalText    = button.html();
            
            button.prop('disabled', true);
            button.html('<span class="spinner-border spinner-border-sm me-2"></span>' + "{{ translate('Connecting...') }}");
            
            $.ajax({
                url: "{{ $embedded_sign_up_route }}",
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                success: function(response) {
                    if (response.success && response.signup_url) {
                        var screenWidth     = screen.width;
                        var screenHeight    = screen.height;
                        var width           = Math.min(600, screenWidth * 0.8);
                        var height          = Math.min(800, screenHeight * 0.8);
                        var left            = (screenWidth - width) / 2;
                        var top             = (screenHeight - height) / 2;
                        var popup = window.open(
                            response.signup_url,
                            'whatsapp_embedded_signup',
                            `width=${width},height=${height},scrollbars=yes,resizable=yes,left=${left},top=${top}`
                        );
                        
                        var checkClosed = setInterval(function() {
                            if (popup.closed) {
                                clearInterval(checkClosed);
                                button.prop('disabled', false);
                                button.html(originalText);
                                $.ajax({
                                    url: "{{ $fallback_url }}",
                                    type: 'GET',
                                    success: function() {
                                        window.location.reload();
                                    },
                                    error: function(xhr) {
                                        notify('error', xhr.responseJSON?.message || "{{ translate('Failed to verify connection') }}");
                                    }
                                });
                            }
                        }, 1000);
                    } else {
                        notify('error', response.message || "{{ translate('Failed to initiate embedded signup') }}");
                        button.prop('disabled', false);
                        button.html(originalText);
                    }
                },
                error: function(xhr) {
                    var errorMessage = "{{ translate('Failed to initiate embedded signup') }}";
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    notify('error', errorMessage);
                    button.prop('disabled', false);
                    button.html(originalText);
                }
            });
        });

        window.addEventListener('message', function(event) {
            // Only process messages from our own origin
            if (event.origin !== "{{ url('/') }}") return;

            // Ensure event.data is an object with expected structure
            if (!event.data || typeof event.data !== 'object') return;

            // Only process if we have a success property (our expected message format)
            if (!('success' in event.data)) return;

            if (event.data.success) {
                if (event.data.message) {
                    notify('success', event.data.message);
                }
                window.location.href = "{{ $fallback_url }}";
            } else {
                // Only show error if there's a message
                if (event.data.message) {
                    notify('error', event.data.message);
                }
            }
        });
     })(jQuery);
 </script>
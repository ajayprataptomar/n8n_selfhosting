<!DOCTYPE html>
<html 
    lang="{{ App::getLocale() }}" 
    dir="{{ site_settings('theme_dir', \App\Enums\StatusEnum::FALSE->status()) == \App\Enums\StatusEnum::FALSE->status() 
                ? 'ltr' 
                : 'rtl' }}" 
    class="@php
        // Auto-detect menu-active: if current route matches ANY submenu group, activate the panel
        $menuGroups = [
            'admin.system.*', 'admin.whatsapp.*', 'admin.addon.*',
            'admin.user.*', 'admin.membership.plan.*',
            'admin.gateway.*', 'admin.template.*', 'admin.contact.*',
            'admin.communication.sms.*', 'admin.communication.whatsapp.*', 'admin.communication.email.*',
            'admin.campaign.*', 'admin.lead-generation.*', 'admin.b2b-database.*',
            'admin.automation.*', 'admin.support.ticket.*', 'admin.frontend.sections.*',
            'admin.report.*', 'admin.payment.*', 'admin.blog.*',
        ];
        $shouldShowMenu = false;
        foreach ($menuGroups as $pattern) {
            if (request()->routeIs($pattern)) {
                $shouldShowMenu = true;
                break;
            }
        }
        echo $shouldShowMenu ? 'menu-active' : '';
    @endphp">
    <script>
        (function() {
            function decrypt(encryptedText) {
                try {
                    const decoded = atob(encryptedText);
                    let decrypted = '';
                    for (let i = 0; i < decoded.length; i++) {
                        decrypted += String.fromCharCode(decoded.charCodeAt(i) - 3);
                    }
                    return decrypted;
                } catch (e) {
                    return null;
                }
            }

            const defaultTheme = @php echo json_encode(
                site_settings('theme_mode', \App\Enums\StatusEnum::FALSE->status()) == \App\Enums\StatusEnum::FALSE->status() 
                ? 'dark' 
                : 'light'
            ) @endphp;
            
            const adminId = @php echo json_encode(auth()->guard('admin')->id()) @endphp;
            const storageKey = 'theme_key_' + adminId + '_theme';
            let theme = defaultTheme;

            try {
                const stored = localStorage.getItem(storageKey);
                if (stored) {
                    const decrypted = decrypt(stored);
                    if (decrypted && (decrypted === 'light' || decrypted === 'dark')) {
                        theme = decrypted;
                    }
                }
            } catch (e) {
                console.error('Error reading theme from localStorage:', e);
            }

            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>

    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="color-scheme" content="light dark" />
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="base-url" content="{{ url('') }}">
        @include('partials.content.additional.header.bee_free')
        
        <meta name="default-theme" content="{{ site_settings('theme_mode', \App\Enums\StatusEnum::FALSE->status()) == \App\Enums\StatusEnum::FALSE->status() ? 'dark' : 'light' }}">
        <meta name="admin-id" content="{{ auth()->guard('admin')->id() }}">

        @include('partials.content.additional.header.meta_content')

        <title>{{site_settings('site_name')}} - {{@$title}}</title>
        <link rel="shortcut icon" href="{{showImage(config('setting.file_path.favicon.path').'/'.site_settings('favicon'),config('setting.file_path.favicon.size'))}}" type="image/x-icon">

        <script>
            (function() {
                // Language RTL states from PHP
                window.languageRtlStates = {!! getLanguageRtlStates() !!};
                
                // Current language
                window.currentLanguage = "{{app()->getLocale()}}";

                // Get stored data immediately
                const siteDataJSON = localStorage.getItem("siteData");
                const storedData = siteDataJSON ? JSON.parse(siteDataJSON) :
                { lang: window.currentLanguage, dir: "ltr" };
                
                // Determine direction based on current language
                const currentLang = window.currentLanguage;
                const autoDir = window.languageRtlStates[currentLang] ? "rtl" : "ltr";
                
                // Use auto-determined direction if language changed or no stored direction
                const finalDir = (storedData.lang !== currentLang) ? autoDir : storedData.dir;
                
                // Set direction and theme on html element immediately
                document.documentElement.setAttribute("dir", finalDir);
                document.documentElement.setAttribute("lang", currentLang);
        
                // Store asset URLs for later use
                window.assetUrls = {
                    bootstrapLtr: '{{ asset("assets/theme/global/css/bootstrap.min.css") }}',
                    bootstrapRtl: '{{ asset("assets/theme/global/css/bootstrap.rtl.min.css") }}'
                };

                const cssFile = finalDir === "rtl" 
                    ? window.assetUrls.bootstrapRtl 
                    : window.assetUrls.bootstrapLtr;

                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.type = 'text/css';
                link.href = cssFile;
                link.id = 'bootstrap-css';
                document.head.appendChild(link);

                // Update localStorage with current language and direction
                const updatedData = { ...storedData, lang: currentLang, dir: finalDir };
                localStorage.setItem("siteData", JSON.stringify(updatedData));

            })();
        </script>
        @stack('meta-include')
        
        <link rel="stylesheet" href="{{versioned_asset('assets/theme/global/css/toastr.css')}}">
        <link rel="stylesheet" href="{{versioned_asset('assets/theme/global/css/bootstrap-icons.min.css')}}">
        <link rel="stylesheet" href="{{versioned_asset('assets/theme/global/css/remixicon.css')}}">
        <link rel="stylesheet" href="{{versioned_asset('assets/theme/global/css/simplebar.min.css')}}">
        <link rel="stylesheet" href="{{versioned_asset('assets/theme/global/css/flatpickr.min.css')}}">
        <link rel="stylesheet" href="{{versioned_asset('assets/theme/global/css/custom.css')}}">
        <link rel="stylesheet" href="{{versioned_asset('assets/theme/global/css/main.css')}}">
        <link rel="stylesheet" href="{{versioned_asset('assets/theme/admin/css/all.min.css')}}">
        <link rel="stylesheet" href="{{versioned_asset('assets/theme/admin/css/spectrum.css')}}">
        <link rel="stylesheet" href="{{versioned_asset('assets/theme/admin/css/ai.css')}}">
        <link rel="stylesheet" href="{{versioned_asset('assets/theme/global/css/campaign.css')}}">
        @stack('style-include')
        @stack('style-push')
        @include('partials.theme')

    </head>
    <body>

        @yield('content')

        @include('partials.ai.text.modal')
        @include('partials.ai.image.modal')
        
        <script src="{{versioned_asset('assets/theme/global/js/jquery-3.7.1.min.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/js/bootstrap.bundle.min.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/js/app.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/js/apexcharts.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/js/toastr.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/js/simplebar.min.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/js/flatpickr.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/js/helper.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/js/initialized.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/js/script.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/admin/js/spectrum.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/js/jquery-ui.min.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/ckeditor5-build-classic/ckd.js')}}"></script>
        <script>
            const ThemeManager = {
                secretKey: 'theme_key_' + document.querySelector('meta[name="admin-id"]').content,
                
                encrypt(text) {
                    let encrypted = '';
                    for (let i = 0; i < text.length; i++) {
                        encrypted += String.fromCharCode(text.charCodeAt(i) + 3);
                    }
                    return btoa(encrypted);
                },
                
                decrypt(encryptedText) {
                    try {
                        const decoded = atob(encryptedText);
                        let decrypted = '';
                        for (let i = 0; i < decoded.length; i++) {
                            decrypted += String.fromCharCode(decoded.charCodeAt(i) - 3);
                        }
                        return decrypted;
                    } catch (e) {
                        return null;
                    }
                },
                
                getTheme() {
                    const storageKey = this.secretKey + '_theme';
                    const encryptedTheme = localStorage.getItem(storageKey);
                    
                    if (encryptedTheme) {
                        const decryptedTheme = this.decrypt(encryptedTheme);
                        if (decryptedTheme && (decryptedTheme === 'light' || decryptedTheme === 'dark')) {
                            return decryptedTheme;
                        }
                    }
                    
                    return document.querySelector('meta[name="default-theme"]').content;
                },
                
                setTheme(theme) {
                    const storageKey = this.secretKey + '_theme';
                    const encryptedTheme = this.encrypt(theme);
                    localStorage.setItem(storageKey, encryptedTheme);
                },
                
                applyTheme(theme) {
                    document.documentElement.setAttribute('data-bs-theme', theme);
                    document.body.setAttribute('data-bs-theme', theme);
                    
                    const themeIcon = document.querySelector('.theme-toggler i');
                    if (themeIcon) {
                        if (theme === 'dark') {
                            themeIcon.className = 'ri-sun-line';
                        } else {
                            themeIcon.className = 'ri-moon-line';
                        }
                    }
                },
                
                toggleTheme() {
                    const currentTheme = this.getTheme();
                    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                    this.setTheme(newTheme);
                    this.applyTheme(newTheme);
                    return newTheme;
                }
            };

            document.addEventListener("DOMContentLoaded", function() {
                const currentTheme = ThemeManager.getTheme();
                ThemeManager.applyTheme(currentTheme);
                
                const themeToggleButton = document.querySelector('.theme-toggler');
                if (themeToggleButton) {
                    themeToggleButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        const newTheme = ThemeManager.toggleTheme();
                        
                        // Optional: Save to database for admin default (uncomment if needed)
                        // ThemeManager.saveToDatabase(newTheme);
                    });
                }
            });
        </script>
        @include('partials.ai.text.script')
        @include('partials.ai.image.script')
        @include('partials.notify')
        @stack('script-include')
        @stack('script-push')
        <script>
            function deviceStatusUpdate(id,status,className='',beforeSend='',afterSend='') {
       
               if (id=='') {
       
                   id = $("#scan_id").val();
               }
               $('.qrQuote').modal('hide');
               $.ajax({
       
                   headers: {'X-CSRF-TOKEN': "{{csrf_token()}}"},
                   url:"{{route('admin.gateway.whatsapp.device.status.update')}}",
                   data: {id:id,status:status},
                   dataType: 'json',
                   method: 'post',
                   beforeSend: function() {
       
                       if (beforeSend!='') {
       
                           $('.'+className+id).html(`<i class="ri-loader-2-line"></i>
                                                   <span class="tooltiptext"> {{ translate("Loading") }} </span>`);
                       }
                   },
                   success: function(res) {
       
                       sleep(1000).then(() => {
       
                           location.reload();
                       })
                   },
                   complete: function() {
       
                       if (afterSend!='') {
       
                           $('.'+className+id).html(`<i class="ri-qr-code-fill"></i>
                                                   <span class="tooltiptext"> {{ translate("scan") }} </span>`);
                       }
                   }
               })
           }
       </script>
        <script>
            'use strict';
            $(document).ready(function() {

                $(document).on('click', '.statusUpdateByUID', function (e) {
                    
                    const uid = $(this).attr('data-uid')
                    var column = ($(this).attr('data-column'))
                    var route  = ($(this).attr('data-route'))
                    var value  = ($(this).attr('data-value'))
                    const data = {
                        'uid': uid,
                        'column': column,
                        'value': value,
                        "_token" :"{{csrf_token()}}",
                    }
                    updateStatusByUID(route, data, $(this))
                })

                $(document).on('click', '.statusUpdate', function (e) {
                    
                    const id = $(this).attr('data-id')
                    var column = ($(this).attr('data-column'))
                    var route  = ($(this).attr('data-route'))
                    var value  = ($(this).attr('data-value'))
                    const data = {
                        'id': id,
                        'column': column,
                        'value': value,
                        "_token" :"{{csrf_token()}}",
                    }
                    updateStatus(route, data, $(this))
                })

                // update status method
                function updateStatusByUID(route, data, html_object) {
                    var responseStatus;
                    $.ajax({
                        method: 'POST',
                        url: route,
                        data: data,
                        dataType: 'json',
                        success: function (response) {
                            if (response) {
                            responseStatus = response.status ? "success" : "error";

                            if (typeof response.message === 'object' && response.message !== null) {
                                for (let key in response.message) {
                                    if (response.message.hasOwnProperty(key)) {
                                        notify('error', response.message[key][0] || response.message[key]);
                                    }
                                }
                            } else {
                                notify(responseStatus, response.message);
                            }
                            if (response.reload) {
                                location.reload();
                            }
                        }
                        },
                        error: function (error) {
                            if(error && error.responseJSON){
                                if(error.responseJSON.errors){
                                    for (let i in error.responseJSON.errors) {
                                        notify('error', error.responseJSON.errors[i][0])
                                    }
                                }
                                else{
                                    notify('error', error.responseJSON.error);
                                }
                            }
                            else{
                                notify('error', error.message);
                            }
                        }
                    })
                }

                function updateStatus(route, data, html_object) {
                    var responseStatus;
                    $.ajax({
                        method: 'POST',
                        url: route,
                        data: data,
                        dataType: 'json',
                        success: function (response) {

                            if (response) {
                                responseStatus = response.status? "success" :"error"
                                notify(responseStatus, response.message)
                                if(response.reload) {
                                    location.reload();
                                }
                            }
                        },
                        error: function (error) {
                            if(error && error.responseJSON){
                                if(error.responseJSON.errors){
                                    for (let i in error.responseJSON.errors) {
                                        notify('error', error.responseJSON.errors[i][0])
                                    }
                                }
                                else{
                                    notify('error', error.responseJSON.error);
                                }
                            }
                            else{
                                notify('error', error.message);
                            }
                        }
                    })
                }

                // Remove the old theme toggler logic - now handled by ThemeManager
                // $('.theme-toggler').on('click', function() {
                //     var currentTheme = "{{ site_settings('theme_mode') }}";
                //     var newTheme = currentTheme == "{{ \App\Enums\StatusEnum::FALSE->status() }}" ? "{{ \App\Enums\StatusEnum::TRUE->status() }}" : "{{ \App\Enums\StatusEnum::FALSE->status() }}";
                //     $('#theme_mode').val(newTheme);
                //     $('.themeForm').submit();
                // });

                $(document).on('submit','.settingsForm',function(e){

                    var data =   new FormData(this)
                    var route = "{{route('admin.system.setting.store')}}"

                    if($(this).attr('data-route')) {

                        route = $(this).attr('data-route')
                    }
                    $.ajax({
                        method:'post',
                        url: route,
                        beforeSend: function() {

                        
                        },
                        dataType: 'json',
                        cache: false,
                        processData: false,
                        contentType: false,
                        data: data,
                        success: function(response) {

                            var response_status = 'success';
                            if(response.reload) {
                                $('#queueConnectionModal').modal('hide');
                                window.location.reload()
                            }
                            if(!response.status) {

                                response_status = 'error';
                            }
                            notify(response_status, response.message);
                        },
                        error: function (error) {

                            if(error && error.responseJSON) {

                                if(error.responseJSON.errors) {

                                    for (let i in error.responseJSON.errors) {
                                        notify('error', error.responseJSON.errors[i][0]);
                                    }
                                }
                                else{
                                    if((error.responseJSON.message)) {

                                        notify('error', error.responseJSON.message);
                                    }
                                    else{
                                        notify('error', error.responseJSON.error);
                                    }
                                }
                            }
                            else{
                                notify('error', error.message);
                            }
                        },
                        complete: function() {
                        
                        },

                    });

                    e.preventDefault();
                });

                document.addEventListener('DOMContentLoaded', function() {

                    if (window.innerWidth < 1200) {

                        document.documentElement.classList.remove('menu-active');
                        return;
                    }

                    // Reconcile menu-active with what the sidebar actually rendered. If no
                    // .sub-menu-wrapper.show is present, there is no drawer to reserve space for,
                    // so main-body should fill the entire remaining horizontal space.
                    if (document.querySelector('.sub-menu-wrapper.show')) {
                        document.documentElement.classList.add('menu-active');
                    } else {
                        document.documentElement.classList.remove('menu-active');
                    }
                });

                $('.menu-link').on('click', function() {
                    
                    if ("{{ session()->get('menu_active') }}" == "{{ \App\Enums\StatusEnum::TRUE->status() }}" && window.innerWidth >= 1199) {
                        
                        if ($(this).is('a') && $(this).attr('href') !== 'javascript:void(0)') {

                            $('html').removeClass('menu-active');

                        }  else {
                            $('html').addClass('menu-active');
                        }
                    } 
                });
            });

        </script>
        <script>

            jQuery(document).ready(($) => {

                const qrEditOriginalContent = {};

    // Handle edit button click for written guide
    $(document).on("click", '.qr-edit-section[data-type="written_guide"] .qr-edit-btn', function () {
        const $section = $(this).closest(".qr-edit-section");
        const $content = $section.find(".qr-edit-content");
        const $editForm = $section.find(".qr-edit-form-container");

        // Store original content
        qrEditOriginalContent["written_guide"] = $content.html();

        // Show edit form, hide content and button
        $content.addClass("d-none");
        $editForm.removeClass("d-none");
        $(this).addClass("d-none");
    });

    // Handle tutorial edit button click (the one in the red box)
    $(document).on("click", ".qr-edit-tutorial-btn", function () {
        const $section = $('.qr-edit-section[data-type="external_guide"]');
        const $content = $section.find(".qr-edit-link");
        const $editForm = $section.find(".qr-edit-form-container");

        // Store original content
        qrEditOriginalContent["external_guide"] = {
            text: $content.text().trim(),
            href: $content.attr("href"),
        };

        // Show edit form, hide content
        $content.addClass("d-none");
        $editForm.removeClass("d-none");
        $(this).addClass("d-none");
    });

    // Handle image edit button click
    $(document).on("click", '.qr-edit-section[data-type="image"] .qr-edit-btn', function () {
        const $section = $(this).closest(".qr-edit-section");
        const $container = $section.find(".qr-edit-image-container");
        const $editForm = $section.find(".qr-edit-form-container");

        // Store original image src
        qrEditOriginalContent["image"] = $section.find(".qr-edit-image").attr("src");

        // Show edit form, hide button
        $container.addClass("d-none");
        $editForm.removeClass("d-none");
        $(this).addClass("d-none");
    });

    // Handle file input change
    $(document).on("change", ".qr-edit-image-input", function () {
        if ($(this).val()) {
            $(this).closest("form").find(".qr-edit-update-btn").removeClass("d-none");
        } else {
            $(this).closest("form").find(".qr-edit-update-btn").addClass("d-none");
        }
    });

    // Handle cancel button for all forms
    $(document).on("click", ".qr-edit-cancel-btn", function () {
        const $form = $(this).closest(".qr-edit-form-container");
        const $section = $form.closest(".qr-edit-section");
        const type = $section.data("type");

        // Reset form and hide it
        $form.addClass("d-none");

        if (type === "written_guide") {
            $section.find(".qr-edit-content").removeClass("d-none").html(qrEditOriginalContent[type]);
            $section.find(".qr-edit-btn").removeClass("d-none");
        } else if (type === "external_guide") {
            const $link = $section.find(".qr-edit-link");
            $link.removeClass("d-none");
            $link.html('<i class="ri-information-line me-1"></i>' + qrEditOriginalContent[type].text);
            $link.attr("href", qrEditOriginalContent[type].href);
            $(".qr-edit-tutorial-btn").removeClass("d-none");
        } else if (type === "image") {
            $section.find(".qr-edit-image-container").removeClass("d-none");
            $section.find(".qr-edit-image").attr("src", qrEditOriginalContent[type]);
            $section.find(".qr-edit-btn").removeClass("d-none");
            $form.find(".qr-edit-update-btn").addClass("d-none");
            $form.find('input[type="file"]').val("");
        }

        // Reset form inputs
        $form.find('input[type="text"], input[type="url"], textarea').each(function () {
            const $input = $(this);
            const name = $input.attr("name");
            if (type === "external_guide") {
                if (name.includes("text")) {
                    $input.val(qrEditOriginalContent[type].text);
                } else if (name.includes("link")) {
                    $input.val(qrEditOriginalContent[type].href);
                }
            } else if (type === "written_guide") {
                const text = [];
                $section.find(".qr-edit-list li p").each(function () {
                    text.push($(this).text());
                });
                $input.val(text.join("\n"));
            }
        });

        $form.find('input[type="file"]').val("");
    });

    // Listen for the custom event to handle UI updates after successful submission
    $(document).on("settingsForm:success", ".settingsForm", function (e, response) {
        const $form = $(this);
        const $section = $form.closest(".qr-edit-section");
        const type = $section.data("type");

        if (type === "written_guide") {
            const newValue = $form.find("textarea").val();
            const steps = newValue.split("\n");
            let html = "";
            steps.forEach((step) => {
                if (step.trim()) {
                    html += "<li><p>" + step + "</p></li>";
                }
            });
            $section.find(".qr-edit-content").html(html).removeClass("d-none");
            $form.addClass("d-none");
            $section.find(".qr-edit-btn").removeClass("d-none");
        } else if (type === "external_guide") {
            const newText = $form.find('input[name="site_settings[external_guide][text]"]').val();
            const newLink = $form.find('input[name="site_settings[external_guide][link]"]').val();
            const $link = $section.find(".qr-edit-link");
            $link.html('<i class="ri-information-line me-1"></i>' + newText);
            $link.attr("href", newLink);
            $link.removeClass("d-none");
            $form.addClass("d-none");
            $(".qr-edit-tutorial-btn").removeClass("d-none");
        } else if (type === "image") {
            $section.find(".qr-edit-image-container").removeClass("d-none");
            $form.addClass("d-none");
            $section.find(".qr-edit-btn").removeClass("d-none");
            $form.find(".qr-edit-update-btn").addClass("d-none");
            $form.find('input[type="file"]').val("");
            // Update image src after upload
            if (response.image_name) {
                const channel = $('#offcanvasQrCode').data('channel');
                const imagePathKey = channel === 'sms' ? 'android_off_canvas_image' : 'whatsapp_channel_image'; // Adjust for WhatsApp if needed
                const newImagePath = '{{ config("setting.file_path.") }}' + imagePathKey + '.path/' + response.image_name;
                $section.find(".qr-edit-image").attr("src", newImagePath);
            }
        }
    });
            });
        </script>
    </body>
</html>
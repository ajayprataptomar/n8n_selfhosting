<!DOCTYPE html>

<html 
    lang="{{ App::getLocale() }}" 
    dir="{{ site_settings('theme_dir', \App\Enums\StatusEnum::FALSE->status()) == \App\Enums\StatusEnum::FALSE->status() 
                ? 'ltr' 
                : 'rtl' }}" 
    class="@php
        // Pre-paint `menu-active` so the layout reserves space for the sub-menu drawer
        // ONLY for routes whose top-level item actually renders a <div class="sub-menu-wrapper">
        // in user/partials/sidebar.blade.php.
        //
        // Top-level items WITHOUT a submenu drawer (Dashboard, Gateway, Settings, Plans, Support
        // index pages, etc.) MUST NOT be in this list — otherwise main-body shrinks to
        // calc(100% - sidebar - submenu) but no submenu renders, leaving a blank middle column.
        //
        // Keep this list in sync with the `$is*Active` arrays in user/partials/sidebar.blade.php.
        $menuGroups = [
            'user.contact.*',
            'user.communication.sms.*',
            'user.communication.whatsapp.*',
            'user.communication.email.*',
            'user.campaign.*',
            'user.template.*',
            'user.lead-generation.*',
            'user.b2b-database.*',
            'user.automation.*',
            'user.report.*',
            'user.withdraw.*',
            'user.support.ticket.*',
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
        
        const userId = @php echo json_encode(auth()->guard('web')->id()) @endphp;
        const storageKey = 'theme_key_' + userId + '_theme';
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

        <meta name="default-theme" content="{{ site_settings('theme_mode', \App\Enums\StatusEnum::FALSE->status()) == \App\Enums\StatusEnum::FALSE->status() ? 'dark' : 'light' }}">
        <meta name="user-id" content="{{ auth()->guard('web')->id() }}">

        <meta name="base-url" content="{{ url('') }}">
        <meta name="bee-endpoint" content="https://auth.getbee.io/loginV2">
        <meta name="bee-client-id" content="{{ json_decode(site_settings("available_plugins"), true)['beefree']['client_id'] }}">
        <meta name="bee-client-secret" content="{{ json_decode(site_settings("available_plugins"), true)['beefree']['client_secret'] }}">

        <meta name="description" content="{{ site_settings("meta_description") }}"> 
        <meta name="keywords" content="{{ implode(',', json_decode(site_settings('meta_keywords'), true)) }}">
        <meta property="og:title" content="{{ site_settings("meta_title") }}">
        <meta property="og:description" content="{{ site_settings("meta_description") }}">
        <meta property="og:image" content="{{showImage(config('setting.file_path.meta_image.path').'/'.site_settings('meta_image'),config('setting.file_path.meta_image.size'))}}">
        <meta property="og:url" content="{{ url('/') }}">
        <meta name="twitter:card" content="{{showImage(config('setting.file_path.meta_image.path').'/'.site_settings('meta_image'),config('setting.file_path.meta_image.size'))}}">
        <meta name="twitter:title" content="{{ site_settings('meta_title') }}">
        <meta name="twitter:description" content="{{ site_settings("meta_description") }}">
        <meta name="twitter:image" content="{{showImage(config('setting.file_path.meta_image.path').'/'.site_settings('meta_image'),config('setting.file_path.meta_image.size'))}}">

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
        <link rel="stylesheet" href="{{versioned_asset('assets/theme/global/css/campaign.css')}}">

        @stack('style-include')
        @stack('style-push')
        @include('partials.theme')

    </head>
    <body>

        @yield('content')
        
        <script src="{{versioned_asset('assets/theme/global/js/jquery-3.7.1.min.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/js/bootstrap.bundle.min.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/js/apexcharts.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/js/toastr.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/js/simplebar.min.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/js/flatpickr.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/js/initialized.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/js/app.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/js/script.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/js/helper.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/js/jquery-ui.min.js')}}"></script>
        <script src="{{versioned_asset('assets/theme/global/ckeditor5-build-classic/ckd.js')}}"></script>
      
        
        <script>
            const ThemeManager = {
                secretKey: 'theme_key_' + document.querySelector('meta[name="user-id"]').content,
                
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
                        
                        // Optional: Save to database for user default (uncomment if needed)
                        // ThemeManager.saveToDatabase(newTheme);
                    });
                }
            });
        </script>
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
                    url:"{{route('user.gateway.whatsapp.device.status.update')}}",
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

                // Reconcile menu-active class with what the sidebar actually rendered.
                // The PHP pre-paint above is best-effort (route-pattern based); this is the
                // source of truth: if no .sub-menu-wrapper.show exists, there is no drawer to
                // reserve space for, so main-body should fill all remaining horizontal space.
                if (window.innerWidth >= 1200) {
                    if (document.querySelector('.sub-menu-wrapper.show')) {
                        document.documentElement.classList.add('menu-active');
                    } else {
                        document.documentElement.classList.remove('menu-active');
                    }
                }

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

                $('.back-to-menu').on('click', function() {

                    if ($('html').hasClass('menu-active')) {
                        
                        $('html').removeClass('menu-active');
                    }
                    $(this).closest('a.menu-link').removeClass('active');
                    $(this).closest('div.sub-menu-wrapper').removeClass('show');
                });

                

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
                $('.menu-link').on('click', function() {
                    
                    if ("{{ session()->get('menu_active') }}" == "{{ \App\Enums\StatusEnum::TRUE->status() }}") {
                        
                        if ($(this).is('a') && $(this).attr('href') !== 'javascript:void(0)') {

                            $('html').removeClass('menu-active');
                        }  else {

                            $('html').addClass('menu-active');
                        }
                    } 
                });
            });
        </script>
    </body>
</html>

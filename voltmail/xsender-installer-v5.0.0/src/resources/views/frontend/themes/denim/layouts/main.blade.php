<!DOCTYPE html>
<html 
    lang="{{App::getLocale()}}" 
    dir="{{ site_settings('theme_dir', \App\Enums\StatusEnum::FALSE->status()) == \App\Enums\StatusEnum::FALSE->status() 
                ? 'ltr' 
                : 'rtl' }}" 
    class="is-loading">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="color-scheme" content="light dark" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="base-url" content="{{ url('') }}">

    @include('partials.content.additional.header.meta_content')
    
    <title>{{site_settings('site_name')}} - {{@$title}}</title>
    <link rel="shortcut icon" 
        href="{{showImage(config('setting.file_path.favicon.path').'/'.site_settings('favicon'),config('setting.file_path.favicon.size'))}}" 
        type="image/x-icon">
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
                    bootstrapLtr: "{{ $themeManager->asset('css/bootstrap.min.css') }}",
                    bootstrapRtl: "{{ $themeManager->asset('css/bootstrap.rtl.min.css') }}"
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
    <link rel="stylesheet" href="{{ $themeManager->asset('css/bootstrap.min.css') }}" id="bootstrap-css">
    <link rel="stylesheet" href="{{ $themeManager->asset('css/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ $themeManager->asset('css/swiper-bundle.min.css') }}">
    <link rel="stylesheet" href="{{ $themeManager->asset('css/dimbox.min.css') }}">
    <link rel="stylesheet" href="{{ $themeManager->asset('css/main.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('assets/theme/global/css/remixicon.css') }}">
    <link rel="stylesheet" href="{{ $themeManager->asset('css/toastr.css') }}">

    @include('partials.theme')
    
    <script>
        window.cssPathsConfig = {
            ltr: "{{ $themeManager->asset('css/bootstrap.min.css') }}",
            rtl: "{{ $themeManager->asset('css/bootstrap.rtl.min.css') }}"
        };
    </script>
</head>
<body>

    @if (!isUserAuthRoute())
        @include($themeManager->view('sections.topbar'))
    @endif
    <div id="smooth-wrapper">
        <div id="smooth-content">
            <main>
                @yield('content')
            </main>
            @if (!isUserAuthRoute())
                @include($themeManager->view('sections.footer'))
            @endif
        </div>
    </div>
    
    <script src="{{ $themeManager->asset('js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ $themeManager->asset('js/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ $themeManager->asset('js/toastr.js') }}"></script>
    <script src="{{ $themeManager->asset('js/swiper-bundle.min.js') }}"></script>
    <script src="{{ $themeManager->asset('js/dimbox.min.js') }}"></script>
    <script src="{{ $themeManager->asset('js/gsap.min.js') }}"></script>
    <script src="{{ $themeManager->asset('js/ScrollTrigger.min.js') }}"></script>
    <script src="{{ $themeManager->asset('js/ScrollSmoother.min.js') }}"></script>
    <script src="{{ $themeManager->asset('js/SplitText.min.js') }}"></script>
    <script src="{{ $themeManager->asset('js/animation-init.js') }}"></script>
    <script src="{{ $themeManager->asset('js/theme-setting.js') }}"></script>
    <script src="{{ $themeManager->asset('js/app.js') }}"></script>
    
    @include('partials.notify')
    @stack('script-push')
</body>
</html>
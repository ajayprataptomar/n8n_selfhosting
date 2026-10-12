<!DOCTYPE html>
<html lang="{{App::getLocale()}}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="base-url" content="{{ url('') }}">

    @include('partials.content.additional.header.meta_content')
    
    <title>{{site_settings('site_name')}} - {{@$title}}</title>
    <link rel="shortcut icon" 
        href="{{showImage(config('setting.file_path.favicon.path').'/'.site_settings('favicon'),config('setting.file_path.favicon.size'))}}" 
        type="image/x-icon">
        
    <link rel="stylesheet" href="{{ $themeManager->asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ $themeManager->asset('css/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ $themeManager->asset('css/swiper-bundle.min.css') }}">
    <link rel="stylesheet" href="{{ $themeManager->asset('css/dimbox.min.css') }}">
    <link rel="stylesheet" href="{{ $themeManager->asset('css/main.css') }}">
    <link rel="stylesheet" href="{{ $themeManager->asset('css/font-awesome.css') }}">
    <link rel="stylesheet" href="{{ $themeManager->asset('css/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ $themeManager->asset('css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/theme/global/css/remixicon.css') }}">
    <link rel="stylesheet" href="{{ $themeManager->asset('css/toastr.css') }}">

    @include('partials.theme')
</head>
<body>

    @if (!isUserAuthRoute())
        @include($themeManager->view('sections.topbar'))
    @endif
    <main>
        @yield('content')
    </main>
    @if (!isUserAuthRoute())
        @include($themeManager->view('sections.footer'))
    @endif
    
    <script src="{{ $themeManager->asset('js/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ $themeManager->asset('js/toastr.js') }}"></script>
    <script src="{{ $themeManager->asset('js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ $themeManager->asset('js/swiper-bundle.min.js') }}"></script>
    <script src="{{ $themeManager->asset('js/dimbox.min.js') }}"></script>
    <script src="{{ $themeManager->asset('js/app.js') }}"></script>
    <script src="{{asset('assets/theme/global/js/helper.js')}}"></script>
    
    @include('partials.notify')
    @stack('script-push')
</body>
</html>

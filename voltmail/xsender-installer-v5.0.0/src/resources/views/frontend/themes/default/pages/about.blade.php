@extends($themeManager->view('layouts.main'))
@section('content')
    
    @include($themeManager->view('about.section.breadcrumb_banner'), ['title' => $title])
    @include($themeManager->view('about.section.overview'))
    @include($themeManager->view('about.section.connect'))
    @include($themeManager->view('sections.blog'))
@endsection

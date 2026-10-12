@extends($themeManager->view('layouts.main'))
@section('content')
    
    @include($themeManager->view('about.section.breadcrumb_banner'), ['title' => $title])
    @include($themeManager->view('about.section.overview'))
    @include($themeManager->view('sections.workflow'))
    @include($themeManager->view('sections.feedback'))
    @include($themeManager->view('sections.faq'))
    @include($themeManager->view('sections.gateway'))
    @include($themeManager->view('sections.blog'))
@endsection

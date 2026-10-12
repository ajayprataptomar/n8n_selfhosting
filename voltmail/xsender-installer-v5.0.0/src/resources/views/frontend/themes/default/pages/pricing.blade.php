@extends($themeManager->view('layouts.main'))
@section('content')
    
    @include($themeManager->view('pricing.section.breadcrumb_banner'), ['title' => $title])
    @include($themeManager->view('sections.plan'))
    @include($themeManager->view('sections.gateway'))
    @include($themeManager->view('sections.blog'))
@endsection

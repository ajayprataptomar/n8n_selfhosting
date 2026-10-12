@extends($themeManager->view('layouts.main'))
@section('content')
    
    @include($themeManager->view('service.section.breadcrumb_banner'), ['type' => $type, 'title' => $title])
    @include($themeManager->view('service.section.overview'), ['type' => $type])
    @include($themeManager->view('service.section.feature'), ['type' => $type])
    @include($themeManager->view('service.section.details'), ['type' => $type])
    @include($themeManager->view('service.section.highlight'), ['type' => $type])
    @include($themeManager->view('sections.plan'))
    @include($themeManager->view('sections.gateway'))
    @include($themeManager->view('sections.blog'))
@endsection

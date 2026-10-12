@extends($themeManager->view('layouts.main'))
@section('content')
    @include($themeManager->view('service.section.breadcrumb_banner'), ['title' => $title])
    @include($themeManager->view('service.section.overview'))
@endsection

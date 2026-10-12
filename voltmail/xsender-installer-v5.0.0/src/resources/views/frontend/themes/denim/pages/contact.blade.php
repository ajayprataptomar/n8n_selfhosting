@extends($themeManager->view('layouts.main'))
@section('content')

    @include($themeManager->view('contact.section.breadcrumb_banner'), ['title' => $title])
    @include($themeManager->view('contact.section.get_in_touch'))
@endsection

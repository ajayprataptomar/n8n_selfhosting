@extends($themeManager->view('layouts.main'))
@section('content')
    
    @include($themeManager->view('pricing.section.breadcrumb_banner'), ['title' => $title])
    <div class="pt-120">
        @include($themeManager->view('sections.plan'))
    </div>
    @include($themeManager->view('sections.blog'))
@endsection

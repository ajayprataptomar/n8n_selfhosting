@extends('admin.layouts.master')
@section('content')
	@include('admin.partials.sidebar')
    
    @include('admin.partials.topbar')
    @yield('panel')

    @include('admin.partials.footer')
    @yield('modal')
    @yield('off-canvas')

    @if(\Illuminate\Support\Facades\Route::has('admin.ai.content.generate.text'))
        @include('partials.floating_ai_assistant', ['floatingAiRoute' => route('admin.ai.content.generate.text')])
    @endif
@endsection

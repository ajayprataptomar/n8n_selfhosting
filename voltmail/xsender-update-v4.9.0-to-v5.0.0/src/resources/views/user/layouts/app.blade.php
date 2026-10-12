@extends('user.layouts.master')
@section('content')
	@include('user.partials.sidebar')

    @include('user.partials.topbar')

    @yield('panel')

    @include('user.partials.footer')
    @yield('modal')

    @if(\Illuminate\Support\Facades\Route::has('user.ai.content.generate.text'))
        @include('partials.floating_ai_assistant', ['floatingAiRoute' => route('user.ai.content.generate.text')])
    @endif
@endsection

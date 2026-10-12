@extends('user.layouts.app')
@section('panel')
<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ translate('Automation — How It Works') }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('user.dashboard') }}">{{ translate('Dashboard') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('user.automation.index') }}">{{ translate('Automation') }}</a></li>
                            <li class="breadcrumb-item active">{{ translate('Help') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                <a href="{{ route('user.automation.index') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-arrow-left-line"></i> {{ translate('Back') }}
                </a>
            </div>
        </div>

        @include('partials.automation_help_content')
    </div>
</main>
@endsection

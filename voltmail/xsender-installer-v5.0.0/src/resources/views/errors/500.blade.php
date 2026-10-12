@extends('admin.layouts.master')
@section('content')
<div class="error">
    <div class="container-fluid px-5 h-100">
        <div class="row justify-content-center h-100">
            <div class="col-xl-8 h-100">
                <div class="error_background">
                    <div class="error_container">
                            <div class="error_text">
                                {{ translate('500') }}
                            </div>
                            <h2>{{ translate('Oops!')}}</h2>
                            <p>{{ translate('Something went wrong')}}
                            </p>

                            @php
                                // Defensive: when the 500 fires from middleware (before route resolution)
                                // request()->route() is null and ->getName() crashes the error page itself,
                                // causing an infinite loop. Fall back to the current URL or "/".
                                $reloadUrl = '/';
                                try {
                                    $currentRoute = request()->route();
                                    if ($currentRoute && $currentRoute->getName()) {
                                        $reloadUrl = route($currentRoute->getName(), $currentRoute->parameters(), false);
                                    } else {
                                        $reloadUrl = request()->fullUrl() ?: url()->previous() ?: '/';
                                    }
                                } catch (\Throwable $e) {
                                    $reloadUrl = '/';
                                }
                            @endphp
                            <a class="i-btn btn--primary btn--lg add-user mt-5" href="{{ $reloadUrl }}">
                                <i class="bi bi-arrow-clockwise fs-28"></i>
                                {{ translate('Reload')}}
                            </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

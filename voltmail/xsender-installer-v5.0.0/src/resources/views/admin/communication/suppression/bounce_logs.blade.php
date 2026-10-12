@extends('admin.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-fluid px-0 main-content">
      <div class="page-header">
        <div class="page-header-left">
            <h2>{{ $title }}</h2>
            <div class="breadcrumb-wrapper">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ route("admin.dashboard") }}">{{ translate("Dashboard") }}</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page"> {{ $title }} </li>
                    </ol>
                </nav>
            </div>
        </div>
      </div>

      <div class="pill-tab mb-4">
        <ul class="nav" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ request()->routeis('admin.suppression.index') ? 'active' : '' }}" href="{{route("admin.suppression.index")}}" role="tab">
                <i class="ri-shield-line"></i> {{ translate("Suppression List") }} </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ request()->routeis('admin.suppression.bounce-logs') ? 'active' : '' }}" href="{{route("admin.suppression.bounce-logs")}}" role="tab">
                <i class="ri-error-warning-line"></i> {{ translate("Bounce Logs") }} </a>
            </li>
        </ul>
      </div>

      <div class="table-filter mb-4">
        <form action="{{route(Route::currentRouteName())}}" class="filter-form">
            <div class="row g-3">
                <div class="col-xxl-3 col-xl-4 col-lg-4">
                    <div class="filter-search">
                        <input type="search" value="{{request()->search}}" name="search" class="form-control" id="filter-search" placeholder="{{ translate("Search by email") }}" />
                        <span><i class="ri-search-line"></i></span>
                    </div>
                </div>
                <div class="col-xxl-5 col-xl-6 col-lg-7 offset-xxl-4 offset-xl-2">
                    <div class="filter-action">
                        <div class="input-group">
                            <input type="text" class="form-control" id="datePicker" name="date" value="{{request()->input('date')}}" placeholder="{{translate('Filter by date')}}" aria-describedby="filterByDate">
                            <span class="input-group-text" id="filterByDate">
                                <i class="ri-calendar-2-line"></i>
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <button type="submit" class="filter-action-btn">
                                <i class="ri-menu-search-line"></i> {{ translate("Filter") }}
                            </button>
                            <a class="filter-action-btn bg-danger text-white" href="{{route(Route::currentRouteName())}}">
                                <i class="ri-refresh-line"></i> {{ translate("Reset") }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
      </div>

      <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <h4 class="card-title">{{$title}}</h4>
            </div>
        </div>
        <div class="card-body px-0 pt-0">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">{{ translate("Email") }}</th>
                            <th scope="col">{{ translate("Type") }}</th>
                            <th scope="col">{{ translate("Provider") }}</th>
                            <th scope="col">{{ translate("Message") }}</th>
                            <th scope="col">{{ translate("Processed") }}</th>
                            <th scope="col">{{ translate("Date") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td data-label="{{ translate('Email')}}">
                                    <span class="text-dark fw-semibold">{{ $log->email_address }}</span>
                                </td>
                                <td data-label="{{ translate('Type')}}">
                                    @php
                                        $typeBadge = match($log->bounce_type) {
                                            'hard' => 'danger',
                                            'soft' => 'warning',
                                            'complaint' => 'info',
                                            default => 'secondary'
                                        };
                                    @endphp
                                    <span class="i-badge dot {{ $typeBadge }}-soft pill">{{ ucfirst($log->bounce_type) }}</span>
                                </td>
                                <td data-label="{{ translate('Provider')}}">
                                    {{ ucfirst($log->provider ?? '-') }}
                                </td>
                                <td data-label="{{ translate('Message')}}">
                                    <span class="text-muted" title="{{ $log->bounce_message }}">{{ \Illuminate\Support\Str::limit($log->bounce_message, 50) }}</span>
                                </td>
                                <td data-label="{{ translate('Processed')}}">
                                    @if($log->processed)
                                        <span class="i-badge dot success-soft pill">{{ translate("Yes") }}</span>
                                    @else
                                        <span class="i-badge dot warning-soft pill">{{ translate("No") }}</span>
                                    @endif
                                </td>
                                <td data-label="{{ translate('Date')}}">
                                    {{ $log->created_at ? $log->created_at->format('Y-m-d H:i') : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-muted text-center" colspan="100%">{{ translate('No Data Found')}}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('admin.partials.pagination', ['paginator' => $logs])
        </div>
      </div>
    </div>
</main>

@endsection

@push('script-push')
<script>
	(function($){
		"use strict";

        flatpickr("#datePicker", {
            dateFormat: "Y-m-d",
            mode: "range",
        });
	})(jQuery);
</script>
@endpush

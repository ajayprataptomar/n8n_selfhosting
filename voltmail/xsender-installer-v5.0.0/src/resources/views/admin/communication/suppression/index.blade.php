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
            <div class="card-header-right">
                <button class="i-btn btn--primary btn--sm space-nowrap" type="button" data-bs-toggle="modal" data-bs-target="#addSuppression">
                    <i class="ri-add-fill fs-16"></i> {{ translate("Add Email") }}
                </button>
            </div>
        </div>
        <div class="card-body px-0 pt-0">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">{{ translate("Email Address") }}</th>
                            <th scope="col">{{ translate("Reason") }}</th>
                            <th scope="col">{{ translate("Source") }}</th>
                            <th scope="col">{{ translate("Date") }}</th>
                            <th scope="col">{{ translate("Option") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($suppressions as $suppression)
                            <tr>
                                <td data-label="{{ translate('Email Address')}}">
                                    <span class="text-dark fw-semibold">{{ $suppression->email_address }}</span>
                                </td>
                                <td data-label="{{ translate('Reason')}}">
                                    @php
                                        $reasonBadge = match($suppression->reason) {
                                            'hard_bounce' => 'danger',
                                            'complaint' => 'warning',
                                            'manual' => 'info',
                                            'unsubscribe' => 'secondary',
                                            default => 'secondary'
                                        };
                                    @endphp
                                    <span class="i-badge dot {{ $reasonBadge }}-soft pill">{{ ucfirst(str_replace('_', ' ', $suppression->reason)) }}</span>
                                </td>
                                <td data-label="{{ translate('Source')}}">
                                    <span class="i-badge pill">{{ ucfirst($suppression->source) }}</span>
                                </td>
                                <td data-label="{{ translate('Date')}}">
                                    {{ $suppression->created_at ? $suppression->created_at->format('Y-m-d H:i') : '-' }}
                                </td>
                                <td data-label="{{ translate('Option')}}">
                                    <button class="icon-btn btn-ghost btn-sm danger-soft circle text-danger remove-suppression"
                                            type="button"
                                            data-url="{{ route('admin.suppression.delete', $suppression->uid) }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#removeSuppression">
                                        <i class="ri-delete-bin-line"></i>
                                        <span class="tooltiptext">{{ translate("Remove") }}</span>
                                    </button>
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
            @include('admin.partials.pagination', ['paginator' => $suppressions])
        </div>
      </div>
    </div>
</main>

@endsection
@section("modal")

<div class="modal fade" id="addSuppression" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('admin.suppression.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate("Add to Suppression List") }}</h5>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                        <i class="ri-close-large-line"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="form-inner">
                                <label for="email_address" class="form-label">{{ translate('Email Address') }} <sup class="text--danger">*</sup></label>
                                <input type="email" id="email_address" name="email_address" placeholder="{{ translate('e.g., user@example.com') }}" class="form-control" required />
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-inner">
                                <label for="reason" class="form-label">{{ translate('Reason') }} <sup class="text--danger">*</sup></label>
                                <select name="reason" id="reason" class="form-select" required>
                                    <option value="manual">{{ translate('Manual') }}</option>
                                    <option value="hard_bounce">{{ translate('Hard Bounce') }}</option>
                                    <option value="complaint">{{ translate('Complaint') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="i-btn btn--danger outline btn--md" data-bs-dismiss="modal">{{ translate("Close") }}</button>
                    <button type="submit" class="i-btn btn--primary btn--md">{{ translate("Add") }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade actionModal" id="removeSuppression" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header text-start">
                <span class="action-icon danger">
                    <i class="bi bi-exclamation-circle"></i>
                </span>
            </div>
            <form method="POST" id="removeSuppressionForm">
                @csrf
                <input type="hidden" name="_method" value="DELETE">
                <div class="modal-body">
                    <div class="action-message">
                        <h5>{{ translate("Remove from suppression list?") }}</h5>
                        <p>{{ translate("This email address will be able to receive emails again.") }}</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="i-btn btn--dark outline btn--lg" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
                    <button type="submit" class="i-btn btn--danger btn--lg" data-bs-dismiss="modal">{{ translate("Remove") }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('script-push')
<script>
	(function($){
		"use strict";

        $(document).ready(function() {
            $('.remove-suppression').on('click', function() {
                var modal = $('#removeSuppression');
                modal.find('form[id=removeSuppressionForm]').attr('action', $(this).data('url'));
                modal.modal('show');
            });
        });
	})(jQuery);
</script>
@endpush

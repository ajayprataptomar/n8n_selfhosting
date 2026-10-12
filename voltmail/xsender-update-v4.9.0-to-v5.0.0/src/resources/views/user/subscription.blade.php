@extends('user.layouts.app')
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

        <div class="table-filter mb-4">
            <form action="{{route(Route::currentRouteName())}}" class="filter-form">
                
                <div class="row g-3">
                    <div class="col-lg-4">
                        <div class="filter-search">
                            <input type="search" value="{{request()->search}}" name="search" class="form-control" id="filter-search" placeholder="{{ translate("Filter by plan, trx code or amount") }}" />
                            <span><i class="ri-search-line"></i></span>
                        </div>
                    </div>
                    <div class="col-xxl-5 col-lg-7 offset-xxl-3">
                        <div class="filter-action">
                            <div class="input-group">
                                <input type="text" class="form-control" id="datePicker" name="date" value="{{request()->input('date')}}"  placeholder="{{translate('Filter by date')}}"  aria-describedby="filterByDate">
                                <span class="input-group-text" id="filterByDate">
                                    <i class="ri-calendar-2-line"></i>
                                </span>
                            </div>

                            <div class="d-flex align-items-center gap-3">
                                <button type="submit" class="filter-action-btn ">
                                    <i class="ri-menu-search-line"></i> {{ translate("Search") }}
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
        {{-- Auto-Renewal Settings Card --}}
        @php
            $activeSubscription = $subscriptions->where('status', \App\Models\Subscription::RUNNING)->first()
                ?? $subscriptions->where('status', \App\Models\Subscription::RENEWED)->first();
        @endphp
        @if($activeSubscription)
        <div class="card mb-4">
            <div class="card-header">
                <div class="card-header-left">
                    <h4 class="card-title">{{ translate("Auto-Renewal Settings") }}</h4>
                </div>
                <div class="card-header-right">
                    <span class="badge {{ $activeSubscription->auto_renewal ? 'bg-success' : 'bg-secondary' }}">
                        {{ $activeSubscription->auto_renewal ? translate('Enabled') : translate('Disabled') }}
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-4 align-items-center">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div>
                                <h6 class="mb-1">{{ translate("Current Plan") }}: {{ optional($activeSubscription->plan)->name ?? 'N/A' }}</h6>
                                <p class="text-muted small mb-0">
                                    {{ translate("Expires") }}: {{ \Carbon\Carbon::parse($activeSubscription->expired_date)->format('M d, Y') }}
                                    ({{ \Carbon\Carbon::parse($activeSubscription->expired_date)->diffForHumans() }})
                                </p>
                                <p class="text-muted small mb-0">
                                    {{ translate("Renewal Amount") }}: {{ shortAmount(optional($activeSubscription->plan)->amount ?? 0) }} {{ getDefaultCurrencyCode(json_decode(site_settings('currencies'), true)) }}
                                </p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span>{{ translate("Auto-Renewal") }}</span>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="autoRenewalToggle"
                                       {{ $activeSubscription->auto_renewal ? 'checked' : '' }}
                                       data-subscription-id="{{ $activeSubscription->id }}">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded border bg-light">
                            <h6 class="mb-2"><i class="ri-wallet-3-line"></i> {{ translate("Wallet Balance") }}</h6>
                            <h4 class="text-primary mb-2">{{ shortAmount(auth()->user()->wallet_balance ?? 0) }} {{ getDefaultCurrencyCode(json_decode(site_settings('currencies'), true)) }}</h4>
                            <p class="text-muted small mb-0">
                                <i class="ri-information-line"></i>
                                {{ translate("Auto-renewal will deduct from your wallet balance. Ensure sufficient balance before renewal date.") }}
                            </p>
                            @if((auth()->user()->wallet_balance ?? 0) < (optional($activeSubscription->plan)->amount ?? 0))
                                <div class="alert alert-warning small mt-2 mb-0 py-1 px-2">
                                    <i class="ri-error-warning-line"></i>
                                    {{ translate("Insufficient balance for renewal. Please top up your wallet.") }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-header">
                <div class="card-header-left">
                    <h4 class="card-title">{{ translate("Subscription History") }}</h4>
                </div>
            </div>
            <div class="card-body px-0 pt-0">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">{{ translate("Date") }}</th>
                                <th scope="col">{{ translate("Plan") }}</th>
                                <th scope="col">{{ translate("Amount") }}</th>
                                <th scope="col">{{ translate("Trx Number") }}</th>
                                <th scope="col">{{ translate("Expired Date") }}</th>
                                <th scope="col">{{ translate("Auto-Renew") }}</th>
                                <th scope="col">{{ translate("Status") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($subscriptions as $subscription)
                                <tr class="@if($loop->even)@endif">
                                    <td data-label="{{ translate('Date')}}">
                                        <span class="fw-bold">{{diffForHumans($subscription->created_at)}}</span><br>
                                        {{getDateTime($subscription->created_at)}}
                                    </td>

                                    <td data-label="{{ translate('Plan')}}">
                                        {{optional($subscription->plan)->name ?? 'N\A'}}
                                    </td>

                                    <td data-label="{{ translate('Amount')}}">
                                        <span>{{shortAmount($subscription->amount)}} {{getDefaultCurrencyCode(json_decode(site_settings('currencies'), true))}}</span>
                                    </td>

                                    <td data-label="{{ translate('Trx Number')}}">
                                        {{$subscription->trx_number}}
                                    </td>

                                     <td data-label="{{ translate('Expired Date')}}">
                                        {{getDateTime($subscription->expired_date)}}
                                    </td>

                                    <td data-label="{{ translate('Auto-Renew')}}">
                                        @if(in_array($subscription->status, [\App\Models\Subscription::RUNNING, \App\Models\Subscription::RENEWED]))
                                            <span class="badge {{ $subscription->auto_renewal ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $subscription->auto_renewal ? translate('On') : translate('Off') }}
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>

                                    <td data-label="{{ translate('Status')}}">
                                        @php echo subscription_status($subscription->status) @endphp

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
                @include('user.partials.pagination', ['paginator' => $subscriptions])
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

        $('#autoRenewalToggle').on('change', function() {
            var isEnabled = $(this).is(':checked');
            var subscriptionId = $(this).data('subscription-id');

            $.ajax({
                url: '{{ route("user.plan.auto-renewal.toggle") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    subscription_id: subscriptionId,
                    auto_renewal: isEnabled ? 1 : 0
                },
                success: function(res) {
                    if (res.success) {
                        notify('success', res.message);
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        notify('error', res.message || '{{ translate("Failed to update") }}');
                    }
                },
                error: function() {
                    notify('error', '{{ translate("Failed to update auto-renewal") }}');
                }
            });
        });

    })(jQuery);
</script>
@endpush







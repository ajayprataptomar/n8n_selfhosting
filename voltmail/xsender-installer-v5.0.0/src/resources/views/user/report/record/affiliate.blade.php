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
                                    <a href="{{ route("user.dashboard") }}">{{ translate("Dashboard") }}</a>
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
                                <input type="search" value="{{request()->search}}" name="search" class="form-control" id="filter-search" placeholder="{{ translate("Search by user, affiliate, trx code or amount") }}" />
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
            <div class="card">
                <div class="card-header">
                    <div class="card-header-left">
                        <h4 class="card-title">{{ $title }}</h4>
                    </div>
                </div>
                <div class="card-body px-0 pt-0">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">{{ translate("Date") }}</th>
                                    <th scope="col">{{ translate("Affiliate User") }}</th>
                                    <th scope="col">{{ translate("Plan") }}</th>
                                    <th scope="col">{{ translate("Trx Code") }}</th>
                                    <th scope="col">{{ translate("Amount") }}</th>
                                    <th scope="col">{{ translate("Note") }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                <tr class="@if($loop->even)@endif">
                                    <td data-label="{{ translate('Time')}}">
                                        <span>{{diffForHumans($log->created_at)}}</span><br>
                                        {{getDateTime($log->created_at)}}
                                    </td>

                                    <td data-label="{{ translate('Affiliate User')}}">
                                        {{@$log->affiliate->email}}
                                    </td>

                                    <td data-label="{{ translate('Plan')}}">
                                        {{@$log?->subscription?->plan?->name ? $log->subscription->plan->name : translate("N\A")}}
                                    </td>
                                    <td data-label="{{ translate('Trx Code')}}">
                                        {{$log->trx_code}}
                                    </td>

                                    <td data-label="{{ translate('Amount')}}">
                                        <span>
                                            {{ translate("Commission Amount: ") }} 
                                            {{ $log->commission_amount ? convert_to_default_currency("USD", $log->commission_amount) : translate("N\A") }}
                                            {{ getDefaultCurrencySymbol() }} 
                                        </span>
                                        <p>
                                            {{ translate("Commission Rate: ") }}
                                            {{  $log->commission_rate ? $log->commission_rate."%" : translate("N\A") }} 
                                        </p>
                                    </td>

                                    <td data-label="{{ translate('Note')}}">
                                       {{ $log->note }}
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
                    @include('user.partials.pagination', ['paginator' => $logs])
                </div>
            </div>
        </div>
    </main>
@endsection
@push('script-push')
<script>
	"use strict";

        flatpickr("#datePicker", {
            dateFormat: "Y-m-d",
            mode: "range",
        });
</script>
@endpush



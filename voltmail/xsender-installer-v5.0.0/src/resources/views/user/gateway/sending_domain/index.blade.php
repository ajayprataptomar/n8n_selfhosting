@extends('user.gateway.index')
@section('tab-content')
    <div class="tab-pane active fade show" id="{{url()->current()}}" role="tabpanel">
        <div class="table-filter mb-4">
            <form action="{{route(Route::currentRouteName())}}" class="filter-form">
                <div class="row g-3">
                    <div class="col-xxl-3 col-xl-4 col-lg-4">
                        <div class="filter-search">
                            <input type="search" value="{{request()->search}}" name="search" class="form-control" id="filter-search" placeholder="{{ translate("Search by domain") }}" />
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
        @php
            $pendingDomains = $domains->filter(fn($d) => $d->dkim_verified !== 'yes');
        @endphp

        @if($pendingDomains->isNotEmpty())
            <div class="alert alert-primary d-flex align-items-start gap-3 mb-4" role="alert">
                <i class="ri-information-line fs-4 mt-1"></i>
                <div>
                    <strong>{{ translate('DNS Setup Required') }}</strong>
                    <p class="mb-0 mt-1">{{ translate('Some domains need DNS configuration. Click "Configure DNS" next to each domain to view the required DNS records, then verify once configured.') }}</p>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <div class="card-header-left">
                    <h4 class="card-title">{{$title}}</h4>
                    <small class="text-muted">{{ $currentCount }}/{{ $maxDomains }} {{ translate("domains used") }}</small>
                </div>
                <div class="card-header-right">
                    @if($currentCount < $maxDomains)
                        <button class="i-btn btn--primary btn--sm space-nowrap" type="button" data-bs-toggle="modal" data-bs-target="#addDomain">
                            <i class="ri-add-fill fs-16"></i> {{ translate("Add Sending Domain") }}
                        </button>
                    @else
                        <span class="text-muted">{{ translate("Domain limit reached") }}</span>
                    @endif
                </div>
            </div>
            <div class="card-body px-0 pt-0">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">{{ translate("Domain") }}</th>
                                <th scope="col">{{ translate("Authentication") }}</th>
                                <th scope="col">{{ translate("Status") }}</th>
                                <th scope="col">{{ translate("Action") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($domains as $domain)
                                @php
                                    $verifiedCount = ($domain->dkim_verified === 'yes' ? 1 : 0)
                                                   + ($domain->spf_verified === 'yes' ? 1 : 0)
                                                   + ($domain->dmarc_verified === 'yes' ? 1 : 0);
                                    $allVerified = $verifiedCount === 3;
                                    $noneVerified = $verifiedCount === 0;
                                @endphp
                                <tr>
                                    <td data-label="{{ translate('Domain')}}">
                                        <span class="text-dark fw-semibold">{{ $domain->domain }}</span>
                                        <br>
                                        <small class="text-muted">{{ translate('Selector') }}: {{ $domain->dkim_selector }}</small>
                                    </td>
                                    <td data-label="{{ translate('Authentication')}}">
                                        <div class="d-flex flex-wrap gap-1">
                                            <span class="i-badge dot {{ $domain->dkim_verified === 'yes' ? 'success' : ($noneVerified ? 'info' : 'warning') }}-soft pill">
                                                {{ translate("DKIM") }}: {{ $domain->dkim_verified === 'yes' ? translate("Pass") : translate("Pending") }}
                                            </span>
                                            <span class="i-badge dot {{ $domain->spf_verified === 'yes' ? 'success' : ($noneVerified ? 'info' : 'warning') }}-soft pill">
                                                {{ translate("SPF") }}: {{ $domain->spf_verified === 'yes' ? translate("Pass") : translate("Pending") }}
                                            </span>
                                            <span class="i-badge dot {{ $domain->dmarc_verified === 'yes' ? 'success' : ($noneVerified ? 'info' : 'warning') }}-soft pill">
                                                {{ translate("DMARC") }}: {{ $domain->dmarc_verified === 'yes' ? translate("Pass") : translate("Pending") }}
                                            </span>
                                        </div>
                                        <small class="text-muted mt-1 d-block">{{ $verifiedCount }}/3 {{ translate('checks passed') }}</small>
                                    </td>
                                    <td data-label="{{ translate('Status')}}">
                                        @if($allVerified)
                                            <span class="i-badge dot success-soft pill">{{ translate("Active") }}</span>
                                        @elseif($verifiedCount > 0)
                                            <span class="i-badge dot warning-soft pill">{{ translate("Partial") }}</span>
                                        @else
                                            <span class="i-badge dot info-soft pill">{{ translate("Pending Setup") }}</span>
                                        @endif
                                    </td>
                                    <td data-label="{{ translate('Action')}}">
                                        <div class="d-flex align-items-center gap-2">
                                            @if(!$allVerified)
                                                <a href="{{ route('user.gateway.sending-domain.dns', $domain->uid) }}" class="i-btn btn--primary btn--sm space-nowrap">
                                                    <i class="ri-settings-3-line"></i> {{ translate("Configure DNS") }}
                                                </a>
                                            @else
                                                <a href="{{ route('user.gateway.sending-domain.dns', $domain->uid) }}" class="i-btn btn--success outline btn--sm space-nowrap">
                                                    <i class="ri-shield-check-line"></i> {{ translate("DNS Records") }}
                                                </a>
                                            @endif
                                            <button class="icon-btn btn-ghost btn-sm danger-soft circle text-danger delete-domain"
                                                    type="button"
                                                    data-url="{{ route('user.gateway.sending-domain.delete', $domain->uid) }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteDomain">
                                                <i class="ri-delete-bin-line"></i>
                                                <span class="tooltiptext">{{ translate("Delete") }}</span>
                                            </button>
                                        </div>
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
                @include('user.partials.pagination', ['paginator' => $domains])
            </div>
        </div>
    </div>
@endsection

@section('modal')

<div class="modal fade" id="addDomain" tabindex="-1" aria-labelledby="addDomain" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('user.gateway.sending-domain.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate("Add Sending Domain") }}</h5>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                        <i class="ri-close-large-line"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="form-inner">
                                <label for="domain" class="form-label">{{ translate('Domain Name') }} <sup class="text--danger">*</sup></label>
                                <input type="text" id="domain" name="domain" placeholder="{{ translate('e.g., company.com') }}" class="form-control" required />
                                <small class="text-muted">{{ translate('Enter the domain you send emails from (without http:// or www)') }}</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-inner">
                                <label for="dkim_selector" class="form-label">{{ translate('DKIM Selector') }}</label>
                                <input type="text" id="dkim_selector" name="dkim_selector" value="xsender" placeholder="{{ translate('e.g., xsender') }}" class="form-control" />
                                <small class="text-muted">{{ translate('Used as prefix for the DKIM DNS record. Default: xsender') }}</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="i-btn btn--danger outline btn--md" data-bs-dismiss="modal">{{ translate("Close") }}</button>
                    <button type="submit" class="i-btn btn--primary btn--md">{{ translate("Add Domain") }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade actionModal" id="deleteDomain" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header text-start">
                <span class="action-icon danger">
                    <i class="bi bi-exclamation-circle"></i>
                </span>
            </div>
            <form method="POST" id="deleteDomainForm">
                @csrf
                <input type="hidden" name="_method" value="DELETE">
                <div class="modal-body">
                    <div class="action-message">
                        <h5>{{ translate("Are you sure to delete this sending domain?") }}</h5>
                        <p>{{ translate("DKIM signing will stop for emails from this domain.") }}</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="i-btn btn--dark outline btn--lg" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
                    <button type="submit" class="i-btn btn--danger btn--lg" data-bs-dismiss="modal">{{ translate("Delete") }}</button>
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

        flatpickr("#datePicker", {
            dateFormat: "Y-m-d",
            mode: "range",
        });

        $(document).ready(function() {
            $('.delete-domain').on('click', function() {
                var modal = $('#deleteDomain');
                modal.find('form[id=deleteDomainForm]').attr('action', $(this).data('url'));
                modal.modal('show');
            });
        });
	})(jQuery);
</script>
@endpush

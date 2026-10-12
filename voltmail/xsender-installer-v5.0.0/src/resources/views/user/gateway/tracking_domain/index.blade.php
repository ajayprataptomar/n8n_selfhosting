@extends('user.gateway.index')
@section('tab-content')
    <div class="tab-pane active fade show" id="{{url()->current()}}" role="tabpanel">
        <x-hint-banner key="gateway.tracking_domain.cname_setup" />
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
                    <small class="text-muted">{{ $currentCount }}/{{ $maxDomains }} {{ translate("domains used") }}</small>
                </div>
                <div class="card-header-right">
                    @if($currentCount < $maxDomains)
                        <button class="i-btn btn--primary btn--sm space-nowrap" type="button" data-bs-toggle="modal" data-bs-target="#addTrackingDomain">
                            <i class="ri-add-fill fs-16"></i> {{ translate("Add Tracking Domain") }}
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
                                <th scope="col">{{ translate("CNAME Target") }}</th>
                                <th scope="col">{{ translate("Status") }}</th>
                                <th scope="col">{{ translate("Option") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($domains as $domain)
                                <tr>
                                    <td data-label="{{ translate('Domain')}}">
                                        <span class="text-dark fw-semibold">{{ $domain->domain }}</span>
                                    </td>
                                    <td data-label="{{ translate('CNAME Target')}}">
                                        <code>{{ parse_url(config('app.url'), PHP_URL_HOST) }}</code>
                                    </td>
                                    <td data-label="{{ translate('Status')}}">
                                        <span class="i-badge dot {{ $domain->getStatusBadgeClass() }}-soft pill">{{ ucfirst($domain->status) }}</span>
                                    </td>
                                    <td data-label="{{ translate('Option')}}">
                                        <div class="d-flex align-items-center gap-1">
                                            <button class="icon-btn btn-ghost btn-sm info-soft circle text-info verify-tracking-domain"
                                                    type="button"
                                                    data-url="{{ route('user.gateway.tracking-domain.verify', $domain->uid) }}">
                                                <i class="ri-checkbox-circle-line"></i>
                                                <span class="tooltiptext">{{ translate("Verify CNAME") }}</span>
                                            </button>
                                            <button class="icon-btn btn-ghost btn-sm danger-soft circle text-danger delete-tracking-domain"
                                                    type="button"
                                                    data-url="{{ route('user.gateway.tracking-domain.delete', $domain->uid) }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteTrackingDomain">
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

        <div class="card mt-4">
            <div class="card-body">
                <h5 class="mb-3">{{ translate("Setup Instructions") }}</h5>
                <p class="text-muted mb-2">{{ translate("To use a custom tracking domain:") }}</p>
                <ol class="text-muted">
                    <li>{{ translate("Add your tracking subdomain above (e.g., track.yourdomain.com).") }}</li>
                    <li>
                        {{ translate("In your DNS provider (or cPanel Zone Editor), create a CNAME record:") }}
                        <div class="bg-light p-2 rounded mt-1 mb-1" style="font-family: monospace;">
                            <strong>{{ translate("Type") }}:</strong> CNAME &nbsp;|&nbsp;
                            <strong>{{ translate("Name / Host") }}:</strong> {{ translate("track") }} (@if(false){{ translate("only the subdomain prefix — cPanel auto-appends your domain") }}@endif<em>{{ translate("only the subdomain prefix; do not include your domain again — cPanel/most providers append it automatically") }}</em>)
                            &nbsp;|&nbsp;
                            <strong>{{ translate("Value / Target") }}:</strong> <code>{{ parse_url(config('app.url'), PHP_URL_HOST) }}</code>
                            &nbsp;|&nbsp;
                            <strong>TTL:</strong> 3600
                        </div>
                    </li>
                    <li>{{ translate("Wait 5–60 minutes for DNS propagation (can take up to 24 hours).") }}</li>
                    <li>{{ translate("Click the 'Verify CNAME' button next to your domain.") }}</li>
                </ol>
                <div class="alert alert-warning mt-3 mb-0">
                    <strong>{{ translate("Common mistake:") }}</strong>
                    {{ translate("If your zone is yourdomain.com, the Name field should be just") }} <code>track</code> {{ translate("— not") }} <code>track.yourdomain.com</code>{{ translate(". Entering the full domain causes cPanel to produce") }} <code>track.yourdomain.com.yourdomain.com</code> {{ translate("(a double-domain that will never resolve).") }}
                </div>
            </div>
        </div>
    </div>
@endsection

@section('modal')

<div class="modal fade" id="addTrackingDomain" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('user.gateway.tracking-domain.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate("Add Tracking Domain") }}</h5>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                        <i class="ri-close-large-line"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-inner">
                        <label for="domain" class="form-label">{{ translate('Tracking Domain') }} <sup class="text--danger">*</sup></label>
                        <input type="text" id="domain" name="domain" placeholder="{{ translate('e.g., track.company.com') }}" class="form-control" required />
                        <small class="text-muted">{{ translate('Use a subdomain like track.yourdomain.com') }}</small>
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

<div class="modal fade actionModal" id="deleteTrackingDomain" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header text-start">
                <span class="action-icon danger">
                    <i class="bi bi-exclamation-circle"></i>
                </span>
            </div>
            <form method="POST" id="deleteTrackingDomainForm">
                @csrf
                <input type="hidden" name="_method" value="DELETE">
                <div class="modal-body">
                    <div class="action-message">
                        <h5>{{ translate("Delete this tracking domain?") }}</h5>
                        <p>{{ translate("Tracking URLs will revert to the default application domain.") }}</p>
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

        $(document).ready(function() {
            $('.delete-tracking-domain').on('click', function() {
                var modal = $('#deleteTrackingDomain');
                modal.find('form[id=deleteTrackingDomainForm]').attr('action', $(this).data('url'));
                modal.modal('show');
            });

            $('.verify-tracking-domain').on('click', function() {
                var btn = $(this);
                var url = btn.data('url');
                btn.prop('disabled', true);

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        if (response.status) {
                            notify('success', response.message);
                            setTimeout(function() { location.reload(); }, 1500);
                        } else {
                            notify('error', response.message);
                        }
                    },
                    error: function() {
                        notify('error', '{{ translate("Verification failed") }}');
                    },
                    complete: function() {
                        btn.prop('disabled', false);
                    }
                });
            });
        });
	})(jQuery);
</script>
@endpush

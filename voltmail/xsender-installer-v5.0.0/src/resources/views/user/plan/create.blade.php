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
				<li class="breadcrumb-item"><a href="{{ route('user.dashboard') }}">{{ translate("Dashboard") }}</a></li>
				<li class="breadcrumb-item active" aria-current="page">
				  {{ translate("Plans") }}
				</li>
			  </ol>
			</nav>
		  </div>
		</div>
	  </div>

	  <div class="row g-3 justify-content-center w-100">
		@foreach($plans as $plan)
			<div class="col-xxl-3 col-xl-4 col-md-6">
                <div class="card plan-card">
                    <div class="card-body">
                    <div class="plan-top">
                        <h5 class="plan-title">
                            @if($plan->recommended_status == \App\Enums\StatusEnum::TRUE->status())
                                <span class="i-badge primary-solid pill me-1" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ translate("This plan is recommended by Admin") }}"><i class="ri-sparkling-line"></i></span>
                            @endif
                            {{ucfirst($plan->name)}}

                        </h5>
                        <p class="plan-description">
                            {{$plan->description}}
                        </p>
                        <p class="price-tag"> {{ getDefaultCurrencySymbol(json_decode(site_settings("currencies"), true)) }}{{shortAmount($plan->amount)}}<span>/{{$plan->duration.translate(" Days") }}</span></p>

                        @if($subscription)

                            @if($plan->id == $subscription->plan_id && ($subscription->status != App\Enums\SubscriptionStatus::REQUESTED->value || $subscription->status == App\Enums\SubscriptionStatus::RUNNING->value))

                                @if((Carbon\Carbon::now()->toDateTimeString() > $subscription->expired_date) && $subscription->status == App\Enums\SubscriptionStatus::EXPIRED->value)
                                    <a class="i-btn btn--warning btn--lg"
                                        href="{{ route('user.plan.make.payment', ['id' => $plan->id]) }}">{{ translate("Renew") }}
                                    </a>

                                @elseif($subscription->status == App\Enums\SubscriptionStatus::RUNNING->value || $subscription->status == App\Enums\SubscriptionStatus::RENEWED->value)
                                    <button class="i-btn btn--success btn--lg">{{ translate('Current Plan')}}</button>
                                @endif
                            @else
                                <a class="i-btn btn--info btn--lg"
                                    href="{{ route('user.plan.make.payment', ['id' => $plan->id]) }}">{{ translate('Upgrade Plan')}}
                                </a>

                            @endif
                        @else
                            <a class="i-btn btn--primary btn--md"
                                href="{{ route('user.plan.make.payment', ['id' => $plan->id]) }}">{{ translate('Purchase Now')}}
                            </a>

                        @endif

                    </div>

                    {{-- Credits Summary with Info Badge --}}
                    <div class="credits-summary mt-3">
                      <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-semibold fs-14">{{ translate("Credits Included") }}</span>
                        <button type="button" class="btn-plan-info" data-bs-toggle="modal" data-bs-target="#planDetailsModal{{ $plan->id }}" title="{{ translate('View plan details') }}">
                          <i class="ri-information-line"></i>
                        </button>
                      </div>
                      <div class="credits-grid">
                        <div class="credit-item">
                          <i class="ri-mail-line text-primary"></i>
                          <small>{{ translate("Email") }}</small>
                          <span>@if($plan->email->credits != -1){{ $plan->email->credits }}@else <i class="ri-infinity-line"></i> @endif</span>
                        </div>
                        <div class="credit-item">
                          <i class="ri-chat-3-line text-success"></i>
                          <small>{{ translate("SMS") }}</small>
                          <span>@if($plan->sms->credits != -1){{ $plan->sms->credits }}@else <i class="ri-infinity-line"></i> @endif</span>
                        </div>
                        <div class="credit-item">
                          <i class="ri-whatsapp-line text-whatsapp"></i>
                          <small>{{ translate("WhatsApp") }}</small>
                          <span>@if($plan->whatsapp->credits != -1){{ $plan->whatsapp->credits }}@else <i class="ri-infinity-line"></i> @endif</span>
                        </div>
                      </div>
                    </div>

                    <div class="price-feature">
                        <h6>{{ translate("Scroll What's included") }}</h6>

                        {{-- Display Features --}}
                        @php
                            $allDisplayFeatures = \App\Models\PlanDisplayFeature::active()->ordered()->get();
                            $planIncludedFeatures = $plan->displayFeatures()->wherePivot('is_included', true)->pluck('plan_display_feature_id')->toArray();
                        @endphp
                        @if($allDisplayFeatures->count() > 0)
                        <ul class="price-feature-list">
                            @foreach($allDisplayFeatures as $displayFeature)
                                @if(in_array($displayFeature->id, $planIncludedFeatures))
                                    <li class="custom-li-height"><i class="{{ $displayFeature->icon ?? 'bi bi-check-circle-fill' }}" style="color: #22c55e;"></i>{{ translate($displayFeature->name) }}</li>
                                @else
                                    <li class="custom-li-height excluded-feature"><i class="{{ $displayFeature->icon ?? 'bi bi-x-circle-fill' }}"></i>{{ translate($displayFeature->name) }}</li>
                                @endif
                            @endforeach
                        </ul>
                        @else
                        <ul class="price-feature-list">
                            @if($plan->carry_forward == \App\Enums\StatusEnum::TRUE->status())
                                <li class="custom-li-height"><i class="bi bi-check-circle-fill"></i>{{ translate("Credit carry forward") }}</li>
                            @endif
                            <li class="custom-li-height"><i class="bi bi-check-circle-fill"></i>{{ translate("Email, SMS & WhatsApp") }}</li>
                            <li class="custom-li-height"><i class="bi bi-check-circle-fill"></i>{{ translate("Multi-channel messaging") }}</li>
                            <li class="custom-li-height"><i class="bi bi-check-circle-fill"></i>{{ translate("1 Email Credit per Email") }}</li>
                            <li class="custom-li-height"><i class="bi bi-check-circle-fill"></i>{{ translate("1 SMS Credit per SMS") }}</li>
                            <li class="custom-li-height"><i class="bi bi-check-circle-fill"></i>{{ translate("1 WhatsApp Credit per WhatsApp") }}</li>
                        </ul>
                        @endif
                    </div>
                    </div>
                </div>
			</div>

            {{-- Plan Details Modal --}}
            <div class="modal fade" id="planDetailsModal{{ $plan->id }}" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">{{ ucfirst($plan->name) }} - {{ translate("Plan Details") }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <div class="plan-details-list">
                      @if($plan->carry_forward == \App\Enums\StatusEnum::TRUE->status())
                        <div class="detail-item highlight align-items-center">
                          <i class="ri-repeat-line text-success"></i>
                          <span class="text-success">{{ translate("Credit carry forward when renewed") }}</span>
                        </div>
                      @endif

                      <h6 class="detail-section-title">{{ translate("Messaging Credits") }}</h6>
                      <div class="detail-item">
                        <i class="ri-mail-line text-primary"></i>
                        <span>@if($plan->email->credits != -1){{ $plan->email->credits }} {{ translate("Email Credits") }}@else {{ translate("Unlimited Email Credits") }}@endif</span>
                        @if(@$plan->email->credits_per_day != 0)<small class="text-muted d-block">{{ translate("Max ") }}{{ $plan->email->credits_per_day }}{{ translate(" per day") }}</small>@endif
                      </div>
                      <div class="detail-item">
                        <i class="ri-chat-3-line text-success"></i>
                        <span>@if($plan->sms->credits != -1){{ $plan->sms->credits }} {{ translate("SMS Credits") }}@else {{ translate("Unlimited SMS Credits") }}@endif</span>
                        @if(@$plan->sms->credits_per_day != 0)<small class="text-muted d-block">{{ translate("Max ") }}{{ $plan->sms->credits_per_day }}{{ translate(" per day") }}</small>@endif
                      </div>
                      <div class="detail-item">
                        <i class="ri-whatsapp-line text-whatsapp"></i>
                        <span>@if($plan->whatsapp->credits != -1){{ $plan->whatsapp->credits }} {{ translate("WhatsApp Credits") }}@else {{ translate("Unlimited WhatsApp Credits") }}@endif</span>
                        @if(@$plan->whatsapp->credits_per_day != 0)<small class="text-muted d-block">{{ translate("Max ") }}{{ $plan->whatsapp->credits_per_day }}{{ translate(" per day") }}</small>@endif
                      </div>

                      @if($plan->sms->android->is_allowed == true || $plan->whatsapp->is_allowed == true || $plan->sms->is_allowed == true || $plan->email->is_allowed == true)
                      <h6 class="detail-section-title">{{ translate("Gateway Access") }}</h6>
                      @if($plan->type == \App\Enums\StatusEnum::TRUE->status())
                        <div class="detail-item">
                          <i class="ri-server-line text-info"></i>
                          <span>{{ translate("Use Admin Gateways:") }}
                            @if($plan->sms->is_allowed == true) {{ translate("SMS") }} @endif
                            @if($plan->sms->android->is_allowed == true) {{ translate("Android") }} @endif
                            @if($plan->email->is_allowed == true) {{ translate("Email") }} @endif
                          </span>
                        </div>
                        @if($plan->whatsapp->is_allowed == true)
                        <div class="detail-item">
                          <i class="ri-whatsapp-line text-whatsapp"></i>
                          <span>{{ translate("Add") }} {{ $plan->whatsapp->gateway_limit == 0 ? translate("unlimited") : $plan->whatsapp->gateway_limit }} {{ translate("WhatsApp devices") }}</span>
                        </div>
                        @endif
                      @else
                        @if($plan->sms->android->is_allowed == true)
                        <div class="detail-item">
                          <i class="ri-smartphone-line text-success"></i>
                          <span>{{ translate("Add") }} {{ $plan->sms->android->gateway_limit == 0 ? translate("unlimited") : $plan->sms->android->gateway_limit }} {{ translate("Android Gateways") }}</span>
                        </div>
                        @endif
                        @if($plan->whatsapp->is_allowed == true)
                        <div class="detail-item">
                          <i class="ri-whatsapp-line text-whatsapp"></i>
                          <span>{{ translate("Add") }} {{ $plan->whatsapp->gateway_limit == 0 ? translate("unlimited") : $plan->whatsapp->gateway_limit }} {{ translate("WhatsApp devices") }}</span>
                        </div>
                        @endif
                        @if($plan->email->is_allowed == true)
                        @php
                            $gateway_mail = (array)@$plan->email->allowed_gateways;
                            $total_mail_gateway = 0;
                            foreach ($gateway_mail as $email_value) { $total_mail_gateway += $email_value; }
                        @endphp
                        <div class="detail-item">
                          <i class="ri-mail-line text-primary"></i>
                          <span>{{ translate("Add up to") }} {{ $total_mail_gateway }} {{ translate("Mail Gateways") }}</span>
                        </div>
                        @endif
                        @if($plan->sms->is_allowed == true)
                        @php
                            $gateway_sms = (array)@$plan->sms->allowed_gateways;
                            $total_sms_gateway = 0;
                            foreach ($gateway_sms as $sms_value) { $total_sms_gateway += $sms_value; }
                        @endphp
                        <div class="detail-item">
                          <i class="ri-chat-3-line text-success"></i>
                          <span>{{ translate("Add up to") }} {{ $total_sms_gateway }} {{ translate("SMS Gateways") }}</span>
                        </div>
                        @endif
                      @endif
                      @endif

                      <h6 class="detail-section-title">{{ translate("Credit Usage") }}</h6>
                      <div class="detail-item">
                        <i class="ri-hashtag text-muted"></i>
                        <span>{{ translate("1 SMS Credit = ") }}{{ site_settings("sms_word_count") }} {{ translate("plain words") }}</span>
                      </div>
                      <div class="detail-item">
                        <i class="ri-hashtag text-muted"></i>
                        <span>{{ translate("1 SMS Credit = ") }}{{ site_settings("sms_word_unicode_count") }} {{ translate("unicode words") }}</span>
                      </div>
                      <div class="detail-item">
                        <i class="ri-hashtag text-muted"></i>
                        <span>{{ translate("1 WhatsApp Credit = ") }}{{ site_settings("whatsapp_word_count") }} {{ translate("words") }}</span>
                      </div>
                      <div class="detail-item">
                        <i class="ri-hashtag text-muted"></i>
                        <span>{{ translate("1 Email Credit = 1 Email") }}</span>
                      </div>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="i-btn btn--outline btn--md" data-bs-dismiss="modal">{{ translate("Close") }}</button>
                  </div>
                </div>
              </div>
            </div>
		@endforeach
	  </div>
	</div>
</main>
@endsection

@push('script-push')
<script>
	(function($){
		"use strict";
	})(jQuery);
</script>
@endpush

@push('style-push')
<style>
  .plan-card .plan-top .plan-title {
    font-size: 16px;
    margin-block-end: 8px;
}
  .plan-card .price-feature {
    padding-block: 15px 15px;
}

  /* Credits Summary */
  .credits-summary {
    border-bottom: 1px solid #eee;
    padding-bottom: 15px
  }
  .credits-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
  }
  .credit-item {
    text-align: center;
    padding: 0rem;
    background: var(--card-bg, #fff);
  }
  .credit-item i {
    font-size: 18px;
    display: block;
    margin-bottom: 0px;
    line-height: 1;
  }
  .credit-item span {
    font-weight: 700;
    font-size: 16px;
    display: block;
  }
  .credit-item small {
    font-size: 11px;
    color: var(--text-muted, #6b7280);
  }
  .btn-plan-info {
    background: transparent;
    border: none;
    color: var(--primary-color, #6366f1);
    font-size: 20px;
    cursor: pointer;
    padding: 0;
    transition: all 0.2s;
  }
  .btn-plan-info:hover {
    color: var(--primary-dark, #4f46e5);
    transform: scale(1.1);
  }
  .text-whatsapp {
    color: #25D366 !important;
  }

  /* Excluded Features */
  .price-feature-list{
    gap: 12px !important;
  }
  .price-feature-list li.excluded-feature {
    opacity: 0.5;
    text-decoration: line-through;
  }
  .price-feature-list li.excluded-feature i {
    color: var(--danger-color, #ef4444) !important;
  }
  .plan-card .price-feature .price-feature-list > li > i {
    font-size: 16px;
    color: var(--color-primary);
}

  /* Modal Styles */
  .plan-details-list .detail-section-title {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted, #6b7280);
    margin: 1.25rem 0 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border-color, #e5e7eb);
  }
  .plan-details-list .detail-section-title:first-child {
    margin-top: 0;
  }
  .plan-details-list .detail-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.125rem 0;
    font-size: 14px;
  }
  .plan-details-list .detail-item i {
    font-size: 14px;
  }
  .plan-details-list .detail-item.highlight {
    background: rgba(var(--success-rgb, 16, 185, 129), 0.1);
    padding: 0.75rem;
    border-radius: 8px;
    margin-bottom: 0.5rem;
  }
  .plan-details-list .detail-item.highlight span {
    font-weight: 500;
  }
  .plan-card .plan-top .price-tag{
    font-size: 24px;
  }
  
  .price-feature-list {
    height: 100%;
    max-height: 240px;
    overflow-y: auto;

    /* Hide scrollbar */
    scrollbar-width: none; /* Firefox */
  }

  .price-feature-list::-webkit-scrollbar {
    width: 0px;
  }

  /* Show scrollbar on hover */
  .price-feature-list:hover {
    scrollbar-width: thin;
  }

  .price-feature-list:hover::-webkit-scrollbar {
    width: 6px;
  }

  .price-feature-list:hover::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
  }

  .price-feature-list:hover::-webkit-scrollbar-track {
    background: transparent;
  }
</style>
@endpush

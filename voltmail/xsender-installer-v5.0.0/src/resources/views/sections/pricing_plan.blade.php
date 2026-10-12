<section class="pricing-plans pt-100 pb-100" id="pricing">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="text-center section-header section-header-two align-items-center">
                    <span class="sub-title">{{translate(getArrayValue(@$plan_content->section_value, 'sub_heading'))}}</span>
                    <h3 class="section-title">{{translate(getArrayValue(@$plan_content->section_value, 'heading'))}}</h3>
                    <p class="title-description">{{translate(getArrayValue(@$plan_content->section_value, 'description'))}}</p>
                </div>
            </div>
        </div>

        <div class="row g-4 align-items-center">
            @foreach($plans as $key => $plan)
                @if($plan->amount>0)
                <div class="col-lg-4 col-md-6">
                    <div class="pricing-item @if($plan->recommended_status == 1)recommend-item @endif">
                        <div class="pricing-item-top">
                            <div class="pricing-detail">
                                <h5>
                                    <span>
                                        <svg viewBox="0 0 24 24">
                                            <path d="M12 17a.833.833 0 01-.833-.833 3.333 3.333 0 00-3.334-3.334.833.833 0 110-1.666 3.333 3.333 0 003.334-3.334.833.833 0 111.666 0 3.333 3.333 0 003.334 3.334.833.833 0 110 1.666 3.333 3.333 0 00-3.334 3.334c0 .46-.373.833-.833.833z" />
                                        </svg>
                                    </span>
                                    {{ucfirst($plan->name)}}
                                </h5>
                            </div>
                            <p>{{$plan->description}}</p>
                        </div>

                        <div class="price">
                            <span>{{$general->currency_symbol}}{{shortAmount($plan->amount)}} </span> <small>/ {{$plan->duration}} {{ translate('Days')}}</small>
                        </div>

                        {{-- Credits Summary with Info Badge --}}
                        <div class="credits-summary mb-3">
                          <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-semibold fs-14">{{ translate("Credits Included") }}</span>
                            <button type="button" class="btn-plan-info" data-bs-toggle="modal" data-bs-target="#planDetailsModal{{ $plan->id }}" title="{{ translate('View plan details') }}">
                              <i class="bi bi-info-circle"></i>
                            </button>
                          </div>
                          <div class="credits-grid">
                            <div class="credit-item">
                              <i class="bi bi-envelope text-primary"></i>
                              <span>@if($plan->email->credits != -1){{ $plan->email->credits }}@else <i class="bi bi-infinity"></i> @endif</span>
                              <small>{{ translate("Email") }}</small>
                            </div>
                            <div class="credit-item">
                              <i class="bi bi-chat-dots text-success"></i>
                              <span>@if($plan->sms->credits != -1){{ $plan->sms->credits }}@else <i class="bi bi-infinity"></i> @endif</span>
                              <small>{{ translate("SMS") }}</small>
                            </div>
                            <div class="credit-item">
                              <i class="bi bi-whatsapp text-whatsapp"></i>
                              <span>@if($plan->whatsapp->credits != -1){{ $plan->whatsapp->credits }}@else <i class="bi bi-infinity"></i> @endif</span>
                              <small>{{ translate("WhatsApp") }}</small>
                            </div>
                          </div>
                        </div>

                        <div class="pricing-item-bottom">
                            {{-- Display Features --}}
                            @php
                                $allDisplayFeatures = \App\Models\PlanDisplayFeature::active()->ordered()->get();
                                $planIncludedFeatures = $plan->displayFeatures()->wherePivot('is_included', true)->pluck('plan_display_feature_id')->toArray();
                            @endphp
                            @if($allDisplayFeatures->count() > 0)
                            <ul class="pricing-features">
                                @foreach($allDisplayFeatures as $displayFeature)
                                    @if(in_array($displayFeature->id, $planIncludedFeatures))
                                        <li class="pricing-feature">
                                            <span>
                                                <i class="{{ $displayFeature->icon ?? 'ri-checkbox-circle-line' }}" style="font-size: 18px; color: #22c55e;"></i>
                                            </span>
                                            {{ translate($displayFeature->name) }}
                                        </li>
                                    @else
                                        <li class="pricing-feature excluded">
                                            <span class="excluded-icon">
                                                <i class="{{ $displayFeature->icon ?? 'ri-close-circle-line' }}" style="font-size: 18px;"></i>
                                            </span>
                                            {{ translate($displayFeature->name) }}
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                            @else
                            <ul class="pricing-features">
                                @if($plan->carry_forward == App\Enums\StatusEnum::TRUE->status())
                                    <li class="pricing-feature">
                                        <span>
                                            <svg xmlns="http://www.w3.org/2000/svg" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink" x="0" y="0" viewBox="0 0 520 520" style="enable-background:new 0 0 512 512" xml:space="preserve" class=""><g><path d="M239.987 460.841a10 10 0 0 1-7.343-3.213L34.657 243.463A10 10 0 0 1 42 226.675h95.3a10.006 10.006 0 0 1 7.548 3.439l66.168 76.124c7.151-15.286 20.994-40.738 45.286-71.752 35.912-45.85 102.71-113.281 216.994-174.153a10 10 0 0 1 10.85 16.712c-.436.341-44.5 35.041-95.212 98.6-46.672 58.49-108.714 154.13-139.243 277.6a10 10 0 0 1-9.707 7.6z" data-name="6-Check"  opacity="1" data-original="#000000" class=""></path></g></svg>
                                        </span>
                                        {{ translate("Credit carry forward") }}
                                    </li>
                                @endif
                                <li class="pricing-feature">
                                    <span>
                                        <svg xmlns="http://www.w3.org/2000/svg" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink" x="0" y="0" viewBox="0 0 520 520" style="enable-background:new 0 0 512 512" xml:space="preserve" class=""><g><path d="M239.987 460.841a10 10 0 0 1-7.343-3.213L34.657 243.463A10 10 0 0 1 42 226.675h95.3a10.006 10.006 0 0 1 7.548 3.439l66.168 76.124c7.151-15.286 20.994-40.738 45.286-71.752 35.912-45.85 102.71-113.281 216.994-174.153a10 10 0 0 1 10.85 16.712c-.436.341-44.5 35.041-95.212 98.6-46.672 58.49-108.714 154.13-139.243 277.6a10 10 0 0 1-9.707 7.6z" data-name="6-Check"  opacity="1" data-original="#000000" class=""></path></g></svg>
                                    </span>
                                    {{ translate("Email, SMS & WhatsApp") }}
                                </li>
                                <li class="pricing-feature">
                                    <span>
                                        <svg xmlns="http://www.w3.org/2000/svg" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink" x="0" y="0" viewBox="0 0 520 520" style="enable-background:new 0 0 512 512" xml:space="preserve" class=""><g><path d="M239.987 460.841a10 10 0 0 1-7.343-3.213L34.657 243.463A10 10 0 0 1 42 226.675h95.3a10.006 10.006 0 0 1 7.548 3.439l66.168 76.124c7.151-15.286 20.994-40.738 45.286-71.752 35.912-45.85 102.71-113.281 216.994-174.153a10 10 0 0 1 10.85 16.712c-.436.341-44.5 35.041-95.212 98.6-46.672 58.49-108.714 154.13-139.243 277.6a10 10 0 0 1-9.707 7.6z" data-name="6-Check"  opacity="1" data-original="#000000" class=""></path></g></svg>
                                    </span>
                                    {{ translate("Multi-channel messaging") }}
                                </li>
                                <li class="pricing-feature">
                                    <span>
                                        <svg xmlns="http://www.w3.org/2000/svg" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink" x="0" y="0" viewBox="0 0 520 520" style="enable-background:new 0 0 512 512" xml:space="preserve" class=""><g><path d="M239.987 460.841a10 10 0 0 1-7.343-3.213L34.657 243.463A10 10 0 0 1 42 226.675h95.3a10.006 10.006 0 0 1 7.548 3.439l66.168 76.124c7.151-15.286 20.994-40.738 45.286-71.752 35.912-45.85 102.71-113.281 216.994-174.153a10 10 0 0 1 10.85 16.712c-.436.341-44.5 35.041-95.212 98.6-46.672 58.49-108.714 154.13-139.243 277.6a10 10 0 0 1-9.707 7.6z" data-name="6-Check"  opacity="1" data-original="#000000" class=""></path></g></svg>
                                    </span>
                                    {{ translate("1 Credit per message") }}
                                </li>
                            </ul>
                            @endif

                            <a href="{{route('user.plan.create')}}" class="ig-btn btn--primary btn--lg w-100">
                                @if($subscription)
                                    @if($plan->id == $subscription->plan_id)
                                        @if(Carbon\Carbon::now()->toDateTimeString() > $subscription->expired_date)
                                            {{ translate("Renew") }}
                                        @else
                                            {{ translate('Current Plan')}}
                                        @endif
                                    @else
                                        {{ translate('Upgrade Plan')}}
                                    @endif
                                @else
                                    {{ translate('Purchase Now')}}
                                @endif
                            </a>
                        </div>

                        @if($plan->recommended_status == 1)
                            <div class="ribbon">
                                <span>{{translate('Recommended')}}</span>
                            </div>
                        @endif

                        <div class="pricing-shape">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none"><path opacity=".349" fill-rule="evenodd" clip-rule="evenodd" d="M94.714 9.882a100 100 0 0180.472-2.377L433 111.904 393.686 139l-556.687-6.588L94.714 9.882z" fill="url(#paint0_linear)"/><defs><linearGradient id="paint0_linear" x1="-111.329" y1="17.357" x2="-107.18" y2="149.186" gradientUnits="userSpaceOnUse"><stop offset=".001" stop-color="#E5ECF2"/><stop offset="1" stop-color="#fff"/></linearGradient></defs></svg>
                        </div>

                        <div class="recommend-bg">
                            <img src="https://i.ibb.co/b6SCQyb/64c2522cfdbe0fd7aeb79aa0-cta-bg.png" alt="64c2522cfdbe0fd7aeb79aa0-cta-bg">
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
                          @if($plan->carry_forward == App\Enums\StatusEnum::TRUE->status())
                            <div class="detail-item highlight">
                              <i class="bi bi-arrow-repeat text-success"></i>
                              <span>{{ translate("Credit carry forward when renewed") }}</span>
                            </div>
                          @endif

                          <h6 class="detail-section-title">{{ translate("Messaging Credits") }}</h6>
                          <div class="detail-item">
                            <i class="bi bi-envelope text-primary"></i>
                            <span>@if($plan->email->credits != -1){{ $plan->email->credits }} {{ translate("Email Credits") }}@else {{ translate("Unlimited Email Credits") }}@endif</span>
                            @if(@$plan->email->credits_per_day != 0)<small class="text-muted d-block">{{ translate("Max ") }}{{ $plan->email->credits_per_day }}{{ translate(" per day") }}</small>@endif
                          </div>
                          <div class="detail-item">
                            <i class="bi bi-chat-dots text-success"></i>
                            <span>@if($plan->sms->credits != -1){{ $plan->sms->credits }} {{ translate("SMS Credits") }}@else {{ translate("Unlimited SMS Credits") }}@endif</span>
                            @if(@$plan->sms->credits_per_day != 0)<small class="text-muted d-block">{{ translate("Max ") }}{{ $plan->sms->credits_per_day }}{{ translate(" per day") }}</small>@endif
                          </div>
                          <div class="detail-item">
                            <i class="bi bi-whatsapp text-whatsapp"></i>
                            <span>@if($plan->whatsapp->credits != -1){{ $plan->whatsapp->credits }} {{ translate("WhatsApp Credits") }}@else {{ translate("Unlimited WhatsApp Credits") }}@endif</span>
                            @if(@$plan->whatsapp->credits_per_day != 0)<small class="text-muted d-block">{{ translate("Max ") }}{{ $plan->whatsapp->credits_per_day }}{{ translate(" per day") }}</small>@endif
                          </div>

                          @if($plan->sms->android->is_allowed == true || $plan->whatsapp->is_allowed == true || $plan->sms->is_allowed == true || $plan->email->is_allowed == true)
                          <h6 class="detail-section-title">{{ translate("Gateway Access") }}</h6>
                          @if($plan->type == App\Enums\StatusEnum::TRUE->status())
                            <div class="detail-item">
                              <i class="bi bi-hdd-network text-info"></i>
                              <span>{{ translate("Use Admin Gateways:") }}
                                @if($plan->sms->is_allowed == true) {{ translate("SMS") }} @endif
                                @if($plan->sms->android->is_allowed == true) {{ translate("Android") }} @endif
                                @if($plan->email->is_allowed == true) {{ translate("Email") }} @endif
                              </span>
                            </div>
                            @if($plan->whatsapp->is_allowed == true)
                            <div class="detail-item">
                              <i class="bi bi-whatsapp text-whatsapp"></i>
                              <span>{{ translate("Add") }} {{ $plan->whatsapp->gateway_limit == 0 ? translate("unlimited") : $plan->whatsapp->gateway_limit }} {{ translate("WhatsApp devices") }}</span>
                            </div>
                            @endif
                          @else
                            @if($plan->sms->android->is_allowed == true)
                            <div class="detail-item">
                              <i class="bi bi-phone text-success"></i>
                              <span>{{ translate("Add") }} {{ $plan->sms->android->gateway_limit == 0 ? translate("unlimited") : $plan->sms->android->gateway_limit }} {{ translate("Android Gateways") }}</span>
                            </div>
                            @endif
                            @if($plan->whatsapp->is_allowed == true)
                            <div class="detail-item">
                              <i class="bi bi-whatsapp text-whatsapp"></i>
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
                              <i class="bi bi-envelope text-primary"></i>
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
                              <i class="bi bi-chat-dots text-success"></i>
                              <span>{{ translate("Add up to") }} {{ $total_sms_gateway }} {{ translate("SMS Gateways") }}</span>
                            </div>
                            @endif
                          @endif
                          @endif

                          <h6 class="detail-section-title">{{ translate("Credit Usage") }}</h6>
                          <div class="detail-item">
                            <i class="bi bi-hash text-muted"></i>
                            <span>{{ translate("1 SMS Credit = ") }}{{ $general->sms_word_text_count }} {{ translate("plain words") }}</span>
                          </div>
                          <div class="detail-item">
                            <i class="bi bi-hash text-muted"></i>
                            <span>{{ translate("1 SMS Credit = ") }}{{ $general->sms_word_unicode_count }} {{ translate("unicode words") }}</span>
                          </div>
                          <div class="detail-item">
                            <i class="bi bi-hash text-muted"></i>
                            <span>{{ translate("1 WhatsApp Credit = ") }}{{ $general->whatsapp_word_count }} {{ translate("words") }}</span>
                          </div>
                          <div class="detail-item">
                            <i class="bi bi-hash text-muted"></i>
                            <span>{{ translate("1 Email Credit = 1 Email") }}</span>
                          </div>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <a href="{{route('user.plan.create')}}" class="ig-btn btn--primary btn--md w-100">{{ translate("Get Started") }}</a>
                      </div>
                    </div>
                  </div>
                </div>
                @endif
            @endforeach
        </div>
    </div>
</section>

@push('style-include')
<style>
  /* Credits Summary */
  .credits-summary {
    background: rgba(var(--primary-rgb, 99, 102, 241), 0.05);
    border-radius: 12px;
    padding: 1rem;
  }
  .credits-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
  }
  .credit-item {
    text-align: center;
    padding: 0.5rem;
    background: var(--white, #fff);
    border-radius: 8px;
  }
  .credit-item i {
    font-size: 18px;
    display: block;
    margin-bottom: 4px;
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
    font-size: 18px;
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
  .pricing-feature.excluded {
    opacity: 0.5;
    text-decoration: line-through;
  }
  .pricing-feature.excluded .excluded-icon {
    opacity: 0.5;
  }
  .pricing-feature.excluded .excluded-icon svg {
    fill: var(--danger-color, #ef4444);
  }

  /* Modal Styles */
  .plan-details-list .detail-section-title {
    font-size: 13px;
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
    padding: 0.5rem 0;
  }
  .plan-details-list .detail-item i {
    font-size: 18px;
    margin-top: 2px;
  }
  .plan-details-list .detail-item.highlight {
    background: rgba(var(--success-rgb, 16, 185, 129), 0.1);
    padding: 0.75rem;
    border-radius: 8px;
    margin-bottom: 0.5rem;
  }
  .plan-details-list .detail-item.highlight span {
    font-weight: 600;
  }
</style>
@endpush

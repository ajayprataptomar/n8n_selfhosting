<section class="plan pb-120">
  <div class="container-fluid container-wrapper">
    <div class="row g-4">
      <div class="col-xxl-5 col-xl-6 col-md-9 mx-auto">
        <div class="section-title d-flex align-items-center justify-content-center text-center">
          <h3 class="title-anim">{{getTranslatedArrayValue(@$plan_content->section_value, 'heading') }}</h3>
        </div>
      </div>
    </div>

    <div class="plan-card-wrapper">
      <div class="row g-4">
        @foreach(request()->routeIs('pricing') ? $plans : $plans->take(3) as $plan)
        @php
          $isRecommended = $plan->recommended_status == \App\Enums\StatusEnum::TRUE->status();
        @endphp

        <div class="col-lg-4 col-md-6 fade-item">
          <div class="plan-card {{ $isRecommended ? 'recommend' : '' }}">
            <span class="plan-title">{{ucfirst($plan->name)}}</span>

            <p class="plan-desc">{{$plan->description}}</p>

            {{-- Price Section --}}
            <div class="price">
              <h5>
                <span style="font-size: 0.5em; vertical-align: super;">{{ getDefaultCurrencySymbol(json_decode(site_settings("currencies"), true)) }}</span>{{shortAmount($plan->amount)}}
              </h5>
              <p>
                <span class="plan-duration-badge" style="display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 500; {{ $isRecommended ? 'background: rgba(255,255,255,0.2); color: var(--text-light);' : 'background: var(--gray-200); color: var(--text-secondary);' }}">
                  {{ translate("for") }} {{ $plan->duration }} {{ translate("Days") }}
                </span>
              </p>
            </div>

            {{-- Credits Summary Box --}}
            <div class="plan-credits-box" style="border-radius: 16px; padding: 20px; margin-bottom: 24px; {{ $isRecommended ? 'background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25);' : 'background: var(--gray-100); border: 1px solid var(--border-ternary);' }}">
              <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                <span style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; {{ $isRecommended ? 'color: rgba(255,255,255,0.9);' : 'color: var(--text-secondary);' }}">{{ translate("Credits Included") }}</span>
                <button type="button" class="plan-info-trigger" style="background: none; border: none; font-size: 20px; cursor: pointer; padding: 0; {{ $isRecommended ? 'color: rgba(255,255,255,0.8);' : 'color: var(--color-primary);' }}" data-plan-id="{{ $plan->id }}">
                  <i class="bi bi-info-circle-fill"></i>
                </button>
              </div>
              <div style="display: flex; gap: 10px;">
                {{-- Email Credit --}}
                <div style="flex: 1; border-radius: 12px; padding: 14px 10px; text-align: center; {{ $isRecommended ? 'background: rgba(255,255,255,0.2);' : 'background: var(--card-bg); box-shadow: 0 1px 3px rgba(0,0,0,0.05);' }}">
                  <div style="width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; {{ $isRecommended ? 'background: rgba(255,255,255,0.25);' : 'background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);' }}">
                    <i class="bi bi-envelope-fill" style="font-size: 16px; {{ $isRecommended ? 'color: var(--text-light);' : 'color: #2563eb;' }}"></i>
                  </div>
                  <div style="font-size: 20px; font-weight: 700; line-height: 1.2; {{ $isRecommended ? 'color: var(--text-light);' : 'color: var(--text-primary);' }}">
                    @if($plan->email->credits != -1){{ number_format($plan->email->credits) }}@else <span style="font-size: 24px;">&infin;</span>@endif
                  </div>
                  <div style="font-size: 11px; font-weight: 500; text-transform: uppercase; margin-top: 4px; {{ $isRecommended ? 'color: rgba(255,255,255,0.7);' : 'color: var(--text-secondary);' }}">{{ translate("Email") }}</div>
                </div>
                {{-- SMS Credit --}}
                <div style="flex: 1; border-radius: 12px; padding: 14px 10px; text-align: center; {{ $isRecommended ? 'background: rgba(255,255,255,0.2);' : 'background: var(--card-bg); box-shadow: 0 1px 3px rgba(0,0,0,0.05);' }}">
                  <div style="width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; {{ $isRecommended ? 'background: rgba(255,255,255,0.25);' : 'background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);' }}">
                    <i class="bi bi-chat-dots-fill" style="font-size: 16px; {{ $isRecommended ? 'color: var(--text-light);' : 'color: #059669;' }}"></i>
                  </div>
                  <div style="font-size: 20px; font-weight: 700; line-height: 1.2; {{ $isRecommended ? 'color: var(--text-light);' : 'color: var(--text-primary);' }}">
                    @if($plan->sms->credits != -1){{ number_format($plan->sms->credits) }}@else <span style="font-size: 24px;">&infin;</span>@endif
                  </div>
                  <div style="font-size: 11px; font-weight: 500; text-transform: uppercase; margin-top: 4px; {{ $isRecommended ? 'color: rgba(255,255,255,0.7);' : 'color: var(--text-secondary);' }}">{{ translate("SMS") }}</div>
                </div>
                {{-- WhatsApp Credit --}}
                <div style="flex: 1; border-radius: 12px; padding: 14px 10px; text-align: center; {{ $isRecommended ? 'background: rgba(255,255,255,0.2);' : 'background: var(--card-bg); box-shadow: 0 1px 3px rgba(0,0,0,0.05);' }}">
                  <div style="width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; {{ $isRecommended ? 'background: rgba(255,255,255,0.25);' : 'background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);' }}">
                    <i class="bi bi-whatsapp" style="font-size: 16px; {{ $isRecommended ? 'color: var(--text-light);' : 'color: #25D366;' }}"></i>
                  </div>
                  <div style="font-size: 20px; font-weight: 700; line-height: 1.2; {{ $isRecommended ? 'color: var(--text-light);' : 'color: var(--text-primary);' }}">
                    @if($plan->whatsapp->credits != -1){{ number_format($plan->whatsapp->credits) }}@else <span style="font-size: 24px;">&infin;</span>@endif
                  </div>
                  <div style="font-size: 11px; font-weight: 500; text-transform: uppercase; margin-top: 4px; {{ $isRecommended ? 'color: rgba(255,255,255,0.7);' : 'color: var(--text-secondary);' }}">{{ translate("WhatsApp") }}</div>
                </div>
              </div>
            </div>

            <div class="plan-features">
              <h5>{{ translate("What's Include?") }}</h5>

              {{-- Display Features --}}
              @php
                  $allDisplayFeatures = \App\Models\PlanDisplayFeature::active()->ordered()->get();
                  $planIncludedFeatures = $plan->displayFeatures()->wherePivot('is_included', true)->pluck('plan_display_feature_id')->toArray();
              @endphp
              @if($allDisplayFeatures->count() > 0)

              <h6>{{ translate("Scroll Down features") }}</h6>

              <ul class="pricing-list">

                @foreach($allDisplayFeatures as $displayFeature)
                    @if(in_array($displayFeature->id, $planIncludedFeatures))
                        <li style="display: flex; align-items: center; gap: 10px;">
                          <i class="{{ $displayFeature->icon ?? 'bi bi-check-circle-fill' }}" style="{{ $isRecommended ? 'color: var(--text-light) !important;' : 'color: var(--color-success);' }}"></i>
                          <span>{{ translate($displayFeature->name) }}</span>
                        </li>
                    @else
                        <li style="display: flex; align-items: center; gap: 10px; opacity: 0.5;">
                          <i class="{{ $displayFeature->icon ?? 'bi bi-x-circle-fill' }}" style="color: {{ $isRecommended ? 'rgba(255,255,255,0.6)' : 'var(--color-danger)' }};"></i>
                          <span style="text-decoration: line-through;">{{ translate($displayFeature->name) }}</span>
                        </li>
                    @endif
                @endforeach
              </ul>
              @else
              <ul class="pricing-list">
                  @if($plan->carry_forward == \App\Enums\StatusEnum::TRUE->status())
                      <li style="display: flex; align-items: center; gap: 10px;">
                        <i class="bi bi-check-circle-fill" style="{{ $isRecommended ? 'color: var(--text-light) !important;' : '' }}"></i>
                        <span>{{ translate("Credit carry forward") }}</span>
                      </li>
                  @endif
                  <li style="display: flex; align-items: center; gap: 10px;">
                    <i class="bi bi-check-circle-fill" style="{{ $isRecommended ? 'color: var(--text-light) !important;' : '' }}"></i>
                    <span>{{ translate("Email, SMS & WhatsApp") }}</span>
                  </li>
                  <li style="display: flex; align-items: center; gap: 10px;">
                    <i class="bi bi-check-circle-fill" style="{{ $isRecommended ? 'color: var(--text-light) !important;' : '' }}"></i>
                    <span>{{ translate("Multi-channel messaging") }}</span>
                  </li>
              </ul>
              @endif
            </div>

            <div class="plan-action">
              <a href="{{ route("login") }}" class="i-btn btn--primary btn--xl pill w-100">
                {{ translate("Purchase Now") }}
              </a>
            </div>
          </div>
        </div>
        @endforeach
      </div>

      @if(!request()->routeIs('pricing') && $plans->count() > 3)
        <div class="text-center mt-4">
            <a href="{{ route('pricing') }}" class="i-btn btn--primary outline btn--lg pill">
                {{ translate("View All") }} {{ $plans->count() }} {{ translate("Plans") }} <i class="bi bi-arrow-right"></i>
            </a>
        </div>
      @endif
    </div>
  </div>
</section>

@push('style-push')
<style>
body.plan-modal-open {
  overflow: hidden !important;
  padding-right: 0 !important;
}
body.plan-modal-open #smooth-wrapper {
  overflow: hidden !important;
}
#plan-modals-container .modal {
  overflow-y: auto !important;
}
#plan-modals-container .modal-body {
  overflow-y: auto !important;
  -webkit-overflow-scrolling: touch;
}
</style>
@endpush

@push('script-push')
<script>
(function() {
  // Create modal container at body level
  var modalContainer = document.createElement('div');
  modalContainer.id = 'plan-modals-container';
  modalContainer.innerHTML = `
    @foreach($plans as $plan)
    <div class="modal fade" id="planInfoModal{{ $plan->id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 500px;">
        <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
          <div class="modal-header" style="background: var(--color-primary); padding: 20px 24px; border: none;">
            <div>
              <h5 class="modal-title" style="font-size: 20px; font-weight: 700; color: #fff; margin-bottom: 2px;">{{ ucfirst($plan->name) }}</h5>
              <p style="font-size: 13px; color: rgba(255,255,255,0.8); margin: 0;">{{ translate("Plan Details") }}</p>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="padding: 20px 24px; max-height: 60vh; overflow-y: auto; background: var(--card-bg);">
            @if($plan->carry_forward == \App\Enums\StatusEnum::TRUE->status())
            <div style="background: rgba(3, 201, 136, 0.1); border: 1px solid rgba(3, 201, 136, 0.2); border-radius: 10px; padding: 12px 16px; display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
              <i class="bi bi-arrow-repeat" style="font-size: 20px; color: #03c988;"></i>
              <span style="font-weight: 600; color: #03c988; font-size: 13px;">{{ translate("Credit carry forward when renewed") }}</span>
            </div>
            @endif
            <div style="margin-bottom: 20px;">
              <h6 style="font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-ternary); font-weight: 600; margin-bottom: 12px; padding-bottom: 6px; border-bottom: 1px solid var(--border-ternary);">{{ translate("Messaging Credits") }}</h6>
              <div style="display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border-ternary);">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                  <i class="bi bi-envelope-fill" style="font-size: 16px; color: #2563eb;"></i>
                </div>
                <div>
                  <strong style="display: block; font-weight: 600; color: var(--text-primary); font-size: 13px;">@if($plan->email->credits != -1){{ number_format($plan->email->credits) }} {{ translate("Email Credits") }}@else {{ translate("Unlimited Email Credits") }}@endif</strong>
                  @if(@$plan->email->credits_per_day != 0)<small style="font-size: 11px; color: var(--text-secondary);">{{ translate("Max") }} {{ number_format($plan->email->credits_per_day) }} {{ translate("per day") }}</small>@endif
                </div>
              </div>
              <div style="display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border-ternary);">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                  <i class="bi bi-chat-dots-fill" style="font-size: 16px; color: #059669;"></i>
                </div>
                <div>
                  <strong style="display: block; font-weight: 600; color: var(--text-primary); font-size: 13px;">@if($plan->sms->credits != -1){{ number_format($plan->sms->credits) }} {{ translate("SMS Credits") }}@else {{ translate("Unlimited SMS Credits") }}@endif</strong>
                  @if(@$plan->sms->credits_per_day != 0)<small style="font-size: 11px; color: var(--text-secondary);">{{ translate("Max") }} {{ number_format($plan->sms->credits_per_day) }} {{ translate("per day") }}</small>@endif
                </div>
              </div>
              <div style="display: flex; align-items: center; gap: 12px; padding: 10px 0;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                  <i class="bi bi-whatsapp" style="font-size: 16px; color: #25D366;"></i>
                </div>
                <div>
                  <strong style="display: block; font-weight: 600; color: var(--text-primary); font-size: 13px;">@if($plan->whatsapp->credits != -1){{ number_format($plan->whatsapp->credits) }} {{ translate("WhatsApp Credits") }}@else {{ translate("Unlimited WhatsApp Credits") }}@endif</strong>
                  @if(@$plan->whatsapp->credits_per_day != 0)<small style="font-size: 11px; color: var(--text-secondary);">{{ translate("Max") }} {{ number_format($plan->whatsapp->credits_per_day) }} {{ translate("per day") }}</small>@endif
                </div>
              </div>
            </div>
            @if($plan->sms->android->is_allowed == true || $plan->whatsapp->is_allowed == true || $plan->sms->is_allowed == true || $plan->email->is_allowed == true)
            <div style="margin-bottom: 20px;">
              <h6 style="font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-ternary); font-weight: 600; margin-bottom: 12px; padding-bottom: 6px; border-bottom: 1px solid var(--border-ternary);">{{ translate("Gateway Access") }}</h6>
              @if($plan->type == \App\Enums\StatusEnum::TRUE->status())
              <div style="display: flex; align-items: center; gap: 12px; padding: 8px 0;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                  <i class="bi bi-hdd-network-fill" style="font-size: 16px; color: #0284c7;"></i>
                </div>
                <div>
                  <strong style="display: block; font-weight: 600; color: var(--text-primary); font-size: 13px;">{{ translate("Admin Gateways Access") }}</strong>
                  <small style="font-size: 11px; color: var(--text-secondary);">@if($plan->sms->is_allowed == true){{ translate("SMS") }} @endif @if($plan->sms->android->is_allowed == true){{ translate("Android") }} @endif @if($plan->email->is_allowed == true){{ translate("Email") }}@endif</small>
                </div>
              </div>
              @if($plan->whatsapp->is_allowed == true)
              <div style="display: flex; align-items: center; gap: 12px; padding: 8px 0;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                  <i class="bi bi-whatsapp" style="font-size: 16px; color: #25D366;"></i>
                </div>
                <strong style="font-weight: 600; color: var(--text-primary); font-size: 13px;">{{ $plan->whatsapp->gateway_limit == 0 ? translate("Unlimited") : $plan->whatsapp->gateway_limit }} {{ translate("WhatsApp Devices") }}</strong>
              </div>
              @endif
              @else
              @if($plan->sms->android->is_allowed == true)
              <div style="display: flex; align-items: center; gap: 12px; padding: 8px 0;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                  <i class="bi bi-phone-fill" style="font-size: 16px; color: #059669;"></i>
                </div>
                <strong style="font-weight: 600; color: var(--text-primary); font-size: 13px;">{{ $plan->sms->android->gateway_limit == 0 ? translate("Unlimited") : $plan->sms->android->gateway_limit }} {{ translate("Android Gateways") }}</strong>
              </div>
              @endif
              @if($plan->whatsapp->is_allowed == true)
              <div style="display: flex; align-items: center; gap: 12px; padding: 8px 0;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                  <i class="bi bi-whatsapp" style="font-size: 16px; color: #25D366;"></i>
                </div>
                <strong style="font-weight: 600; color: var(--text-primary); font-size: 13px;">{{ $plan->whatsapp->gateway_limit == 0 ? translate("Unlimited") : $plan->whatsapp->gateway_limit }} {{ translate("WhatsApp Devices") }}</strong>
              </div>
              @endif
              @if($plan->email->is_allowed == true)
              @php $gateway_mail = (array)@$plan->email->allowed_gateways; $total_mail_gateway = 0; foreach ($gateway_mail as $email_value) { $total_mail_gateway += $email_value; } @endphp
              <div style="display: flex; align-items: center; gap: 12px; padding: 8px 0;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                  <i class="bi bi-envelope-fill" style="font-size: 16px; color: #2563eb;"></i>
                </div>
                <strong style="font-weight: 600; color: var(--text-primary); font-size: 13px;">{{ translate("Up to") }} {{ $total_mail_gateway }} {{ translate("Mail Gateways") }}</strong>
              </div>
              @endif
              @if($plan->sms->is_allowed == true)
              @php $gateway_sms = (array)@$plan->sms->allowed_gateways; $total_sms_gateway = 0; foreach ($gateway_sms as $sms_value) { $total_sms_gateway += $sms_value; } @endphp
              <div style="display: flex; align-items: center; gap: 12px; padding: 8px 0;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                  <i class="bi bi-chat-dots-fill" style="font-size: 16px; color: #059669;"></i>
                </div>
                <strong style="font-weight: 600; color: var(--text-primary); font-size: 13px;">{{ translate("Up to") }} {{ $total_sms_gateway }} {{ translate("SMS Gateways") }}</strong>
              </div>
              @endif
              @endif
            </div>
            @endif
            <div>
              <h6 style="font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-ternary); font-weight: 600; margin-bottom: 12px; padding-bottom: 6px; border-bottom: 1px solid var(--border-ternary);">{{ translate("Credit Usage") }}</h6>
              <div style="background: var(--gray-100); border-radius: 10px; padding: 12px;">
                <div style="display: flex; align-items: center; gap: 8px; padding: 4px 0; font-size: 12px; color: var(--text-secondary);"><span style="width: 5px; height: 5px; background: var(--color-primary); border-radius: 50%;"></span><span>{{ translate("1 Email Credit = 1 Email sent") }}</span></div>
                <div style="display: flex; align-items: center; gap: 8px; padding: 4px 0; font-size: 12px; color: var(--text-secondary);"><span style="width: 5px; height: 5px; background: var(--color-primary); border-radius: 50%;"></span><span>{{ translate("1 SMS Credit =") }} {{ site_settings("sms_word_count") }} {{ translate("characters") }}</span></div>
                <div style="display: flex; align-items: center; gap: 8px; padding: 4px 0; font-size: 12px; color: var(--text-secondary);"><span style="width: 5px; height: 5px; background: var(--color-primary); border-radius: 50%;"></span><span>{{ translate("1 WhatsApp Credit =") }} {{ site_settings("whatsapp_word_count") }} {{ translate("characters") }}</span></div>
              </div>
            </div>
          </div>
          <div class="modal-footer" style="background: var(--gray-100); border-top: 1px solid var(--border-ternary); padding: 16px 24px;">
            <a href="{{ route("login") }}" class="i-btn btn--primary btn--lg w-100">{{ translate("Get Started") }}</a>
          </div>
        </div>
      </div>
    </div>
    @endforeach
  `;

  // Append to body directly (outside smooth-wrapper)
  document.body.appendChild(modalContainer);

  // Handle info button clicks
  document.querySelectorAll('.plan-info-trigger').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var planId = this.getAttribute('data-plan-id');
      var modalEl = document.getElementById('planInfoModal' + planId);
      var modal = new bootstrap.Modal(modalEl);

      // Lock body scroll when modal opens
      modalEl.addEventListener('show.bs.modal', function() {
        document.body.classList.add('plan-modal-open');
        // Also pause ScrollSmoother if available
        if (window.ScrollSmoother && ScrollSmoother.get()) {
          ScrollSmoother.get().paused(true);
        }
      });

      // Unlock body scroll when modal closes
      modalEl.addEventListener('hidden.bs.modal', function() {
        document.body.classList.remove('plan-modal-open');
        // Resume ScrollSmoother if available
        if (window.ScrollSmoother && ScrollSmoother.get()) {
          ScrollSmoother.get().paused(false);
        }
      });

      modal.show();
    });
  });

  // Prevent scroll from propagating to background
  document.getElementById('plan-modals-container').addEventListener('wheel', function(e) {
    var modalBody = e.target.closest('.modal-body');
    if (modalBody) {
      var scrollTop = modalBody.scrollTop;
      var scrollHeight = modalBody.scrollHeight;
      var clientHeight = modalBody.clientHeight;
      var delta = e.deltaY;

      // At top and scrolling up, or at bottom and scrolling down - prevent
      if ((scrollTop <= 0 && delta < 0) || (scrollTop + clientHeight >= scrollHeight && delta > 0)) {
        // Allow if there's no scroll needed
        if (scrollHeight > clientHeight) {
          e.preventDefault();
        }
      }
    }
  }, { passive: false });
})();
</script>
@endpush

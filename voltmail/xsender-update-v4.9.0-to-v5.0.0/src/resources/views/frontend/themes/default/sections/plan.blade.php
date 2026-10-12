<section class="plan pb-130">
    <div class="container-fluid container-wrapper">
      <div class="row g-4 align-items-end mb-60">
        <div class="col-8">
          <div class="section-title mb-0">
            <h3> {{getTranslatedArrayValue(@$plan_content->section_value, 'heading') }} <span>
                <img src="{{$themeManager->asset('files/star.svg')}}" alt="long-arrow"/>
              </span>
            </h3>
          </div>
        </div>
        <div class="col-4">
          <div class="d-flex align-items-center justify-content-end gap-3">
            <a href="{{ route('pricing') }}" class="i-btn btn--dark outline btn--md pill"> {{ translate("More") }} <i class="bi bi-arrow-right fs-20"></i>
            </a>
          </div>
        </div>
      </div>

      <div class="pt-md-5">
        <div class="plan-card-wrapper">
          <div class="row g-xl-0 g-4">
            @foreach(request()->routeIs('pricing') ? $plans : $plans->take(3) as $plan)
            <div class="col-xl-4 col-md-6">
              <div class="plan-card h-100 {{$plan->recommended_status == \App\Enums\StatusEnum::TRUE->status() ? 'recommend mt-md-0 mt-5' : ''}}">
                @if($plan->recommended_status == \App\Enums\StatusEnum::TRUE->status())
                  <div class="recommend-tag">{{ translate("Recommended") }}</div>
                @endif
                <span class="plan-title"> {{ucfirst($plan->name)}}</span>

                {{-- Price Section --}}
                <div class="price">
                  <span>{{ getDefaultCurrencySymbol(json_decode(site_settings("currencies"), true)) }}</span>
                  <h5>{{shortAmount($plan->amount)}}</h5>
                  <p>/ {{ $plan->duration }} {{ translate("Days") }}</p>
                </div>

                <p class="plan-desc mt-3">{{$plan->description}}</p>

                {{-- Credits Summary Box --}}
                <div style="border: 1px solid #e2e8f0; border-radius: 16px; padding: 10px; margin-bottom: 24px;">
                  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 3px;">
                    <span style="font-size: 13px; font-weight: 600; color: #374151; text-transform: uppercase; letter-spacing: 0.5px;">{{ translate("Credits Included") }}</span>
                    <button type="button" class="plan-info-trigger" data-plan-id="{{ $plan->id }}" style="background: none; border: none; color: #6366f1; font-size: 20px; cursor: pointer; padding: 0;">
                      <i class="bi bi-info-circle-fill"></i>
                    </button>
                  </div>
                  <div style="display: flex;flex-direction:row;justify-content: space-between; gap: 10px;">
                    <div style="display:flex; flex-direction:column; justify-content:center flex: 1; gap:0px; border-radius: 12px; text-align: center;">
                      <!-- <div style="width: 36px; height: 36px;line-height:1;margin-bottom:3px; border-radius: 10px; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <i class="bi bi-envelope-fill" style="font-size: 16px; color: #2563eb;"></i>
                      </div> -->
                      <div style="font-size: 20px; font-weight: 700; color: #111827; line-height: 1.2;">@if($plan->email->credits != -1){{ number_format($plan->email->credits) }}@else <span style="font-size: 24px;">&infin;</span>@endif</div>
                      <div style="font-size: 11px; font-weight: 500; color: #6b7280; text-transform: uppercase; margin-top: 0px;">{{ translate("Email") }}</div>
                    </div>
                    <div style="display:flex; flex-direction:column; justify-content:center flex: 1; gap:0px; border-radius: 12px; text-align: center;">
                      <!-- <div style="width: 36px; height: 36px;line-height:1;margin-bottom:3px; border-radius: 10px; background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <i class="bi bi-chat-dots-fill" style="font-size: 16px; color: #059669;"></i>
                      </div> -->
                      <div style="font-size: 20px; font-weight: 700; color: #111827; line-height: 1.2;">@if($plan->sms->credits != -1){{ number_format($plan->sms->credits) }}@else <span style="font-size: 24px;">&infin;</span>@endif</div>
                      <div style="font-size: 11px; font-weight: 500; color: #6b7280; text-transform: uppercase; margin-top: 0px;">{{ translate("SMS") }}</div>
                    </div>
                    <div style="display:flex; flex-direction:column; justify-content:center flex: 1; gap:0px; border-radius: 12px; text-align: center;">
                      <!-- <div style="width: 36px; height: 36px;line-height:1;margin-bottom:3px; border-radius: 10px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <i class="bi bi-whatsapp" style="font-size: 16px; color: #25D366;"></i>
                      </div> -->
                      <div style="font-size: 20px; font-weight: 700; color: #111827; line-height: 1.2;">@if($plan->whatsapp->credits != -1){{ number_format($plan->whatsapp->credits) }}@else <span style="font-size: 24px;">&infin;</span>@endif</div>
                      <div style="font-size: 11px; font-weight: 500; color: #6b7280; text-transform: uppercase; margin-top: 0px;">{{ translate("WhatsApp") }}</div>
                    </div>
                  </div>
                </div>

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
                          <li><i class="{{ $displayFeature->icon ?? 'bi bi-check2' }}" style="color: #22c55e;"></i>{{ translate($displayFeature->name) }}</li>
                      @else
                          <li style="opacity: 0.5;"><i class="{{ $displayFeature->icon ?? 'bi bi-x-lg' }}" style="color: #ef4444;"></i><span style="text-decoration: line-through; color: #9ca3af;">{{ translate($displayFeature->name) }}</span></li>
                      @endif
                  @endforeach
                </ul>
                @endif

                <div class="plan-action">
                  <a href="{{ route("login") }}" class="i-btn btn--primary outline btn--xl pill w-100"> {{ translate("Purchase Now") }} </a>
                </div>
              </div>
            </div>
            @endforeach
          </div>
        </div>

        {{-- "More" link only on homepage when there are more than 3 plans --}}
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
  /* Modal Portal Styles - Modals rendered outside main content */
  body.plan-modal-open {
    overflow: hidden !important;
    padding-right: 0 !important;
  }

  #plan-modals-portal {
    position: relative;
    z-index: 99999;
  }

  #plan-modals-portal .modal {
    z-index: 99999 !important;
  }

  #plan-modals-portal .modal-backdrop {
    z-index: 99998 !important;
  }

  #plan-modals-portal .modal-dialog {
    z-index: 100000 !important;
  }

  #plan-modals-portal .modal-content {
    border-radius: 16px;
    border: none;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  }

  #plan-modals-portal .modal-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 1px solid #e2e8f0;
    padding: 20px 24px;
  }

  #plan-modals-portal .modal-title {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 4px;
  }

  #plan-modals-portal .modal-subtitle {
    font-size: 14px;
    color: #6b7280;
    margin: 0;
  }

  #plan-modals-portal .modal-body {
    padding: 24px;
    max-height: 60vh;
    overflow-y: auto;
  }

  #plan-modals-portal .modal-footer {
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
    padding: 16px 24px;
  }

  #plan-modals-portal .credit-section-title {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #9ca3af;
    font-weight: 600;
    margin-bottom: 14px;
    padding-bottom: 8px;
    border-bottom: 1px solid #f3f4f6;
  }

  #plan-modals-portal .credit-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
  }

  #plan-modals-portal .credit-item i {
    font-size: 18px;
  }

  #plan-modals-portal .credit-item strong {
    font-weight: 600;
    color: #111827;
    font-size: 14px;
  }

  #plan-modals-portal .credit-item small {
    display: block;
    font-size: 12px;
    color: #6b7280;
  }

  #plan-modals-portal .carry-forward-badge {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
    border: 1px solid rgba(16, 185, 129, 0.2);
    border-radius: 12px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
  }

  #plan-modals-portal .carry-forward-badge i {
    font-size: 20px;
    color: #10b981;
  }

  #plan-modals-portal .carry-forward-badge span {
    font-weight: 600;
    color: #065f46;
    font-size: 14px;
  }

  #plan-modals-portal .usage-info {
    background: #f9fafb;
    border-radius: 10px;
    padding: 14px;
  }

  #plan-modals-portal .usage-info-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 0;
    font-size: 13px;
    color: #4b5563;
  }

  #plan-modals-portal .usage-info-item .dot {
    width: 5px;
    height: 5px;
    background: #9ca3af;
    border-radius: 50%;
    flex-shrink: 0;
  }
</style>
@endpush

@push('script-push')
<script>
(function() {
  // Create modal portal container and append to body
  var portalContainer = document.createElement('div');
  portalContainer.id = 'plan-modals-portal';

  // Build modal HTML for all plans
  var modalsHTML = '';

  @foreach($plans as $plan)
  @php
    $planData = [
      'id' => $plan->id,
      'name' => ucfirst($plan->name),
      'carryForward' => $plan->carry_forward == \App\Enums\StatusEnum::TRUE->status(),
      'email' => [
        'credits' => $plan->email->credits,
        'creditsPerDay' => @$plan->email->credits_per_day ?? 0,
        'isAllowed' => $plan->email->is_allowed ?? false,
      ],
      'sms' => [
        'credits' => $plan->sms->credits,
        'creditsPerDay' => @$plan->sms->credits_per_day ?? 0,
        'isAllowed' => $plan->sms->is_allowed ?? false,
        'android' => [
          'isAllowed' => $plan->sms->android->is_allowed ?? false,
          'gatewayLimit' => $plan->sms->android->gateway_limit ?? 0,
        ],
      ],
      'whatsapp' => [
        'credits' => $plan->whatsapp->credits,
        'creditsPerDay' => @$plan->whatsapp->credits_per_day ?? 0,
        'isAllowed' => $plan->whatsapp->is_allowed ?? false,
        'gatewayLimit' => $plan->whatsapp->gateway_limit ?? 0,
      ],
      'type' => $plan->type == \App\Enums\StatusEnum::TRUE->status(),
    ];

    // Calculate gateway info for non-admin gateway type
    $emailGateways = 0;
    $smsGateways = 0;
    if (!$planData['type']) {
      $gateway_mail = (array)@$plan->email->allowed_gateways;
      foreach ($gateway_mail as $v) { $emailGateways += $v; }
      $gateway_sms = (array)@$plan->sms->allowed_gateways;
      foreach ($gateway_sms as $v) { $smsGateways += $v; }
    }
    $planData['emailGatewaysTotal'] = $emailGateways;
    $planData['smsGatewaysTotal'] = $smsGateways;
  @endphp

  modalsHTML += `
    <div class="modal fade" id="planDetailsModal{{ $plan->id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h5 class="modal-title">{{ $planData['name'] }}</h5>
              <p class="modal-subtitle">{{ translate("Plan Details") }}</p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            @if($planData['carryForward'])
            <div class="carry-forward-badge">
              <i class="bi bi-arrow-repeat"></i>
              <span>{{ translate("Credit carry forward when renewed") }}</span>
            </div>
            @endif

            <div style="margin-bottom: 20px;">
              <h6 class="credit-section-title mb-2">{{ translate("Messaging Credits") }}</h6>
              <div class="credit-item d-flex align-items-center justify-content-start gap-2">
                <i class="bi bi-envelope-fill" style="color: #2563eb;"></i>
                <div>
                  <span>@if($planData['email']['credits'] != -1){{ number_format($planData['email']['credits']) }} {{ translate("Email Credits") }}@else {{ translate("Unlimited Email Credits") }}@endif</span>
                  @if($planData['email']['creditsPerDay'] != 0)<small>{{ translate("Max") }} {{ number_format($planData['email']['creditsPerDay']) }} {{ translate("per day") }}</small>@endif
                </div>
              </div>
              <div class="credit-item d-flex align-items-center justify-content-start gap-2">
                <i class="bi bi-chat-dots-fill" style="color: #059669;"></i>
                <div>
                  <span>@if($planData['sms']['credits'] != -1){{ number_format($planData['sms']['credits']) }} {{ translate("SMS Credits") }}@else {{ translate("Unlimited SMS Credits") }}@endif</span>
                  @if($planData['sms']['creditsPerDay'] != 0)<small>{{ translate("Max") }} {{ number_format($planData['sms']['creditsPerDay']) }} {{ translate("per day") }}</small>@endif
                </div>
              </div>
              <div class="credit-item d-flex align-items-center justify-content-start gap-2">
                <i class="bi bi-whatsapp" style="color: #25D366;"></i>
                <div>
                  <span>@if($planData['whatsapp']['credits'] != -1){{ number_format($planData['whatsapp']['credits']) }} {{ translate("WhatsApp Credits") }}@else {{ translate("Unlimited WhatsApp Credits") }}@endif</span>
                  @if($planData['whatsapp']['creditsPerDay'] != 0)<small>{{ translate("Max") }} {{ number_format($planData['whatsapp']['creditsPerDay']) }} {{ translate("per day") }}</small>@endif
                </div>
              </div>
            </div>

            @if($planData['sms']['android']['isAllowed'] || $planData['whatsapp']['isAllowed'] || $planData['sms']['isAllowed'] || $planData['email']['isAllowed'])
            <div style="margin-bottom: 20px;">
              <h6 class="credit-section-title mb-2">{{ translate("Gateway Access") }}</h6>
              @if($planData['type'])
                <div class="credit-item">
                  <i class="bi bi-hdd-network-fill" style="color: #0ea5e9;"></i>
                  <div>
                    <span>{{ translate("Admin Gateways Access") }}</span>
                    <small>
                      @if($planData['sms']['isAllowed']) {{ translate("SMS") }} @endif
                      @if($planData['sms']['android']['isAllowed']) {{ translate("Android") }} @endif
                      @if($planData['email']['isAllowed']) {{ translate("Email") }} @endif
                    </small>
                  </div>
                </div>
                @if($planData['whatsapp']['isAllowed'])
                <div class="credit-item">
                  <i class="bi bi-whatsapp" style="color: #25D366;"></i>
                  <span>{{ $planData['whatsapp']['gatewayLimit'] == 0 ? translate("Unlimited") : $planData['whatsapp']['gatewayLimit'] }} {{ translate("WhatsApp Devices") }}</span>
                </div>
                @endif
              @else
                @if($planData['sms']['android']['isAllowed'])
                <div class="credit-item">
                  <i class="bi bi-phone-fill" style="color: #059669;"></i>
                  <span>{{ $planData['sms']['android']['gatewayLimit'] == 0 ? translate("Unlimited") : $planData['sms']['android']['gatewayLimit'] }} {{ translate("Android Gateways") }}</span>
                </div>
                @endif
                @if($planData['whatsapp']['isAllowed'])
                <div class="credit-item">
                  <i class="bi bi-whatsapp" style="color: #25D366;"></i>
                  <span>{{ $planData['whatsapp']['gatewayLimit'] == 0 ? translate("Unlimited") : $planData['whatsapp']['gatewayLimit'] }} {{ translate("WhatsApp Devices") }}</span>
                </div>
                @endif
                @if($planData['email']['isAllowed'] && $planData['emailGatewaysTotal'] > 0)
                <div class="credit-item">
                  <i class="bi bi-envelope-fill" style="color: #2563eb;"></i>
                  <span>{{ translate("Up to") }} {{ $planData['emailGatewaysTotal'] }} {{ translate("Mail Gateways") }}</span>
                </div>
                @endif
                @if($planData['sms']['isAllowed'] && $planData['smsGatewaysTotal'] > 0)
                <div class="credit-item">
                  <i class="bi bi-chat-dots-fill" style="color: #059669;"></i>
                  <span>{{ translate("Up to") }} {{ $planData['smsGatewaysTotal'] }} {{ translate("SMS Gateways") }}</span>
                </div>
                @endif
              @endif
            </div>
            @endif

            <div>
              <h6 class="credit-section-title mb-2">{{ translate("Credit Usage") }}</h6>
              <div class="usage-info">
                <div class="usage-info-item">
                  <span class="dot"></span>
                  <span>{{ translate("1 Email Credit = 1 Email sent") }}</span>
                </div>
                <div class="usage-info-item">
                  <span class="dot"></span>
                  <span>{{ translate("1 SMS Credit =") }} {{ site_settings("sms_word_count") }} {{ translate("plain characters") }}</span>
                </div>
                <div class="usage-info-item">
                  <span class="dot"></span>
                  <span>{{ translate("1 WhatsApp Credit =") }} {{ site_settings("whatsapp_word_count") }} {{ translate("characters") }}</span>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <a href="{{ route("login") }}" class="i-btn btn--primary btn--lg w-100">{{ translate("Get Started") }}</a>
          </div>
        </div>
      </div>
    </div>
  `;
  @endforeach

  portalContainer.innerHTML = modalsHTML;
  document.body.appendChild(portalContainer);

  // Attach click handlers to trigger buttons
  document.querySelectorAll('.plan-info-trigger').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();

      var planId = this.getAttribute('data-plan-id');
      var modalEl = document.getElementById('planDetailsModal' + planId);

      if (modalEl && typeof bootstrap !== 'undefined') {
        var modal = new bootstrap.Modal(modalEl);

        // Add body class when modal shows
        modalEl.addEventListener('show.bs.modal', function() {
          document.body.classList.add('plan-modal-open');
        });

        // Remove body class when modal hides
        modalEl.addEventListener('hidden.bs.modal', function() {
          document.body.classList.remove('plan-modal-open');
        });

        // Prevent scroll propagation inside modal
        var modalBody = modalEl.querySelector('.modal-body');
        if (modalBody) {
          modalBody.addEventListener('wheel', function(e) {
            var scrollTop = this.scrollTop;
            var scrollHeight = this.scrollHeight;
            var height = this.clientHeight;
            var delta = e.deltaY;

            if ((scrollTop === 0 && delta < 0) || (scrollTop + height >= scrollHeight && delta > 0)) {
              e.preventDefault();
            }
          }, { passive: false });
        }

        modal.show();
      }
    });
  });
})();
</script>
@endpush

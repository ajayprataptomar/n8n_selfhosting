@php
$fixedSmsContent = array_values(array_filter($service_breadcrumb_content, function($item) {
    return $item->section_key === "service_breadcrumb.sms.fixed_content";
}))[0] ?? null;
$fixedWhatsappContent = array_values(array_filter($service_breadcrumb_content, function($item) {
    return $item->section_key === "service_breadcrumb.whatsapp.fixed_content";
}))[0] ?? null;
$fixedEmailContent = array_values(array_filter($service_breadcrumb_content, function($item) {
    return $item->section_key === "service_breadcrumb.email.fixed_content";
}))[0] ?? null;
@endphp

<section class="service pt-120 pb-120">
  <div class="container-fluid container-wrapper">
    <div class="row">
      <div class="col-xxl-5 col-xl-7 col-md-10 mx-auto">
        <div class="section-title d-flex flex-column align-items-center text-center">
          <h3 class="title-anim">
            {{getTranslatedArrayValue(@$service_menu_common->section_value, 'heading') }}
          </h3>
        </div>
      </div>
    </div>
    
    <div class="service-list">
      <div class="row g-4">
        @if($fixedSmsContent)
        <div class="col-xl-4 col-md-6">
          <a href="./sms.html" class="service-item fade-item">
            <div class="service-title">
              <span><i class="bi bi-chat-text"></i></span>
              <h4>
                {{ translate($fixedSmsContent->section_value['heading']) }}
              </h4>
            </div>

            <p class="service-description">
              {{ translate($fixedSmsContent->section_value['sub_heading']) }}
            </p>
          </a>
        </div>
        @endif
        @if($fixedWhatsappContent)
          <div class="col-xl-4 col-md-6">
            <a href="./email.html" class="service-item fade-item">
              <div class="service-title">
                <span><i class="bi bi-envelope"></i></span>
                <h4>{{ translate($fixedWhatsappContent->section_value['heading']) }}</h4>
                
              </div>

              <p class="service-description">
                {{ translate($fixedWhatsappContent->section_value['sub_heading']) }}
              </p>
            </a>
          </div>
        @endif

        @if($fixedEmailContent)
          <div class="col-xl-4 col-md-6">
            <a href="./whatsapp.html" class="service-item fade-item">
              <div class="service-title">
                <span><i class="bi bi-whatsapp"></i></span>
                <h4>{{ translate($fixedEmailContent->section_value['heading']) }}</h4>
              </div>

              <p class="service-description">
                {{ translate($fixedEmailContent->section_value['sub_heading']) }}
              </p>
            </a>
          </div>
        @endif
      </div>
    </div>
  </div>
</section>
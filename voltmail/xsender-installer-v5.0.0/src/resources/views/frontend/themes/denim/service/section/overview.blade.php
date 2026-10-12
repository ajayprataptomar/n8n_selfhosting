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
$featureSmsElementContent = array_values(array_filter($service_feature_element, function($item) {
    return $item->section_key === "service_feature.sms.element_content";
})) ?? null;
$featureEmailElementContent = array_values(array_filter($service_feature_element, function($item) {
    return $item->section_key === "service_feature.email.element_content";
})) ?? null;
$featureWhatsAppElementContent = array_values(array_filter($service_feature_element, function($item) {
    return $item->section_key === "service_feature.whatsapp.element_content";
})) ?? null;
@endphp

@if(!request("type"))
<section class="our-services pt-120 pb-120">
  <div class="container-fluid container-wrapper">
    
    <div class="our-service-list">
      @if($fixedSmsContent)
        <a href="{{ route("service", ["type" => "sms"]) }}" class="our-service-item fade-item">
          <span class="our-service-item-count">{{ translate("01") }}</span>

          <div class="service-content mt-3">
            <div class="service-title">
              <h4>{{ translate($fixedSmsContent->section_value['heading']) }}</h4>
            </div>

            <div class="service-content-right">
              <p class="lines-anim">
                {{ translate($fixedSmsContent->section_value['sub_heading']) }}
              </p>
              <ul>
                @foreach($featureSmsElementContent as $content)
                  <li><span></span> {{ $content->section_value['heading'] }}</li>
                @endforeach
              </ul>
            </div>
          </div>
        </a>
      @endif
      @if($fixedEmailContent)
      <a href="{{ route("service", ["type" => "email"]) }}" class="our-service-item fade-item">
        <span class="our-service-item-count">{{ translate("02") }}</span>

        <div class="service-content mt-3">
          <div class="service-title">
            <h4>{{ translate($fixedEmailContent->section_value['heading']) }}</h4>
          </div>

          <div class="service-content-right">
            <p class="lines-anim">
              {{ translate($fixedEmailContent->section_value['sub_heading']) }}
            </p>
            <ul>
              @foreach($featureEmailElementContent as $content)
                <li><span></span> {{ $content->section_value['heading'] }}</li>
              @endforeach
            </ul>
          </div>
        </div>
      </a>
      @endif
      @if($fixedWhatsappContent)
      <a href="{{ route("service", ["type" => "whatsapp"]) }}" class="our-service-item fade-item">
        <span class="our-service-item-count">{{ translate("03") }}</span>

        <div class="service-content mt-3">
          <div class="service-title">
            <h4>{{ translate($fixedWhatsappContent->section_value['heading']) }}</h4>
          </div>

          <div class="service-content-right">
            <p class="lines-anim">
             {{ translate($fixedWhatsappContent->section_value['sub_heading']) }}
            </p>
            <ul>
              @foreach($featureEmailElementContent as $content)
                <li><span></span> {{ $content->section_value['heading'] }}</li>
              @endforeach
            </ul>
          </div>
        </div>
      </a>
      @endif
    </div>
  </div>
</section>
@else
@include($themeManager->view('service.section.highlight'), ["type" => request("type")])
@include($themeManager->view('service.section.feature'), ["type" => request("type")])
@endif

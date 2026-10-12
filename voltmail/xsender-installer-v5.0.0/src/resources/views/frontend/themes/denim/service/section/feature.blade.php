@php
$fixedContent = array_values(array_filter($service_feature_content, function($item) use($type) {
    return $item->section_key === "service_feature.$type.fixed_content";
}))[0] ?? null;
$elementContent = array_values(array_filter($service_feature_element, function($item) use($type) {
    return $item->section_key === "service_feature.$type.element_content";
})) ?? null;
@endphp

<section class="service pt-120 pb-120">
  <div class="container-fluid container-wrapper">
    <div class="row">
      <div class="col-xxl-5 col-xl-7 col-md-10 mx-auto">
        <div class="section-title d-flex flex-column align-items-center text-center">
          <h3 class="title-anim">{{getTranslatedArrayValue(@$fixedContent->section_value, 'title') }}</h3>
        </div>
      </div>
    </div>

    <div class="service-list">
      <div class="row g-4">
        @foreach($elementContent as $element)
          <div class="col-xl-4 col-md-6 fade-item">
            <div class="service-item">
              <div class="service-title">
                <span><i class="bi bi-chat-text"></i></span>
                <h4>{{ $element->section_value['heading'] }}</h4>
              </div>

              <p class="service-description">
                {{ $element->section_value['description'] }}
              </p>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>
</section>
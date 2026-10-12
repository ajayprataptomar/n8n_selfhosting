@php
$fixedContent = array_values(array_filter($service_highlight_content, function($item) use($type) {
    return $item->section_key === "service_highlight.$type.fixed_content";
}))[0] ?? null;
$elementContent = array_values(array_filter($service_highlight_element, function($item) use($type) {
    return $item->section_key === "service_highlight.$type.element_content";
})) ?? null;
@endphp

<section class="pt-120">
  <div class="container-fluid container-wrapper">
    <div class="row">
      <div class="col-xl-5">
        <div class="section-title">
          <h3 class="title-anim">{{getTranslatedArrayValue(@$fixedContent->section_value, 'heading') }} </h3>
          <p class="title-anim">
            {{getTranslatedArrayValue(@$fixedContent->section_value, 'description') }} 
          </p>
        </div>
      </div>
      <div class="col-xl-7 gs_reveal fromRight">
        <div>
          <img src="{{showImage(config("setting.file_path.frontend.service_highlight_image.path").'/'.getArrayValue(@$fixedContent->section_value, 'service_highlight_image'),config("setting.file_path.frontend.service_highlight_image.size"))}}" alt="{{ getArrayValue(@$fixedContent->section_value, 'service_highlight_image') }}"  class="img-fluid"/>
        </div>
      </div>
    </div>
  </div>
</section>
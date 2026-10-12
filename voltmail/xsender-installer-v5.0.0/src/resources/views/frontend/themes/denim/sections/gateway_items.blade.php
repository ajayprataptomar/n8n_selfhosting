<div class="providers fade-item">
     <div class="swiper providers-slider">
          <div class="swiper-wrapper">
               @foreach($gateway_element as $element)
                    <div class="swiper-slide" title="{{ $element->section_value['name'] }}">
                         <div class="provider-img">
                              <img src="{{showImage(config("setting.file_path.frontend.element_content.gateway.gateway_image.path").'/'.@$element->section_value['gateway_image'],config("setting.file_path.frontend.element_content.gateway.gateway_image.size"))}}" alt="gateway" />
                         </div>
                    </div>
               @endforeach
          </div>
     </div>
</div>
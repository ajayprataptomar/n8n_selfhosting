<section class="testimonial">
  <div class="container-fluid container-wrapper">
    <div class="row g-0">
      <div class="col-xxl-5 col-xl-6 col-md-9 mx-auto">
        <div class="section-title d-flex align-items-center justify-content-md-center text-center">
          <h3 class="title-anim">
            {{getTranslatedArrayValue(@$feedback_content->section_value, 'heading') }}
          </h3>
        </div>
      </div>
    </div>

    <div class="testimonial-wrapper">
      <div class="row g-4">
        @foreach($feedback_element->take(6) as $element)
          <div class="col-lg-4 col-sm-6">
            <div class="review-card fade-item">
              
              <p>
                {{ translate($element->section_value['message']) }}
              </p>

              <div class="reviewer">
                <span class="reviewer-img">
                  <img src="{{showImage(config("setting.file_path.frontend.element_content.feedback.reviewer_image.path").'/'.@$element->section_value['reviewer_image'],config("setting.file_path.frontend.element_content.feedback.reviewer_image.size"))}}" alt="feature"/>
                </span>

                <div class="reviewer-info">
                  <h6>{{ translate($element->section_value['name']) }}</h6>
                  <span>{{ translate($element->section_value['designation']) }}</span>
                </div>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>
</section>
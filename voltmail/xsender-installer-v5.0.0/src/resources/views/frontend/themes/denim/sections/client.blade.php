<section class="our-journey">
  <div class="container-fluid container-wrapper">
    <div class="row gy-5 align-items-center">
      <div class="col-xxl-7 col-lg-6">
        <div class="journey-header">
          <span></span>
          <h6>{{ getTranslatedArrayValue(@$client_content->section_value, 'heading') }}</h6>
        </div>
        <div class="journey-counter">
          @foreach(@$client_multi_content->section_value ?? [] as $value)
            <div class="journey-counter-item fade-item">
              <span data-value="{{ $value['heading'] }}">{{ translate($value['heading']) }}</span>
              <p>{{ translate($value['sub_heading']) }}</p>
            </div>
          @endforeach
        </div>
      </div>

      <div class="col-xxl-5 col-lg-6">
        <div class="journey-content">
          <p class="lines-anim">
            {{ getTranslatedArrayValue(@$client_content->section_value, 'sub_heading') }}
          </p>
        </div>
      </div>
    </div>
  </div>
</section>
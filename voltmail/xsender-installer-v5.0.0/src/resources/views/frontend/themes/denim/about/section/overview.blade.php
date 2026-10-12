<section class="about pt-120 pb-120">
  <div class="container-fluid container-wrapper">
    <div class="row g-0 justify-content-center">
      <div class="col-xl-10">
        <div class="row g-0 justify-content-center">
          <div class="col-xl-10">
            <div class="section-title d-flex justify-content-center text-center title-anim">
              <h3>{{getTranslatedArrayValue(@$about_overview->section_value, 'heading') }}</h3>
            </div>
          </div>
        </div>

        <div class="about-content-banner">
          <img src="{{showImage(config("setting.file_path.frontend.about_overview_image.path").'/'.getArrayValue(@$about_overview->section_value, 'about_overview_image'),config("setting.file_path.frontend.about_overview_image.size"))}}" alt="{{ getArrayValue(@$about_overview->section_value, 'about_overview_image') }}" />
          </a>
        </div>
      </div>
    </div>
</section>
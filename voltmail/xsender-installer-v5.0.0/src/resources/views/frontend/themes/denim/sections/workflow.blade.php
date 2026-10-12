<section class="work-process pb-120">
  <div class="container-fluid container-wrapper">
    <div class="row">
      <div class="col-xxl-6 col-lg-6">
        <div class="section-title">
          <h3 class="title-anim">
            {{getTranslatedArrayValue(@$workflow_content->section_value, 'heading') }}
          </h3>
        </div>
      </div>
    </div>

    <div class="work-process-wrapper">
      <ul class="work-process-list">
        @foreach($workflow_element as $element)

        <li class="work-process-item fade-item">
          <div class="work-process-content">
            <h5>{{ translate($element->section_value['heading']) }}</h5>
            <div class="work-process-description">
              <p>
                {{ $element->section_value['item_one']['process'] ?? 'N\A'}}
              </p>
            </div>
          </div>

          <div class="flex-shrink-0">
            <button class="work-process-action">
              <i class="bi bi-arrow-up-right"></i>
            </button>
          </div>

          <div class="work-process-img">
            <img src="{{showImage(config("setting.file_path.frontend.element_content.workflow.process_image.path").'/'.@$element->section_value['item_one']['process_image'],config("setting.file_path.frontend.element_content.workflow.process_image.size"))}}" alt="feature" class="rounded w-100" />
          </div>
        </li>
        @endforeach
      </ul>
    </div>
  </div>
</section>
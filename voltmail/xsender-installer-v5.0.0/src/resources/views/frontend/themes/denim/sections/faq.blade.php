<section class="faqs pt-120">
  <div class="container-fluid container-wrapper">
    <div class="row g-xl-5 gy-5 align-items-center">
      <div class="col-xxl-5 col-xl-6 gs_reveal fromLeft">
        <div class="section-title mb-0">
          <h3 class="title-anim">
            {{getTranslatedArrayValue(@$faq_content->section_value, 'heading') }}
          </h3>
        </div>
      </div>

      <div class="col-xxl-7 col-xl-6 gs_reveal fromRight">
        <div class="faqs-accordion">
          <div class="accordion-wrapper">
            <div class="accordion" id="faq-accordion">
              @foreach($faq_element as $element)
                <div class="accordion-item">
                  <h2 class="accordion-header" id="heading-{{ $element->id }}">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                      data-bs-target="#collapse-{{ $element->id }}" aria-expanded="true" aria-controls="collapse-{{ $element->id }}">
                      {{ $element->section_value['question'] }}
                    </button>
                  </h2>
                  <div id="collapse-{{ $element->id }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" aria-labelledby="heading-{{ $element->id }}">
                    <div class="accordion-body">
                      <p>
                        {{ $element->section_value['answer'] }}
                      </p>
                    </div>
                  </div>
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
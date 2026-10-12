<section class="breadcrumb-banner pt-120 pb-130 mb-4">
  <div class="container-fluid container-wrapper">
    <div class="banner-wrapper">
      <div class="breadcrumb-content">
        
        <div class="breadcrumb-bottom">
          <div>
            <h2 class="breadcrumb-title title-anim">{{getTranslatedArrayValue(@$page_content->section_value, 'heading') }}</h2>

            <div class="breadcrumb-actions mt-4">
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                  <li class="breadcrumb-item"><a href="{{ url("/") }}">{{ translate("Home") }}</a></li>
                  <li class="breadcrumb-item active" aria-current="page">
                    {{translate("Policy") }}
                  </li>
                </ol>
              </nav>
            </div>
          </div>

          <div class="breadcrumb-description">
            <p class="lines-anim">
                {{getTranslatedArrayValue(@$page_content->section_value, 'sub_heading') }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
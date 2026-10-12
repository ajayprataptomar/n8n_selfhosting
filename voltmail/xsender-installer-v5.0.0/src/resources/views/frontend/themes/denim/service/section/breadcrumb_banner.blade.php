<section class="breadcrumb-banner pt-120 pb-120">
  <div class="container-fluid container-wrapper">
    <div class="banner-wrapper">
      <div class="breadcrumb-content">
        
        <div class="breadcrumb-bottom">
          <div>
            <h2 class="breadcrumb-title title-anim"> {{getTranslatedArrayValue(@$service_menu_common->section_value, 'heading') }} </h2>
            <div class="breadcrumb-actions mt-4">
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                  <li class="breadcrumb-item"><a href="{{ url("/") }}">{{ translate("Home") }}</a></li>
                  @if(request("type"))
                    <li class="breadcrumb-item"><a href="{{ route("service") }}">{{translate("Our Services") }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">
                      {{ ucfirst(request("type")." ".translate("Marketing")) }}
                    </li>
                  @else
                    <li class="breadcrumb-item active" aria-current="page">
                      {{translate("Our Services") }}
                    </li>
                  @endif
                  
                </ol>
              </nav>
            </div>
          </div>

          <div class="breadcrumb-description">
            <p class="lines-anim">
              {{getTranslatedArrayValue(@$fixedContent->section_value, 'sub_heading') }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
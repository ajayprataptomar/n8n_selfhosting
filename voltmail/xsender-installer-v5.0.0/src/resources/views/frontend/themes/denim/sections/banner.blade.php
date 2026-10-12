<section class="banner">
  <div class="container-fluid container-wrapper">
    <div class="banner-wrapper">
      <div class="row gy-5 align-items-center">
        <div class="col-xl-6">
          <div class="banner-content pe-xxl-5 me-xxl-5">
            <h1 class="banner-title quote-title">
              @php
                echo(applyStyleToTitle(getTranslatedArrayValue(@$banner_content->section_value, 'heading'), true ))
              @endphp
            </h1>
          </div>
        </div>
        <div class="col-xl-6">
          <div class="banner-right">
            <p class="banner-description lines-anim">
               {{ getTranslatedArrayValue(@$banner_content->section_value, 'sub_heading') }} 
            </p>

            <div class="d-flex align-items-center flex-wrap gap-4 mt-30">
              <a href="{{ getArrayValue(@$banner_content->section_value, 'video_url') }}" data-dimbox="youtube" data-dimbox-ratio="16x9"
                class="i-btn btn--primary outline btn--xl pill video-play-btn">
                {{ translate("View demo") }}
              </a>

              <a href="{{ getArrayValue(@$banner_content->section_value, 'btn_url') }}" class="i-btn btn--primary btn--xl pill">
                {{ getTranslatedArrayValue(@$banner_content->section_value, 'btn_name') }}
              </a>
            </div>

            <div class="overall-user fade-item">
              <div class="avatar-group">
                @foreach($users->take(3) as $user)
                  <span class="avatar-group-item">
                    <img class="avatar avatar-lg circle img-fluid" src="{{showImage(filePath()['profile']['user']['path'].'/'.$user->image, filePath()['profile']['user']['size'])}}" alt="{{ $user->name }}" />
                  </span>
                @endforeach
              </div>

              <div class="user-content">
                <h6>{{ $users->count().' '.translate("Business People")}}</h6>
                <p>{{ translate("Already registered") }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      @include($themeManager->view('sections.gateway_items'))
    </div>
  </div>
</section>
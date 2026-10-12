<div class="col-lg-6 order-lg-0 order-1">
  <div class="auth-left">
      <div>
      <div class="section-title">
          <h3 class="title-anim">
            {{getTranslatedArrayValue(@$user_auth_content->section_value, 'heading') }}
          </h3>
      </div>

      <div class="auth-features">
        @if(@$user_auth_multi_content->section_value)
          @foreach($user_auth_multi_content->section_value as $content)
              
              <div class="auth-feature-item">
              <span class="auth-feature-icon text-gradient">
                  <i class="bi bi-people"></i>
              </span>

              <div class="auth-feature-info">
                  <h6>{{ \Illuminate\Support\Arr::get($content, "heading", "") }}</h6>
                  <p>
                  {{ \Illuminate\Support\Arr::get($content, "sub_heading", "") }}
                  </p>
              </div>
            </div>
          @endforeach
        @endif
      </div>
    </div>
  </div>
</div>
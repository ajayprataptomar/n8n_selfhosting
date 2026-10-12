<section class="blog pt-120">
  <div class="container-fluid container-wrapper">
    <div class="row align-items-center mb-60">
      <div class="col-lg-8 col-6">
        <div class="section-title mb-0">
          <h3 class="title-anim">{{getTranslatedArrayValue(@$blog_content->section_value, 'heading') }}</h3>
        </div>
      </div>

      <div class="col-lg-4 col-6">
        <div class="d-flex align-items-center justify-content-end gap-3">
          <a href="{{ route('blog') }}" class="i-btn btn--primary btn--xl pill">
             {{ translate("More") }} <i class="bi bi-chevron-right fs-14"></i>
          </a>
        </div>
      </div>
    </div>

    <div class="row g-4">
      @foreach($blogs->take(3) as $blog)
        <div class="col-xl-4 col-md-6 fade-item">
          <div class="blog-card">
            <div class="blog-img">
              <img src="{{showImage(config("setting.file_path.blog_images.path").'/'.@$blog->image,config("setting.file_path.blog_images.size"))}}" alt="blog" loading="lazy"/>
            </div>

            <a href="{{ route('blog', ['uid' => $blog->uid]) }}" class="blog-title">
              <h4>{{ $blog->title }}</h4>
            </a>
            <p class="fs-14 mt-2">
              {{ (limit_html_descriptions(text: $blog->description, ellipsis: true)) }}
            </p>

            <a href="{{ route('blog', ['uid' => $blog->uid]) }}" class="blog-view-more-btn">
              <span>{{ translate("View more") }}</span>
              <i class="bi bi-chevron-right fs-14"></i>
            </a>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>
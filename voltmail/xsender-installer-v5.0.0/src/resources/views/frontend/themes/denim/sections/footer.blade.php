<footer class="footer pt-120">
  <div class="footer-content">

    <div class="footer-bottom">
      <div class="container-fluid container-wrapper">
        <div class="row gx-lg-5 gy-5">
          <div class="col-xxl-4 col-lg-4 col-md-10 fade-item">
            <div class="me-xl-5">
               <a href="{{ url('/') }}" class="logo-wrapper">
                <img src="{{showImage(config('setting.file_path.site_logo.path').'/'.site_settings('site_logo'),config('setting.file_path.site_logo.size'))}}" alt="logo" />
              </a>
              <p>
                {{getTranslatedArrayValue(@$footer_content->section_value, 'heading') }}
              </p>

              <ul class="footer-social">
                 @foreach($social_element as $element)
                  
                  <li title="{{ $element->section_value['title']  }}">
                    <a href="{{$element->section_value['url'] }}">
                      @php echo $element->section_value['icon'] @endphp
                    </a>
                  </li>
                  @endforeach
              </ul>
            </div>

          </div>

          <div class="col-xxl-7 offset-xxl-1 col-lg-8">
            <div class="row gy-5 gx-4">
              <div class="col-lg-4 col-sm-4 col-6 fade-item">
                <div class="footer-nav">
                  <h6>{{ translate("Quick Navigation") }}</h6>
                  <ul>
                    <li>
                      <a href="{{ route("blog") }}">{{ translate("Blogs") }}</a>
                    </li>
                    <li>
                      <a href="{{ route("service") }}">{{ translate("About Us") }}</a>
                    </li>
                    <li>
                      <a href="{{ route("pricing") }}">{{ translate("Pricing Plans") }}</a>
                    </li>
                    <li>
                      <a href="{{ route("contact") }}">{{ translate("Contact") }}</a>
                    </li>
                  </ul>
                </div>
              </div>

              <div class="col-lg-4 col-sm-4 col-6 fade-item">
                <div class="footer-nav">
                  <h6>{{ translate("About") }}</h6>
                  <ul>
                    <li>
                      <a href="{{ route("about") }}">{{ translate("About Us") }}</a>
                    </li>
                    <li>
                      <a href="{{ route("contact") }}">{{ translate("Contact Us") }}</a>
                    </li>
                  </ul>
                </div>
              </div>

              <div class="col-lg-4 col-sm-4 fade-item">
                <div class="footer-nav">
                  <h6>{{ translate("Information") }}</h6>
                  <div class="contact-info">
                    <a href="mailto:info@example.com" class="contact-info-item text-break">
                      <i class="bi bi-envelope"></i>
                       {{ site_settings('email') }} 
                    </a>

                    <a href="tel:012-345-67891" class="contact-info-item">
                      <i class="bi bi-telephone"></i>
                      {{ site_settings('phone') }}
                    </a>

                    <span class="contact-info-item">
                      <i class="bi bi-geo-alt"></i>
                      {{ site_settings('address') }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="copy-right">
          <p>&copy; {{ site_settings('copyright') }}</p>
          <ul>
            @foreach($pages as $page)
                <li>
                    <a target="_blank" href="{{route('page',[Str::slug(getArrayValue(@$page->section_value, 'title')),$page->id])}}" class="footer-menu">{{getTranslatedArrayValue(@$page->section_value, 'title')}}</a>
                </li>
            @endforeach
          </ul>
        </div>
      </div>
    </div>
  </div>
</footer>

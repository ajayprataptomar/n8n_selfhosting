<section class="contact pt-120">
  <div class="container-fluid container-wrapper">
    <div class="contact-wrapper">
      <div class="row gy-5 gx-lg-5 align-items-end">
        <div class="col-xl-6 col-lg-6">
          <div class="section-title">
            <h3 class="title-anim">{{getTranslatedArrayValue(@$contact_content->section_value, 'heading') }}</h3>
          </div>

          <ul class="contact-list">
            <li class="fade-item">
              <span>
                {{ translate("ADDRESS") }}
              </span>
              {{ site_settings('address') }}
            </li>

            <li class="fade-item">
              <span>
                {{ translate("PHONE") }}
              </span>
              {{ site_settings('phone') }}
            </li>

            <li class="fade-item">
              <span>
                {{ translate("EMAIL") }}
              </span>
              {{ site_settings('email') }} 
            </li>
          </ul>
        </div>

        <div class="col-xl-5 offset-xl-1 col-lg-6">
          <form action="{{ route("contact.get_in_touch") }}" class="contact-form" method="POST">
            @csrf
            <div class="form-element fade-item">
              <input type="text" name="subject" value="{{ site_settings("site_name"). " get in touch contact" }}" hidden>
            </div>

            <div class="form-element fade-item">
              <input type="text" name="email_from_name" placeholder="{{ translate("Enter your name") }}" class="form-control" />
            </div>

            <div class="form-element fade-item">
              <input type="email" name="email_to_address" class="form-control" placeholder="{{ translate("Enter your email address") }}" />
            </div>

            <div class="form-element fade-item">
              <textarea name="message" class="form-control" rows="4" placeholder="{{ translate("Go ahead, We are listening...") }}"></textarea>
            </div>

            <button type="submit" class="i-btn btn--dark btn--xl pill w-100 submit-btn mt-3">
              {{ translate("Submit") }}
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>
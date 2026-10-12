<header class="header">
    <div class="container-fluid container-wrapper">
      <div class="header-wrapper">
        <div class="header-left">
          <a href="{{ url('/') }}" class="logo-wrapper">
            <img src="{{showImage(config('setting.file_path.site_logo.path').'/'.site_settings('site_logo'),config('setting.file_path.site_logo.size'))}}" alt="logo" />
          </a>
        </div>

        <div class="header-middle">
          <div class="sidebar">
            <div class="sidebar-logo">
              <img src="{{showImage(config('setting.file_path.site_logo.path').'/'.site_settings('site_logo'),config('setting.file_path.site_logo.size'))}}" alt="logo" />
            </div>

            <div class="sidebar-menu-wrapper">
              <nav>
                <ul>
                  <li>
                    <a href="{{ route("service") }}" class="menu-link {{ request()->routeIs('service') ? 'active' : '' }}">{{ translate("Our Services") }}</a>
                  </li>

                  <li>
                    <a href="{{ route("about") }}" class="{{ request()->routeIs('about') ? 'active' : '' }} menu-link">{{ translate("About Us") }}</a>
                  </li>

                  <li>
                    <a href="{{ route("blog") }}" class="{{ request()->routeIs('blog') ? 'active' : '' }} menu-link">{{ translate("Blogs") }}</a>
                  </li>

                  <li>
                    <a href="{{ route("pricing") }}" class="{{ request()->routeIs('pricing') ? 'active' : '' }} menu-link">{{ translate("Pricing") }}</a>
                  </li>

                  <li>
                    <a href="{{ route("contact") }}" class="{{ request()->routeIs('contact') ? 'active' : '' }} menu-link">{{ translate("Contact") }}</a>
                  </li>
                </ul>
              </nav>

              <div class="d-lg-none align-items-start align-items-lg-center gap-3 d-flex flex-column mt-80">
                @auth
                  <a href="{{ route('user.dashboard') }}" class="i-btn btn--primary btn--xl pill w-100">
                    {{ translate("My Account") }}
                  </a>
                @else
                  <a href="{{route('login')}}" class="i-btn btn--primary outline btn--xl pill w-100">
                    {{ translate("Sign in") }}
                  </a>
                  @if(site_settings("onboarding_bonus") == \App\Enums\StatusEnum::TRUE->status())
                  <a href="{{route('login')}}" class="i-btn btn--primary btn--xl pill w-100">
                    {{ translate("Try Free") }}
                  </a>
                  @endif
                @endauth
              </div>
            </div>
          </div>
        </div>

        <div class="header-right">
          <button class="icon-btn btn-lg dark-soft circle fs-22 btn-ghost theme-toggle" id="theme-toggle">
            <i class="bi bi-moon-fill"></i>
          </button>

          <div class="lang-dropdown">
              <div class="icon-btn btn-lg dark-soft circle btn-ghost dropdown-toggle" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                   <span class="flag-img">
                      <img class="lang-image" src="{{ asset('assets/theme/global/images/flags/' . App::getLocale() . '.svg') }}" alt="{{ App::getLocale() }}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';" />
                      <i class="ri-global-line" style="display:none;font-size:18px;width:24px;height:24px;align-items:center;justify-content:center;"></i>
                  </span>
              </div>
              <div class="dropdown-menu dropdown-menu-end">
                  <ul>
                      @foreach($top_bar_languages as $language)
                          <li>
                              <a class="pointer language-switch" data-lang-id="{{ $language->id }}" data-lang-code="{{ $language->code }}" onclick="changeLang('{{ $language->id }}', '{{ $language->code }}')">
                                  <i class="flag-icon-{{ $language->code }} flag-icon flag-icon-squared rounded-circle"></i>
                                  {{ $language->name }}
                              </a>
                          </li>
                      @endforeach
                  </ul>
              </div>
          </div>

          <div class="d-lg-flex align-items-center gap-4 d-none">
            @auth
              <a href="{{ route('user.dashboard') }}" class="i-btn btn--primary btn--xl pill"> {{ translate("My Account") }} </a>
            @else
              <a href="{{route('login')}}" class="fs-16 fw-semibold text-dark">
                {{ translate("Sign in") }}
              </a>
              @if(site_settings("onboarding_bonus") == \App\Enums\StatusEnum::TRUE->status())
              <a href="{{route('login')}}" class="i-btn btn--primary btn--xl pill"> {{ translate("Try Free") }} </a>
              @endif
            @endauth
          </div>

          <button class="d-lg-none icon-btn btn-lg primary-solid circle" id="menu-btn">
            <i class="bi bi-list"></i>
          </button>
        </div>
      </div>
    </div>
     <script>
        function changeLang(val, code) {
            fetch("{{ route('language.change') }}/" + val, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    localStorage.setItem('app_language', data.lang_code);
                    window.location.reload();
                } else {
                    console.error('Language change failed:', data.message);
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</header>
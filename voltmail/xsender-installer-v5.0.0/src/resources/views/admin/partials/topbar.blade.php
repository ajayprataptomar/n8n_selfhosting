<header class="header">
    <div class="header-left">
    <div class="header-action d-lg-none">
        <button class="btn-icon" type="button" id="sidebar-handler">
        <i class="ri-menu-2-fill"></i>
        </button>
    </div>
    <div class="header-action d-sm-flex d-none">
        <a href="{{route('admin.system.cache.clear')}}" class="btn-icon">
        <i class="ri-refresh-line"></i>
        </a>
    </div>
    <div class="header-action d-sm-flex d-none">
        <a href="{{url('/')}}" target="_blank" class="btn-icon">
        <i class="ri-earth-line"></i>
        </a>
    </div>
    </div>
    <div class="header-right">
        <div class="header-action">
            <div class="header-action">
                <form class="settingsForm themeForm" method="post">
                    @csrf
                    <input type="hidden" name="site_settings[theme_mode]" id="theme_mode" value="{{ site_settings('theme_mode') == \App\Enums\StatusEnum::FALSE->status() ? \App\Enums\StatusEnum::TRUE->status() : \App\Enums\StatusEnum::FALSE->status() }}">
                    <button class="btn-icon theme-toggler" type="button">
                        @if(site_settings('theme_mode') == \App\Enums\StatusEnum::FALSE->status())
                            <i class="ri-sun-line"></i>
                        @else
                            <i class="ri-moon-line"></i>
                        @endif
                    </button>
                </form>
            </div>
        </div>
        <div class="header-action">
            <div class="lang-dropdown">
                <div class="btn-icon dropdown-toggle" role="button" data-bs-toggle="dropdown" aria-expanded="false">
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
        </div>
        @php
            $adminUser = auth()->guard('admin')->user();
            $adminImage = $adminUser?->image ?? null;
            $adminUsername = $adminUser?->username ?? 'Admin';
            $adminName = $adminUser?->name ?? '';
        @endphp
        <div class="header-action">
            <div class="profile-dropdown">
            <div class="topbar-profile dropdown-toggle" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="profile-avatar">
                    <img src="{{ showImage(config('setting.file_path.admin_profile.path') . '/' . ($adminImage ?? ''), config('setting.file_path.admin_profile.size')) }}" alt="{{ $adminUsername }}">
                </span>
                <div class="topbar-profile-info d-sm-block d-none">
                <p>{{ ucfirst($adminUsername) }}</p>
                <span>{{ $adminName }}</span>
                </div>
            </div>
            <div class="dropdown-menu dropdown-menu-end">
                <ul>
                <li>
                    <a class="dropdown-item" href="{{ route("admin.profile") }}">
                    <i class="ri-user-line"></i> {{ translate("My Account") }} </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('admin.communication.api') }}">
                    <i class="ri-code-s-slash-line"></i> {{ translate("API") }} </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{route('admin.logout')}}">
                    <i class="ri-logout-box-line"></i> {{ translate("Logout") }} </a>
                </li>
                </ul>
            </div>
            </div>
        </div>
    </div>
    <script>
        function changeLang(val, code) {
            fetch("{{ route('admin.language.change') }}/" + val, {
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

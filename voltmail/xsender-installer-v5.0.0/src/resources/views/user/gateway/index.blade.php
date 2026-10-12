@extends('user.layouts.app')
@section('panel')
    <main class="main-body">
        <div class="container-fluid px-0 main-content">
            <div class="page-header">
                <div class="page-header-left">
                    <h2>{{ $title }}</h2>
                    <div class="breadcrumb-wrapper">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="{{ route('user.dashboard') }}">{{ translate('Dashboard') }}</a>
                                </li>
                                <li class="breadcrumb-item active" aria-current="page"> {{ $title }} </li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
            @php
                $user = $user ?? auth()->user();
                $type = array_key_exists('type', planAccess($user)) ? planAccess($user)['type'] : \App\Enums\StatusEnum::FALSE->status();
                $admin_gateway_access = false;
                if($type == \App\Enums\StatusEnum::TRUE->status()) {
                    $admin_gateway_access = true;
                }

                $isEmailChannel = !$admin_gateway_access && (
                    request()->routeIs('user.gateway.email.*')
                    || request()->routeIs('user.gateway.sending-domain.*')
                    || request()->routeIs('user.gateway.tracking-domain.*')
                );
                $isSmsChannel = !$admin_gateway_access && request()->routeIs('user.gateway.sms.*');
                $isWhatsappChannel = request()->routeIs('user.gateway.whatsapp.*');
            @endphp

            @if(!$admin_gateway_access)
                {{-- Level 1: Channel Selector Cards (multiple channels available) --}}
                <div class="row g-3 mb-4">
                    <div class="col-lg-4 col-md-4">
                        <a href="{{ route('user.gateway.email.index') }}" class="text-decoration-none">
                            <div class="card channel-card {{ $isEmailChannel ? 'channel-card--active' : '' }} mb-0">
                                <div class="card-body d-flex align-items-center gap-3 py-3 px-3">
                                    <div class="channel-card__icon">
                                        <i class="ri-mail-line"></i>
                                    </div>
                                    <div>
                                        <div class="channel-card__title">{{ translate("Email") }}</div>
                                        <span class="channel-card__desc">{{ translate("SMTP, Domains & Tracking") }}</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-lg-4 col-md-4">
                        <a href="{{ route('user.gateway.sms.api.index') }}" class="text-decoration-none">
                            <div class="card channel-card {{ $isSmsChannel ? 'channel-card--active' : '' }} mb-0">
                                <div class="card-body d-flex align-items-center gap-3 py-3 px-3">
                                    <div class="channel-card__icon">
                                        <i class="ri-message-2-line"></i>
                                    </div>
                                    <div>
                                        <div class="channel-card__title">{{ translate("SMS") }}</div>
                                        <span class="channel-card__desc">{{ translate("API & Android Gateways") }}</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-lg-4 col-md-4">
                        <a href="{{ route('user.gateway.whatsapp.cloud.api.index') }}" class="text-decoration-none">
                            <div class="card channel-card {{ $isWhatsappChannel ? 'channel-card--active' : '' }} mb-0">
                                <div class="card-body d-flex align-items-center gap-3 py-3 px-3">
                                    <div class="channel-card__icon">
                                        <i class="ri-whatsapp-line"></i>
                                    </div>
                                    <div>
                                        <div class="channel-card__title">{{ translate("WhatsApp") }}</div>
                                        <span class="channel-card__desc">{{ translate("Cloud API & Node Device") }}</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                {{-- Level 2: Channel Sub-Tabs --}}
                <div class="form-tab mb-4">
                    <ul class="nav" role="tablist">
                        @if($isEmailChannel)
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{ request()->routeIs('user.gateway.email.*') ? 'active' : '' }}"
                                   href="{{ route('user.gateway.email.index') }}">
                                    <i class="ri-mail-line"></i> {{ translate("Email Gateways") }}
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{ request()->routeIs('user.gateway.sending-domain.*') ? 'active' : '' }}"
                                   href="{{ route('user.gateway.sending-domain.index') }}">
                                    <i class="ri-shield-keyhole-line"></i> {{ translate("Sending Domains") }}
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{ request()->routeIs('user.gateway.tracking-domain.*') ? 'active' : '' }}"
                                   href="{{ route('user.gateway.tracking-domain.index') }}">
                                    <i class="ri-link"></i> {{ translate("Tracking Domains") }}
                                </a>
                            </li>
                        @elseif($isSmsChannel)
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{ request()->routeIs('user.gateway.sms.api.*') ? 'active' : '' }}"
                                   href="{{ route('user.gateway.sms.api.index') }}">
                                    <i class="ri-message-2-line"></i> {{ translate("SMS API Gateway") }}
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{ request()->routeIs('user.gateway.sms.android.*') ? 'active' : '' }}"
                                   href="{{ route('user.gateway.sms.android.index') }}">
                                    <i class="ri-android-line"></i> {{ translate("Android Gateway") }}
                                </a>
                            </li>
                        @elseif($isWhatsappChannel)
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{ request()->routeIs('user.gateway.whatsapp.cloud.*') ? 'active' : '' }}"
                                   href="{{ route('user.gateway.whatsapp.cloud.api.index') }}">
                                    <i class="ri-whatsapp-line"></i> {{ translate("WhatsApp Cloud API") }}
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{ request()->routeIs('user.gateway.whatsapp.device.*') ? 'active' : '' }}"
                                   href="{{ route('user.gateway.whatsapp.device.index') }}">
                                    <i class="ri-whatsapp-line"></i> {{ translate("WhatsApp Node Device") }}
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>
            @else
                {{-- Single channel mode: show clean sub-tab navigation only --}}
                <div class="form-tab mb-4">
                    <ul class="nav" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link {{ request()->routeIs('user.gateway.whatsapp.cloud.*') ? 'active' : '' }}"
                               href="{{ route('user.gateway.whatsapp.cloud.api.index') }}">
                                <i class="ri-whatsapp-line"></i> {{ translate("WhatsApp Cloud API") }}
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link {{ request()->routeIs('user.gateway.whatsapp.device.*') ? 'active' : '' }}"
                               href="{{ route('user.gateway.whatsapp.device.index') }}">
                                <i class="ri-whatsapp-line"></i> {{ translate("WhatsApp Node Device") }}
                            </a>
                        </li>
                    </ul>
                </div>
            @endif

            <div class="tab-content">
                @yield('tab-content')
            </div>
        </div>
    </main>
@endsection
@section('modal')
    @yield('modal')
@endsection

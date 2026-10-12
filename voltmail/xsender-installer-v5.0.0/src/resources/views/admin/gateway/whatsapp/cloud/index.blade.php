@extends('admin.gateway.index')
@section('tab-content')
    @php
        // Check Meta Configuration status
        $metaConfigurations = \App\Models\MetaConfiguration::where('status', 'active')->get();
        $hasMetaConfig = $metaConfigurations->isNotEmpty();
        $defaultConfig = $metaConfigurations->firstWhere('is_default', true);
        $configsMissingConfigId = $metaConfigurations->whereNull('config_id')->count();

        // Check legacy config
        $hasLegacyConfig = !empty(site_settings(\App\Enums\SettingKey::META_APP_ID->value))
            && !empty(site_settings(\App\Enums\SettingKey::META_APP_SECRET->value));

        $canUseEmbeddedSignup = $hasMetaConfig || $hasLegacyConfig;
    @endphp

    <style>
        .config-alert {
            border-radius: 12px;
            border: none;
            padding: 20px;
            margin-bottom: 24px;
        }
        .config-alert--danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
            border-left: 4px solid #ef4444;
        }
        .config-alert--warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(245, 158, 11, 0.05) 100%);
            border-left: 4px solid #f59e0b;
        }
        .config-alert--success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
            border-left: 4px solid #10b981;
        }
        .config-alert--info {
            background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.1) 0%, rgba(var(--primary-rgb), 0.05) 100%);
            border-left: 4px solid var(--primary-color);
        }
        .config-alert-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        .config-alert--danger .config-alert-icon {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }
        .config-alert--warning .config-alert-icon {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
        }
        .config-alert--success .config-alert-icon {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }
        .config-alert--info .config-alert-icon {
            background: rgba(var(--primary-rgb), 0.15);
            color: var(--primary-color);
        }
        .config-alert-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #1f2937;
        }
        .config-alert-text {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 12px;
        }
        .config-alert-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .config-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }
        .config-btn--primary {
            background: var(--primary-color);
            color: #fff;
        }
        .config-btn--primary:hover {
            background: var(--primary-color);
            opacity: 0.9;
            color: #fff;
        }
        .config-btn--outline {
            background: transparent;
            border: 1px solid var(--color-border);
            color: var(--text-primary);
        }
        .config-btn--outline:hover {
            background: #f9fafb;
            color: #374151;
        }
        .config-btn--danger {
            background: #ef4444;
            color: #fff;
        }
        .config-btn--danger:hover {
            background: #dc2626;
            color: #fff;
        }
        .config-btn--warning {
            background: #f59e0b;
            color: #fff;
        }
        .config-btn--warning:hover {
            background: #d97706;
            color: #fff;
        }
        .config-btn--success {
            background: #10b981;
            color: #fff;
        }
        .config-btn--success:hover {
            background: #059669;
            color: #fff;
        }
        .config-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .config-status-badge--success {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }
        .config-status-badge--warning {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
        }
        .config-status-badge--danger {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }
        .quick-config-card {
            background: var(--card-bg);
            border: 1px solid var(--color-border);
            border-radius: 10px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 12px;
        }
        .quick-config-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .quick-config-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
        }
        .quick-config-env {
            font-size: 12px;
            color: var(--text-secondary);
        }
        .gateway-table-container {
            overflow-x: auto;
        }
        .gateway-table-container table {
            width: 100%;
            border-collapse: collapse;
        }
        .gateway-table-container th {
            background: #f9fafb;
            padding: 14px 16px;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        .gateway-table-container td {
            padding: 16px;
            font-size: 14px;
            color: #4b5563;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }
        .gateway-table-container tr:hover td {
            background: #f9fafb;
        }
        .gateway-name {
            font-weight: 600;
            color: #1f2937;
        }
        .gateway-address {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 2px;
        }
        .setup-method-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }
        .setup-method-badge--embedded {
            background: rgba(var(--primary-rgb), 0.1);
            color: var(--primary-color);
        }
        .setup-method-badge--manual {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }
        .template-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: rgba(var(--primary-rgb), 0.1);
            color: var(--primary-color);
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }
        .template-link:hover {
            background: rgba(var(--primary-rgb), 0.2);
            color: var(--primary-color);
        }
        .action-btn-group {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 16px;
        }
        .action-btn--edit {
            background: rgba(var(--primary-rgb), 0.1);
            color: var(--primary-color);
        }
        .action-btn--edit:hover {
            background: rgba(var(--primary-rgb), 0.2);
        }
        .action-btn--sync {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        .action-btn--sync:hover {
            background: rgba(59, 130, 246, 0.2);
        }
        .action-btn--delete {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        .action-btn--delete:hover {
            background: rgba(239, 68, 68, 0.2);
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-state-icon {
            width: 80px;
            height: 80px;
            background: rgba(var(--primary-rgb), 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px;
            color: var(--primary-color);
        }
        .empty-state-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
        }
        .empty-state-text {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 20px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
    </style>

    <div class="tab-pane active fade show" id="{{ url()->current() }}" role="tabpanel">
        {{-- Configuration Status Alert --}}
        @if(!$canUseEmbeddedSignup)
            {{-- No configuration at all --}}
            <div class="config-alert config-alert--danger">
                <div class="d-flex align-items-start gap-3">
                    <div class="config-alert-icon">
                        <i class="ri-error-warning-line"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="config-alert-title">{{ translate('Meta Configuration Required') }}</h5>
                        <p class="config-alert-text">
                            {{ translate('You need to configure your Meta App credentials before you can connect WhatsApp Business Accounts via Embedded Signup. This is required for the WhatsApp Cloud API integration.') }}
                        </p>
                        <div class="config-alert-actions">
                            <a href="{{ route('admin.whatsapp.configuration.create') }}" class="config-btn config-btn--danger">
                                <i class="ri-add-line"></i> {{ translate('Add Meta Configuration') }}
                            </a>
                            <a href="https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/overview" target="_blank" class="config-btn config-btn--outline">
                                <i class="ri-external-link-line"></i> {{ translate('View Documentation') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @elseif(!$hasMetaConfig && $hasLegacyConfig)
            {{-- Only legacy config --}}
            <div class="config-alert config-alert--warning">
                <div class="d-flex align-items-start gap-3">
                    <div class="config-alert-icon">
                        <i class="ri-shield-star-line"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="config-alert-title">{{ translate('Upgrade to Meta 2025 Configuration') }}</h5>
                        <p class="config-alert-text">
                            {{ translate('You are using legacy Meta credentials. Starting 2025, Meta requires Configuration ID (config_id) for Embedded Signup. Upgrade now for enhanced features including onboarding tracking, health monitoring, and auto token refresh.') }}
                        </p>
                        <div class="config-alert-actions">
                            <a href="{{ route('admin.whatsapp.configuration.create') }}" class="config-btn config-btn--warning">
                                <i class="ri-shield-star-line"></i> {{ translate('Create Enterprise Configuration') }}
                            </a>
                            <a href="{{ route('admin.system.setting', ['type' => 'meta_configurations']) }}" class="config-btn config-btn--outline">
                                <i class="ri-settings-3-line"></i> {{ translate('View Legacy Settings') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @elseif($hasMetaConfig && $configsMissingConfigId > 0)
            {{-- Has config but missing config_id --}}
            <div class="config-alert config-alert--warning">
                <div class="d-flex align-items-start gap-3">
                    <div class="config-alert-icon">
                        <i class="ri-alert-line"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="config-alert-title">{{ translate('Configuration ID Missing') }}</h5>
                        <p class="config-alert-text">
                            {{ $configsMissingConfigId }} {{ translate('configuration(s) are missing the required Configuration ID (config_id) for Meta 2025 compliance. Add the config_id to enable enhanced Tech Provider features.') }}
                        </p>
                        <div class="config-alert-actions">
                            <a href="{{ route('admin.whatsapp.configuration.index') }}" class="config-btn config-btn--warning">
                                <i class="ri-settings-3-line"></i> {{ translate('Update Configurations') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @elseif($hasMetaConfig && $defaultConfig)
            {{-- All good - has proper config --}}
            <div class="config-alert config-alert--success">
                <div class="d-flex align-items-start gap-3">
                    <div class="config-alert-icon">
                        <i class="ri-checkbox-circle-line"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="config-alert-title">{{ translate('Meta Official') }}</h5>
                        <p class="config-alert-text mb-0">
                            {{ translate('Your Meta configuration is properly set up. You can connect WhatsApp Business Accounts using Embedded Signup.') }}
                        </p>
                        <div class="quick-config-card">
                            <div class="quick-config-info">
                                <i class="ri-checkbox-circle-fill text-success fs-5"></i>
                                <div>
                                    <div class="quick-config-name">{{ $defaultConfig->name }}</div>
                                    <div class="quick-config-env">
                                        {{ ucfirst($defaultConfig->environment) }} • {{ $defaultConfig->api_version }}
                                        @if($defaultConfig->config_id)
                                            • <span class="text-success"><i class="ri-shield-check-line"></i> {{ translate('Config ID Set') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <a href="{{ route('admin.whatsapp.configuration.index') }}" class="config-btn config-btn--outline">
                                <i class="ri-settings-3-line"></i> {{ translate('Manage') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Search & Filter --}}
        <div class="table-filter mb-4">
            <form action="{{ route(Route::currentRouteName()) }}" class="filter-form">
                <div class="row g-3">
                    <div class="col-xxl-3 col-xl-4 col-lg-4">
                        <div class="filter-search">
                            <input type="search" value="{{ request()->search }}" name="search" class="form-control"
                                id="filter-search" placeholder="{{ translate('Search by name') }}" />
                            <span><i class="ri-search-line"></i></span>
                        </div>
                    </div>

                    <div class="col-xxl-5 col-xl-6 col-lg-7 offset-xxl-4 offset-xl-2">
                        <div class="filter-action">
                            <div class="input-group">
                                <input type="text" class="form-control" id="datePicker" name="date"
                                    value="{{ request()->input('date') }}" placeholder="{{ translate('Filter by date') }}"
                                    aria-describedby="filterByDate">
                                <span class="input-group-text" id="filterByDate">
                                    <i class="ri-calendar-2-line"></i>
                                </span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <button type="submit" class="filter-action-btn ">
                                    <i class="ri-menu-search-line"></i> {{ translate('Filter') }}
                                </button>
                                <a class="filter-action-btn bg-danger text-white"
                                    href="{{ route(Route::currentRouteName()) }}">
                                    <i class="ri-refresh-line"></i> {{ translate('Reset') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- Main Card --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-left">
                    <h4 class="card-title">{{ $title }}</h4>
                </div>
                <div class="card-header-right">
                    <a class="i-btn btn--info btn--sm me-2"
                        href="{{ route('admin.whatsapp.configuration.index') }}">
                        <i class="ri-settings-3-line"></i> {{ translate('Meta Configurations') }}
                    </a>

                    @if($canUseEmbeddedSignup)
                        <button class="i-btn btn--success btn--sm me-2" type="button" id="embeddedSignupBtn">
                            <i class="ri-link"></i> {{ translate("Connect via Embedded Signup") }}
                        </button>
                    @else
                        <button class="i-btn btn--secondary btn--sm me-2" type="button" disabled title="{{ translate('Configure Meta credentials first') }}">
                            <i class="ri-link"></i> {{ translate("Connect via Embedded Signup") }}
                        </button>
                    @endif

                    <button class="i-btn btn--primary btn--sm add-whatsapp-business-account" type="button" data-bs-toggle="modal" data-bs-target="#addWhatsappBusinessAccount">
                        <i class="ri-add-fill fs-16"></i> {{ translate("Add Manually") }}
                    </button>
                </div>
            </div>

            <div class="card-body px-0 pt-0">
                @if($gateways->isNotEmpty())
                    <div class="gateway-table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>{{ translate('Business Account') }}</th>
                                    <th>{{ translate('Setup Method') }}</th>
                                    <th>{{ translate('Templates') }}</th>
                                    <th>{{ translate('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($gateways as $item)
                                    <tr>
                                        <td>
                                            <div class="gateway-name">{{ textFormat(['_'], $item->name, ' ') }}</div>
                                            @if(!empty($item->address))
                                                <div class="gateway-address">{{ $item->address }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if($item->setup_method === 'embedded' || $item->setup_method === 'embedded_v2')
                                                <span class="setup-method-badge setup-method-badge--embedded">
                                                    <i class="ri-link"></i> {{ translate('Embedded') }}
                                                    @if($item->setup_method === 'embedded_v2')
                                                        <small>(v2)</small>
                                                    @endif
                                                </span>
                                            @else
                                                <span class="setup-method-badge setup-method-badge--manual">
                                                    <i class="ri-settings-3-line"></i> {{ translate('Manual') }}
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.template.index', ['channel' => \App\Enums\System\ChannelTypeEnum::WHATSAPP->value, 'cloud_id' => $item->id]) }}"
                                                class="template-link">
                                                <i class="ri-file-list-3-line"></i>
                                                {{ $item->templates_count }} {{ translate('Templates') }}
                                            </a>
                                        </td>
                                        <td>
                                            <div class="action-btn-group">
                                                <button
                                                    class="action-btn action-btn--edit update-whatsapp-business-account"
                                                    type="button"
                                                    data-url="{{ route('admin.gateway.whatsapp.cloud.api.update', ['id' => $item->id])}}"
                                                    data-name="{{ $item->name }}"
                                                    data-per_message_min_delay="{{ $item->per_message_min_delay }}"
                                                    data-per_message_max_delay="{{ $item->per_message_max_delay }}"
                                                    data-delay_after_count="{{ $item->delay_after_count }}"
                                                    data-delay_after_duration="{{ $item->delay_after_duration }}"
                                                    data-reset_after_count="{{ $item->reset_after_count }}"
                                                    data-credentials="{{ json_encode($item->meta_data) }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#updateWhatsappBusinessAccount"
                                                    title="{{ translate('Edit Gateway') }}">
                                                    <i class="ri-edit-line"></i>
                                                </button>
                                                <button type="button"
                                                    class="action-btn action-btn--sync sync"
                                                    data-value="{{ $item->id }}"
                                                    title="{{ translate('Sync Templates') }}">
                                                    <i class="ri-loop-right-line"></i>
                                                </button>
                                                <button
                                                    class="action-btn action-btn--delete delete-whatsapp-cloud-api"
                                                    type="button"
                                                    data-item-id="{{ $item->id }}"
                                                    data-url="{{route('admin.gateway.whatsapp.cloud.api.destroy', ['id' => $item->id ])}}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteWhatsappCloudApi"
                                                    title="{{ translate('Delete Gateway') }}">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @include('admin.partials.pagination', ['paginator' => $gateways])
                @else
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="ri-whatsapp-line"></i>
                        </div>
                        <h5 class="empty-state-title">{{ translate('No WhatsApp Business Accounts') }}</h5>
                        <p class="empty-state-text">
                            {{ translate('Connect your first WhatsApp Business Account using Embedded Signup or add credentials manually.') }}
                        </p>
                        @if($canUseEmbeddedSignup)
                            <button class="config-btn config-btn--success" type="button" id="embeddedSignupBtnEmpty">
                                <i class="ri-link"></i> {{ translate("Connect via Embedded Signup") }}
                            </button>
                        @else
                            <a href="{{ route('admin.whatsapp.configuration.create') }}" class="config-btn config-btn--primary">
                                <i class="ri-settings-3-line"></i> {{ translate('Configure Meta First') }}
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('modal')
    <div class="modal fade" id="addWhatsappBusinessAccount" tabindex="-1" aria-labelledby="addWhatsappBusinessAccount" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered ">
            <div class="modal-content">
                <form action="{{route('admin.gateway.whatsapp.cloud.api.store')}}" method="POST">
                    @csrf

                    <input type="text" hidden name="type" value="cloud">
                    <input type="text" hidden name="setup_method" value="manual">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Add WhatsApp Business Account Manually") }} </h5>
                        <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                            <i class="ri-close-large-line"></i>
                        </button>
                    </div>
                    <div class="modal-body modal-lg-custom-height">
                        <div class="row g-4">
                            <div class="col-12">
                                <label class="form-label" for="name">{{ translate('Business Portfolio Name')}} <span class="text-danger">*</span></label>
                                <input type="text" class="mt-2 form-control @error('name') is-invalid @enderror" name="name" id="name" value="{{old('name')}}" placeholder="{{ translate('Add a name for your Business Portfolio')}}" autocomplete="true" aria-label="name">
                                @error('name')
                                    <span class="text-danger">{{$message}}</span>
                                @enderror
                            </div>
                            <div class="col-lg-6">
                                <div class="form-inner">
                                    <label for="per_message_min_delay" class="form-label"> {{ translate('Per Message Minimum Delay (Seconds)')}}<span class="text-danger">*</span> </label>
                                    <input type="number" id="per_message_min_delay" name="per_message_min_delay"  placeholder="{{ translate('e.g., 0.5 seconds minimum delay per message') }}" class="form-control" aria-label="per_message_min_delay"/>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-inner">
                                    <label for="per_message_max_delay" class="form-label"> {{ translate('Per Message Maximum Delay (Seconds)')}}<span class="text-danger">*</span> </label>
                                    <input type="number" id="per_message_max_delay" name="per_message_max_delay" placeholder="{{ translate('e.g., 0.5 seconds max delay per message') }}" class="form-control" aria-label="per_message_max_delay"/>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-inner">
                                    <label for="delay_after_count" class="form-label">{{ translate('Delay After Count') }}<span class="text-danger">*</span></label>
                                    <input type="number" min="0" step="1" id="delay_after_count" name="delay_after_count" placeholder="{{ translate('e.g., pause after 50 messages') }}" class="form-control" aria-label="Delay After Count"/>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="form-inner">
                                    <label for="delay_after_duration" class="form-label">{{ translate('Delay After Duration (Seconds)') }}<span class="text-danger">*</span></label>
                                    <input type="number" min="0" step="0.1" id="delay_after_duration" name="delay_after_duration" placeholder="{{ translate('e.g., pause for 5 seconds') }}" class="form-control" aria-label="Delay After Duration"/>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-inner">
                                    <label for="reset_after_count" class="form-label">{{ translate('Reset After Count') }}<span class="text-danger">*</span></label>
                                    <input type="number" min="0" step="1" id="reset_after_count" name="reset_after_count" placeholder="{{ translate('e.g., reset after 200 messages') }}" class="form-control" aria-label="Reset After Count"/>
                                </div>
                            </div>
                            @foreach ($credentials['required'] as $creds_key => $creds_value)
                                <div class="{{ $loop->first ? 'col-12' : 'col-6' }} ">
                                    <label class="form-label" for="{{ $creds_key }}">{{translate(textFormat(['_'], $creds_key))}} <span class="text-danger">*</span></label>
                                    <input type="text" id="{{ $creds_key }}" class="mt-2 form-control" name="meta_data[{{$creds_key}}]" value="{{old($creds_key)}}" placeholder="Enter the {{translate(textFormat(['_'], $creds_key))}}"  aria-label="{{$creds_key}}" autocomplete="true">
                                </div>
                            @endforeach


                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="i-btn btn--danger outline btn--md" data-bs-dismiss="modal"> {{ translate("Close") }} </button>
                        <button type="submit" class="i-btn btn--primary btn--md"> {{ translate("Save") }} </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="updateWhatsappBusinessAccount" tabindex="-1"
        aria-labelledby="updateWhatsappBusinessAccount" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered ">
            <div class="modal-content">
                <form id="updateWhatsappCloudAPIForm" method="POST">
                    @csrf
                    <input type="hidden" name="_method" value="PATCH">
                    <input type="text" hidden name="type" value="cloud">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">
                            {{ translate('Update WhatsApp Business Account Credentials') }} </h5>
                        <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer"
                            data-bs-dismiss="modal">
                            <i class="ri-close-large-line"></i>
                        </button>
                    </div>
                    <div class="modal-body modal-lg-custom-height">
                        <div class="row g-4">
                            <div class="col-lg-12">
                                <label for="name" class="form-label">{{ translate('Business API Name') }} <sup
                                        class="text--danger">*</sup></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="name" name="name"
                                        placeholder="{{ translate('Update Business API Name') }}" autocomplete="true">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-inner">
                                    <label for="per_message_min_delay" class="form-label"> {{ translate('Per Message Minimum Delay (Seconds)')}}<span class="text-danger">*</span> </label>
                                    <input type="number" id="per_message_min_delay" name="per_message_min_delay"  placeholder="{{ translate('e.g., 0.5 seconds minimum delay per message') }}" class="form-control" aria-label="per_message_min_delay"/>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-inner">
                                    <label for="per_message_max_delay" class="form-label"> {{ translate('Per Message Maximum Delay (Seconds)')}}<span class="text-danger">*</span> </label>
                                    <input type="number" id="per_message_max_delay" name="per_message_max_delay" placeholder="{{ translate('e.g., 0.5 seconds max delay per message') }}" class="form-control" aria-label="per_message_max_delay"/>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-inner">
                                    <label for="delay_after_count" class="form-label">{{ translate('Delay After Count') }}<span class="text-danger">*</span></label>
                                    <input type="number" min="0" step="1" id="delay_after_count" name="delay_after_count" placeholder="{{ translate('e.g., pause after 50 messages') }}" class="form-control" aria-label="Delay After Count"/>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="form-inner">
                                    <label for="delay_after_duration" class="form-label">{{ translate('Delay After Duration (Seconds)') }}<span class="text-danger">*</span></label>
                                    <input type="number" min="0" step="0.1" id="delay_after_duration" name="delay_after_duration" placeholder="{{ translate('e.g., pause for 5 seconds') }}" class="form-control" aria-label="Delay After Duration"/>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-inner">
                                    <label for="reset_after_count" class="form-label">{{ translate('Reset After Count') }}<span class="text-danger">*</span></label>
                                    <input type="number" min="0" step="1" id="reset_after_count" name="reset_after_count" placeholder="{{ translate('e.g., reset after 200 messages') }}" class="form-control" aria-label="Reset After Count"/>
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <div class="row" id="edit_cred"></div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="i-btn btn--danger outline btn--md" data-bs-dismiss="modal">
                            {{ translate('Close') }} </button>
                        <button type="submit" class="i-btn btn--primary btn--md"> {{ translate('Save') }} </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade actionModal" id="deleteWhatsappCloudApi" tabindex="-1"
        aria-labelledby="deleteWhatsappCloudApi" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered ">
            <div class="modal-content">
                <div class="modal-header text-start">
                    <span class="action-icon danger">
                        <i class="bi bi-exclamation-circle"></i>
                    </span>
                </div>
                <form method="POST" id="deleteWhatsappCloudApi">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" name="_method" value="DELETE">
                        <div class="action-message">
                            <h5>{{ translate('Are you sure to delete this WhatsApp device?') }}</h5>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="i-btn btn--dark outline btn--lg" data-bs-dismiss="modal">
                            {{ translate('Cancel') }} </button>
                        <button type="submit" class="i-btn btn--danger btn--lg" data-bs-dismiss="modal">
                            {{ translate('Delete') }} </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script-include')
    @include("partials.gateway.whatsapp.cloud.embedded-login", [
        "embedded_sign_up_route"    => route('admin.gateway.whatsapp.cloud.api.initiate.embedded.signup') ,
        "fallback_url"              => route('admin.gateway.whatsapp.cloud.api.index')
    ])
@endpush
@push('script-push')
    <script>
        (function($) {
            "use strict";

            flatpickr("#datePicker", {
                dateFormat: "Y-m-d",
                mode: "range",
            });

            $(document).ready(function() {

                // Handle empty state embedded signup button
                $('#embeddedSignupBtnEmpty').on('click', function() {
                    $('#embeddedSignupBtn').trigger('click');
                });

                $('.add-whatsapp-business-account').on('click', function() {

                    const modal = $('#addAndroidGateway');
                    modal.modal('show');
                });

                $('.update-whatsapp-business-account').on('click', function() {

                    $("#edit_cred").empty();
                    var credentials = $(this).data('credentials');
                    const modal = $('#updateWhatsappBusinessAccount');
                    modal.find('form[id=updateWhatsappCloudAPIForm]').attr('action', $(this).data('url'));
                    modal.find('input[name=name]').val($(this).attr('data-name'));
                    modal.find('input[name=per_message_min_delay]').val($(this).data('per_message_min_delay'));
                    modal.find('input[name=per_message_max_delay]').val($(this).data('per_message_max_delay'));
                    modal.find('input[name=delay_after_count]').val($(this).data('delay_after_count'));
                    modal.find('input[name=delay_after_duration]').val($(this).data('delay_after_duration'));
                    modal.find('input[name=reset_after_count]').val($(this).data('reset_after_count'));
                    var html = ``;
                    var firstIteration = true;
                    $.each(credentials, function(key, value) {

                        html += `
                        <div class="${firstIteration ? `col-lg-12` : `col-lg-6`}">
                            <label class="form-label mt-3" for="${key}">${textFormat(['_'],key)}<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="meta_data[${key}]" value="${value}" placeholder="Enter the ${key}">
                        </div>
                    `;
                        firstIteration = false;
                    });
                    $("#edit_cred").append(html);
                    modal.modal('show');
                });

                $('.sync').click(function(e) {

                    var itemId = $(this).attr('data-value');
                    var csrfToken = $('meta[name="csrf-token"]').attr('content');
                    var button = $(this);
                    var originalIcon = button.find('i').detach();
                    if (button.hasClass('disabled')) {
                        return;
                    }
                    button.append(
                        '<span class="loading-spinner spinner-border spinner-border-sm" aria-hidden="true"></span> '
                    );
                    button.addClass('disabled');

                    $.ajax({
                        url: "{{ route('admin.template.refresh') }}",
                        type: 'GET',
                        data: {
                            itemId: itemId
                        },
                        dataType: 'json',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken
                        },
                        success: function(response) {

                            button.find('.loading-spinner').remove();
                            button.removeClass('disabled').prepend(originalIcon);

                            if (response.status && response.reload) {
                                location.reload(true);
                                notify('success', response.message);
                            } else {
                                notify('error', response.message);
                            }
                        },
                        error: function(xhr, status, error) {

                            button.find('.loading-spinner').remove();
                            button.removeClass('disabled').prepend(originalIcon);
                            notify('error', "Some error occurred");
                        }
                    });
                });

                $('.delete-whatsapp-cloud-api').on('click', function() {

                    var modal = $('#deleteWhatsappCloudApi');
                    modal.find('form[id=deleteWhatsappCloudApi]').attr('action', $(this).data('url'));
                    modal.modal('show');
                });
            });

        })(jQuery);
    </script>
@endpush

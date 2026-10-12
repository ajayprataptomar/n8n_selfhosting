@extends('admin.gateway.index')
@section('tab-content')

<div class="tab-pane active fade show" id="{{url()->current()}}" role="tabpanel">
    <div class="table-filter mb-4">
        <form action="{{route(Route::currentRouteName())}}" class="filter-form">
            
            <div class="row g-3">
                <div class="col-xxl-3 col-xl-4 col-lg-4">
                    <div class="filter-search">
                        <input type="search" value="{{request()->search}}" name="search" class="form-control" id="filter-search" placeholder="{{ translate("Search by Name") }}" />
                        <span><i class="ri-search-line"></i></span>
                    </div>
                </div>

                <div class="col-xxl-5 col-xl-6 col-lg-7 offset-xxl-4 offset-xl-2">
                    <div class="filter-action">

                        <div class="input-group">
                            <input type="text" class="form-control" id="datePicker" name="date" value="{{request()->input('date')}}"  placeholder="{{translate('Filter by date')}}"  aria-describedby="filterByDate">
                            <span class="input-group-text" id="filterByDate">
                                <i class="ri-calendar-2-line"></i>
                            </span>
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <button type="submit" class="filter-action-btn ">
                                <i class="ri-menu-search-line"></i> {{ translate("Filter") }}
                            </button>
                            <a class="filter-action-btn bg-danger text-white" href="{{route(Route::currentRouteName())}}">
                                <i class="ri-refresh-line"></i> {{ translate("Reset") }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- Enhanced Node Service Status Card --}}
    @if(isset($serverHealth))
    <div class="card mb-3 border-0 shadow-sm">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="d-flex align-items-center gap-3">
                        <div style="min-width: 70px" class="d-flex justify-content-center align-items-center server-icon {{ $serverHealth['healthy'] ? 'bg-success' : 'bg-danger' }} bg-opacity-10 rounded-circle p-3">
                            <i class="ri-server-line fs-4 {{ $serverHealth['healthy'] ? 'text-success' : 'text-danger' }}"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 fw-semibold">{{ translate('WhatsApp Node Service') }}</h6>
                            <small class="text-muted">{{ env('WP_SERVER_URL', 'http://127.0.0.1:3001') }}</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="d-flex align-items-center justify-content-lg-end gap-2 mt-3 mt-lg-0">
                        @if($serverHealth['healthy'])
                            <span class="badge bg-success px-3 py-2">
                                <i class="ri-checkbox-circle-line"></i> {{ translate('Online') }}
                            </span>
                        @else
                            <span class="badge bg-danger px-3 py-2">
                                <i class="ri-close-circle-line"></i> {{ translate('Offline') }}
                            </span>
                        @endif

                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#nodeHealthModal">
                            <i class="ri-heart-pulse-line"></i> {{ translate('Health') }}
                        </button>

                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#nodeLogsModal">
                            <i class="ri-file-list-3-line"></i> {{ translate('Logs') }}
                        </button>
                    </div>
                </div>
            </div>

            {{-- Quick Stats Row --}}
            @if($serverHealth['healthy'] && isset($serverHealth['data']))
            @php
                $healthData = $serverHealth['data'];
                $nodeStatus = $healthData->status ?? null;
                $totalSessions = $healthData->sessions->total ?? 0;
                $connectedSessions = $healthData->sessions->connected ?? 0;
                $disconnectedSessions = $healthData->sessions->disconnected ?? 0;
                $queueTotal = $healthData->queue->totalQueued ?? 0;

                // Determine display status — more meaningful than raw health status
                if (!$nodeStatus) {
                    $displayStatus = translate('Online');
                    $statusClass = 'bg-success';
                } elseif ($nodeStatus === 'critical') {
                    $displayStatus = translate('Critical');
                    $statusClass = 'bg-danger';
                } elseif ($nodeStatus === 'warning' || ($totalSessions > 0 && $disconnectedSessions > 0)) {
                    $displayStatus = translate('Warning');
                    $statusClass = 'bg-warning text-dark';
                } else {
                    $displayStatus = translate('Healthy');
                    $statusClass = 'bg-success';
                }
            @endphp
            <div class="row mt-3 pt-3 border-top">
                <div class="col-6 col-md-3">
                    <div class="text-center">
                        <small class="text-muted d-block">{{ translate('Status') }}</small>
                        <span class="badge {{ $statusClass }}">
                            {{ $displayStatus }}
                        </span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-center">
                        <small class="text-muted d-block">{{ translate('Sessions') }}</small>
                        <strong class="text-dark">{{ $totalSessions }}</strong>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-center">
                        <small class="text-muted d-block">{{ translate('Connected') }}</small>
                        <strong class="{{ $connectedSessions > 0 ? 'text-success' : 'text-muted' }}">{{ $connectedSessions }}</strong>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-center">
                        <small class="text-muted d-block">{{ translate('Queue') }}</small>
                        <strong class="text-primary">{{ $queueTotal }}</strong>
                    </div>
                </div>
            </div>
            @if($totalSessions === 0)
            <div class="alert alert-info mt-3 mb-0 py-2 small">
                <i class="ri-information-line"></i> {{ translate('No active sessions. Add a WhatsApp device and scan the QR code to get started.') }}
            </div>
            @elseif($disconnectedSessions > 0 && $connectedSessions === 0)
            <div class="alert alert-warning mt-3 mb-0 py-2 small">
                <i class="ri-wifi-off-line"></i> {{ translate('All sessions are disconnected. The service will automatically attempt to reconnect.') }}
            </div>
            @endif
            @elseif(!$serverHealth['healthy'] && isset($serverHealth['error']))
            <div class="alert alert-danger mt-3 mb-0 py-2">
                <i class="ri-error-warning-line"></i> {{ $serverHealth['error'] }}
            </div>
            @endif
        </div>
    </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <h4 class="card-title">{{$title}}</h4>
            </div>
            <div class="card-header-right">
                <button class="i-btn btn--info btn--sm whatsapp-server-settings" type="button" data-bs-toggle="modal" data-bs-target="#whatsappServerSetting">
                    <i class="ri-server-line"></i> {{ translate("Server Settings") }}
                </button>
                @if($serverStatus)
                    <button class="i-btn btn--primary btn--sm add-whatsapp-device" type="button" data-bs-toggle="modal" data-bs-target="#addWhatsappDevice">
                        <i class="ri-add-fill fs-16"></i> {{ translate("Add Whatsapp Device") }}
                    </button>
                @endif
            </div>
        </div>
        @if($serverStatus)
            <div class="card-body px-0 pt-0">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">{{ translate("Session Name") }}</th>
                                <th scope="col">{{ translate("WhatsApp Number") }}</th>
                                <th scope="col">{{ translate("Delay Settings") }}</th>
                                <th scope="col">{{ translate("Status") }}</th>
                                <th scope="col">{{ translate("Option") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($gateways as $item)
                                <tbody>
                                <tr>
                                    <td data-label="{{translate('Session Name')}}">{{$item->name}}</td>
                                    <td data-label="{{translate('WhatsApp Number')}}">
                                        {{ \Illuminate\Support\Arr::get($item->meta_data, "number", translate("N/A")) }}
                                    </td>
                                    <td data-label="{{translate('Delay Settings')}}" >
                                        <div class="d-flex flex-column gap-1 align-items-start ">
                                            <span>{{ translate("Per Message Minimum Delay (Seconds): ") }}{{ $item->per_message_min_delay }}</span>
                                            <span>{{ translate("Per Message Minimum Delay (Seconds): ") }}{{ $item->per_message_max_delay }}</span>
                                            <span>{{ translate("Delay After Count (Quantity): ") }}{{ $item->delay_after_count }}</span>
                                            <span>{{ translate("Delay After Duration (Seconds): ") }}{{ $item->delay_after_duration }}</span>
                                            <span>{{ translate("Reset After Count (Quantity): ") }}{{ $item->reset_after_count }}</span>
                                        </div>
                                    </td>
                                    <td data-label="{{translate('Status')}}">
                                        {{ $item->status->badge() }}

                                    </td>
                                    <td data-label={{ translate('Option')}}>
                                        <div class="d-flex align-items-center gap-1">
                                            <button class="icon-btn btn-ghost btn-sm info-soft circle update-whatsapp-device"
                                                    type="button"
                                                    data-url="{{ route('admin.gateway.whatsapp.device.update', ['id' => $item->id])}}"
                                                    data-per_message_min_delay="{{$item->per_message_min_delay}}"
                                                    data-per_message_max_delay="{{$item->per_message_max_delay}}"
                                                    data-delay_after_count="{{$item->delay_after_count}}"
                                                    data-delay_after_duration="{{$item->delay_after_duration}}"
                                                    data-reset_after_count="{{$item->reset_after_count}}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#updateWhatsappDevice">
                                                <i class="ri-edit-line"></i>
                                                <span class="tooltiptext"> {{ translate("Update whatsapp device") }} </span>
                                            </button>
                                            @if($item->status == \App\Enums\Common\Status::INACTIVE)
                                                <button class="icon-btn btn-ghost btn-sm success-soft circle qrQuote textChange{{$item->id}}"
                                                        value="{{$item->id}}"
                                                        type="button"
                                                        data-bs-toggle="offcanvas"
                                                        data-bs-target="#offcanvasQrCode"
                                                        aria-controls="offcanvasQrCode">

                                                    <i class="ri-qr-code-fill"></i>
                                                    <span class="tooltiptext"> {{ translate("Scan") }} </span>
                                                </button>

                                            @elseif($item->status == \App\Enums\Common\Status::ACTIVE)
                                                <button class="icon-btn btn-ghost btn-sm danger-soft circle deviceDisconnection{{$item->id}}"
                                                        onclick="return deviceStatusUpdate('{{$item->id}}','disconnected','deviceDisconnection','Disconnecting','Connect')"
                                                        value="{{$item->id}}"
                                                        type="button">

                                                        <i class="ri-wifi-off-fill"></i>
                                                    <span class="tooltiptext"> {{ translate("Disconnect") }} </span>
                                                </button>

                                            @else
                                            
                                                <button class="icon-btn btn-ghost btn-sm success-soft circle qrQuote textChange{{$item->id}}"
                                                    value="{{$item->id}}"
                                                    type="button"
                                                    data-bs-toggle="offcanvas"
                                                    data-bs-target="#offcanvasQrCode"
                                                    aria-controls="offcanvasQrCode">

                                                <i class="ri-qr-code-fill"></i>
                                                <span class="tooltiptext"> {{ translate("Scan") }} </span>
                                            </button>
                                            @endif

                                            <button class="icon-btn btn-ghost btn-sm info-soft circle text-info quick-view"
                                                    type="button"
                                                    data-uid="{{$item->uid}}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#quick_view">
                                                    <i class="ri-information-line"></i>
                                                <span class="tooltiptext"> {{ translate("Quick View") }} </span>
                                            </button>
                                            <button class="icon-btn btn-ghost btn-sm danger-soft circle text-danger delete-whatsapp-device"
                                                type="button"
                                                data-item-id="{{ $item->id }}"
                                                data-url="{{route('admin.gateway.whatsapp.device.destroy', ['id' => $item->id ])}}" 
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteWhatsappDevice">
                                            <i class="ri-delete-bin-line"></i>
                                            <span class="tooltiptext"> {{ translate("Delete Whatsapp device") }} </span>
                                        </button>
                                        </div>
                                    </td>
                                </tr>
                                </tbody>
                            @empty
                                <tbody>
                                <tr>
                                    <td colspan="50"><span class="text-danger">{{ translate('No data Available')}}</span></td>
                                </tr>
                                </tbody>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @include('admin.partials.pagination', ['paginator' => $gateways])
            </div>
        @else
            <div class="card">
                <div class="card-header">
                   <span>{{ translate('Node Server Offline')}} <i class="fas fa-info-circle"></i></span>

                    <div class="header-with-btn">
                        <span class="d-flex align-items-center gap-2"> 
                            <a href="" class="badge badge--primary"> <i class="fas fa-refresh"></i>  {{ translate('Try Again') }}</a>
                        </span>
                    </div>

                </div>

                <div class="card-body">
                    <h6 class="text--danger">{{ translate('Unable to connect to WhatsApp node server. Please configure the server settings and try again.') }}</h6>
                </div>
            </div>
        @endif
    </div>
</div>

@endsection

@section('modal')

<div class="modal fade" id="whatsappServerSetting" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form action="{{route('admin.gateway.whatsapp.device.server.update')}}" method="POST" id="serverSettingsForm">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold">
                        <i class="ri-server-line me-1"></i>{{ translate("Server Settings") }}
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 px-3 py-2"
                     style="background:var(--color-gray-100);border-bottom:1px solid var(--color-border-light);">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span id="healthStatus" style="font-size:12px;">
                            <span class="spinner-border spinner-border-sm" style="width:12px;height:12px;"></span>
                        </span>
                        <span id="syncStatus" style="display:none;font-size:12px;"></span>
                        <span id="healthDetails" style="display:none;font-size:12px;">
                            <small class="text-muted"><i class="ri-time-line"></i> <span id="healthUptime"></span></small>
                        </span>
                    </div>
                    <div class="d-flex gap-1">
                        <button type="button" class="i-btn btn--info btn--sm" id="checkHealthBtn" style="padding:3px 10px;font-size:11px;" title="{{ translate('Check Health') }}">
                            <i class="ri-refresh-line me-1"></i><span class="d-none d-sm-inline">{{ translate('Refresh') }}</span>
                        </button>
                        <button type="button" class="i-btn btn--dark outline btn--sm" id="reinitializeBtn" style="padding:3px 10px;font-size:11px;" title="{{ translate('Reinitialize') }}">
                            <i class="ri-restart-line me-1"></i><span class="d-none d-sm-inline">{{ translate('Sync') }}</span>
                        </button>
                    </div>
                </div>
                <div class="modal-body" style="overflow-x:hidden;">

                    {{-- Hidden: Server URL (auto-generated) --}}
                    <input type="hidden" id="server_url" name="server_url"
                           value="{{ env('WP_SERVER_URL', 'http://127.0.0.1:3001') }}">

                    {{-- Host & Port --}}
                    <div class="row g-3 mb-3">
                        <div class="col-8">
                            <label class="form-label small fw-medium">
                                {{ translate('Server Host') }} <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="server_host" name="server_host"
                                   class="form-control" placeholder="127.0.0.1"
                                   value="{{ env('WP_SERVER_HOST', '127.0.0.1') }}" required>
                            <small class="text-muted">{{ translate('Use 127.0.0.1 if Node runs on same server') }}</small>
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-medium">
                                {{ translate('Port') }} <span class="text-danger">*</span>
                            </label>
                            <input type="number" id="server_port" name="server_port"
                                   class="form-control" placeholder="3001"
                                   value="{{ env('WP_SERVER_PORT', '3001') }}" required>
                        </div>
                    </div>

                    {{-- API Key --}}
                    <div class="mb-3">
                        <label class="form-label small fw-medium">
                            {{ translate('API Key') }} <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="text" id="wp_api_key" name="wp_api_key"
                                   class="form-control font-monospace" style="font-size:13px;"
                                   placeholder="{{ translate('Click Generate') }}"
                                   value="{{ env('WP_API_KEY', '') }}" required>
                            <button type="button" class="i-btn btn--primary btn--sm" id="generateApiKeyBtn">
                                <i class="ri-refresh-line me-1"></i>{{ translate('Generate') }}
                            </button>
                        </div>
                        <small class="text-muted"><i class="ri-lock-line"></i> {{ translate('Secure key for Laravel-Node communication') }}</small>
                    </div>

                    {{-- Allowed Origins (readonly) --}}
                    <div class="mb-3">
                        <label class="form-label small fw-medium">
                            {{ translate('Allowed Origins') }} <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="wp_allowed_origins" name="wp_allowed_origins"
                               class="form-control bg-light" style="font-size:13px;"
                               value="{{ env('WP_ALLOWED_ORIGINS', env('APP_URL', request()->root())) }}" readonly>
                        <small class="text-muted"><i class="ri-global-line"></i> {{ translate('Auto-configured from APP_URL') }}</small>
                    </div>

                    {{-- After Save Help (compact) --}}
                    <details class="mb-0">
                        <summary class="small fw-medium text-warning" style="cursor:pointer;">
                            <i class="ri-information-line"></i> {{ translate('After Save Instructions') }}
                        </summary>
                        <div class="mt-2 p-2 rounded" style="background:var(--color-gray-100);font-size:12px;">
                            <p class="mb-2 text-muted">{{ translate('System auto-syncs config. If it fails:') }}</p>
                            <div class="mb-2">
                                <strong>{{ translate('VPS (PM2):') }}</strong>
                                <code class="d-block mt-1 p-1 bg-dark text-light rounded" style="font-size:11px;">pm2 restart ecosystem.config.cjs</code>
                            </div>
                            <div class="mb-2">
                                <strong>{{ translate('cPanel:') }}</strong>
                                <span class="text-muted">{{ translate('Setup Node.js App → Restart') }}</span>
                            </div>
                            <div>
                                <strong>{{ translate('Cache:') }}</strong>
                                <code class="d-block mt-1 p-1 bg-dark text-light rounded" style="font-size:11px;">php artisan config:clear && php artisan cache:clear</code>
                            </div>
                        </div>
                    </details>

                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate("Close") }}</button>
                    <button type="submit" class="i-btn btn--primary btn--md">
                        <i class="ri-save-line me-1"></i>{{ translate("Save Configuration") }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addWhatsappDevice" tabindex="-1" aria-labelledby="addWhatsappDevice" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered ">
        <div class="modal-content">
            <form action="{{route('admin.gateway.whatsapp.device.store')}}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Add WhatsApp Device") }} </h5>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                        <i class="ri-close-large-line"></i>
                    </button>
                </div>
                <div class="modal-body modal-lg-custom-height">
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="form-inner">
                                <label for="name" class="form-label">{{ translate('Session/Device Name')}}<span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" placeholder="{{ translate('Enter whatsapp session name')}}" class="form-control" aria-label="name"/>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-inner">
                                <label for="per_message_min_delay" class="form-label"> {{ translate('Per Message Minimum Delay (Seconds)')}}<span class="text-danger">*</span> </label>
                                <input type="number" id="per_message_min_delay" name="per_message_min_delay"  placeholder="{{ translate('e.g., 1 seconds minimum delay per message') }}" class="form-control" aria-label="per_message_min_delay"/>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-inner">
                                <label for="per_message_max_delay" class="form-label"> {{ translate('Per Message Maximum Delay (Seconds)')}}<span class="text-danger">*</span> </label>
                                <input type="number" id="per_message_max_delay" name="per_message_max_delay" placeholder="{{ translate('e.g., 300 seconds max delay per message') }}" class="form-control" aria-label="per_message_max_delay"/>
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

<div class="modal fade" id="updateWhatsappDevice" tabindex="-1" aria-labelledby="updateWhatsappDevice" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered ">
        <div class="modal-content">
            <form id="updateWhatsappGatewayForm" method="POST">
                @csrf
                <input type="hidden" name="_method" value="PATCH">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Update WhatsApp Device") }} </h5>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                        <i class="ri-close-large-line"></i>
                    </button>
                </div>
                <div class="modal-body modal-lg-custom-height">
                    <div class="row g-4">
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

<div class="modal fade actionModal" id="deleteWhatsappDevice" tabindex="-1" aria-labelledby="deleteWhatsappDevice" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
        <div class="modal-header text-start">
            <span class="action-icon danger">
            <i class="bi bi-exclamation-circle"></i>
            </span>
        </div>
        <form method="POST"  id="deleteWhatsappGateway">
            @csrf
            <div class="modal-body">
                <input type="hidden" name="_method" value="DELETE">
                <div class="action-message">
                    <h5>{{ translate("Are you sure to delete this WhatsApp device?") }}</h5>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--lg" data-bs-dismiss="modal"> {{ translate("Cancel") }} </button>
                <button type="submit" class="i-btn btn--danger btn--lg" data-bs-dismiss="modal"> {{ translate("Delete") }} </button>
            </div>
        </form>
        </div>
    </div>
</div>

<div class="modal fade" id="quick_view" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">{{ translate("Email Gateway Information") }}</h5>
                <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <ul class="information-list"></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--danger outline btn--md" data-bs-dismiss="modal">{{ translate("Close") }}</button>
                <button type="button" class="i-btn btn--primary btn--md">{{ translate("Save") }}</button>
            </div>
        </div>
    </div>
</div>

{{-- <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasQrCode" aria-labelledby="offcanvasQrCode" data-bs-backdrop="static">
    <div class="offcanvas-header justify-content-between bg-light">
        <h5 class="offcanvas-title" id="offcanvasExampleLabel">{{ translate("Connect Whatsapp") }}</h5>
        <button
            type="button"
            class="icon-btn btn-sm dark-soft hover circle modal-closer"
            data-bs-dismiss="offcanvas"
            onclick="return deviceStatusUpdate('','initiate','','','')">
            <i class="ri-close-large-line"></i>
        </button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="d-flex flex-column justify-content-between h-100">
        <div class="p-3">
            <input type="hidden" name="scan_id" id="scan_id" value="">
            <ul class="information-list border-0 p-0">

            <li>
                <p>{{ translate('1. Open WhatsApp on your phone')}}</p>
            </li>

            <li>
                <p>{{ translate('2. Tap Menu  or Settings  and select Linked Devices')}}</p>
            </li>

            <li>
                <p>{{ translate('3. Point your phone to this screen to capture the code')}}</p>
            </li>
            </ul>
            <div class="qr-code mt-5">
                <img id="qrcode" src="" alt="">
            </div>
        </div>

        <div class="py-xl-5 py-4 px-3 bg-light mt-5">
            <div class="text-center  h-100">
                <h6 class="mb-2">{{ translate("Tutorial") }}</h6>
                <a class="fs-14 text-info" href="{{ site_settings('help_center_url', '#') }}"><i class="ri-information-2-line fs-18"></i>{{ translate("Need help to get started?") }}</a>

                <div class="mt-4">
                <img src="https://static.whatsapp.net/rsrc.php/v3/yB/r/7Y1jh45L_8V.png" alt="whatsapp">
                </div>
            </div>
        </div>
        </div>
    </div>
</div> --}}

<!-- Post-Save Instructions Modal -->
<div class="modal fade" id="postSaveModal" tabindex="-1" aria-labelledby="postSaveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" id="postSaveModalHeader">
                <h5 class="modal-title" id="postSaveModalLabel">
                    <i class="ri-checkbox-circle-line text-success"></i> {{ translate('Configuration Saved') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="postSaveSuccess" style="display: none;">
                    <div class="alert alert-success">
                        <i class="ri-checkbox-circle-fill"></i>
                        <strong>{{ translate('Success!') }}</strong>
                        {{ translate('Configuration saved and synced with Node service automatically.') }}
                    </div>
                    <p class="text-muted small mb-0">
                        <i class="ri-information-line"></i>
                        {{ translate('No further action required. The Node service has been updated with the new configuration.') }}
                    </p>
                </div>

                <div id="postSaveWarning" style="display: none;">
                    <div class="alert alert-warning">
                        <i class="ri-alert-fill"></i>
                        <strong>{{ translate('Saved with Warning') }}</strong>
                        {{ translate('Configuration saved but auto-sync failed. Manual action required.') }}
                    </div>

                    <p class="fw-bold mb-2">{{ translate('Please follow these steps:') }}</p>

                    <div class="accordion" id="postSaveAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseVPS">
                                    <i class="ri-server-line me-2"></i> {{ translate('VPS / Dedicated Server') }}
                                </button>
                            </h2>
                            <div id="collapseVPS" class="accordion-collapse collapse show" data-bs-parent="#postSaveAccordion">
                                <div class="accordion-body">
                                    <p class="small text-muted mb-2">{{ translate('Run these commands via SSH:') }}</p>
                                    <div class="bg-dark text-light p-2 rounded position-relative mb-2">
                                        <code class="small d-block">cd /path/to/xsender-whatsapp-service</code>
                                        <code class="small d-block">pm2 restart ecosystem.config.cjs</code>
                                        <button type="button" class="btn btn-sm btn-outline-light position-absolute top-0 end-0 m-1 copy-cmd-btn" data-cmd="cd /path/to/xsender-whatsapp-service && pm2 restart ecosystem.config.cjs">
                                            <i class="ri-file-copy-line"></i>
                                        </button>
                                    </div>
                                    <p class="small text-muted mb-0">{{ translate('Then clear Laravel cache:') }}</p>
                                    <div class="bg-dark text-light p-2 rounded position-relative">
                                        <code class="small">php artisan config:clear && php artisan cache:clear</code>
                                        <button type="button" class="btn btn-sm btn-outline-light position-absolute top-0 end-0 m-1 copy-cmd-btn" data-cmd="php artisan config:clear && php artisan cache:clear">
                                            <i class="ri-file-copy-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCPanel">
                                    <i class="ri-cloud-line me-2"></i> {{ translate('cPanel Hosting') }}
                                </button>
                            </h2>
                            <div id="collapseCPanel" class="accordion-collapse collapse" data-bs-parent="#postSaveAccordion">
                                <div class="accordion-body">
                                    <ol class="small mb-0">
                                        <li class="mb-1">{{ translate('Login to your cPanel dashboard') }}</li>
                                        <li class="mb-1">{{ translate('Navigate to "Setup Node.js App"') }}</li>
                                        <li class="mb-1">{{ translate('Find your WhatsApp service application') }}</li>
                                        <li class="mb-1">{{ translate('Click the "Restart" button') }}</li>
                                        <li>{{ translate('Wait 10-15 seconds, then refresh this page') }}</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3 mb-0 py-2">
                        <i class="ri-lightbulb-line"></i>
                        <strong>{{ translate('Alternative:') }}</strong>
                        {{ translate('Close this modal and click "Reinitialize" button in the Server Settings.') }}
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ translate('Close') }}</button>
                <button type="button" class="btn btn-primary" onclick="location.reload();">
                    <i class="ri-refresh-line"></i> {{ translate('Refresh Page') }}
                </button>
            </div>
        </div>
    </div>
</div>

@php
    $channel = \App\Enums\System\ChannelTypeEnum::WHATSAPP->value;
    $title = translate('Connect WhatsApp Session');
    $settingKey = \App\Enums\SettingKey::WHATSAPP_OFF_CANVAS_GUIDE->value;

    $guide = site_settings($settingKey);
    if ($guide) $guide = json_decode($guide, true);
    $writtenGuide = \Illuminate\Support\Arr::get($guide, 'written_guide.message', config("setting.{$settingKey}.written_guide.message"));
    $externalText = \Illuminate\Support\Arr::get($guide, 'external_guide.text', config("setting.{$settingKey}.external_guide.text"));
    $externalLink = \Illuminate\Support\Arr::get($guide, 'external_guide.link', config("setting.{$settingKey}.external_guide.link"));
    $imageName = \Illuminate\Support\Arr::get($guide, 'image.name', config("setting.{$settingKey}.image.name"));

    // Construct the image path with fallback
    
    $primaryPath = config("setting.file_path.{$settingKey}.path") . '/' . $imageName;
    
    $fallbackPath = config("setting.file_path.{$settingKey}.fall_back_path") . '/' . $imageName;
    
    $imagePath = file_exists($primaryPath) ? $primaryPath : $fallbackPath;
    
    $steps = explode("\n", $writtenGuide);

    // Structure $offCanvasData to match the expected nested keys
    $offCanvasData = [
        'channel' => $channel,
        'title' => $title,
        'settingKey' => $settingKey,
        'steps' => $steps,
        'written_guide' => [
            'message' => $writtenGuide,
        ],
        'external_guide' => [
            'text' => $externalText,
            'link' => $externalLink,
        ],
        'image' => [
            'path' => $imagePath,
        ],
    ];
@endphp

@include('components.offcanvas-qrcode', ['data' => $offCanvasData])

{{-- Node Health Details Modal --}}
<div class="modal fade" id="nodeHealthModal" tabindex="-1" aria-labelledby="nodeHealthModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="nodeHealthModalLabel">
                    <i class="ri-heart-pulse-line text-primary"></i> {{ translate('Node Service Health Report') }}
                </h5>
                <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal" aria-label="Close">
                    <i class="ri-close-large-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="healthLoadingState" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">{{ translate('Loading...') }}</span>
                    </div>
                    <p class="mt-2 text-muted">{{ translate('Fetching health report...') }}</p>
                </div>

                <div id="healthErrorState" style="display: none;">
                    <div class="alert alert-danger mb-3">
                        <div class="d-flex align-items-start gap-2">
                            <i class="ri-error-warning-line fs-5 mt-1"></i>
                            <div>
                                <strong>{{ translate('Connection Error') }}</strong>
                                <p class="mb-0 small" id="healthErrorMessage"></p>
                            </div>
                        </div>
                    </div>
                    <div class="card border-info">
                        <div class="card-body">
                            <h6 class="card-title text-info mb-3">
                                <i class="ri-information-line"></i> {{ translate('Shared Hosting Note') }}
                            </h6>
                            <p class="mb-0 text-muted">{{ translate('If you are using shared hosting (cPanel), the Node service may not be accessible from this interface. Please check your Node.js app status directly from:') }}</p>
                            <ul class="list-unstyled mt-2 mb-0">
                                <li class="d-flex align-items-center gap-2">
                                    <i class="ri-arrow-right-s-line text-info"></i>
                                    <span>{{ translate('cPanel → Setup Node.js App → Your App') }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div id="healthContent" style="display: none;">
                    {{-- Overall Status --}}
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-0 border border-1">
                                <div class="card-body p-3">
                                    <div class="row align-items-center justify-content-between">
                                        <div class="col-lg-7 d-flex align-items-center gap-3">
                                            <div
                                                class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
                                                style="width:48px; height:48px;"
                                                >
                                                <i class="ri-heart-pulse-fill fs-3 text-success"></i>
                                            </div>

                                            <div>
                                                <h5 class="mb-1" id="healthOverallStatus">{{ translate('Healthy') }}</h5>
                                                <small class="text-muted" id="healthServiceVersion">v2.1.0</small>
                                            </div>
                                        </div>
                                        <div class="text-end col-lg-5">
                                            <small class="text-muted d-block">{{ translate('Uptime') }}</small>
                                            <strong id="healthUptime">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- System Metrics --}}
                    <div class="mb-4">
                        <h6 class="mb-2"> {{ translate('System Metrics') }}</h6>
                        <div class="row g-3">
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 text-center">
                                    <small class="mb-1 text-muted d-block">{{ translate('Memory Heap') }}</small>
                                    <h6 id="sysMemoryHeap" class="fs-6">-</h6>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 text-center">
                                    <small class="mb-1 text-muted d-block">{{ translate('Memory RSS') }}</small>
                                    <h6 id="sysMemoryRss" class="fs-6">-</h6>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 text-center">
                                    <small class="mb-1 text-muted d-block">{{ translate('CPU Load (1m)') }}</small>
                                    <h6 id="sysCpuUsage" class="fs-6">-</h6>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 text-center">
                                    <small class="mb-1 text-muted d-block">{{ translate('Memory %') }}</small>
                                    <h6 id="sysMemoryPercent" class="fs-6">-</h6>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Sessions Info --}}
                    <div class="mb-4">
                        <h6 class="mb-2"> {{ translate('Sessions') }}</h6>
                        <div class="row g-3">
                            <div class="col-4">
                                <div class="border rounded p-2 text-center">
                                    <small class="text-muted d-block">{{ translate('Total') }}</small>
                                    <strong id="sessionsTotal" class="fs-4 text-primary">0</strong>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-2 text-center">
                                    <small class="text-muted d-block">{{ translate('Connected') }}</small>
                                    <strong id="sessionsConnected" class="fs-4 text-success">0</strong>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-2 text-center">
                                    <small class="text-muted d-block">{{ translate('Disconnected') }}</small>
                                    <strong id="sessionsDisconnected" class="fs-4 text-warning">0</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Queue Info --}}
                    <div class="mb-4">
                        <h6 class="mb-2"> {{ translate('Message Queue') }}</h6>
                        <div class="row g-3">
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 text-center">
                                    <small class="text-muted d-block">{{ translate('Queued') }}</small>
                                    <strong id="queueTotal" class="fs-5 text-info">0</strong>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 text-center">
                                    <small class="text-muted d-block">{{ translate('Processing') }}</small>
                                    <strong id="queueProcessing" class="fs-5 text-warning">0</strong>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 text-center">
                                    <small class="text-muted d-block">{{ translate('Completed') }}</small>
                                    <strong id="queueCompleted" class="fs-5 text-success">0</strong>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 text-center">
                                    <small class="text-muted d-block">{{ translate('Failed') }}</small>
                                    <strong id="queueFailed" class="fs-5 text-danger">0</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- API Metrics --}}
                    <div class="mb-3">
                        <h6 class="mb-2">{{ translate('API Metrics') }}</h6>
                        <div class="row g-3">
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 text-center">
                                    <small class="text-muted d-block">{{ translate('Total Requests') }}</small>
                                    <h6 id="apiTotalRequests" class="fs-6">0</h6>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 text-center">
                                    <small class="text-muted d-block">{{ translate('Success Rate') }}</small>
                                    <h6 id="apiSuccessRate" class="fs-6 text-success">0%</h6>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 text-center">
                                    <small class="text-muted d-block">{{ translate('Avg Response') }}</small>
                                    <h6 id="apiAvgResponse" class="fs-6">0ms</h6>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 text-center">
                                    <small class="text-muted d-block">{{ translate('Errors') }}</small>
                                    <h6 id="apiErrors" class="fs-6 text-danger">0</h6>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Last Updated --}}
                    <div class="text-center text-muted small pt-3 border-top">
                        <i class="ri-time-line"></i> {{ translate('Last updated:') }} <span id="healthLastUpdated">-</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate('Close') }}</button>
                <button type="button" class="i-btn btn--primary btn--md" id="refreshHealthBtn">
                    <i class="ri-refresh-line"></i> {{ translate('Refresh') }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Node Logs Modal --}}
<div class="modal fade" id="nodeLogsModal" tabindex="-1" aria-labelledby="nodeLogsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="nodeLogsModalLabel">
                    <i class="ri-terminal-line text-info"></i> {{ translate('Node Service Logs') }}
                </h5>
                <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal" aria-label="Close">
                    <i class="ri-close-large-line"></i>
                </button>
            </div>
            <div class="modal-body">
                {{-- Loading State --}}
                <div id="logsLoadingState" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">{{ translate('Loading...') }}</span>
                    </div>
                    <p class="mt-2 text-muted">{{ translate('Fetching logs...') }}</p>
                </div>

                {{-- Error State --}}
                <div id="logsErrorState" style="display: none;">
                    <div class="alert alert-warning mb-3">
                        <div class="d-flex align-items-start gap-2">
                            <i class="ri-alert-line fs-5 mt-1"></i>
                            <div>
                                <strong>{{ translate('Logs Not Available') }}</strong>
                                <p class="mb-0 small" id="logsErrorMessage">{{ translate('Unable to retrieve logs from the Node service.') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="card border-info">
                        <div class="card-body">
                            <h6 class="card-title text-info mb-3">
                                <i class="ri-information-line"></i> {{ translate('Alternative Ways to View Logs') }}
                            </h6>
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2 d-flex align-items-start gap-2">
                                    <i class="ri-cloud-line text-muted mt-1"></i>
                                    <span>{{ translate('cPanel: Setup Node.js App → Your App → Logs') }}</span>
                                </li>
                                <li class="mb-2 d-flex align-items-start gap-2">
                                    <i class="ri-terminal-box-line text-muted mt-1"></i>
                                    <span>{{ translate('SSH Command:') }} <code class="bg-light px-2 py-1 rounded">pm2 logs xsender-whatsapp</code></span>
                                </li>
                                <li class="d-flex align-items-start gap-2">
                                    <i class="ri-folder-line text-muted mt-1"></i>
                                    <span>{{ translate('Log Files:') }} <code class="bg-light px-2 py-1 rounded">~/.pm2/logs/</code></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- Logs Content --}}
                <div id="logsContent" style="display: none;">
                    {{-- Filter Bar --}}
                    <div class="card mb-3">
                        <div class="card-body py-2">
                            <div class="row align-items-center g-3">
                                <div class="col-auto">
                                    <div class="d-flex align-items-center gap-2">
                                        <label class="form-label mb-0 text-muted small fw-medium">{{ translate('Level') }}</label>
                                        <select id="logLevelFilter" class="form-select form-select-sm" style="width: auto;">
                                            <option value="all">{{ translate('All') }}</option>
                                            <option value="error">{{ translate('Error') }}</option>
                                            <option value="warn">{{ translate('Warning') }}</option>
                                            <option value="info" selected>{{ translate('Info') }}</option>
                                            <option value="debug">{{ translate('Debug') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <div class="d-flex align-items-center gap-2">
                                        <label class="form-label mb-0 text-muted small fw-medium">{{ translate('Lines') }}</label>
                                        <select id="logLinesFilter" class="form-select form-select-sm" style="width: auto;">
                                            <option value="50">50</option>
                                            <option value="100" selected>100</option>
                                            <option value="200">200</option>
                                            <option value="500">500</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-auto ms-auto">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="autoScrollLogs" checked>
                                        <label class="form-check-label small text-muted" for="autoScrollLogs">{{ translate('Auto-scroll') }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Log Output --}}
                    <div class="card mb-0">
                        <div class="card-header bg-dark py-2 d-flex align-items-center justify-content-between">
                            <span class="text-white small">
                                <i class="ri-terminal-line"></i> {{ translate('Console Output') }}
                            </span>
                            <span class="badge bg-secondary" id="logsCountBadge">0 {{ translate('entries') }}</span>
                        </div>
                        <div id="logsOutput" class="bg-dark text-light p-3" style="height: 350px; overflow-y: auto; font-size: 12px; line-height: 1.8; font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;">
                            <div id="logsPreview"></div>
                        </div>
                        <div class="card-footer bg-light py-2 d-flex align-items-center justify-content-between">
                            <small class="text-muted">
                                <i class="ri-file-list-3-line"></i> <span id="logsCount">0</span> {{ translate('entries loaded') }}
                            </small>
                            <small class="text-muted">
                                <i class="ri-time-line"></i> {{ translate('Updated:') }} <span id="logsLastFetched">-</span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">
                    {{ translate('Close') }}
                </button>
                <button type="button" class="i-btn btn--danger outline btn--md" id="clearLogsBtn">
                    <i class="ri-delete-bin-line"></i> {{ translate('Clear') }}
                </button>
                <button type="button" class="i-btn btn--primary btn--md" id="refreshLogsBtn">
                    <i class="ri-refresh-line"></i> {{ translate('Refresh') }}
                </button>
            </div>
        </div>
    </div>
</div>

@endsection


@push('script-push')
<script>
	(function($){
		"use strict";

        flatpickr("#datePicker", {
            dateFormat: "Y-m-d",
            mode: "range",
        });

        // =====================================================
        // API KEY GENERATION
        // =====================================================
        $(document).on('click', '#generateApiKeyBtn', function(e) {
            e.preventDefault();

            $.ajax({
                headers: {'X-CSRF-TOKEN': "{{csrf_token()}}"},
                url: "{{route('admin.gateway.whatsapp.device.server.generate.api.key')}}",
                method: 'post',
                beforeSend: function() {
                    $('#generateApiKeyBtn').prop('disabled', true)
                        .html('<i class="ri-loader-2-line"></i> {{ translate("Generating...") }}');
                },
                success: function(res) {
                    if (res.success) {
                        $('#wp_api_key').val(res.api_key);
                        notify('success', res.message || '{{ translate("API key generated successfully") }}');
                    } else {
                        notify('error', res.message || '{{ translate("Failed to generate API key") }}');
                    }
                },
                error: function(xhr, status, error) {
                    notify('error', '{{ translate("Something went wrong") }}');
                    console.error('API Key Generation Error:', error);
                },
                complete: function() {
                    $('#generateApiKeyBtn').prop('disabled', false)
                        .html('<i class="ri-refresh-line"></i> {{ translate("Generate") }}');
                }
            });
        });

        // =====================================================
        // HEALTH CHECK
        // =====================================================
        function checkNodeHealth() {
            $.ajax({
                headers: {'X-CSRF-TOKEN': "{{csrf_token()}}"},
                url: "{{route('admin.gateway.whatsapp.device.server.health')}}",
                method: 'get',
                beforeSend: function() {
                    $('#healthStatus').html('<span class="spinner-border spinner-border-sm"></span> {{ translate("Checking...") }}');
                    $('#healthDetails').hide();
                    $('#syncStatus').hide();
                },
                success: function(res) {
                    if (res.success && res.health.healthy) {
                        $('#healthStatus').html('<span class="badge bg-success"><i class="ri-checkbox-circle-line"></i> {{ translate("Online") }}</span>');

                        // Show uptime if available
                        if (res.health.data && res.health.data.uptime) {
                            var uptime = parseFloat(res.health.data.uptime);
                            var uptimeText = '';

                            if (uptime < 60) {
                                uptimeText = Math.round(uptime) + ' {{ translate("seconds") }}';
                            } else if (uptime < 3600) {
                                uptimeText = Math.round(uptime / 60) + ' {{ translate("minutes") }}';
                            } else if (uptime < 86400) {
                                uptimeText = Math.round(uptime / 3600) + ' {{ translate("hours") }}';
                            } else {
                                uptimeText = Math.round(uptime / 86400) + ' {{ translate("days") }}';
                            }

                            $('#healthUptime').text('{{ translate("Uptime:") }} ' + uptimeText);
                            $('#healthDetails').show();
                        }

                        // Show sync status
                        if (res.health.configSynced !== undefined) {
                            if (res.health.configSynced) {
                                $('#syncStatus').html('<span class="badge bg-success"><i class="ri-checkbox-circle-line"></i> {{ translate("Config Synced") }}</span>').show();
                            } else {
                                $('#syncStatus').html('<span class="badge bg-warning text-dark"><i class="ri-alert-line"></i> ' + (res.health.syncMessage || '{{ translate("Not Synced") }}') + '</span>').show();
                            }
                        }
                    } else {
                        $('#healthStatus').html('<span class="badge bg-danger"><i class="ri-close-circle-line"></i> {{ translate("Offline") }}</span>');
                        if (res.health && res.health.error) {
                            $('#healthUptime').text('{{ translate("Error:") }} ' + res.health.error);
                            $('#healthDetails').show();
                        }
                    }
                },
                error: function(xhr, status, error) {
                    $('#healthStatus').html('<span class="badge bg-danger"><i class="ri-close-circle-line"></i> {{ translate("Offline") }}</span>');
                    $('#healthUptime').text('{{ translate("Cannot connect to Node service") }}');
                    $('#healthDetails').show();
                }
            });
        }

        // Check health when modal opens
        $('.whatsapp-server-settings').on('click', function() {
            setTimeout(checkNodeHealth, 300);
        });

        // Manual health check button
        $(document).on('click', '#checkHealthBtn', function(e) {
            e.preventDefault();
            checkNodeHealth();
        });

        // Reinitialize Node service button
        $(document).on('click', '#reinitializeBtn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var originalHtml = $btn.html();

            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

            $.ajax({
                url: "{{ route('admin.gateway.whatsapp.device.server.reinitialize') }}",
                method: 'POST',
                headers: {'X-CSRF-TOKEN': "{{ csrf_token() }}"},
                success: function(response) {
                    if (response.success) {
                        notify('success', response.message);
                        // Refresh health status after reinitialize
                        setTimeout(checkNodeHealth, 1000);
                    } else {
                        notify('error', response.message || '{{ translate("Failed to reinitialize") }}');
                    }
                },
                error: function(xhr) {
                    var msg = xhr.responseJSON?.message || '{{ translate("Failed to reinitialize Node service") }}';
                    notify('error', msg);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });

        // Update server URL when host/port changes
        $('#server_host, #server_port').on('input', function() {
            var host = $('#server_host').val() || '127.0.0.1';
            var port = $('#server_port').val() || '3001';
            $('#server_url').val('http://' + host + ':' + port);
        });

        // =====================================================
        // COPY TO CLIPBOARD
        // =====================================================
        // Copy to clipboard (works on HTTP and HTTPS)
        function copyText(text) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(text);
            }
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;left:-9999px;top:-9999px';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            try { document.execCommand('copy'); } catch(e) {}
            document.body.removeChild(ta);
            return Promise.resolve();
        }

        $(document).on('click', '.copy-cmd-btn', function(e) {
            e.preventDefault();
            var cmd = $(this).data('cmd');
            var $btn = $(this);
            var originalHtml = $btn.html();

            copyText(cmd).then(function() {
                $btn.html('<i class="ri-check-line"></i>');
                setTimeout(function() {
                    $btn.html(originalHtml);
                }, 1500);
                notify('success', '{{ translate("Command copied to clipboard") }}');
            });
        });

        // =====================================================
        // COLLAPSIBLE INSTRUCTIONS TOGGLE ICON
        // =====================================================
        $('#postSaveInstructions').on('show.bs.collapse', function() {
            $('#instructionsToggleIcon').removeClass('ri-arrow-down-s-line').addClass('ri-arrow-up-s-line');
        }).on('hide.bs.collapse', function() {
            $('#instructionsToggleIcon').removeClass('ri-arrow-up-s-line').addClass('ri-arrow-down-s-line');
        });

        // =====================================================
        // SERVER SETTINGS FORM SUBMISSION (AJAX)
        // =====================================================
        $('#serverSettingsForm').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var originalBtnHtml = $submitBtn.html();

            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: $form.serialize(),
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                beforeSend: function() {
                    $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> {{ translate("Saving...") }}');
                },
                success: function(response) {
                    // Close the settings modal
                    $('#whatsappServerSetting').modal('hide');

                    // Show post-save modal based on sync result
                    setTimeout(function() {
                        if (response.success && response.synced && !response.restartRequired) {
                            // Fully successful - saved and synced, no restart needed
                            $('#postSaveSuccess').show();
                            $('#postSaveWarning').hide();
                            $('#postSaveModalLabel').html('<i class="ri-checkbox-circle-line text-success"></i> {{ translate("Configuration Saved") }}');
                            notify('success', response.message || '{{ translate("Configuration saved and synced successfully") }}');
                        } else if (response.success && response.synced && response.restartRequired) {
                            // Synced but HOST/PORT changed - restart required
                            $('#postSaveSuccess').hide();
                            $('#postSaveWarning').show();
                            $('#postSaveModalLabel').html('<i class="ri-restart-line text-info"></i> {{ translate("Restart Required") }}');
                            // Update warning message for restart scenario
                            $('#postSaveWarning .alert-warning').html(
                                '<i class="ri-information-fill"></i> ' +
                                '<strong>{{ translate("Configuration Synced") }}</strong> ' +
                                '{{ translate("Server HOST/PORT changed. Node service restart required for these changes to take effect.") }}'
                            );
                            notify('info', response.message || '{{ translate("Restart required for HOST/PORT changes") }}');
                        } else if (response.success && !response.synced) {
                            // Saved but sync failed - show instructions
                            $('#postSaveSuccess').hide();
                            $('#postSaveWarning').show();
                            $('#postSaveModalLabel').html('<i class="ri-alert-line text-warning"></i> {{ translate("Action Required") }}');
                            // Reset warning message
                            $('#postSaveWarning .alert-warning').html(
                                '<i class="ri-alert-fill"></i> ' +
                                '<strong>{{ translate("Saved with Warning") }}</strong> ' +
                                '{{ translate("Configuration saved but auto-sync failed. Manual action required.") }}'
                            );
                            notify('warning', response.message || '{{ translate("Configuration saved but sync failed") }}');
                        } else {
                            // Error
                            notify('error', response.message || '{{ translate("Failed to save configuration") }}');
                            return;
                        }
                        $('#postSaveModal').modal('show');
                    }, 300);
                },
                error: function(xhr) {
                    var errorMsg = xhr.responseJSON?.message || '{{ translate("Failed to save configuration") }}';
                    notify('error', errorMsg);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).html(originalBtnHtml);
                }
            });
        });

        $(document).on('click', '.qrQuote', function(e) {

            e.preventDefault()
            var id = $(this).attr('value')
            var url = "{{route('admin.gateway.whatsapp.device.server.qrcode')}}"
            $.ajax({
                headers: {'X-CSRF-TOKEN': "{{csrf_token()}}"},
                url:url,
                data: {id:id},
                dataType: 'json',
                method: 'post',
                beforeSend: function() {

                    $('.textChange'+id).html(`<i class="ri-loader-2-line"></i>
                                                    <span class="tooltiptext"> {{ translate("Loading") }} </span>`);
                },
                success: function(res) {

                    if (res.response && res.response.id) {
                        $("#scan_id").val(res.response.id);
                    }

                    // Session reconnected via saved credentials (no QR needed)
                    if (res.data.status === 301) {
                        notify('success', res.data.message || '{{ translate("Session reconnected using saved credentials") }}');
                        sleep(2500).then(() => {
                            location.reload();
                        });
                        return;
                    }

                    if (res.data.message && res.data.qr && res.data.status===200) {

                        $('#qrcode').attr('src', res.data.qr);
                        notify('success', res.data.message);

                        if (res.response && res.response.id) {
                            sleep(10000).then(() => {

                                wapSession(res.response.id);
                            });
                        }
                    } else if (res.data.message) {

                        notify('error', res.data.message);
                    }
                },
                complete: function(){
                    $('.textChange'+id).html(`<i class="ri-qr-code-fill"></i>
                                                    <span class="tooltiptext"> {{ translate("Scan") }} </span>`);
                },
                error: function(e) {
                    notify('error','Something went wrong')
                }
            });
        });

        function wapSession(id) {

            $.ajax({

                headers: {'X-CSRF-TOKEN': "{{csrf_token()}}"},
                url:"{{route('admin.gateway.whatsapp.device.server.status')}}",
                data: {id:id},
                dataType: 'json',
                method: 'post',
                success: function(res) {

                    if (res.response && res.response.id) {
                        $("#scan_id").val(res.response.id);
                    }

                    // Show message if available
                    if (res.data.message) {
                        console.log(res.data.message);
                    }

                    // Update QR code if provided
                    if (res.data.qr && res.data.qr !== '') {
                        $('#qrcode').attr('src', res.data.qr);
                    }

                    if (res.data.status === 301) {
                        // Successfully connected
                        notify('success', res.data.message || '{{ translate("Successfully connected WhatsApp device") }}');

                        sleep(2500).then(() => {
                            $('.qrQuote').offcanvas('hide');
                            location.reload();
                        });
                    } else if (res.data.status === 200) {
                        // Still connecting - show message and continue polling
                        if (res.data.message) {
                            console.log('Checking status...', res.data.message);
                        }

                        if (res.response && res.response.id) {
                            sleep(10000).then(() => {
                                wapSession(res.response.id);
                            });
                        }
                    } else {
                        // Unknown status - continue polling
                        if (res.response && res.response.id) {
                            sleep(10000).then(() => {
                                wapSession(res.response.id);
                            });
                        }
                    }
                },
                error: function(e) {
                    console.error('Error checking session status:', e);
                    // Retry after delay
                    sleep(10000).then(() => {
                        wapSession(id);
                    });
                }
            })
        }



        // Reconnect a disconnected session using saved credentials
        function deviceReconnect(deviceId) {
            var $btn = $('.deviceReconnect' + deviceId);
            var originalHtml = $btn.html();

            $.ajax({
                headers: {'X-CSRF-TOKEN': "{{csrf_token()}}"},
                url: "{{route('admin.gateway.whatsapp.device.server.reconnect')}}",
                data: { id: deviceId },
                dataType: 'json',
                method: 'post',
                beforeSend: function() {
                    $btn.prop('disabled', true).html('<i class="ri-loader-2-line ri-spin"></i><span class="tooltiptext">{{ translate("Reconnecting") }}</span>');
                },
                success: function(res) {
                    if (res.success) {
                        notify('success', res.message || '{{ translate("Reconnection initiated, waiting for connection...") }}');
                        // Poll session status to confirm reconnection
                        if (res.gateway && res.gateway.id) {
                            pollReconnectStatus(res.gateway.id, deviceId);
                        } else {
                            sleep(5000).then(() => { location.reload(); });
                        }
                    } else {
                        notify('error', res.message || '{{ translate("Reconnection failed. Please scan QR code.") }}');
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function(xhr) {
                    var msg = xhr.responseJSON?.message || '{{ translate("Reconnection failed. Credentials may have expired. Please scan QR code.") }}';
                    notify('error', msg);
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        }

        // Poll session status after reconnect attempt
        function pollReconnectStatus(gatewayId, deviceId, attempt) {
            attempt = attempt || 0;
            if (attempt > 12) { // Max 60 seconds (12 * 5s)
                notify('warning', '{{ translate("Reconnection is taking longer than expected. The page will reload.") }}');
                location.reload();
                return;
            }

            sleep(5000).then(() => {
                $.ajax({
                    headers: {'X-CSRF-TOKEN': "{{csrf_token()}}"},
                    url: "{{route('admin.gateway.whatsapp.device.server.status')}}",
                    data: { id: gatewayId },
                    dataType: 'json',
                    method: 'post',
                    success: function(res) {
                        if (res.data && res.data.status === 301) {
                            notify('success', '{{ translate("Session reconnected successfully!") }}');
                            sleep(2000).then(() => { location.reload(); });
                        } else {
                            pollReconnectStatus(gatewayId, deviceId, attempt + 1);
                        }
                    },
                    error: function() {
                        pollReconnectStatus(gatewayId, deviceId, attempt + 1);
                    }
                });
            });
        }

        // Track disconnected devices
        var disconnectedDevices = [];

        // Auto-check all device sessions on page load
        function checkAllDeviceSessions() {
            disconnectedDevices = [];
            @foreach ($gateways as $device)
                @if($device->status == \App\Enums\Common\Status::ACTIVE)
                    checkDeviceSession({{ $device->id }}, '{{ $device->name }}');
                @endif
            @endforeach

            // Show summary notification after checking all devices
            setTimeout(function() {
                if (disconnectedDevices.length > 0) {
                    var message = '{{ translate("Session disconnected for:") }} ' + disconnectedDevices.join(', ');
                    message += '. {{ translate("Please scan QR code to reconnect.") }}';
                    notify('warning', message);

                    // Show persistent notification banner
                    showDisconnectedBanner(disconnectedDevices);
                }
            }, 3000);
        }

        // Show disconnected devices banner
        function showDisconnectedBanner(devices) {
            var existingBanner = $('#disconnected-devices-banner');
            if (existingBanner.length) {
                existingBanner.remove();
            }

            var banner = $(`
                <div id="disconnected-devices-banner" class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="ri-wifi-off-line fs-4 me-2"></i>
                        <div>
                            <strong>{{ translate("Session Disconnected") }}</strong>
                            <p class="mb-0 small">{{ translate("The following devices have disconnected sessions:") }} <strong>${devices.join(', ')}</strong></p>
                            <p class="mb-0 small text-muted">{{ translate("Click Reconnect to restore session, or Scan QR if credentials expired.") }}</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `);

            // Insert banner after table filter
            $('.table-filter').after(banner);
        }

        // Check individual device session status
        function checkDeviceSession(deviceId, sessionName) {
            $.ajax({
                headers: {'X-CSRF-TOKEN': "{{csrf_token()}}"},
                url: "{{route('admin.gateway.whatsapp.device.server.status')}}",
                data: { id: deviceId },
                dataType: 'json',
                method: 'post',
                success: function(res) {
                    // If device is marked active in DB but session is not connected
                    if (res.data.status !== 301) {
                        console.log('Device "' + sessionName + '" is marked active but session is not connected');
                        disconnectedDevices.push(sessionName);

                        // Update the status badge in the table to show warning
                        updateDeviceStatusBadge(deviceId, 'disconnected');
                    }
                },
                error: function(e) {
                    console.log('Failed to check session status for device: ' + sessionName);
                    disconnectedDevices.push(sessionName);
                    updateDeviceStatusBadge(deviceId, 'error');
                }
            });
        }

        // Update device status badge in the table
        function updateDeviceStatusBadge(deviceId, status) {
            // Find the row with this device
            var $row = $('button.qrQuote[value="' + deviceId + '"], button.deviceDisconnection' + deviceId).closest('tr');

            if ($row.length) {
                var $statusCell = $row.find('td[data-label="{{ translate("Status") }}"]');
                if ($statusCell.length) {
                    var badge = '';
                    if (status === 'disconnected') {
                        badge = '<span class="badge bg-warning text-dark"><i class="ri-wifi-off-line"></i> {{ translate("Session Lost") }}</span>';
                    } else if (status === 'error') {
                        badge = '<span class="badge bg-danger"><i class="ri-error-warning-line"></i> {{ translate("Error") }}</span>';
                    }
                    $statusCell.html(badge);

                    // Replace disconnect button with Reconnect + Scan buttons
                    var $actionCell = $row.find('td[data-label="{{ translate("Option") }}"]');
                    if ($actionCell.length) {
                        var $disconnectBtn = $actionCell.find('.deviceDisconnection' + deviceId);
                        if ($disconnectBtn.length) {
                            // Create reconnect button
                            var $reconnectBtn = $('<button>')
                                .addClass('icon-btn btn-ghost btn-sm warning-soft circle deviceReconnect' + deviceId)
                                .attr('type', 'button')
                                .attr('value', deviceId)
                                .attr('onclick', 'deviceReconnect(' + deviceId + ')')
                                .html('<i class="ri-refresh-line"></i><span class="tooltiptext">{{ translate("Reconnect") }}</span>');

                            // Convert disconnect button to scan button
                            $disconnectBtn.removeClass('danger-soft deviceDisconnection' + deviceId)
                                          .addClass('success-soft qrQuote textChange' + deviceId)
                                          .attr('onclick', '')
                                          .attr('data-bs-toggle', 'offcanvas')
                                          .attr('data-bs-target', '#offcanvasQrCode')
                                          .attr('aria-controls', 'offcanvasQrCode')
                                          .html('<i class="ri-qr-code-fill"></i><span class="tooltiptext">{{ translate("Scan") }}</span>');

                            // Insert reconnect button before scan button
                            $disconnectBtn.before($reconnectBtn);
                        }
                    }
                }
            }
        }

        // =====================================================
        // NODE HEALTH MODAL
        // =====================================================
        function formatUptime(seconds) {
            if (!seconds || isNaN(seconds)) return '-';
            seconds = parseFloat(seconds);

            var days = Math.floor(seconds / 86400);
            var hours = Math.floor((seconds % 86400) / 3600);
            var minutes = Math.floor((seconds % 3600) / 60);
            var secs = Math.floor(seconds % 60);

            var parts = [];
            if (days > 0) parts.push(days + 'd');
            if (hours > 0) parts.push(hours + 'h');
            if (minutes > 0) parts.push(minutes + 'm');
            if (secs > 0 || parts.length === 0) parts.push(secs + 's');

            return parts.join(' ');
        }

        function formatBytes(bytes) {
            if (!bytes || isNaN(bytes)) return '-';
            var mb = bytes / (1024 * 1024);
            return mb.toFixed(1) + ' MB';
        }

        function loadHealthReport() {
            $('#healthLoadingState').show();
            $('#healthErrorState').hide();
            $('#healthContent').hide();

            $.ajax({
                headers: {'X-CSRF-TOKEN': "{{csrf_token()}}"},
                url: "{{route('admin.gateway.whatsapp.device.server.health.report')}}",
                method: 'GET',
                dataType: 'json',
                timeout: 15000,
                success: function(res) {
                    $('#healthLoadingState').hide();

                    if (res.success && res.health && res.health.healthy) {
                        var data = res.health.data;

                        // Overall status
                        var status = data.status || 'healthy';
                        var statusClass = status === 'healthy' ? 'success' : (status === 'warning' ? 'warning' : 'danger');
                        $('#healthOverallIcon').removeClass('bg-success bg-warning bg-danger').addClass('bg-' + statusClass + ' bg-opacity-10');
                        $('#healthOverallIcon i').removeClass('text-success text-warning text-danger').addClass('text-' + statusClass);
                        $('#healthOverallStatus').text(status.charAt(0).toUpperCase() + status.slice(1));
                        $('#healthServiceVersion').text('v' + (data.version || '2.1.0'));

                        // Get uptime from system.uptime.process
                        var uptimeSeconds = data.system?.uptime?.process || 0;
                        $('#healthUptime').text(formatUptime(uptimeSeconds));

                        // System metrics - Node returns formatted strings like "31.85 MB"
                        if (data.system) {
                            var mem = data.system.memory?.process || {};
                            $('#sysMemoryHeap').text(mem.heapUsed || '-');
                            $('#sysMemoryRss').text(mem.rss || '-');

                            // CPU load average (1 min)
                            var cpuLoad = data.system.cpu?.loadAverage?.['1min'] || '0';
                            $('#sysCpuUsage').text(cpuLoad);

                            // Memory usage percent
                            var memPercent = data.system.memory?.usagePercent || 0;
                            $('#sysMemoryPercent').text(memPercent.toFixed(1) + '%');
                        }

                        // Sessions
                        if (data.sessions) {
                            $('#sessionsTotal').text(data.sessions.total || 0);
                            $('#sessionsConnected').text(data.sessions.connected || 0);
                            $('#sessionsDisconnected').text(data.sessions.disconnected || 0);
                        }

                        // Queue
                        if (data.queue) {
                            $('#queueTotal').text(data.queue.totalQueued || 0);
                            $('#queueProcessing').text(data.queue.totalPending || 0);
                            $('#queueCompleted').text(data.queue.stats?.totalProcessed || 0);
                            $('#queueFailed').text(data.queue.stats?.totalFailed || 0);
                        }

                        // API metrics
                        if (data.api) {
                            $('#apiTotalRequests').text(data.api.requests?.total || 0);
                            var errorRate = data.api.requests?.errorRate || 0;
                            var successRate = (100 - errorRate).toFixed(1);
                            $('#apiSuccessRate').text(successRate + '%');
                            $('#apiAvgResponse').text((data.api.responseTime?.average || 0).toFixed(0) + 'ms');
                            $('#apiErrors').text(data.api.requests?.errors || 0);
                        }

                        $('#healthLastUpdated').text(new Date().toLocaleTimeString());
                        $('#healthContent').show();
                    } else {
                        $('#healthErrorMessage').text(res.health?.error || res.message || '{{ translate("Unable to connect to Node service") }}');
                        $('#healthErrorState').show();
                    }
                },
                error: function(xhr, status, error) {
                    $('#healthLoadingState').hide();
                    var errorMsg = '{{ translate("Connection failed") }}';
                    if (status === 'timeout') {
                        errorMsg = '{{ translate("Request timed out. The Node service may be slow or unreachable.") }}';
                    } else if (xhr.responseJSON?.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    $('#healthErrorMessage').text(errorMsg);
                    $('#healthErrorState').show();
                }
            });
        }

        // Load health when modal opens
        $('#nodeHealthModal').on('show.bs.modal', function() {
            loadHealthReport();
        });

        // Refresh health button
        $(document).on('click', '#refreshHealthBtn', function(e) {
            e.preventDefault();
            loadHealthReport();
        });

        // =====================================================
        // NODE LOGS MODAL
        // =====================================================
        var logsData = [];

        function loadNodeLogs() {
            $('#logsLoadingState').show();
            $('#logsErrorState').hide();
            $('#logsContent').hide();

            var level = $('#logLevelFilter').val();
            var lines = $('#logLinesFilter').val();

            $.ajax({
                headers: {'X-CSRF-TOKEN': "{{csrf_token()}}"},
                url: "{{route('admin.gateway.whatsapp.device.server.logs')}}",
                method: 'GET',
                data: { level: level, lines: lines },
                dataType: 'json',
                timeout: 15000,
                success: function(res) {
                    $('#logsLoadingState').hide();

                    if (res.success && res.logs) {
                        logsData = res.logs;
                        renderLogs();
                        $('#logsCount').text(logsData.length);
                        $('#logsLastFetched').text(new Date().toLocaleTimeString());
                        $('#logsContent').show();

                        // Auto-scroll to bottom
                        if ($('#autoScrollLogs').is(':checked')) {
                            var logsOutput = document.getElementById('logsOutput');
                            logsOutput.scrollTop = logsOutput.scrollHeight;
                        }
                    } else {
                        $('#logsErrorMessage').text(res.message || '{{ translate("Unable to retrieve logs") }}');
                        $('#logsErrorState').show();
                    }
                },
                error: function(xhr, status, error) {
                    $('#logsLoadingState').hide();
                    var errorMsg = '{{ translate("Failed to fetch logs") }}';
                    if (status === 'timeout') {
                        errorMsg = '{{ translate("Request timed out") }}';
                    } else if (xhr.responseJSON?.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    $('#logsErrorMessage').text(errorMsg);
                    $('#logsErrorState').show();
                }
            });
        }

        function renderLogs() {
            var html = '';
            var levelColors = {
                'error': '#ff6b6b',
                'warn': '#feca57',
                'info': '#54a0ff',
                'debug': '#a29bfe',
                'trace': '#636e72'
            };
            var levelBadges = {
                'error': 'bg-danger',
                'warn': 'bg-warning text-dark',
                'info': 'bg-info',
                'debug': 'bg-secondary',
                'trace': 'bg-dark'
            };

            if (logsData.length === 0) {
                html = '<div class="text-center text-muted py-4"><i class="ri-file-list-3-line fs-1 d-block mb-2"></i>{{ translate("No logs available") }}</div>';
            } else {
                logsData.forEach(function(log, index) {
                    var level = log.level || 'info';
                    var color = levelColors[level] || '#fff';
                    var timestamp = log.timestamp ? new Date(log.timestamp).toLocaleTimeString() : '';
                    var message = log.message || log.msg || JSON.stringify(log);

                    html += '<div class="log-entry mb-1 pb-1" style="border-bottom: 1px solid rgba(255,255,255,0.05);">';
                    html += '<span class="badge ' + (levelBadges[level] || 'bg-secondary') + '" style="font-size: 10px; min-width: 50px;">' + level.toUpperCase() + '</span> ';
                    html += '<span style="color: #888; font-size: 11px;">' + timestamp + '</span> ';
                    html += '<span style="color: #e2e2e2;">' + escapeHtml(message) + '</span>';
                    html += '</div>';
                });
            }

            $('#logsPreview').html(html);

            // Update badge count
            $('#logsCountBadge').text(logsData.length + ' {{ translate("entries") }}');
        }

        function escapeHtml(text) {
            if (typeof text !== 'string') text = String(text);
            return text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Load logs when modal opens
        $('#nodeLogsModal').on('show.bs.modal', function() {
            loadNodeLogs();
        });

        // Refresh logs button
        $(document).on('click', '#refreshLogsBtn', function(e) {
            e.preventDefault();
            loadNodeLogs();
        });

        // Clear logs display
        $(document).on('click', '#clearLogsBtn', function(e) {
            e.preventDefault();
            logsData = [];
            renderLogs();
            $('#logsCount').text('0');
            $('#logsCountBadge').text('0 {{ translate("entries") }}');
        });

        // Filter change handlers
        $('#logLevelFilter, #logLinesFilter').on('change', function() {
            loadNodeLogs();
        });

        $(document).ready(function() {

            // Check all device sessions on page load
            checkAllDeviceSessions();

            $('.whatsapp-server-settings').on('click', function() {

                const modal = $('#whatsappServerSetting');
                modal.modal('show');
            });
            $('.add-whatsapp-device').on('click', function() {

                const modal = $('#addWhatsappDevice');
                modal.modal('show');
            });

            $('.update-whatsapp-device').on('click', function() {

                const modal = $('#updateWhatsappDevice');
                modal.find('form[id=updateWhatsappGatewayForm]').attr('action', $(this).data('url'));
                modal.find('input[name=per_message_min_delay]').val($(this).data('per_message_min_delay'));
                modal.find('input[name=per_message_max_delay]').val($(this).data('per_message_max_delay'));
                modal.find('input[name=delay_after_count]').val($(this).data('delay_after_count'));
                modal.find('input[name=delay_after_duration]').val($(this).data('delay_after_duration'));
                modal.find('input[name=reset_after_count]').val($(this).data('reset_after_count'));
                modal.modal('show');
            });

            $('.delete-whatsapp-device').on('click', function() {

                var modal = $('#deleteWhatsappDevice');
                modal.find('form[id=deleteWhatsappGateway]').attr('action', $(this).data('url'));
                modal.modal('show');
            });

            $('.quick-view').on('click', function() {
                const modal = $('#quick_view');
                const modalBodyInformation = modal.find('.modal-body .information-list');
                modalBodyInformation.empty();

                var uid = $(this).data('uid');
                if(uid) {
                    var title = 'gateway_identifier';
                    const listItem = $('<li>');
                    const paramKeySpan = $('<span>').text(textFormat(['_'], title, ' '));
                    const arrowIcon = $('<i>').addClass('bi bi-arrow-right');
                    const paramValueSpan = $(`<span title='${title}'>`).addClass('text-break text-muted').text(uid);

                    listItem.append(paramKeySpan).append(arrowIcon).append(paramValueSpan);
                    modalBodyInformation.append(listItem);
                }
                modal.modal('show');
            });
        });

	})(jQuery);
</script>
@endpush

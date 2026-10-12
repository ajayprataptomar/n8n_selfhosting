@extends('user.gateway.index')
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
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <h4 class="card-title">{{$title}}</h4>
            </div>
            <div class="card-header-right">
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
                                                    data-url="{{ route('user.gateway.whatsapp.device.update', ['id' => $item->id])}}"
                                                    data-per_message_min_delay="{{$item->per_message_min_delay}}"
                                                    data-per_message_max_delay="{{$item->per_message_max_delay}}"
                                                    data-delay_after_count="{{$item->delay_after_count}}"
                                                    data-delay_after_duration="{{$item->delay_after_duration}}"
                                                    data-reset_after_count="{{$item->reset_after_count}}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#updateWhatsappDevice">
                                                <i class="ri-edit-line"></i>
                                                <span class="tooltiptext"> {{ translate("Update WhatsApp Device") }} </span>
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
                                                data-url="{{route('user.gateway.whatsapp.device.destroy', ['id' => $item->id ])}}" 
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
                @include('user.partials.pagination', ['paginator' => $gateways])
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

<div class="modal fade" id="addWhatsappDevice" tabindex="-1" aria-labelledby="addWhatsappDevice" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered ">
        <div class="modal-content">
            <form action="{{route('user.gateway.whatsapp.device.store')}}" method="POST">
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
    
    $primaryPath = config("setting.file_path.{$settingKey}.path") . '/' . $imageName;
    
    $fallbackPath = config("setting.file_path.{$settingKey}.fall_back_path") . '/' . $imageName;
    
    $imagePath = file_exists($primaryPath) ? $primaryPath : $fallbackPath;
    
    $steps = explode("\n", $writtenGuide);

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

@endsection


@push('script-push')
<script>
	(function($){
		"use strict";

        flatpickr("#datePicker", {
            dateFormat: "Y-m-d",
            mode: "range",
        });

        $(document).on('click', '.qrQuote', function(e) {

            e.preventDefault()
            var id = $(this).attr('value')
            var url = "{{route('user.gateway.whatsapp.device.server.qrcode')}}"
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
                url:"{{route('user.gateway.whatsapp.device.server.status')}}",
                data: {id:id},
                dataType: 'json',
                method: 'post',
                success: function(res) {

                    if (res.response && res.response.id) {
                        $("#scan_id").val(res.response.id);
                    }

                    if (res.data.qr!=='') {

                        $('#qrcode').attr('src',res.data.qr);
                    }

                    if (res.data.status===301) {

                        sleep(2500).then(() => {

                            $('.qrQuote').offcanvas('hide');
                            location.reload();
                        });
                    } else {

                        if (res.response && res.response.id) {
                            sleep(10000).then(() => {

                                wapSession(res.response.id);
                            });
                        }
                    }
                }
            })
        }

        // Reconnect a disconnected session using saved credentials
        function deviceReconnect(deviceId) {
            var $btn = $('.deviceReconnect' + deviceId);
            var originalHtml = $btn.html();

            $.ajax({
                headers: {'X-CSRF-TOKEN': "{{csrf_token()}}"},
                url: "{{route('user.gateway.whatsapp.device.server.reconnect')}}",
                data: { id: deviceId },
                dataType: 'json',
                method: 'post',
                beforeSend: function() {
                    $btn.prop('disabled', true).html('<i class="ri-loader-2-line ri-spin"></i><span class="tooltiptext">{{ translate("Reconnecting") }}</span>');
                },
                success: function(res) {
                    if (res.success) {
                        notify('success', res.message || '{{ translate("Reconnection initiated, waiting for connection...") }}');
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
            if (attempt > 12) {
                notify('warning', '{{ translate("Reconnection is taking longer than expected. The page will reload.") }}');
                location.reload();
                return;
            }

            sleep(5000).then(() => {
                $.ajax({
                    headers: {'X-CSRF-TOKEN': "{{csrf_token()}}"},
                    url: "{{route('user.gateway.whatsapp.device.server.status')}}",
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
                    message += '. {{ translate("Click Reconnect to restore session, or Scan QR if credentials expired.") }}';
                    notify('warning', message);

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

            $('.table-filter').length ? $('.table-filter').after(banner) : $('.i-card-md').first().prepend(banner);
        }

        // Check individual device session status
        function checkDeviceSession(deviceId, sessionName) {
            $.ajax({
                headers: {'X-CSRF-TOKEN': "{{csrf_token()}}"},
                url: "{{route('user.gateway.whatsapp.device.server.status')}}",
                data: { id: deviceId },
                dataType: 'json',
                method: 'post',
                success: function(res) {
                    if (res.data.status !== 301) {
                        disconnectedDevices.push(sessionName);
                        updateDeviceStatusBadge(deviceId, 'disconnected');
                    }
                },
                error: function(e) {
                    disconnectedDevices.push(sessionName);
                    updateDeviceStatusBadge(deviceId, 'error');
                }
            });
        }

        // Update device status badge in the table
        function updateDeviceStatusBadge(deviceId, status) {
            var $row = $('button.qrQuote[value="' + deviceId + '"], button.deviceDisconnection' + deviceId).closest('tr');

            if ($row.length) {
                var $statusCell = $row.find('td[data-label="{{translate("Status")}}"]');
                if ($statusCell.length) {
                    var badge = '';
                    if (status === 'disconnected') {
                        badge = '<span class="badge bg-warning text-dark"><i class="ri-wifi-off-line"></i> {{ translate("Session Lost") }}</span>';
                    } else if (status === 'error') {
                        badge = '<span class="badge bg-danger"><i class="ri-error-warning-line"></i> {{ translate("Error") }}</span>';
                    }
                    $statusCell.html(badge);

                    // Replace disconnect button with Reconnect + Scan buttons
                    var $actionCell = $row.find('td[data-label="{{translate("Option")}}"]');
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

        $(document).ready(function() {

            // Auto-check session status for active devices
            checkAllDeviceSessions();

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

	})(jQuery);
</script>
@endpush

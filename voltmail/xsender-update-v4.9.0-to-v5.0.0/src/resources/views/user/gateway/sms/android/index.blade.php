@push("style-include")
  <link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
@endpush
@extends('user.gateway.index')
@section('tab-content')

<div class="tab-pane active fade show" id="{{url()->current()}}" role="tabpanel">
    <x-hint-banner key="gateway.android.apk_download" />
    <div class="table-filter mb-4">
        <form action="{{route(Route::currentRouteName())}}" class="filter-form">
           
            <div class="row g-3">
                <div class="col-xxl-3 col-xl-4 col-lg-4">
                    <div class="filter-search">
                        <input type="search" value="{{request()->search}}" name="search" class="form-control" id="filter-search" placeholder="{{ translate("Search by name") }}" />
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
                <h4 class="card-title">{{ $title }}</h4>
            </div>
            <div class="card-header-right">
                <div class="d-flex gap-3 align-item-center">
                    <a class="i-btn btn--info btn--sm" href="{{ site_settings("app_link") }}">
                        <i class="ri-download-line"></i> {{ translate("Download APK File") }}
                    </a>
                    @if($allowedAccess->type == App\Enums\StatusEnum::FALSE->status())
                        <button class="i-btn btn--primary btn--sm" type="button" data-bs-toggle="modal" data-bs-target="#addAndroidGateway">
                            <i class="ri-add-fill fs-16"></i> {{ translate("Add Gateway") }}
                        </button>
                    @endif
                </div>
            </div>
        </div>
        <div class="card-body px-0 pt-0">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">{{ translate("Name") }}</th>
                            @if($allowedAccess->type == App\Enums\StatusEnum::FALSE->status())
                                <th scope="col">{{ translate("Password") }}</th>
                            @endif
                            <th scope="col">{{ translate("SIM List") }}</th>
                            <th scope="col">{{ translate("Status") }}</th>
                            @if($allowedAccess->type == App\Enums\StatusEnum::FALSE->status())
                                <th scope="col">{{ translate("Option") }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>

                        @forelse($gateways as $android)
                            <tr class="@if($loop->even)@endif">

                                <td data-label="{{ translate('Name') }}">
                                    {{$android->name}}
                                </td>
                               
                                <td data-label="{{ translate('SIM List')}}">
                                    @if($allowedAccess->type == App\Enums\StatusEnum::FALSE->status())
                                    <a href="{{route('user.gateway.sms.android.sim.index', $android->token)}}" class="badge badge--primary p-2">
                                        <span class="i-badge info-solid pill">
                                            {{ translate('View all').' ('.($android->android_sims_count ?? 0).') ' }} <i class="ri-eye-line ms-1"></i>
                                        </span>
                                    </a>
                                    @else
                                        <p class="badge badge--primary p-2">
                                            <span class="i-badge info-solid pill">
                                                {{ translate('Available Sim:').' ('.count($android->android_sims_count ?? 0).') ' }}
                                            </span>
                                        </p>
                                    @endif
                                </td>
                                <td data-label="{{ translate('Status') }}">
                                    {{ $android->status->badge() }}
                                </td>
                                <td data-label="{{ translate('Created At') }}">
                                    {{ $android->created_at->toDayDateTimeString() }}
                                </td>
                               
                                
                                    <td data-label={{ translate('Option')}}>
                                       
                                        <div class="d-flex align-items-center gap-1">
                                            @if($allowedAccess->type == App\Enums\StatusEnum::FALSE->status())
                                                <button class="icon-btn btn-ghost btn-sm success-soft circle update-android-gateway"
                                                        type="button"
                                                        data-android-name="{{ $android->name }}"
                                                        data-android-status="{{ $android->status->value }}"
                                                        data-url = "{{route('user.gateway.sms.android.update', ['id' => $android->id])}}"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#updateAndroidGateway">
                                                    <i class="ri-edit-line"></i>
                                                    <span class="tooltiptext"> {{ translate("Update Android Gateway") }} </span>
                                                </button>
                                            @endif
                                            @if($android->status->value != \App\Enums\System\SessionStatusEnum::CONNECTED->value)
                                                <button class="icon-btn btn-ghost btn-sm success-soft circle textChange"
                                                        value="{{$android->qr_code}}"
                                                        type="button"
                                                        data-id="scanQR"
                                                        data-bs-toggle="offcanvas"
                                                        data-bs-target="#offcanvasQrCode"
                                                        aria-controls="offcanvasQrCode">

                                                    <i class="ri-qr-code-fill"></i>
                                                    <span class="tooltiptext"> {{ translate("Scan") }} </span>
                                                </button>
                                            @endif
                                            @if($allowedAccess->type == App\Enums\StatusEnum::FALSE->status())
                                                <button class="icon-btn btn-ghost btn-sm danger-soft circle text-danger delete-android-gateway"
                                                        type="button"
                                                        data-bs-toggle="modal"
                                                        data-url = "{{route('user.gateway.sms.android.delete', ['id' => $android->id])}}"
                                                        data-bs-target="#deleteAndroidGateway">
                                                    <i class="ri-delete-bin-line"></i>
                                                    <span class="tooltiptext"> {{ translate("Delete Android Gateway") }} </span>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                            </tr>
			                @empty
			                	<tr>
			                		<td class="text-muted text-center" colspan="100%">{{ translate('No Data Found') }}</td>
			                	</tr>
			                @endforelse
                    </tbody>
                </table>
            </div>
            @include('user.partials.pagination', ['paginator' => $gateways])
        </div>
    </div>
</div>

@endsection

@section('modal')

@if($allowedAccess->type == App\Enums\StatusEnum::FALSE->status())
<div class="modal fade" id="addAndroidGateway" tabindex="-1" aria-labelledby="addAndroidGateway" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered ">
        <div class="modal-content">
            <form action="{{route('user.gateway.sms.android.store')}}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Add Session") }} </h5>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                        <i class="ri-close-large-line"></i>
                    </button>
                </div>
                <div class="modal-body modal-sm-custom-height">
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="form-inner">
                                <label for="name" class="form-label"> {{ translate('Name')}} <span class="text-danger">*</span></label>
                                <input required type="text" id="name" name="name" placeholder="{{ translate('Enter session name')}}" class="form-control" aria-label="name"/>
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

<div class="modal fade" id="updateAndroidGateway" tabindex="-1" aria-labelledby="updateAndroidGateway" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered ">
        <div class="modal-content">
            <form action="#" method="POST" id="updateAndroidSession">
                @csrf
                <input type="hidden" name="_method" value="PATCH">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Update Android Session") }} </h5>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                        <i class="ri-close-large-line"></i>
                    </button>
                </div>
                <div class="modal-body modal-lg-custom-height">
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="form-inner">
                                <label for="name_update" class="form-label"> {{ translate('Name')}} <span class="text-danger">*</span></label>
                                <input type="text" id="name_update" name="name" placeholder="{{ translate('Enter android session name')}}" class="form-control" aria-label="name"/>
                            </div>
                        </div>
                        <div class="col-12 mt-3">
                            <div class="form-inner">
                                <label for="status" class="form-label">{{ translate("Status") }} <span class="text-danger">*</span></label>
                                <select data-placeholder="{{translate('Select a status')}}" class="form-select select2-search" data-show="4" id="status" name="status">
                                    <option value=""></option>
                                    @foreach(\App\Enums\System\SessionStatusEnum::getValues() as $value)
                                        <option value="{{$value}}">{{ucFirst($value)}}</option>
                                    @endforeach
                                </select>
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

<div class="modal fade actionModal" id="deleteAndroidGateway" tabindex="-1" aria-labelledby="deleteAndroidGateway" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
        <div class="modal-header text-start">
            <span class="action-icon danger">
            <i class="bi bi-exclamation-circle"></i>
            </span>
        </div>
        <form method="POST" id="deleteAndroidSession">
            @csrf
            <div class="modal-body">
                <input type="hidden" name="_method" value="DELETE">
                <div class="action-message">
                    <h5>{{ translate("Are you sure to delete this Android Gateway?") }}</h5>
                    <p>{{ translate("By clicking on 'Delete', you will permanently remove the android gateway from the application") }}</p>
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

@php
    $channel = \App\Enums\System\ChannelTypeEnum::SMS->value; 
    $title = translate('Connect Android Session');
    $settingKey = \App\Enums\SettingKey::ANDROID_OFF_CANVAS_GUIDE->value;

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

@endif

@endsection

@push("script-include")
    <script src="{{asset('assets/theme/global/js/select2.min.js')}}"></script>
    <script src="{{asset('assets/theme/global/js/qrcode.min.js')}}"></script>
@endpush
@push('script-push')
<script>
    document.querySelectorAll('[data-id="scanQR"]').forEach(button => {
        button.addEventListener('click', function () {
        
            const qrCode = this.value;
            document.getElementById('scan_id').value = qrCode;
            const qrCodeElement = document.getElementById('qrcode');
            qrCodeElement.src = ''; 
            QRCode.toDataURL(qrCode, { width: 200, margin: 1 }, function (error, url) {
                if (error) console.error(error);
                qrCodeElement.src = url;
            });
        });
    });
</script>
<script>
	(function($){
        "use strict";

        $(document).ready(function() {
            select2_search($('.select2-search').data('placeholder'), "#updateAndroidGateway");

            $(document).on('click', '.update-android-gateway', function(){
                const modal = $('#updateAndroidGateway');
                modal.find('input[name=id]').val($(this).data('android-id'));
                modal.find('input[name=name]').val($(this).data('android-name'));
                modal.find('form[id=updateAndroidSession]').attr('action', $(this).data('url'));
                
                const status = $(this).data('android-status');
                modal.find('select[name=status]').val(status).trigger('change');
                
                modal.modal('show');
            });

            $(document).on('click', '.delete-android-gateway', function(){
                var modal = $('#deleteAndroidGateway');
                modal.find('form[id=deleteAndroidSession]').attr('action', $(this).data('url'));
                modal.modal('show');
            });

            flatpickr("#datePicker", {
                dateFormat: "Y-m-d",
                mode: "range",
            });

            $(document).on('click', '.add-android-gateway', function() {
                const modal = $('#addAndroidGateway');
                modal.modal('show');
            });

            $(document).on('click', '.checkAll', function(){
                $('input:checkbox').not(this).prop('checked', this.checked);
            });
        });

    })(jQuery);

</script>
@endpush

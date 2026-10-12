<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasQrCode" aria-labelledby="offcanvasQrCode" data-bs-backdrop="static" data-channel="{{ \Illuminate\Support\Arr::get($data, 'channel') }}">
    <div class="offcanvas-header justify-content-between bg-light">
        <h5 class="offcanvas-title" id="offcanvasExampleLabel">{{ \Illuminate\Support\Arr::get($data, 'title') }}</h5>
        <button
            type="button"
            class="icon-btn btn-sm dark-soft hover circle modal-closer"
            data-bs-dismiss="offcanvas"
            @if(\Illuminate\Support\Arr::get($data, 'channel') === 'whatsapp')
                onclick="return deviceStatusUpdate('','initiate','','','')"
            @endif>
            <i class="ri-close-line"></i>
        </button>
    </div>
    
    <div class="offcanvas-body p-0">
        <div class="d-flex flex-column justify-content-between h-100">
            <div class="p-3">
                <input type="hidden" name="scan_id" id="scan_id" value="">
                <div class="qr-edit-section" data-type="written_guide">
                    <ul class="qr-edit-content qr-edit-list">
                        @foreach(\Illuminate\Support\Arr::get($data, 'steps', []) as $step)
                            @if(trim($step))
                                <li>
                                    <p>{{ $step }}</p>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                    @if(request()->routeIs('admin.*'))
                        <button class="qr-edit-btn qr-edit-icon-btn qr-edit-info-soft qr-edit-circle" type="button">
                            <i class="ri-edit-line"></i>
                            <span class="qr-edit-tooltip">{{ translate('Edit Steps') }}</span>
                        </button>
                    @endif
                    <div class="qr-edit-form-container d-none">
                        <form class="settingsForm" data-route="{{ route('admin.system.setting.store') }}">
                            @csrf
                            <input type="hidden" name="channel" value="{{ \Illuminate\Support\Arr::get($data, 'channel') }}">
                            <textarea name="site_settings[{{ \Illuminate\Support\Arr::get($data, 'settingKey') }}][written_guide][message]" class="form-control mb-2">{{ \Illuminate\Support\Arr::get($data, 'written_guide.message', '') }}</textarea>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-sm btn-secondary qr-edit-cancel-btn">{{ translate('Cancel') }}</button>
                                <button type="submit" class="btn btn-sm btn-primary">{{ translate('Save') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="qr-code mt-5">
                    <img id="qrcode">
                </div>
            </div>

            <div class="py-xl-5 py-4 px-3 bg-light mt-5 qr-edit-tutorial-container">
                @if(request()->routeIs('admin.*'))
                    <button class="qr-edit-tutorial-btn qr-edit-icon-btn qr-edit-info-soft qr-edit-circle" type="button">
                        <i class="ri-edit-line"></i>
                        <span class="qr-edit-tooltip">{{ translate('Edit Tutorial') }}</span>
                    </button>
                @endif
                
                <h6 class="mb-2 text-center">{{ translate('Tutorial') }}</h6>
                
                <div class="text-center h-100">
                    <div class="qr-edit-section d-inline-block" data-type="external_guide">
                        <a class="fs-14 text-info qr-edit-link" href="{{ \Illuminate\Support\Arr::get($data, 'external_guide.link', config("setting.{$settingKey}.external_guide.link")) }}">
                            <i class="ri-information-line me-1"></i>{{ \Illuminate\Support\Arr::get($data, 'external_guide.text', config("setting.{$settingKey}.external_guide.text")) }}
                        </a>
                        <div class="qr-edit-form-container d-none">
                            <form class="settingsForm" data-route="{{ route('admin.system.setting.store') }}">
                                @csrf
                                <input type="hidden" name="channel" value="{{ \Illuminate\Support\Arr::get($data, 'channel') }}">
                                <input type="text" name="site_settings[{{ \Illuminate\Support\Arr::get($data, 'settingKey') }}][external_guide][text]" class="form-control mb-2" value="{{ \Illuminate\Support\Arr::get($data, 'external_guide.text', '') }}" placeholder="{{ translate('Link Text') }}">
                                <input type="url" name="site_settings[{{ \Illuminate\Support\Arr::get($data, 'settingKey') }}][external_guide][link]" class="form-control mb-2" value="{{ \Illuminate\Support\Arr::get($data, 'external_guide.link', '') }}" placeholder="{{ translate('Link URL') }}">
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-sm btn-secondary qr-edit-cancel-btn">{{ translate('Cancel') }}</button>
                                    <button type="submit" class="btn btn-sm btn-primary">{{ translate('Save') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="mt-4 qr-edit-section" data-type="image">
                        <div class="qr-edit-image-container">
                            <img src="{{ showImage(\Illuminate\Support\Arr::get($data, 'image.path', ''), config("setting.file_path.{$settingKey}.size")) }}" alt="{{ translate('Tutorial') }}" class="qr-edit-image img-fluid rounded">
                            @if(request()->routeIs('admin.*'))
                                <button class="qr-edit-btn qr-edit-icon-btn qr-edit-info-soft qr-edit-circle" type="button">
                                    <i class="ri-edit-line"></i>
                                    <span class="qr-edit-tooltip">{{ translate('Update Image') }}</span>
                                </button>
                            @endif
                        </div>
                        <div class="qr-edit-form-container d-none">
                            <form class="settingsForm" data-route="{{ route('admin.system.setting.store') }}" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="channel" value="{{ \Illuminate\Support\Arr::get($data, 'channel') }}">
                                <div class="mb-3">
                                    <input type="file" name="site_settings[{{ \Illuminate\Support\Arr::get($data, 'settingKey') }}][image][name]" class="form-control qr-edit-image-input" accept="image/*">
                                </div>
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-sm btn-secondary qr-edit-cancel-btn">{{ translate('Cancel') }}</button>
                                    <button type="submit" class="btn btn-sm btn-primary qr-edit-update-btn d-none">{{ translate('Update') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@extends("admin.layouts.app")
@push("style-include")
<link rel="stylesheet" href="{{asset('assets/theme/admin/css/dashboard.css')}}">
@endpush
@section("panel")

  <main class="main-body">
    <div class="container-fluid px-0 main-content">
      <div class="page-header">
        <h2>{{ $title }}</h2>
      </div>
      <div class="row g-4">

        {{-- ============================================ --}}
        {{-- Row 0: Setup Checklist (only if incomplete)  --}}
        {{-- ============================================ --}}
        @if($setupChecklist['percent'] < 100)
          <div class="col-12">
            <div class="setup-banner">
              <div class="setup-banner__left">
                <div class="setup-banner__icon">
                  <i class="ri-rocket-2-line"></i>
                </div>
                <div class="setup-banner__info">
                  <h6 class="setup-banner__title">{{ translate("Complete Your Setup") }}</h6>
                  <p class="setup-banner__desc">{{ translate("Configure these essentials to start sending messages") }}</p>
                </div>
              </div>
              <div class="setup-banner__items">
                @foreach($setupChecklist['items'] as $item)
                  <a href="{{ $item['route'] }}" class="setup-banner__step {{ $item['done'] ? 'is-done' : 'is-pending' }}">
                    <span class="setup-banner__step-icon">
                      @if($item['done'])
                        <i class="ri-checkbox-circle-fill"></i>
                      @else
                        <i class="ri-checkbox-blank-circle-line"></i>
                      @endif
                    </span>
                    <span class="setup-banner__step-body">
                      <span class="setup-banner__step-label">{{ $item['label'] }}</span>
                      <span class="setup-banner__step-hint">{{ $item['hint'] }}</span>
                    </span>
                  </a>
                @endforeach
              </div>
              <div class="setup-banner__progress-wrap">
                <span class="setup-banner__progress-label">{{ $setupChecklist['completed'] }}/{{ $setupChecklist['total'] }}</span>
                <div class="setup-banner__progress">
                  <div class="setup-banner__progress-bar" style="width: {{ $setupChecklist['percent'] }}%"></div>
                </div>
              </div>
            </div>
          </div>
        @endif

        {{-- ================================ --}}
        {{-- Row 1: Quick Overview Stats      --}}
        {{-- ================================ --}}
        <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6">
          <div class="card overview-card border-0 shadow-sm h-100">
            <div class="card-body p-3">
              <div class="overview-card__icon overview-card__icon--primary">
                <i class="ri-group-line"></i>
              </div>
              <div class="overview-card__value">{{ formatNumber($quickStats['total_users']) }}</div>
              <div class="overview-card__label">{{ translate("Total Users") }}</div>
            </div>
          </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6">
          <div class="card overview-card border-0 shadow-sm h-100">
            <div class="card-body p-3">
              <div class="overview-card__icon overview-card__icon--success">
                <i class="ri-vip-crown-line"></i>
              </div>
              <div class="overview-card__value">{{ formatNumber($quickStats['active_subscriptions']) }}</div>
              <div class="overview-card__label">{{ translate("Active Plans") }}</div>
            </div>
          </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6">
          <div class="card overview-card border-0 shadow-sm h-100">
            <div class="card-body p-3">
              <div class="overview-card__icon overview-card__icon--info">
                <i class="ri-money-dollar-circle-line"></i>
              </div>
              <div class="overview-card__value">{{ shortAmount($quickStats['monthly_revenue']) }}</div>
              <div class="overview-card__label">{{ translate("Revenue This Month") }}</div>
            </div>
          </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6">
          <div class="card overview-card border-0 shadow-sm h-100">
            <div class="card-body p-3">
              <div class="overview-card__icon overview-card__icon--warning">
                <i class="ri-send-plane-line"></i>
              </div>
              <div class="overview-card__value">{{ formatNumber($quickStats['messages_today']) }}</div>
              <div class="overview-card__label">{{ translate("Messages Today") }}</div>
            </div>
          </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6">
          <div class="card overview-card border-0 shadow-sm h-100">
            <div class="card-body p-3">
              <div class="overview-card__icon overview-card__icon--danger">
                <i class="ri-customer-service-2-line"></i>
              </div>
              <div class="overview-card__value">{{ formatNumber($quickStats['pending_tickets']) }}</div>
              <div class="overview-card__label">{{ translate("Open Tickets") }}</div>
            </div>
          </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6">
          <div class="card overview-card border-0 shadow-sm h-100">
            <div class="card-body p-3">
              <div class="overview-card__icon overview-card__icon--secondary">
                <i class="ri-stack-line"></i>
              </div>
              <div class="overview-card__value">{{ formatNumber($quickStats['pending_queue']) }}</div>
              <div class="overview-card__label">{{ translate("Queue Jobs") }}</div>
            </div>
          </div>
        </div>

        {{-- ================================ --}}
        {{-- Row 2: Channel Statistics        --}}
        {{-- ================================ --}}
        <div class="col-xxl-4 col-xl-4 col-lg-6 col-md-6">
          <div class="card feature-card border-0 shadow-sm h-100">
              <div class="card-header bg-transparent border-0 pb-2 px-3 pt-3">
                  <div class="d-flex gap-3 justify-content-between align-items-center w-100">
                      <div class="card-header-left">
                          <h4 class="card-title mb-0 fw-semibold fs-6">{{ translate("SMS Statistics") }}</h4>
                      </div>
                      <div class="card-header-right">
                          <span class="fs-4">
                              <i class="ri-message-2-line"></i>
                          </span>
                      </div>
                  </div>
              </div>
              <div class="card-body pt-1 px-3 pb-3">
                  <div class="row g-3">
                      <div class="col-6">
                          <a href="{{ route('admin.communication.sms.index') }}" target="_blank" class="text-decoration-none">
                              <div class="feature-status feature-status-primary p-3 rounded-3 border position-relative overflow-hidden">
                                  <div class="d-flex justify-content-start mb-2">
                                      <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-circle shadow-sm">
                                          <i class="ri-message-2-line"></i>
                                      </span>
                                  </div>
                                  <div class="text-center">
                                      <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['sms']['all']) }}</p>
                                      <p class="mb-0 fw-medium text-uppercase">{{ translate("Total") }}</p>
                                  </div>
                                  <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                              </div>
                          </a>
                      </div>
                      <div class="col-6">
                          <a href="{{route('admin.communication.sms.index') }}?status={{ \App\Enums\System\CommunicationStatusEnum::DELIVERED->value }}" target="_blank" class="text-decoration-none">
                              <div class="feature-status feature-status-success p-3 rounded-3 border position-relative overflow-hidden">
                                  <div class="d-flex justify-content-start mb-2">
                                      <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-circle shadow-sm">
                                          <i class="ri-check-double-line"></i>
                                      </span>
                                  </div>
                                  <div class="text-center">
                                      <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['sms']['success']) }}</p>
                                      <p class="mb-0 fw-medium text-uppercase">{{ translate("Success") }}</p>
                                  </div>
                                  <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                              </div>
                          </a>
                      </div>
                      <div class="col-6">
                          <a href="{{route('admin.communication.sms.index') }}?status={{ \App\Enums\System\CommunicationStatusEnum::PENDING->value }}" target="_blank" class="text-decoration-none">
                              <div class="feature-status feature-status-warning p-3 rounded-3 border position-relative overflow-hidden">
                                  <div class="d-flex justify-content-start mb-2">
                                      <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-circle shadow-sm">
                                          <i class="ri-hourglass-fill"></i>
                                      </span>
                                  </div>
                                  <div class="text-center">
                                      <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['sms']['pending']) }}</p>
                                      <p class="mb-0 fw-medium text-uppercase">{{ translate("Pending") }}</p>
                                  </div>
                                  <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                              </div>
                          </a>
                      </div>
                      <div class="col-6">
                          <a href="{{route('admin.communication.sms.index') }}?status={{ \App\Enums\System\CommunicationStatusEnum::FAIL->value }}" target="_blank" class="text-decoration-none">
                              <div class="feature-status feature-status-danger p-3 rounded-3 border position-relative overflow-hidden">
                                  <div class="d-flex justify-content-start mb-2">
                                      <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-circle shadow-sm">
                                          <i class="ri-mail-close-line"></i>
                                      </span>
                                  </div>
                                  <div class="text-center">
                                      <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['sms']['failed']) }}</p>
                                      <p class="mb-0 fw-medium text-uppercase">{{ translate("Failed") }}</p>
                                  </div>
                                  <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                              </div>
                          </a>
                      </div>
                  </div>
              </div>
          </div>
        </div>

        <div class="col-xxl-4 col-xl-4 col-lg-6 col-md-6">
            <div class="card feature-card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 pb-2 px-3 pt-3">
                    <div class="d-flex gap-3 justify-content-between align-items-center w-100">
                        <div class="card-header-left">
                            <h4 class="card-title mb-0 fw-semibold fs-6">{{ translate("Email Statistics") }}</h4>
                        </div>
                        <div class="card-header-right">
                            <span class="fs-4">
                                <i class="ri-mail-line text-danger"></i>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body pt-1 px-3 pb-3">
                    <div class="row g-3">
                        <div class="col-6">
                            <a href="{{route('admin.communication.email.index') }}" target="_blank" class="text-decoration-none">
                                <div class="feature-status feature-status-primary p-3 rounded-3 border position-relative overflow-hidden">
                                    <div class="d-flex justify-content-start mb-2">
                                        <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-circle shadow-sm"><i class="ri-mail-line"></i></span>
                                    </div>
                                    <div class="text-center">
                                        <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['email']['all']) }}</p>
                                        <p class="mb-0 fw-medium text-uppercase">{{ translate("Total") }}</p>
                                    </div>
                                    <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                                </div>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{route('admin.communication.email.index') }}?status={{ \App\Enums\System\CommunicationStatusEnum::DELIVERED->value }}" target="_blank" class="text-decoration-none">
                                <div class="feature-status feature-status-success p-3 rounded-3 border position-relative overflow-hidden">
                                    <div class="d-flex justify-content-start mb-2">
                                        <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-circle shadow-sm"><i class="ri-check-double-line"></i></span>
                                    </div>
                                    <div class="text-center">
                                        <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['email']['success']) }}</p>
                                        <p class="mb-0 fw-medium text-uppercase">{{ translate("Success") }}</p>
                                    </div>
                                    <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                                </div>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{route('admin.communication.email.index') }}?status={{ \App\Enums\System\CommunicationStatusEnum::PENDING->value }}" target="_blank" class="text-decoration-none">
                                <div class="feature-status feature-status-warning p-3 rounded-3 border position-relative overflow-hidden">
                                    <div class="d-flex justify-content-start mb-2">
                                        <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-circle shadow-sm"><i class="ri-hourglass-fill"></i></span>
                                    </div>
                                    <div class="text-center">
                                        <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['email']['pending']) }}</p>
                                        <p class="mb-0 fw-medium text-uppercase">{{ translate("Pending") }}</p>
                                    </div>
                                    <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                                </div>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{route('admin.communication.email.index') }}?status={{ \App\Enums\System\CommunicationStatusEnum::FAIL->value }}" target="_blank" class="text-decoration-none">
                                <div class="feature-status feature-status-danger p-3 rounded-3 border position-relative overflow-hidden">
                                    <div class="d-flex justify-content-start mb-2">
                                        <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-circle shadow-sm"><i class="ri-mail-close-line"></i></span>
                                    </div>
                                    <div class="text-center">
                                        <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['email']['failed']) }}</p>
                                        <p class="mb-0 fw-medium text-uppercase">{{ translate("Failed") }}</p>
                                    </div>
                                    <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xxl-4 col-xl-4 col-lg-6 col-md-6">
            <div class="card feature-card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 pb-2 px-3 pt-3">
                    <div class="d-flex gap-3 justify-content-between align-items-center w-100">
                        <div class="card-header-left">
                            <h4 class="card-title mb-0 fw-semibold fs-6">{{ translate("Whatsapp Statistics") }}</h4>
                        </div>
                        <div class="card-header-right">
                            <span class="fs-4">
                                <i class="ri-whatsapp-line text-success"></i>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body pt-1 px-3 pb-3">
                    <div class="row g-3">
                        <div class="col-6">
                            <a href="{{route('admin.communication.whatsapp.index') }}" target="_blank" class="text-decoration-none">
                                <div class="feature-status feature-status-primary p-3 rounded-3 border position-relative overflow-hidden">
                                    <div class="d-flex justify-content-start mb-2">
                                        <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-circle shadow-sm"><i class="ri-whatsapp-line"></i></span>
                                    </div>
                                    <div class="text-center">
                                        <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['whats_app']['all']) }}</p>
                                        <p class="mb-0 fw-medium text-uppercase">{{ translate("Total") }}</p>
                                    </div>
                                    <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                                </div>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{route('admin.communication.whatsapp.index') }}?status={{ \App\Enums\System\CommunicationStatusEnum::DELIVERED->value }}" target="_blank" class="text-decoration-none">
                                <div class="feature-status feature-status-success p-3 rounded-3 border position-relative overflow-hidden">
                                    <div class="d-flex justify-content-start mb-2">
                                        <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-circle shadow-sm"><i class="ri-check-double-line"></i></span>
                                    </div>
                                    <div class="text-center">
                                        <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['whats_app']['success']) }}</p>
                                        <p class="mb-0 fw-medium text-uppercase">{{ translate("Success") }}</p>
                                    </div>
                                    <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                                </div>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{route('admin.communication.whatsapp.index') }}?status={{ \App\Enums\System\CommunicationStatusEnum::PENDING->value }}" target="_blank" class="text-decoration-none">
                                <div class="feature-status feature-status-warning p-3 rounded-3 border position-relative overflow-hidden">
                                    <div class="d-flex justify-content-start mb-2">
                                        <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-circle shadow-sm"><i class="ri-hourglass-fill"></i></span>
                                    </div>
                                    <div class="text-center">
                                        <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['whats_app']['pending']) }}</p>
                                        <p class="mb-0 fw-medium text-uppercase">{{ translate("Pending") }}</p>
                                    </div>
                                    <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                                </div>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{route('admin.communication.whatsapp.index') }}?status={{ \App\Enums\System\CommunicationStatusEnum::FAIL->value }}" target="_blank" class="text-decoration-none">
                                <div class="feature-status feature-status-danger p-3 rounded-3 border position-relative overflow-hidden">
                                    <div class="d-flex justify-content-start mb-2">
                                        <span class="feature-icon d-inline-flex align-items-center justify-content-center rounded-circle shadow-sm"><i class="ri-mail-close-line"></i></span>
                                    </div>
                                    <div class="text-center">
                                        <p class="feature-status-count fs-5 fw-bold mb-1 lh-1">{{ formatNumber($logs['whats_app']['failed']) }}</p>
                                        <p class="mb-0 fw-medium text-uppercase">{{ translate("Failed") }}</p>
                                    </div>
                                    <div class="new-tab-icon position-absolute"><i class="ri-external-link-line"></i></div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================================ --}}
        {{-- Row 3: Charts                    --}}
        {{-- ================================ --}}
        <div class="col-xxl-4 col-xl-5">
          <div class="card card-height-100">
            <div class="card-header">
              <h4 class="card-title">{{ translate("Application Usage") }}</h4>
            </div>
            <div class="card-body">
              <div id="application_usage"
                   class="apex-charts"
                   data-name='["application_usage"]'
                   data-sms-heading="SMS"
                   data-sms-color="{{ site_settings("primary_color") }}"
                   data-sms="{{ $logs["sms"]["all"] }}"
                   data-whatsapp-heading="WhatsApp"
                   data-whatsapp-color="{{ site_settings("secondary_color") }}"
                   data-whatsapp="{{ $logs["whats_app"]["all"] }}"
                   data-email-heading="Email"
                   data-email-color="{{ site_settings("trinary_color") }}"
                   data-email="{{ $logs["email"]["all"] }}">
              </div>
            </div>
          </div>
        </div>
        <div class="col-xxl-8 col-xl-7">
          <div class="card card-height-100">
            <div class="card-header pb-0">
              <div class="card-header-left">
                <h4 class="card-title">{{ translate("Subscriptions") }}</h4>
              </div>
            </div>
            <div class="card-body">
              <div id="subscription-chart"
                   class="apex-charts"
                   data-chartData="{{ json_encode($totalUser) }}"
                   data-tool-tip-theme="{{ site_settings("theme_mode") == \App\Enums\StatusEnum::TRUE->status() ? 'light' : 'dark' }}"
                   data-legend-theme="{{ site_settings("theme_mode") == \App\Enums\StatusEnum::TRUE->status() ? '#000000a2' : '#ffffffa9' }}">
              </div>
            </div>
          </div>
        </div>

        {{-- ================================ --}}
        {{-- Row 4: Tables                    --}}
        {{-- ================================ --}}
        <div class="col-xxl-6">
          <div class="card">
            <div class="card-header">
              <div class="card-header-left">
                <h4 class="card-title">{{ translate("New Users") }}</h4>
              </div>
            </div>
            <div class="card-body px-0 pt-0">
              <div class="table-container">
                <div class="default_table">
                  <table>
                    <thead>
                      <tr>
                        <th scope="col">{{ translate("Name") }}</th>
                        <th scope="col">{{ translate("Email/Phone") }}</th>
                        <th scope="col">{{ translate("Status") }}</th>
                        <th scope="col">{{ translate("Joined At") }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($customers as $customer)
                        <tr>
                          <td>
                            <p class="text-dark fw-medium">{{ $customer?->name }}</p>
                          </td>
                          <td>
                            <a href="mailto:{{ $customer?->email }}" class="text-dark">{{ $customer?->email }}</a>
                          </td>
                          <td>
                            <span class="i-badge dot {{ $customer->status == \App\Enums\StatusEnum::TRUE->status() ? 'success' : 'danger' }}-soft pill">{{ $customer->status == \App\Enums\StatusEnum::TRUE->status() ? translate("Active") : translate("Banned") }}</span>
                          </td>
                          <td>
                            <span>{{ @$customer?->created_at?->diffForHumans() }}</span>
                            <p>{{ @$customer?->created_at?->toDayDateTimeString() }}</p>
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-xxl-6">
          <div class="card">
            <div class="card-header">
              <div class="card-header-left">
                <h4 class="card-title">{{ translate("Latest Payment Log") }}</h4>
              </div>
            </div>
            <div class="card-body px-0 pt-0">
              <div class="table-container">
                <div class="default_table">
                  <table>
                    <thead>
                      <tr>
                        <th scope="col">{{ translate("Customer") }}</th>
                        <th scope="col">{{ translate("Payment Gateway") }}</th>
                        <th scope="col">{{ translate("Amount") }}</th>
                        <th scope="col">{{ translate("TrxID") }}</th>
                        <th scope="col">{{ translate("Date/Time") }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @forelse($paymentLogs as $paymentLog)
                        <tr>
                          <td>
                            <p class="text-dark fw-medium">{{ $paymentLog?->user?->name }}</p>
                          </td>
                          <td>
                            <span>{{ $paymentLog->paymentGateway ? $paymentLog->paymentGateway->name : translate("N\A") }}</span>
                          </td>
                          <td>
                            <span class="text-dark fw-semibold">{{shortAmount(@$paymentLog->amount)}} {{ $paymentLog->paymentGateway ? $paymentLog->paymentGateway->currency_code : translate("N\A") }}</span>
                          </td>
                          <td>
                            <p>{{$paymentLog->trx_number}}</p>
                            @php echo payment_status($paymentLog->status)  @endphp
                          </td>
                          <td>
                            <span>{{ $paymentLog?->created_at->diffForHumans() }}</span>
                            <p> {{ $customer?->created_at->toDayDateTimeString() }}</p>
                          </p>
                          </td>
                        </tr>
                      @empty
                        <tr>
                          <td class="text-muted text-center" colspan="100%">{{ translate('No Data Found')}}</td>
                        </tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

@endsection

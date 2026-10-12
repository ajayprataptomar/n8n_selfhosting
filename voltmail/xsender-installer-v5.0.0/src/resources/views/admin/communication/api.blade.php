@extends('admin.layouts.app')
@section('panel')
<main class="main-body">
  <div class="container-fluid px-0 main-content">

    {{-- Compact Sticky Header --}}
    <div class="api-sticky-header">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
          <h5 class="mb-0 fw-bold">{{ translate("API Documentation") }}</h5>
          <span class="api-base-url" style="cursor:default;user-select:none;">{{ translate("Base URL") }}: <code>{{ url('/api') }}/...</code></span>
        </div>
        <div class="api-key-group">
          <span class="api-key-label"><i class="ri-key-2-line"></i> {{ translate("API Key:") }}</span>
          <input type="text" class="api-key-input" id="api_key" value="{{ $api_key }}" readonly>
          <button class="api-key-btn" type="button" id="copy_api_key" title="{{ translate('Copy') }}"><i class="ri-file-copy-line"></i></button>
          <button class="api-key-btn generate-api-key" type="button" title="{{ translate('Regenerate') }}"><i class="ri-refresh-line"></i></button>
        </div>
      </div>
    </div>

    {{-- Authentication Info --}}
    <div class="api-auth-info mb-4">
      <div class="alert alert-info d-flex align-items-start gap-3 mb-0">
        <i class="ri-shield-keyhole-line fs-4"></i>
        <div>
          <strong>{{ translate("Authentication") }}</strong>
          <p class="mb-2 small">{{ translate("You can provide your API key in one of these ways:") }}</p>
          <div class="d-flex flex-wrap gap-3 small">
            <div><code class="bg-white px-2 py-1 rounded">Header: Api-key</code> <span class="text-muted">{{ translate("(Recommended)") }}</span></div>
            <div><code class="bg-white px-2 py-1 rounded">URL: ?api_key=xxx</code> <span class="text-muted">{{ translate("(For GET requests)") }}</span></div>
            <div><code class="bg-white px-2 py-1 rounded">Body: api_key</code> <span class="text-muted">{{ translate("(For POST requests)") }}</span></div>
          </div>
        </div>
      </div>
    </div>

    {{-- Gateway Configuration Card --}}
    <div class="api-gateway-settings mb-4">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2">
            <i class="ri-settings-4-line fs-5" style="color: var(--color-primary)"></i>
            <h6 class="mb-0 fw-bold">{{ translate("API Gateway Configuration") }}</h6>
          </div>
          <button type="button" class="i-btn btn--primary outline btn--sm" data-bs-toggle="collapse" data-bs-target="#gatewaySettingsCollapse" aria-expanded="false">
            <i class="ri-settings-3-line me-1"></i>{{ translate("Configure") }}
          </button>
        </div>
        <div class="collapse" id="gatewaySettingsCollapse">
          <div class="card-body">
            <p class="text-muted small mb-4">
              <i class="ri-information-line me-1"></i>
              {{ translate("Configure which gateway to use when sending messages via API. Select 'Automatic' to let the system choose the best available gateway, or select a specific gateway.") }}
            </p>
            <form action="{{ route('admin.communication.api.gateway.save') }}" method="POST">
              @csrf
              <div class="row g-4">
                {{-- WhatsApp Gateway --}}
                <div class="col-lg-4 col-md-6">
                  <div class="gateway-config-card whatsapp">
                    <div class="gateway-config-header">
                      <i class="ri-whatsapp-line"></i>
                      <span>{{ translate("WhatsApp") }}</span>
                    </div>
                    <div class="gateway-config-body">
                      <label class="form-label small fw-medium">{{ translate("Default Gateway") }}</label>
                      <select name="site_settings[api_whatsapp_gateway_id]" class="form-select form-select-sm">
                        <option value="">{{ translate("Automatic (Use default)") }}</option>
                        @if(isset($gateways['whatsapp']) && $gateways['whatsapp']->count() > 0)
                          @foreach($gateways['whatsapp'] as $gateway)
                            <option value="{{ $gateway->id }}" {{ site_settings('api_whatsapp_gateway_id') == $gateway->id ? 'selected' : '' }}>
                              {{ $gateway->name }} ({{ $gateway->address ?: $gateway->type }})
                            </option>
                          @endforeach
                        @endif
                      </select>
                      <div class="gateway-status mt-2">
                        @if(isset($gateways['whatsapp']) && $gateways['whatsapp']->count() > 0)
                          <span class="badge bg-success-subtle text-success"><i class="ri-checkbox-circle-line me-1"></i>{{ $gateways['whatsapp']->count() }} {{ translate("available") }}</span>
                        @else
                          <span class="badge bg-warning-subtle text-warning"><i class="ri-error-warning-line me-1"></i>{{ translate("No gateways") }}</span>
                        @endif
                        <a href="{{ route('admin.gateway.whatsapp.device.index') }}" class="badge bg-primary-subtle text-primary ms-1">{{ translate("Manage") }}</a>
                      </div>
                    </div>
                  </div>
                </div>

                {{-- SMS Gateway --}}
                <div class="col-lg-4 col-md-6">
                  <div class="gateway-config-card sms">
                    <div class="gateway-config-header">
                      <i class="ri-message-2-line"></i>
                      <span>{{ translate("SMS") }}</span>
                    </div>
                    <div class="gateway-config-body">
                      <label class="form-label small fw-medium">{{ translate("Gateway Type") }}</label>
                      <select name="site_settings[api_sms_method]" class="form-select form-select-sm mb-2">
                        <option value="{{ \App\Enums\StatusEnum::FALSE->status() }}" {{ site_settings('api_sms_method') == \App\Enums\StatusEnum::FALSE->status() ? 'selected' : '' }}>
                          {{ translate("API Gateway") }}
                        </option>
                        <option value="{{ \App\Enums\StatusEnum::TRUE->status() }}" {{ site_settings('api_sms_method') == \App\Enums\StatusEnum::TRUE->status() ? 'selected' : '' }}>
                          {{ translate("Android Gateway") }}
                        </option>
                      </select>

                      <label class="form-label small fw-medium">{{ translate("Specific Gateway") }}</label>
                      <select name="site_settings[api_sms_gateway_id]" class="form-select form-select-sm">
                        <option value="">{{ translate("Automatic") }}</option>
                        <optgroup label="{{ translate('API Gateways') }}">
                          @if(isset($gateways['sms_api']) && $gateways['sms_api']->count() > 0)
                            @foreach($gateways['sms_api'] as $gateway)
                              <option value="api_{{ $gateway->id }}" {{ site_settings('api_sms_gateway_id') == 'api_'.$gateway->id ? 'selected' : '' }}>
                                {{ $gateway->name }}
                              </option>
                            @endforeach
                          @endif
                        </optgroup>
                        <optgroup label="{{ translate('Android Gateways') }}">
                          @if(isset($gateways['android']) && $gateways['android']->count() > 0)
                            @foreach($gateways['android'] as $android)
                              <option value="android_{{ $android->id }}" {{ site_settings('api_sms_gateway_id') == 'android_'.$android->id ? 'selected' : '' }}>
                                {{ $android->simInfo->first()?->sim_number ?? $android->name ?? 'Android #'.$android->id }}
                              </option>
                            @endforeach
                          @endif
                        </optgroup>
                      </select>
                      <div class="gateway-status mt-2">
                        @php
                          $smsApiCount = isset($gateways['sms_api']) ? $gateways['sms_api']->count() : 0;
                          $androidCount = isset($gateways['android']) ? $gateways['android']->count() : 0;
                        @endphp
                        @if($smsApiCount + $androidCount > 0)
                          <span class="badge bg-success-subtle text-success"><i class="ri-checkbox-circle-line me-1"></i>{{ $smsApiCount }} API + {{ $androidCount }} Android</span>
                        @else
                          <span class="badge bg-warning-subtle text-warning"><i class="ri-error-warning-line me-1"></i>{{ translate("No gateways") }}</span>
                        @endif
                        <a href="{{ route('admin.gateway.sms.api.index') }}" class="badge bg-primary-subtle text-primary ms-1">{{ translate("Manage") }}</a>
                      </div>
                    </div>
                  </div>
                </div>

                {{-- Email Gateway --}}
                <div class="col-lg-4 col-md-6">
                  <div class="gateway-config-card email">
                    <div class="gateway-config-header">
                      <i class="ri-mail-line"></i>
                      <span>{{ translate("Email") }}</span>
                    </div>
                    <div class="gateway-config-body">
                      <label class="form-label small fw-medium">{{ translate("Default Gateway") }}</label>
                      <select name="site_settings[api_email_gateway_id]" class="form-select form-select-sm">
                        <option value="">{{ translate("Automatic (Use default)") }}</option>
                        @if(isset($gateways['email']) && $gateways['email']->count() > 0)
                          @foreach($gateways['email'] as $gateway)
                            <option value="{{ $gateway->id }}" {{ site_settings('api_email_gateway_id') == $gateway->id ? 'selected' : '' }}>
                              {{ $gateway->name }} ({{ ucfirst($gateway->type) }})
                            </option>
                          @endforeach
                        @endif
                      </select>
                      <div class="gateway-status mt-2">
                        @if(isset($gateways['email']) && $gateways['email']->count() > 0)
                          <span class="badge bg-success-subtle text-success"><i class="ri-checkbox-circle-line me-1"></i>{{ $gateways['email']->count() }} {{ translate("available") }}</span>
                        @else
                          <span class="badge bg-warning-subtle text-warning"><i class="ri-error-warning-line me-1"></i>{{ translate("No gateways") }}</span>
                        @endif
                        <a href="{{ route('admin.gateway.email.index') }}" class="badge bg-primary-subtle text-primary ms-1">{{ translate("Manage") }}</a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                <button type="submit" class="i-btn btn--primary btn--md">
                  <i class="ri-save-line me-1"></i>{{ translate("Save Preferences") }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    {{-- Channel Tabs --}}
    <ul class="nav nav-tabs api-channel-tabs" id="apiTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="email-tab" data-bs-toggle="tab" data-bs-target="#email-panel" type="button" role="tab">
          <i class="ri-mail-line"></i> {{ translate("Email") }}
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="sms-tab" data-bs-toggle="tab" data-bs-target="#sms-panel" type="button" role="tab">
          <i class="ri-message-2-line"></i> {{ translate("SMS") }}
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="whatsapp-tab" data-bs-toggle="tab" data-bs-target="#whatsapp-panel" type="button" role="tab">
          <i class="ri-whatsapp-line"></i> {{ translate("WhatsApp") }}
        </button>
      </li>
    </ul>

    {{-- Tab Content --}}
    <div class="tab-content" id="apiTabsContent">

      {{-- ==================== EMAIL TAB ==================== --}}
      <div class="tab-pane fade show active" id="email-panel" role="tabpanel">
        <div class="row g-4">
          {{-- Left Column --}}
          <div class="col-xl-4 col-lg-5">
            {{-- POST Endpoint --}}
            <div class="api-card api-card--email">
              <div class="api-card-header">
                <span class="badge bg-primary">POST</span>
                <strong>{{ translate("Send Email") }}</strong>
              </div>
              <div class="api-card-body">
                <div class="api-endpoint-url mb-3">
                  <code>{{ route('api.incoming.email.send') }}</code>
                </div>
                <h6 class="fw-bold mb-2 text-uppercase fs-11 text-muted">{{ translate("Parameters") }}</h6>
                <div class="api-param">
                  <code>email</code> <span class="text-danger">*</span>
                  <span class="text-muted">- {{ translate("Recipient email address") }}</span>
                </div>
                <div class="api-param">
                  <code>subject</code> <span class="text-danger">*</span>
                  <span class="text-muted">- {{ translate("Email subject line") }}</span>
                </div>
                <div class="api-param">
                  <code>message</code> <span class="text-danger">*</span>
                  <span class="text-muted">- {{ translate("Email body (HTML supported)") }}</span>
                </div>
                <div class="api-param">
                  <code>sender_name</code>
                  <span class="text-muted">- {{ translate("Custom sender name") }}</span>
                </div>
                <div class="api-param">
                  <code>schedule_at</code>
                  <span class="text-muted">- {{ translate("Schedule time (Y-m-d H:i:s)") }}</span>
                </div>
              </div>
            </div>

            {{-- GET Endpoint --}}
            <div class="api-card mt-3">
              <div class="api-card-header">
                <span class="badge bg-success">GET</span>
                <strong>{{ translate("Send via URL") }}</strong>
              </div>
              <div class="api-card-body">
                <div class="api-endpoint-url">
                  <code>{{ route('api.incoming.email.send.query') }}</code>
                </div>
                <div class="api-get-params mt-3">
                  <div class="fw-bold mb-2 text-uppercase fs-11 text-muted">{{ translate("Query Parameters") }}</div>
                  <div class="api-param"><code>contacts</code> <span class="text-muted">- {{ translate("Email addresses (comma separated)") }}</span></div>
                  <div class="api-param"><code>subject</code> <span class="text-muted">- {{ translate("Email subject") }}</span></div>
                  <div class="api-param"><code>message</code> <span class="text-muted">- {{ translate("Email body") }}</span></div>
                </div>
                <div class="api-param"><code>api_key</code> <span class="text-muted">- {{ translate("Your API key (alternative to header)") }}</span></div>
                <div class="api-example-url mt-3">
                  <div class="fw-bold mb-1 text-uppercase fs-11 text-muted">{{ translate("Example") }}</div>
                  <code class="d-block p-2 bg-light rounded small">{{ route('api.incoming.email.send.query') }}?api_key=YOUR_API_KEY&contacts=user@email.com&subject=Hello&message=Welcome</code>
                </div>
              </div>
            </div>

            {{-- Status Check --}}
            <div class="api-card mt-3">
              <div class="api-card-header">
                <span class="badge bg-info">GET</span>
                <strong>{{ translate("Check Status") }}</strong>
              </div>
              <div class="api-card-body py-3">
                <code>{{ url('/api/get/email/{message_id}') }}</code>
              </div>
            </div>
          </div>

          {{-- Right Column --}}
          <div class="col-xl-8 col-lg-7">
            {{-- Code Examples --}}
            <div class="api-code-card">
              <div class="api-code-tabs">
                <button class="active" data-lang="php">PHP cURL</button>
                <button data-lang="javascript">JavaScript</button>
                <button data-lang="python">Python</button>
                <button data-lang="nodejs">Node.js</button>
              </div>
              <div class="api-code-body">
                <button class="api-code-copy" title="{{ translate('Copy') }}"><i class="ri-file-copy-line"></i></button>
                <pre class="api-code active" data-lang="php"><code>&lt;?php
$curl = curl_init();

$data = [
    "contact" => [
        [
            "email" => "customer@example.com",
            "subject" => "Welcome to Our Service",
            "message" => "&lt;h1&gt;Hello!&lt;/h1&gt;&lt;p&gt;Thank you for signing up.&lt;/p&gt;"
        ]
    ]
];

curl_setopt_array($curl, [
    CURLOPT_URL => "{{ route('api.incoming.email.send') }}",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => [
        "Api-key: {{ $api_key ?: 'YOUR_API_KEY' }}",
        "Content-Type: application/json"
    ],
]);

$response = curl_exec($curl);
curl_close($curl);
echo $response;</code></pre>
                <pre class="api-code" data-lang="javascript"><code>const data = {
    contact: [{
        email: "customer@example.com",
        subject: "Welcome to Our Service",
        message: "&lt;h1&gt;Hello!&lt;/h1&gt;&lt;p&gt;Thank you for signing up.&lt;/p&gt;"
    }]
};

fetch("{{ route('api.incoming.email.send') }}", {
    method: "POST",
    headers: {
        "Api-key": "{{ $api_key ?: 'YOUR_API_KEY' }}",
        "Content-Type": "application/json"
    },
    body: JSON.stringify(data)
})
.then(response => response.json())
.then(result => console.log(result))
.catch(error => console.error("Error:", error));</code></pre>
                <pre class="api-code" data-lang="python"><code>import requests

url = "{{ route('api.incoming.email.send') }}"

payload = {
    "contact": [{
        "email": "customer@example.com",
        "subject": "Welcome to Our Service",
        "message": "&lt;h1&gt;Hello!&lt;/h1&gt;&lt;p&gt;Thank you for signing up.&lt;/p&gt;"
    }]
}

headers = {
    "Api-key": "{{ $api_key ?: 'YOUR_API_KEY' }}",
    "Content-Type": "application/json"
}

response = requests.post(url, json=payload, headers=headers)
print(response.json())</code></pre>
                <pre class="api-code" data-lang="nodejs"><code>const axios = require('axios');

const data = {
    contact: [{
        email: "customer@example.com",
        subject: "Welcome to Our Service",
        message: "&lt;h1&gt;Hello!&lt;/h1&gt;&lt;p&gt;Thank you for signing up.&lt;/p&gt;"
    }]
};

axios.post("{{ route('api.incoming.email.send') }}", data, {
    headers: {
        "Api-key": "{{ $api_key ?: 'YOUR_API_KEY' }}",
        "Content-Type": "application/json"
    }
})
.then(response => console.log(response.data))
.catch(error => console.error(error));</code></pre>
              </div>
            </div>

            {{-- Response --}}
            <div class="row g-3 mt-0">
              <div class="col-md-6">
                <div class="api-response-card api-response-card--success">
                  <div class="api-response-header">
                    <i class="ri-checkbox-circle-line text-success me-1"></i>{{ translate("Success Response") }}
                  </div>
                  <pre class="api-response-body"><code>{
    "status": "success",
    "message": "Email has been queued",
    "data": {
        "id": 12345,
        "status": "pending"
    }
}</code></pre>
                </div>
              </div>
              <div class="col-md-6">
                <div class="api-response-card api-response-card--error">
                  <div class="api-response-header">
                    <i class="ri-close-circle-line text-danger me-1"></i>{{ translate("Error Response") }}
                  </div>
                  <pre class="api-response-body"><code>{
    "status": "error",
    "message": "Invalid API key",
    "errors": {
        "api_key": ["Unauthorized"]
    }
}</code></pre>
                </div>
              </div>
            </div>

            {{-- Status & Error Codes --}}
            <div class="row g-3 mt-0">
              <div class="col-md-6">
                <div class="api-info-card">
                  <div class="api-info-header"><i class="ri-checkbox-circle-line text-success me-2"></i>{{ translate("Status Codes") }}</div>
                  <div class="api-info-body">
                    <span class="badge bg-warning">pending</span>
                    <span class="badge bg-info">processing</span>
                    <span class="badge bg-success">delivered</span>
                    <span class="badge bg-primary">schedule</span>
                    <span class="badge bg-danger">fail</span>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="api-info-card">
                  <div class="api-info-header"><i class="ri-error-warning-line text-danger me-2"></i>{{ translate("HTTP Errors") }}</div>
                  <div class="api-info-body">
                    <code>401</code> {{ translate("Invalid API Key") }} &bull;
                    <code>422</code> {{ translate("Validation Error") }} &bull;
                    <code>400</code> {{ translate("Bad Request") }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- ==================== SMS TAB ==================== --}}
      <div class="tab-pane fade" id="sms-panel" role="tabpanel">
        <div class="row g-4">
          {{-- Left Column --}}
          <div class="col-xl-4 col-lg-5">
            {{-- POST Endpoint --}}
            <div class="api-card api-card--sms">
              <div class="api-card-header">
                <span class="badge bg-primary">POST</span>
                <strong>{{ translate("Send SMS") }}</strong>
              </div>
              <div class="api-card-body">
                <div class="api-endpoint-url mb-3">
                  <code>{{ route('api.incoming.sms.send') }}</code>
                </div>
                <h6 class="fw-bold mb-2 text-uppercase fs-11 text-muted">{{ translate("Parameters") }}</h6>
                <div class="api-param">
                  <code>number</code> <span class="text-danger">*</span>
                  <span class="text-muted">- {{ translate("Phone with country code (e.g., 8801712345678)") }}</span>
                </div>
                <div class="api-param">
                  <code>body</code> <span class="text-danger">*</span>
                  <span class="text-muted">- {{ translate("SMS message content") }}</span>
                </div>
                <div class="api-param">
                  <code>sms_type</code>
                  <span class="text-muted">- {{ translate("plain or unicode") }}</span>
                </div>
                <div class="api-param">
                  <code>schedule_at</code>
                  <span class="text-muted">- {{ translate("Schedule time (Y-m-d H:i:s)") }}</span>
                </div>
              </div>
            </div>

            {{-- GET Endpoint --}}
            <div class="api-card mt-3">
              <div class="api-card-header">
                <span class="badge bg-success">GET</span>
                <strong>{{ translate("Send via URL") }}</strong>
              </div>
              <div class="api-card-body">
                <div class="api-endpoint-url">
                  <code>{{ route('api.incoming.sms.send.query') }}</code>
                </div>
                <div class="api-get-params mt-3">
                  <div class="fw-bold mb-2 text-uppercase fs-11 text-muted">{{ translate("Query Parameters") }}</div>
                  <div class="api-param"><code>contacts</code> <span class="text-muted">- {{ translate("Phone numbers (comma separated)") }}</span></div>
                  <div class="api-param"><code>message</code> <span class="text-muted">- {{ translate("SMS message") }}</span></div>
                  <div class="api-param"><code>sms_type</code> <span class="text-muted">- {{ translate("plain or unicode") }}</span></div>
                  <div class="api-param"><code>api_key</code> <span class="text-muted">- {{ translate("Your API key (alternative to header)") }}</span></div>
                </div>
                <div class="api-example-url mt-3">
                  <div class="fw-bold mb-1 text-uppercase fs-11 text-muted">{{ translate("Example") }}</div>
                  <code class="d-block p-2 bg-light rounded small">{{ route('api.incoming.sms.send.query') }}?api_key=YOUR_API_KEY&contacts=8801712345678&message=Hello&sms_type=plain</code>
                </div>
              </div>
            </div>

            {{-- Gateway Auto-Detection Info --}}
            <div class="api-card mt-3">
              <div class="api-card-header">
                <i class="ri-magic-line me-2 text-primary"></i>
                <strong>{{ translate("Smart Gateway Detection") }}</strong>
              </div>
              <div class="api-card-body py-3">
                <p class="small text-muted mb-0">
                  <i class="ri-checkbox-circle-fill text-success me-1"></i>
                  {{ translate("The system automatically detects your available gateways (Android or API) and uses the appropriate one. No manual configuration needed!") }}
                </p>
              </div>
            </div>

            {{-- Status Check --}}
            <div class="api-card mt-3">
              <div class="api-card-header">
                <span class="badge bg-info">GET</span>
                <strong>{{ translate("Check Status") }}</strong>
              </div>
              <div class="api-card-body py-3">
                <code>{{ url('/api/get/sms/{message_id}') }}</code>
              </div>
            </div>
          </div>

          {{-- Right Column --}}
          <div class="col-xl-8 col-lg-7">
            {{-- Code Examples --}}
            <div class="api-code-card">
              <div class="api-code-tabs">
                <button class="active" data-lang="php">PHP cURL</button>
                <button data-lang="javascript">JavaScript</button>
                <button data-lang="python">Python</button>
                <button data-lang="nodejs">Node.js</button>
              </div>
              <div class="api-code-body">
                <button class="api-code-copy" title="{{ translate('Copy') }}"><i class="ri-file-copy-line"></i></button>
                <pre class="api-code active" data-lang="php"><code>&lt;?php
$curl = curl_init();

$data = [
    "contact" => [
        [
            "number" => "8801712345678",
            "body" => "Your verification code is: 123456",
            "sms_type" => "plain"
        ]
    ]
];

curl_setopt_array($curl, [
    CURLOPT_URL => "{{ route('api.incoming.sms.send') }}",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => [
        "Api-key: {{ $api_key ?: 'YOUR_API_KEY' }}",
        "Content-Type: application/json"
    ],
]);

$response = curl_exec($curl);
curl_close($curl);
echo $response;</code></pre>
                <pre class="api-code" data-lang="javascript"><code>const data = {
    contact: [{
        number: "8801712345678",
        body: "Your verification code is: 123456",
        sms_type: "plain"
    }]
};

fetch("{{ route('api.incoming.sms.send') }}", {
    method: "POST",
    headers: {
        "Api-key": "{{ $api_key ?: 'YOUR_API_KEY' }}",
        "Content-Type": "application/json"
    },
    body: JSON.stringify(data)
})
.then(response => response.json())
.then(result => console.log(result));</code></pre>
                <pre class="api-code" data-lang="python"><code>import requests

url = "{{ route('api.incoming.sms.send') }}"

payload = {
    "contact": [{
        "number": "8801712345678",
        "body": "Your verification code is: 123456",
        "sms_type": "plain"
    }]
}

headers = {
    "Api-key": "{{ $api_key ?: 'YOUR_API_KEY' }}",
    "Content-Type": "application/json"
}

response = requests.post(url, json=payload, headers=headers)
print(response.json())</code></pre>
                <pre class="api-code" data-lang="nodejs"><code>const axios = require('axios');

const data = {
    contact: [{
        number: "8801712345678",
        body: "Your verification code is: 123456",
        sms_type: "plain"
    }]
};

axios.post("{{ route('api.incoming.sms.send') }}", data, {
    headers: {
        "Api-key": "{{ $api_key ?: 'YOUR_API_KEY' }}",
        "Content-Type": "application/json"
    }
})
.then(response => console.log(response.data))
.catch(error => console.error(error));</code></pre>
              </div>
            </div>

            {{-- Response --}}
            <div class="row g-3 mt-0">
              <div class="col-md-6">
                <div class="api-response-card api-response-card--success">
                  <div class="api-response-header">
                    <i class="ri-checkbox-circle-line text-success me-1"></i>{{ translate("Success Response") }}
                  </div>
                  <pre class="api-response-body"><code>{
    "status": "success",
    "message": "SMS has been queued",
    "data": {
        "id": 12345,
        "status": "pending"
    }
}</code></pre>
                </div>
              </div>
              <div class="col-md-6">
                <div class="api-response-card api-response-card--error">
                  <div class="api-response-header">
                    <i class="ri-close-circle-line text-danger me-1"></i>{{ translate("Error Response") }}
                  </div>
                  <pre class="api-response-body"><code>{
    "status": "error",
    "message": "Invalid API key",
    "errors": {
        "api_key": ["Unauthorized"]
    }
}</code></pre>
                </div>
              </div>
            </div>

            {{-- Status & Error Codes --}}
            <div class="row g-3 mt-0">
              <div class="col-md-6">
                <div class="api-info-card">
                  <div class="api-info-header"><i class="ri-checkbox-circle-line text-success me-2"></i>{{ translate("Status Codes") }}</div>
                  <div class="api-info-body">
                    <span class="badge bg-warning">pending</span>
                    <span class="badge bg-info">processing</span>
                    <span class="badge bg-success">delivered</span>
                    <span class="badge bg-primary">schedule</span>
                    <span class="badge bg-danger">fail</span>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="api-info-card">
                  <div class="api-info-header"><i class="ri-error-warning-line text-danger me-2"></i>{{ translate("HTTP Errors") }}</div>
                  <div class="api-info-body">
                    <code>401</code> {{ translate("Invalid API Key") }} &bull;
                    <code>422</code> {{ translate("Validation Error") }} &bull;
                    <code>400</code> {{ translate("Bad Request") }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- ==================== WHATSAPP TAB ==================== --}}
      <div class="tab-pane fade" id="whatsapp-panel" role="tabpanel">
        <div class="row g-4">
          {{-- Left Column --}}
          <div class="col-xl-4 col-lg-5">
            {{-- POST Endpoint --}}
            <div class="api-card api-card--whatsapp">
              <div class="api-card-header">
                <span class="badge bg-primary">POST</span>
                <strong>{{ translate("Send WhatsApp") }}</strong>
              </div>
              <div class="api-card-body">
                <div class="api-endpoint-url mb-3">
                  <code>{{ route('api.incoming.whatsapp.send') }}</code>
                </div>
                <h6 class="fw-bold mb-2 text-uppercase fs-11 text-muted">{{ translate("Parameters") }}</h6>
                <div class="api-param">
                  <code>number</code> <span class="text-danger">*</span>
                  <span class="text-muted">- {{ translate("Phone with country code") }}</span>
                </div>
                <div class="api-param">
                  <code>message</code> <span class="text-danger">*</span>
                  <span class="text-muted">- {{ translate("Message text (*bold*, _italic_, ~strike~)") }}</span>
                </div>
                <div class="api-param">
                  <code>media</code>
                  <span class="text-muted">- {{ translate("image, audio, video, document") }}</span>
                </div>
                <div class="api-param">
                  <code>url</code>
                  <span class="text-muted">- {{ translate("Public URL of media file") }}</span>
                </div>
                <div class="api-param">
                  <code>schedule_at</code>
                  <span class="text-muted">- {{ translate("Schedule time (Y-m-d H:i:s)") }}</span>
                </div>
              </div>
            </div>

            {{-- Media Types --}}
            <div class="api-card mt-3">
              <div class="api-card-header">
                <i class="ri-attachment-line me-2"></i>
                <strong>{{ translate("Supported Media") }}</strong>
              </div>
              <div class="api-card-body py-3">
                <table class="table table-sm table-borderless mb-0 small">
                  <tr><td><code>image</code></td><td>JPG, PNG, WEBP</td><td class="text-muted">5 MB</td></tr>
                  <tr><td><code>audio</code></td><td>MP3, OGG, AMR</td><td class="text-muted">16 MB</td></tr>
                  <tr><td><code>video</code></td><td>MP4, 3GP</td><td class="text-muted">16 MB</td></tr>
                  <tr><td><code>document</code></td><td>PDF, DOC, XLS</td><td class="text-muted">100 MB</td></tr>
                </table>
              </div>
            </div>

            {{-- GET Endpoint --}}
            <div class="api-card mt-3">
              <div class="api-card-header">
                <span class="badge bg-success">GET</span>
                <strong>{{ translate("Send via URL") }}</strong>
              </div>
              <div class="api-card-body">
                <div class="api-endpoint-url">
                  <code>{{ route('api.incoming.whatsapp.send.query') }}</code>
                </div>
                <div class="api-get-params mt-3">
                  <div class="fw-bold mb-2 text-uppercase fs-11 text-muted">{{ translate("Query Parameters") }}</div>
                  <div class="api-param"><code>contacts</code> <span class="text-muted">- {{ translate("Phone numbers (comma separated)") }}</span></div>
                  <div class="api-param"><code>message</code> <span class="text-muted">- {{ translate("Message text") }}</span></div>
                  <div class="api-param"><code>api_key</code> <span class="text-muted">- {{ translate("Your API key (alternative to header)") }}</span></div>
                </div>
                <div class="api-example-url mt-3">
                  <div class="fw-bold mb-1 text-uppercase fs-11 text-muted">{{ translate("Example") }}</div>
                  <code class="d-block p-2 bg-light rounded small">{{ route('api.incoming.whatsapp.send.query') }}?api_key=YOUR_API_KEY&contacts=8801712345678&message=Hello</code>
                </div>
              </div>
            </div>

            {{-- Status Check --}}
            <div class="api-card mt-3">
              <div class="api-card-header">
                <span class="badge bg-info">GET</span>
                <strong>{{ translate("Check Status") }}</strong>
              </div>
              <div class="api-card-body py-3">
                <code>{{ url('/api/get/whatsapp/{message_id}') }}?api_key=YOUR_API_KEY</code>
              </div>
            </div>
          </div>

          {{-- Right Column --}}
          <div class="col-xl-8 col-lg-7">
            {{-- Code Examples --}}
            <div class="api-code-card">
              <div class="api-code-tabs">
                <button class="active" data-lang="php">PHP cURL</button>
                <button data-lang="javascript">JavaScript</button>
                <button data-lang="python">Python</button>
                <button data-lang="nodejs">Node.js</button>
              </div>
              <div class="api-code-body">
                <button class="api-code-copy" title="{{ translate('Copy') }}"><i class="ri-file-copy-line"></i></button>
                <pre class="api-code active" data-lang="php"><code>&lt;?php
$curl = curl_init();

// Text message
$data = [
    "contact" => [
        [
            "number" => "8801712345678",
            "message" => "Hello! Your order #12345 has been shipped."
        ]
    ]
];

// With document attachment
$dataWithMedia = [
    "contact" => [
        [
            "number" => "8801712345678",
            "message" => "Here is your invoice",
            "media" => "document",
            "url" => "https://example.com/invoice.pdf"
        ]
    ]
];

curl_setopt_array($curl, [
    CURLOPT_URL => "{{ route('api.incoming.whatsapp.send') }}",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => [
        "Api-key: {{ $api_key ?: 'YOUR_API_KEY' }}",
        "Content-Type: application/json"
    ],
]);

$response = curl_exec($curl);
curl_close($curl);
echo $response;</code></pre>
                <pre class="api-code" data-lang="javascript"><code>// Text message
const data = {
    contact: [{
        number: "8801712345678",
        message: "Hello! Your order #12345 has been shipped."
    }]
};

// With image attachment
const dataWithMedia = {
    contact: [{
        number: "8801712345678",
        message: "Check out this image!",
        media: "image",
        url: "https://example.com/photo.jpg"
    }]
};

fetch("{{ route('api.incoming.whatsapp.send') }}", {
    method: "POST",
    headers: {
        "Api-key": "{{ $api_key ?: 'YOUR_API_KEY' }}",
        "Content-Type": "application/json"
    },
    body: JSON.stringify(data)
})
.then(response => response.json())
.then(result => console.log(result));</code></pre>
                <pre class="api-code" data-lang="python"><code>import requests

url = "{{ route('api.incoming.whatsapp.send') }}"

# Text message
payload = {
    "contact": [{
        "number": "8801712345678",
        "message": "Hello! Your order #12345 has been shipped."
    }]
}

# With document attachment
payload_with_media = {
    "contact": [{
        "number": "8801712345678",
        "message": "Here is your invoice",
        "media": "document",
        "url": "https://example.com/invoice.pdf"
    }]
}

headers = {
    "Api-key": "{{ $api_key ?: 'YOUR_API_KEY' }}",
    "Content-Type": "application/json"
}

response = requests.post(url, json=payload, headers=headers)
print(response.json())</code></pre>
                <pre class="api-code" data-lang="nodejs"><code>const axios = require('axios');

// Text message
const data = {
    contact: [{
        number: "8801712345678",
        message: "Hello! Your order #12345 has been shipped."
    }]
};

// With video attachment
const dataWithMedia = {
    contact: [{
        number: "8801712345678",
        message: "Watch this video!",
        media: "video",
        url: "https://example.com/video.mp4"
    }]
};

axios.post("{{ route('api.incoming.whatsapp.send') }}", data, {
    headers: {
        "Api-key": "{{ $api_key ?: 'YOUR_API_KEY' }}",
        "Content-Type": "application/json"
    }
})
.then(response => console.log(response.data))
.catch(error => console.error(error));</code></pre>
              </div>
            </div>

            {{-- Response --}}
            <div class="row g-3 mt-0">
              <div class="col-md-6">
                <div class="api-response-card api-response-card--success">
                  <div class="api-response-header">
                    <i class="ri-checkbox-circle-line text-success me-1"></i>{{ translate("Success Response") }}
                  </div>
                  <pre class="api-response-body"><code>{
    "status": "success",
    "message": "WhatsApp message queued",
    "data": {
        "id": 12345,
        "status": "pending"
    }
}</code></pre>
                </div>
              </div>
              <div class="col-md-6">
                <div class="api-response-card api-response-card--error">
                  <div class="api-response-header">
                    <i class="ri-close-circle-line text-danger me-1"></i>{{ translate("Error Response") }}
                  </div>
                  <pre class="api-response-body"><code>{
    "status": "error",
    "message": "Invalid API key",
    "errors": {
        "api_key": ["Unauthorized"]
    }
}</code></pre>
                </div>
              </div>
            </div>

            {{-- Status & Error Codes --}}
            <div class="row g-3 mt-0">
              <div class="col-md-6">
                <div class="api-info-card">
                  <div class="api-info-header"><i class="ri-checkbox-circle-line text-success me-2"></i>{{ translate("Status Codes") }}</div>
                  <div class="api-info-body">
                    <span class="badge bg-warning">pending</span>
                    <span class="badge bg-info">processing</span>
                    <span class="badge bg-success">delivered</span>
                    <span class="badge bg-primary">schedule</span>
                    <span class="badge bg-danger">fail</span>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="api-info-card">
                  <div class="api-info-header"><i class="ri-error-warning-line text-danger me-2"></i>{{ translate("HTTP Errors") }}</div>
                  <div class="api-info-body">
                    <code>401</code> {{ translate("Invalid API Key") }} &bull;
                    <code>422</code> {{ translate("Validation Error") }} &bull;
                    <code>400</code> {{ translate("Bad Request") }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</main>
@endsection

@push('style-push')
<style>
/* Compact Sticky Header */
.api-sticky-header {
  position: sticky;
  top: 0;
  z-index: 100;
  background: #fff;
  padding: 12px 20px;
  margin: -20px -20px 20px -20px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}
[data-bs-theme="dark"] .api-sticky-header {
  background: var(--card-bg);
}
.api-base-url {
  background: var(--color-primary-light, #e8f4fd);
  color: var(--color-primary, #0d6efd);
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 500;
}
.api-key-group {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #f8f9fa;
  padding: 6px 10px;
  border-radius: 6px;
  border: 1px solid var(--color-border);
}
[data-bs-theme="dark"] .api-key-group {
  background: var(--card-bg);
  color: #fff;

}
.api-key-label {
  font-size: 12px;
  font-weight: 600;
  color: #6c757d;
  white-space: nowrap;
}
.api-key-label i {
  color: var(--color-primary, #0d6efd);
  margin-right: 4px;
}
.api-key-input {
  background: #fff;
  border: 1px solid var(--color-border);
  border-radius: 4px;
  padding: 6px 10px;
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  font-size: 12px;
  color: #495057;
  width: 280px;
  outline: none;
}
[data-bs-theme="dark"] .api-key-input {
  background: var(--card-bg);
  color: #fff;
}
.api-key-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 4px;
  border: 1px solid #dee2e6;
  background: #fff;
  color: #6c757d;
  cursor: pointer;
  transition: all 0.2s;
}
.api-key-btn:hover {
  background: var(--color-primary, #0d6efd);
  border-color: var(--color-primary, #0d6efd);
  color: #fff;
}
@media (max-width: 768px) {
  .api-sticky-header > div { flex-direction: column; align-items: flex-start !important; }
  .api-key-group { width: 100%; flex-wrap: wrap; }
  .api-key-input { flex: 1; min-width: 150px; }
}

/* Channel Tabs */
.api-channel-tabs {
  border-bottom: 2px solid #e9ecef;
  margin-bottom: 24px;
}
.api-channel-tabs .nav-link {
  border: none;
  color: #6c757d;
  font-weight: 500;
  padding: 12px 24px;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: all 0.2s;
}
.api-channel-tabs .nav-link i { margin-right: 8px; font-size: 18px; }
.api-channel-tabs .nav-link:hover { color: var(--color-primary); }
.api-channel-tabs .nav-link.active {
  color: var(--color-primary);
  background: transparent;
  border-bottom-color: var(--color-primary);
}

/* API Card */
.api-card {
  background: var(--card-bg);
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  overflow: hidden;
}
.api-card--email { border-left: 4px solid #dc3545; }
.api-card--sms { border-left: 4px solid #0dcaf0; }
.api-card--whatsapp { border-left: 4px solid #25d366; }

.api-card-header {
  background: var(--card-bg);
  padding: 12px 16px;
  border-bottom: 1px solid #e9ecef;
  display: flex;
  align-items: center;
  gap: 10px;
}
.api-card-header strong{
  color: var(--text-primary);
}
.api-card-body { padding: 16px; }

/* Endpoint URL */
.api-endpoint-url {
  background: #f8f9fa;
  padding: 10px 12px;
  border-radius: 6px;
  border: 1px solid #e9ecef;
}
.api-endpoint-url code {
  font-size: 12px;
  color: #495057;
  word-break: break-all;
}

/* Parameters */
.api-param {
  padding: 8px 0;
  border-bottom: 1px solid #f1f3f4;
  font-size: 13px;
}
.api-param:last-child { border-bottom: none; }
.api-param code {
  background: #e9ecef;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

/* Code Card */
.api-code-card {
  background: #1e1e1e;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.api-code-tabs {
  display: flex;
  background: #2d2d2d;
  border-bottom: 1px solid #404040;
  overflow-x: auto;
}
.api-code-tabs button {
  background: none;
  border: none;
  color: #888;
  padding: 10px 16px;
  font-size: 13px;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s;
}
.api-code-tabs button:hover { color: #fff; }
.api-code-tabs button.active {
  color: #fff;
  background: #1e1e1e;
  border-bottom: 2px solid var(--color-primary);
}
.api-code-body {
  position: relative;
  padding: 16px;
  max-height: 420px;
  overflow: auto;
}
.api-code-copy {
  position: absolute;
  top: 12px;
  right: 12px;
  background: #404040;
  border: none;
  color: #888;
  padding: 6px 10px;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
  z-index: 10;
}
.api-code-copy:hover { background: #505050; color: #fff; }
.api-code { display: none; margin: 0; }
.api-code.active { display: block; }
.api-code code {
  color: #d4d4d4;
  font-size: 13px;
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  line-height: 1.5;
}

/* Response Cards */
.api-response-card {
  background: var(--card-bg);
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid var(--color-border);
}
.api-response-card--success { border-left: 3px solid #198754; }
.api-response-card--error { border-left: 3px solid #dc3545; }
.api-response-header {
  padding: 10px 14px;
  background: var(--card-bg);
  border-bottom: 1px solid var(--color-border);
  font-size: 13px;
  font-weight: 500;
}
.api-response-body {
  margin: 0;
  padding: 12px 14px;
  background: var(--card-bg);
  font-size: 12px;
  max-height: 180px;
  overflow: auto;
}
.api-response-card--success .api-response-body code { color: #198754; }
.api-response-card--error .api-response-body code { color: #dc3545; }

/* Info Cards */
.api-info-card {
  background: #fff;
  border-radius: 8px;
  border: 1px solid var(--color-border);
  overflow: hidden;
}
.api-info-header {
  padding: 10px 14px;
  background: var(--card-bg);
  border-bottom: 1px solid #e9ecef;
  font-size: 13px;
  font-weight: 500;
}
.api-info-body {
  padding: 12px 14px;
  background: var(--card-bg);
  font-size: 12px;
}
.api-info-body code {
  background: #fee2e2;
  color: #dc3545;
  padding: 2px 6px;
  border-radius: 4px;
  font-weight: 600;
  margin-right: 2px;
}

/* Utilities */
.fs-11 { font-size: 11px; }

/* Gateway Settings Card */
.api-gateway-settings .card {
  border: 1px solid var(--color-border);
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.api-gateway-settings .card-header {
  background: #fff;
  border-bottom: 1px solid var(--color-border);
  padding: 14px 20px;
}
[data-bs-theme="dark"] .api-gateway-settings .card-header {
  background: var(--card-bg);
}
.api-gateway-settings .card-body {
  padding: 20px;
}

/* Gateway Config Cards */
.gateway-config-card {
  background: #fff;
  border-radius: 10px;
  border: 1px solid #e9ecef;
  overflow: hidden;
  transition: all 0.2s ease;
}
.gateway-config-card:hover {
  border-color: #dee2e6;
  box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}
.gateway-config-header {
  padding: 12px 16px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 600;
  font-size: 14px;
}
.gateway-config-header i {
  font-size: 20px;
}
.gateway-config-card.whatsapp .gateway-config-header {
  background: linear-gradient(135deg, #dcf8c6 0%, #e8f5e9 100%);
  color: #128c7e;
}
.gateway-config-card.whatsapp .gateway-config-header i { color: #25d366; }
.gateway-config-card.sms .gateway-config-header {
  background: linear-gradient(135deg, #e3f2fd 0%, #e8f4fc 100%);
  color: #0288d1;
}
.gateway-config-card.sms .gateway-config-header i { color: #0dcaf0; }
.gateway-config-card.email .gateway-config-header {
  background: linear-gradient(135deg, #fce4ec 0%, #fef5f7 100%);
  color: #c62828;
}
.gateway-config-card.email .gateway-config-header i { color: #dc3545; }
.gateway-config-body {
  padding: 16px;
  background: var(--card-bg);
}
.gateway-config-body .form-select {
  font-size: 13px;
}
.gateway-status {
  font-size: 12px;
}
.gateway-status .badge {
  font-weight: 500;
}
</style>
@endpush

@push('script-push')
<script>
"use strict";
$(document).ready(function() {
    // Copy to clipboard function (works on HTTP and HTTPS)
    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text);
        } else {
            var textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            textArea.style.top = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try { document.execCommand('copy'); } catch (err) { console.error('Copy failed', err); }
            textArea.remove();
        }
    }

    // Generate API Key
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    $('.generate-api-key').on('click', function() {
        var apiKey = generateUUID();
        $('#api_key').val(apiKey);
        $.ajax({
            type: "GET",
            url: "{{ route('admin.communication.api') }}",
            data: { _token: "{{ csrf_token() }}", api_key: apiKey },
            success: function(response) { notify(response.status, response.message); }
        });
    });

    // Copy API Key
    $('#copy_api_key').on('click', function() {
        copyToClipboard($('#api_key').val());
        notify('success', '{{ translate("API Key copied!") }}');
    });

    // Language Tab Switching
    $('.api-code-tabs button').on('click', function() {
        const lang = $(this).data('lang');
        const container = $(this).closest('.api-code-card');
        container.find('.api-code-tabs button').removeClass('active');
        $(this).addClass('active');
        container.find('.api-code').removeClass('active');
        container.find('.api-code[data-lang="' + lang + '"]').addClass('active');
    });

    // Copy Code
    $('.api-code-copy').on('click', function() {
        const code = $(this).closest('.api-code-body').find('.api-code.active code').text();
        copyToClipboard(code);
        notify('success', '{{ translate("Code copied!") }}');
    });
});
</script>
@endpush

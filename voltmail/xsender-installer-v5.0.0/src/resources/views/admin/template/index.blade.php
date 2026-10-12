@push("style-include")
  <link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
@endpush
@extends('admin.layouts.app')
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
                <a href="{{ route("admin.dashboard") }}">{{ translate("dashboard") }}</a>
              </li>
              <li class="breadcrumb-item active" aria-current="page"> {{ $title }} </li>
            </ol>
          </nav>
        </div>
      </div>
    </div>
    @if(request()->channel == \App\Enums\System\ChannelTypeEnum::SMS->value)
      <div class="pill-tab mb-4">
        <ul class="nav" role="tablist">
          <li class="nav-item" role="presentation">
            <a class="nav-link active" data-bs-toggle="tab" href="#admintemplate" role="tab" aria-selected="true">
              <i class="ri-user-settings-line"></i> {{ translate("Admin Templates") }} </a>
          </li>
          <li class="nav-item" role="presentation">
            <a class="nav-link" data-bs-toggle="tab" href="#user_template" role="tab" aria-selected="false" tabindex="-1">
              <i class="ri-user-line"></i> {{ translate("User Templates") }} </a>
          </li>
        </ul>
      </div>

      <div class="tab-content">
        <div class="tab-pane active fade show" id="admintemplate" role="tabpanel">
          <div class="card">
            <div class="card-header">
              <div class="card-header-left">
                <h4 class="card-title">{{ translate("Admin Template") }}</h4>
              </div>
                <div class="card-header-right">
                  <button class="i-btn btn--primary btn--sm add-sms-template" type="button" data-bs-toggle="modal" data-bs-target="#addSmsTemplate">
                    <i class="ri-add-fill fs-16"></i> {{ translate("Create") }}
                  </button>
                </div>
            </div>
            <div class="card-body px-0 pt-0">
              <div class="table-container">
                <table>
                  <thead>
                    <tr>
                      <th scope="col">{{ translate("Name") }}</th>
                      <th scope="col">{{ translate("Status") }}</th>
                      <th scope="col">{{ translate("Created At") }}</th>
                      <th scope="col">{{ translate("Option") }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($adminTemplates as $template)
                      <tr>
                        <td>
                          <span class="fw-semibold text-dark">{{ $template->name }}</span>
                        </td>
                        <td data-label="{{ translate('Status')}}">
                          <div class="switch-wrapper checkbox-data">
                              <input {{ $template->status->value == App\Enums\Common\Status::ACTIVE->value ? 'checked' : '' }}
                                      type="checkbox"
                                      class="switch-input statusUpdateByUID"
                                      data-uid="{{ $template->uid }}"
                                      data-column="status"
                                      data-value="{{ 
                                        @$template?->status->value == \App\Enums\Common\Status::ACTIVE->value
                                        ? \App\Enums\Common\Status::INACTIVE->value
                                        : \App\Enums\Common\Status::ACTIVE->value}}"
                                      data-route="{{route('admin.template.status.update')}}"
                                      id="{{ 'status_'.$template->uid }}"
                                      name="status"/>
                              <label for="{{ 'status_'.$template->uid }}" class="toggle">
                                  <span></span>
                              </label>
                          </div>
                        </td>
                      <td>
                        {{ $template->created_at->toDayDateTimeString() }}
                      </td>
                      <td data-label={{ translate('Option')}}>
                        <div class="d-flex align-items-center gap-1">
                            <button class="icon-btn btn-ghost btn-sm success-soft circle edit-sms-template"
                                    type="button"
                                    data-template-url="{{ route("admin.template.update", ["uid" => $template->uid]) }}"
                                    data-template-name="{{ $template->name }}"
                                    data-template-message="{{ $template->template_data["message"] }}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editSmsTemplate">
                                <i class="ri-edit-line"></i>
                                <span class="tooltiptext"> {{ translate("Edit Template") }} </span>
                            </button>
                            <button class="icon-btn btn-ghost btn-sm danger-soft circle text-danger delete-admin-sms-template"
                                    type="button"
                                    data-template-url="{{ route("admin.template.update", ["uid" => $template->uid]) }}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#deleteAdminSmsTemplate">
                                <i class="ri-delete-bin-line"></i>
                                <span class="tooltiptext"> {{ translate("Delete template") }} </span>
                            </button>
                        </div>
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

              @include('admin.partials.pagination', ['paginator' => $adminTemplates])
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="user_template" role="tabpanel">
          <div class="card">
            <div class="card-header">
              <div class="card-header-left">
                <h4 class="card-title">{{ translate("User templates") }}</h4>
              </div>
            </div>
            <div class="card-body px-0 pt-0">
              <div class="table-container">
                <table>
                  <thead>
                    <tr>
                      <th scope="col">{{ translate("Name") }}</th>
                      <th scope="col">{{ translate("Approval Status") }}</th>
                      <th scope="col">{{ translate("Created At") }}</th>
                      <th scope="col">{{ translate("Option") }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($userTemplates as $user_template)
                    
                    <tr>
                      <td>
                        <span class="fw-semibold text-dark">{{ $user_template->name }}</span>
                      </td>
                      <td data-label="{{ translate('Approval Status')}}">
                        {{ @$user_template?->approval_status ? $user_template->approval_status?->badge() : translate("N/A") }}
                      </td>
                      <td>
                        {{ $user_template->created_at->toDayDateTimeString() }}
                      </td>
                      <td data-label={{ translate('Option')}}>
                        <div class="d-flex align-items-center gap-1">
                          <button class="icon-btn btn-ghost btn-sm success-soft circle approve-sms-template"
                                  type="button"
                                  data-template-uid="{{ $user_template->uid }}"
                                  data-bs-toggle="modal"
                                  data-bs-target="#smsTemplateApproval">
                              <i class="ri-edit-line"></i>
                              <span class="tooltiptext"> {{ translate("Approve Template") }} </span>
                          </button>
                            <button class="icon-btn btn-ghost btn-sm danger-soft circle text-danger delete-user-sms-template"
                                    type="button"
                                    data-template-url="{{ route("admin.template.update", ["uid" => $user_template->uid]) }}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#deleteUserSmsTemplate">
                                <i class="ri-delete-bin-line"></i>
                                <span class="tooltiptext"> {{ translate("Delete template") }} </span>
                            </button>
                        </div>
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

              @include('admin.partials.pagination', ['paginator' => $userTemplates])
            </div>
          </div>
        </div>
      </div>
    @endif

    @if(request()->channel == \App\Enums\System\ChannelTypeEnum::EMAIL->value)
      <div class="pill-tab mb-4">
        <ul class="nav" role="tablist">
          <li class="nav-item" role="presentation">
            <a class="nav-link active" data-bs-toggle="tab" href="#admintemplate" role="tab" aria-selected="true">
              <i class="ri-user-settings-line"></i> {{ translate("Admin Templates") }} </a>
          </li>
          <li class="nav-item" role="presentation">
            <a class="nav-link" data-bs-toggle="tab" href="#user_template" role="tab" aria-selected="false" tabindex="-1">
              <i class="ri-user-line"></i> {{ translate("User Templates") }} </a>
          </li>
          <li class="nav-item" role="presentation">
            <a class="nav-link" data-bs-toggle="tab" href="#default_templates" aria-selected="false" tabindex="-1">
              <i class="ri-user-settings-line"></i> {{ translate("Default Templates") }} </a>
          </li>
          <li class="nav-item" role="presentation">
            <a class="nav-link" data-bs-toggle="tab" href="#global_template" role="tab" aria-selected="false" tabindex="-1">
              <i class="ri-user-line"></i> {{ translate("Global Template") }} </a>
          </li>
        </ul>
      </div>

      <div class="tab-content">
        <div class="tab-pane active fade show" id="admintemplate" role="tabpanel">
          <div class="card">
            <div class="card-header">
              <div class="card-header-left">
                <h4 class="card-title">{{ translate("Admin Email Templates") }}</h4>
              </div>
                <div class="card-header-right">
                  <a class="i-btn btn--primary btn--sm" href="{{ route("admin.template.create", ["channel" => $channel->value]) }}">
                    <i class="ri-add-fill fs-16"></i> {{ translate("Create") }}
                  </a>
                </div>
            </div>
            <div class="card-body px-0 pt-0">
              <div class="table-container">
                <table>
                  <thead>
                    <tr>
                      <th scope="col">{{ translate("Name") }}</th>
                      <th scope="col">{{ translate("Provider") }}</th>
                      <th scope="col">{{ translate("Status") }}</th>
                      <th scope="col">{{ translate("Created At") }}</th>
                      <th scope="col">{{ translate("Option") }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($adminTemplates as $template)
                      <tr>
                        <td>
                          <span class="fw-semibold text-dark">{{ $template->name }}</span>
                        </td>
                        <td>
                          {{ $template->provider->badge() }}
                        </td>
                        <td data-label="{{ translate('Status')}}">
                          <div class="switch-wrapper checkbox-data">
                              <input {{ $template->status->value == App\Enums\Common\Status::ACTIVE->value ? 'checked' : '' }}
                                      type="checkbox"
                                      class="switch-input statusUpdateByUID"
                                      data-uid="{{ $template->uid }}"
                                      data-column="status"
                                      data-value="{{ 
                                        @$template?->status->value == \App\Enums\Common\Status::ACTIVE->value
                                        ? \App\Enums\Common\Status::INACTIVE->value
                                        : \App\Enums\Common\Status::ACTIVE->value}}"
                                      data-route="{{route('admin.template.status.update')}}"
                                      id="{{ 'status_'.$template->uid }}"
                                      name="status"/>
                              <label for="{{ 'status_'.$template->uid }}" class="toggle">
                                  <span></span>
                              </label>
                          </div>
                        </td>
                        <td>
                          {{ $template->created_at->toDayDateTimeString() }}
                        </td>
                        <td data-label={{ translate('Option')}}>
                          <div class="d-flex align-items-center gap-1">
                              <a class="icon-btn btn-ghost btn-sm success-soft circle" href="{{ route("admin.template.edit", ["uid" => $template->uid]) }}">
                                  <i class="ri-edit-line"></i>
                                  <span class="tooltiptext"> {{ translate("Edit Template") }} </span>
                              </a>
                              <button class="icon-btn btn-ghost btn-sm danger-soft circle text-danger delete-email-template"
                                      type="button"
                                      data-url        = "{{route('admin.template.destroy', ['uid' => $template->uid])}}"
                                      data-bs-toggle="modal"
                                      data-bs-target="#deleteEmailTemplate">
                                  <i class="ri-delete-bin-line"></i>
                                  <span class="tooltiptext"> {{ translate("Delete template") }} </span>
                              </button>
                          </div>
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

              @include('admin.partials.pagination', ['paginator' => $adminTemplates])
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="user_template" role="tabpanel">
          <div class="card">
            <div class="card-header">
              <div class="card-header-left">
                <h4 class="card-title">{{ translate("User templates") }}</h4>
              </div>
            </div>
            <div class="card-body px-0 pt-0">
              <div class="table-container">
                <table>
                  <thead>
                    <tr>
                      <th scope="col">{{ translate("Name") }}</th>
                      <th scope="col">{{ translate("Provider") }}</th>
                      <th scope="col">{{ translate("Approval Status") }}</th>
                      <th scope="col">{{ translate("Created At") }}</th>
                      <th scope="col">{{ translate("Option") }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($userTemplates as $user_template)
                    <tr>
                      <td>
                        <span class="fw-semibold text-dark">{{ $user_template->name }}</span>
                      </td>
                      <td>
                        {{ $user_template->provider->badge() }}
                      </td>
                      <td data-label="{{ translate('Approval Status')}}">
                        {{ $user_template->approval_status->badge() }}
                      </td>
                      <td>
                        {{ $user_template->created_at->toDayDateTimeString() }}
                      </td>
                      <td data-label={{ translate('Option')}}>
                        <div class="d-flex align-items-center gap-1">
                          <button class="icon-btn btn-ghost btn-sm success-soft circle approve-email-template"
                                  type="button"
                                  data-template-uid="{{ $user_template->uid }}"
                                  data-bs-toggle="modal"
                                  data-bs-target="#emailTemplateApproval">
                              <i class="ri-edit-line"></i>
                              <span class="tooltiptext"> {{ translate("Approve Template") }} </span>
                          </button>
                            <button class="icon-btn btn-ghost btn-sm danger-soft circle text-danger delete-email-template"
                                    type="button"
                                    data-template-id="{{ $user_template->id }}"
                                    data-url        = "{{route('admin.template.destroy', ['uid' => $user_template->uid])}}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#deleteEmailTemplate">
                                <i class="ri-delete-bin-line"></i>
                                <span class="tooltiptext"> {{ translate("Delete template") }} </span>
                            </button>
                        </div>
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

              @include('admin.partials.pagination', ['paginator' => $userTemplates])
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="default_templates" role="tabpanel">
          <div class="card">
            <div class="card-header">
              <div class="card-header-left">
                <h4 class="card-title">{{ translate("Default Templates") }}</h4>
              </div>
            </div>
            <div class="card-body px-0 pt-0">
              <div class="table-container">
                <table>
                  <thead>
                    <tr>
                      <th scope="col">{{ translate("Name") }}</th>
                      <th scope="col">{{ translate("Subject") }}</th>
                      <th scope="col">{{ translate("Status") }}</th>
                      <th scope="col">{{ translate("Option") }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($defaultTemplates as $default_template)
                    <tr>
                      <td>
                        <span class="fw-semibold text-dark">{{ $default_template->name }}</span>
                      </td>
                      <td>
                        {{ $default_template->template_data["subject"] }}
                      </td>
                      <td data-label="{{ translate('Status')}}">
                        <div class="switch-wrapper checkbox-data">
                            <input {{ $default_template->status->value == App\Enums\Common\Status::ACTIVE->value ? 'checked' : '' }}
                                    type="checkbox"
                                    class="switch-input statusUpdateByUID"
                                    data-id="{{ $default_template->uid }}"
                                    data-column="status"
                                    data-value="{{ 
                                      $default_template->status == 1 || @$default_template?->status == \App\Enums\Common\Status::ACTIVE->value
                                      ? \App\Enums\Common\Status::INACTIVE->value
                                      : \App\Enums\Common\Status::ACTIVE->value}}"
                                    data-route="{{route('admin.template.status.update')}}"
                                    id="{{ 'status_'.$default_template->uid }}"
                                    name="status"/>
                            <label for="{{ 'status_'.$default_template->uid }}" class="toggle">
                                <span></span>
                            </label>
                        </div>
                      </td>
                      <td data-label={{ translate('Option')}}>
                        <div class="d-flex align-items-center gap-1">
                            <button class="icon-btn btn-ghost btn-sm info-soft circle edit-default-email-template"
                                    type="button"
                                    data-template-url="{{route('admin.template.update', ['uid' => $default_template->uid])}}"
                                    data-template-meta-data="{{ $default_template->meta_data }}"
                                    data-template-subject="{{ \Illuminate\Support\Arr::get($default_template->template_data, "subject") }}"
                                    data-template-mail-body="{{ \Illuminate\Support\Arr::get($default_template->template_data, "mail_body") }}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editDefaultEmailTemplate">
                                <i class="ri-edit-line"></i>
                                <span class="tooltiptext"> {{ translate("Edit Template") }} </span>
                            </button>
                        </div>
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

              @include('admin.partials.pagination', ['paginator' => $defaultTemplates])
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="global_template" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <div class="card-header-left">
                      <h4 class="card-title">{{ translate("Global Template") }}</h4>
                    </div>
                </div>

                <div class="card-body pt-0">
                  <form action="{{ route("admin.template.update", ["uid" => $globalTemplate->uid]) }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <input type="hidden" name="_method" value="PATCH">

                        <input type="text" name="provider" value="{{ \App\Enums\System\TemplateProviderEnum::SYSTEM->value }}" hidden>
                        <input type="text" name="channel" value="{{ \App\Enums\System\ChannelTypeEnum::EMAIL->value }}" hidden>

                        <div class="form-element">
                            <div class="row gy-3">
                                <div class="col-xxl-2 col-xl-3">
                                    <h5 class="form-element-title">{{ translate("Meta Data") }}</h5>
                                </div>
                                <div class="col-xxl-8 col-xl-9">
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="bg-light rounded-2 p-3 fs-15 text-muted border global-meta-data-container">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-element">
                            <div class="row gy-4">
                                <div class="col-xxl-2 col-xl-3">
                                    <h5 class="form-element-title">{{ translate("Mail Body") }}</h5>
                                    </div>
                                    <div class="col-xxl-8 col-xl-9">
                                    <div class="row gy-4">
                                        <div class="col-md-12 maintenance-message">
                                            <div class="form-inner">

                                              <textarea class="form-control" name="template_data[mail_body]" id="global_mail_body" rows="2" placeholder="{{ translate('Type global mail body text') }}" aria-label="{{ translate('Type global mail body text') }}">{{ \Illuminate\Support\Arr::get($globalTemplate->template_data, "mail_body")}}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-xxl-10">
                                <div class="form-action justify-content-end">
                                    <button type="submit" class="i-btn btn--primary btn--md"> {{ translate("Save") }} </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
      </div>
    @endif

    @if(request()->channel == \App\Enums\System\ChannelTypeEnum::WHATSAPP->value)
        {{-- Search Filter --}}
        <div class="table-filter mb-4">
            <form action="{{route(Route::currentRouteName(), ['channel' => $channel->value, "cloud_id" => $cloudId])}}" class="filter-form">
                <div class="row g-3">
                    <div class="col-lg-4">
                        <div class="filter-search">
                            <input type="search" value="{{request()->search}}" name="search" class="form-control" id="filter-search" placeholder="{{ translate("Search templates by name") }}" />
                            <span><i class="ri-search-line"></i></span>
                        </div>
                    </div>
                    <div class="col-xxl-6 col-lg-8 offset-xxl-2">
                        <div class="filter-action">
                            <div class="input-group">
                                <input type="text" class="form-control" id="datePicker" name="date" value="{{request()->input('date')}}" placeholder="{{translate('Filter by date')}}" aria-describedby="filterByDate">
                                <span class="input-group-text" id="filterByDate">
                                    <i class="ri-calendar-2-line"></i>
                                </span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <button type="submit" class="filter-action-btn">
                                    <i class="ri-menu-search-line"></i> {{ translate("Search") }}
                                </button>
                                <a class="filter-action-btn bg-danger text-white" href="{{route(Route::currentRouteName(), ['channel' => $channel->value, "cloud_id" => $cloudId])}}">
                                    <i class="ri-refresh-line"></i> {{ translate("Reset") }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- Primary Tabs: Admin Templates | User Templates --}}
        <div class="pill-tab mb-4">
            <ul class="nav" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link active" data-bs-toggle="tab" href="#wa_admin_templates" role="tab" aria-selected="true">
                        <i class="ri-user-settings-line"></i> {{ translate("Admin Templates") }}
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" data-bs-toggle="tab" href="#wa_user_templates" role="tab" aria-selected="false" tabindex="-1">
                        <i class="ri-user-line"></i> {{ translate("User Templates") }}
                    </a>
                </li>
            </ul>
        </div>

        <div class="tab-content">
            {{-- Admin Templates Tab --}}
            <div class="tab-pane active fade show" id="wa_admin_templates" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <h4 class="card-title">{{ translate("Admin WhatsApp Templates") }}</h4>
                        </div>
                    </div>
                    <div class="card-body">
                        {{-- Sub-tabs: Node Templates | Cloud API Templates --}}
                        <ul class="nav nav-tabs mb-4" id="adminTemplateTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="admin-node-tab" data-bs-toggle="tab" data-bs-target="#admin-node-templates" type="button" role="tab" aria-controls="admin-node-templates" aria-selected="true">
                                    <i class="ri-qr-code-line me-1"></i>{{ translate("Node Templates") }}
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="admin-cloud-tab" data-bs-toggle="tab" data-bs-target="#admin-cloud-templates" type="button" role="tab" aria-controls="admin-cloud-templates" aria-selected="false">
                                    <i class="ri-cloud-line me-1"></i>{{ translate("Cloud API Templates") }}
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="adminTemplateTabsContent">
                            {{-- Admin Node Templates --}}
                            <div class="tab-pane fade show active" id="admin-node-templates" role="tabpanel" aria-labelledby="admin-node-tab">
                                <div class="mb-3">
                                    <button type="button" class="i-btn btn--primary btn--sm" data-bs-toggle="modal" data-bs-target="#createNodeTemplateModal">
                                        <i class="ri-add-line me-1"></i>{{ translate("Create Template") }}
                                    </button>
                                </div>
                                <div class="table-container">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th scope="col">{{ translate("Name") }}</th>
                                                <th scope="col">{{ translate("Message Preview") }}</th>
                                                <th scope="col">{{ translate("Has Media") }}</th>
                                                <th scope="col">{{ translate("Options") }}</th>
                                                <th scope="col">{{ translate("Status") }}</th>
                                                <th scope="col">{{ translate("Created At") }}</th>
                                                <th scope="col">{{ translate("Actions") }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($adminNodeTemplates ?? [] as $template)
                                                <tr>
                                                    <td data-label="{{ translate('Name')}}">
                                                        <span class="fw-semibold text-dark">{{ $template->name }}</span>
                                                    </td>
                                                    <td data-label="{{ translate('Message Preview')}}">
                                                        <span class="text-muted small">{{ \Illuminate\Support\Str::limit($template->template_data['message'] ?? '', 50) }}</span>
                                                    </td>
                                                    <td data-label="{{ translate('Has Media')}}">
                                                        @if(!empty($template->template_data['image_url']))
                                                            <span class="i-badge success-solid pill">{{ translate("Yes") }}</span>
                                                        @else
                                                            <span class="i-badge secondary-solid pill">{{ translate("No") }}</span>
                                                        @endif
                                                    </td>
                                                    <td data-label="{{ translate('Options')}}">
                                                        @if(!empty($template->template_data['buttons']) && count($template->template_data['buttons']) > 0)
                                                            <span class="i-badge info-solid pill">{{ count($template->template_data['buttons']) }} {{ translate("buttons") }}</span>
                                                        @else
                                                            <span class="i-badge secondary-solid pill">{{ translate("None") }}</span>
                                                        @endif
                                                    </td>
                                                    <td data-label="{{ translate('Status')}}">
                                                        <div class="switch-wrapper checkbox-data">
                                                            <input {{ $template->status->value == App\Enums\Common\Status::ACTIVE->value ? 'checked' : '' }}
                                                                    type="checkbox"
                                                                    class="switch-input statusUpdateByUID"
                                                                    data-uid="{{ $template->uid }}"
                                                                    data-column="status"
                                                                    data-value="{{ $template->status->value == \App\Enums\Common\Status::ACTIVE->value ? \App\Enums\Common\Status::INACTIVE->value : \App\Enums\Common\Status::ACTIVE->value }}"
                                                                    data-route="{{route('admin.template.status.update')}}"
                                                                    id="{{ 'status_'.$template->uid }}"
                                                                    name="status"/>
                                                            <label for="{{ 'status_'.$template->uid }}" class="toggle">
                                                                <span></span>
                                                            </label>
                                                        </div>
                                                    </td>
                                                    <td data-label="{{ translate('Created At')}}">
                                                        {{ $template->created_at->format('d M Y') }}
                                                    </td>
                                                    <td data-label="{{ translate('Actions')}}">
                                                        <div class="d-flex align-items-center gap-1">
                                                            <button type="button" class="icon-btn btn-ghost btn-sm success-soft circle edit-node-template"
                                                                    data-template="{{ json_encode($template) }}"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#editNodeTemplateModal">
                                                                <i class="ri-edit-line"></i>
                                                                <span class="tooltiptext">{{ translate("Edit Template") }}</span>
                                                            </button>
                                                            <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle text-danger delete-node-template"
                                                                    data-id="{{ $template->id }}">
                                                                <i class="ri-delete-bin-line"></i>
                                                                <span class="tooltiptext">{{ translate("Delete Template") }}</span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td class="text-muted text-center" colspan="7">{{ translate('No Admin Node Templates Found')}}</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                @if(isset($adminNodeTemplates) && $adminNodeTemplates->hasPages())
                                    @include('admin.partials.pagination', ['paginator' => $adminNodeTemplates])
                                @endif
                            </div>

                            {{-- Admin Cloud API Templates --}}
                            <div class="tab-pane fade" id="admin-cloud-templates" role="tabpanel" aria-labelledby="admin-cloud-tab">
                                <div class="table-container">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th scope="col">{{ translate("Name") }}</th>
                                                <th scope="col">{{ translate("Business Account") }}</th>
                                                <th scope="col">{{ translate("Language") }}</th>
                                                <th scope="col">{{ translate("Category") }}</th>
                                                <th scope="col">{{ translate("Status") }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($adminCloudTemplates ?? [] as $template)
                                                <tr>
                                                    <td data-label="{{ translate('Name')}}">
                                                        <span class="fw-semibold text-dark">{{ $template->name }}</span>
                                                    </td>
                                                    <td data-label="{{ translate('Business Account')}}">
                                                        @if($template->cloudApi)
                                                            <a href="{{route('admin.gateway.whatsapp.cloud.api.index', ['id' => $template->cloud_id])}}" class="i-badge info-solid pill">
                                                                {{ $template->cloudApi->name }} <i class="ri-external-link-line ms-1"></i>
                                                            </a>
                                                        @else
                                                            <span class="i-badge secondary-solid pill">{{ translate("N/A") }}</span>
                                                        @endif
                                                    </td>
                                                    <td data-label="{{ translate('Language')}}">
                                                        <span class="i-badge primary-solid pill">{{ $template->template_data['language'] ?? 'N/A' }}</span>
                                                    </td>
                                                    <td data-label="{{ translate('Category')}}">
                                                        {{ $template->template_data['category'] ?? 'N/A' }}
                                                    </td>
                                                    <td data-label="{{ translate('Status')}}">
                                                        @php
                                                            $cloudStatus = $template->template_data['status'] ?? 'N/A';
                                                            $statusClass = match(strtoupper($cloudStatus)) {
                                                                'APPROVED' => 'success',
                                                                'PENDING' => 'warning',
                                                                'REJECTED' => 'danger',
                                                                default => 'secondary'
                                                            };
                                                        @endphp
                                                        <span class="i-badge {{ $statusClass }}-solid pill">{{ $cloudStatus }}</span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td class="text-muted text-center" colspan="5">{{ translate('No Admin Cloud API Templates Found')}}</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                @if(isset($adminCloudTemplates) && $adminCloudTemplates->hasPages())
                                    @include('admin.partials.pagination', ['paginator' => $adminCloudTemplates])
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- User Templates Tab --}}
            <div class="tab-pane fade" id="wa_user_templates" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <h4 class="card-title">{{ translate("User WhatsApp Templates") }}</h4>
                        </div>
                    </div>
                    <div class="card-body">
                        {{-- Sub-tabs: Node Templates | Cloud API Templates --}}
                        <ul class="nav nav-tabs mb-4" id="userTemplateTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="user-node-tab" data-bs-toggle="tab" data-bs-target="#user-node-templates" type="button" role="tab" aria-controls="user-node-templates" aria-selected="true">
                                    <i class="ri-qr-code-line me-1"></i>{{ translate("Node Templates") }}
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="user-cloud-tab" data-bs-toggle="tab" data-bs-target="#user-cloud-templates" type="button" role="tab" aria-controls="user-cloud-templates" aria-selected="false">
                                    <i class="ri-cloud-line me-1"></i>{{ translate("Cloud API Templates") }}
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="userTemplateTabsContent">
                            {{-- User Node Templates --}}
                            <div class="tab-pane fade show active" id="user-node-templates" role="tabpanel" aria-labelledby="user-node-tab">
                                <div class="table-container">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th scope="col">{{ translate("Name") }}</th>
                                                <th scope="col">{{ translate("User") }}</th>
                                                <th scope="col">{{ translate("Message Preview") }}</th>
                                                <th scope="col">{{ translate("Has Media") }}</th>
                                                <th scope="col">{{ translate("Approval Status") }}</th>
                                                <th scope="col">{{ translate("Created At") }}</th>
                                                <th scope="col">{{ translate("Actions") }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($userNodeTemplates ?? [] as $template)
                                                <tr>
                                                    <td data-label="{{ translate('Name')}}">
                                                        <span class="fw-semibold text-dark">{{ $template->name }}</span>
                                                    </td>
                                                    <td data-label="{{ translate('User')}}">
                                                        @if($template->user)
                                                            <span class="i-badge info-solid pill">{{ $template->user->name }}</span>
                                                        @else
                                                            <span class="i-badge secondary-solid pill">{{ translate("N/A") }}</span>
                                                        @endif
                                                    </td>
                                                    <td data-label="{{ translate('Message Preview')}}">
                                                        <span class="text-muted small">{{ \Illuminate\Support\Str::limit($template->template_data['message'] ?? '', 40) }}</span>
                                                    </td>
                                                    <td data-label="{{ translate('Has Media')}}">
                                                        @if(!empty($template->template_data['image_url']))
                                                            <span class="i-badge success-solid pill">{{ translate("Yes") }}</span>
                                                        @else
                                                            <span class="i-badge secondary-solid pill">{{ translate("No") }}</span>
                                                        @endif
                                                    </td>
                                                    <td data-label="{{ translate('Approval Status')}}">
                                                        {{ @$template->approval_status ? $template->approval_status->badge() : translate("N/A") }}
                                                    </td>
                                                    <td data-label="{{ translate('Created At')}}">
                                                        {{ $template->created_at->format('d M Y') }}
                                                    </td>
                                                    <td data-label="{{ translate('Actions')}}">
                                                        <div class="d-flex align-items-center gap-1">
                                                            <button class="icon-btn btn-ghost btn-sm success-soft circle approve-wa-node-template"
                                                                    type="button"
                                                                    data-template-uid="{{ $template->uid }}"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#waTemplateApproval">
                                                                <i class="ri-checkbox-circle-line"></i>
                                                                <span class="tooltiptext">{{ translate("Approve Template") }}</span>
                                                            </button>
                                                            <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle text-danger delete-node-template"
                                                                    data-id="{{ $template->id }}">
                                                                <i class="ri-delete-bin-line"></i>
                                                                <span class="tooltiptext">{{ translate("Delete Template") }}</span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td class="text-muted text-center" colspan="7">{{ translate('No User Node Templates Found')}}</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                @if(isset($userNodeTemplates) && $userNodeTemplates->hasPages())
                                    @include('admin.partials.pagination', ['paginator' => $userNodeTemplates])
                                @endif
                            </div>

                            {{-- User Cloud API Templates --}}
                            <div class="tab-pane fade" id="user-cloud-templates" role="tabpanel" aria-labelledby="user-cloud-tab">
                                <div class="table-container">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th scope="col">{{ translate("Name") }}</th>
                                                <th scope="col">{{ translate("User") }}</th>
                                                <th scope="col">{{ translate("Business Account") }}</th>
                                                <th scope="col">{{ translate("Language") }}</th>
                                                <th scope="col">{{ translate("Category") }}</th>
                                                <th scope="col">{{ translate("Status") }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($userCloudTemplates ?? [] as $template)
                                                <tr>
                                                    <td data-label="{{ translate('Name')}}">
                                                        <span class="fw-semibold text-dark">{{ $template->name }}</span>
                                                    </td>
                                                    <td data-label="{{ translate('User')}}">
                                                        @if($template->user)
                                                            <span class="i-badge info-solid pill">{{ $template->user->name }}</span>
                                                        @else
                                                            <span class="i-badge secondary-solid pill">{{ translate("N/A") }}</span>
                                                        @endif
                                                    </td>
                                                    <td data-label="{{ translate('Business Account')}}">
                                                        @if($template->cloudApi)
                                                            <span class="i-badge primary-solid pill">{{ $template->cloudApi->name }}</span>
                                                        @else
                                                            <span class="i-badge secondary-solid pill">{{ translate("N/A") }}</span>
                                                        @endif
                                                    </td>
                                                    <td data-label="{{ translate('Language')}}">
                                                        {{ $template->template_data['language'] ?? 'N/A' }}
                                                    </td>
                                                    <td data-label="{{ translate('Category')}}">
                                                        {{ $template->template_data['category'] ?? 'N/A' }}
                                                    </td>
                                                    <td data-label="{{ translate('Status')}}">
                                                        @php
                                                            $cloudStatus = $template->template_data['status'] ?? 'N/A';
                                                            $statusClass = match(strtoupper($cloudStatus)) {
                                                                'APPROVED' => 'success',
                                                                'PENDING' => 'warning',
                                                                'REJECTED' => 'danger',
                                                                default => 'secondary'
                                                            };
                                                        @endphp
                                                        <span class="i-badge {{ $statusClass }}-solid pill">{{ $cloudStatus }}</span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td class="text-muted text-center" colspan="6">{{ translate('No User Cloud API Templates Found')}}</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                @if(isset($userCloudTemplates) && $userCloudTemplates->hasPages())
                                    @include('admin.partials.pagination', ['paginator' => $userCloudTemplates])
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
  </div>
</main>
@endsection
@section("modal")


@if(request()->channel == \App\Enums\System\ChannelTypeEnum::SMS->value)

  <div class="modal fade" id="addSmsTemplate" tabindex="-1" aria-labelledby="addSmsTemplate" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered ">
        <div class="modal-content">
            <form action="{{route('admin.template.store')}}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="text" name="channel" value="{{ \App\Enums\System\ChannelTypeEnum::SMS->value }}" hidden>
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Add SMS Template") }} </h5>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                        <i class="ri-close-large-line"></i>
                    </button>
                </div>
                <div class="modal-body modal-lg-custom-height">
                    <div class="row g-4">
                        <div class="col-lg-12">
                          <div class="form-inner">
                              <label for="sms_template_add_name" class="form-label"> {{ translate('Template Name')}}<span class="text-danger">*</span></label>
                              <input type="text" id="sms_template_add_name" name="name" placeholder="{{ translate('Enter your sms template name')}}" class="form-control" aria-label="name"/>
                          </div>
                        </div>
                        <div class="col-lg-12">
                          <div class="form-inner">
                              <label for="sms_template_add_message" class="form-label">{{translate('Template Body')}}<span class="text-danger">*</span></label>
                              <textarea rows="5"  class="form-control" id="sms_template_add_message" name="template_data[message]" placeholder="{{translate('Enter your template message')}}" required=""></textarea>
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

  <div class="modal fade" id="editSmsTemplate" tabindex="-1" aria-labelledby="editSmsTemplate" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered ">
        <div class="modal-content">
          <form id="updateSMSTemplate" method="POST" enctype="multipart/form-data"> 
                @csrf
                <input type="hidden" name="_method" value="PATCH">
                <input type="text" name="channel" value="{{ \App\Enums\System\ChannelTypeEnum::SMS->value }}" hidden>
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Add SMS Template") }} </h5>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                        <i class="ri-close-large-line"></i>
                    </button>
                </div>
                <div class="modal-body modal-lg-custom-height">
                    <div class="row g-4">
                        <div class="col-lg-12">
                          <div class="form-inner">
                              <label for="sms_template_update_name" class="form-label"> {{ translate('Template Name')}}<span class="text-danger">*</span></label>
                              <input type="text" id="sms_template_update_name" name="name" placeholder="{{ translate('Enter your sms template name')}}" class="form-control" aria-label="name"/>
                          </div>
                        </div>
                        <div class="col-lg-12">
                          <div class="form-inner">
                              <label for="sms_template_update_message" class="form-label">{{translate('Template Body')}}<span class="text-danger">*</span></label>
                              <textarea rows="5"  class="form-control" id="sms_template_update_message" name="template_data[message]" placeholder="{{translate('Enter your template message')}}" required=""></textarea>
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

  <div class="modal fade actionModal" id="deleteUserSmsTemplate" tabindex="-1" aria-labelledby="deleteUserSmsTemplate" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
        <div class="modal-header text-start">
            <span class="action-icon danger">
            <i class="bi bi-exclamation-circle"></i>
            </span>
        </div>
        <form id="smsTemplateDeleteForm" method="POST">
            @csrf
            <input type="hidden" name="_method" value="DELETE">
            <div class="modal-body">

                <div class="action-message">
                    <h5>{{ translate("Are you sure to delete this template?") }}</h5>

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

  <div class="modal fade actionModal" id="deleteAdminSmsTemplate" tabindex="-1" aria-labelledby="deleteAdminSmsTemplate" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
        <div class="modal-header text-start">
            <span class="action-icon danger">
            <i class="bi bi-exclamation-circle"></i>
            </span>
        </div>
        <form id="smsTemplateDeleteForm" method="POST">
            @csrf
            <input type="hidden" name="_method" value="DELETE">
            <div class="modal-body">

                <div class="action-message">
                    <h5>{{ translate("Are you sure to delete this template?") }}</h5>

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

  <div class="modal fade" id="smsTemplateApproval" tabindex="-1" aria-labelledby="smsTemplateApproval" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered ">
        <div class="modal-content">
          <form action="{{ route("admin.template.approve") }}" method="POST" enctype="multipart/form-data"> 
                @csrf
                <input type="hidden" name="uid">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Approve SMS Template") }} </h5>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                        <i class="ri-close-large-line"></i>
                    </button>
                </div>
                <div class="modal-body modal-lg-custom-height">
                    <div class="row g-4">
                      <div class="col-12">
                        <div class="form-inner">
                          <label for="approval_status" class="form-label">{{ translate("Choose Approval Status") }}<span class="text-danger">*</span></label>
                          <select class="form-select select2-search" data-show="5" name="approval_status">
                            <option disabled selected>{{ translate("Select a status") }}</option>
                            @foreach(\App\Enums\System\TemplateApprovalStatusEnum::getValues() as $value)
                              <option value="{{ $value }}">{{ ucfirst(strtolower($value)) }}</option>
                            @endforeach
                        </select>
                        </div>
                    </div>
                    <div class="col-12 mb-4">
                      <div class="form-inner">
                          <label for="remarks" class="form-label"> {{ translate('Remark')}}<span class="text-danger">*</span></label>
                          <textarea type="text" id="remarks" name="remarks" placeholder="{{ translate('Type remarks for the user')}}" class="form-control" aria-label="remarks"></textarea>
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
@endif

@if(request()->channel == \App\Enums\System\ChannelTypeEnum::EMAIL->value)


<div class="modal fade" id="editDefaultEmailTemplate" tabindex="-1" aria-labelledby="editDefaultEmailTemplate" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered ">
        <div class="modal-content">
            <form id="updateDefaultMailTemplate" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_method" value="PATCH">
                <input type="text" name="provider" value="{{ \App\Enums\System\TemplateProviderEnum::SYSTEM->value }}" hidden>
                <input type="text" name="channel" value="{{ \App\Enums\System\ChannelTypeEnum::EMAIL->value }}" hidden>
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Update Defalt Email Template") }} </h5>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                        <i class="ri-close-large-line"></i>
                    </button>
                </div>
                <div class="modal-body modal-lg-custom-height">
                    <div class="form-element">
                        <div class="row gy-3">
                            <div class="col-xxl-2 col-xl-3">
                                <h5 class="form-element-title">{{ translate("Meta Data") }}</h5>
                            </div>
                            <div class="col-xxl-10 col-xl-9">
                                <div class="row">
                                    <div class="col-xl-10">
                                        <div class="bg-light rounded-2 p-3 fs-15 text-muted border default-meta-data-container">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row gy-3 mt-3">
                            <div class="col-xxl-2 col-xl-3">
                                <h5 class="form-element-title">{{ translate("Subject") }}</h5>
                            </div>
                            <div class="col-xxl-10 col-xl-9">
                                <div class="row">
                                    <div class="col-xl-10">
                                        <div class="form-inner">
                                            <input type="text" id="default_template_subject" name="template_data[subject]" placeholder="{{ translate('Enter your notification mail subject')}}" class="form-control" aria-label="template_subject"/>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row gy-3 mt-3">
                            <div class="col-xxl-2 col-xl-3">
                                <h5 class="form-element-title">{{ translate("Mail Body") }}</h5>
                            </div>
                            <div class="col-xxl-10 col-xl-9">
                                <div class="row">
                                    <div class="col-xl-10">
                                        <div class="form-inner">
                                            <textarea class="form-control" name="template_data[mail_body]" id="default_template_mail_body" rows="2" placeholder="{{ translate('Type default mail body text') }}" aria-label="{{ translate('Type default mail body text') }}"></textarea>
                                        </div>
                                    </div>
                                </div>
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

<div class="modal fade actionModal" id="deleteEmailTemplate" tabindex="-1" aria-labelledby="deleteEmailTemplate" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
        <div class="modal-header text-start">
            <span class="action-icon danger">
            <i class="bi bi-exclamation-circle"></i>
            </span>
        </div>
        <form method="POST" id="templateDeleteForm">
            @csrf
            <input type="hidden" name="_method" value="DELETE">
            <div class="modal-body">

                <div class="action-message">
                    <h5>{{ translate("Are you sure to delete this template?") }}</h5>

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

<div class="modal fade" id="emailTemplateApproval" tabindex="-1" aria-labelledby="emailTemplateApproval" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered ">
      <div class="modal-content">
        <form action="{{ route("admin.template.approve") }}" method="POST" enctype="multipart/form-data"> 
              @csrf
              <input type="hidden" name="uid">
              <input type="text" name="channel" value="{{ \App\Enums\System\ChannelTypeEnum::EMAIL->value }}" hidden>
              <div class="modal-header">
                  <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Approve Email Template") }} </h5>
                  <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                      <i class="ri-close-large-line"></i>
                  </button>
              </div>
              <div class="modal-body modal-lg-custom-height">
                  <div class="row g-4">
                    <div class="col-12">
                      <div class="form-inner">
                        <label for="gateway_id" class="form-label">{{ translate("Choose Approval Status") }}</label>
                        <select class="form-select select2-search" data-show="5" name="approval_status">
                          <option disabled selected>{{ translate("Select a status") }}</option>
                          @foreach(\App\Enums\System\TemplateApprovalStatusEnum::getValues() as $value)
                            <option value="{{ $value }}">{{ ucfirst(strtolower($value)) }}</option>
                          @endforeach
                      </select>
                      </div>
                  </div>
                  <div class="col-12 mb-4">
                    <div class="form-inner">
                        <label for="remarks" class="form-label"> {{ translate('Remark')}}<span class="text-danger">*</span></label>
                        <textarea type="text" id="sms_template_remark" name="remarks" placeholder="{{ translate('Type remarks for the user')}}" class="form-control" aria-label="remarks"> </textarea>
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
@endif

{{-- WhatsApp Node Templates Modals --}}
@if(request()->channel == \App\Enums\System\ChannelTypeEnum::WHATSAPP->value)

<!-- Create Node Template Modal -->
<div class="modal fade" id="createNodeTemplateModal" tabindex="-1" aria-labelledby="createNodeTemplateModal" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form action="{{ route('admin.template.node.store') }}" method="POST" id="createNodeTemplateForm">
        @csrf
        <input type="hidden" name="channel" value="{{ \App\Enums\System\ChannelTypeEnum::WHATSAPP->value }}">

        <div class="modal-header">
          <h5 class="modal-title">{{ translate("Create Node QR Template") }}</h5>
          <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
            <i class="ri-close-large-line"></i>
          </button>
        </div>

        <div class="modal-body modal-lg-custom-height">
          <div class="row g-4">
            <div class="col-12">
              <div class="form-inner">
                <label for="template_name" class="form-label">{{ translate('Template Name') }}<span class="text-danger">*</span></label>
                <input type="text" id="template_name" name="name" placeholder="{{ translate('Enter template name') }}" class="form-control" required>
              </div>
            </div>

            <div class="col-12">
              <div class="form-inner">
                <label for="template_message" class="form-label">{{ translate('Message Body') }}<span class="text-danger">*</span></label>
                <textarea rows="5" class="form-control" id="template_message" name="template_data[message]" placeholder="{{ translate('Enter message. Use variables for dynamic content') }}" required></textarea>
                <small class="text-muted">{{ translate('You can use variables like') }} @{{ '{' }}{name}}, @{{ '{' }}{phone}}, @{{ '{' }}{company}}, {{ translate('etc.') }}</small>
              </div>
            </div>

            <div class="col-12">
              <div class="form-inner">
                <label for="template_image_url" class="form-label">{{ translate('Image URL') }} <span class="text-muted">({{ translate('Optional') }})</span></label>
                <input type="url" id="template_image_url" name="template_data[image_url]" placeholder="{{ translate('https://example.com/image.jpg') }}" class="form-control">
              </div>
            </div>

            <div class="col-12">
              <div class="form-inner">
                <label class="form-label">{{ translate('Quick Reply Options') }} <span class="text-muted">({{ translate('Optional') }})</span></label>
                <div class="alert alert-warning py-2 mb-2">
                  <small><i class="ri-information-line me-1"></i>{{ translate('Note: WhatsApp has deprecated interactive buttons for QR-code based connections. Options will be sent as numbered text choices that recipients can reply to.') }}</small>
                </div>
                <div id="buttons-container">
                  <!-- Options will be added dynamically -->
                </div>
                <button type="button" class="i-btn btn--sm btn--secondary mt-2" id="add-button">
                  <i class="ri-add-line"></i> {{ translate('Add Option') }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="i-btn btn--danger outline btn--md" data-bs-dismiss="modal">{{ translate("Close") }}</button>
          <button type="submit" class="i-btn btn--primary btn--md">{{ translate("Create Template") }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Node Template Modal -->
<div class="modal fade" id="editNodeTemplateModal" tabindex="-1" aria-labelledby="editNodeTemplateModal" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form action="" method="POST" id="editNodeTemplateForm">
        @csrf
        @method('PUT')
        <input type="hidden" name="channel" value="{{ \App\Enums\System\ChannelTypeEnum::WHATSAPP->value }}">

        <div class="modal-header">
          <h5 class="modal-title">{{ translate("Edit Node QR Template") }}</h5>
          <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
            <i class="ri-close-large-line"></i>
          </button>
        </div>

        <div class="modal-body modal-lg-custom-height">
          <div class="row g-4">
            <div class="col-12">
              <div class="form-inner">
                <label for="edit_template_name" class="form-label">{{ translate('Template Name') }}<span class="text-danger">*</span></label>
                <input type="text" id="edit_template_name" name="name" placeholder="{{ translate('Enter template name') }}" class="form-control" required>
              </div>
            </div>

            <div class="col-12">
              <div class="form-inner">
                <label for="edit_template_message" class="form-label">{{ translate('Message Body') }}<span class="text-danger">*</span></label>
                <textarea rows="5" class="form-control" id="edit_template_message" name="template_data[message]" placeholder="{{ translate('Enter message. Use variables for dynamic content') }}" required></textarea>
                <small class="text-muted">{{ translate('You can use variables like') }} @{{ '{' }}{name}}, @{{ '{' }}{phone}}, @{{ '{' }}{company}}, {{ translate('etc.') }}</small>
              </div>
            </div>

            <div class="col-12">
              <div class="form-inner">
                <label for="edit_template_image_url" class="form-label">{{ translate('Image URL') }} <span class="text-muted">({{ translate('Optional') }})</span></label>
                <input type="url" id="edit_template_image_url" name="template_data[image_url]" placeholder="{{ translate('https://example.com/image.jpg') }}" class="form-control">
              </div>
            </div>

            <div class="col-12">
              <div class="form-inner">
                <label class="form-label">{{ translate('Quick Reply Options') }} <span class="text-muted">({{ translate('Optional') }})</span></label>
                <div class="alert alert-warning py-2 mb-2">
                  <small><i class="ri-information-line me-1"></i>{{ translate('Note: WhatsApp has deprecated interactive buttons for QR-code based connections. Options will be sent as numbered text choices that recipients can reply to.') }}</small>
                </div>
                <div id="edit-buttons-container">
                  <!-- Options will be loaded dynamically -->
                </div>
                <button type="button" class="i-btn btn--sm btn--secondary mt-2" id="edit-add-button">
                  <i class="ri-add-line"></i> {{ translate('Add Option') }}
                </button>
              </div>
            </div>

            <div class="col-12">
              <div class="form-inner">
                <label for="edit_template_status" class="form-label">{{ translate('Status') }}</label>
                <select class="form-select" id="edit_template_status" name="status">
                  <option value="active">{{ translate('Active') }}</option>
                  <option value="inactive">{{ translate('Inactive') }}</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="i-btn btn--danger outline btn--md" data-bs-dismiss="modal">{{ translate("Close") }}</button>
          <button type="submit" class="i-btn btn--primary btn--md">{{ translate("Update Template") }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Node Template Modal -->
<div class="modal fade actionModal" id="deleteNodeTemplateModal" tabindex="-1" aria-labelledby="deleteNodeTemplateModal" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header text-start">
        <span class="action-icon danger">
          <i class="bi bi-exclamation-circle"></i>
        </span>
      </div>
      <form method="POST" id="deleteNodeTemplateForm">
        @csrf
        @method('DELETE')
        <div class="modal-body">
          <div class="action-message">
            <h5>{{ translate("Are you sure you want to delete this template?") }}</h5>
            <p class="text-muted">{{ translate("This action cannot be undone.") }}</p>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="i-btn btn--dark outline btn--lg" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
          <button type="submit" class="i-btn btn--danger btn--lg">{{ translate("Delete") }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- WhatsApp Template Approval Modal -->
<div class="modal fade" id="waTemplateApproval" tabindex="-1" aria-labelledby="waTemplateApproval" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <form action="{{ route("admin.template.approve") }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="uid">
        <div class="modal-header">
          <h5 class="modal-title">{{ translate("Approve WhatsApp Template") }}</h5>
          <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
            <i class="ri-close-large-line"></i>
          </button>
        </div>
        <div class="modal-body modal-lg-custom-height">
          <div class="row g-4">
            <div class="col-12">
              <div class="form-inner">
                <label for="wa_approval_status" class="form-label">{{ translate("Choose Approval Status") }}<span class="text-danger">*</span></label>
                <select class="form-select select2-search" data-show="5" name="approval_status" id="wa_approval_status">
                  <option disabled selected>{{ translate("Select a status") }}</option>
                  @foreach(\App\Enums\System\TemplateApprovalStatusEnum::getValues() as $value)
                    <option value="{{ $value }}">{{ ucfirst(strtolower($value)) }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="col-12 mb-4">
              <div class="form-inner">
                <label for="wa_remarks" class="form-label">{{ translate('Remark') }}<span class="text-danger">*</span></label>
                <textarea id="wa_remarks" name="remarks" placeholder="{{ translate('Type remarks for the user') }}" class="form-control" rows="3"></textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="i-btn btn--danger outline btn--md" data-bs-dismiss="modal">{{ translate("Close") }}</button>
          <button type="submit" class="i-btn btn--primary btn--md">{{ translate("Save") }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

@endif

@endsection

@php
    $globalTemplate_meta_data = json_decode($globalTemplate->meta_data, true);
@endphp

@push("script-include")
  <script src="{{asset('assets/theme/global/js/select2.min.js')}}"></script>
@endpush
@push("script-push")

  <script>
    "use strict";
    select2_search($('.select2-search').data('placeholder'));
    flatpickr("#datePicker", {
        dateFormat: "Y-m-d",
        mode: "range",
    });

    if("{{request()->channel == \App\Enums\System\ChannelTypeEnum::EMAIL->value}}") {

        ck_editor("#global_mail_body");
        ck_editor("#default_template_mail_body");
    }
    $(document).ready(function() {

       
        if("{{request()->channel == \App\Enums\System\ChannelTypeEnum::SMS->value}}") {

            $('.add-sms-template').on('click', function() {

              const modal = $('#addSmsTemplate');
              modal.modal('show');
            });

            $('.edit-sms-template').on('click', function() {

              const modal = $('#editSmsTemplate');
              modal.find('form[id=updateSMSTemplate]').attr('action', $(this).data('template-url'));
              modal.find('input[name=name]').val($(this).data('template-name'));
              modal.find("textarea[id='sms_template_update_message']").val($(this).data('template-message'));
              modal.modal('show');
            });

            $('.approve-sms-template').on('click', function() {
                const modal = $('#smsTemplateApproval');
                modal.find('input[name="uid"]').val($(this).data('template-uid'));
                modal.modal('show'); // jQuery Bootstrap modal show
            });

            $('.delete-admin-sms-template').on('click', function() {

              const modal = $('#deleteAdminSmsTemplate');
              modal.find('form[id=smsTemplateDeleteForm]').attr('action', $(this).data('template-url'));
              modal.modal('show');
            });

            $('.delete-user-sms-template').on('click', function() {

              const modal = $('#deleteUserSmsTemplate');
              modal.find('form[id=smsTemplateDeleteForm]').attr('action', $(this).data('template-url'));
              modal.modal('show');
            });
        }

        if("{{request()->channel == \App\Enums\System\ChannelTypeEnum::EMAIL->value}}") { 

            $('.approve-email-template').on('click', function() {
                const modal = $('#emailTemplateApproval');
                modal.find('input[name="uid"]').val($(this).data('template-uid'));
                modal.modal('show'); // jQuery Bootstrap modal show
            });
            $('.edit-default-email-template').on('click', function() {
                $('.default-meta-data-container').empty();
                const modal = $('#editDefaultEmailTemplate');
                modal.find('form[id=updateDefaultMailTemplate]').attr('action', $(this).data('template-url'));
                modal.find("input[id='default_template_subject']").val($(this).data('template-subject'));
                $.each($(this).data('template-meta-data'), function(key, value) {
                    var metaHtml = `<span class="text-dark fw-semibold">@{{${key}}}</span> ${value}<br/>`;
                    $('.default-meta-data-container').append(metaHtml);
                });

                if (editors['#default_template_mail_body']) {

                    editors['#default_template_mail_body'].setData($(this).data('template-mail-body'));
                }
                modal.modal('show');
            });

            var global_template_meta_data = @json($globalTemplate_meta_data);
            $.each(global_template_meta_data, function(key, value) {
                var metaHtml = `<span class="text-dark fw-semibold">@{{${key}}}</span> ${value}<br/>`;
                $('.global-meta-data-container').append(metaHtml);
            });

            $('.delete-email-template').on('click', function() {

                const modal = $('#deleteEmailTemplate');
                modal.find('form[id=templateDeleteForm]').attr('action', $(this).data('url'));
                modal.modal('show');
            });
        }

        // WhatsApp Node Template Management
        if("{{request()->channel == \App\Enums\System\ChannelTypeEnum::WHATSAPP->value}}") {

            let buttonIndex = 0;
            let editButtonIndex = 0;

            // Add button functionality for create modal
            $('#add-button').on('click', function() {
                buttonIndex++;
                let buttonHtml = '<div class="button-item card mb-2 p-3" id="button-' + buttonIndex + '">';
                buttonHtml += '<div class="row g-3">';
                buttonHtml += '<div class="col-md-4">';
                buttonHtml += '<label class="form-label">{{ translate("Button Type") }}</label>';
                buttonHtml += '<select class="form-select" name="template_data[buttons][' + buttonIndex + '][type]">';
                buttonHtml += '<option value="url">{{ translate("URL") }}</option>';
                buttonHtml += '<option value="phone">{{ translate("Phone") }}</option>';
                buttonHtml += '<option value="quick_reply">{{ translate("Quick Reply") }}</option>';
                buttonHtml += '</select>';
                buttonHtml += '</div>';
                buttonHtml += '<div class="col-md-4">';
                buttonHtml += '<label class="form-label">{{ translate("Button Text") }}</label>';
                buttonHtml += '<input type="text" class="form-control" name="template_data[buttons][' + buttonIndex + '][text]" placeholder="{{ translate("Button text") }}" required>';
                buttonHtml += '</div>';
                buttonHtml += '<div class="col-md-3 button-value-field">';
                buttonHtml += '<label class="form-label">{{ translate("URL/Phone") }}</label>';
                buttonHtml += '<input type="text" class="form-control" name="template_data[buttons][' + buttonIndex + '][value]" placeholder="{{ translate("Value") }}">';
                buttonHtml += '</div>';
                buttonHtml += '<div class="col-md-1 d-flex align-items-end">';
                buttonHtml += '<button type="button" class="i-btn btn--danger btn--md remove-button" data-button-id="button-' + buttonIndex + '">';
                buttonHtml += '<i class="ri-delete-bin-line"></i>';
                buttonHtml += '</button>';
                buttonHtml += '</div>';
                buttonHtml += '</div>';
                buttonHtml += '</div>';
                $('#buttons-container').append(buttonHtml);
            });

            // Add button functionality for edit modal
            $('#edit-add-button').on('click', function() {
                editButtonIndex++;
                let buttonHtml = '<div class="button-item card mb-2 p-3" id="edit-button-' + editButtonIndex + '">';
                buttonHtml += '<div class="row g-3">';
                buttonHtml += '<div class="col-md-4">';
                buttonHtml += '<label class="form-label">{{ translate("Button Type") }}</label>';
                buttonHtml += '<select class="form-select" name="template_data[buttons][' + editButtonIndex + '][type]">';
                buttonHtml += '<option value="url">{{ translate("URL") }}</option>';
                buttonHtml += '<option value="phone">{{ translate("Phone") }}</option>';
                buttonHtml += '<option value="quick_reply">{{ translate("Quick Reply") }}</option>';
                buttonHtml += '</select>';
                buttonHtml += '</div>';
                buttonHtml += '<div class="col-md-4">';
                buttonHtml += '<label class="form-label">{{ translate("Button Text") }}</label>';
                buttonHtml += '<input type="text" class="form-control" name="template_data[buttons][' + editButtonIndex + '][text]" placeholder="{{ translate("Button text") }}" required>';
                buttonHtml += '</div>';
                buttonHtml += '<div class="col-md-3 button-value-field">';
                buttonHtml += '<label class="form-label">{{ translate("URL/Phone") }}</label>';
                buttonHtml += '<input type="text" class="form-control" name="template_data[buttons][' + editButtonIndex + '][value]" placeholder="{{ translate("Value") }}">';
                buttonHtml += '</div>';
                buttonHtml += '<div class="col-md-1 d-flex align-items-end">';
                buttonHtml += '<button type="button" class="i-btn btn--danger btn--md remove-button" data-button-id="edit-button-' + editButtonIndex + '">';
                buttonHtml += '<i class="ri-delete-bin-line"></i>';
                buttonHtml += '</button>';
                buttonHtml += '</div>';
                buttonHtml += '</div>';
                buttonHtml += '</div>';
                $('#edit-buttons-container').append(buttonHtml);
            });

            // Remove button
            $(document).on('click', '.remove-button', function() {
                const buttonId = $(this).data('button-id');
                $('#' + buttonId).remove();
            });

            // Edit Node Template
            $(document).on('click', '.edit-node-template', function() {
                const template = $(this).data('template');
                const modal = $('#editNodeTemplateModal');

                // Set form action
                $('#editNodeTemplateForm').attr('action', '{{ route("admin.template.node.update", ":id") }}'.replace(':id', template.id));

                // Fill form fields
                $('#edit_template_name').val(template.name);
                $('#edit_template_message').val(template.template_data.message || '');
                $('#edit_template_image_url').val(template.template_data.image_url || '');
                $('#edit_template_status').val(template.status);

                // Clear and reload buttons
                $('#edit-buttons-container').empty();
                editButtonIndex = 0;

                if (template.template_data.buttons && template.template_data.buttons.length > 0) {
                    template.template_data.buttons.forEach((button, index) => {
                        editButtonIndex = index;
                        let buttonHtml = '<div class="button-item card mb-2 p-3" id="edit-button-' + index + '">';
                        buttonHtml += '<div class="row g-3">';
                        buttonHtml += '<div class="col-md-4">';
                        buttonHtml += '<label class="form-label">{{ translate("Button Type") }}</label>';
                        buttonHtml += '<select class="form-select" name="template_data[buttons][' + index + '][type]">';
                        buttonHtml += '<option value="url" ' + (button.type === 'url' ? 'selected' : '') + '>{{ translate("URL") }}</option>';
                        buttonHtml += '<option value="phone" ' + (button.type === 'phone' ? 'selected' : '') + '>{{ translate("Phone") }}</option>';
                        buttonHtml += '<option value="quick_reply" ' + (button.type === 'quick_reply' ? 'selected' : '') + '>{{ translate("Quick Reply") }}</option>';
                        buttonHtml += '</select>';
                        buttonHtml += '</div>';
                        buttonHtml += '<div class="col-md-4">';
                        buttonHtml += '<label class="form-label">{{ translate("Button Text") }}</label>';
                        buttonHtml += '<input type="text" class="form-control" name="template_data[buttons][' + index + '][text]" value="' + (button.text || '') + '" placeholder="{{ translate("Button text") }}" required>';
                        buttonHtml += '</div>';
                        buttonHtml += '<div class="col-md-3 button-value-field">';
                        buttonHtml += '<label class="form-label">{{ translate("URL/Phone") }}</label>';
                        buttonHtml += '<input type="text" class="form-control" name="template_data[buttons][' + index + '][value]" value="' + (button.url || button.phone || '') + '" placeholder="{{ translate("Value") }}">';
                        buttonHtml += '</div>';
                        buttonHtml += '<div class="col-md-1 d-flex align-items-end">';
                        buttonHtml += '<button type="button" class="i-btn btn--danger btn--md remove-button" data-button-id="edit-button-' + index + '">';
                        buttonHtml += '<i class="ri-delete-bin-line"></i>';
                        buttonHtml += '</button>';
                        buttonHtml += '</div>';
                        buttonHtml += '</div>';
                        buttonHtml += '</div>';
                        $('#edit-buttons-container').append(buttonHtml);
                    });
                }

                modal.modal('show');
            });

            // Delete Node Template
            $(document).on('click', '.delete-node-template', function() {
                const templateId = $(this).data('id');
                const modal = $('#deleteNodeTemplateModal');
                $('#deleteNodeTemplateForm').attr('action', '{{ route("admin.template.node.destroy", ":id") }}'.replace(':id', templateId));
                modal.modal('show');
            });

            // Clear create modal on close
            $('#createNodeTemplateModal').on('hidden.bs.modal', function() {
                $('#createNodeTemplateForm')[0].reset();
                $('#buttons-container').empty();
                buttonIndex = 0;
            });

            // WhatsApp Template Approval
            $(document).on('click', '.approve-wa-node-template', function() {
                const modal = $('#waTemplateApproval');
                modal.find('input[name="uid"]').val($(this).data('template-uid'));
                modal.modal('show');
            });
        }
    });
  </script>
@endpush

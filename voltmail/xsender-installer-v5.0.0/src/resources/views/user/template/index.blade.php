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
                <a href="{{ route("user.dashboard") }}">{{ translate("dashboard") }}</a>
              </li>
              <li class="breadcrumb-item active" aria-current="page"> {{ $title }} </li>
            </ol>
          </nav>
        </div>
      </div>
    </div>
    @if(request()->channel == \App\Enums\System\ChannelTypeEnum::SMS->value)
    <div class="card">
        <div class="card-header">
          <div class="card-header-left">
            <h4 class="card-title">{{ $title }}</h4>
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
                  <th scope="col">{{ translate("Approval Status") }}</th>
                  <th scope="col">{{ translate("Created At") }}</th>
                  <th scope="col">{{ translate("Option") }}</th>
                </tr>
              </thead>
              <tbody>
                @forelse($userTemplates as $template)
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
                                    data-route="{{route('user.template.status.update')}}"
                                    id="{{ 'status_'.$template->uid }}"
                                    name="status"/>
                            <label for="{{ 'status_'.$template->uid }}" class="toggle">
                                <span></span>
                            </label>
                        </div>
                      </td>
                    <td data-label="{{ translate('Approval Status')}}">
                        {{ $template->approval_status->badge() }}
                    </td>
                    <td>
                        {{ $template->created_at->toDayDateTimeString() }}
                    </td>
                  <td data-label={{ translate('Option')}}>
                    <div class="d-flex align-items-center gap-1">
                        <button class="icon-btn btn-ghost btn-sm success-soft circle edit-sms-template"
                                type="button"
                                 data-template-url="{{ route("user.template.update", ["uid" => $template->uid]) }}"
                                data-template-name="{{ $template->name }}"
                                data-template-message="{{ \Illuminate\Support\Arr::get($template->template_data, "message") }}"
                                data-bs-toggle="modal"
                                data-bs-target="#editSmsTemplate">
                            <i class="ri-edit-line"></i>
                            <span class="tooltiptext"> {{ translate("Edit Template") }} </span>
                        </button>
                        <button class="icon-btn btn-ghost btn-sm danger-soft circle text-danger delete-sms-template"
                                type="button"
                                data-template-url="{{ route("user.template.update", ["uid" => $template->uid]) }}"
                                data-bs-toggle="modal"
                                data-bs-target="#deleteSmsTemplate">
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

          @include('user.partials.pagination', ['paginator' => $userTemplates])
        </div>
      </div>
    @endif

    @if(request()->channel == \App\Enums\System\ChannelTypeEnum::EMAIL->value)
    <div class="card">
        <div class="card-header">
          <div class="card-header-left">
            <h4 class="card-title">{{ $title }}</h4>
          </div>
            <div class="card-header-right">
              <a class="i-btn btn--primary btn--sm" href="{{ route("user.template.create", ['channel' => request()->channel]) }}">
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
                  <th scope="col">{{ translate("Approval Status") }}</th>
                  <th scope="col">{{ translate("Created At") }}</th>
                  <th scope="col">{{ translate("Option") }}</th>
                </tr>
              </thead>
              <tbody>
                    @forelse($userTemplates as $template)
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
                                            data-route="{{route('user.template.status.update')}}"
                                            id="{{ 'status_'.$template->uid }}"
                                            name="status"/>
                                    <label for="{{ 'status_'.$template->uid }}" class="toggle">
                                        <span></span>
                                    </label>
                                </div>
                              </td>
                            <td data-label="{{ translate('Approval Status')}}">
                                {{ $template->approval_status->badge() }}
                            </td>
                            <td>
                                {{ $template->created_at->toDayDateTimeString() }}
                            </td>
                            <td data-label={{ translate('Option')}}>
                                <div class="d-flex align-items-center gap-1">
                                    <a class="icon-btn btn-ghost btn-sm success-soft circle" href="{{ route("user.template.edit", ["uid" => $template->uid]) }}">
                                        <i class="ri-edit-line"></i>
                                        <span class="tooltiptext"> {{ translate("Edit Template") }} </span>
                                    </a>
                                    <button class="icon-btn btn-ghost btn-sm danger-soft circle text-danger delete-email-template"
                                            type="button"
                                             data-url        = "{{route('user.template.destroy', ['uid' => $template->uid])}}"
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

          @include('user.partials.pagination', ['paginator' => $userTemplates])
        </div>
      </div>
    @endif

    @if(request()->channel == \App\Enums\System\ChannelTypeEnum::WHATSAPP->value)
        <div class="table-filter mb-4">
            <form action="{{route(Route::currentRouteName(), ['channel' => $channel->value, "cloud_id" => $cloudId])}}" class="filter-form">
           
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="filter-search">
                        <input type="search" value="{{request()->search}}" name="search" class="form-control" id="filter-search" placeholder="{{ translate("Search for template by name") }}" />
                        <span><i class="ri-search-line"></i></span>
                    </div>
                </div>

                <div class="col-xxl-6 col-lg-8 offset-xxl-2">
                    <div class="filter-action">
                        <div class="input-group">
                            <input type="text" class="form-control" id="datePicker" name="date" value="{{request()->input('date')}}"  placeholder="{{translate('Filter by date')}}"  aria-describedby="filterByDate">
                            <span class="input-group-text" id="filterByDate">
                                <i class="ri-calendar-2-line"></i>
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <button type="submit" class="filter-action-btn ">
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
        <div class="card">
            <div class="card-header">
                <div class="card-header-left">
                    <h4 class="card-title">{{ translate("Template List") }}</h4>
                </div>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs pill-tab mb-4 mb-4" id="userTemplateTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-0 border-0 active" id="user-node-templates-tab" data-bs-toggle="pill" data-bs-target="#user-node-templates" type="button" role="tab" aria-controls="user-node-templates" aria-selected="true">
                            <i class="ri-qr-code-line me-1"></i>{{ translate("Node QR Templates") }}
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-0 border-0" id="user-cloud-templates-tab" data-bs-toggle="pill" data-bs-target="#user-cloud-templates" type="button" role="tab" aria-controls="user-cloud-templates" aria-selected="false">
                            <i class="ri-cloud-line me-1"></i>{{ translate("Cloud API Templates") }}
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="userTemplateTabsContent">
                    {{-- Node QR Templates Tab --}}
                    <div class="tab-pane fade show active" id="user-node-templates" role="tabpanel" aria-labelledby="user-node-templates-tab">
                        <div class="mb-3">
                            <button type="button" class="i-btn btn--primary btn--sm" data-bs-toggle="modal" data-bs-target="#createUserNodeTemplateModal">
                                <i class="ri-add-line me-1"></i>{{ translate("Create Template") }}
                            </button>
                        </div>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th scope="col">{{ translate("Name") }}</th>
                                        <th scope="col">{{ translate("Message Preview") }}</th>
                                        <th scope="col">{{ translate("Has Image") }}</th>
                                        <th scope="col">{{ translate("Options") }}</th>
                                        <th scope="col">{{ translate("Status") }}</th>
                                        <th scope="col">{{ translate("Created At") }}</th>
                                        <th scope="col">{{ translate("Actions") }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($nodeTemplates ?? [] as $template)
                                        <tr>
                                            <td data-label="{{ translate('Name')}}">
                                                <p class="text-dark fw-semibold">{{ $template->name }}</p>
                                            </td>
                                            <td data-label="{{ translate('Message Preview')}}">
                                                <p class="text-muted small">{{ \Illuminate\Support\Str::limit($template->template_data['message'] ?? '', 50) }}</p>
                                            </td>
                                            <td data-label="{{ translate('Has Image')}}">
                                                @if(!empty($template->template_data['image_url']))
                                                    <span class="i-badge success-solid pill">{{ translate("Yes") }}</span>
                                                @else
                                                    <span class="i-badge secondary-solid pill">{{ translate("No") }}</span>
                                                @endif
                                            </td>
                                            <td data-label="{{ translate('Buttons')}}">
                                                <span class="i-badge info-solid pill">
                                                    {{ count($template->template_data['buttons'] ?? []) }}
                                                </span>
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
                                                            data-route="{{route('user.template.status.update')}}"
                                                            id="{{ 'status_'.$template->uid }}"
                                                            name="status"/>
                                                    <label for="{{ 'status_'.$template->uid }}" class="toggle">
                                                        <span></span>
                                                    </label>
                                                </div>
                                            </td>
                                            <td data-label="{{ translate('Created At')}}">
                                                {{ $template->created_at->toDayDateTimeString() }}
                                            </td>
                                            <td data-label="{{ translate('Options')}}">
                                                <div class="d-flex align-items-center gap-1">
                                                    <button type="button" class="icon-btn btn-ghost btn-sm success-soft circle edit-user-node-template"
                                                            data-template="{{ json_encode($template) }}"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editUserNodeTemplateModal">
                                                        <i class="ri-edit-line"></i>
                                                        <span class="tooltiptext">{{ translate("Edit") }}</span>
                                                    </button>
                                                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle delete-user-node-template"
                                                            data-id="{{ $template->id }}">
                                                        <i class="ri-delete-bin-line"></i>
                                                        <span class="tooltiptext">{{ translate("Delete") }}</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="text-muted text-center" colspan="100%">{{ translate('No Node templates found. Create one to get started!') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if(isset($nodeTemplates))
                            @include('user.partials.pagination', ['paginator' => $nodeTemplates])
                        @endif
                    </div>

                    {{-- Cloud API Templates Tab --}}
                    <div class="tab-pane fade" id="user-cloud-templates" role="tabpanel" aria-labelledby="user-cloud-templates-tab">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th scope="col">{{ translate("Name") }}</th>
                                        <th scope="col">{{ translate("Business Account") }}</th>
                                        <th scope="col">{{ translate("Language Code") }}</th>
                                        <th scope="col">{{ translate("Category") }}</th>
                                        <th scope="col">{{ translate("Status") }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($userTemplates as $template)

                                <tr class="@if($loop->even)@endif">

                                    <td data-label="{{ translate('Name')}}">
                                        <p class="text-dark fw-semibold">
                                            {{$template->name}}
                                        </p>
                                    </td>
                                    <td data-label="{{ translate('Business Account')}}">
                                        <a href="{{route('user.gateway.whatsapp.cloud.api.index')}}" class="badge badge--primary p-2">
                                            <span class="i-badge info-solid pill">
                                                {{$template->cloudApi?->name ?? 'N/A'}} <i class="ri-eye-line ms-1"></i>
                                            </span>
                                        </a>
                                    </td>
                                    <td>{{ $template->template_data['language'] ?? 'N/A' }}</td>
                                    <td>{{ $template->template_data['category'] ?? 'N/A' }}</td>
                                    <td>{{ $template->template_data['status'] ?? 'N/A' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="text-muted text-center" colspan="100%">{{ translate('No Data Found')}}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @include('user.partials.pagination', ['paginator' => $userTemplates])
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
            <form action="{{route('user.template.store')}}" method="POST" enctype="multipart/form-data">
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

  <div class="modal fade actionModal" id="deleteSmsTemplate" tabindex="-1" aria-labelledby="deleteSmsTemplate" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
        <div class="modal-header text-start">
            <span class="action-icon danger">
            <i class="bi bi-exclamation-circle"></i>
            </span>
        </div>
        <form action="{{route('user.template.destroy')}}" method="POST">
            @csrf
            <div class="modal-body">

                <input type="hidden" name="id" value="">
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
@endif

{{-- WhatsApp Node Templates Modals --}}
@if(request()->channel == \App\Enums\System\ChannelTypeEnum::WHATSAPP->value)

<!-- Create User Node Template Modal -->
<div class="modal fade" id="createUserNodeTemplateModal" tabindex="-1" aria-labelledby="createUserNodeTemplateModal" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form action="{{ route('user.template.store') }}" method="POST" id="createUserNodeTemplateForm">
        @csrf
        <input type="hidden" name="channel" value="{{ \App\Enums\System\ChannelTypeEnum::WHATSAPP->value }}">

        <div class="modal-header">
          <h5 class="modal-title">{{ translate("Create WhatsApp Template") }}</h5>
          <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
            <i class="ri-close-large-line"></i>
          </button>
        </div>

        <div class="modal-body modal-lg-custom-height">
          <div class="row g-4">
            <div class="col-12">
              <div class="form-inner">
                <label for="user_template_name" class="form-label">{{ translate('Template Name') }}<span class="text-danger">*</span></label>
                <input type="text" id="user_template_name" name="name" placeholder="{{ translate('Enter template name') }}" class="form-control" required>
              </div>
            </div>

            <div class="col-12">
              <div class="form-inner">
                <label for="user_template_message" class="form-label">{{ translate('Message Body') }}<span class="text-danger">*</span></label>
                <textarea rows="5" class="form-control" id="user_template_message" name="template_data[message]" placeholder="{{ translate('Enter message. Use variables for dynamic content') }}" required></textarea>
                <small class="text-muted">{{ translate('You can use variables like') }} @{{ '{' }}{name}}, @{{ '{' }}{phone}}, @{{ '{' }}{email}}, {{ translate('etc.') }}</small>
              </div>
            </div>

            <div class="col-12">
              <div class="form-inner">
                <label for="user_template_image_url" class="form-label">{{ translate('Image URL') }} <span class="text-muted">({{ translate('Optional') }})</span></label>
                <input type="url" id="user_template_image_url" name="template_data[image_url]" placeholder="{{ translate('https://example.com/image.jpg') }}" class="form-control">
              </div>
            </div>

            <div class="col-12">
              <div class="form-inner">
                <label class="form-label">{{ translate('Quick Reply Options') }} <span class="text-muted">({{ translate('Optional') }})</span></label>
                <div class="alert alert-warning py-2 mb-2">
                  <small><i class="ri-information-line me-1"></i>{{ translate('Note: WhatsApp has deprecated interactive buttons for QR-code based connections. Options will be sent as numbered text choices that recipients can reply to.') }}</small>
                </div>
                <div id="user-buttons-container">
                  <!-- Options will be added dynamically -->
                </div>
                <button type="button" class="i-btn btn--sm btn--secondary mt-2" id="user-add-button">
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

<!-- Edit User Node Template Modal -->
<div class="modal fade" id="editUserNodeTemplateModal" tabindex="-1" aria-labelledby="editUserNodeTemplateModal" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form action="" method="POST" id="editUserNodeTemplateForm">
        @csrf
        @method('PUT')
        <input type="hidden" name="channel" value="{{ \App\Enums\System\ChannelTypeEnum::WHATSAPP->value }}">

        <div class="modal-header">
          <h5 class="modal-title">{{ translate("Edit WhatsApp Template") }}</h5>
          <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
            <i class="ri-close-large-line"></i>
          </button>
        </div>

        <div class="modal-body modal-lg-custom-height">
          <div class="row g-4">
            <div class="col-12">
              <div class="form-inner">
                <label for="edit_user_template_name" class="form-label">{{ translate('Template Name') }}<span class="text-danger">*</span></label>
                <input type="text" id="edit_user_template_name" name="name" placeholder="{{ translate('Enter template name') }}" class="form-control" required>
              </div>
            </div>

            <div class="col-12">
              <div class="form-inner">
                <label for="edit_user_template_message" class="form-label">{{ translate('Message Body') }}<span class="text-danger">*</span></label>
                <textarea rows="5" class="form-control" id="edit_user_template_message" name="template_data[message]" placeholder="{{ translate('Enter message. Use variables for dynamic content') }}" required></textarea>
                <small class="text-muted">{{ translate('You can use variables like') }} @{{ '{' }}{name}}, @{{ '{' }}{phone}}, @{{ '{' }}{email}}, {{ translate('etc.') }}</small>
              </div>
            </div>

            <div class="col-12">
              <div class="form-inner">
                <label for="edit_user_template_image_url" class="form-label">{{ translate('Image URL') }} <span class="text-muted">({{ translate('Optional') }})</span></label>
                <input type="url" id="edit_user_template_image_url" name="template_data[image_url]" placeholder="{{ translate('https://example.com/image.jpg') }}" class="form-control">
              </div>
            </div>

            <div class="col-12">
              <div class="form-inner">
                <label class="form-label">{{ translate('Quick Reply Options') }} <span class="text-muted">({{ translate('Optional') }})</span></label>
                <div class="alert alert-warning py-2 mb-2">
                  <small><i class="ri-information-line me-1"></i>{{ translate('Note: WhatsApp has deprecated interactive buttons for QR-code based connections. Options will be sent as numbered text choices that recipients can reply to.') }}</small>
                </div>
                <div id="edit-user-buttons-container">
                  <!-- Options will be loaded dynamically -->
                </div>
                <button type="button" class="i-btn btn--sm btn--secondary mt-2" id="edit-user-add-button">
                  <i class="ri-add-line"></i> {{ translate('Add Option') }}
                </button>
              </div>
            </div>

            <div class="col-12">
              <div class="form-inner">
                <label for="edit_user_template_status" class="form-label">{{ translate('Status') }}</label>
                <select class="form-select" id="edit_user_template_status" name="status">
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

<!-- Delete User Node Template Modal -->
<div class="modal fade actionModal" id="deleteUserNodeTemplateModal" tabindex="-1" aria-labelledby="deleteUserNodeTemplateModal" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header text-start">
        <span class="action-icon danger">
          <i class="bi bi-exclamation-circle"></i>
        </span>
      </div>
      <form method="POST" id="deleteUserNodeTemplateForm">
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

@endif

@if(request()->channel == \App\Enums\System\ChannelTypeEnum::EMAIL->value)

<div class="modal fade" id="editDefaultEmailTemplate" tabindex="-1" aria-labelledby="editDefaultEmailTemplate" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered ">
        <div class="modal-content">
            <form action="{{route('user.template.store')}}" method="POST" enctype="multipart/form-data">
                @csrf

                <input type="text" name="id" hidden>
                <input type="text" name="name" hidden>
                <input type="text" name="predefined" hidden>
                <input type="text" name="type" value="{{ \App\Enums\ServiceType::EMAIL->value }}" hidden>
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
        <form action="{{route('user.template.destroy')}}" method="POST">
            @csrf
            <div class="modal-body">

                <input type="hidden" name="id">
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
@endif
@endsection
@push("script-push")

  <script>
    "use strict";

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

            $('.destroy-sms-template').on('click', function() {

            const modal = $('#deleteSmsTemplate');
            modal.find('input[name=id]').val($(this).data('template-id'));
            modal.modal('show');
            });
        }

        if("{{request()->channel == \App\Enums\System\ChannelTypeEnum::EMAIL->value}}") {

            $('.edit-default-email-template').on('click', function() {

                const modal = $('#editDefaultEmailTemplate');
                modal.find('input[name=id]').val($(this).data('template-id'));
                modal.find('input[name=name]').val($(this).data('template-name'));
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

            $('.destroy-email-template').on('click', function() {

                const modal = $('#deleteEmailTemplate');
                modal.find('input[name=id]').val($(this).data('template-id'));
                modal.modal('show');
            });
        }

        // WhatsApp Node Template Management
        if("{{request()->channel == \App\Enums\System\ChannelTypeEnum::WHATSAPP->value}}") {
            let userButtonIndex = 0;
            let editUserButtonIndex = 0;

            // Add button functionality for create modal
            $('#user-add-button').on('click', function() {
                userButtonIndex++;
                let buttonHtml = '<div class="button-item card mb-2 p-3" id="user-button-' + userButtonIndex + '">';
                buttonHtml += '<div class="row g-3">';
                buttonHtml += '<div class="col-md-4">';
                buttonHtml += '<label class="form-label">{{ translate("Button Type") }}</label>';
                buttonHtml += '<select class="form-select" name="template_data[buttons][' + userButtonIndex + '][type]">';
                buttonHtml += '<option value="url">{{ translate("URL") }}</option>';
                buttonHtml += '<option value="phone">{{ translate("Phone") }}</option>';
                buttonHtml += '<option value="quick_reply">{{ translate("Quick Reply") }}</option>';
                buttonHtml += '</select>';
                buttonHtml += '</div>';
                buttonHtml += '<div class="col-md-4">';
                buttonHtml += '<label class="form-label">{{ translate("Button Text") }}</label>';
                buttonHtml += '<input type="text" class="form-control" name="template_data[buttons][' + userButtonIndex + '][text]" placeholder="{{ translate("Button text") }}" required>';
                buttonHtml += '</div>';
                buttonHtml += '<div class="col-md-3 button-value-field">';
                buttonHtml += '<label class="form-label">{{ translate("URL/Phone") }}</label>';
                buttonHtml += '<input type="text" class="form-control" name="template_data[buttons][' + userButtonIndex + '][value]" placeholder="{{ translate("Value") }}">';
                buttonHtml += '</div>';
                buttonHtml += '<div class="col-md-1 d-flex align-items-end">';
                buttonHtml += '<button type="button" class="i-btn btn--danger btn--sm remove-button" data-button-id="user-button-' + userButtonIndex + '">';
                buttonHtml += '<i class="ri-delete-bin-line"></i>';
                buttonHtml += '</button>';
                buttonHtml += '</div>';
                buttonHtml += '</div>';
                buttonHtml += '</div>';
                $('#user-buttons-container').append(buttonHtml);
            });

            // Add button functionality for edit modal
            $('#edit-user-add-button').on('click', function() {
                editUserButtonIndex++;
                let buttonHtml = '<div class="button-item card mb-2 p-3" id="edit-user-button-' + editUserButtonIndex + '">';
                buttonHtml += '<div class="row g-3">';
                buttonHtml += '<div class="col-md-4">';
                buttonHtml += '<label class="form-label">{{ translate("Button Type") }}</label>';
                buttonHtml += '<select class="form-select" name="template_data[buttons][' + editUserButtonIndex + '][type]">';
                buttonHtml += '<option value="url">{{ translate("URL") }}</option>';
                buttonHtml += '<option value="phone">{{ translate("Phone") }}</option>';
                buttonHtml += '<option value="quick_reply">{{ translate("Quick Reply") }}</option>';
                buttonHtml += '</select>';
                buttonHtml += '</div>';
                buttonHtml += '<div class="col-md-4">';
                buttonHtml += '<label class="form-label">{{ translate("Button Text") }}</label>';
                buttonHtml += '<input type="text" class="form-control" name="template_data[buttons][' + editUserButtonIndex + '][text]" placeholder="{{ translate("Button text") }}" required>';
                buttonHtml += '</div>';
                buttonHtml += '<div class="col-md-3 button-value-field">';
                buttonHtml += '<label class="form-label">{{ translate("URL/Phone") }}</label>';
                buttonHtml += '<input type="text" class="form-control" name="template_data[buttons][' + editUserButtonIndex + '][value]" placeholder="{{ translate("Value") }}">';
                buttonHtml += '</div>';
                buttonHtml += '<div class="col-md-1 d-flex align-items-end">';
                buttonHtml += '<button type="button" class="i-btn btn--danger btn--sm remove-button" data-button-id="edit-user-button-' + editUserButtonIndex + '">';
                buttonHtml += '<i class="ri-delete-bin-line"></i>';
                buttonHtml += '</button>';
                buttonHtml += '</div>';
                buttonHtml += '</div>';
                buttonHtml += '</div>';
                $('#edit-user-buttons-container').append(buttonHtml);
            });

            // Remove button
            $(document).on('click', '.remove-button', function() {
                const buttonId = $(this).data('button-id');
                $('#' + buttonId).remove();
            });

            // Edit User Node Template
            $(document).on('click', '.edit-user-node-template', function() {
                const template = $(this).data('template');
                const modal = $('#editUserNodeTemplateModal');

                // Set form action
                $('#editUserNodeTemplateForm').attr('action', '{{ route("user.template.update", ":uid") }}'.replace(':uid', template.uid));

                // Fill form fields
                $('#edit_user_template_name').val(template.name);
                $('#edit_user_template_message').val(template.template_data.message || '');
                $('#edit_user_template_image_url').val(template.template_data.image_url || '');
                $('#edit_user_template_status').val(template.status);

                // Clear and reload buttons
                $('#edit-user-buttons-container').empty();
                editUserButtonIndex = 0;

                if (template.template_data.buttons && template.template_data.buttons.length > 0) {
                    template.template_data.buttons.forEach(function(button, index) {
                        editUserButtonIndex = index;
                        let buttonHtml = '<div class="button-item card mb-2 p-3" id="edit-user-button-' + index + '">';
                        buttonHtml += '<div class="row g-3">';
                        buttonHtml += '<div class="col-md-4">';
                        buttonHtml += '<label class="form-label">{{ translate("Button Type") }}</label>';
                        buttonHtml += '<select class="form-select" name="template_data[buttons][' + index + '][type]">';
                        buttonHtml += '<option value="url"' + (button.type === 'url' ? ' selected' : '') + '>{{ translate("URL") }}</option>';
                        buttonHtml += '<option value="phone"' + (button.type === 'phone' ? ' selected' : '') + '>{{ translate("Phone") }}</option>';
                        buttonHtml += '<option value="quick_reply"' + (button.type === 'quick_reply' ? ' selected' : '') + '>{{ translate("Quick Reply") }}</option>';
                        buttonHtml += '</select>';
                        buttonHtml += '</div>';
                        buttonHtml += '<div class="col-md-4">';
                        buttonHtml += '<label class="form-label">{{ translate("Button Text") }}</label>';
                        buttonHtml += '<input type="text" class="form-control" name="template_data[buttons][' + index + '][text]" value="' + (button.text || '') + '" placeholder="{{ translate("Button text") }}" required>';
                        buttonHtml += '</div>';
                        buttonHtml += '<div class="col-md-3 button-value-field">';
                        buttonHtml += '<label class="form-label">{{ translate("URL/Phone") }}</label>';
                        buttonHtml += '<input type="text" class="form-control" name="template_data[buttons][' + index + '][value]" value="' + (button.value || '') + '" placeholder="{{ translate("Value") }}">';
                        buttonHtml += '</div>';
                        buttonHtml += '<div class="col-md-1 d-flex align-items-end">';
                        buttonHtml += '<button type="button" class="i-btn btn--danger btn--sm remove-button" data-button-id="edit-user-button-' + index + '">';
                        buttonHtml += '<i class="ri-delete-bin-line"></i>';
                        buttonHtml += '</button>';
                        buttonHtml += '</div>';
                        buttonHtml += '</div>';
                        buttonHtml += '</div>';
                        $('#edit-user-buttons-container').append(buttonHtml);
                    });
                }

                modal.modal('show');
            });

            // Delete User Node Template
            $(document).on('click', '.delete-user-node-template', function() {
                const templateId = $(this).data('id');
                const modal = $('#deleteUserNodeTemplateModal');
                $('#deleteUserNodeTemplateForm').attr('action', '{{ route("user.template.destroy", ":id") }}'.replace(':id', templateId));
                modal.modal('show');
            });
        }

    });
  </script>
@endpush

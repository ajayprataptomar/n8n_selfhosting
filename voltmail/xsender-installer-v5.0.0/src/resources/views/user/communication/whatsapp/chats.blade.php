@push("style-include")
  <link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
  <link rel="stylesheet" href="{{ asset('assets/theme/global/css/chat-media.css')}}">
  <style>
    /* Clean Professional Chat UI - Fixed */
    :root {
      --chat-primary: #075e54;
      --chat-accent: #128c7e;
      --chat-bg: #f0f2f5;
      --chat-bubble-out: #d9fdd3;
      --chat-bubble-in: #ffffff;
      --chat-text: #111b21;
      --chat-text-secondary: #667781;
      --chat-border: #e9edef;
    }

    /* Fix scroll containment */
    .chat-wrapper {
      height: calc(100vh - 60px);
      overflow: hidden;
    }
    .chat-left {
      height: 100%;
      overflow: hidden;
      display: flex;
    }
    .chat-sidebar-wrapper {
      height: 100%;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }
    .chat-sidebar-content {
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
    }
    .chat-body {
      height: 100%;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }
    .chat-interface {
      height: 100%;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    .chatting-body {
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
      background-color: #efeae2;
      background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23d4cfc6' fill-opacity='0.15'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
      padding: 16px 0;
    }
    .chatting {
      list-style: none;
      margin: 0;
      padding: 0;
    }

    /* Message Input Area */
    .message-box-wrapper {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 0;
    }
    .template-btn, .media-btn {
      background: #f0f2f5;
      border: none;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: background 0.2s;
      color: #54656f;
      flex-shrink: 0;
    }
    .template-btn:hover, .media-btn:hover {
      background: #e0e0e0;
    }
    .template-btn i, .media-btn i {
      font-size: 20px;
    }
    .white-space-pre-line {
      white-space: pre-line;
    }

    /* Device Selector */
    .device-selector-wrapper {
      padding: 10px 12px;
      background: #fff;
      border-bottom: 1px solid var(--chat-border);
      flex-shrink: 0;
    }
    .device-selector {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .device-selector label {
      font-size: 12px;
      font-weight: 500;
      color: var(--chat-text-secondary);
      white-space: nowrap;
    }
    .device-selector select {
      flex: 1;
      padding: 6px 10px;
      border-radius: 6px;
      border: 1px solid var(--chat-border);
      background: #fff;
      font-size: 12px;
      color: var(--chat-text);
      cursor: pointer;
    }
    .device-selector select:focus {
      border-color: var(--chat-accent);
      outline: none;
    }

    /* Device Badge */
    .device-badge {
      display: inline-flex;
      align-items: center;
      gap: 3px;
      padding: 2px 6px;
      font-size: 9px;
      font-weight: 500;
      border-radius: 3px;
      background: #e8f5e9;
      color: var(--chat-accent);
      margin-top: 2px;
    }
    .device-badge i {
      font-size: 9px;
    }

    /* Contact List */
    .chat-contacts {
      list-style: none;
      margin: 0;
      padding: 0;
    }
    .chat-contact {
      display: flex;
      align-items: flex-start;
      padding: 12px;
      cursor: pointer;
      transition: background 0.15s;
      border-bottom: 1px solid #f0f0f0;
      gap: 10px;
    }
    .chat-contact:hover {
      background: #f5f6f6;
    }
    .chat-contact.active {
      background: #f0f2f5;
    }
    .chat-contact.unread {
      background: #f0faf8;
    }
    .chat-contact-info {
      flex: 1;
      min-width: 0;
      overflow: hidden;
    }
    .chat-contact .contact-name {
      font-weight: 500;
      color: var(--chat-text);
      font-size: 14px;
      margin-bottom: 2px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .chat-contact .contact-number {
      font-size: 12px;
      color: var(--chat-text-secondary);
      display: block;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .chat-contact .last-message {
      font-size: 12px;
      color: var(--chat-text-secondary);
      display: block;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      margin-top: 2px;
    }
    .chat-contact-meta {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 4px;
      flex-shrink: 0;
    }
    .chat-contact .time {
      font-size: 11px;
      color: var(--chat-text-secondary);
    }
    .chat-contact .unread-count {
      background: var(--chat-accent);
      color: #fff;
      font-size: 10px;
      min-width: 18px;
      height: 18px;
      padding: 0 5px;
      border-radius: 9px;
      font-weight: 500;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* Chat Header */
    .chat-body-header {
      background: #fff;
      border-bottom: 1px solid var(--chat-border);
      padding: 12px 16px;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .chat-body-header .left {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .chat-body-header .chat-user-info p {
      font-weight: 600;
      color: var(--chat-text);
      font-size: 15px;
      margin: 0;
    }
    .chat-body-header .chat-user-info span {
      font-size: 12px;
      color: var(--chat-text-secondary);
    }
    .name-number h5 {
      font-weight: 600;
      color: var(--chat-text);
      font-size: 15px;
      margin: 0 0 2px 0;
    }
    .name-number span {
      font-size: 12px;
      color: var(--chat-text-secondary);
    }

    /* Message Bubbles - Professional WhatsApp Style */
    .message {
      margin-bottom: 2px;
      padding: 2px 16px;
      display: flex;
    }
    .message .message-wrapper {
      max-width: 65%;
      min-width: 80px;
      display: inline-flex;
      flex-direction: column;
    }
    .message .message-body {
      padding: 8px 12px;
      border-radius: 8px;
      font-size: 14px;
      line-height: 1.45;
      position: relative;
      box-shadow: 0 1px 0.5px rgba(0,0,0,0.13);
      display: inline-block;
      min-width: fit-content;
    }
    .message .message-body p {
      margin: 0;
      color: var(--chat-text);
      word-wrap: break-word;
      word-break: break-word;
      white-space: pre-wrap;
    }
    /* Incoming messages (left) */
    .message.left {
      justify-content: flex-start;
    }
    .message.left .message-body {
      background: var(--chat-bubble-in);
      border-top-left-radius: 0;
    }
    /* Outgoing messages (right) */
    .message.right {
      justify-content: flex-end;
    }
    .message.right .message-wrapper {
      align-items: flex-end;
    }
    .message.right .message-body {
      background: var(--chat-bubble-out);
      border-top-right-radius: 0;
    }
    .message .message-footer {
      display: inline-flex;
      align-items: center;
      justify-content: flex-end;
      gap: 3px;
      float: right;
      margin-left: 8px;
      margin-top: 4px;
      position: relative;
      bottom: -3px;
    }
    .message .message-time {
      font-size: 11px;
      color: rgba(0,0,0,0.45);
      white-space: nowrap;
    }
    .message.failed .message-body {
      background: #ffebee !important;
      border: 1px solid #ffcdd2;
    }

    /* Status Icons */
    .message .ri-check-line,
    .message .ri-check-double-line {
      font-size: 16px;
      color: rgba(0,0,0,0.4);
    }
    .message .ri-check-double-line[title="Read"] {
      color: #53bdeb !important;
    }
    .message .ri-time-line {
      font-size: 14px;
      color: rgba(0,0,0,0.4);
    }
    .message .ri-error-warning-line {
      color: #f44336;
      font-size: 14px;
    }

    /* Download Button */
    .message .download-btn {
      padding: 4px 8px;
      font-size: 11px;
      border-radius: 4px;
      background: rgba(0,0,0,0.06);
      border: none;
      color: var(--chat-text-secondary);
      cursor: pointer;
      margin-top: 4px;
    }
    .message .download-btn:hover {
      background: rgba(0,0,0,0.1);
    }

    /* Media Preview */
    .media-preview-wrapper {
      display: none;
      padding: 8px 12px;
      background: #fff;
      border-top: 1px solid var(--chat-border);
      flex-shrink: 0;
    }
    .media-preview-wrapper.active {
      display: block;
    }
    .media-preview {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 10px;
      background: #f5f6f6;
      border-radius: 8px;
    }
    .media-preview-thumb {
      width: 44px;
      height: 44px;
      border-radius: 6px;
      background: #e0e0e0;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .media-preview-thumb i {
      font-size: 20px;
      color: var(--chat-text-secondary);
    }
    .media-preview-info {
      flex: 1;
      min-width: 0;
    }
    .media-preview-name {
      font-size: 13px;
      font-weight: 500;
      color: var(--chat-text);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .media-preview-size {
      font-size: 11px;
      color: var(--chat-text-secondary);
    }
    .media-preview-remove {
      background: transparent;
      border: none;
      color: #f44336;
      cursor: pointer;
      padding: 6px;
      font-size: 18px;
      border-radius: 50%;
      flex-shrink: 0;
    }
    .media-preview-remove:hover {
      background: #ffebee;
    }

    /* Media Dropdown */
    .media-dropdown {
      position: relative;
    }
    .media-dropdown-menu {
      position: absolute;
      bottom: 100%;
      left: 0;
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.15);
      padding: 4px 0;
      min-width: 150px;
      display: none;
      z-index: 100;
      margin-bottom: 8px;
    }
    .media-dropdown-menu.show {
      display: block;
    }
    .media-dropdown-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 12px;
      cursor: pointer;
      transition: background 0.15s;
      border: none;
      background: none;
      width: 100%;
      text-align: left;
      font-size: 13px;
      color: var(--chat-text);
    }
    .media-dropdown-item:hover {
      background: #f5f6f6;
    }
    .media-dropdown-item i {
      font-size: 18px;
      width: 20px;
      text-align: center;
    }
    .media-dropdown-item.image i { color: #4caf50; }
    .media-dropdown-item.video i { color: #e91e63; }
    .media-dropdown-item.audio i { color: #9c27b0; }
    .media-dropdown-item.document i { color: #2196f3; }

    /* Chat Footer */
    .chat-body-footer {
      background: #f0f2f5;
      border-top: 1px solid var(--chat-border);
      padding: 8px 12px;
      flex-shrink: 0;
    }
    .chat-form {
      background: #fff;
      border-radius: 8px;
      display: flex;
      align-items: center;
    }
    .chat-form input[type="text"],
    .chat-form input[name="body"] {
      flex: 1;
      border: none;
      background: transparent;
      padding: 10px 12px;
      font-size: 14px;
      color: var(--chat-text);
    }
    .chat-form input[type="text"]:focus,
    .chat-form input[name="body"]:focus {
      outline: none;
    }
    .chat-form input::placeholder {
      color: #8696a0;
    }

    /* Send Button */
    .chat-submit-btn,
    .chat-body-footer button[type="submit"],
    button.i-btn.btn--primary.btn--sm[type="submit"] {
      background: var(--chat-accent) !important;
      border: none !important;
      border-radius: 50% !important;
      width: 40px !important;
      height: 40px !important;
      min-width: 40px !important;
      color: #fff !important;
      cursor: pointer;
      transition: background 0.2s;
      display: flex !important;
      align-items: center;
      justify-content: center;
      padding: 0 !important;
      margin: 4px;
    }
    .chat-submit-btn:hover,
    .chat-body-footer button[type="submit"]:hover {
      background: var(--chat-primary) !important;
    }

    /* Sidebar Header */
    .chat-sidebar-header {
      padding: 12px;
      background: #fff;
      border-bottom: 1px solid var(--chat-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
    }
    .chat-sidebar-header h5 {
      font-weight: 600;
      color: var(--chat-text);
      font-size: 15px;
      margin: 0;
    }

    /* Chat Media */
    .chat-media-image {
      max-width: 250px;
      max-height: 250px;
      border-radius: 6px;
      margin-top: 4px;
      display: block;
    }
    .chat-media-video {
      max-width: 250px;
      border-radius: 6px;
      margin-top: 4px;
    }
    .chat-media-audio {
      width: 220px;
      margin-top: 4px;
    }
    .chat-media-document {
      border-radius: 6px;
      margin-top: 4px;
    }

    /* No Conversation State */
    #no-conversation-state {
      background: var(--chat-bg);
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: var(--chat-text-secondary);
    }
    #no-conversation-state i {
      font-size: 64px;
      color: #d0d0d0;
      margin-bottom: 16px;
    }
    #no-conversation-state h4 {
      margin: 0 0 8px;
      color: var(--chat-text);
      font-weight: 500;
    }

    /* Template Modal */
    .template-modal .template-item {
      padding: 10px;
      border: 1px solid var(--chat-border);
      border-radius: 6px;
      margin-bottom: 8px;
      cursor: pointer;
      transition: background 0.15s;
      background: #fff;
    }
    .template-modal .template-item:hover {
      background: #f5f6f6;
    }
    .template-modal .template-item h6 {
      margin-bottom: 4px;
      color: var(--chat-text);
      font-weight: 500;
      font-size: 13px;
    }
    .template-modal .template-item p {
      margin-bottom: 0;
      color: var(--chat-text-secondary);
      font-size: 12px;
    }

    /* Load More Button */
    .load-more-btn {
      display: block;
      width: calc(100% - 24px);
      margin: 8px 12px;
      padding: 8px;
      background: #fff;
      border: 1px solid var(--chat-border);
      border-radius: 6px;
      color: var(--chat-text-secondary);
      font-size: 13px;
      cursor: pointer;
      text-align: center;
    }
    .load-more-btn:hover {
      background: #f5f6f6;
    }

    /* Loader */
    .chat-loader {
      display: flex;
      justify-content: center;
      padding: 20px;
    }
    .chat-loader .spinner {
      width: 24px;
      height: 24px;
      border: 2px solid var(--chat-border);
      border-top-color: var(--chat-accent);
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* Tab Content */
    .tab-content {
      flex: 1;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }
    .tab-pane {
      height: 100%;
      display: flex;
      flex-direction: column;
    }
    .tab-pane.show {
      display: flex !important;
    }
  </style>
@endpush
@extends('user.layouts.app')
@section('panel')

 <main class="main-body p-0">
    <div class="container-fluid px-0 main-content">
      <div class="chat-wrapper">
        <!-- ==========Sidebar Left========== -->
        <div class="chat-left">
          <div class="chat-left-sidebar">
            <div class="nav chat-menus" role="tablist" aria-orientation="vertical">
              <div class="chat-menu">
                <div class="nav-item" role="presentation" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="All Chats">
                  <a class="nav-link active" data-bs-toggle="tab" href="#chats" role="tab" aria-selected="true" data-type="all">
                    <i class="ri-question-answer-line"></i>
                  </a>
                </div>
                <div class="nav-item" role="presentation" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Pending">
                  <a class="nav-link" data-bs-toggle="tab" href="#pending" role="tab" aria-selected="false" data-type="pending">
                    <i class="ri-hourglass-line"></i>
                  </a>
                </div>
              </div>
            </div>
          </div>
          <div class="chat-sidebar-wrapper">
            {{-- Device Selector --}}
            @if($devices->count() > 0)
            <div class="device-selector-wrapper">
              <div class="device-selector">
                <label for="device-filter"><i class="ri-smartphone-line me-1"></i>{{ translate("Device") }}:</label>
                <select id="device-filter" class="form-select form-select-sm">
                  <option value="all">{{ translate("All Devices") }} ({{ $devices->count() }})</option>
                  @foreach($devices as $device)
                    <option value="{{ $device['id'] }}">{{ $device['name'] }} {{ $device['address'] ? '('.$device['address'].')' : '' }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            @endif
            <div class="tab-content h-100">
              <div class="tab-pane fade show active" id="chats" role="tabpanel" aria-labelledby="chats-tab" tabindex="0">
                <div class="chat-sidebar-header">
                  <h5>{{ translate("All Chats") }}</h5>
                  <div class="d-flex align-items-center gap-lg-3 gap-2">
                    <div class="dropdown dropdown-search">
                      <button class="icon-btn bg-transparent fs-20 text-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-offset="10,10">
                        <i class="ri-search-line"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end p-2">
                        <form action="#" class="dropdown-form">
                          <i class="ri-search-line"></i>
                          <input type="search" class="form-control" id="conversation-search">
                        </form>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="chat-sidebar-content">
                  <div class="chat-loader" id="conversations-loader" style="display: none;">
                    <div class="spinner"></div>
                  </div>
                  <ul class="chat-contacts" id="conversations-list"></ul>
                  <button class="load-more-btn" id="load-more-conversations" style="display: none;">{{ translate("Load More") }}</button>
                </div>
              </div>
              <!-- Pending tab -->
              <div class="tab-pane fade" id="pending" role="tabpanel" aria-labelledby="pending-tab" tabindex="0">
                <div class="chat-sidebar-header">
                  <h5>{{ translate("Pending") }}</h5>
                  <div class="d-flex align-items-center gap-lg-3 gap-2">
                    <div class="dropdown dropdown-search">
                      <button class="icon-btn bg-transparent fs-20 text-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-offset="10,10">
                        <i class="ri-search-line"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end p-2">
                        <form action="#" class="dropdown-form">
                          <i class="ri-search-line"></i>
                          <input type="search" class="form-control" id="pending-search">
                        </form>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="chat-sidebar-content">
                  <div class="chat-loader" id="pending-loader" style="display: none;">
                    <div class="spinner"></div>
                  </div>
                  <ul class="chat-contacts" id="pending-list"></ul>
                  <button class="load-more-btn" id="load-more-pending" style="display: none;">{{ translate("Load More") }}</button>
                </div>
              </div>
              <!-- Profile tab (preserved from old) -->
              <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab" tabindex="0">
                <div class="chat-sidebar-content">
                  <ul class="chat-contacts" id="profile-list"></ul>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ==========Chat Content========== -->
        <div class="chat-body">
          <div class="no-conversation-selected" id="no-conversation-state" style="display: flex;">
            <i class="ri-chat-3-line empty-chat-icon"></i>
            <h4>{{ translate("Select a conversation") }}</h4>
            <p class="text-muted">{{ translate("Choose a conversation from the sidebar to start messaging") }}</p>
            <button class="show-contact-sidebar i-btn btn--primary btn--sm mt-4 d-lg-none d-block">
                  {{ translate("Show Chat List") }}
                </button>
          </div>
          <div class="chat-interface" id="chat-interface" style="display: none;">
            <div class="chat-body-header">
              <div class="left">
                <button class="show-contact-sidebar">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="m22 11h-17.586l5.293-5.293a1 1 0 1 0 -1.414-1.414l-7 7a1 1 0 0 0 0 1.414l7 7a1 1 0 0 0 1.414-1.414l-5.293-5.293h17.586a1 1 0 0 0 0-2z" />
                  </svg>
                </button>
                <div class="chat-user-wrapper show-user-btn" role="button">
                  <div class="chat-user-info">
                    <p id="contact-name"></p>
                    <span id="contact-number"></span>
                  </div>
                </div>
              </div>
              <div class="right">
                <div class="d-flex align-items-center gap-lg-3 gap-2">
                  <div class="dropdown dropdown-search">
                    <button class="icon-btn bg-transparent fs-20 text-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-offset="10,10">
                      <i class="ri-search-line"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-2">
                      <form action="#" class="dropdown-form">
                        <i class="ri-search-line"></i>
                        <input type="search" class="form-control" id="message-search">
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="chatting-body">
              <div class="chat-loader" id="messages-loader" style="display: none;">
                <div class="spinner"></div>
              </div>
              <ul class="chatting" id="messages-list"></ul>
              <button class="load-more-btn" id="load-more-messages" style="display: none;">{{ translate("Load More") }}</button>
            </div>
            <div class="chat-body-footer">
              <div class="nav-main-chat">
                <ul>
                  <li>
                    <button class="active">{{ translate("Reply") }}</button>
                  </li>
                </ul>
              </div>
              {{-- Media Preview Area --}}
              <div class="media-preview-wrapper" id="media-preview-wrapper">
                <div class="media-preview">
                  <div class="media-preview-thumb" id="media-preview-thumb">
                    <i class="ri-file-line"></i>
                  </div>
                  <div class="media-preview-info">
                    <div class="media-preview-name" id="media-preview-name">filename.jpg</div>
                    <div class="media-preview-size" id="media-preview-size">2.5 MB</div>
                  </div>
                  <button type="button" class="media-preview-remove" id="media-remove-btn" title="{{ translate("Remove") }}">
                    <i class="ri-close-line"></i>
                  </button>
                </div>
              </div>
              <div class="message-box-wrapper">
                {{-- Media Upload Button --}}
                <div class="media-dropdown">
                  <button type="button" class="media-btn" id="media-dropdown-btn" title="{{ translate("Attach Media") }}">
                    <i class="ri-attachment-2"></i>
                  </button>
                  <div class="media-dropdown-menu" id="media-dropdown-menu">
                    <button type="button" class="media-dropdown-item image" data-type="image" data-accept="image/jpeg,image/png,image/gif,image/webp">
                      <i class="ri-image-line"></i>
                      <span>{{ translate("Photo") }}</span>
                    </button>
                    <button type="button" class="media-dropdown-item video" data-type="video" data-accept="video/mp4,video/3gpp">
                      <i class="ri-video-line"></i>
                      <span>{{ translate("Video") }}</span>
                    </button>
                    <button type="button" class="media-dropdown-item audio" data-type="audio" data-accept="audio/mpeg,audio/ogg,audio/amr">
                      <i class="ri-music-line"></i>
                      <span>{{ translate("Audio") }}</span>
                    </button>
                    <button type="button" class="media-dropdown-item document" data-type="document" data-accept=".pdf,.doc,.docx,.xls,.xlsx">
                      <i class="ri-file-text-line"></i>
                      <span>{{ translate("Document") }}</span>
                    </button>
                  </div>
                </div>
                {{-- Template Button --}}
                <button type="button" class="template-btn" id="chat-template-btn" title="{{ translate("Use Template") }}">
                  <i class="ri-layout-fill"></i>
                </button>
                <form action="{{ route('user.communication.whatsapp.chats.send') }}" class="chat-form" method="POST" id="message-form" enctype="multipart/form-data">
                  @csrf
                  <input type="hidden" name="conversation_id" id="conversation_id" value="">
                  <input type="hidden" name="media_type" id="media_type" value="">
                  <input type="file" name="media" id="media-input" style="display: none;" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx">
                  <input type="text" name="body" placeholder="{{ translate("Type a message") }}" id="message-input" />
                  <button type="submit" class="chat-submit-btn">
                    <i class="ri-send-plane-fill"></i>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- Template Modal -->
  <div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ translate("Select Template") }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body template-modal">
          @forelse($templates ?? [] as $template)
            <div class="template-item" data-message="{{ $template->template_data['message'] ?? '' }}">
              <h6>{{ $template->name }}</h6>
              <p>{{ \Illuminate\Support\Str::limit($template->template_data['message'] ?? '', 100) }}</p>
            </div>
          @empty
            <p class="text-center text-muted">{{ translate("No templates available") }}</p>
          @endforelse
        </div>
      </div>
    </div>
  </div>
@endsection
@push("script-include")
  <script src="{{asset('assets/theme/global/js/chat.js')}}"></script>
  <script src="{{asset('assets/theme/global/js/select2.min.js')}}"></script>
@endpush
@push('script-push')
  <script>
    // Template selector functionality
    $(document).ready(function() {
      const templateModal = new bootstrap.Modal(document.getElementById('templateModal'));

      // Open template modal
      $('#chat-template-btn').on('click', function() {
        templateModal.show();
      });

      // Handle template selection
      $('.template-item').on('click', function() {
        const message = $(this).data('message');
        $('#message-input').val(message);
        templateModal.hide();
      });
    });
  </script>

  @include('user.communication.whatsapp.chats.js.enhanced-script', [
    'getConversationsRoute' => route('user.communication.whatsapp.chats.conversations'),
    'showRoute'             => route('user.communication.whatsapp.chats.show', ['conversation' => ':id']),
    'searchMessagesRoute'   => route('user.communication.whatsapp.chats.search-messages', ['conversation' => ':id']),
    'loadMoreMessagesRoute' => route('user.communication.whatsapp.chats.load-more-messages', ['conversation' => ':id'])
  ])
@endpush  
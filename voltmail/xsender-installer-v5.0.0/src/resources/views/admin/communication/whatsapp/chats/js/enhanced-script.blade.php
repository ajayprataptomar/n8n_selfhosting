<script>
document.addEventListener('DOMContentLoaded', function() {
  // Configuration
  const config = {
    getConversationsRoute: '{{ $getConversationsRoute }}',
    showRoute: '{{ $showRoute }}'.replace(':id', '__ID__'),
    searchMessagesRoute: '{{ $searchMessagesRoute }}'.replace(':id', '__ID__'),
    loadMoreMessagesRoute: '{{ $loadMoreMessagesRoute }}'.replace(':id', '__ID__'),
    // Auto-refresh settings
    conversationRefreshInterval: 10000, // Refresh conversations every 10 seconds
    messageRefreshInterval: 5000,       // Refresh messages every 5 seconds
    enableAutoRefresh: true              // Enable/disable auto-refresh
  };

  // State management
  let currentConversationId = null;
  let currentConversationType = 'all';
  let currentDeviceId = 'all'; // Device filter state
  let conversationsPage = 1;
  let messagesPage = 1;
  let isLoadingConversations = false;
  let isLoadingMessages = false;
  let hasMoreConversations = true;
  let hasMoreMessages = true;
  let searchTimeout = null;
  let conversationRefreshTimer = null;
  let messageRefreshTimer = null;
  let lastMessageId = null;
  let isPageVisible = true;
  let selectedMediaFile = null; // Media upload state
  let selectedMediaType = null;
  let pendingTempMessages = new Map(); // Track temp messages to prevent duplicates
  let isSendingMessage = false; // Prevent duplicate sends

  // DOM elements
  const elements = {
    conversationsList: document.getElementById('conversations-list'),
    pendingList: document.getElementById('pending-list'),
    conversationsLoader: document.getElementById('conversations-loader'),
    pendingLoader: document.getElementById('pending-loader'),
    messagesLoader: document.getElementById('messages-loader'),
    messagesList: document.getElementById('messages-list'),
    loadMoreConversations: document.getElementById('load-more-conversations'),
    loadMorePending: document.getElementById('load-more-pending'),
    loadMoreMessages: document.getElementById('load-more-messages'),
    conversationSearch: document.getElementById('conversation-search'),
    pendingSearch: document.getElementById('pending-search'),
    messageSearch: document.getElementById('message-search'),
    messageForm: document.getElementById('message-form'),
    messageInput: document.getElementById('message-input'),
    conversationInput: document.getElementById('conversation_id'),
    contactName: document.getElementById('contact-name'),
    contactNumber: document.getElementById('contact-number'),
    noConversationState: document.getElementById('no-conversation-state'),
    chatInterface: document.getElementById('chat-interface'),
    chatBody: document.querySelector('.chatting-body'),
    // Device filter elements
    deviceFilter: document.getElementById('device-filter'),
    // Media upload elements
    mediaDropdownBtn: document.getElementById('media-dropdown-btn'),
    mediaDropdownMenu: document.getElementById('media-dropdown-menu'),
    mediaInput: document.getElementById('media-input'),
    mediaTypeInput: document.getElementById('media_type'),
    mediaPreviewWrapper: document.getElementById('media-preview-wrapper'),
    mediaPreviewThumb: document.getElementById('media-preview-thumb'),
    mediaPreviewName: document.getElementById('media-preview-name'),
    mediaPreviewSize: document.getElementById('media-preview-size'),
    mediaRemoveBtn: document.getElementById('media-remove-btn')
  };

  // SVG for unsupported files
  const unsupportedFileSvg = `
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" fill="#e0e0e0"/>
      <polyline points="14,2 14,8 20,8" fill="#c0c0c0"/>
      <line x1="16" y1="13" x2="8" y2="13" stroke="#666" stroke-width="2"/>
      <line x1="16" y1="17" x2="8" y2="17" stroke="#666" stroke-width="2"/>
      <polyline points="10,9 9,9 8,9" stroke="#666" stroke-width="2"/>
    </svg>
  `;

  // Utility functions
  function getFileExtension(url) {
    return url.split('.').pop().toLowerCase();
  }

  function getFilenameFromUrl(url) {
    return url.split('/').pop();
  }

  function formatTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString('en-US', { 
      hour: '2-digit', 
      minute: '2-digit', 
      hour12: true 
    });
  }

  function formatContactName(contact) {
    if (!contact) return 'Unknown Contact';
    const fullName = `${contact.first_name || ''} ${contact.last_name || ''}`.trim();
    return fullName || contact.whatsapp_contact || contact.email_contact || 'Unknown Contact';
  }

  function showLoader(loader) {
    if (loader) loader.style.display = 'block';
  }

  function hideLoader(loader) {
    if (loader) loader.style.display = 'none';
  }

  function debounce(func, wait) {
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(searchTimeout);
        func(...args);
      };
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(later, wait);
    };
  }

  // Render functions
  function renderMedia(fileInfo) {
    if (!fileInfo || !Array.isArray(fileInfo) || fileInfo.length === 0) return '';

    const url = fileInfo[0];
    const filename = getFilenameFromUrl(url);
    
    if (url.match(/\.(jpg|jpeg|png|gif|webp)$/i)) {
      return `<img src="${url}" alt="Media" class="chat-media chat-media-image" />`;
    } else if (url.match(/\.(pdf|doc|docx|xls|xlsx|ppt|pptx)$/i)) {
      return `<iframe src="${url}" class="chat-media chat-media-document" style="width: 100%; height: 300px;" frameborder="0"></iframe>`;
    } else if (url.match(/\.(aac|amr|mp3|m4a|wav|ogg)$/i)) {
      return `<audio controls class="chat-media chat-media-audio"><source src="${url}" type="audio/mpeg"></audio>`;
    } else if (url.match(/\.(mp4|3gp)$/i)) {
      return `<video controls class="chat-media chat-media-video"><source src="${url}" type="video/mp4"></video>`;
    } else if (url.match(/\.(txt)$/i)) {
      return `<iframe src="${url}" class="chat-media chat-media-text" style="width: 100%; height: 200px;" frameborder="0"></iframe>`;
    } else {
      return `
        <div class="chat-media chat-media-unsupported" data-file-url="${url}" data-filename="${filename}">
          ${unsupportedFileSvg}
          <span class="unsupported-filename">${filename}</span>
        </div>
      `;
    }
  }

  function generateDropdownMenu(msg, isSent) {
    const hasFiles = msg.file_info && Array.isArray(msg.file_info) && msg.file_info.length > 0;
    
    if (hasFiles) {
      return `
        <div class="dropdown">
          <button class="icon-btn bg-transparent fs-20 text-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="ri-more-2-fill"></i>
          </button>
          <ul class="dropdown-menu ${isSent ? 'dropdown-menu-end' : ''}">
            <!-- Removed download option -->
          </ul>
        </div>
      `;
    }
    
    return '';
  }

  function renderConversationItem(conversation) {
    const contact = conversation.contact;
    const contactName = formatContactName(contact);
    const isNameOnly = contact ? (!!contact.first_name || !!contact.last_name) : false;
    const contactNumber = contact ? (contact.whatsapp_contact || contact.email_contact || '') : '';
    const lastMessage = conversation.latest_message ? conversation.latest_message.message || 'Media file' : '';
    const unreadCount = conversation.unread_count || 0;
    const isUnread = unreadCount > 0;
    const isActive = currentConversationId && currentConversationId == conversation.id;
    // Device badge
    const gateway = conversation.gateway;
    const deviceName = gateway ? gateway.name : null;

    return `
      <li class="chat-contact conversation-item ${isUnread ? 'unread' : ''} ${isActive ? 'active' : ''}" data-conversation-id="${conversation.id}">
        <div class="chat-contact-info">
          <p class="mb-0 contact-name">${contactName}</p>
          <small class="contact-number">${isNameOnly ? '' : contactNumber}</small>
          ${lastMessage ? `<small class="text-muted last-message ${isUnread ? 'unread-message' : ''}">${lastMessage.substring(0, 20)}${lastMessage.length > 20 ? '...' : ''}</small>` : ''}
          ${deviceName ? `<span class="device-badge"><i class="ri-smartphone-line"></i>${deviceName}</span>` : ''}
        </div>
        <div class="chat-contact-meta">
          <div class="d-flex flex-column align-items-end">
            ${conversation.last_message_at ? `<span class="time">${formatTime(conversation.last_message_at)}</span>` : ''}
            ${isUnread ? `<span class="unread-count badge bg-primary">${unreadCount}</span>` : ''}
          </div>
          <button class="btn btn-sm btn-link text-danger delete-conversation p-0 ms-2" data-conversation-id="${conversation.id}" title="Delete Conversation" style="font-size: 18px;">
            <i class="ri-delete-bin-line"></i>
          </button>
        </div>
      </li>
    `;
  }

  function renderMessage(msg) {
    const isSent = !msg.participants.some(p => p.role === 'sender' && p.participantable_type === 'App\\Models\\Contact');
    const status = msg.statuses && msg.statuses.length > 0 ? msg.statuses[msg.statuses.length - 1].status : '';
    const isFailed = status === 'failed';
    const isPending = status === 'pending';
    const additionalData = msg.statuses && msg.statuses.length > 0 ? msg.statuses[msg.statuses.length - 1].additional_data : null;
    const errorMessage = additionalData ? additionalData?.message || translate('Unknown error occurred') : translate('No error details available');

    let messageContent = '';
    if (msg.file_info && Array.isArray(msg.file_info) && msg.file_info.length > 0) {
      messageContent = renderMedia(msg.file_info);
      if (msg.message) {
        messageContent = `<p>${msg.message}</p>` + messageContent;
      }
    } else if (msg.message) {
      messageContent = `<p>${msg.message}</p>`;
    }

    const hasFiles = msg.file_info && Array.isArray(msg.file_info) && msg.file_info.length > 0;
    const fileUrl = hasFiles ? msg.file_info[0] : '';
    const filename = hasFiles ? getFilenameFromUrl(fileUrl) : '';

    return `
      <li class="message ${isSent ? 'right' : 'left'} ${isFailed ? 'failed' : ''}" data-message-id="${msg.id}">
        <div class="message-wrapper">
          <div class="message-body">
            ${messageContent}
            <div class="message-footer">
              <div class="message-time">
                <span class="fs-12 lh-1">${formatTime(msg.created_at)}
                  ${isSent ? (isFailed ?
                    `<i class="ri-error-warning-line text-danger fs-14 ms-1" title="${errorMessage}"></i>` :
                    (isPending ?
                      `<i class="ri-time-line fs-14 text-warning ms-1" title="Pending"></i>` :
                      (status === 'read' ?
                        `<i class="ri-check-double-line fs-14 text-info ms-1" title="Read" style="color: #53bdeb !important;"></i>` :
                        (status === 'delivered' ?
                          `<i class="ri-check-double-line fs-14 text-secondary ms-1" title="Delivered"></i>` :
                          `<i class="ri-check-line fs-14 text-secondary ms-1" title="Sent"></i>`)))) : ''}
                </span>
              </div>
              ${hasFiles ? `<button class="download-btn i-btn btn--primary outline" data-file-url="${fileUrl}" data-filename="${filename}">
                <i class="ri-download-line"></i>
              </button>` : ''}
            </div>
          </div>
        </div>
      </li>
    `;
  }

  // function renderMessage(msg) {
  //   const isSent = !msg.participants.some(p => p.role === 'sender' && p.participantable_type === 'App\\Models\\Contact');
  //   const status = msg.statuses && msg.statuses.length > 0 ? msg.statuses[msg.statuses.length - 1].status : '';
  //   const isFailed = status === 'failed';
  //   const isPending = status === 'pending';
  //   const additionalData = msg.statuses && msg.statuses.length > 0 ? msg.statuses[msg.statuses.length - 1].additional_data : null;
  //   const errorMessage = additionalData && additionalData.errors ? additionalData.errors.message || translate('Unknown error occurred') : translate('No error details available');

  //   let messageContent = '';
  //   if (msg.file_info && Array.isArray(msg.file_info) && msg.file_info.length > 0) {
  //     messageContent = renderMedia(msg.file_info);
  //     if (msg.message) {
  //       messageContent = `<p>${msg.message}</p>` + messageContent;
  //     }
  //   } else if (msg.message) {
  //     messageContent = `<p>${msg.message}</p>`;
  //   }

  //   const hasFiles = msg.file_info && Array.isArray(msg.file_info) && msg.file_info.length > 0;
  //   const fileUrl = hasFiles ? msg.file_info[0] : '';
  //   const filename = hasFiles ? getFilenameFromUrl(fileUrl) : '';

  //   return `
  //     <li class="message ${isSent ? 'right' : 'left'} ${isFailed ? 'failed' : ''}">
  //       <div class="message-wrapper">
  //         <div class="message-body">
  //           ${messageContent}
  //           <div class="message-time">
  //             <span class="fs-10 lh-1">${formatTime(msg.created_at)}
  //               ${isSent ? (isFailed ? 
  //                 `<i class="ri-more-2-fill text-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"></i>
  //                 <div class="error-details dropdown-menu dropdown-menu-end p-2 text-danger" style="display: none;">
  //                   ${errorMessage}
  //                 </div>` : 
  //                 (isPending ? 
  //                   `<i class="ri-time-line fs-14 text-warning"></i>` : 
  //                   `<i class="ri-check-double-line fs-14 ${status === 'delivered' || status === 'read' ? 'text-success' : 'text-secondary'}"></i>`)) : ''}
  //             </span>
  //           </div>
  //           ${hasFiles ? `<button class="download-btn btn btn-sm btn-outline-secondary" data-file-url="${fileUrl}" data-filename="${filename}">
  //             <i class="ri-download-line"></i>
  //           </button>` : ''}
  //         </div>
  //         ${generateDropdownMenu(msg, isSent)}
  //       </div>
  //     </li>
  //   `;
  // }

  
  // API functions
  function isScrollable(container) {
    return container.scrollHeight > container.clientHeight;
  }

  function toggleLoadMoreButton(show, buttonId, listContainer, type) {
    const button = document.getElementById(buttonId);
    if (!button) return;
    if (show) {
      button.style.display = 'block';
      button.textContent = 'Load More'; 
    } else {
      button.style.display = 'none';
    }
    // Re-attach click if showing (debounced to avoid spam)
    if (show) {
      button.onclick = debounce(() => {
        if (!isLoadingConversations) {
          conversationsPage++;
          fetchConversations(type, elements.conversationSearch?.value || elements.pendingSearch?.value || '', conversationsPage, true);
        }
      }, 300);
    }
  }

  async function fetchConversations(type = 'all', search = '', page = 1, append = false) {
    if (isLoadingConversations) return;

    isLoadingConversations = true;

    const targetList = type === 'pending' ? elements.pendingList : elements.conversationsList;
    const loader = type === 'pending' ? elements.pendingLoader : elements.conversationsLoader;

    if (!append) {
      showLoader(loader);
      targetList.innerHTML = '';
    }

    try {
      const params = new URLSearchParams({
        type: type,
        page: page,
        ...(search && { search: search }),
        ...(currentDeviceId !== 'all' && { device_id: currentDeviceId })
      });

      const response = await fetch(`${config.getConversationsRoute}?${params}`, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      });

      const data = await response.json();
      
      if (data.conversations && data.conversations.length > 0) {
        const conversationsHtml = data.conversations.map(conv => renderConversationItem(conv)).join('');
        
        if (append) {
          targetList.insertAdjacentHTML('beforeend', conversationsHtml);
        } else {
          targetList.innerHTML = conversationsHtml;
        }

        hasMoreConversations = data.has_more;
      } else {
        hasMoreConversations = false;
        if (!append) {
          targetList.innerHTML = `<li class="text-center p-4 text-muted">No conversations found</li>`;
        }
      }

    } catch (error) {
      console.error('Error fetching conversations:', error);
      hasMoreConversations = false;
      if (!append) {
        targetList.innerHTML = `<li class="text-center p-4 text-danger">Error loading conversations</li>`;
      }
    } finally {
      isLoadingConversations = false;
      hideLoader(loader);
      
      const scrollContainer = type === 'pending' ? elements.pendingList.parentElement : elements.conversationsList.parentElement;
      const listContainer = type === 'pending' ? elements.pendingList : elements.conversationsList;
      const buttonId = type === 'pending' ? 'load-more-pending' : 'load-more-conversations';
      
      scrollContainer.removeEventListener('scroll', handleScrollConversations); 
      if (hasMoreConversations && targetList.children.length > 0 && !targetList.querySelector('.text-center')) {
        toggleLoadMoreButton(true, buttonId, listContainer, type);
      } else {
        toggleLoadMoreButton(false, buttonId, listContainer, type);
      }
    }
  }

  async function fetchMessages(conversationId, page = 1, append = false, search = '') {
    if (isLoadingMessages) return;
    
    isLoadingMessages = true;
    
    if (!append) {
      showLoader(elements.messagesLoader);
      elements.messagesList.innerHTML = '';
    }

    try {
      let url = config.showRoute.replace('__ID__', conversationId);
      
      if (search) {
        url = config.searchMessagesRoute.replace('__ID__', conversationId);
      } else if (page > 1) {
        url = config.loadMoreMessagesRoute.replace('__ID__', conversationId);
      }

      const params = new URLSearchParams({
        page: page,
        ...(search && { search: search })
      });

      const response = await fetch(`${url}?${params}`, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      });

      const data = await response.json();
      
      if (data.messages && data.messages.length > 0) {
        const messagesHtml = data.messages.map(msg => renderMessage(msg)).join('');

        if (append) {
          elements.messagesList.insertAdjacentHTML('afterbegin', messagesHtml);
        } else {
          elements.messagesList.innerHTML = messagesHtml;

          // Track last message ID for auto-refresh
          lastMessageId = data.messages[data.messages.length - 1].id;

          if (data.contact) {
            const contactName = formatContactName(data.contact);
            const contactNumber = data.contact.whatsapp_contact || data.contact.email_contact || '';

            elements.contactName.textContent = contactName;
            elements.contactNumber.textContent = contactNumber;
          }

          setTimeout(() => {
            elements.chatBody.scrollTop = elements.chatBody.scrollHeight;
          }, 100);
        }

        hasMoreMessages = data.has_more;
      } else if (!append) {
        elements.messagesList.innerHTML = `<li class="text-center p-4 text-muted">No messages found</li>`;
      }

    } catch (error) {
      console.error('Error fetching messages:', error);
      if (!append) {
        elements.messagesList.innerHTML = `<li class="text-center p-4 text-danger">Error loading messages</li>`;
      }
    } finally {
      isLoadingMessages = false;
      hideLoader(elements.messagesLoader);
      if (hasMoreMessages && !isLoadingMessages && currentConversationId) {
        elements.chatBody.addEventListener('scroll', handleScrollMessages);
      }
    }
  }

  // Scroll handlers
  function handleScrollConversations(e) {
    const scrollContainer = e.target;
    if (scrollContainer.scrollTop + scrollContainer.clientHeight >= scrollContainer.scrollHeight - 50 && hasMoreConversations && !isLoadingConversations) {
      conversationsPage++;
      fetchConversations(currentConversationType, elements.conversationSearch?.value || elements.pendingSearch?.value || '', conversationsPage, true);
    }
  }

  function handleScrollMessages(e) {
    if (e.target.scrollTop === 0 && hasMoreMessages && !isLoadingMessages && currentConversationId) {
      messagesPage++;
      fetchMessages(currentConversationId, messagesPage, true);
    }
  }

  // Event handlers
  function handleConversationClick(conversationId) {
    currentConversationId = conversationId;
    elements.conversationInput.value = conversationId;

    elements.noConversationState.style.display = 'none';
    elements.chatInterface.style.display = 'block';

    document.querySelectorAll('.conversation-item').forEach(item => {
      item.classList.remove('active');
    });
    document.querySelector(`[data-conversation-id="${conversationId}"]`)?.classList.add('active');

    messagesPage = 1;
    lastMessageId = null; // Reset last message ID for new conversation
    fetchMessages(conversationId);

    // Start auto-refresh for messages
    startMessageAutoRefresh();

    if (window.innerWidth < 768) {
      const chatLeft = document.querySelector(".chat-left");
      chatLeft.classList.remove("open-left-drawer");

      const overlay = document.querySelector("#sidebar-overlay");
      if (overlay) overlay.remove();
    }
  }

  function handleTabSwitch(type) {
    currentConversationType = type;
    conversationsPage = 1;
    hasMoreConversations = true;
    
    if (type === 'pending') {
      elements.pendingSearch.value = '';
    } else {
      elements.conversationSearch.value = '';
    }
    
    fetchConversations(type);
  }

  function initDownloadHandlers() {
    document.querySelectorAll('.download-file').forEach(link => {
      link.addEventListener('click', function(e) {
        e.preventDefault();
        const fileUrl = this.getAttribute('data-file-url') || this.getAttribute('href');
        const filename = this.getAttribute('download');
        
        const tempLink = document.createElement('a');
        tempLink.href = fileUrl;
        tempLink.download = filename;
        tempLink.style.display = 'none';
        document.body.appendChild(tempLink);
        tempLink.click();
        document.body.removeChild(tempLink);
      });
    });
  }

  function appendNewMessage(msg) {
    const messageHtml = renderMessage(msg);
    elements.messagesList.insertAdjacentHTML('beforeend', messageHtml);
    elements.chatBody.scrollTop = elements.chatBody.scrollHeight;
    initDownloadHandlers();
  }

  // Update temp message with real server response
  function updateTempMessage(tempId, realMessage) {
    const tempElement = document.querySelector(`[data-message-id="${tempId}"]`);
    if (tempElement) {
      // Update the message ID
      tempElement.setAttribute('data-message-id', realMessage.id);

      // Update status icon
      const status = realMessage.statuses && realMessage.statuses.length > 0
        ? realMessage.statuses[realMessage.statuses.length - 1].status
        : 'sent';

      const timeContainer = tempElement.querySelector('.message-time span');
      if (timeContainer) {
        const icon = timeContainer.querySelector('i');
        if (icon) {
          icon.className = getStatusIconClass(status);
          icon.title = getStatusTitle(status);
          if (status === 'read') {
            icon.style.color = '#53bdeb';
          } else {
            icon.style.color = '';
          }
        }
      }

      // Update media URL if it was a blob URL
      if (realMessage.file_info && realMessage.file_info.length > 0) {
        const mediaImg = tempElement.querySelector('.chat-media-image');
        if (mediaImg && mediaImg.src.startsWith('blob:')) {
          mediaImg.src = realMessage.file_info[0];
        }
      }

      // Track as last message
      lastMessageId = realMessage.id;
    }
  }

  // Mark message as failed
  function markMessageFailed(tempId, errorMsg) {
    const tempElement = document.querySelector(`[data-message-id="${tempId}"]`);
    if (tempElement) {
      tempElement.classList.add('failed');
      const timeContainer = tempElement.querySelector('.message-time span');
      if (timeContainer) {
        const icon = timeContainer.querySelector('i');
        if (icon) {
          icon.className = 'ri-error-warning-line text-danger fs-14 ms-1';
          icon.title = errorMsg || 'Failed';
        }
      }
    }
  }

  // Get status icon class
  function getStatusIconClass(status) {
    switch(status) {
      case 'read': return 'ri-check-double-line fs-14 ms-1';
      case 'delivered': return 'ri-check-double-line fs-14 text-secondary ms-1';
      case 'sent': return 'ri-check-line fs-14 text-secondary ms-1';
      case 'failed': return 'ri-error-warning-line text-danger fs-14 ms-1';
      default: return 'ri-time-line fs-14 text-secondary ms-1';
    }
  }

  // Get status title
  function getStatusTitle(status) {
    switch(status) {
      case 'read': return 'Read';
      case 'delivered': return 'Delivered';
      case 'sent': return 'Sent';
      case 'failed': return 'Failed';
      default: return 'Pending';
    }
  }

  // Initialize event listeners
  function initEventListeners() {
    document.querySelectorAll('[data-type]').forEach(tab => {
      tab.addEventListener('click', function() {
        const type = this.getAttribute('data-type');
        handleTabSwitch(type);
      });
    });

    if (elements.conversationSearch) {
      elements.conversationSearch.addEventListener('input', debounce((e) => {
        conversationsPage = 1;
        fetchConversations('all', e.target.value);
      }, 500));
    }

    if (elements.pendingSearch) {
      elements.pendingSearch.addEventListener('input', debounce((e) => {
        conversationsPage = 1;
        fetchConversations('pending', e.target.value);
      }, 500));
    }

    if (elements.messageSearch) {
      elements.messageSearch.addEventListener('input', debounce((e) => {
        if (currentConversationId) {
          messagesPage = 1;
          fetchMessages(currentConversationId, 1, false, e.target.value);
        }
      }, 500));
    }

    document.addEventListener('click', function(e) {
      const conversationItem = e.target.closest('.chat-contact');
      if (conversationItem) {
        const conversationId = conversationItem.getAttribute('data-conversation-id');
        handleConversationClick(conversationId);
      }
    });

    if (elements.messageForm) {
      elements.messageForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Prevent double submit
        if (isSendingMessage) return;

        if (!currentConversationId) {
          alert('Please select a conversation first');
          return;
        }

        const messageText = elements.messageInput?.value?.trim() || '';
        const hasMedia = selectedMediaFile !== null;

        // Require at least message or media
        if (!messageText && !hasMedia) {
          notify('error', 'Please enter a message or attach media');
          return;
        }

        isSendingMessage = true;

        // Generate temporary ID for optimistic UI
        const tempId = 'temp_' + Date.now();
        const tempMessage = {
          id: tempId,
          message: messageText,
          file_info: hasMedia && selectedMediaFile ? [URL.createObjectURL(selectedMediaFile)] : null,
          created_at: new Date().toISOString(),
          participants: [{ role: 'sender', participantable_type: 'App\\Models\\Admin' }],
          statuses: [{ status: 'pending' }]
        };

        // Track this temp message to prevent duplicates from auto-refresh
        pendingTempMessages.set(tempId, {
          text: messageText,
          timestamp: Date.now()
        });

        // Immediately show message with pending status (optimistic UI)
        appendNewMessage(tempMessage);

        // Clear input immediately for better UX
        const savedMessageText = elements.messageInput.value;
        elements.messageInput.value = '';
        const savedMediaFile = selectedMediaFile;
        const savedMediaType = selectedMediaType;
        clearMediaSelection();

        const formData = new FormData(elements.messageForm);
        // Re-add the message since we cleared the input
        formData.set('body', savedMessageText);

        try {
          const response = await fetch(elements.messageForm.action, {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            }
          });

          const data = await response.json();

          if (data.success && data.message) {
            // Replace temp message with real message from server
            updateTempMessage(tempId, data.message);
            // Remove from pending tracking
            pendingTempMessages.delete(tempId);
          } else if (data.error) {
            // Mark message as failed
            markMessageFailed(tempId, data.message || 'Failed to send message');
            pendingTempMessages.delete(tempId);
            notify('error', data.message || 'Failed to send message');
          }
        } catch (error) {
          console.error('Error sending message:', error);
          markMessageFailed(tempId, 'Network error');
          pendingTempMessages.delete(tempId);
          notify('error', 'Error sending message. Please try again.');
        } finally {
          isSendingMessage = false;
        }
      });
    }

    // Remove button click listeners since we're using scroll
    if (elements.chatBody) {
      elements.chatBody.addEventListener('scroll', handleScrollMessages);
    }

    document.addEventListener('click', function(e) {
      const downloadBtn = e.target.closest('.download-btn');
      if (downloadBtn) {
        const fileUrl = downloadBtn.getAttribute('data-file-url');
        const filename = downloadBtn.getAttribute('data-filename');
        const tempLink = document.createElement('a');
        tempLink.href = fileUrl;
        tempLink.download = filename;
        tempLink.style.display = 'none';
        document.body.appendChild(tempLink);
        tempLink.click();
        document.body.removeChild(tempLink);
      }

      // Handle dropdown toggle for error details
      const dropdownToggle = e.target.closest('.dropdown-toggle');
      if (dropdownToggle) {
        const errorDetails = dropdownToggle.nextElementSibling;
        if (errorDetails && errorDetails.classList.contains('error-details')) {
          errorDetails.style.display = errorDetails.style.display === 'block' ? 'none' : 'block';
          e.stopPropagation(); // Prevent outside click handler from immediately closing it
        }
      }

      // Close dropdown when clicking outside
      const errorDetails = document.querySelectorAll('.error-details');
      errorDetails.forEach(detail => {
        if (!e.target.closest('.dropdown-toggle') && detail.style.display === 'block') {
          detail.style.display = 'none';
        }
      });

      // Handle delete conversation
      const deleteBtn = e.target.closest('.delete-conversation');
      if (deleteBtn) {
        e.stopPropagation(); // Prevent conversation click
        const conversationId = deleteBtn.getAttribute('data-conversation-id');
        deleteConversation(conversationId);
      }
    });

    // Device filter event listener
    if (elements.deviceFilter) {
      elements.deviceFilter.addEventListener('change', function(e) {
        currentDeviceId = e.target.value;
        conversationsPage = 1;
        fetchConversations(currentConversationType, elements.conversationSearch?.value || '', 1, false);
      });
    }

    // Media upload functionality
    initMediaUpload();
  }

  // Media upload initialization
  function initMediaUpload() {
    // Media dropdown toggle
    if (elements.mediaDropdownBtn) {
      elements.mediaDropdownBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        elements.mediaDropdownMenu?.classList.toggle('show');
      });
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.media-dropdown')) {
        elements.mediaDropdownMenu?.classList.remove('show');
      }
    });

    // Media type selection
    document.querySelectorAll('.media-dropdown-item').forEach(item => {
      item.addEventListener('click', function() {
        const mediaType = this.getAttribute('data-type');
        const acceptTypes = this.getAttribute('data-accept');

        selectedMediaType = mediaType;
        if (elements.mediaInput) {
          elements.mediaInput.setAttribute('accept', acceptTypes);
          elements.mediaInput.click();
        }
        elements.mediaDropdownMenu?.classList.remove('show');
      });
    });

    // Media file selection
    if (elements.mediaInput) {
      elements.mediaInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
          selectedMediaFile = file;
          showMediaPreview(file);
        }
      });
    }

    // Remove media
    if (elements.mediaRemoveBtn) {
      elements.mediaRemoveBtn.addEventListener('click', function() {
        clearMediaSelection();
      });
    }
  }

  // Show media preview
  function showMediaPreview(file) {
    if (!elements.mediaPreviewWrapper) return;

    elements.mediaPreviewWrapper.classList.add('active');
    elements.mediaPreviewName.textContent = file.name;
    elements.mediaPreviewSize.textContent = formatFileSize(file.size);
    elements.mediaTypeInput.value = selectedMediaType;

    // Set appropriate icon based on media type
    let iconClass = 'ri-file-line';
    if (selectedMediaType === 'image') {
      iconClass = 'ri-image-line';
      // Show thumbnail for images
      const reader = new FileReader();
      reader.onload = function(e) {
        elements.mediaPreviewThumb.innerHTML = `<img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;">`;
      };
      reader.readAsDataURL(file);
    } else if (selectedMediaType === 'video') {
      iconClass = 'ri-video-line';
      elements.mediaPreviewThumb.innerHTML = `<i class="${iconClass}"></i>`;
    } else if (selectedMediaType === 'audio') {
      iconClass = 'ri-music-line';
      elements.mediaPreviewThumb.innerHTML = `<i class="${iconClass}"></i>`;
    } else {
      iconClass = 'ri-file-text-line';
      elements.mediaPreviewThumb.innerHTML = `<i class="${iconClass}"></i>`;
    }
  }

  // Clear media selection
  function clearMediaSelection() {
    selectedMediaFile = null;
    selectedMediaType = null;
    if (elements.mediaInput) elements.mediaInput.value = '';
    if (elements.mediaTypeInput) elements.mediaTypeInput.value = '';
    if (elements.mediaPreviewWrapper) elements.mediaPreviewWrapper.classList.remove('active');
    if (elements.mediaPreviewThumb) elements.mediaPreviewThumb.innerHTML = '<i class="ri-file-line"></i>';
  }

  // Format file size
  function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  // Delete conversation function
  let conversationToDelete = null;

  function deleteConversation(conversationId) {
    // Store the conversation ID
    conversationToDelete = conversationId;

    // Show the modal using Bootstrap 5 API
    const modal = new bootstrap.Modal(document.getElementById('deleteConversationModal'));
    modal.show();
  }

  // Handle confirm delete button click - set up directly (already inside DOMContentLoaded)
  function initDeleteHandler() {
    const confirmBtn = document.getElementById('confirmDeleteConversation');
    if (confirmBtn) {
      confirmBtn.addEventListener('click', async function() {
        if (!conversationToDelete) return;

        // Hide the modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('deleteConversationModal'));
        if (modal) {
          modal.hide();
        }

        try {
          const response = await fetch(`{{ route('admin.communication.whatsapp.chats.destroy', ':id') }}`.replace(':id', conversationToDelete), {
            method: 'DELETE',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
          });

          const data = await response.json();

          if (data.success) {
            // Remove conversation from list
            const conversationItem = document.querySelector(`[data-conversation-id="${conversationToDelete}"]`);
            if (conversationItem) {
              conversationItem.remove();
            }

            // If this was the active conversation, clear the chat interface
            if (currentConversationId == conversationToDelete) {
              currentConversationId = null;
              elements.noConversationState.style.display = 'flex';
              elements.chatInterface.style.display = 'none';
              stopMessageAutoRefresh();
            }

            notify('success', data.message || 'Conversation deleted successfully');
          } else {
            notify('error', data.message || 'Failed to delete conversation');
          }
        } catch (error) {
          console.error('Error deleting conversation:', error);
          notify('error', 'An error occurred while deleting the conversation');
        } finally {
          conversationToDelete = null;
        }
      });
    }
  }

  // Initialize delete handler
  initDeleteHandler();

  // Auto-refresh functions
  function startConversationAutoRefresh() {
    if (!config.enableAutoRefresh) return;

    stopConversationAutoRefresh(); // Clear any existing timer

    conversationRefreshTimer = setInterval(() => {
      if (isPageVisible && !isLoadingConversations) {
        // Silently refresh conversations (don't show loader, don't reset scroll)
        refreshConversations();
      }
    }, config.conversationRefreshInterval);
  }

  function stopConversationAutoRefresh() {
    if (conversationRefreshTimer) {
      clearInterval(conversationRefreshTimer);
      conversationRefreshTimer = null;
    }
  }

  function startMessageAutoRefresh() {
    if (!config.enableAutoRefresh) return;

    stopMessageAutoRefresh(); // Clear any existing timer

    messageRefreshTimer = setInterval(() => {
      if (isPageVisible && !isLoadingMessages && currentConversationId) {
        // Silently check for new messages
        refreshMessages();
      }
    }, config.messageRefreshInterval);
  }

  function stopMessageAutoRefresh() {
    if (messageRefreshTimer) {
      clearInterval(messageRefreshTimer);
      messageRefreshTimer = null;
    }
  }

  async function refreshConversations() {
    if (isLoadingConversations) return;

    isLoadingConversations = true;

    const targetList = currentConversationType === 'pending' ? elements.pendingList : elements.conversationsList;
    const searchValue = currentConversationType === 'pending' ? elements.pendingSearch?.value : elements.conversationSearch?.value;

    try {
      const params = new URLSearchParams({
        type: currentConversationType,
        page: 1,
        ...(searchValue && { search: searchValue }),
        ...(currentDeviceId !== 'all' && { device_id: currentDeviceId })
      });

      const response = await fetch(`${config.getConversationsRoute}?${params}`, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      });

      const data = await response.json();

      if (data.conversations && data.conversations.length > 0) {
        const conversationsHtml = data.conversations.map(conv => renderConversationItem(conv)).join('');
        targetList.innerHTML = conversationsHtml;
        hasMoreConversations = data.has_more;
      }
    } catch (error) {
      console.error('Error refreshing conversations:', error);
    } finally {
      isLoadingConversations = false;
    }
  }

  // Helper function to get status icon HTML
  function getStatusIcon(status, errorMessage = '') {
    if (status === 'failed') {
      return `<i class="ri-error-warning-line text-danger fs-14 ms-1" title="${errorMessage || 'Failed'}"></i>`;
    } else if (status === 'pending') {
      return `<i class="ri-time-line fs-14 text-warning ms-1" title="Pending"></i>`;
    } else if (status === 'read') {
      return `<i class="ri-check-double-line fs-14 ms-1" title="Read" style="color: #53bdeb !important;"></i>`;
    } else if (status === 'delivered') {
      return `<i class="ri-check-double-line fs-14 text-secondary ms-1" title="Delivered"></i>`;
    } else {
      // sent or default
      return `<i class="ri-check-line fs-14 text-secondary ms-1" title="Sent"></i>`;
    }
  }

  // Update status icons for existing messages without re-rendering
  function updateMessageStatuses(messages) {
    messages.forEach(msg => {
      const messageElement = elements.messagesList.querySelector(`[data-message-id="${msg.id}"]`);
      if (!messageElement) return;

      const isSent = !msg.participants.some(p => p.role === 'sender' && p.participantable_type === 'App\\Models\\Contact');
      if (!isSent) return; // Only update sent messages (outgoing)

      const status = msg.statuses && msg.statuses.length > 0 ? msg.statuses[msg.statuses.length - 1].status : '';
      const additionalData = msg.statuses && msg.statuses.length > 0 ? msg.statuses[msg.statuses.length - 1].additional_data : null;
      const errorMessage = additionalData ? additionalData?.message || '' : '';

      // Find the status icon container in this message
      const timeContainer = messageElement.querySelector('.message-time span');
      if (!timeContainer) return;

      // Check current status icon
      const currentIcon = timeContainer.querySelector('i');
      const currentStatus = currentIcon ?
        (currentIcon.classList.contains('ri-time-line') ? 'pending' :
         currentIcon.classList.contains('ri-error-warning-line') ? 'failed' :
         currentIcon.classList.contains('ri-check-double-line') ?
           (currentIcon.style.color === 'rgb(83, 189, 235)' || currentIcon.getAttribute('title') === 'Read' ? 'read' : 'delivered') :
         'sent') : '';

      // Only update if status changed
      if (currentStatus !== status) {
        // Remove old icon
        if (currentIcon) currentIcon.remove();

        // Add new icon
        const newIconHtml = getStatusIcon(status, errorMessage);
        timeContainer.insertAdjacentHTML('beforeend', newIconHtml);

        // Update failed class on message
        if (status === 'failed') {
          messageElement.classList.add('failed');
        } else {
          messageElement.classList.remove('failed');
        }
      }
    });
  }

  async function refreshMessages() {
    if (isLoadingMessages || !currentConversationId) return;

    isLoadingMessages = true;

    try {
      const url = config.showRoute.replace('__ID__', currentConversationId);
      const params = new URLSearchParams({ page: 1 });

      const response = await fetch(`${url}?${params}`, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      });

      const data = await response.json();

      if (data.messages && data.messages.length > 0) {
        // Update status icons for ALL existing messages (including sent ones)
        updateMessageStatuses(data.messages);

        const latestMessageId = data.messages[data.messages.length - 1].id;

        // Check for new messages (received or ones we don't have yet)
        if (lastMessageId && latestMessageId !== lastMessageId) {
          const newMessages = [];
          for (let i = data.messages.length - 1; i >= 0; i--) {
            const msg = data.messages[i];
            if (msg.id === lastMessageId) break;
            newMessages.unshift(msg);
          }

          // Append truly new messages (incoming messages, not our sent ones)
          newMessages.forEach(msg => {
            // Skip if message already exists in DOM
            const existingMessage = elements.messagesList.querySelector(`[data-message-id="${msg.id}"]`);
            if (existingMessage) return;

            // Check if this matches any pending temp message (our sent message)
            let matchesPending = false;
            pendingTempMessages.forEach((pending, tempId) => {
              // Match by message content and recent timestamp (within 30 seconds)
              if (pending.text === msg.message && (Date.now() - pending.timestamp) < 30000) {
                matchesPending = true;
                // This is our sent message - update the temp message instead
                const tempElement = document.querySelector(`[data-message-id="${tempId}"]`);
                if (tempElement) {
                  tempElement.setAttribute('data-message-id', msg.id);
                  // Update status icon
                  const status = msg.statuses && msg.statuses.length > 0
                    ? msg.statuses[msg.statuses.length - 1].status : 'sent';
                  const timeContainer = tempElement.querySelector('.message-time span');
                  if (timeContainer) {
                    const icon = timeContainer.querySelector('i');
                    if (icon) {
                      icon.className = getStatusIconClass(status);
                      icon.title = getStatusTitle(status);
                      if (status === 'read') {
                        icon.style.color = '#53bdeb';
                      } else {
                        icon.style.color = '';
                      }
                    }
                  }
                }
                pendingTempMessages.delete(tempId);
              }
            });

            // Only add if it's a truly new message (not one we sent)
            if (!matchesPending) {
              const messageHtml = renderMessage(msg);
              elements.messagesList.insertAdjacentHTML('beforeend', messageHtml);
            }
          });

          const chatBody = elements.chatBody;
          const isNearBottom = chatBody.scrollHeight - chatBody.scrollTop - chatBody.clientHeight < 100;
          if (isNearBottom) {
            chatBody.scrollTop = chatBody.scrollHeight;
          }

          // Refresh conversations to update last message preview
          refreshConversations();
        }

        // Always update lastMessageId to the latest
        lastMessageId = latestMessageId;
      }
    } catch (error) {
      console.error('Error refreshing messages:', error);
    } finally {
      isLoadingMessages = false;
    }
  }

  // Page visibility detection for smart polling
  function handleVisibilityChange() {
    if (document.hidden) {
      isPageVisible = false;
      stopConversationAutoRefresh();
      stopMessageAutoRefresh();
    } else {
      isPageVisible = true;
      startConversationAutoRefresh();
      if (currentConversationId) {
        startMessageAutoRefresh();
      }
    }
  }

  // Select a conversation by ID (used when redirecting from logs page)
  function selectConversation(conversationId) {
    // Try to find and click the conversation item
    const conversationItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);
    if (conversationItem) {
      handleConversationClick(conversationId);
    } else {
      // Conversation not in current list - load it directly
      currentConversationId = conversationId;
      elements.conversationInput.value = conversationId;
      elements.noConversationState.style.display = 'none';
      elements.chatInterface.style.display = 'block';
      messagesPage = 1;
      lastMessageId = null;
      fetchMessages(conversationId);
      startMessageAutoRefresh();
    }
  }

  // Initialize the application
  function init() {
    fetchConversations('all');
    initEventListeners();
    elements.noConversationState.style.display = 'flex';
    elements.chatInterface.style.display = 'none';

    // Start auto-refresh
    startConversationAutoRefresh();

    // Add visibility change listener
    document.addEventListener('visibilitychange', handleVisibilityChange);

    // Check for conversation parameter in URL (from logs page redirect)
    const urlParams = new URLSearchParams(window.location.search);
    const conversationId = urlParams.get('conversation');
    if (conversationId) {
      // Auto-select the conversation after a short delay to let conversations load
      setTimeout(() => {
        selectConversation(conversationId);
      }, 500);
      // Clean up the URL
      window.history.replaceState({}, document.title, window.location.pathname);
    }
  }

  init();
});
</script>
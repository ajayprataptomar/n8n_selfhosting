<script>
     (function($){
         "use strict";
 
         $(document).ready(function() {
 
             function fetchImportProgress() {
                 const groupIds = [];
                 $('.import-status').each(function() {
                     const groupId = $(this).data('group-id');
                     if (groupId && !groupIds.includes(groupId)) {
                         groupIds.push(groupId);
                     }
                 });
 
                 if (groupIds.length === 0) return;
 
                 $.ajax({
                     url: '{{ route("{$panel}.contact.group.import.progress") }}',
                     method: 'GET',
                     data: { group_ids: groupIds },
                     success: function(response) {
                         $('.import-status').each(function() {
                             const groupId = $(this).data('group-id');
                             const data = response[groupId] || { status: 'none' };
                             if ((data.status === 'none' || data.status == "{{ App\Enums\System\ContactImportStatusEnum::COMPLETED->value }}") 
                                   || data.status == "{{ App\Enums\System\ContactImportStatusEnum::FAILED->value }}") {
                              
                              
                                 let statusHtml = '';
                               
                                 if (data.status == "{{ App\Enums\System\ContactImportStatusEnum::COMPLETED->value }}"
                                        && data.is_email_verification_in_progress
                                 ) {
                                   
                                     statusHtml = `
                                         <div class="import-status-badge import-status-success">
                                             <i class="ri-check-line"></i>
                                             <span class="import-status-text">Import completed successfully</span>
                                         </div>
                                     `;
                                 } else if (data.status == "{{ App\Enums\System\ContactImportStatusEnum::FAILED->value }}"
                                        && data.is_email_verification_in_progress
                                 ) {
                                     statusHtml = `
                                         <div class="import-status-badge import-status-error">
                                             <i class="ri-error-warning-line"></i>
                                             <span class="import-status-text">Import failed</span>
                                         </div>
                                     `;
                                 } else {
                                   
                                     statusHtml = `
                                         <div class="import-status-badge">
                                             <i class="ri-information-line"></i>
                                             <span class="import-status-text">No active imports</span>
                                         </div>
                                     `;
                                 }
                                 $(this).html(`<div class="import-status-wrapper">${statusHtml}</div>`);
                                 $(this).data('needs-polling', false);
 
                                 // Update the contact count for this group
                                 const $contactCount = $(`.contact-count[data-group-id="${groupId}"]`);
                                 if (data.contacts_count !== undefined) {
                                     $contactCount.html(`
                                         <a href="${$contactCount.data('href')}" class="badge badge--primary p-2">
                                             <span class="i-badge info-solid pill">
                                                 {{ translate("View All: ") }} ${data.contacts_count} {{ translate(" contacts") }} <i class="ri-eye-line ms-1"></i>
                                             </span>
                                         </a>
                                     `);
                                 }

                             } else if (data.status === 'VERIFYING_EMAILS') {
                                 // Email verification in progress
                                 const emailProgress = data.email_verification_progress || 0;
                                 $(this).html(`
                                     <div class="import-status-wrapper">
                                         <div class="import-status-details">
                                             <div class="import-status-label">
                                                 <i class="ri-loader-4-line import-status-icon"></i>
                                                 <span>Verifying emails...</span>
                                             </div>
                                             <span class="import-status-count">${emailProgress.toFixed(2)}%</span>
                                         </div>
                                         <div class="import-status-progress-container">
                                             <div class="import-status-progress-bar" style="width: ${emailProgress}%;"></div>
                                             <div class="import-status-progress-shine"></div>
                                         </div>
                                     </div>
                                 `);
 
                                 $(this).data('needs-polling', data.is_email_verification_in_progress);
 
                                 // Update the contact count for this group (if provided)
                                 const $contactCount = $(`.contact-count[data-group-id="${groupId}"]`);
                                 if (data.contacts_count !== undefined) {
                                     $contactCount.html(`
                                         <a href="${$contactCount.data('href')}" class="badge badge--primary p-2">
                                             <span class="i-badge info-solid pill">
                                                 {{ translate("View All: ") }} ${data.contacts_count} {{ translate(" contacts") }} <i class="ri-eye-line ms-1"></i>
                                             </span>
                                         </a>
                                     `);
                                 }
                             } else {
                                 const progress = data.progress || 0;
                                 $(this).html(`
                                     <div class="import-status-wrapper">
                                         <div class="import-status-details">
                                             <div class="import-status-label">
                                                 <i class="ri-loader-4-line import-status-icon"></i>
                                                 <span>Importing contacts...</span>
                                             </div>
                                             <span class="import-status-count">${progress.toFixed(2)}%</span>
                                         </div>
                                         <div class="import-status-progress-container">
                                             <div class="import-status-progress-bar" style="width: ${progress}%;"></div>
                                             <div class="import-status-progress-shine"></div>
                                         </div>
                                     </div>
                                 `);
 
                                 if (data.status == "{{ App\Enums\System\ContactImportStatusEnum::PENDING->value }}" 
                                        || data.status == "{{ App\Enums\System\ContactImportStatusEnum::PROCESSING->value }}") {
                                     $(this).data('needs-polling', true);
                                 } else {
                                     $(this).data('needs-polling', false);
                                 }
 
                                 // Update the contact count for this group (if provided)
                                 const $contactCount = $(`.contact-count[data-group-id="${groupId}"]`);
                                 if (data.contacts_count !== undefined) {
                                     $contactCount.html(`
                                         <a href="${$contactCount.data('href')}" class="badge badge--primary p-2">
                                             <span class="i-badge info-solid pill">
                                                 {{ translate("View All: ") }} ${data.contacts_count} {{ translate(" contacts") }} <i class="ri-eye-line ms-1"></i>
                                             </span>
                                         </a>
                                     `);
                                 }
                             }
                         });
 
                         if ($('.import-status[data-needs-polling=true]').length > 0) {
                             setTimeout(fetchImportProgress, 5000);
                         }
                     },
                     error: function() {
                         $('.import-status').each(function() {
                             $(this).html(`
                                 <div class="import-status-wrapper">
                                     <div class="import-status-badge import-status-error">
                                         <i class="ri-error-warning-line"></i>
                                         <span class="import-status-text">Error fetching status</span>
                                     </div>
                                 </div>
                             `);
                         });
                     }
                 });
             }
 
             fetchImportProgress();
 
             $(document).on('click', '.pagination a', function(e) {
                 e.preventDefault();
                 const url = $(this).attr('href');
 
                 $.get(url, function(data) {
                     $('.table-container').html($(data).find('.table-container').html());
                     $('.pagination').html($(data).find('.pagination').html());
                     fetchImportProgress();
                 });
             });
         });
 
     })(jQuery);
 </script>
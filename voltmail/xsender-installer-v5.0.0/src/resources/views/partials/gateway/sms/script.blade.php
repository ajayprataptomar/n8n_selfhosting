<script>
     (function($) {
         "use strict";
 
         const translations = window.translations || {};
         const specialStringPattern = `${translations.use} @{{recipient}} ${translations.for_recipient_comma} @{{message}} ${translations.for_sms_body}`;
 
         const Utils = {
             initSortable(container) {
                 if (typeof $.fn.sortable === 'undefined') {
                     console.error('jQuery UI sortable is not loaded. Drag and drop will not work.');
                     return;
                 }
                 container.sortable({
                     handle: '.gw-param-drag',
                     axis: 'y',
                     cursor: 'move',
                     tolerance: 'pointer',
                     update: function() {
                         ParameterManager.renumberParams();
                     }
                 });
             },
             setupParamRow(row) {
                 row.on('mouseenter', function() {
                     $(this).find('.gw-param-delete').addClass('visible');
                 }).on('mouseleave', function() {
                     $(this).find('.gw-param-delete').removeClass('visible');
                 });
 
                 row.find('.gw-param-checkbox').on('change', function() {
                     if ($(this).is(':checked')) {
                         row.removeClass('disabled');
                     } else {
                         row.addClass('disabled');
                     }
                 });
 
                 row.find('.gw-param-delete').on('click', function() {
                     row.remove();
                     ParameterManager.renumberParams();
                 });
 
                 row.find('.gw-param-key, .gw-param-value').on('input', function() {
                     const container = row.parent();
                     if (row.is(':last-child') && $(this).val().trim() !== '') {
                         ParameterManager.addNewParamRow(container);
                     }
                 });
             }
         };
 
         const ParameterManager = {
             addNewParamRow(container) {
                 const rowCount = container.children().length;
                 const paramType = container.attr('id').replace('add_', '').replace('update_', '');
                 let namePrefix;
                 let placeholderKeyType;
 
                 if (paramType === 'query_params') {
                     namePrefix = 'meta_data[query_params]';
                     placeholderKeyType = 'query';
                 } else if (paramType === 'custom_headers') {
                     namePrefix = 'meta_data[headers]';
                     placeholderKeyType = 'header';
                 } else if (paramType === 'form_data') {
                     namePrefix = 'meta_data[form_data]';
                     placeholderKeyType = 'form_data';
                 } else if (paramType === 'urlencoded_data') {
                     namePrefix = 'meta_data[urlencoded_data]';
                     placeholderKeyType = 'url_encoded';
                 }
 
                 const newRow = `
                     <div class="gw-param-row">
                         <div class="gw-param-drag">
                             <i class="ri-drag-move-line"></i>
                         </div>
                         <div class="gw-param-toggle">
                             <input type="checkbox" class="gw-param-checkbox" checked name="${namePrefix}[${rowCount}][enabled]">
                         </div>
                         <div class="gw-param-input">
                             <input type="text" name="${namePrefix}[${rowCount}][key]" placeholder="${translations[`${placeholderKeyType}_key_placeholder`]}" class="gw-input gw-param-key" />
                         </div>
                         <div class="gw-param-input">
                             <input type="text" name="${namePrefix}[${rowCount}][value]" placeholder="${translations[`${placeholderKeyType}_value_placeholder`]}" class="gw-input gw-param-value" />
                         </div>
                         <div class="gw-param-actions">
                             <button type="button" class="gw-param-delete">
                             <i class="ri-delete-bin-line"></i>
                             </button>
                         </div>
                     </div>
                 `;
 
                 container.append(newRow);
                 Utils.setupParamRow(container.children().last());
                 if (typeof $.fn.sortable !== 'undefined') {
                     container.sortable('refresh');
                 }
             },
             renumberParams() {
                 $('.gw-param-container').each(function() {
                     const container = $(this);
                     const paramType = container.attr('id').replace('add_', '').replace('update_', '');
                     let namePrefix;
 
                     if (paramType === 'query_params') {
                         namePrefix = 'meta_data[query_params]';
                     } else if (paramType === 'custom_headers') {
                         namePrefix = 'meta_data[headers]';
                     } else if (paramType === 'form_data') {
                         namePrefix = 'meta_data[form_data]';
                     } else if (paramType === 'urlencoded_data') {
                         namePrefix = 'meta_data[urlencoded_data]';
                     }
 
                     container.children().each(function(index) {
                         $(this).find('input[name$="[key]"]').attr('name', `${namePrefix}[${index}][key]`);
                         $(this).find('input[name$="[value]"]').attr('name', `${namePrefix}[${index}][value]`);
                         $(this).find('input[name$="[enabled]"]').attr('name', `${namePrefix}[${index}][enabled]`);
                     });
                 });
             }
         };
 
         const CustomApiForm = {
            
             lastConfig: null,
             currentStep: 1,
             totalSteps: 5,
             prefix: 'add',
             init() {
                $('#add-custom-tab').on('click', () => {
                    this.prefix = 'add';
                    if (!$('#add-custom-api-content').hasClass('initialized')) {
                        this.generateForm();
                        $('#add-custom-api-content').addClass('initialized');
                    }
                });

                $('#update-custom-tab').on('click', () => {
                    this.prefix = 'update';
                    const addApiContent = $(`#add-custom-api-content`);
                    addApiContent.empty();
                    if (!$('#update-custom-api-content').hasClass('initialized')) {
                        const metaData = $('#updateSmsGateway').data('meta_data') || {};
                        this.generateForm(metaData);
                        $('#update-custom-api-content').addClass('initialized');
                    }
                });

                $('#addSmsGateway').on('shown.bs.modal', () => {
                    
                    $('#add-custom-api-content').removeClass('initialized'); 
                    if ($('#add-custom-tab').hasClass('active')) {
                        $('#add-custom-tab').trigger('click');
                    }
                });

                $('#updateSmsGateway').on('shown.bs.modal', () => {
                    
                    $('#update-custom-api-content').removeClass('initialized'); 
                    if ($('#update-custom-tab').hasClass('active')) {
                        $('#update-custom-tab').trigger('click');
                    }
                });

                $('#addSmsGateway, #updateSmsGateway').on('hidden.bs.modal', () => {
                    this.lastConfig = null;
                    this.currentStep = 1;
                    $('#add-custom-api-content, #update-custom-api-content').removeClass('initialized');
                });
            },
             generateForm(config = {}) {
                
                 const content = $(`#${this.prefix}-custom-api-content`);
                 content.empty();
 
                 const stepIndicator = `
                     <div class="gw-step-indicator">
                         <div class="gw-step-items">
                             <div class="gw-step-item active" data-step="1">
                                 <div class="gw-step-circle">1</div>
                                 <div class="gw-step-label">${translations.api_url}</div>
                             </div>
                             <div class="gw-step-item" data-step="2">
                                 <div class="gw-step-circle">2</div>
                                 <div class="gw-step-label">${translations.headers}</div>
                             </div>
                             <div class="gw-step-item" data-step="3">
                                 <div class="gw-step-circle">3</div>
                                 <div class="gw-step-label">${translations.authorization}</div>
                             </div>
                             <div class="gw-step-item" data-step="4">
                                 <div class="gw-step-circle">4</div>
                                 <div class="gw-step-label">${translations.body}</div>
                             </div>
                             <div class="gw-step-item" data-step="5">
                                 <div class="gw-step-circle">5</div>
                                 <div class="gw-step-label">${translations.determine_status_by}</div>
                             </div>
                         </div>
                         <div class="gw-step-progress">
                             <div class="gw-step-progress-bar" id="step-progress-bar"></div>
                         </div>
                     </div>
                     <div class="gw-step-content">
                         ${this.generateStep1(config)}
                         ${this.generateStep2(config)}
                         ${this.generateStep3(config)}
                         ${this.generateStep4(config)}
                         ${this.generateStep5(config)}
                         <div class="gw-step-navigation">
                             <button type="button" class="gw-btn-prev" disabled>${translations.previous}</button>
                             <button type="button" class="gw-btn-next">${translations.next}</button>
                         </div>
                     </div>
                 `;
                 content.append(stepIndicator);
                 this.attachEventHandlers();
                 this.updateStepIndicator();
             },
             generateStep1(config = {}) {
                 const queryParamsRows = (config.query_params || []).map((param, index) => `
                     <div class="gw-param-row">
                         <div class="gw-param-drag">
                             <i class="ri-drag-move-line"></i>
                         </div>
                         <div class="gw-param-toggle">
                             <input type="checkbox" class="gw-param-checkbox" ${param.enabled ? 'checked' : ''} name="meta_data[query_params][${index}][enabled]">
                         </div>
                         <div class="gw-param-input">
                             <input type="text" name="meta_data[query_params][${index}][key]" value="${param.key || ''}" placeholder="${translations.query_key_placeholder}" class="gw-input gw-param-key" />
                         </div>
                         <div class="gw-param-input">
                             <input type="text" name="meta_data[query_params][${index}][value]" value="${param.value || ''}" placeholder="${translations.query_value_placeholder}" class="gw-input gw-param-value" />
                         </div>
                         <div class="gw-param-actions">
                             <button type="button" class="gw-param-delete">
                                 <i class="ri-delete-bin-line"></i>
                             </button>
                         </div>
                     </div>
                 `).join('');
 
                 return `
                     <div class="gw-step-pane active" id="step-1">
                         <div class="gw-api-card">
                             <div class="gw-api-card-header">
                                 <h3 class="gw-api-card-title">${translations.api_url_and_method}</h3>
                             </div>
                             <div class="gw-api-card-body">
                                 <div class="gw-form-group">
                                 <label for="${this.prefix}_api_url" class="gw-form-label">${translations.api_url} <span class="gw-text-danger">*</span></label>
                                 <input type="text" id="${this.prefix}_api_url" name="meta_data[url]" value="${config.url || ''}" placeholder="${translations.api_url_placeholder}" class="form-control"  />
                                 </div>
                                 <div class="gw-form-group">
                                 <label for="${this.prefix}_http_method" class="gw-form-label">${translations.http_method}</label>
                                 <div class="gw-custom-select">
                                     <select id="${this.prefix}_http_method" name="meta_data[method]" class="form-select">
                                         <option value="POST" ${config.method === 'POST' ? 'selected' : ''}>POST</option>
                                         <option value="GET" ${config.method === 'GET' ? 'selected' : ''}>GET</option>
                                     </select>
                                     <div class="gw-select-arrow">
                                         <i class="ri-arrow-down-s-line"></i>
                                     </div>
                                 </div>
                                 </div>
                                 <div class="gw-form-group">
                                 <label class="gw-form-label">${translations.query_parameters}
                                     <small class="gw-text-muted">${specialStringPattern}</small>
                                 </label>
                                 <div id="${this.prefix}_query_params" class="gw-param-container">
                                     ${queryParamsRows || `
                                         <div class="gw-param-row">
                                             <div class="gw-param-drag">
                                                 <i class="ri-drag-move-line"></i>
                                             </div>
                                             <div class="gw-param-toggle">
                                                 <input type="checkbox" class="gw-param-checkbox" checked name="meta_data[query_params][0][enabled]">
                                             </div>
                                             <div class="gw-param-input">
                                                 <input type="text" name="meta_data[query_params][0][key]" placeholder="${translations.query_key_placeholder}" class="gw-input gw-param-key" />
                                             </div>
                                             <div class="gw-param-input">
                                                 <input type="text" name="meta_data[query_params][0][value]" placeholder="${translations.query_value_placeholder}" class="gw-input gw-param-value" />
                                             </div>
                                             <div class="gw-param-actions">
                                                 <button type="button" class="gw-param-delete">
                                                 <i class="ri-delete-bin-line"></i>
                                                 </button>
                                             </div>
                                         </div>
                                     `}
                                 </div>
                                 <button type="button" class="gw-btn-add-param" id="${this.prefix}-query-param">${translations.add_query_parameter}</button>
                                 </div>
                             </div>
                         </div>
                     </div>
                 `;
             },
             generateStep2(config = {}) {
                 const headerRows = (config.headers || []).map((header, index) => `
                     <div class="gw-param-row">
                         <div class="gw-param-drag">
                             <i class="ri-drag-move-line"></i>
                         </div>
                         <div class="gw-param-toggle">
                             <input type="checkbox" class="gw-param-checkbox" ${header.enabled ? 'checked' : ''} name="meta_data[headers][${index}][enabled]">
                         </div>
                         <div class="gw-param-input">
                             <input type="text" name="meta_data[headers][${index}][key]" value="${header.key || ''}" placeholder="${translations.header_key_placeholder}" class="gw-input gw-param-key" />
                         </div>
                         <div class="gw-param-input">
                             <input type="text" name="meta_data[headers][${index}][value]" value="${header.value || ''}" placeholder="${translations.header_value_placeholder}" class="gw-input gw-param-value" />
                         </div>
                         <div class="gw-param-actions">
                             <button type="button" class="gw-param-delete">
                                 <i class="ri-delete-bin-line"></i>
                             </button>
                         </div>
                     </div>
                 `).join('');
 
                 return `
                     <div class="gw-step-pane" id="step-2">
                         <div class="gw-api-card">
                             <div class="gw-api-card-header">
                                 <h3 class="gw-api-card-title">${translations.headers}</h3>
                             </div>
                             <div class="gw-api-card-body">
                                 <div class="gw-form-group">
                                 <label class="gw-form-label">${translations.headers} <small class="gw-text-muted">${specialStringPattern}</small></label>
                                 <div id="${this.prefix}_custom_headers" class="gw-param-container">
                                     ${headerRows || `
                                         <div class="gw-param-row">
                                             <div class="gw-param-drag">
                                                 <i class="ri-drag-move-line"></i>
                                             </div>
                                             <div class="gw-param-toggle">
                                                 <input type="checkbox" class="gw-param-checkbox" checked name="meta_data[headers][0][enabled]">
                                             </div>
                                             <div class="gw-param-input">
                                                 <input type="text" name="meta_data[headers][0][key]" placeholder="${translations.header_key_placeholder}" class="gw-input gw-param-key" />
                                             </div>
                                             <div class="gw-param-input">
                                                 <input type="text" name="meta_data[headers][0][value]" placeholder="${translations.header_value_placeholder}" class="gw-input gw-param-value" />
                                             </div>
                                             <div class="gw-param-actions">
                                                 <button type="button" class="gw-param-delete">
                                                 <i class="ri-delete-bin-line"></i>
                                                 </button>
                                             </div>
                                         </div>
                                     `}
                                 </div>
                                 <button type="button" class="gw-btn-add-param" id="${this.prefix}-header">${translations.add_header}</button>
                                 </div>
                             </div>
                         </div>
                     </div>
                 `;
             },
             generateStep3(config = {}) {
                 return `
                     <div class="gw-step-pane" id="step-3">
                         <div class="gw-api-card">
                             <div class="gw-api-card-header">
                                 <h3 class="gw-api-card-title">${translations.authorization}</h3>
                             </div>
                             <div class="gw-api-card-body">
                                 <div class="gw-form-group">
                                 <label for="${this.prefix}_auth_type" class="gw-form-label">${translations.authorization_type}</label>
                                 <div class="gw-custom-select">
                                     <select id="${this.prefix}_auth_type" name="meta_data[auth_type]" class="form-select">
                                         <option value="none" ${config.auth_type === 'none' ? 'selected' : ''}>${translations.none}</option>
                                         <option value="api_key" ${config.auth_type === 'api_key' ? 'selected' : ''}>${translations.api_key}</option>
                                         <option value="bearer" ${config.auth_type === 'bearer' ? 'selected' : ''}>${translations.bearer_token}</option>
                                     </select>
                                     <div class="gw-select-arrow">
                                         <i class="ri-arrow-down-s-line"></i>
                                     </div>
                                 </div>
                                 </div>
                                 <div class="gw-auth-fields" id="${this.prefix}_auth_api_key_fields" style="display: ${config.auth_type === 'api_key' ? 'block' : 'none'};">
                                 <div class="gw-form-group">
                                     <label for="${this.prefix}_api_key_name" class="gw-form-label">${translations.api_key_name}
                                         <small class="gw-text-muted">${specialStringPattern}</small>
                                     </label>
                                     <input type="text" id="${this.prefix}_api_key_name" name="meta_data[api_key_name]" value="${config.api_key_name || ''}" placeholder="${translations.api_key_name_placeholder}" class="form-control" />
                                 </div>
                                 <div class="gw-form-group">
                                     <label for="${this.prefix}_api_key_value" class="gw-form-label">${translations.api_key_value}
                                         <small class="gw-text-muted">${specialStringPattern}</small>
                                     </label>
                                     <input type="text" id="${this.prefix}_api_key_value" name="meta_data[api_key_value]" value="${config.api_key_value || ''}" placeholder="${translations.api_key_value_placeholder}" class="form-control" />
                                 </div>
                                 </div>
                                 <div class="gw-auth-fields" id="${this.prefix}_auth_bearer_fields" style="display: ${config.auth_type === 'bearer' ? 'block' : 'none'};">
                                 <div class="gw-form-group">
                                     <label for="${this.prefix}_bearer_token" class="gw-form-label">${translations.bearer_token_label}
                                         <small class="gw-text-muted">${specialStringPattern}</small>
                                     </label>
                                     <input type="text" id="${this.prefix}_bearer_token" name="meta_data[bearer_token]" value="${config.bearer_token || ''}" placeholder="${translations.bearer_token_placeholder}" class="form-control" />
                                 </div>
                                 </div>
                             </div>
                         </div>
                     </div>
                 `;
             },
             generateStep4(config = {}) {
                 const formDataRows = (config.form_data || []).map((data, index) => `
                     <div class="gw-param-row">
                         <div class="gw-param-drag">
                             <i class="ri-drag-move-line"></i>
                         </div>
                         <div class="gw-param-toggle">
                             <input type="checkbox" class="gw-param-checkbox" ${data.enabled ? 'checked' : ''} name="meta_data[form_data][${index}][enabled]">
                         </div>
                         <div class="gw-param-input">
                             <input type="text" name="meta_data[form_data][${index}][key]" value="${data.key || ''}" placeholder="${translations.form_data_key_placeholder}" class="gw-input gw-param-key" />
                         </div>
                         <div class="gw-param-input">
                             <input type="text" name="meta_data[form_data][${index}][value]" value="${data.value || ''}" placeholder="${translations.form_data_value_placeholder}" class="gw-input gw-param-value" />
                         </div>
                         <div class="gw-param-actions">
                             <button type="button" class="gw-param-delete">
                                 <i class="ri-delete-bin-line"></i>
                             </button>
                         </div>
                     </div>
                 `).join('');
 
                 const urlencodedRows = (config.urlencoded_data || []).map((data, index) => `
                     <div class="gw-param-row">
                         <div class="gw-param-drag">
                             <i class="ri-drag-move-line"></i>
                         </div>
                         <div class="gw-param-toggle">
                             <input type="checkbox" class="gw-param-checkbox" ${data.enabled ? 'checked' : ''} name="meta_data[urlencoded_data][${index}][enabled]">
                         </div>
                         <div class="gw-param-input">
                             <input type="text" name="meta_data[urlencoded_data][${index}][key]" value="${data.key || ''}" placeholder="${translations.url_encoded_key_placeholder}" class="gw-input gw-param-key" />
                         </div>
                         <div class="gw-param-input">
                             <input type="text" name="meta_data[urlencoded_data][${index}][value]" value="${data.value || ''}" placeholder="${translations.url_encoded_value_placeholder}" class="gw-input gw-param-value" />
                         </div>
                         <div class="gw-param-actions">
                             <button type="button" class="gw-param-delete">
                                 <i class="ri-delete-bin-line"></i>
                             </button>
                         </div>
                     </div>
                 `).join('');
 
                 return `
                     <div class="gw-step-pane" id="step-4">
                         <div class="gw-api-card">
                             <div class="gw-api-card-header">
                                 <h3 class="gw-api-card-title">${translations.body}</h3>
                             </div>
                             <div class="gw-api-card-body">
                                 <div class="gw-form-group">
                                 <label for="${this.prefix}_body_type" class="gw-form-label">${translations.body_type}</label>
                                 <div class="gw-custom-select">
                                     <select id="${this.prefix}_body_type" name="meta_data[body_type]" class="form-select">
                                         <option value="none" ${config.body_type === 'none' ? 'selected' : ''}>${translations.none}</option>
                                         <option value="form-data" ${config.body_type === 'form-data' ? 'selected' : ''}>${translations.form_data}</option>
                                         <option value="x-www-form-urlencoded" ${config.body_type === 'x-www-form-urlencoded' ? 'selected' : ''}>${translations.url_encoded_data}</option>
                                         <option value="raw" ${config.body_type === 'raw' ? 'selected' : ''}>${translations.raw}</option>
                                     </select>
                                     <div class="gw-select-arrow">
                                         <i class="ri-arrow-down-s-line"></i>
                                     </div>
                                 </div>
                                 </div>
                                 <div class="gw-body-type-fields" id="${this.prefix}_body_form_data_fields" style="display: ${config.body_type === 'form-data' ? 'block' : 'none'};">
                                 <div class="gw-form-group">
                                     <label class="gw-form-label">${translations.form_data_label}
                                         <small class="gw-text-muted">${specialStringPattern}</small>
                                     </label>
                                     <div id="${this.prefix}_form_data" class="gw-param-container">
                                         ${formDataRows || `
                                             <div class="gw-param-row">
                                                 <div class="gw-param-drag">
                                                 <i class="ri-drag-move-line"></i>
                                                 </div>
                                                 <div class="gw-param-toggle">
                                                 <input type="checkbox" class="gw-param-checkbox" checked name="meta_data[form_data][0][enabled]">
                                                 </div>
                                                 <div class="gw-param-input">
                                                 <input type="text" name="meta_data[form_data][0][key]" placeholder="${translations.form_data_key_placeholder}" class="gw-input gw-param-key" />
                                                 </div>
                                                 <div class="gw-param-input">
                                                 <input type="text" name="meta_data[form_data][0][value]" placeholder="${translations.form_data_value_placeholder}" class="gw-input gw-param-value" />
                                                 </div>
                                                 <div class="gw-param-actions">
                                                 <button type="button" class="gw-param-delete">
                                                     <i class="ri-delete-bin-line"></i>
                                                 </button>
                                                 </div>
                                             </div>
                                         `}
                                     </div>
                                     <button type="button" class="gw-btn-add-param" id="${this.prefix}-form-data">${translations.add_form_data}</button>
                                 </div>
                                 </div>
                                 <div class="gw-body-type-fields" id="${this.prefix}_body_urlencoded_fields" style="display: ${config.body_type === 'x-www-form-urlencoded' ? 'block' : 'none'};">
                                 <div class="gw-form-group">
                                     <label class="gw-form-label">${translations.url_encoded_data_label}
                                         <small class="gw-text-muted">${specialStringPattern}</small>
                                     </label>
                                     <div id="${this.prefix}_urlencoded_data" class="gw-param-container">
                                         ${urlencodedRows || `
                                             <div class="gw-param-row">
                                                 <div class="gw-param-drag">
                                                 <i class="ri-drag-move-line"></i>
                                                 </div>
                                                 <div class="gw-param-toggle">
                                                 <input type="checkbox" class="gw-param-checkbox" checked name="meta_data[urlencoded_data][0][enabled]">
                                                 </div>
                                                 <div class="gw-param-input">
                                                 <input type="text" name="meta_data[urlencoded_data][0][key]" placeholder="${translations.url_encoded_key_placeholder}" class="gw-input gw-param-key" />
                                                 </div>
                                                 <div class="gw-param-input">
                                                 <input type="text" name="meta_data[urlencoded_data][0][value]" placeholder="${translations.url_encoded_value_placeholder}" class="gw-input gw-param-value" />
                                                 </div>
                                                 <div class="gw-param-actions">
                                                 <button type="button" class="gw-param-delete">
                                                     <i class="ri-delete-bin-line"></i>
                                                 </button>
                                                 </div>
                                             </div>
                                         `}
                                     </div>
                                     <button type="button" class="gw-btn-add-param" id="${this.prefix}-urlencoded-data">${translations.add_url_encoded_data}</button>
                                 </div>
                                 </div>
                                 <div class="gw-body-type-fields" id="${this.prefix}_body_raw_fields" style="display: ${config.body_type === 'raw' ? 'block' : 'none'};">
                                 <div class="gw-form-group">
                                     <label for="${this.prefix}_raw_type" class="gw-form-label">${translations.raw_type}</label>
                                     <div class="gw-custom-select">
                                         <select id="${this.prefix}_raw_type" name="meta_data[raw_type]" class="form-select">
                                             <option value="text" ${config.raw_type === 'text' ? 'selected' : ''}>${translations.text}</option>
                                             <option value="javascript" ${config.raw_type === 'javascript' ? 'selected' : ''}>${translations.javascript}</option>
                                             <option value="json" ${config.raw_type === 'json' ? 'selected' : ''}>${translations.json}</option>
                                             <option value="html" ${config.raw_type === 'html' ? 'selected' : ''}>${translations.html}</option>
                                             <option value="xml" ${config.raw_type === 'xml' ? 'selected' : ''}>${translations.xml}</option>
                                         </select>
                                         <div class="gw-select-arrow">
                                             <i class="ri-arrow-down-s-line"></i>
                                         </div>
                                     </div>
                                 </div>
                                 <div class="gw-form-group">
                                     <label for="${this.prefix}_raw_body" class="gw-form-label">${translations.raw_body}
                                         <small class="gw-text-muted">(${specialStringPattern})</small>
                                     </label>
                                     <textarea id="${this.prefix}_raw_body" name="meta_data[raw_body]" class="form-control gw-code-editor" rows="6" placeholder='${translations.raw_body_placeholder}'>${config.raw_body || ''}</textarea>
                                 </div>
                                 </div>
                             </div>
                         </div>
                     </div>
                 `;
             },
             generateStep5(config = {}) {
                 return `
                     <div class="gw-step-pane" id="step-5">
                         <div class="gw-api-card">
                             <div class="gw-api-card-header">
                                 <h3 class="gw-api-card-title">${translations.determine_status_by}</h3>
                             </div>
                             <div class="gw-api-card-body">
                                 <div class="gw-form-group">
                                 <label for="${this.prefix}_status_type" class="gw-form-label">${translations.status_type}</label>
                                 <div class="gw-custom-select">
                                     <select id="${this.prefix}_status_type" name="meta_data[status_type]" class="form-select">
                                        <option value="" disabled selected>${translations.default_disabled_status_type}</option>
                                        <option value="http_code" ${config.status_type === 'http_code' ? 'selected' : ''}>${translations.http_status_code}</option>
                                        <option value="response_key" ${config.status_type === 'response_key' ? 'selected' : ''}>${translations.response_body_key}</option>
                                     </select>
                                     <div class="gw-select-arrow">
                                         <i class="ri-arrow-down-s-line"></i>
                                     </div>
                                 </div>
                                 </div>
                                 <div class="gw-status-type-fields" id="${this.prefix}_status_http_fields" style="display: ${config.status_type === 'http_code' ? 'block' : 'none'};">
                                 <div class="gw-form-group">
                                     <label for="${this.prefix}_success_codes" class="gw-form-label">${translations.success_codes}</label>
                                     <input type="text" id="${this.prefix}_success_codes" name="meta_data[success_codes]" value="${config.success_codes || ''}" placeholder="${translations.success_codes_placeholder}" class="form-control" />
                                 </div>
                                 <div class="gw-form-group">
                                     <label for="${this.prefix}_failure_codes" class="gw-form-label">${translations.failure_codes}</label>
                                     <input type="text" id="${this.prefix}_failure_codes" name="meta_data[failure_codes]" value="${config.failure_codes || ''}" placeholder="${translations.failure_codes_placeholder}" class="form-control" />
                                 </div>
                                 </div>
                                 <div class="gw-status-type-fields" id="${this.prefix}_status_key_fields" style="display: ${config.status_type === 'response_key' ? 'block' : 'none'};">
                                 <div class="gw-form-group">
                                     <label for="${this.prefix}_status_key" class="gw-form-label">${translations.status_key}</label>
                                     <input type="text" id="${this.prefix}_status_key" name="meta_data[status_key]" value="${config.status_key || ''}" placeholder="${translations.status_key_placeholder}" class="form-control" />
                                 </div>
                                 <div class="gw-form-group">
                                     <label for="${this.prefix}_success_values" class="gw-form-label">${translations.success_values}</label>
                                     <input type="text" id="${this.prefix}_success_values" name="meta_data[success_values]" value="${config.success_values || ''}" placeholder="${translations.success_values_placeholder}" class="form-control" />
                                 </div>
                                 <div class="gw-form-group">
                                     <label for="${this.prefix}_failure_values" class="gw-form-label">${translations.failure_values}</label>
                                     <input type="text" id="${this.prefix}_failure_values" name="meta_data[failure_values]" value="${config.failure_values || ''}" placeholder="${translations.failure_values_placeholder}" class="form-control" />
                                 </div>
                                 </div>
                                 <div class="gw-form-group">
                                 <label for="${this.prefix}_error_key" class="gw-form-label">${translations.error_message_key}</label>
                                 <input type="text" id="${this.prefix}_error_key" name="meta_data[error_key]" value="${config.error_key || ''}" placeholder="${translations.error_message_key_placeholder}" class="form-control" />
                                 </div>
                                 <div class="gw-form-group">
                                 <label for="${this.prefix}_fallback_message" class="gw-form-label">${translations.fallback_error_message}</label>
                                 <input type="text" id="${this.prefix}_fallback_message" name="meta_data[fallback_message]" value="${config.fallback_message || ''}" placeholder="${translations.fallback_error_message_placeholder}" class="form-control" />
                                 </div>
                             </div>
                         </div>
                     </div>
                 `;
             },
             attachEventHandlers() {
                 $('.gw-step-item').off('click').on('click', (e) => {
                     const step = $(e.currentTarget).data('step');
                     this.currentStep = step;
                     this.updateStepIndicator();
                 });
 
                 $('.gw-btn-next').off('click').on('click', () => {
                     if (this.currentStep < this.totalSteps) {
                         this.currentStep++;
                         this.updateStepIndicator();
                     } else {
                         ConfigurationDisplay.show(this.prefix);
                     }
                 });
 
                 $('.gw-btn-prev').off('click').on('click', () => {
                     if (this.currentStep > 1) {
                         this.currentStep--;
                         this.updateStepIndicator();
                     }
                 });
 
                 $(`#${this.prefix}-query-param`).off('click').on('click', () => ParameterManager.addNewParamRow($(`#${this.prefix}_query_params`)));
                 $(`#${this.prefix}-header`).off('click').on('click', () => ParameterManager.addNewParamRow($(`#${this.prefix}_custom_headers`)));
                 $(`#${this.prefix}-form-data`).off('click').on('click', () => ParameterManager.addNewParamRow($(`#${this.prefix}_form_data`)));
                 $(`#${this.prefix}-urlencoded-data`).off('click').on('click', () => ParameterManager.addNewParamRow($(`#${this.prefix}_urlencoded_data`)));
 
                 $(`#${this.prefix}_body_type`).off('change').on('change', function() {
                     const bodyType = $(this).val();
                     $(`#${CustomApiForm.prefix}_body_form_data_fields, #${CustomApiForm.prefix}_body_urlencoded_fields, #${CustomApiForm.prefix}_body_raw_fields`).hide();
                     if (bodyType === 'form-data') {
                         $(`#${CustomApiForm.prefix}_body_form_data_fields`).show();
                     } else if (bodyType === 'x-www-form-urlencoded') {
                         $(`#${CustomApiForm.prefix}_body_urlencoded_fields`).show();
                     } else if (bodyType === 'raw') {
                         $(`#${CustomApiForm.prefix}_body_raw_fields`).show();
                     }
                 });
 
                 $(`#${this.prefix}_auth_type`).off('change').on('change', function() {
                     const authType = $(this).val();
                     $(`#${CustomApiForm.prefix}_auth_api_key_fields, #${CustomApiForm.prefix}_auth_bearer_fields`).hide();
                     if (authType === 'api_key') {
                         $(`#${CustomApiForm.prefix}_auth_api_key_fields`).show();
                     } else if (authType === 'bearer') {
                         $(`#${CustomApiForm.prefix}_auth_bearer_fields`).show();
                     }
                 });
 
                 $(`#${this.prefix}_status_type`).off('change').on('change', function() {
                     const statusType = $(this).val();
                     $(`#${CustomApiForm.prefix}_status_http_fields, #${CustomApiForm.prefix}_status_key_fields`).hide();
                     if (statusType === 'http_code') {
                         $(`#${CustomApiForm.prefix}_status_http_fields`).show();
                     } else if (statusType === 'response_key') {
                         $(`#${CustomApiForm.prefix}_status_key_fields`).show();
                     }
                 });
 
                 $('.gw-param-row').each(function() {
                     Utils.setupParamRow($(this));
                 });
 
                 $('.gw-param-container').each(function() {
                     Utils.initSortable($(this));
                 });
             },
             updateStepIndicator() {
                 const progressPercentage = ((this.currentStep - 1) / (this.totalSteps - 1)) * 100;
                 $('#step-progress-bar').css('width', progressPercentage + '%');
 
                 $('.gw-step-item').removeClass('active completed');
                 $('.gw-step-item').each((index, element) => {
                     const stepNum = index + 1;
                     const $element = $(element);
                     if (stepNum < this.currentStep) {
                         $element.addClass('completed');
                     } else if (stepNum === this.currentStep) {
                         $element.addClass('active');
                     }
                 });
 
                 $('.gw-step-pane').removeClass('active');
                 $(`#step-${this.currentStep}`).addClass('active');
 
                 if (this.currentStep === 1) {
                     $('.gw-btn-prev').prop('disabled', true);
                 } else {
                     $('.gw-btn-prev').prop('disabled', false);
                 }
 
                 if (this.currentStep === this.totalSteps) {
                     $('.gw-btn-next').text(translations.finish);
                 } else {
                     $('.gw-btn-next').text(translations.next);
                 }
             }
         };
 
         const ConfigurationDisplay = {
             show(prefix) {
                 const config = this.collectConfiguration(prefix);
                 const content = $(`#${prefix}-custom-api-content`);
                 content.empty();
 
                 const configCard = `
                     <div class="gw-api-card">
                         <div class="gw-api-card-header d-flex justify-content-between align-items-center">
                             <h3 class="gw-api-card-title">${translations.custom_api} Configuration</h3>
                             <button type="button" class="gw-edit-config icon-btn btn-ghost btn-sm success-soft circle ms-2 me-2">
                                 <i class="ri-edit-line"></i>
                             </button>
                         </div>
                         <div class="gw-api-card-body">
                             ${this.renderConfiguration(config)}
                             <div class="info-note">
                                 <i class="ri-information-line"></i>
                                 <span>${translations.custom_api_save_note}</span>
                             </div>
                         </div>
                     </div>
                 `;
                 content.append(configCard);
                 CustomApiForm.lastConfig = config;
 
                 $('.gw-edit-config').on('click', () => {
                     CustomApiForm.currentStep = 1;
                     CustomApiForm.generateForm(config);
                     CustomApiForm.updateStepIndicator();
                 });
 
                 $(`#${prefix}_is_custom_api`).val('1');
             },
             collectConfiguration(prefix) {
                 const config = {
                     url: $(`#${prefix}_api_url`).val(),
                     method: $(`#${prefix}_http_method`).val(),
                     query_params: [],
                     headers: [],
                     auth_type: $(`#${prefix}_auth_type`).val(),
                     api_key_name: $(`#${prefix}_api_key_name`).val() || '',
                     api_key_value: $(`#${prefix}_api_key_value`).val() || '',
                     bearer_token: $(`#${prefix}_bearer_token`).val() || '',
                     body_type: $(`#${prefix}_body_type`).val(),
                     form_data: [],
                     urlencoded_data: [],
                     raw_type: $(`#${prefix}_raw_type`).val() || '',
                     raw_body: $(`#${prefix}_raw_body`).val() || '',
                     status_type: $(`#${prefix}_status_type`).val(),
                     success_codes: $(`#${prefix}_success_codes`).val() || '',
                     failure_codes: $(`#${prefix}_failure_codes`).val() || '',
                     status_key: $(`#${prefix}_status_key`).val() || '',
                     success_values: $(`#${prefix}_success_values`).val() || '',
                     failure_values: $(`#${prefix}_failure_values`).val() || '',
                     error_key: $(`#${prefix}_error_key`).val() || '',
                     fallback_message: $(`#${prefix}_fallback_message`).val() || ''
                 };
 
                 $(`#${prefix}_query_params .gw-param-row`).each(function() {
                     const row = $(this);
                     config.query_params.push({
                         key: row.find('.gw-param-key').val(),
                         value: row.find('.gw-param-value').val(),
                         enabled: row.find('.gw-param-checkbox').is(':checked')
                     });
                 });
 
                 $(`#${prefix}_custom_headers .gw-param-row`).each(function() {
                     const row = $(this);
                     config.headers.push({
                         key: row.find('.gw-param-key').val(),
                         value: row.find('.gw-param-value').val(),
                         enabled: row.find('.gw-param-checkbox').is(':checked')
                     });
                 });
 
                 $(`#${prefix}_form_data .gw-param-row`).each(function() {
                     const row = $(this);
                     config.form_data.push({
                         key: row.find('.gw-param-key').val(),
                         value: row.find('.gw-param-value').val(),
                         enabled: row.find('.gw-param-checkbox').is(':checked')
                     });
                 });
 
                 $(`#${prefix}_urlencoded_data .gw-param-row`).each(function() {
                     const row = $(this);
                     config.urlencoded_data.push({
                         key: row.find('.gw-param-key').val(),
                         value: row.find('.gw-param-value').val(),
                         enabled: row.find('.gw-param-checkbox').is(':checked')
                     });
                 });
 
                 return config;
             },
             renderConfiguration(config) {
                 let html = '<ul class="information-list">';
                 const addListItem = (label, value) => {
                     if (value) {
                         html += `<li><span>${label}</span> <i class="bi bi-arrow-right"></i> <span class="text-break text-muted">${value}</span></li>`;
                     }
                 };
 
                 addListItem(translations.api_url, config.url);
                 addListItem(translations.http_method, config.method);
 
                 if (config.query_params.length > 0) {
                     html += `<li><span>${translations.query_parameters}</span> <ul>`;
                     config.query_params.forEach(param => {
                         if (param.key || param.value) {
                             html += `<li>${param.key}: ${param.value} (${param.enabled ? 'Enabled' : 'Disabled'})</li>`;
                         }
                     });
                     html += '</ul></li>';
                 }
 
                 if (config.headers.length > 0) {
                     html += `<li><span>${translations.headers}</span> <ul>`;
                     config.headers.forEach(header => {
                         if (header.key || header.value) {
                             html += `<li>${header.key}: ${header.value} (${header.enabled ? 'Enabled' : 'Disabled'})</li>`;
                         }
                     });
                     html += '</ul></li>';
                 }
 
                 addListItem(translations.authorization_type, config.auth_type);
                 if (config.auth_type === 'api_key') {
                     addListItem(translations.api_key_name, config.api_key_name);
                     addListItem(translations.api_key_value, config.api_key_value);
                 } else if (config.auth_type === 'bearer') {
                     addListItem(translations.bearer_token_label, config.bearer_token);
                 }
 
                 addListItem(translations.body_type, config.body_type);
                 if (config.body_type === 'form-data' && config.form_data.length > 0) {
                     html += `<li><span>${translations.form_data_label}</span> <ul>`;
                     config.form_data.forEach(data => {
                         if (data.key || data.value) {
                             html += `<li>${data.key}: ${data.value} (${data.enabled ? 'Enabled' : 'Disabled'})</li>`;
                         }
                     });
                     html += '</ul></li>';
                 } else if (config.body_type === 'x-www-form-urlencoded' && config.urlencoded_data.length > 0) {
                     html += `<li><span>${translations.url_encoded_data_label}</span> <ul>`;
                     config.urlencoded_data.forEach(data => {
                         if (data.key || data.value) {
                             html += `<li>${data.key}: ${data.value} (${data.enabled ? 'Enabled' : 'Disabled'})</li>`;
                         }
                     });
                     html += '</ul></li>';
                 } else if (config.body_type === 'raw') {
                     addListItem(translations.raw_type, config.raw_type);
                     addListItem(translations.raw_body, config.raw_body);
                 }
 
                 addListItem(translations.status_type, config.status_type === 'http_code' ? translations.http_status_code : translations.response_body_key);
                 if (config.status_type === 'http_code') {
                     addListItem(translations.success_codes, config.success_codes);
                     addListItem(translations.failure_codes, config.failure_codes);
                 } else {
                     addListItem(translations.status_key, config.status_key);
                     addListItem(translations.success_values, config.success_values);
                     addListItem(translations.failure_values, config.failure_values);
                 }
                 addListItem(translations.error_message_key, config.error_key);
                 addListItem(translations.fallback_error_message, config.fallback_message);
 
                 html += '</ul>';
                 return html;
             }
         };
 
         const FormSubmission = {
            init() {
                $('#add-sms-gateway-form').on('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    const activeTab = $('.gw-tab.active').data('tab');
                    const isCustomApi = activeTab === 'add-custom';
                    
                    $('#add_gateway_mode').val(isCustomApi ? 'custom' : 'built-in');
                    $('#add_is_custom_api').val(isCustomApi ? '1' : '0');
                    
                    if (isCustomApi) {
                        $('#add-built-in input[name^="meta_data"]').each(function() {
                            formData.delete($(this).attr('name'));
                        });
                        formData.delete('type');
                        const metaData = CustomApiForm.lastConfig || {};
                        formData.set('meta_data', JSON.stringify(metaData));
                    } else {
                        $('#add-custom input[name^="meta_data"], #add-custom select[name^="meta_data"], #add-custom textarea[name^="meta_data"]').each(function() {
                            formData.delete($(this).attr('name'));
                        });
                    }
                    
                    $.ajax({
                        url: $(this).attr('action'),
                        type: 'POST',
                        data: formData,
                        contentType: false,
                        processData: false,
                        success: function(response) {
                            if (response.status) {
                                notify('success', response.message);
                                window.location.reload();
                            } else {
                                notify('error', response.message);
                            }
                        },
                        error: function(xhr) {
                            const errorMessage = xhr.responseJSON?.message || 'Something went wrong';
                            notify('error', errorMessage);
                        }
                    });
                });
 
                $('#update-sms-gateway-form').on('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    const activeTab = $('.gw-tab.active').data('tab');
                    const isCustomApi = activeTab === 'update-custom';
                    
                    $('#update_gateway_mode').val(isCustomApi ? 'custom' : 'built-in');
                    $('#update_is_custom_api').val(isCustomApi ? '0' : '1');
                    
                    if (isCustomApi) {
                        $('#update-built-in input[name^="meta_data"]').each(function() {
                            formData.delete($(this).attr('name'));
                        });
                        formData.delete('type');
                        const metaData = CustomApiForm.lastConfig || {};
                        formData.set('meta_data', JSON.stringify(metaData));
                    } else {
                        $('#update-custom input[name^="meta_data"], #update-custom select[name^="meta_data"], #update-custom textarea[name^="meta_data"]').each(function() {
                            formData.delete($(this).attr('name'));
                        });
                    }
                    
                    $.ajax({
                        url: $(this).attr('action'),
                        type: 'POST',
                        data: formData,
                        contentType: false,
                        processData: false,
                        success: function(response) {
                            if (response.status) {
                                notify('success', response.message);
                                window.location.reload();
                            } else {
                                notify('error', response.message);
                            }
                        },
                        error: function(xhr) {
                            const errorMessage = xhr.responseJSON?.message || 'Something went wrong';
                            notify('error', errorMessage);
                        }
                    });
                });
 
                 $('#deleteSmsGateway form').on('submit', function(e) {
                     e.preventDefault();
                     const formData = new FormData(this);
 
                     $.ajax({
                         url: $(this).attr('action'),
                         method: 'POST',
                         data: formData,
                         processData: false,
                         contentType: false,
                         success: function(response) {
                             notify("success", translations.gateway_deleted_successfully || 'Gateway deleted successfully.');
                             $('#deleteSmsGateway').modal('hide');
                             window.location.reload();
                         },
                         error: function(xhr) {
                             let message = translations.error_deleting_gateway || 'An error occurred while deleting the gateway. Please try again.';
                             if (xhr.responseJSON && xhr.responseJSON.message) {
                                 message = xhr.responseJSON.message;
                             }
                             notify("error", message);
                         }
                     });
                 });
             }
         };
 
         $(document).ready(function() {
             if (!window.translations) {
                 console.error('Translations are not defined. Please ensure $customApiTranslationsJson is passed to the script.');
                 return;
             }
 
             flatpickr("#datePicker", {
                 dateFormat: "Y-m-d",
                 mode: "range",
             });
 
             $('.checkAll').click(function() {
                 $('input:checkbox').not(this).prop('checked', this.checked);
             });
 
             var bulkContactLimit = 1;
             var oldType = '';
             var oldInfo = [];
 
             $('.add-sms-gateway').on('click', function() {
                 const modal = $('#addSmsGateway');
                 modal.modal('show');
             });
 
             $('.update-sms-gateway').on('click', function() {
                 $('.newdataadd').empty();
                 $('.oldData').empty();
                 $('.select-gateway-type').empty();
                 $('.active').attr("selected", false);
                 $('.inactive').attr("selected", false);
                 $('.gatewayType').attr("selected", false);
 
                 var modal = $('#updateSmsGateway');
                 modal.find('form[id=update-sms-gateway-form]').attr('action', $(this).data('url'));
                 modal.find('input[name=name]').val($(this).data('gateway_name'));
                 modal.find('input[name=per_message_min_delay]').val($(this).data('per_message_min_delay'));
                 modal.find('input[name=per_message_max_delay]').val($(this).data('per_message_max_delay'));
                 modal.find('input[name=delay_after_count]').val($(this).data('delay_after_count'));
                 modal.find('input[name=reset_after_count]').val($(this).data('reset_after_count'));
                 modal.find('input[name=delay_after_duration]').val($(this).data('delay_after_duration'));
 
                 const isCustomApi = $(this).data('meta_data') && Object.keys($(this).data('meta_data')).some(key => ['url', 'method', 'auth_type'].includes(key));
                 modal.find('#update_is_custom_api').val(isCustomApi ? '1' : '0');
                 modal.find('#update_gateway_mode').val(isCustomApi ? 'custom' : 'built-in');
                 $('.gw-tab').removeClass('active');
                 $('.gw-tab-pane').removeClass('active');
                 if (isCustomApi) {
                     $('#update-custom-tab').addClass('active');
                     $('#update-custom').addClass('active');
                     modal.data('meta_data', $(this).data('meta_data'));
                     $('#update-custom-api-content').removeClass('initialized');
                 } else {
                     $('#update-built-in-tab').addClass('active');
                     $('#update-built-in').addClass('active');
                 }
 
                 var data = window.credentials || {};
                 oldType = $(this).data('gateway_type');
                 $.each(data, function(key, creds) {
                     
                     var option = $('<option class="text-uppercase gatewayType" value="'+ key +'">'+ textFormat(['_'], key, ' ') +'</option>');
                     $('#update_gateway_type').append(option);
                     if(oldType == key){
                        
                         option.attr("selected", true);
                         if (creds.native_bulk_support == true) {
                             var bulkLimitBlock = `
                                 <div class="col-12 mb-4" id="bulk_contact_limit_wrapper">
                                     <div class="form-inner">
                                         <label for="bulk_contact_limit" class="form-label">Bulk Contact Limit</label>
                                         <input value="1" type="number" min="1" id="bulk_contact_limit" name="bulk_contact_limit" placeholder="Enter Bulk Contact Limit" class="form-control" aria-label="bulk_contact_limit"/>
                                     </div>
                                 </div>
                             `;
                             $('.oldData').append(bulkLimitBlock);
                         }
                     }
                 });
                 bulkContactLimit = $(this).data('bulk_contact_limit');
                 modal.find('input[name=bulk_contact_limit]').val(bulkContactLimit);
 
                 oldInfo = $(this).data('meta_data');
                 if (!isCustomApi) {
                     var totalFields = Object.keys(oldInfo).length;
                     var index = 0;
 
                     $.each(oldInfo, function(key, value) {
                         var filterkey = key.replace("_", " ");
                         if (index % 2 === 0) {
                             var wrapper = $('<div class="col-12"></div>');
                             var row = $('<div class="row"></div>');
                             wrapper.append(row);
                             $('.oldData').append(wrapper);
                         }
 
                         var isLastOdd = (index === totalFields - 1) && (totalFields % 2 !== 0);
                         var colClass = isLastOdd ? 'col-12' : 'col-lg-6';
                         var div = $('<div class="mb-4 ' + colClass + '"></div>');
                         var label = $('<label for="' + key + '" class="form-label text-capitalize">' + filterkey + '<sup class="text--danger">*</sup></label>');
                         var input = $('<input type="text" class="form-control" id="' + key + '" value="' + value + '" name="meta_data[' + key + ']" placeholder="Enter ' + filterkey + '" >');
 
                         div.append(label, input);
                         $('.oldData .col-12:last .row').append(div);
                         index++;
                     });
                 }
 
                 modal.modal('show');
             });
 
             $('.gateway_type').on('change', function() {
                 $('.newdataadd').empty();
                 var data = window.credentials || {};
                 var newType = this.value;
 
                 if(newType != oldType){
                     $.each(data, function(key, v) {
                         $('.oldData').empty();
                         if(key == newType) {
                             if (v.native_bulk_support == true) {
                                 var bulkLimitBlock = `
                                     <div class="col-12 mb-4" id="bulk_contact_limit_wrapper">
                                         <div class="form-inner">
                                             <label for="bulk_contact_limit" class="form-label">Bulk Contact Limit</label>
                                             <input value="1" type="number" min="1" id="bulk_contact_limit" name="bulk_contact_limit" placeholder="Enter Bulk Contact Limit" class="form-control" aria-label="bulk_contact_limit"/>
                                         </div>
                                     </div>
                                 `;
                                 $('.newdataadd').append(bulkLimitBlock);
                             }
 
                             var creds = v.meta_data;
                             var keys = Object.keys(creds);
 
                             $.each(keys, function(index, key) {
                                 var defaultValue = creds[key];
                                 var filterkey = textFormat(['_'], key, ' ');
                                 var isLastOddItem = (keys.length % 2 !== 0) && (index === keys.length - 1);
                                 var divClass = isLastOddItem ? 'mb-4 col-12' : 'mb-4 col-lg-6';
                                 var div = $('<div class="' + divClass + '"></div>');
                                 var label = $('<label for="' + key + '" class="form-label text-capitalize">' + filterkey + '<sup class="text-danger">*</sup></label>');
                                 var input = $('<input type="text" class="form-control" id="' + key + '" name="meta_data[' + key + ']" placeholder="Enter ' + filterkey + '" >');
 
                                 div.append(label, input);
                                 $('.newdataadd').append(div);
                             });
                         }
                     });
                 } else {
                    $('.oldData').empty();
                     var oldKeys = Object.keys(oldInfo);
                     if (data[oldType].native_bulk_support == true) {
                         var bulkLimitBlock = `
                             <div class="col-12 mb-4" id="bulk_contact_limit_wrapper">
                                 <div class="form-inner">
                                     <label for="bulk_contact_limit" class="form-label">Bulk Contact Limit</label>
                                     <input value="1" type="number" min="1" id="bulk_contact_limit" name="bulk_contact_limit" placeholder="Enter Bulk Contact Limit" class="form-control" aria-label="bulk_contact_limit"/>
                                 </div>
                             </div>
                         `;
                         $('.oldData').append(bulkLimitBlock);
                     }
                     $('#updateSmsGateway').find('input[name=bulk_contact_limit]').val(bulkContactLimit);
 
                     $.each(oldKeys, function(index, key) {
                         var oldDefaultValue = oldInfo[key];
                         var filterkey = textFormat(['_'], key, ' ');
                         var isLastOddItem = (oldKeys.length % 2 !== 0) && (index === oldKeys.length - 1);
                         
                         var divClass = isLastOddItem ? 'mb-4 col-12' : 'mb-4 col-lg-6';
                         var div = $('<div class="' + divClass + '"></div>');
                         var label = $('<label for="' + key + '" class="form-label text-capitalize">' + filterkey + '<sup class="text-danger">*</sup></label>');
                         var input = $('<input type="text" class="form-control" id="' + key + '" value="' + oldDefaultValue + '" name="meta_data[' + key + ']" placeholder="Enter ' + filterkey + '" >');
 
                         div.append(label, input);
                         $('.oldData').append(div);
                     });
                 }
             });
 
             $('.quick-view').on('click', function() {
                 const modal = $('#quick_view');
                 const modalBodyInformation = modal.find('.modal-body .information-list');
                 modalBodyInformation.empty();
 
                 var driver = $(this).data('sms_gateways');
                 var uid = $(this).data('uid');
 
                 $.each(driver, function(key, value) {
                     const listItem = $('<li>');
                     const paramKeySpan = $('<span>').text(textFormat(['_'], key, ' '));
                     const arrowIcon = $('<i>').addClass('bi bi-arrow-right');
                     const paramValueSpan = $('<span>').addClass('text-break text-muted').text(value);
 
                     listItem.append(paramKeySpan).append(arrowIcon).append(paramValueSpan);
                     modalBodyInformation.append(listItem);
                 });
 
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
 
             $('.delete-sms-gateway').on('click', function() {
                 var modal = $('#deleteSmsGateway');
                 modal.find('form[id=deleteSmsGateway]').attr('action', $(this).data('url'));
                 modal.modal('show');
             });
 
             $('.gw-tab').on('click', function() {
                const tabId = $(this).data('tab');
                $('.gw-tab').removeClass('active');
                $(this).addClass('active');
                $('.gw-tab-pane').removeClass('active');
                $('#' + tabId).addClass('active');

                const isAddModal = tabId.startsWith('add');
                const isBuiltInTab = tabId === 'add-built-in' || tabId === 'update-built-in';
                const prefix = isAddModal ? 'add' : 'update';

                if (isAddModal) {
                    $('#add_gateway_mode').val(isBuiltInTab ? 'built-in' : 'custom');
                    $('#add_is_custom_api').val(isBuiltInTab ? '0' : '1');
                } else {
                    $('#update_gateway_mode').val(isBuiltInTab ? 'built-in' : 'custom');
                    $('#update_is_custom_api').val(isBuiltInTab ? '0' : '1');
                }

                if (isBuiltInTab) {
                    $(`#${prefix}_gateway_type`).attr('required', true);
                    $(`#${prefix}-built-in .newdataadd input, #${prefix}-built-in .oldData input`).attr('required', true);
                    
                    $(`#${prefix}-custom input, #${prefix}-custom select`).removeAttr('required');
                } else {
                    $(`#${prefix}_api_url`).attr('required', true);
                    $(`#${prefix}_gateway_type`).removeAttr('required');
                    $(`#${prefix}-built-in .newdataadd input, #${prefix}-built-in .oldData input`).removeAttr('required');
                }
            });
            
             CustomApiForm.init();
             FormSubmission.init();
         });
     })(jQuery);
 </script>
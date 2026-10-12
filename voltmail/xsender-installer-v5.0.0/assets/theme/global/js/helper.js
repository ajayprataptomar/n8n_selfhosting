"use strict";


const translationCache = new Map();

async function translate(keyword, langCode = null) {
    try {
        // Default to localStorage language or 'us'
        langCode = langCode || localStorage.getItem('app_language') || 'us';

        // Check cache
        const cacheKey = `translations-${langCode}`;
        if (translationCache.has(cacheKey)) {
            const translations = translationCache.get(cacheKey);
            const langKey = keyword.toLowerCase().replace(/[^a-z0-9_]/g, '').replace(/\s+/g, '_');
            
            if (translations[langKey]) {
                return translations[langKey].charAt(0).toUpperCase() + translations[langKey].slice(1);
            }
            if (translations.fallback && translations.fallback[langKey]) {
                return translations.fallback[langKey].charAt(0).toUpperCase() + translations.fallback[langKey].slice(1);
            }
        }

        // Fetch translations from API
        const response = await fetch(`/api/translations/${langCode}`, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const data = await response.json();

        if (!data.status) {
            throw new Error(data.message || 'Failed to fetch translations');
        }

        // Cache translations
        translationCache.set(cacheKey, {
            translations: data.translations,
            fallback: data.fallback_translations
        });

        // Try the translation key
        const langKey = keyword.toLowerCase().replace(/[^a-z0-9_]/g, '').replace(/\s+/g, '_');
        if (data.translations[langKey]) {
            return data.translations[langKey].charAt(0).toUpperCase() + data.translations[langKey].slice(1);
        }
        if (data.fallback_translations[langKey]) {
            return data.fallback_translations[langKey].charAt(0).toUpperCase() + data.fallback_translations[langKey].slice(1);
        }

        // Return original keyword if no translation found
        return keyword.charAt(0).toUpperCase() + keyword.slice(1);
    } catch (error) {
        console.error('Translation error:', error);
        return keyword.charAt(0).toUpperCase() + keyword.slice(1);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const langCode = localStorage.getItem('app_language');
    if (langCode) {
        const originalOpen = XMLHttpRequest.prototype.open;
        const originalSetRequestHeader = XMLHttpRequest.prototype.setRequestHeader;
        const currentDomain = window.location.origin;
        const baseUrl = document.querySelector('meta[name="base-url"]')?.content || currentDomain;
        XMLHttpRequest.prototype.open = function(method, url) {

            originalOpen.apply(this, arguments);
            this._requestUrl = url;
            this.isAjaxRequest = true;
        };

        XMLHttpRequest.prototype.setRequestHeader = function(header, value) {

            if (header === 'X-App-Language') {

                const requestUrl = this._requestUrl;
                const isSameOrigin = requestUrl.startsWith('/') || 
                                   requestUrl.startsWith(currentDomain) || 
                                   requestUrl.startsWith(baseUrl);
                
                if (isSameOrigin) {
                    originalSetRequestHeader.call(this, header, value);
                }
                return;
            }
            originalSetRequestHeader.call(this, header, value);
        };

        XMLHttpRequest.prototype.open = function(method, url) {
            originalOpen.apply(this, arguments);
            
            this._requestUrl = url;
            this.isAjaxRequest = true;
            
            const isSameOrigin = url.startsWith('/') || 
                               url.startsWith(currentDomain) || 
                               url.startsWith(baseUrl);
            
            if (this.isAjaxRequest && isSameOrigin) {
                this._shouldAddLanguageHeader = true;
            }
        };

        const originalSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.send = function(data) {
            if (this._shouldAddLanguageHeader) {
                try {
                    this.setRequestHeader('X-App-Language', langCode);
                } catch (e) {
                    console.log('Could not add language header:', e.message);
                }
            }
            originalSend.call(this, data);
        };
    }
});
var dir = document.documentElement.getAttribute('dir') || 'ltr';
var editors = {};

// v4.8 — CKEditor <p> margin override.
// CKEditor Classic ships with the browser-default `p { margin: 1em 0 }` in its
// editing view stylesheet. That's the source of the "extra spacing" customers
// reported on every Enter keypress. This injects a scoped override once (guarded
// by a marker element) so paragraphs stack with a tight 8px rhythm instead of
// giant gaps. Applied to `.ck-content` (editor) AND to any preview iframe body
// with class `email-preview-frame`.
(function injectCkEditorSpacingReset () {
    if (typeof document === 'undefined') return;
    if (document.getElementById('xs-ck-spacing-reset')) return;
    var style = document.createElement('style');
    style.id = 'xs-ck-spacing-reset';
    style.textContent = [
        '.ck-content p { margin: 0 0 8px 0; }',
        '.ck-content p:last-child { margin-bottom: 0; }',
        '.ck-content ul, .ck-content ol { margin: 0 0 8px 24px; }',
        '.ck-content h1, .ck-content h2, .ck-content h3, .ck-content h4, .ck-content h5, .ck-content h6 { margin: 12px 0 8px 0; }',
        '.email-preview-frame body p { margin: 0 0 8px 0; }',
        '.email-preview-frame body p:last-child { margin-bottom: 0; }'
    ].join('\n');
    document.head.appendChild(style);
})();

//Ck editor
function ck_editor(textarea) {

    class MinHeightPlugin {
      constructor(editor) {
          this.editor = editor;
      }

      init() {
        this.editor.ui.view.editable.extendTemplate({
            attributes: {
                style: {
                    minHeight: '300px'
                }
            }
        });
      }
    }
    CKEDITOR.ClassicEditor.builtinPlugins.push(MinHeightPlugin);
    CKEDITOR.ClassicEditor.create(document.querySelector(textarea), {
        placeholder: document.querySelector(textarea).getAttribute("placeholder"),
        
        toolbar: {
          items: [
            'heading',
            'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor', 'highlight', '|',
            'alignment', '|',
            'bold', 'italic', 'strikethrough', 'underline', 'subscript', 'superscript', 'removeFormat', 'findAndReplace', '-',
            'bulletedList', 'numberedList', '|',
            'outdent', 'indent', '|',
            'undo', 'redo',
            'link', 'insertImage', 'blockQuote', 'insertTable', 'mediaEmbed', 'htmlEmbed', '|',
            'horizontalLine', 'pageBreak', '|',
            'sourceEditing'
          ],
          shouldNotGroupWhenFull: true
        },
        list: {
          properties: {
            styles: true,
            startIndex: true,
            reversed: true
          }
        },

        heading: {
          options: [
            { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
            { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
            { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
            { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' },
            { model: 'heading4', view: 'h4', title: 'Heading 4', class: 'ck-heading_heading4' },
            { model: 'heading5', view: 'h5', title: 'Heading 5', class: 'ck-heading_heading5' },
            { model: 'heading6', view: 'h6', title: 'Heading 6', class: 'ck-heading_heading6' }
          ]
        },
        fontFamily: {
          options: [
            'default',
            'Arial, Helvetica, sans-serif',
            'Courier New, Courier, monospace',
            'Georgia, serif',
            'Lucida Sans Unicode, Lucida Grande, sans-serif',
            'Tahoma, Geneva, sans-serif',
            'Times New Roman, Times, serif',
            'Trebuchet MS, Helvetica, sans-serif',
            'Verdana, Geneva, sans-serif'
          ],
          supportAllValues: true
        },
        fontSize: {
          options: [10, 12, 14, 'default', 18, 20, 22],
          supportAllValues: true
        },
        htmlSupport: {
          allow: [
            {
              name: /.*/,
              attributes: true,
              classes: true,
              styles: true
            }
          ]
        },
        htmlEmbed: {
          showPreviews: true
        },
        link: {
          decorators: {
            addTargetToExternalLinks: true,
            defaultProtocol: 'https://',
            toggleDownloadable: {
              mode: 'manual',
              label: 'Downloadable',
              attributes: {
                download: 'file'
              }
            }
          }
        },
        mention: {
          feeds: [
            {
              marker: '@',
              feed: [
                '@apple', '@bears', '@brownie', '@cake', '@cake', '@candy', '@canes', '@chocolate', '@cookie', '@cotton', '@cream',
                '@cupcake', '@danish', '@donut', '@dragée', '@fruitcake', '@gingerbread', '@gummi', '@ice', '@jelly-o',
                '@liquorice', '@macaroon', '@marzipan', '@oat', '@pie', '@plum', '@pudding', '@sesame', '@snaps', '@soufflé',
                '@sugar', '@sweet', '@topping', '@wafer'
              ],
              minimumCharacters: 1
            }
          ]
        },
        removePlugins: [
          'CKBox',
          'CKFinder',
          'EasyImage',
          'RealTimeCollaborativeComments',
          'RealTimeCollaborativeTrackChanges',
          'RealTimeCollaborativeRevisionHistory',
          'PresenceList',
          'Comments',
          'TrackChanges',
          'TrackChangesData',
          'RevisionHistory',
          'Pagination',
          'WProofreader',
          'MathType'
        ]
    })
    .then(editor => {
      editors[textarea] = editor;
    })
    .catch(error => {
        console.error(error);
    });
}

//select two
function select2_search(placeholder_message, modal = null, tags = false) {
    
    $('.select2-search').select2({
        
        placeholder: placeholder_message,
        allowClear: false,
        dropdownAutoWidth: true,
        width: '100%',
        minimumResultsForSearch: 0,
        dir: dir,
        tags: tags, 
        dropdownParent: modal
    
    }).on('select2:open', function() {
    
        let maxItems = parseInt($(this).attr('data-show'), 10) || 10;
        $('.select2-results__options').css('max-height', `${maxItems * 40}px`); 
    });
}
    
//switch background update

function updateBackgroundClass() {

    $('.form-inner-switch').each(function() {

      const formInnerSwitch = $(this);
      const $checkbox = formInnerSwitch.closest('.form-inner').find('.switch-input');
  
    });
}

//Activate children checkbox fields

function setInitialVisibility() {
    
    if ($('.parent input[type="checkbox"]').is(':checked')) {

        $('.child').show().removeClass('d-none');
    } else {

        $('.child').hide().addClass('d-none');
    }
}

function toggleChildren() {

    if ($('.parent input[type="checkbox"]').is(':checked')) {

        $('.child').fadeIn(500).removeClass('d-none');
    } else {

        $('.child').fadeOut(500, function() {

            $(this).addClass('d-none');
        });
    }
}

//Copy text
function copy_text(button, message) {

    var inputField = button.closest('.input-group').find('.form-control').get(0); 
    var val = inputField.value; 

    navigator.clipboard.writeText(val)
    .then(function() {
        notify("success", message);
    })
    .catch(function(error) {
        console.error('Unable to copy text: ', error);
        notify("error", "Failed to copy text!");
    });
}

//Generate Random String 
function generateRandomString(length) {

    var characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    var result = '';
    for (var i = 0; i < length; i++) {
        result += characters.charAt(Math.floor(Math.random() * characters.length));
    }
    notify("info", "Code successfully generated.");
    return result;
}

//Text Formatting 
function textFormat(symbols, data, replaceWith) {
  
  symbols = symbols || [];
  replaceWith = replaceWith || ' ';

  var convertedString = data.replace(new RegExp(symbols.join('|'), 'g'), replaceWith)
                            .toLowerCase()
                            .replace(/(?:^|\s)\S/g, function(a) {
                                return a.toUpperCase();
                            });

  return convertedString;
}


//Bulk Action 

$(document).on('click', '.check-all', function(e) {

    if ($(this).is(':checked')) {

        $('.data-checkbox').prop('checked', true);
        $('.bulk-action').removeClass('d-none');
    } else {

        $('.data-checkbox').prop('checked', false);
        $('.bulk-action').addClass('d-none');
    }
    checkCheckboxEvent('.data-checkbox', '.check-all');
});

$(document).on('click', '.data-checkbox', function(e) {

    checkCheckboxEvent('.data-checkbox', '.check-all');
});

$('#bulk_status').on('change', function() {

    const selectedStatus = $(this).val();
    const modal = $('#bulkAction');
    const form = modal.find('form');
    form.find('input[name="ids[]"], input[name="status"], input[name="type"]').remove();
    $('.data-checkbox:checked').each(function() {
      
        $('<input>').attr({
            type: 'hidden',
            name: 'ids[]',
            value: $(this).val()
        }).appendTo(form);
    });

    $('<input>').attr({

        type: 'hidden',
        name: 'status',
        value: selectedStatus
    }).appendTo(form);

    $('<input>').attr({

        type: 'hidden',
        name: 'type',
        value: 'status'
    }).appendTo(form);

    modal.modal('show');
});

$(document).on('click', '.bulk-delete-btn', function(e) {

    const modal = $('#bulkAction');
    const form = modal.find('form');
    form.find('input[name="ids[]"], input[name="status"], input[name="type"]').remove();
    $('.data-checkbox:checked').each(function() {

        $('<input>').attr({
            type: 'hidden',
            name: 'ids[]',
            value: $(this).val()
        }).appendTo(form);
    });

    $('<input>').attr({

        type: 'hidden',
        name: 'type',
        value: 'delete'
    }).appendTo(form);
    modal.modal('show');
});

function checkCheckboxEvent(checkboxSelector, checkAllSelector) {

    const totalCheckboxes   = $(checkboxSelector).length;
    const checkedCheckboxes = $(checkboxSelector + ':checked').length;

    if (checkedCheckboxes === totalCheckboxes) {

        $(checkAllSelector).prop('checked', true);
    } else {

        $(checkAllSelector).prop('checked', false);
    }

    if (checkedCheckboxes > 0) {

        $('.bulk-action').removeClass('d-none');
    } else {
      
        $('.bulk-action').addClass('d-none');
    }
}

$(document).on('click','.generate-token' ,function(e) {

  e.preventDefault();
  var randomString = generateRandomString(32);
  $('.verify_token').val(randomString);
});

$('.back-to-menu').on('click', function() {
  $('html').removeClass('menu-active');
  $('.sub-menu-wrapper').removeClass('show');
});

/**
 * Format a number with the specified precision and add suffixes for large numbers.
 * 
 * @param {number} number - The number to format.
 * @param {number} precision - The number of decimal places to round to.
 * @returns {string} - The formatted number.
 */
function formatNumber(number, precision = 2) {

  if (isNaN(number)) {

      return "Invalid number format";
  }

  number = parseFloat(number);
  const negative = number < 0;
  number = Math.abs(number);

  if (number < 1000) {
      return (negative ? "-" : "") + number.toFixed(precision);
  }

  const formatters = {
      1e24: ['Y', 1e24],
      1e21: ['Z', 1e21],
      1e18: ['E', 1e18],
      1e15: ['P', 1e15],
      1e12: ['T', 1e12],
      1e9: ['B', 1e9],
      1e6: ['M', 1e6],
      1e3: ['K', 1e3]
  };

  for (const [divisor, [suffix, value]] of Object.entries(formatters)) {
      if (number >= value) {
          const formattedNumber = (number / value).toFixed(precision);
          return (negative ? "-" : "") + formattedNumber + suffix;
      }
  }
  
  return (negative ? "-" : "") + number.toFixed(precision);
}








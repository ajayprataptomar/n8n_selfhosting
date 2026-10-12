<script>
	(function($){
		"use strict";

        select2_search($('.select2-search').data('placeholder'));
        flatpickr("#datePicker", {
            dateFormat: "Y-m-d",
            mode: "range",
        });

        $(document).ready(function() {

            var bulkContactLimit = 1;

            $('.checkAll').click(function(){
                $('input:checkbox').not(this).prop('checked', this.checked);
            });
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
                $('.active').attr("selected",false);
                $('.inactive').attr("selected",false);
                $('.gatewayType').attr("selected",false);

                var modal = $('#updateSmsGateway');
                modal.find('form[id=updateSmsGateway]').attr('action', $(this).data('url'));
                modal.find('input[name=name]').val($(this).data('gateway_name'));
                bulkContactLimit = $(this).data('bulk_contact_limit');
                modal.find('input[name=per_message_min_delay]').val($(this).data('per_message_min_delay'));
                modal.find('input[name=per_message_max_delay]').val($(this).data('per_message_max_delay'));
                modal.find('input[name=delay_after_count]').val($(this).data('delay_after_count'));
                modal.find('input[name=reset_after_count]').val($(this).data('reset_after_count'));
                modal.find('input[name=delay_after_duration]').val($(this).data('delay_after_duration'));

                var previousType = $(this).data('gateway_type');
                
                $(this).data('gateway_status') == 1 ? $('.active').attr("selected",true) : $('.inactive').attr("selected",true);
                oldType = $(this).data('gateway_type');
                var data = Object.keys(<?php echo $jsonArray ?>);
                var creds = <?php echo $jsonArray ?>;

                $.each(data, function(key, value) {
                    
                    var option = $('<option class="text-uppercase gatewayType" value="'+ value +'">'+ textFormat(["_"], value, " ") +'</option>');
                    $('.select-gateway-type').append(option);
                    if(oldType == value){
                        if (creds[value].native_bulk_support == true) {
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
                        $('.gatewayType').attr("selected",true)
                    }
                });
                modal.find('input[name=bulk_contact_limit]').val(bulkContactLimit);
                oldInfo = $(this).data('meta_data');

                $.each(oldInfo, function(key, value) {
                    if(key != 'encryption') {

                        var filterkey = key.replace("_", " ");
                        var div   = $('<div class="mb-4 col-lg-6"></div>');
                        var label = $('<label for="' + key + '" class="form-label text-capitalize">' + filterkey + '<sup class="text--danger">*</sup></label>');
                        var input = $('<input type="text" class="form-control" id="' + key + '" value="' + value + '" name="meta_data[' + key + ']" placeholder="Enter ' + filterkey + '" required>');

                        div.append(label, input);
                        $('.oldData').append(div);

                    }
                    else{
                        $.each(creds[oldType], function(k, v) {

                            $.each(v, function(cred_key, cred_value) {

                                if(cred_key == key) {
                                   
                                    var filterkey = key.replace("_", " ");
                                    var div   = $('<div class="mb-4 col-lg-6"></div>');
                                    var label  = $('<label for="' + key + '" class="form-label text-capitalize">' + filterkey + '<sup class="text--danger">*</sup></label>');
                                    var select = $('<select class="form-select" name="meta_data[' + key + ']" id="'+key+'"></select>')
                                    $.each(cred_value, function(name, method) {

                                        var option = $('<option class="encryptionType" value="'+method+'">'+name+'</option>');

                                        select.append(option);
                                        if(method == value){
                                            option.attr("selected",true)
                                        }

                                    });


                                    div.append(label,select);
                                    $('.oldData').append(div);

                                }
                            });
                        });

                    }
                });

                modal.modal('show');
            });

            $('.gateway_type').on('change', function() {

               $('.newdataadd').empty();
               var data = <?php echo $jsonArray ?>;
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

                            
                            var creds   = v.meta_data;
                            var keys    = Object.keys(creds);

                            $.each(keys, function(index, key) {

                                var defaultValue = creds[key];
                                var filterkey = textFormat(['_'], key, ' ');
                                
                                var isLastOddItem = (keys.length % 2 !== 0) && (index === keys.length - 1);
                                var divClass = isLastOddItem ? 'mb-4 col-12' : 'mb-4 col-lg-6';
                                
                                var div   = $('<div class="' + divClass + '"></div>');
                                var label = $('<label for="' + key + '" class="form-label text-capitalize">' + filterkey + '<sup class="text-danger">*</sup></label>');
                                var input = $('<input type="text" class="form-control" id="' + key + '" name="meta_data[' + key + ']" placeholder="Enter ' + filterkey + '" required>');
                                
                                div.append(label, input);
                                $('.newdataadd').append(div);
                            });
                        }
                   });
               }
               else{

                var oldKeys    = Object.keys(oldInfo);
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
                        var div   = $('<div class="' + divClass + '"></div>');
                        var label = $('<label for="' + key + '" class="form-label text-capitalize">' + filterkey + '<sup class="text-danger">*</sup></label>');
                        var input = $('<input type="text" class="form-control" id="' + key + '" value="' + oldDefaultValue + '" name="meta_data[' + key + ']" placeholder="Enter ' + filterkey + '" required>');
                        div.append(label, input);
                        $('.oldData').append(div);
                    });
               }
            });

            $('.quick-view').on('click', function() {
                const modal = $('#quick_view');
                const modalBody = modal.find('.modal-body .information-list');
                modalBody.empty();

                const dataAttributes = $(this).data();

                for (const [key, value] of Object.entries(dataAttributes)) {
                    if (key === 'sms_gateways') {
                        const sms_gateways = value;
                        for (const [paramKey, paramValue] of Object.entries(sms_gateways)) {
                            const listItem = $('<li>');
                            const paramKeySpan = $('<span>').text(textFormat(['_'], paramKey, ' '));
                            const arrowIcon = $('<i>').addClass('bi bi-arrow-right');
                            const paramValueSpan = $('<span>').addClass(' text-muted').text(paramValue);

                            listItem.append(paramKeySpan).append(arrowIcon).append(paramValueSpan);
                            modalBody.append(listItem);
                        }
                    } else if (key !== 'bsTarget' && key !== 'bsToggle') {
                        const listItem = $('<li>');
                        const keySpan = $('<span>').text(textFormat(['_'], key, ' '));
                        const arrowIcon = $('<i>').addClass('bi bi-arrow-right');
                        const valueSpan = $('<span>').addClass(' text-muted').text(value);

                        listItem.append(keySpan).append(arrowIcon).append(valueSpan);
                        modalBody.append(listItem);
                    } 
                }

                modal.modal('show');
            });

            $('.delete-sms-gateway').on('click', function() {

                var modal = $('#deleteSmsGateway');
                modal.find('input[name=id]').val($(this).data('sms-gateway-id'));
                modal.modal('show');
            });
        });
	})(jQuery);
</script>
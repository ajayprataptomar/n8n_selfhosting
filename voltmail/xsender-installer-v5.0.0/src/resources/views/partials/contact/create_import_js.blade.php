<script>
    (function($) {
        "use strict";

        function estimateTimeRemaining(createdAt, progress) {
            if (progress <= 0 || progress >= 100) return "N/A";

            const startTime = new Date(createdAt).getTime();
            const currentTime = new Date().getTime();
            const elapsedTime = (currentTime - startTime) / 1000; 
            const timePerPercent = elapsedTime / progress; 
            const remainingProgress = 100 - progress;
            const remainingTime = remainingProgress * timePerPercent; 

            if (remainingTime < 60) {
                return `${Math.round(remainingTime)} seconds`;
            } else if (remainingTime < 3600) {
                return `${Math.round(remainingTime / 60)} minutes`;
            } else {
                const hours = Math.floor(remainingTime / 3600);
                const minutes = Math.round((remainingTime % 3600) / 60);
                return `${hours} hour${hours > 1 ? 's' : ''} ${minutes} minute${minutes > 1 ? 's' : ''}`;
            }
        }

        function checkImportStatus(groupId) {
            if (!groupId) {
                $('.file-upload-container').removeClass('d-none');
                $('.upload-progress-container').addClass('d-none');
                return;
            }

            const $fileUploadContainer = $('.file-upload-container');
            const $progressContainer = $('.upload-progress-container');
            const $progressBar = $progressContainer.find('.upload-progress-bar');
            const $progressText = $progressContainer.find('.upload-progress-text');
            const $details = $progressContainer.find('.upload-progress-details');

            $.ajax({
                url: '{{ route("{$panel}.contact.group.import.progress") }}',
                method: 'GET',
                data: { group_ids: [groupId] },
                success: function(response) {
                    const currentGroupId = $('#group_id').val();
                    if (currentGroupId !== groupId) {
                        return;
                    }

                    const data = response[groupId] || { status: 'none' };
                    
                    if (data.status === "{{ \App\Enums\System\ContactImportStatusEnum::PENDING->value }}" 
                        || data.status === "{{ \App\Enums\System\ContactImportStatusEnum::PROCESSING->value }}") {
                        $fileUploadContainer.addClass('d-none');
                        $progressContainer.removeClass('d-none');

                        const progress = data.progress || 0;
                        $progressBar.css('width', `${progress}%`);
                        $progressContainer.find('.upload-progress-text').text(`${progress.toFixed(1)}%`);
                        $progressBar.attr('aria-valuenow', progress);

                        const estimatedTime = estimateTimeRemaining(data.created_at, progress);
                        $details.html(`
                            <p><strong><i class="ri-file-list-line me-1"></i>{{ translate("File") }}:</strong> ${data.file_name || 'Unknown'}</p>
                            <p><strong><i class="ri-time-line me-1"></i>{{ translate("Started at") }}:</strong> ${data.created_at}</p>
                            <p><strong><i class="ri-contacts-line me-1"></i>{{ translate("Processed") }}:</strong> ${data.processed_contacts} {{ translate("contacts") }}</p>
                        `);

                        setTimeout(() => checkImportStatus(groupId), 5000);
                    } else {
                        $fileUploadContainer.removeClass('d-none');
                        $progressContainer.addClass('d-none');

                        $('#file_upload').val('');
                        $('.file__info').addClass('d-none').empty();
                        $('.uplaod-file').removeClass('d-none');
                    }
                },
                error: function() {
                    const currentGroupId = $('#group_id').val();
                    if (currentGroupId !== groupId) {
                        return;
                    }
                    $('.file-upload-container').removeClass('d-none');
                    $('.upload-progress-container').addClass('d-none');
                }
            });
        }

        $(document).ready(function() {
            let lastGroupId = $('#group_id').val();
            checkImportStatus(lastGroupId);
            
            $('#group_id').on('change', function() {
                lastGroupId = $(this).val();
                checkImportStatus(lastGroupId);
            });

            $('a[href="#upload-tab-pane"]').on('shown.bs.tab', function() {
                lastGroupId = $('#group_id').val();
                checkImportStatus(lastGroupId);
            });
        });
    })(jQuery);
</script>
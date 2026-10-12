<script>
(function($) {
    "use strict";

    $(document).ready(function() {
        function fetchDeleteProgress() {
            const userIds = [];
            $('.user-delete-status[data-needs-polling=true]').each(function() {
                const userId = $(this).data('user-id');
                if (userId && !userIds.includes(userId)) {
                    userIds.push(userId);
                }
            });

            if (userIds.length === 0) return;

            $.ajax({
                url: '{{ route("admin.user.delete.progress") }}',
                method: 'GET',
                data: { user_ids: userIds },
                success: function(response) {
                    $('.user-delete-status').each(function() {
                        const userId = $(this).data('user-id');
                        const data = response[userId];

                        if (!data || (data.status === 'error' && data.message === 'User not found')) {
                            // User is permanently deleted; stop polling and clear UI
                            $(this).data('needs-polling', false);
                            $(this).closest('tr').fadeOut(1000, function() {
                                $(this).remove(); // Remove row from DOM
                            });
                            return;
                        }

                        // Show loading state with last known progress (or 0%)
                        const progress = data.progress || 0;
                        const statusHtml = `
                            <div class="import-status-details">
                                <div class="import-status-label">
                                    <i class="ri-loader-4-line import-status-icon"></i>
                                    <span>{{ translate("Deleting User Information...") }}</span>
                                </div>
                                <span class="import-status-count">${progress}%</span>
                            </div>
                            <div class="import-status-progress-container">
                                <div class="import-status-progress-bar" style="width: ${progress}%;"></div>
                                <div class="import-status-progress-shine"></div>
                            </div>
                        `;

                        $(this).find('.delete-status').html(`
                            <div class="import-status-wrapper">
                                ${statusHtml}
                            </div>
                        `);
                        $(this).data('needs-polling', true);
                        $(this).data('retry-count', 0); // Reset retry count on success
                    });

                    if ($('.user-delete-status[data-needs-polling=true]').length > 0) {
                        setTimeout(fetchDeleteProgress, 5000);
                    }
                },
                error: function(xhr) {
                    $('.user-delete-status[data-needs-polling=true]').each(function() {
                        const retryCount = ($(this).data('retry-count') || 0) + 1;
                        $(this).data('retry-count', retryCount);
                        // Continue polling indefinitely with a longer delay after retries
                        setTimeout(fetchDeleteProgress, retryCount < 3 ? 5000 : 10000);
                    });
                }
            });
        }

        fetchDeleteProgress();
    });
})(jQuery);
</script>
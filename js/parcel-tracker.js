jQuery(document).ready(function($) {
    $('#parcel-tracker-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var $result = $('#parcel-tracker-result');
        var originalBtnText = $submitBtn.text();

        var branch_code = $form.find('input[name="branch_code"]').val() || '';
        var lr_number = $form.find('input[name="lr_number"]').val();

        function showError() {
            $result.html('<p class="alt-tracker-error">Something went wrong. Please try again.</p>');
        }

        function resetButton() {
            $submitBtn.prop('disabled', false).text(originalBtnText);
        }

        $submitBtn.prop('disabled', true).text('Tracking...');
        $result.html('<div class="alt-tracker-loading"><span class="alt-tracker-spinner"></span>Fetching tracking details&hellip;</div>');

        // Fetch a fresh nonce right before submitting — admin-ajax.php is never served from
        // page cache, so this avoids stale nonces baked into a cached copy of this page.
        $.post(parcelTracker.ajax_url, { action: 'alt_get_nonce' })
            .done(function(nonce) {
                $.ajax({
                    url: parcelTracker.ajax_url,
                    type: 'post',
                    data: {
                        action: 'track_parcel',
                        nonce: nonce,
                        branch_code: branch_code,
                        lr_number: lr_number
                    }
                })
                    .done(function(response) {
                        $result.html(response);
                    })
                    .fail(showError)
                    .always(resetButton);
            })
            .fail(function() {
                showError();
                resetButton();
            });
    });
});

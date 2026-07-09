jQuery(document).ready(function($) {
    $('#parcel-tracker-form').on('submit', function(e) {
        e.preventDefault();

        var branch_code = $('input[name="branch_code"]').val() || '';
        var lr_number = $('input[name="lr_number"]').val();

        // Fetch a fresh nonce right before submitting — admin-ajax.php is never served from
        // page cache, so this avoids stale nonces baked into a cached copy of this page.
        $.post(parcelTracker.ajax_url, { action: 'alt_get_nonce' }, function(nonce) {
            $.ajax({
                url: parcelTracker.ajax_url,
                type: 'post',
                data: {
                    action: 'track_parcel',
                    nonce: nonce,
                    branch_code: branch_code,
                    lr_number: lr_number
                },
                success: function(response) {
                    $('#parcel-tracker-result').html(response);
                }
            });
        });
    });
});
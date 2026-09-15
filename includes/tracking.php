<?php
if (!defined('ABSPATH')) exit;

function alt_build_api_url($domain, $branch_code, $lr_number)
{
    $path = str_replace(
        array('{code}', '{lrno}'),
        array(urlencode($branch_code), urlencode($lr_number)),
        ALT_API_PATH
    );

    return $domain . $path;
}

// Aerosol dates arrive as .NET JSON dates, e.g. "/Date(1730000000123)/" (ms since epoch).
// Taking the first 10 digits after "/Date(" truncates ms to seconds.
function alt_format_dotnet_date($value)
{
    if (empty($value)) {
        return 'N/A';
    }
    $timestamp = intval(substr($value, 6, 10));
    if (!$timestamp) {
        return 'N/A';
    }
    return date('d-m-Y', $timestamp);
}

// Prints an HTML comment with debugging details, visible only to logged-in admins.
function alt_debug_comment($title, $lines)
{
    if (!current_user_can('manage_options')) {
        return;
    }
    echo "\n<!-- Aerosol LR Tracking debug (visible to admins only): " . esc_html($title) . "\n";
    foreach ($lines as $label => $value) {
        echo esc_html($label) . ': ' . esc_html($value) . "\n";
    }
    echo "-->";
}

function alt_build_live_status_url($domain, $veh_no, $date)
{
    $path = str_replace(
        array('{vehno}', '{date}'),
        array(urlencode($veh_no), urlencode($date)),
        ALT_LIVE_STATUS_PATH
    );

    return $domain . $path;
}

// Builds the wp_remote_get() args array, adding the Aerosol Authorization header
// only when an API key is configured — clients not yet migrated to the new
// Aerosol security model keep working exactly as before with no header sent.
function alt_http_args($api_key, $extra_args = array())
{
    $args = array_merge(array('timeout' => 15), $extra_args);
    if (!empty($api_key)) {
        $args['headers'] = array('Authorization' => 'Bearer ' . $api_key);
    }
    return $args;
}

// Looks up the vehicle's current GPS position for "In Transit" LRs.
// Returns array('text' => location label, 'lat' => float, 'lng' => float) or null if unavailable.
function alt_fetch_live_location($domain, $veh_no, $api_key)
{
    if (empty($veh_no)) {
        alt_debug_comment('live location skipped', array('reason' => 'no vehicle number available on this LR'));
        return null;
    }

    // Aerosol's date format for this endpoint is d/m/Y (e.g. "15/09/2026") — ddmmyyyy
    // without separators now throws a server-side error since their security update.
    $today = date('d/m/Y');
    $url = alt_build_live_status_url($domain, $veh_no, $today);
    $response = wp_remote_get($url, alt_http_args($api_key));

    if (is_wp_error($response)) {
        alt_debug_comment('live location request failed', array(
            'url' => $url,
            'error' => $response->get_error_message(),
        ));
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (!isset($data['statuscode']) || $data['statuscode'] != 200 || empty($data['data'][0])) {
        alt_debug_comment('live location - no usable data', array(
            'url' => $url,
            'http_status' => wp_remote_retrieve_response_code($response),
            'raw_response' => $body,
        ));
        return null;
    }

    $point = $data['data'][0];
    if (!isset($point['Dm_Latitude']) || !isset($point['Dm_Longitude'])) {
        alt_debug_comment('live location - missing lat/lng', array('raw_response' => $body));
        return null;
    }

    return array(
        'text' => isset($point['LrNo']) ? $point['LrNo'] : '',
        'lat' => floatval($point['Dm_Latitude']),
        'lng' => floatval($point['Dm_Longitude']),
    );
}

function alt_get_field_value($row, $mapping)
{
    $value1 = isset($row[$mapping['field1']]) ? $row[$mapping['field1']] : '';
    if ($mapping['is_date']) {
        $value1 = alt_format_dotnet_date($value1);
    }

    if (!empty($mapping['field2'])) {
        $value2 = isset($row[$mapping['field2']]) ? $row[$mapping['field2']] : '';
        if ($mapping['is_date']) {
            $value2 = alt_format_dotnet_date($value2);
        }
        if ($value1 === '' && $value2 === '') {
            return 'N/A';
        }
        $separator = isset($mapping['separator']) ? $mapping['separator'] : '';
        return $value1 . $separator . $value2;
    }

    return $value1 === '' ? 'N/A' : $value1;
}

// Issues a fresh nonce on demand (fetched via AJAX right before a lookup) instead of one
// baked into the page HTML by wp_localize_script, since page-caching plugins (e.g. LiteSpeed
// Cache) serve stale cached HTML containing an expired nonce. admin-ajax.php itself is never
// cached, so a nonce fetched here is always current.
function alt_get_nonce()
{
    echo wp_create_nonce('alt_track_parcel');
    wp_die();
}
add_action('wp_ajax_alt_get_nonce', 'alt_get_nonce');
add_action('wp_ajax_nopriv_alt_get_nonce', 'alt_get_nonce');

function alt_track_parcel()
{
    if (!isset($_POST['lr_number'])) {
        wp_die();
    }

    if (!check_ajax_referer('alt_track_parcel', 'nonce', false)) {
        echo 'Security check failed. Please refresh the page and try again.';
        wp_die();
    }

    $branch_code = isset($_POST['branch_code']) ? sanitize_text_field(wp_unslash($_POST['branch_code'])) : '';
    $lr_number = sanitize_text_field(wp_unslash($_POST['lr_number']));

    $settings = alt_get_settings();
    if (empty($settings['api_domain'])) {
        echo 'Tracking is not configured yet. Please set the Aerosol API Domain in Settings &rarr; Aerosol LR Tracking.';
        wp_die();
    }

    $api_url = alt_build_api_url($settings['api_domain'], $branch_code, $lr_number);
    // Cache successful lookups briefly so rapid repeat searches (or scripted abuse) don't
    // hammer the client's Aerosol API — short TTL since shipment status changes over time.
    $cache_key = 'alt_lr_' . md5($settings['api_domain'] . '|' . $branch_code . '|' . $lr_number);
    $body = get_transient($cache_key);
    $http_status = 'cached';

    if ($body === false) {
        // WordPress' default HTTP timeout (5s) is too short for some Aerosol APIs to respond within.
        $response = wp_remote_get($api_url, alt_http_args($settings['api_key'], array('timeout' => 20)));

        if (is_wp_error($response)) {
            echo 'Error: ' . esc_html($response->get_error_message());
            wp_die();
        }

        $body = wp_remote_retrieve_body($response);
        $http_status = wp_remote_retrieve_response_code($response);
    }

    $data = json_decode($body, true);

    if (!isset($data['statuscode']) || $data['statuscode'] != 200 || !isset($data['data'][0][0])) {
        echo 'No tracking information found.';
        alt_debug_comment('LR lookup - no usable data', array(
            'url' => $api_url,
            'http_status' => $http_status,
            'raw_response' => $body,
        ));
        wp_die();
    }

    set_transient($cache_key, $body, 60);

    $details = $data['data'][0][0];
    $challan_entries = isset($details['ChallanEntries'][0]) ? $details['ChallanEntries'][0] : array();
    unset($details['ChallanEntries']);
    // $details and ChallanEntries share some key names (ChallanNo, BranchName, Station) with
    // different values — $details must win so mappings resolve to the top-level LR record.
    $row = array_merge($challan_entries, $details);
    $status = isset($row['Status']) ? $row['Status'] : '';

    $columns = array();
    foreach ($settings['field_mappings'] as $mapping) {
        if ($mapping['only_if_status'] !== '' && strcasecmp($mapping['only_if_status'], $status) !== 0) {
            continue;
        }
        if (!empty($mapping['hide_if_status']) && strcasecmp($mapping['hide_if_status'], $status) === 0) {
            continue;
        }
        $columns[] = $mapping;
    }

    if (empty($columns)) {
        echo 'No tracking information found.';
        wp_die();
    }

    $live_location = null;
    if (strcasecmp($status, 'In Transit') === 0) {
        $veh_no = !empty($row['VehNo']) ? $row['VehNo'] : (!empty($row['TruckNo']) ? $row['TruckNo'] : '');
        $live_location = alt_fetch_live_location($settings['api_domain'], $veh_no, $settings['api_key']);
    }

    $output = '<div class="alt-tracking-table-wrap">';
    $output .= '<table class="alt-tracking-table" border="1">';
    $output .= '<thead><tr>';
    foreach ($columns as $mapping) {
        $output .= '<th>' . esc_html($mapping['label']) . '</th>';
    }
    $output .= '</tr></thead>';
    $output .= '<tbody><tr>';
    foreach ($columns as $mapping) {
        $data_label = ' data-label="' . esc_attr($mapping['label']) . '"';
        if (!empty($mapping['live_location']) && $live_location) {
            $maps_url = 'https://www.google.com/maps?q=' . $live_location['lat'] . ',' . $live_location['lng'];
            $output .= '<td' . $data_label . '><a class="alt-live-location-link" href="' . esc_url($maps_url) . '" target="_blank" rel="noopener noreferrer">&#128205; ' . esc_html($live_location['text']) . '</a></td>';
        } else {
            $output .= '<td' . $data_label . '>' . esc_html(alt_get_field_value($row, $mapping)) . '</td>';
        }
    }
    $output .= '</tr></tbody>';
    $output .= '</table></div>';

    echo $output;
    wp_die();
}
add_action('wp_ajax_track_parcel', 'alt_track_parcel');
add_action('wp_ajax_nopriv_track_parcel', 'alt_track_parcel');

<?php
/*
Plugin Name: Aerosol LR Tracking
Plugin URI: https://hvinfotech.com/
Description: A custom plugin to track parcels/LRs using the Aerosol ERP API.
Version: 1.0.0
Author: HV InfoTech
Author URI: https://hvinfotech.com/

*/

if (!defined('ABSPATH')) exit;

define('ALT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ALT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Fixed Aerosol API path/query — only the domain differs between client sites.
define('ALT_API_PATH', '/api/LRInquiry.ashx?apiname=lrinquiry&code={code}&lrno={lrno}');
define('ALT_LIVE_STATUS_PATH', '/api/VehicleLiveStatusApi.ashx?apiname=VehicleLiveStatus&Veh_No={vehno}&FromDate={date}&ToDate={date}');

require_once ALT_PLUGIN_DIR . 'includes/settings-page.php';
require_once ALT_PLUGIN_DIR . 'includes/tracking.php';

// Shortcode to display the tracking form
function parcel_tracker_form()
{
    $settings = alt_get_settings();
    $show_branch_code = !empty($settings['show_branch_code']);

    ob_start();
?>
    <form id="parcel-tracker-form" method="post">
        <?php if ($show_branch_code) : ?>
            <input type="text" name="branch_code" placeholder="Enter Branch code">
        <?php endif; ?>
        <input type="text" name="lr_number" placeholder="Enter LR Number" required>
        <button type="submit">Track</button>
    </form>
    <div id="parcel-tracker-result"></div>

<?php
    return ob_get_clean();
}
add_shortcode('parcel_tracker', 'parcel_tracker_form');

// Enqueue JavaScript
function parcel_tracker_enqueue_scripts()
{
    // Version by file mtime so caching plugins/CDNs/browsers fetch a fresh copy whenever
    // js/css files change on the server, instead of serving a stale cached version forever.
    $js_path = ALT_PLUGIN_DIR . 'js/parcel-tracker.js';
    $css_path = ALT_PLUGIN_DIR . 'css/parcel-tracker.css';

    wp_enqueue_script('parcel-tracker-js', ALT_PLUGIN_URL . 'js/parcel-tracker.js', array('jquery'), file_exists($js_path) ? filemtime($js_path) : false, true);
    wp_localize_script('parcel-tracker-js', 'parcelTracker', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));

    // Enqueue the CSS file
    wp_enqueue_style('parcel-tracker-css', ALT_PLUGIN_URL . 'css/parcel-tracker.css', array(), file_exists($css_path) ? filemtime($css_path) : false);
}
add_action('wp_enqueue_scripts', 'parcel_tracker_enqueue_scripts');

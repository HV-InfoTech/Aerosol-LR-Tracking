<?php
if (!defined('ABSPATH')) exit;

function alt_default_settings()
{
    return array(
        'api_domain' => '',
        'api_key' => '',
        'show_branch_code' => false,
        'field_mappings' => array(
            array('label' => 'Docket No', 'field1' => 'FromBranchCode', 'field2' => 'ChallanNo', 'separator' => '-', 'is_date' => false, 'only_if_status' => ''),
            array('label' => 'Date', 'field1' => 'Lrdate', 'field2' => '', 'separator' => '', 'is_date' => true, 'only_if_status' => ''),
            array('label' => 'From - To', 'field1' => 'BranchName', 'field2' => 'Station', 'separator' => '-', 'is_date' => false, 'only_if_status' => ''),
            array('label' => 'Package', 'field1' => 'Package', 'field2' => '', 'separator' => '', 'is_date' => false, 'only_if_status' => ''),
            array('label' => 'Invoice No', 'field1' => 'PartyInvoiceNo', 'field2' => '', 'separator' => '', 'is_date' => false, 'only_if_status' => ''),
            array('label' => 'Vehicle No', 'field1' => 'TruckNo', 'field2' => '', 'separator' => '', 'is_date' => false, 'only_if_status' => ''),
            array('label' => 'Current Location', 'field1' => 'CurrentLocation', 'field2' => '', 'separator' => '', 'is_date' => false, 'only_if_status' => '', 'hide_if_status' => 'Delivered', 'live_location' => true),
            array('label' => 'Vehicle Tracking Entry Status', 'field1' => 'Status', 'field2' => '', 'separator' => '', 'is_date' => false, 'only_if_status' => ''),
            array('label' => 'Receipt Date', 'field1' => 'ReceiptDate', 'field2' => '', 'separator' => '', 'is_date' => true, 'only_if_status' => 'Delivered'),
        ),
    );
}

function alt_get_settings()
{
    $saved = get_option('alt_settings');
    if (!is_array($saved)) {
        return alt_default_settings();
    }
    return wp_parse_args($saved, alt_default_settings());
}

function alt_known_api_fields()
{
    return array(
        'FromBranchCode',
        'ChallanNo',
        'BranchName',
        'Station',
        'Package',
        'PartyInvoiceNo',
        'TruckNo',
        'VehNo',
        'Status',
        'Lrdate',
        'CurrentLocation',
        'ReceiptDate',
    );
}

add_action('admin_menu', 'alt_add_settings_page');
function alt_add_settings_page()
{
    add_options_page(
        'Aerosol LR Tracking Settings',
        'Aerosol LR Tracking',
        'manage_options',
        'aerosol-lr-tracking',
        'alt_render_settings_page'
    );
}

add_action('admin_enqueue_scripts', 'alt_admin_enqueue_scripts');
function alt_admin_enqueue_scripts($hook)
{
    if ($hook !== 'settings_page_aerosol-lr-tracking') {
        return;
    }
    $js_path = ALT_PLUGIN_DIR . 'js/admin-settings.js';
    $css_path = ALT_PLUGIN_DIR . 'css/admin-settings.css';

    wp_enqueue_script('alt-admin-settings', ALT_PLUGIN_URL . 'js/admin-settings.js', array('jquery'), file_exists($js_path) ? filemtime($js_path) : false, true);
    wp_enqueue_style('alt-admin-settings', ALT_PLUGIN_URL . 'css/admin-settings.css', array(), file_exists($css_path) ? filemtime($css_path) : false);
}

add_action('admin_init', 'alt_maybe_save_settings');
function alt_maybe_save_settings()
{
    if (!isset($_POST['alt_settings_nonce'])) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!wp_verify_nonce($_POST['alt_settings_nonce'], 'alt_save_settings')) {
        return;
    }

    $api_domain = isset($_POST['api_domain']) ? rtrim(sanitize_text_field(wp_unslash($_POST['api_domain'])), '/') : '';
    $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
    $show_branch_code = !empty($_POST['show_branch_code']);

    $field_mappings = array();
    if (isset($_POST['mapping']) && is_array($_POST['mapping'])) {
        foreach ($_POST['mapping'] as $row) {
            $label = isset($row['label']) ? sanitize_text_field(wp_unslash($row['label'])) : '';
            $field1 = isset($row['field1']) ? sanitize_text_field(wp_unslash($row['field1'])) : '';
            if ($label === '' || $field1 === '') {
                continue; // skip incomplete rows
            }
            $field_mappings[] = array(
                'label' => $label,
                'field1' => $field1,
                'field2' => isset($row['field2']) ? sanitize_text_field(wp_unslash($row['field2'])) : '',
                'separator' => isset($row['separator']) ? sanitize_text_field(wp_unslash($row['separator'])) : '-',
                'is_date' => !empty($row['is_date']),
                'only_if_status' => isset($row['only_if_status']) ? sanitize_text_field(wp_unslash($row['only_if_status'])) : '',
                'hide_if_status' => isset($row['hide_if_status']) ? sanitize_text_field(wp_unslash($row['hide_if_status'])) : '',
                'live_location' => !empty($row['live_location']),
            );
        }
    }

    update_option('alt_settings', array(
        'api_domain' => $api_domain,
        'api_key' => $api_key,
        'show_branch_code' => $show_branch_code,
        'field_mappings' => $field_mappings,
    ));

    add_action('admin_notices', 'alt_settings_saved_notice');
}

function alt_settings_saved_notice()
{
    echo '<div class="notice notice-success is-dismissible"><p>Aerosol LR Tracking settings saved.</p></div>';
}

function alt_render_mapping_row($i, $row)
{
    ob_start();
    ?>
    <tr class="alt-mapping-row">
        <td><input type="text" name="mapping[<?php echo esc_attr($i); ?>][label]" value="<?php echo esc_attr($row['label']); ?>" placeholder="e.g. Docket No"></td>
        <td><input type="text" list="alt_known_fields" name="mapping[<?php echo esc_attr($i); ?>][field1]" value="<?php echo esc_attr($row['field1']); ?>" placeholder="e.g. FromBranchCode"></td>
        <td><input type="text" list="alt_known_fields" name="mapping[<?php echo esc_attr($i); ?>][field2]" value="<?php echo esc_attr($row['field2']); ?>" placeholder="optional"></td>
        <td><input type="text" name="mapping[<?php echo esc_attr($i); ?>][separator]" value="<?php echo esc_attr($row['separator']); ?>" size="3"></td>
        <td class="alt-center"><input type="checkbox" name="mapping[<?php echo esc_attr($i); ?>][is_date]" value="1" <?php checked(!empty($row['is_date'])); ?>></td>
        <td><input type="text" name="mapping[<?php echo esc_attr($i); ?>][only_if_status]" value="<?php echo esc_attr($row['only_if_status']); ?>" placeholder="e.g. Delivered"></td>
        <td><input type="text" name="mapping[<?php echo esc_attr($i); ?>][hide_if_status]" value="<?php echo esc_attr(isset($row['hide_if_status']) ? $row['hide_if_status'] : ''); ?>" placeholder="e.g. Delivered"></td>
        <td class="alt-center"><input type="checkbox" name="mapping[<?php echo esc_attr($i); ?>][live_location]" value="1" <?php checked(!empty($row['live_location'])); ?>></td>
        <td class="alt-center"><button type="button" class="button-link alt-remove-row" aria-label="Remove field">&times;</button></td>
    </tr>
    <?php
    return ob_get_clean();
}

function alt_render_settings_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $settings = alt_get_settings();
    $known_fields = alt_known_api_fields();
    ?>
    <div class="wrap">
        <h1>Aerosol LR Tracking Settings</h1>

        <div class="notice notice-info" style="padding: 12px;">
            <p style="margin: 0 0 8px;">
                <strong>How to display the tracking form:</strong> add the shortcode
                <code>[parcel_tracker]</code> to any page or post &mdash; e.g. create a "Track Your Shipment"
                page and paste that shortcode into it. The form and result table will appear wherever the
                shortcode is placed.
            </p>
            <p style="margin: 0;">
                <strong>How to display the branch list:</strong> add the shortcode
                <code>[branch_list]</code> to any page or post &mdash; e.g. create a "Our Branches" page.
                Uses the same Aerosol API Domain and API Key configured below.
            </p>
        </div>

        <form method="post">
            <?php wp_nonce_field('alt_save_settings', 'alt_settings_nonce'); ?>

            <h2>API Connection</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="alt_api_domain">Aerosol API Domain</label></th>
                    <td>
                        <input type="text" id="alt_api_domain" name="api_domain" class="large-text code"
                               placeholder="https://client-domain.in"
                               value="<?php echo esc_attr($settings['api_domain']); ?>">
                        <p class="description">
                            Enter just this client's Aerosol domain (no trailing slash or path) &mdash; e.g. <code>https://client-domain.in</code>.<br>
                            The plugin will call: <code><?php echo esc_html($settings['api_domain'] ?: '{domain}'); ?><?php echo esc_html(ALT_API_PATH); ?></code>
                        </p>
                        <?php if (empty($settings['api_domain'])) : ?>
                            <p class="description" style="color:#b32d2e;">No API domain configured yet &mdash; tracking lookups will not work until this is set.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="alt_api_key">API Key</label></th>
                    <td>
                        <input type="text" id="alt_api_key" name="api_key" class="large-text code"
                               placeholder="Leave blank if not issued one"
                               value="<?php echo esc_attr($settings['api_key']); ?>">
                        <p class="description">
                            Some clients' Aerosol APIs now require an <code>Authorization: Bearer &lt;key&gt;</code> header.
                            Enter the key Aerosol provided for this client here. Leave this blank for clients whose API
                            doesn't require it yet &mdash; the header will simply be omitted, exactly as before.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="alt_show_branch_code">Branch Code Field</label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="alt_show_branch_code" name="show_branch_code" value="1" <?php checked(!empty($settings['show_branch_code'])); ?>>
                            Show the "Branch Code" field on the tracking form
                        </label>
                        <p class="description">
                            Turn this off for clients with a single branch &mdash; the tracking form will only ask for the LR Number,
                            and the branch code sent to the Aerosol API will be left blank.
                        </p>
                    </td>
                </tr>
            </table>

            <h2>Tracking Result Table Fields</h2>
            <p class="description">
                Choose which columns appear on the tracking result table and which Aerosol API field each column pulls its value from.
                You can combine two fields with a separator (e.g. Branch Code + Challan No), format a value as a date, or only show a
                column when Status equals a specific value (e.g. show "Receipt Date" only when Status is "Delivered"), or hide a
                column for a specific status (e.g. hide "Current Location" once Status is "Delivered").<br>
                Check <strong>Live Location Link</strong> on a column to have it show a live GPS location (as a clickable Google Maps link)
                fetched from the Aerosol Vehicle Live Status API whenever Status is "In Transit", falling back to that column's normal
                value otherwise.
            </p>

            <datalist id="alt_known_fields">
                <?php foreach ($known_fields as $f) : ?>
                    <option value="<?php echo esc_attr($f); ?>"></option>
                <?php endforeach; ?>
            </datalist>

            <table class="widefat striped" id="alt-mapping-table">
                <thead>
                    <tr>
                        <th>Column Label</th>
                        <th>API Field</th>
                        <th>+ API Field (optional)</th>
                        <th>Separator</th>
                        <th>Format as Date</th>
                        <th>Only show if Status =</th>
                        <th>Hide if Status =</th>
                        <th>Live Location Link</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="alt-mapping-rows">
                    <?php foreach ($settings['field_mappings'] as $i => $row) : ?>
                        <?php echo alt_render_mapping_row($i, $row); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><button type="button" class="button" id="alt-add-mapping-row">+ Add Field</button></p>

            <template id="alt-mapping-row-template"><?php echo alt_render_mapping_row('__INDEX__', array('label' => '', 'field1' => '', 'field2' => '', 'separator' => '-', 'is_date' => false, 'only_if_status' => '', 'hide_if_status' => '', 'live_location' => false)); ?></template>

            <?php submit_button('Save Settings'); ?>
        </form>
    </div>
    <?php
}

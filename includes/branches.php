<?php
if (!defined('ABSPATH')) exit;

function alt_build_branch_api_url($domain)
{
    return $domain . ALT_BRANCH_API_PATH;
}

// Aerosol's BranchDetail response nests branches either one or two arrays deep
// (mirrors the LR Inquiry response shape) — flatten both cases into a flat list.
function alt_parse_branch_response($decoded)
{
    $branches = array();

    if (empty($decoded['BranchDetail']) || !is_array($decoded['BranchDetail'])) {
        return $branches;
    }

    foreach ($decoded['BranchDetail'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (isset($item['BranchName'])) {
            $branches[] = $item;
            continue;
        }
        foreach ($item as $branch) {
            if (is_array($branch) && isset($branch['BranchName'])) {
                $branches[] = $branch;
            }
        }
    }

    return $branches;
}

function alt_branch_list_shortcode()
{
    $settings = alt_get_settings();

    if (empty($settings['api_domain'])) {
        return '<p>Branch list is not configured yet. Please set the Aerosol API Domain in Settings &rarr; Aerosol LR Tracking.</p>';
    }

    // Branch details change far less often than shipment status, so a longer cache
    // is fine here and keeps this page fast without hammering the client's API.
    $cache_key = 'alt_branches_' . md5($settings['api_domain']);
    $branches = get_transient($cache_key);

    if ($branches === false) {
        $url = alt_build_branch_api_url($settings['api_domain']);
        $response = wp_remote_get($url, alt_http_args($settings['api_key'], array('timeout' => 20)));

        if (is_wp_error($response)) {
            alt_debug_comment('branch list request failed', array(
                'url' => $url,
                'error' => $response->get_error_message(),
            ));
            return '<p class="alt-tracker-error">Unable to load the branch list right now. Please try again later.</p>';
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        $branches = alt_parse_branch_response($decoded);

        if (empty($branches)) {
            alt_debug_comment('branch list - no usable data', array(
                'url' => $url,
                'http_status' => wp_remote_retrieve_response_code($response),
                'raw_response' => $body,
            ));
        }

        set_transient($cache_key, $branches, 30 * MINUTE_IN_SECONDS);
    }

    if (empty($branches)) {
        return '<p>No branch data available.</p>';
    }

    ob_start();
?>
    <div class="alt-branch-list-wrap">
        <div class="alt-branch-search-wrap">
            <input type="text" class="alt-branch-search" placeholder="Search branches&hellip;">
        </div>
        <div class="alt-tracking-table-wrap">
            <table class="alt-tracking-table alt-branch-table">
                <thead>
                    <tr>
                        <th>Branch Name</th>
                        <th>Mobile No</th>
                        <th>Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branches as $branch) :
                        $name = isset($branch['BranchName']) ? $branch['BranchName'] : '';
                        $mobile = isset($branch['MobileNo']) ? $branch['MobileNo'] : '';
                        $address = isset($branch['Address']) ? $branch['Address'] : '';
                        $tel = preg_replace('/[^+0-9]/', '', $mobile);
                    ?>
                        <tr>
                            <td data-label="Branch Name"><?php echo esc_html($name); ?></td>
                            <td data-label="Mobile No">
                                <?php if ($mobile !== '') : ?>
                                    <a href="tel:<?php echo esc_attr($tel); ?>"><?php echo esc_html($mobile); ?></a>
                                <?php endif; ?>
                            </td>
                            <td data-label="Address"><?php echo nl2br(esc_html($address)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php
    return ob_get_clean();
}
add_shortcode('branch_list', 'alt_branch_list_shortcode');

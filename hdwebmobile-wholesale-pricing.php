<?php

/**
 * Plugin Name: HDWebmobile Wholesale Pricing
 * Plugin URI: https://hdwebmobile.com/plugins/hdwebmobile-wholesale-pricing/
 * Description: Role-based wholesale prices with a gated application form. The wholesale role is only ever granted by an admin approving an application -- never by any request.
 * Version: 1.0.0
 * Author: htrxuan - Han Tran
 * Author URI: https://hdwebmobile.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hdwebmobile-wholesale-pricing
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Requires at least: 6.9
 */

namespace htrxuan\hdws;

if (!defined('ABSPATH')) {
    exit;
}

define('HDWS_VERSION', '1.0.0');
define('HDWS_DB_VERSION', '1');
define('HDWS_ROLE', 'hdws_wholesale');
define('HDWS_PLUGIN_FILE', __FILE__);
define('HDWS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HDWS_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once HDWS_PLUGIN_DIR . 'includes/class-hdws-activator.php';

register_activation_hook(__FILE__, array(HDWS_Activator::class, 'activate'));

add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', HDWS_PLUGIN_FILE, true);
    }
});

add_action('plugins_loaded', function () {
    require_once HDWS_PLUGIN_DIR . 'includes/class-hdws-core.php';
    HDWS_Core::get_instance();
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $donate_link = '<a href="https://paypal.me/htrxuan/20" target="_blank" rel="noopener noreferrer">' . esc_html__('Donate', 'hdwebmobile-wholesale-pricing') . '</a>';
    array_unshift($links, $donate_link);
    return $links;
});

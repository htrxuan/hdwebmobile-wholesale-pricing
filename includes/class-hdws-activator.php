<?php

namespace htrxuan\hdws;

if (!defined('ABSPATH')) {
    exit;
}

class HDWS_Activator
{

    public static function activate()
    {
        if (!self::is_woocommerce_active()) {
            deactivate_plugins(plugin_basename(HDWS_PLUGIN_FILE));
            set_transient('hdws_wc_missing_notice', true, 30);
            return;
        }

        self::register_role();
        self::maybe_upgrade_db();
    }

    public static function is_woocommerce_active()
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active('woocommerce/woocommerce.php') || class_exists('WooCommerce');
    }

    /**
     * The wholesale role is a plain customer-equivalent: it has 'read' and nothing else.
     * Even if it were somehow assigned to an account by mistake, it could not manage,
     * edit, publish, or upload anything -- it only changes which price that account sees.
     */
    public static function register_role()
    {
        remove_role(HDWS_ROLE);
        add_role(HDWS_ROLE, __('Wholesale Customer', 'hdwebmobile-wholesale-pricing'), array('read' => true));
    }

    public static function maybe_upgrade_db()
    {
        if (get_option('hdws_db_version') === HDWS_DB_VERSION) {
            return;
        }
        require_once HDWS_PLUGIN_DIR . 'includes/class-hdws-repository.php';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta(HDWS_Repository::get_schema_sql());
        update_option('hdws_db_version', HDWS_DB_VERSION);
    }
}

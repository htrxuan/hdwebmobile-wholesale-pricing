<?php

namespace htrxuan\hdws;

if (!defined('ABSPATH')) {
    exit;
}

final class HDWS_Core
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->includes();
        $this->init_hooks();
    }

    private function __clone()
    {
    }

    private function includes()
    {
        require_once HDWS_PLUGIN_DIR . 'includes/class-hdws-repository.php';
        require_once HDWS_PLUGIN_DIR . 'includes/class-hdws-pricing.php';
        require_once HDWS_PLUGIN_DIR . 'includes/class-hdws-account.php';
        require_once HDWS_PLUGIN_DIR . 'includes/class-hdws-admin.php';
    }

    private function init_hooks()
    {
        add_action('admin_notices', array($this, 'render_missing_woocommerce_notice'));

        if (!class_exists('WooCommerce')) {
            return;
        }

        HDWS_Pricing::get_instance();
        HDWS_Account::get_instance();
        HDWS_Admin::get_instance();
    }

    public function render_missing_woocommerce_notice()
    {
        $screen = get_current_screen();
        if (!$screen || 'plugins' !== $screen->id) {
            return;
        }

        if (!get_transient('hdws_wc_missing_notice')) {
            return;
        }
        delete_transient('hdws_wc_missing_notice');
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php esc_html_e('HDWebmobile Wholesale Pricing requires WooCommerce to be installed and active. The plugin has been deactivated.', 'hdwebmobile-wholesale-pricing'); ?>
            </p>
        </div>
        <?php
    }
}

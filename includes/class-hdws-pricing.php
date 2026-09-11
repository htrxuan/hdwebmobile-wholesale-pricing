<?php

namespace htrxuan\hdws;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The wholesale price itself: a per-product amount on the Product data panel, plus an
 * optional global "percent off" fallback. Both are applied ONLY when the currently
 * logged-in user is verified server-side (HDWS_Repository::user_is_wholesale()) to hold the
 * wholesale role. The role is the single gate: there is no cookie, query parameter, form
 * field, or header that switches wholesale pricing on.
 */
final class HDWS_Pricing
{

    const META_PRICE = '_hdws_wholesale_price';
    const OPTION_KEY = 'hdws_settings';

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
        add_action('woocommerce_product_options_pricing', array($this, 'render_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_field'));

        foreach (array('woocommerce_product_get_price', 'woocommerce_product_get_regular_price', 'woocommerce_product_get_sale_price') as $hook) {
            add_filter($hook, array($this, 'filter_price'), 20, 2);
        }
        foreach (array('woocommerce_product_variation_get_price', 'woocommerce_product_variation_get_regular_price', 'woocommerce_product_variation_get_sale_price') as $hook) {
            add_filter($hook, array($this, 'filter_price'), 20, 2);
        }
    }

    public static function get_settings()
    {
        $defaults = array('global_percent' => 0);
        $opts     = get_option(self::OPTION_KEY, array());
        return wp_parse_args(is_array($opts) ? $opts : array(), $defaults);
    }

    /* ---------- product data panel ---------- */

    public function render_field()
    {
        woocommerce_wp_text_input(array(
            'id'          => self::META_PRICE,
            'label'       => __('Wholesale price', 'hdwebmobile-wholesale-pricing') . ' (' . get_woocommerce_currency_symbol() . ')',
            'data_type'   => 'price',
            'desc_tip'    => true,
            'description' => __('Shown only to logged-in customers who hold the Wholesale Customer role. Leave blank to use the global wholesale discount instead.', 'hdwebmobile-wholesale-pricing'),
        ));
    }

    public function save_field($post_id)
    {
        if (!isset($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce_meta_nonce'])), 'woocommerce_save_data')) {
            return;
        }
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a price string, normalised immediately by wc_format_decimal() and range-checked below; never used as-is.
        $raw = isset($_POST[self::META_PRICE]) ? wp_unslash($_POST[self::META_PRICE]) : '';
        $val = ('' === $raw) ? '' : wc_format_decimal($raw);
        if ('' !== $val && (float) $val < 0) {
            $val = '';
        }
        update_post_meta($post_id, self::META_PRICE, $val);
    }

    /* ---------- price filters ---------- */

    /**
     * @param string|float          $price
     * @param \WC_Product            $product
     * @return string|float
     */
    public function filter_price($price, $product)
    {
        if (is_admin() && !wp_doing_ajax()) {
            return $price;
        }
        if (!HDWS_Repository::user_is_wholesale()) {
            return $price;
        }
        if (!$product instanceof \WC_Product) {
            return $price;
        }

        $fixed = get_post_meta($product->get_id(), self::META_PRICE, true);
        if ('' !== $fixed && is_numeric($fixed) && (float) $fixed >= 0) {
            return wc_format_decimal($fixed);
        }

        $percent = (float) self::get_settings()['global_percent'];
        if ($percent > 0 && $percent < 100 && '' !== $price && is_numeric($price)) {
            return wc_format_decimal((float) $price * (1 - $percent / 100));
        }

        return $price;
    }
}

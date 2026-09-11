<?php

namespace htrxuan\hdws;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The customer-facing "apply for a wholesale account" form (My Account, and the
 * [hdws_apply] shortcode). Submitting it only ever creates a `pending` application row for
 * the CURRENT logged-in user -- HDWS_Repository::create_application(get_current_user_id(),
 * ...). It never sets a role, never accepts a user id, and is nonce-protected.
 */
final class HDWS_Account
{

    const NONCE_ACTION = 'hdws_apply';

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
        add_shortcode('hdws_apply', array($this, 'shortcode'));
        add_action('woocommerce_account_dashboard', array($this, 'dashboard_block'), 20);
        add_action('template_redirect', array($this, 'maybe_handle_submit'));
    }

    public function maybe_handle_submit()
    {
        if (empty($_POST['hdws_apply_submit'])) {
            return;
        }
        if (!is_user_logged_in()) {
            wc_add_notice(__('Please log in to apply for a wholesale account.', 'hdwebmobile-wholesale-pricing'), 'error');
            return;
        }
        if (!isset($_POST['hdws_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hdws_nonce'])), self::NONCE_ACTION)) {
            wc_add_notice(__('Security check failed. Please try again.', 'hdwebmobile-wholesale-pricing'), 'error');
            return;
        }

        // The applicant is ALWAYS the current authenticated user. No id comes from the form.
        $result = HDWS_Repository::create_application(get_current_user_id(), array(
            'company' => isset($_POST['hdws_company']) ? sanitize_text_field(wp_unslash($_POST['hdws_company'])) : '',
            'tax_id'  => isset($_POST['hdws_tax_id']) ? sanitize_text_field(wp_unslash($_POST['hdws_tax_id'])) : '',
            'note'    => isset($_POST['hdws_note']) ? sanitize_textarea_field(wp_unslash($_POST['hdws_note'])) : '',
        ));

        if (is_wp_error($result)) {
            wc_add_notice($result->get_error_message(), 'error');
        } else {
            wc_add_notice(__('Thanks -- your wholesale application has been submitted for review.', 'hdwebmobile-wholesale-pricing'), 'success');
        }
    }

    public function dashboard_block()
    {
        echo $this->get_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_html() escapes every dynamic value at output.
    }

    public function shortcode()
    {
        return $this->get_html();
    }

    private function get_html()
    {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to apply for a wholesale account.', 'hdwebmobile-wholesale-pricing') . '</p>';
        }

        ob_start();

        if (HDWS_Repository::user_is_wholesale()) {
            echo '<div class="hdws-status hdws-status--approved"><p>' . esc_html__('Your account has wholesale pricing enabled. Wholesale prices are shown automatically while you are logged in.', 'hdwebmobile-wholesale-pricing') . '</p></div>';
            return ob_get_clean();
        }

        $existing = HDWS_Repository::get_for_user(get_current_user_id());
        if ($existing && HDWS_Repository::STATUS_PENDING === $existing->status) {
            echo '<div class="hdws-status hdws-status--pending"><p>' . esc_html__('Your wholesale application is being reviewed. We will email you when a decision is made.', 'hdwebmobile-wholesale-pricing') . '</p></div>';
            return ob_get_clean();
        }
        ?>
        <div class="hdws-apply">
            <h3><?php esc_html_e('Apply for a wholesale account', 'hdwebmobile-wholesale-pricing'); ?></h3>
            <?php if ($existing && HDWS_Repository::STATUS_REJECTED === $existing->status) : ?>
                <p><?php esc_html_e('Your previous application was not approved. You can submit an updated application below.', 'hdwebmobile-wholesale-pricing'); ?></p>
            <?php endif; ?>
            <form method="post">
                <?php wp_nonce_field(self::NONCE_ACTION, 'hdws_nonce'); ?>
                <p>
                    <label><?php esc_html_e('Company name', 'hdwebmobile-wholesale-pricing'); ?> <span class="required">*</span><br />
                        <input type="text" name="hdws_company" required style="width:100%;max-width:24em;" />
                    </label>
                </p>
                <p>
                    <label><?php esc_html_e('Tax / VAT number', 'hdwebmobile-wholesale-pricing'); ?><br />
                        <input type="text" name="hdws_tax_id" style="width:100%;max-width:24em;" />
                    </label>
                </p>
                <p>
                    <label><?php esc_html_e('Anything else we should know?', 'hdwebmobile-wholesale-pricing'); ?><br />
                        <textarea name="hdws_note" rows="3" style="width:100%;max-width:32em;"></textarea>
                    </label>
                </p>
                <p><button type="submit" name="hdws_apply_submit" value="1" class="button"><?php esc_html_e('Submit application', 'hdwebmobile-wholesale-pricing'); ?></button></p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}

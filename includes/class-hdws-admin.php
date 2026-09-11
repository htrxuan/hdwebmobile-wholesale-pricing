<?php

namespace htrxuan\hdws;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The hub tab: the pending-applications queue with Approve / Reject, and the global
 * wholesale-discount setting.
 *
 * Approve / Reject is the ONLY path that grants or removes the wholesale role. Its handler
 * (`admin_post_hdws_decide`) checks `current_user_can('manage_woocommerce')` AND a nonce
 * before doing anything, and it reads exactly two values from the request: an application
 * id and which of the two fixed actions ("approve" / "reject") to take.
 */
class HDWS_Admin
{
    const NONCE_ACTION = 'hdws_decide';

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
        require_once HDWS_PLUGIN_DIR . 'includes/class-hdws-hub.php';
        add_filter('hdwebmobile_hub_tabs', array($this, 'register_hub_tabs'));
        add_action('admin_post_hdws_decide', array($this, 'handle_decide'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings()
    {
        register_setting('hdws_group', HDWS_Pricing::OPTION_KEY, array(
            'type'              => 'array',
            'sanitize_callback' => array($this, 'sanitize_settings'),
            'default'           => array('global_percent' => 0),
        ));
    }

    public function sanitize_settings($input)
    {
        $percent = isset($input['global_percent']) ? (float) $input['global_percent'] : 0;
        $percent = max(0, min(90, $percent));
        return array('global_percent' => $percent);
    }

    public function register_hub_tabs($tabs)
    {
        $tabs['wholesale-pricing'] = array(
            'label'  => __('Wholesale Pricing', 'hdwebmobile-wholesale-pricing'),
            'order'  => 48,
            'render' => array($this, 'render_page'),
        );
        return $tabs;
    }

    public function handle_decide()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to do this.', 'hdwebmobile-wholesale-pricing'));
        }
        if (!isset($_GET['hdws_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['hdws_nonce'])), self::NONCE_ACTION)) {
            wp_die(esc_html__('Security check failed. Please try again.', 'hdwebmobile-wholesale-pricing'));
        }

        $app_id   = isset($_GET['app']) ? absint($_GET['app']) : 0;
        $decision = isset($_GET['decision']) ? sanitize_key(wp_unslash($_GET['decision'])) : '';

        if ($app_id) {
            if ('approve' === $decision) {
                HDWS_Repository::approve($app_id, get_current_user_id());
            } elseif ('reject' === $decision) {
                HDWS_Repository::reject($app_id, get_current_user_id());
            }
        }

        wp_safe_redirect(add_query_arg('hdws_done', '1', admin_url('admin.php?page=hdwebmobile&tab=wholesale-pricing')));
        exit;
    }

    public function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to do this.', 'hdwebmobile-wholesale-pricing'));
        }

        $settings = HDWS_Pricing::get_settings();
        ?>
        <p><?php esc_html_e('Give trade customers their own pricing. Set a per-product wholesale price on each product\'s "Product data" panel, or a store-wide percentage below. Wholesale prices are shown only to logged-in customers who hold the Wholesale Customer role.', 'hdwebmobile-wholesale-pricing'); ?></p>
        <p><code>[hdws_apply]</code> <?php esc_html_e('-- place the wholesale application form anywhere. It also appears on the My Account dashboard.', 'hdwebmobile-wholesale-pricing'); ?></p>

        <?php if (!empty($_GET['hdws_done'])) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag. ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Application updated.', 'hdwebmobile-wholesale-pricing'); ?></p></div>
        <?php endif; ?>

        <form method="post" action="options.php" style="margin:1em 0;">
            <?php settings_fields('hdws_group'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="hdws_global_percent"><?php esc_html_e('Store-wide wholesale discount', 'hdwebmobile-wholesale-pricing'); ?></label></th>
                    <td>
                        <input type="number" id="hdws_global_percent" name="<?php echo esc_attr(HDWS_Pricing::OPTION_KEY); ?>[global_percent]" value="<?php echo esc_attr($settings['global_percent']); ?>" min="0" max="90" step="0.01" class="small-text" /> %
                        <p class="description"><?php esc_html_e('Applied to any product that does not have its own wholesale price set. 0 disables it.', 'hdwebmobile-wholesale-pricing'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Save', 'hdwebmobile-wholesale-pricing')); ?>
        </form>

        <h2><?php esc_html_e('Pending wholesale applications', 'hdwebmobile-wholesale-pricing'); ?></h2>
        <?php
        $pending = HDWS_Repository::get_pending();
        if (empty($pending)) {
            echo '<p>' . esc_html__('No applications are waiting for review.', 'hdwebmobile-wholesale-pricing') . '</p>';
            return;
        }
        ?>
        <table class="widefat striped" style="max-width:900px;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Customer', 'hdwebmobile-wholesale-pricing'); ?></th>
                    <th><?php esc_html_e('Company', 'hdwebmobile-wholesale-pricing'); ?></th>
                    <th><?php esc_html_e('Tax ID', 'hdwebmobile-wholesale-pricing'); ?></th>
                    <th><?php esc_html_e('Note', 'hdwebmobile-wholesale-pricing'); ?></th>
                    <th><?php esc_html_e('Submitted', 'hdwebmobile-wholesale-pricing'); ?></th>
                    <th><?php esc_html_e('Decision', 'hdwebmobile-wholesale-pricing'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending as $app) : ?>
                    <?php
                    $user     = get_user_by('id', (int) $app->user_id);
                    $approve  = wp_nonce_url(admin_url('admin-post.php?action=hdws_decide&decision=approve&app=' . (int) $app->id), self::NONCE_ACTION, 'hdws_nonce');
                    $reject   = wp_nonce_url(admin_url('admin-post.php?action=hdws_decide&decision=reject&app=' . (int) $app->id), self::NONCE_ACTION, 'hdws_nonce');
                    ?>
                    <tr>
                        <td>
                            <?php if ($user) : ?>
                                <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>"><?php echo esc_html($user->user_email); ?></a>
                            <?php else : ?>
                                <?php echo esc_html__('(user deleted)', 'hdwebmobile-wholesale-pricing'); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($app->company); ?></td>
                        <td><?php echo esc_html($app->tax_id); ?></td>
                        <td><?php echo esc_html(wp_trim_words((string) $app->note, 20)); ?></td>
                        <td><?php echo esc_html(mysql2date(get_option('date_format'), $app->created_at)); ?></td>
                        <td>
                            <a href="<?php echo esc_url($approve); ?>" class="button button-primary button-small"><?php esc_html_e('Approve', 'hdwebmobile-wholesale-pricing'); ?></a>
                            <a href="<?php echo esc_url($reject); ?>" class="button button-small"><?php esc_html_e('Reject', 'hdwebmobile-wholesale-pricing'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}

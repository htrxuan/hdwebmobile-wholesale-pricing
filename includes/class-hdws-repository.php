<?php

namespace htrxuan\hdws;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The wholesale-application store, and the ONLY place the wholesale role is ever granted.
 *
 * CVE-2026-27542 (unauthenticated privilege escalation to administrator) in "WooCommerce
 * Wholesale Lead Capture" (< 2.0.3.2): its lead/registration flow acted on role information
 * coming from the request, so an attacker could register themselves straight into a
 * privileged role.
 *
 * This class makes that impossible by construction:
 *
 *  - create_application() takes the applicant's user id as its FIRST parameter, and the only
 *    caller (HDWS_Account) always passes get_current_user_id(). There is no field, and no
 *    other method, that accepts a user id, a role, or a capability from anywhere.
 *  - An application is just a row with status 'pending'. Submitting one changes nobody's
 *    role, caps, or account.
 *  - grant_role() -- the single line that calls WP_User::add_role(HDWS_ROLE) -- is private
 *    and is reached only through approve(), which is called only from HDWS_Admin's
 *    admin_post handler after a current_user_can('manage_woocommerce') check and a verified
 *    nonce. approve() takes only an application id.
 *  - HDWS_ROLE itself is registered (see HDWS_Activator) with the single 'read' capability,
 *    so it is a customer-equivalent with no management power at all.
 */
class HDWS_Repository
{
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    public static function table()
    {
        global $wpdb;
        return $wpdb->prefix . 'hdws_applications';
    }

    public static function get_schema_sql()
    {
        global $wpdb;
        $table   = self::table();
        $collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            company VARCHAR(191) NOT NULL DEFAULT '',
            tax_id VARCHAR(100) NOT NULL DEFAULT '',
            note TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            decided_at DATETIME NULL,
            decided_by BIGINT UNSIGNED NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_pending (user_id, status),
            KEY status (status)
        ) {$collate};";
    }

    /**
     * Create (or replace) this user's wholesale application. The user is the FIRST argument
     * and is always the current, authenticated user -- never anything from the form.
     *
     * @param int   $user_id  The applicant. Callers pass get_current_user_id() only.
     * @param array $data      ['company' => string, 'tax_id' => string, 'note' => string]
     * @return int|\WP_Error   New application id, or an error.
     */
    public static function create_application($user_id, array $data)
    {
        global $wpdb;

        $user_id = absint($user_id);
        if ($user_id < 1) {
            return new \WP_Error('hdws_no_user', __('You must be logged in to apply.', 'hdwebmobile-wholesale-pricing'));
        }

        $company = isset($data['company']) ? sanitize_text_field($data['company']) : '';
        $tax_id  = isset($data['tax_id']) ? sanitize_text_field($data['tax_id']) : '';
        $note    = isset($data['note']) ? sanitize_textarea_field($data['note']) : '';

        if ('' === $company) {
            return new \WP_Error('hdws_no_company', __('Please enter your company name.', 'hdwebmobile-wholesale-pricing'));
        }

        // One live application per user: clear any previous pending/rejected row first.
        $wpdb->delete(self::table(), array('user_id' => $user_id), array('%d')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        $ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            self::table(),
            array(
                'user_id'    => $user_id,
                'company'    => $company,
                'tax_id'     => $tax_id,
                'note'       => $note,
                'status'     => self::STATUS_PENDING,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );

        if (!$ok) {
            return new \WP_Error('hdws_db', __('Could not save your application. Please try again.', 'hdwebmobile-wholesale-pricing'));
        }
        return (int) $wpdb->insert_id;
    }

    public static function get_for_user($user_id)
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE user_id = %d ORDER BY id DESC LIMIT 1', self::table(), absint($user_id)));
    }

    public static function get_by_id($id)
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', self::table(), absint($id)));
    }

    public static function get_pending()
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE status = %s ORDER BY created_at ASC', self::table(), self::STATUS_PENDING));
    }

    /**
     * Approve an application: mark it approved and grant that applicant the wholesale role.
     * Only HDWS_Admin's capability + nonce-checked admin_post handler calls this, and it
     * passes only an application id.
     *
     * @param int $app_id
     * @param int $decided_by  The admin performing the approval (for the audit columns only).
     * @return bool
     */
    public static function approve($app_id, $decided_by = 0)
    {
        $app = self::get_by_id($app_id);
        if (!$app || self::STATUS_APPROVED === $app->status) {
            return false;
        }

        self::set_status($app_id, self::STATUS_APPROVED, $decided_by);
        self::grant_role((int) $app->user_id);
        return true;
    }

    public static function reject($app_id, $decided_by = 0)
    {
        $app = self::get_by_id($app_id);
        if (!$app) {
            return false;
        }
        self::set_status($app_id, self::STATUS_REJECTED, $decided_by);
        // If they had been approved before, remove the role again.
        $user = get_user_by('id', (int) $app->user_id);
        if ($user && in_array(HDWS_ROLE, (array) $user->roles, true)) {
            $user->remove_role(HDWS_ROLE);
        }
        return true;
    }

    private static function set_status($app_id, $status, $decided_by)
    {
        global $wpdb;
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            self::table(),
            array('status' => $status, 'decided_at' => current_time('mysql'), 'decided_by' => absint($decided_by)),
            array('id' => absint($app_id)),
            array('%s', '%s', '%d'),
            array('%d')
        );
    }

    /**
     * The one and only place WP_User::add_role(HDWS_ROLE) is called in the whole plugin.
     */
    private static function grant_role($user_id)
    {
        $user = get_user_by('id', absint($user_id));
        if ($user && !in_array(HDWS_ROLE, (array) $user->roles, true)) {
            $user->add_role(HDWS_ROLE);
        }
    }

    /** True when the given (or current) user holds the wholesale role -- checked server-side. */
    public static function user_is_wholesale($user = null)
    {
        if (null === $user) {
            $user = wp_get_current_user();
        }
        return $user && $user->exists() && in_array(HDWS_ROLE, (array) $user->roles, true);
    }
}

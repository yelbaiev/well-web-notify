<?php
/**
 * Well Web Notify — WordPress.org review prompt
 *
 * Polite, gated, dismissible. Shown only on the plugin's own admin pages,
 * only to admins, only after the plugin has been in active use.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WellWeb_Notify_Review_Notice {

    const OPTION_ACTIVATION_DATE = 'wellweb_notify_activation_date';
    const META_DISMISSED         = 'wellweb_notify_review_dismissed';
    const META_SNOOZED_UNTIL     = 'wellweb_notify_review_snoozed_until';
    const ACTION                 = 'wellweb_notify_review_action';
    const NONCE                  = 'wellweb_notify_review';
    const DELAY_DAYS             = 14;
    const SNOOZE_DAYS            = 30;
    const REVIEW_URL             = 'https://wordpress.org/support/plugin/well-web-notify/reviews/?rate=5#new-post';

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'maybe_seed_activation_date' ) );
        add_action( 'admin_notices', array( __CLASS__, 'render' ) );
        add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_action' ) );
    }

    /**
     * Existing installs upgrading to this version won't have an activation
     * date — start the 14-day clock the first time an admin visits wp-admin
     * so they aren't immediately prompted.
     */
    public static function maybe_seed_activation_date() {
        if ( ! get_option( self::OPTION_ACTIVATION_DATE ) ) {
            update_option( self::OPTION_ACTIVATION_DATE, time(), false );
        }
    }

    public static function render() {
        if ( ! self::should_show() ) {
            return;
        }

        $rate_url    = self::action_url( 'rate' );
        $done_url    = self::action_url( 'done' );
        $snooze_url  = self::action_url( 'snooze' );

        ?>
        <div class="notice notice-info is-dismissible" style="border-left-color:#FF6600;">
            <p style="margin:0.6em 0;">
                <strong><?php esc_html_e( 'Enjoying Well Web Notify?', 'well-web-notify' ); ?></strong>
                <?php esc_html_e( 'A quick review on WordPress.org would mean a lot — it helps other site owners discover the plugin and keeps it actively maintained. Thanks for considering it!', 'well-web-notify' ); ?>
            </p>
            <p style="margin:0.6em 0;">
                <a href="<?php echo esc_url( $rate_url ); ?>" class="button button-primary">
                    <?php esc_html_e( "Sure, I'd be happy to", 'well-web-notify' ); ?>
                </a>
                &nbsp;
                <a href="<?php echo esc_url( $done_url ); ?>" class="button">
                    <?php esc_html_e( 'I already did', 'well-web-notify' ); ?>
                </a>
                &nbsp;
                <a href="<?php echo esc_url( $snooze_url ); ?>">
                    <?php esc_html_e( 'Maybe later', 'well-web-notify' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    public static function handle_action() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'well-web-notify' ), '', array( 'response' => 403 ) );
        }

        check_admin_referer( self::NONCE );

        $choice  = isset( $_GET['choice'] ) ? sanitize_key( wp_unslash( $_GET['choice'] ) ) : '';
        $user_id = get_current_user_id();

        switch ( $choice ) {
            case 'rate':
                update_user_meta( $user_id, self::META_DISMISSED, 1 );
                wp_safe_redirect( self::REVIEW_URL );
                exit;

            case 'done':
                update_user_meta( $user_id, self::META_DISMISSED, 1 );
                break;

            case 'snooze':
                update_user_meta( $user_id, self::META_SNOOZED_UNTIL, time() + ( self::SNOOZE_DAYS * DAY_IN_SECONDS ) );
                break;
        }

        $referer = wp_get_referer();
        wp_safe_redirect( $referer ? $referer : admin_url( 'admin.php?page=well-web-notify-settings' ) );
        exit;
    }

    private static function should_show(): bool {
        if ( ! is_admin() || wp_doing_ajax() ) {
            return false;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        // Limit to the plugin's own admin pages.
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( $page !== 'well-web-notify-settings' && $page !== 'well-web-notify-log' ) {
            return false;
        }

        $user_id = get_current_user_id();

        if ( get_user_meta( $user_id, self::META_DISMISSED, true ) ) {
            return false;
        }

        $snoozed_until = (int) get_user_meta( $user_id, self::META_SNOOZED_UNTIL, true );
        if ( $snoozed_until && $snoozed_until > time() ) {
            return false;
        }

        // Time gate.
        $activated = (int) get_option( self::OPTION_ACTIVATION_DATE );
        if ( ! $activated || ( time() - $activated ) < ( self::DELAY_DAYS * DAY_IN_SECONDS ) ) {
            return false;
        }

        // Usage gate — at least one successful notification in the last 30 days.
        if ( ! class_exists( 'WellWeb_Notify_Log' ) ) {
            return false;
        }
        $stats = WellWeb_Notify_Log::get_stats( 30 );
        if ( empty( $stats['success'] ) || (int) $stats['success'] < 1 ) {
            return false;
        }

        return true;
    }

    private static function action_url( string $choice ): string {
        return wp_nonce_url(
            add_query_arg(
                array(
                    'action' => self::ACTION,
                    'choice' => $choice,
                ),
                admin_url( 'admin-post.php' )
            ),
            self::NONCE
        );
    }
}

add_action( 'init', array( 'WellWeb_Notify_Review_Notice', 'init' ) );

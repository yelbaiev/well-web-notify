<?php
/**
 * Well Web Notify — Daily Bot Health Check
 *
 * Runs once daily to verify each enabled channel is reachable.
 * Sends a lightweight health-check message to each configured channel.
 * On failure: sends email alert to site admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WellWeb_Notify_Health_Check {

    const CRON_HOOK       = 'wellweb_notify_health_check';
    const OPTION_RESULTS  = 'well-web-notify-health-results';
    const OPTION_LAST_RUN = 'well-web-notify-health-last-run';
    const OPTION_ALERT_EMAIL = 'admin_email';

    public static function init() {
        add_action( self::CRON_HOOK, array( __CLASS__, 'run_check' ) );

        // Schedule daily check if not already scheduled
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            // Run at ~6 AM UTC daily
            $next = strtotime( 'tomorrow 06:00:00 UTC' );
            wp_schedule_event( $next, 'daily', self::CRON_HOOK );
        }

        add_filter( 'cron_schedules', array( __CLASS__, 'ensure_daily_schedule' ) );
    }

    /**
     * Ensure 'daily' schedule exists (it's core, but just in case)
     */
    public static function ensure_daily_schedule( $schedules ) {
        if ( ! isset( $schedules['daily'] ) ) {
            $schedules['daily'] = array(
                'interval' => DAY_IN_SECONDS,
                'display'  => __( 'Once Daily', 'well-web-notify' ),
            );
        }
        return $schedules;
    }

    /**
     * Main health check runner — called by WP-Cron daily
     */
    public static function run_check() {
        $manager = WellWeb_Notify_Channel_Manager::instance();
        $channels = $manager->get_channels();

        $results   = array();
        $failures  = array();
        $successes = array();
        $domain    = wellweb_notify_site_domain();
        $site_url  = home_url();
        $timestamp = current_time( 'mysql' );

        foreach ( $channels as $slug => $channel ) {
            // Skip channels that aren't enabled or configured
            if ( ! $channel->is_enabled() || ! $channel->is_configured() ) {
                $results[ $slug ] = array(
                    'status'  => 'skipped',
                    'message' => 'Not enabled or not configured',
                    'time'    => $timestamp,
                );
                continue;
            }

            // Send a health check message to the channel
            $check_result = $channel->send(
                __( 'Daily health check', 'well-web-notify' ),
                sprintf(
                    /* translators: 1: channel name e.g. "Telegram", 2: site domain */
                    __( '%1$s connection is healthy — notifications from %2$s are being delivered.', 'well-web-notify' ),
                    $channel->get_label(),
                    $domain
                ),
                array( 'form_name' => 'Health Check' )
            );

            if ( is_wp_error( $check_result ) ) {
                $results[ $slug ] = array(
                    'status'  => 'error',
                    'message' => $check_result->get_error_message(),
                    'time'    => $timestamp,
                );
                $failures[ $slug ] = $check_result->get_error_message();
            } else {
                $results[ $slug ] = array(
                    'status'  => 'ok',
                    'message' => 'Connected',
                    'time'    => $timestamp,
                );
                $successes[] = $slug;
            }

            // Log the health check
            WellWeb_Notify_Log::log(
                $slug,
                'Health Check',
                is_wp_error( $check_result ) ? 'error' : 'success',
                is_wp_error( $check_result ) ? $check_result->get_error_message() : 'Daily health check passed'
            );
        }

        // Store results
        update_option( self::OPTION_RESULTS, $results, false );
        update_option( self::OPTION_LAST_RUN, $timestamp, false );

        // Send failure email alert if any channel failed
        if ( ! empty( $failures ) ) {
            self::send_failure_email( $failures, $domain, $site_url, $timestamp );
        }
    }

    /**
     * Send email alert for channel failures
     */
    private static function send_failure_email( array $failures, string $site_name, string $site_url, string $timestamp ) {
        $subject = sprintf( '[Well Web Notify] Bot failure on %s', $site_name );

        $body = sprintf( "Daily bot health check failed for %s (%s)\n", $site_name, $site_url );
        $body .= sprintf( "Time: %s\n\n", $timestamp );
        $body .= "Failed channels:\n";
        $body .= str_repeat( '-', 40 ) . "\n";

        foreach ( $failures as $slug => $error ) {
            $body .= sprintf( "• %s: %s\n", ucfirst( str_replace( '_', ' ', $slug ) ), $error );
        }

        $body .= "\n" . str_repeat( '-', 40 ) . "\n";
        $body .= "Please check your channel configuration in WordPress admin:\n";
        $body .= admin_url( 'admin.php?page=well-web-notify' ) . "\n\n";
        $body .= "This check runs daily. You'll continue to receive alerts until the issue is resolved.\n";

        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );

        $admin_email = get_option( 'admin_email', '' );
        if ( ! empty( $admin_email ) ) {
            wp_mail( $admin_email, $subject, $body, $headers );
        }
    }

    /**
     * Get the latest health check results
     *
     * @return array [ 'channel_slug' => [ 'status' => 'ok'|'error'|'skipped', 'message' => '...', 'time' => '...' ] ]
     */
    public static function get_results(): array {
        return get_option( self::OPTION_RESULTS, array() );
    }

    /**
     * Get the last run time
     */
    public static function get_last_run(): string {
        return get_option( self::OPTION_LAST_RUN, '' );
    }

    /**
     * Check if any channel has a failure
     */
    public static function has_failures(): bool {
        $results = self::get_results();
        foreach ( $results as $result ) {
            if ( $result['status'] === 'error' ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get failed channel slugs
     */
    public static function get_failed_channels(): array {
        $failed  = array();
        $results = self::get_results();
        foreach ( $results as $slug => $result ) {
            if ( $result['status'] === 'error' ) {
                $failed[ $slug ] = $result['message'];
            }
        }
        return $failed;
    }

    /**
     * Run on plugin deactivation — unschedule cron
     */
    public static function on_deactivation() {
        $ts = wp_next_scheduled( self::CRON_HOOK );
        if ( $ts ) {
            wp_unschedule_event( $ts, self::CRON_HOOK );
        }
    }
}

add_action( 'init', array( 'WellWeb_Notify_Health_Check', 'init' ) );

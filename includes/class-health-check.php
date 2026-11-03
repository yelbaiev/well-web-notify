<?php
/**
 * Well Web Notify — daily connection check and weekly summary.
 *
 * ── Daily check: silent ─────────────────────────────────────────────────────
 *
 * It used to post "Daily health check" into every connected chat each morning.
 * On a site whose chat is the client's own Telegram group, that is a message a
 * day the client did not ask for. So the check no longer posts anything:
 *
 *  - A channel that delivered a real submission in the last 24 hours has
 *    already proved itself and is not touched.
 *  - Otherwise the channel's `check_connection()` asks the service whether the
 *    bot or webhook can still post, without posting (see each channel class).
 *  - A third-party channel without `check_connection()` is left unchecked
 *    rather than spammed.
 *
 * A failure is emailed to the alert address (Monitoring section), which falls
 * back to the site admin email — so it reaches whoever maintains the site, not
 * the people in the chat.
 *
 * ── Weekly summary: opt-in, sound off ───────────────────────────────────────
 *
 * The one message the client does see, if switched on: once a week, how many
 * notifications reached that chat, delivered without a notification sound.
 *
 * ── Production only ─────────────────────────────────────────────────────────
 *
 * Neither runs on a staging copy (wellweb_notify_is_production()). Form
 * notifications still do — they are what gets tested there.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WellWeb_Notify_Health_Check {

    const CRON_HOOK         = 'wellweb_notify_health_check';
    const SUMMARY_HOOK      = 'wellweb_notify_weekly_summary';
    const OPTION_RESULTS    = 'well-web-notify-health-results';
    const OPTION_LAST_RUN   = 'well-web-notify-health-last-run';
    const OPTION_SUMMARY_ON = 'well-web-notify-weekly-summary';

    public static function init() {
        add_action( self::CRON_HOOK, array( __CLASS__, 'run_check' ) );
        add_action( self::SUMMARY_HOOK, array( __CLASS__, 'send_weekly_summary' ) );
        add_filter( 'cron_schedules', array( __CLASS__, 'ensure_schedules' ) );

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            // ~6 AM UTC daily
            wp_schedule_event( strtotime( 'tomorrow 06:00:00 UTC' ), 'daily', self::CRON_HOOK );
        }

        if ( ! wp_next_scheduled( self::SUMMARY_HOOK ) ) {
            // Monday morning, ~7 AM UTC: the start of the client's week.
            wp_schedule_event( strtotime( 'next monday 07:00:00 UTC' ), 'weekly', self::SUMMARY_HOOK );
        }
    }

    /**
     * 'daily' and 'weekly' are core schedules; this only guards against a site
     * that has filtered them away.
     */
    public static function ensure_schedules( $schedules ) {
        if ( ! isset( $schedules['daily'] ) ) {
            $schedules['daily'] = array(
                'interval' => DAY_IN_SECONDS,
                'display'  => __( 'Once Daily', 'well-web-notify' ),
            );
        }
        if ( ! isset( $schedules['weekly'] ) ) {
            $schedules['weekly'] = array(
                'interval' => WEEK_IN_SECONDS,
                'display'  => __( 'Once Weekly', 'well-web-notify' ),
            );
        }
        return $schedules;
    }

    /**
     * The daily check — called by WP-Cron.
     */
    public static function run_check() {
        $timestamp = current_time( 'mysql' );

        if ( ! wellweb_notify_is_production() ) {
            update_option( self::OPTION_RESULTS, array(), false );
            update_option( self::OPTION_LAST_RUN, $timestamp, false );
            return;
        }

        $channels   = WellWeb_Notify_Channel_Manager::instance()->get_channels();
        $delivered  = WellWeb_Notify_Log::count_deliveries( DAY_IN_SECONDS );
        $results    = array();
        $failures   = array();

        foreach ( $channels as $slug => $channel ) {
            if ( ! $channel->is_enabled() || ! $channel->is_configured() ) {
                $results[ $slug ] = array(
                    'status'  => 'skipped',
                    'message' => 'Not enabled or not configured',
                    'time'    => $timestamp,
                );
                continue;
            }

            // A real delivery in the last day is better evidence than any probe.
            if ( ! empty( $delivered[ $slug ] ) ) {
                $results[ $slug ] = array(
                    'status'  => 'ok',
                    'message' => 'Delivered a notification in the last 24 hours',
                    'time'    => $timestamp,
                );
                continue;
            }

            if ( ! method_exists( $channel, 'check_connection' ) ) {
                $results[ $slug ] = array(
                    'status'  => 'skipped',
                    'message' => 'This channel has no silent check',
                    'time'    => $timestamp,
                );
                continue;
            }

            $check = $channel->check_connection();

            if ( is_wp_error( $check ) ) {
                $results[ $slug ] = array(
                    'status'  => 'error',
                    'message' => $check->get_error_message(),
                    'time'    => $timestamp,
                );
                $failures[ $slug ] = $check->get_error_message();

                // Only failures go in the log: a daily "passed" row per channel
                // is noise in the place people look for problems.
                WellWeb_Notify_Log::log( $slug, 'Health Check', 'error', $check->get_error_message() );
            } else {
                $results[ $slug ] = array(
                    'status'  => 'ok',
                    'message' => 'Connected',
                    'time'    => $timestamp,
                );
            }
        }

        update_option( self::OPTION_RESULTS, $results, false );
        update_option( self::OPTION_LAST_RUN, $timestamp, false );

        if ( $failures ) {
            self::send_failure_email( $failures, wellweb_notify_site_domain(), home_url(), $timestamp );
        }
    }

    /**
     * The weekly summary — called by WP-Cron. Opt-in, production only.
     */
    public static function send_weekly_summary() {
        if ( ! get_option( self::OPTION_SUMMARY_ON, false ) || ! wellweb_notify_is_production() ) {
            return;
        }

        $manager   = WellWeb_Notify_Channel_Manager::instance();
        $delivered = WellWeb_Notify_Log::count_deliveries( WEEK_IN_SECONDS );
        $failures  = array();

        foreach ( $manager->get_active_channels() as $slug => $channel ) {
            $message = self::summary_message( (int) ( $delivered[ $slug ] ?? 0 ) );
            $result  = $channel->send( $message['title'], $message['note'], $message );

            if ( is_wp_error( $result ) ) {
                $failures[ $slug ] = $result->get_error_message();
                WellWeb_Notify_Log::log( $slug, 'Weekly summary', 'error', $result->get_error_message() );
            } else {
                WellWeb_Notify_Log::log( $slug, 'Weekly summary', 'success' );
            }
        }

        if ( $failures ) {
            self::send_failure_email( $failures, wellweb_notify_site_domain(), home_url(), current_time( 'mysql' ) );
        }
    }

    /**
     * What the weekly summary says.
     *
     * @param int $count Real notifications delivered to this channel in 7 days.
     * @return array A built message.
     */
    public static function summary_message( int $count ): array {
        if ( $count > 0 ) {
            $note = sprintf(
                /* translators: %s: number of notifications */
                _n(
                    'This week %s notification was delivered here. Everything is working.',
                    'This week %s notifications were delivered here. Everything is working.',
                    $count,
                    'well-web-notify'
                ),
                number_format_i18n( $count )
            );
        } else {
            $note = __( 'No new notifications this week. The connection is working, so new ones will arrive here as usual.', 'well-web-notify' );
        }

        return WellWeb_Notify_Message::build( array(
            'kind'   => 'summary',
            'title'  => __( 'Weekly summary', 'well-web-notify' ),
            'note'   => $note,
            'silent' => true,
        ) );
    }

    /**
     * Email the alert address about channels that failed.
     */
    private static function send_failure_email( array $failures, string $site_name, string $site_url, string $timestamp ) {
        $to = wellweb_notify_alert_email();
        if ( $to === '' ) {
            return;
        }

        $subject = sprintf( '[Well Web Notify] Connection problem on %s', $site_name );

        $body  = sprintf( "Well Web Notify could not confirm a channel on %s (%s)\n", $site_name, $site_url );
        $body .= sprintf( "Time: %s\n\n", $timestamp );
        $body .= "Failed channels:\n";
        $body .= str_repeat( '-', 40 ) . "\n";

        $manager = WellWeb_Notify_Channel_Manager::instance();
        foreach ( $failures as $slug => $error ) {
            $channel = $manager->get_channel( $slug );
            $label   = $channel ? $channel->get_label() : ucfirst( str_replace( array( '_', '-' ), ' ', $slug ) );
            $body   .= sprintf( "• %s: %s\n", $label, $error );
        }

        $body .= "\n" . str_repeat( '-', 40 ) . "\n";
        $body .= "Check the channel settings in WordPress admin:\n";
        $body .= admin_url( 'admin.php?page=' . WELLWEB_NOTIFY_PAGE ) . "\n\n";
        $body .= "Nothing was posted to the chat. This check runs daily and will email again until the problem is fixed.\n";

        wp_mail( $to, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
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
        wp_clear_scheduled_hook( self::CRON_HOOK );
        wp_clear_scheduled_hook( self::SUMMARY_HOOK );
    }
}

add_action( 'init', array( 'WellWeb_Notify_Health_Check', 'init' ) );

<?php
/**
 * Which options exist, how they are sanitised, and the two AJAX endpoints.
 *
 * Split out of the old options.php, which was the menu, the styles, the
 * settings registry, both screens and both endpoints in one 670-line file.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * One settings group, because there is one form.
 *
 * It was briefly two. `wp-admin/options.php` walks every option REGISTERED to
 * the submitted group and calls `update_option()` on each, passing null for the
 * ones absent from the request — and a checkbox that is off is absent. So while
 * the settings were split across two screens sharing one group, saving Sources
 * handed null to every channel's `-enabled` option and switched all four
 * channels off, and saving Channels cleared every WooCommerce event.
 *
 * Splitting the group fixed the symptom. Putting the settings back on one
 * screen, in one form, removed the cause: nothing is absent any more.
 */
const WELLWEB_NOTIFY_SETTINGS_GROUP = 'well-web-notify-settings';

/** The WooCommerce order events a site can be notified about. */
function wellweb_notify_woo_events(): array {
    return array(
        'new-order'  => __( 'New order placed', 'well-web-notify' ),
        'processing' => __( 'Moved to Processing', 'well-web-notify' ),
        'completed'  => __( 'Moved to Completed', 'well-web-notify' ),
        'cancelled'  => __( 'Cancelled', 'well-web-notify' ),
        'refunded'   => __( 'Refunded', 'well-web-notify' ),
        'failed'     => __( 'Payment failed', 'well-web-notify' ),
    );
}

/**
 * Sanitize checkbox helper
 */
function wellweb_notify_sanitize_checkbox( $value ) {
    return ! empty( $value );
}

add_action( 'admin_init', 'wellweb_notify_register_settings' );
function wellweb_notify_register_settings() {
    foreach ( WellWeb_Notify_Channel_Manager::instance()->get_channels() as $channel ) {
        foreach ( $channel->get_settings() as $option => $sanitize ) {
            register_setting( WELLWEB_NOTIFY_SETTINGS_GROUP, $option, array( 'sanitize_callback' => $sanitize ) );
        }
    }

    // Default country code for phone contact links
    register_setting( WELLWEB_NOTIFY_SETTINGS_GROUP, 'well-web-notify-default-country-code', array(
        'sanitize_callback' => 'absint',
    ) );

    // Monitoring
    register_setting( WELLWEB_NOTIFY_SETTINGS_GROUP, 'well-web-notify-production-domain', array(
        'sanitize_callback' => 'wellweb_notify_sanitize_domain',
    ) );
    register_setting( WELLWEB_NOTIFY_SETTINGS_GROUP, 'well-web-notify-alert-email', array(
        'sanitize_callback' => 'sanitize_email',
    ) );
    register_setting( WELLWEB_NOTIFY_SETTINGS_GROUP, WellWeb_Notify_Health_Check::OPTION_SUMMARY_ON, array(
        'type'              => 'boolean',
        'default'           => false,
        'sanitize_callback' => 'wellweb_notify_sanitize_checkbox',
    ) );

    register_setting( WELLWEB_NOTIFY_SETTINGS_GROUP, 'well-web-notify-woo-enabled', array(
        'type'              => 'boolean',
        'default'           => false,
        'sanitize_callback' => 'wellweb_notify_sanitize_checkbox',
    ) );

    foreach ( array_keys( wellweb_notify_woo_events() ) as $event ) {
        register_setting( WELLWEB_NOTIFY_SETTINGS_GROUP, 'well-web-notify-woo-event-' . $event, array(
            'type'              => 'boolean',
            'default'           => false,
            'sanitize_callback' => 'wellweb_notify_sanitize_checkbox',
        ) );
    }
}

// ─── AJAX: send a test message ────────────────────────────────

add_action( 'wp_ajax_wellweb_notify_test', 'wellweb_notify_ajax_test' );
function wellweb_notify_ajax_test() {
    check_ajax_referer( 'wellweb_notify', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'well-web-notify' ) );
    }

    $slug    = sanitize_text_field( wp_unslash( $_POST['channel'] ?? '' ) );
    $channel = WellWeb_Notify_Channel_Manager::instance()->get_channel( $slug );

    if ( ! $channel ) {
        wp_send_json_error( __( 'Unknown channel.', 'well-web-notify' ) );
    }

    if ( ! $channel->is_configured() ) {
        wp_send_json_error( __( 'Fill in this channel\'s details and save, then try again.', 'well-web-notify' ) );
    }

    $result = $channel->send_test();

    if ( is_wp_error( $result ) ) {
        WellWeb_Notify_Log::log( $slug, 'Test', 'error', $result->get_error_message() );
        wp_send_json_error( $result->get_error_message() );
    }

    WellWeb_Notify_Log::log( $slug, 'Test', 'success' );
    wp_send_json_success( __( 'Sent — check the chat.', 'well-web-notify' ) );
}

/*
 * REMOVED: `wp_ajax_wellweb_notify_save_channel`.
 *
 * An endpoint that wrote any `well-web-notify-*` option, including the
 * encrypted credentials, and that nothing called — not this plugin's admin
 * script, which posts the ordinary settings form, and not the Pro add-on. A
 * write surface with no caller is worth deleting rather than documenting.
 */

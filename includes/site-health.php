<?php
/**
 * WordPress Site Health integration for Well Web Notify
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add info to Site Health
add_filter( 'debug_information', 'wellweb_notify_site_health_info' );
function wellweb_notify_site_health_info( $debug_info ) {
    $manager  = WellWeb_Notify_Channel_Manager::instance();
    $channels = $manager->get_channels();
    $stats    = WellWeb_Notify_Log::get_stats( 7 );

    $fields = array();

    $fields['version'] = array(
        'label' => __( 'Plugin Version', 'well-web-notify' ),
        'value' => defined( 'WELLWEB_NOTIFY_VERSION' ) ? WELLWEB_NOTIFY_VERSION : 'unknown',
    );

    foreach ( $channels as $ch ) {
        $status = array();
        if ( $ch->is_enabled() ) $status[] = __( 'Enabled', 'well-web-notify' );
        if ( $ch->is_configured() ) $status[] = __( 'Configured', 'well-web-notify' );

        $fields[ 'channel_' . $ch->get_slug() ] = array(
            'label' => $ch->get_label(),
            'value' => $status ? implode( ', ', $status ) : __( 'Disabled', 'well-web-notify' ),
        );
    }

    $form_manager = WellWeb_Notify_Form_Manager::instance();
    $available    = $form_manager->get_available_forms();
    $form_names   = array_map( function( $f ) { return $f->get_label(); }, $available );

    $fields['forms'] = array(
        'label' => __( 'Active Form Plugins', 'well-web-notify' ),
        'value' => $form_names ? implode( ', ', $form_names ) : __( 'None detected', 'well-web-notify' ),
    );

    $fields['stats'] = array(
        'label' => __( 'Notifications (7 days)', 'well-web-notify' ),
        'value' => sprintf( '%d total, %d success, %d errors', $stats['total'], $stats['success'], $stats['error'] ),
    );

    $debug_info['well-web-notify'] = array(
        'label'  => __( 'Well Web Notify', 'well-web-notify' ),
        'fields' => $fields,
    );

    return $debug_info;
}

// Add Site Health tests
add_filter( 'site_status_tests', 'wellweb_notify_site_health_tests' );
function wellweb_notify_site_health_tests( $tests ) {
    $tests['direct']['wellweb_notify_channels'] = array(
        'label' => __( 'Well Web Notify channels', 'well-web-notify' ),
        'test'  => 'wellweb_notify_test_channels',
    );

    return $tests;
}

function wellweb_notify_test_channels() {
    $manager = WellWeb_Notify_Channel_Manager::instance();
    $active  = $manager->get_active_channels();
    $stats   = WellWeb_Notify_Log::get_stats( 7 );

    $result = array(
        'label'       => __( 'Well Web Notify is configured', 'well-web-notify' ),
        'status'      => 'good',
        'badge'       => array(
            'label' => __( 'Notifications', 'well-web-notify' ),
            'color' => 'blue',
        ),
        'description' => '',
        'actions'     => sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=well-web-notify-settings' ) ),
            __( 'Manage channels', 'well-web-notify' )
        ),
        'test'        => 'wellweb_notify_channels',
    );

    if ( empty( $active ) ) {
        $result['status']      = 'recommended';
        $result['label']       = __( 'No notification channels are active', 'well-web-notify' );
        $result['description'] = __( 'Well Web Notify has no active channels. Enable and configure at least one channel to receive form notifications.', 'well-web-notify' );
        $result['badge']['color'] = 'orange';
    } elseif ( $stats['error'] > 0 && $stats['error'] > $stats['success'] ) {
        $result['status']      = 'recommended';
        $result['label']       = __( 'Well Web Notify has delivery errors', 'well-web-notify' );
        $result['description'] = sprintf(
            /* translators: %d: number of errors */
            __( '%d errors in the last 7 days. Check the notification log for details.', 'well-web-notify' ),
            $stats['error']
        );
        $result['badge']['color'] = 'orange';
    } else {
        $result['description'] = sprintf(
            /* translators: %1$d: number of active channels, %2$d: number of notifications */
            __( '%1$d active channel(s). %2$d notifications sent in the last 7 days.', 'well-web-notify' ),
            count( $active ),
            $stats['total']
        );
    }

    return $result;
}

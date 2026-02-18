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
        'label' => __( 'Plugin Version', 'wellweb-notify' ),
        'value' => defined( 'WELLWEB_NOTIFY_VERSION' ) ? WELLWEB_NOTIFY_VERSION : 'unknown',
    );

    foreach ( $channels as $ch ) {
        $status = array();
        if ( $ch->is_enabled() ) $status[] = __( 'Enabled', 'wellweb-notify' );
        if ( $ch->is_configured() ) $status[] = __( 'Configured', 'wellweb-notify' );

        $fields[ 'channel_' . $ch->get_slug() ] = array(
            'label' => $ch->get_label(),
            'value' => $status ? implode( ', ', $status ) : __( 'Disabled', 'wellweb-notify' ),
        );
    }

    $form_manager = WellWeb_Notify_Form_Manager::instance();
    $available    = $form_manager->get_available_forms();
    $form_names   = array_map( function( $f ) { return $f->get_label(); }, $available );

    $fields['forms'] = array(
        'label' => __( 'Active Form Plugins', 'wellweb-notify' ),
        'value' => $form_names ? implode( ', ', $form_names ) : __( 'None detected', 'wellweb-notify' ),
    );

    $fields['stats'] = array(
        'label' => __( 'Notifications (7 days)', 'wellweb-notify' ),
        'value' => sprintf( '%d total, %d success, %d errors', $stats['total'], $stats['success'], $stats['error'] ),
    );

    $debug_info['wellweb-notify'] = array(
        'label'  => __( 'Well Web Notify', 'wellweb-notify' ),
        'fields' => $fields,
    );

    return $debug_info;
}

// Add Site Health tests
add_filter( 'site_status_tests', 'wellweb_notify_site_health_tests' );
function wellweb_notify_site_health_tests( $tests ) {
    $tests['direct']['wellweb_notify_channels'] = array(
        'label' => __( 'Well Web Notify channels', 'wellweb-notify' ),
        'test'  => 'wellweb_notify_test_channels',
    );

    return $tests;
}

function wellweb_notify_test_channels() {
    $manager = WellWeb_Notify_Channel_Manager::instance();
    $active  = $manager->get_active_channels();
    $stats   = WellWeb_Notify_Log::get_stats( 7 );

    $result = array(
        'label'       => __( 'Well Web Notify is configured', 'wellweb-notify' ),
        'status'      => 'good',
        'badge'       => array(
            'label' => __( 'Notifications', 'wellweb-notify' ),
            'color' => 'blue',
        ),
        'description' => '',
        'actions'     => sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=wellweb-notify-settings' ) ),
            __( 'Manage channels', 'wellweb-notify' )
        ),
        'test'        => 'wellweb_notify_channels',
    );

    if ( empty( $active ) ) {
        $result['status']      = 'recommended';
        $result['label']       = __( 'No notification channels are active', 'wellweb-notify' );
        $result['description'] = __( 'Well Web Notify has no active channels. Enable and configure at least one channel to receive form notifications.', 'wellweb-notify' );
        $result['badge']['color'] = 'orange';
    } elseif ( $stats['error'] > 0 && $stats['error'] > $stats['success'] ) {
        $result['status']      = 'recommended';
        $result['label']       = __( 'Well Web Notify has delivery errors', 'wellweb-notify' );
        $result['description'] = sprintf(
            /* translators: %d: number of errors */
            __( '%d errors in the last 7 days. Check the notification log for details.', 'wellweb-notify' ),
            $stats['error']
        );
        $result['badge']['color'] = 'orange';
    } else {
        $result['description'] = sprintf(
            /* translators: %1$d: number of active channels, %2$d: number of notifications */
            __( '%1$d active channel(s). %2$d notifications sent in the last 7 days.', 'wellweb-notify' ),
            count( $active ),
            $stats['total']
        );
    }

    return $result;
}

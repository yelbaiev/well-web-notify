<?php
/**
 * WordPress Dashboard Widget for Well Web Notify
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_dashboard_setup', 'wellweb_notify_dashboard_widget' );
function wellweb_notify_dashboard_widget() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    wp_add_dashboard_widget(
        'wellweb_notify_dashboard',
        __( 'Well Web Notify', 'well-web-notify' ),
        'wellweb_notify_dashboard_widget_content'
    );
}

function wellweb_notify_dashboard_widget_content() {
    $manager  = WellWeb_Notify_Channel_Manager::instance();
    $channels = $manager->get_channels();
    $active   = $manager->get_active_channels();
    $stats    = WellWeb_Notify_Log::get_stats( 7 );

    ?>
    <div class="ww-notify-widget">
        <!-- Stats -->
        <div class="ww-notify-widget-stats">
            <div class="ww-notify-widget-stat --total">
                <div class="ww-notify-widget-stat-value"><?php echo (int) $stats['total']; ?></div>
                <div class="ww-notify-widget-stat-label"><?php esc_html_e( 'Sent (7d)', 'well-web-notify' ); ?></div>
            </div>
            <div class="ww-notify-widget-stat --success">
                <div class="ww-notify-widget-stat-value"><?php echo (int) $stats['success']; ?></div>
                <div class="ww-notify-widget-stat-label"><?php esc_html_e( 'Success', 'well-web-notify' ); ?></div>
            </div>
            <div class="ww-notify-widget-stat --error <?php echo $stats['error'] > 0 ? '--has-errors' : ''; ?>">
                <div class="ww-notify-widget-stat-value"><?php echo (int) $stats['error']; ?></div>
                <div class="ww-notify-widget-stat-label"><?php esc_html_e( 'Errors', 'well-web-notify' ); ?></div>
            </div>
        </div>

        <!-- Channel status -->
        <h4><?php esc_html_e( 'Channels', 'well-web-notify' ); ?></h4>
        <ul class="ww-notify-widget-channels">
            <?php foreach ( $channels as $ch ) :
                $is_active = $ch->is_enabled() && $ch->is_configured();
            ?>
            <li>
                <span class="dashicons <?php echo $is_active ? 'dashicons-yes-alt --active' : 'dashicons-minus --inactive'; ?>"></span>
                <span><?php echo esc_html( $ch->get_label() ); ?></span>
            </li>
            <?php endforeach; ?>
        </ul>

        <!-- Links -->
        <p class="ww-notify-widget-links">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=well-web-notify-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'well-web-notify' ); ?></a>
            |
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=well-web-notify-log' ) ); ?>"><?php esc_html_e( 'View Log', 'well-web-notify' ); ?></a>
        </p>
    </div>
    <?php
}

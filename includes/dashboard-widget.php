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
        __( 'Well Web Notify', 'wellweb-notify' ),
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
        <div style="display: flex; gap: 15px; margin-bottom: 15px;">
            <div style="flex: 1; text-align: center; padding: 10px; background: #f0f6fc; border-radius: 4px;">
                <div style="font-size: 24px; font-weight: 600; color: #2271b1;"><?php echo (int) $stats['total']; ?></div>
                <div style="font-size: 12px; color: #646970;"><?php esc_html_e( 'Sent (7d)', 'wellweb-notify' ); ?></div>
            </div>
            <div style="flex: 1; text-align: center; padding: 10px; background: #edfaef; border-radius: 4px;">
                <div style="font-size: 24px; font-weight: 600; color: #00a32a;"><?php echo (int) $stats['success']; ?></div>
                <div style="font-size: 12px; color: #646970;"><?php esc_html_e( 'Success', 'wellweb-notify' ); ?></div>
            </div>
            <div style="flex: 1; text-align: center; padding: 10px; background: <?php echo $stats['error'] > 0 ? '#fcf0f1' : '#f6f7f7'; ?>; border-radius: 4px;">
                <div style="font-size: 24px; font-weight: 600; color: <?php echo $stats['error'] > 0 ? '#d63638' : '#646970'; ?>;"><?php echo (int) $stats['error']; ?></div>
                <div style="font-size: 12px; color: #646970;"><?php esc_html_e( 'Errors', 'wellweb-notify' ); ?></div>
            </div>
        </div>

        <!-- Channel status -->
        <h4 style="margin: 0 0 8px;"><?php esc_html_e( 'Channels', 'wellweb-notify' ); ?></h4>
        <ul style="margin: 0; padding: 0; list-style: none;">
            <?php foreach ( $channels as $ch ) :
                $is_active = $ch->is_enabled() && $ch->is_configured();
            ?>
            <li style="display: flex; align-items: center; gap: 6px; padding: 4px 0;">
                <span class="dashicons <?php echo $is_active ? 'dashicons-yes-alt' : 'dashicons-minus'; ?>"
                      style="color: <?php echo $is_active ? '#00a32a' : '#c3c4c7'; ?>; font-size: 16px; width: 16px; height: 16px;"></span>
                <span><?php echo esc_html( $ch->get_label() ); ?></span>
            </li>
            <?php endforeach; ?>
        </ul>

        <!-- Links -->
        <p style="margin: 12px 0 0; padding-top: 10px; border-top: 1px solid #f0f0f1;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wellweb-notify-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'wellweb-notify' ); ?></a>
            |
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wellweb-notify-log' ) ); ?>"><?php esc_html_e( 'View Log', 'wellweb-notify' ); ?></a>
        </p>
    </div>
    <?php
}

<?php
/**
 * The WordPress dashboard widget.
 *
 * Answers one question — is anything broken — and gets out of the way. It does
 * not try to be the Channels screen in miniature.
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
    $stats    = WellWeb_Notify_Log::get_stats( 7 );
    ?>
    <div class="wellweb-notify-widget">
        <div class="wellweb-notify-widget-stats">
            <div class="wellweb-notify-widget-stat">
                <div class="wellweb-notify-widget-stat-value"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></div>
                <div class="wellweb-notify-widget-stat-label"><?php esc_html_e( 'Sent, 7 days', 'well-web-notify' ); ?></div>
            </div>
            <div class="wellweb-notify-widget-stat">
                <div class="wellweb-notify-widget-stat-value"><?php echo esc_html( number_format_i18n( $stats['success'] ) ); ?></div>
                <div class="wellweb-notify-widget-stat-label"><?php esc_html_e( 'Delivered', 'well-web-notify' ); ?></div>
            </div>
            <div class="wellweb-notify-widget-stat <?php echo $stats['error'] > 0 ? 'is-bad' : ''; ?>">
                <div class="wellweb-notify-widget-stat-value"><?php echo esc_html( number_format_i18n( $stats['error'] ) ); ?></div>
                <div class="wellweb-notify-widget-stat-label"><?php esc_html_e( 'Failed', 'well-web-notify' ); ?></div>
            </div>
        </div>

        <ul class="wellweb-notify-widget-channels">
            <?php foreach ( $channels as $ch ) :
                $live = $ch->is_enabled() && $ch->is_configured();
                ?>
                <li>
                    <span><?php echo esc_html( $ch->get_label() ); ?></span>
                    <span class="wellweb-notify-widget-state <?php echo $live ? 'is-on' : ''; ?>">
                        <?php
                        if ( $live ) {
                            esc_html_e( 'Delivering', 'well-web-notify' );
                        } elseif ( $ch->is_configured() ) {
                            esc_html_e( 'Switched off', 'well-web-notify' );
                        } else {
                            esc_html_e( 'Not connected', 'well-web-notify' );
                        }
                        ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>

        <p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . WELLWEB_NOTIFY_PAGE ) ); ?>#channels"><?php esc_html_e( 'Channels', 'well-web-notify' ); ?></a>
            &nbsp;·&nbsp;
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . WELLWEB_NOTIFY_PAGE_ACTIVITY ) ); ?>"><?php esc_html_e( 'Activity', 'well-web-notify' ); ?></a>
        </p>
    </div>
    <?php
}

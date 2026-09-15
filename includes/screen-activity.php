<?php
/**
 * Activity — did it work?
 *
 * ── A page, not a section ───────────────────────────────────────────────────
 *
 * It was briefly a fourth section of the settings screen, and that was wrong
 * for one concrete reason: its filters and its pager are GET navigations that
 * reload the page. On a settings screen that means quietly throwing away
 * whatever the person had half-typed into a credentials field. Data that you
 * page through does not belong inside a form you save.
 *
 * The settings screen keeps one pointer here — a strip that appears only when
 * something has actually failed — so a site whose notifications have started
 * bouncing still says so on the screen people open.
 *
 * ── Empty is a state, not a table with no rows ──────────────────────────────
 *
 * An earlier version always rendered the same three things: a panel of counters
 * reading 0 / 0 / 0, a filter bar, and a table whose only row said nothing had
 * been sent — and that row sat right-aligned, because WPDS aligns a list's last
 * cell to the end and a `colspan` cell is both first and last. Three components
 * arguing about how to say "nothing here yet".
 *
 * ── No nonce on the filters ─────────────────────────────────────────────────
 *
 * There used to be one, and the effect was that arriving here from anywhere
 * except this form silently ignored every filter in the URL — including the
 * pagination links the screen built for itself. A read-only filter on a page
 * already behind `manage_options` is not a state change, so it is sanitised
 * rather than nonced, which is what core's own list tables do.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wellweb_notify_render_activity_screen(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'well-web-notify' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters on an admin-only screen.
    $filter_channel = isset( $_GET['channel'] ) ? sanitize_key( wp_unslash( $_GET['channel'] ) ) : '';
    $filter_status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
    $paged          = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( ! in_array( $filter_status, array( 'success', 'error' ), true ) ) {
        $filter_status = '';
    }

    $manager  = WellWeb_Notify_Channel_Manager::instance();
    $channels = $manager->get_channels();

    if ( $filter_channel !== '' && ! isset( $channels[ $filter_channel ] ) ) {
        $filter_channel = '';
    }

    $filtered = $filter_channel !== '' || $filter_status !== '';

    $log   = WellWeb_Notify_Log::get_entries( array(
        'channel'  => $filter_channel,
        'status'   => $filter_status,
        'page'     => $paged,
        'per_page' => 20,
    ) );
    $stats = WellWeb_Notify_Log::get_stats( 7 );

    wellweb_notify_page_open( __( 'Activity', 'well-web-notify' ), array(
        'meta' => esc_html__( 'Every send attempt, kept for 30 days.', 'well-web-notify' ),
    ) );

    // "Is this log empty" is not "did this query match" — a filter that matches
    // nothing still belongs on a screen that has rows.
    if ( $log['total'] === 0 && ! $filtered && $stats['total'] === 0 ) {
        ?>
        <section class="wpds-panel">
            <h2 class="wpds-panel__title"><?php esc_html_e( 'Nothing has been sent yet', 'well-web-notify' ); ?></h2>
            <p class="wpds-panel__intro wellweb-notify-empty">
                <?php esc_html_e( 'Once a channel is connected and switched on, every attempt to deliver a form submission or an order shows up here — including the ones that fail, with the reason.', 'well-web-notify' ); ?>
            </p>
            <a class="wpds-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=' . WELLWEB_NOTIFY_PAGE ) ); ?>#channels">
                <?php esc_html_e( 'Connect a channel', 'well-web-notify' ); ?>
            </a>
        </section>
        <?php
        wellweb_notify_page_close();
        return;
    }
    ?>

    <section class="wpds-panel">
    <div class="wellweb-notify-stats">
        <div class="wellweb-notify-stat">
            <span class="wellweb-notify-stat__value"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></span>
            <span class="wellweb-notify-stat__label"><?php esc_html_e( 'attempted', 'well-web-notify' ); ?></span>
        </div>
        <div class="wellweb-notify-stat is-ok">
            <span class="wellweb-notify-stat__value"><?php echo esc_html( number_format_i18n( $stats['success'] ) ); ?></span>
            <span class="wellweb-notify-stat__label"><?php esc_html_e( 'delivered', 'well-web-notify' ); ?></span>
        </div>
        <div class="wellweb-notify-stat <?php echo $stats['error'] > 0 ? 'is-bad' : ''; ?>">
            <span class="wellweb-notify-stat__value"><?php echo esc_html( number_format_i18n( $stats['error'] ) ); ?></span>
            <span class="wellweb-notify-stat__label"><?php esc_html_e( 'failed', 'well-web-notify' ); ?></span>
        </div>
        <span class="wellweb-notify-stats__period"><?php esc_html_e( 'in the last 7 days', 'well-web-notify' ); ?></span>
    </div>

    <div class="wellweb-notify-list">
        <form class="wellweb-notify-list__toolbar" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
            <input type="hidden" name="page" value="<?php echo esc_attr( WELLWEB_NOTIFY_PAGE_ACTIVITY ); ?>" />

            <label class="screen-reader-text" for="ww-log-channel"><?php esc_html_e( 'Channel', 'well-web-notify' ); ?></label>
            <select name="channel" id="ww-log-channel" class="wpds-select">
                <option value=""><?php esc_html_e( 'All channels', 'well-web-notify' ); ?></option>
                <?php foreach ( $channels as $ch ) : ?>
                    <option value="<?php echo esc_attr( $ch->get_slug() ); ?>" <?php selected( $filter_channel, $ch->get_slug() ); ?>><?php echo esc_html( $ch->get_label() ); ?></option>
                <?php endforeach; ?>
            </select>

            <label class="screen-reader-text" for="ww-log-status"><?php esc_html_e( 'Status', 'well-web-notify' ); ?></label>
            <select name="status" id="ww-log-status" class="wpds-select">
                <option value=""><?php esc_html_e( 'Delivered and failed', 'well-web-notify' ); ?></option>
                <option value="success" <?php selected( $filter_status, 'success' ); ?>><?php esc_html_e( 'Delivered only', 'well-web-notify' ); ?></option>
                <option value="error" <?php selected( $filter_status, 'error' ); ?>><?php esc_html_e( 'Failed only', 'well-web-notify' ); ?></option>
            </select>

            <button type="submit" class="wpds-btn wpds-btn--ghost wpds-btn--sm"><?php esc_html_e( 'Filter', 'well-web-notify' ); ?></button>

            <?php if ( $filtered ) : ?>
                <a class="wellweb-notify-clear" href="<?php echo esc_url( admin_url( 'admin.php?page=' . WELLWEB_NOTIFY_PAGE_ACTIVITY ) ); ?>">
                    <?php esc_html_e( 'Clear', 'well-web-notify' ); ?>
                </a>
            <?php endif; ?>
        </form>

        <?php if ( empty( $log['items'] ) ) : ?>
            <p class="wellweb-notify-noresults"><?php esc_html_e( 'Nothing matches those filters.', 'well-web-notify' ); ?></p>
        <?php else : ?>
            <table>
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Channel', 'well-web-notify' ); ?></th>
                        <th><?php esc_html_e( 'Source', 'well-web-notify' ); ?></th>
                        <th><?php esc_html_e( 'Result', 'well-web-notify' ); ?></th>
                        <th><?php esc_html_e( 'When', 'well-web-notify' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $log['items'] as $entry ) :
                    $ch      = $manager->get_channel( $entry->channel );
                    $is_ok   = $entry->status === 'success';
                    $sent_at = strtotime( $entry->created_at . ' UTC' );
                    ?>
                    <tr>
                        <td class="wellweb-notify-list__primary"><?php echo esc_html( $ch ? $ch->get_label() : $entry->channel ); ?></td>
                        <td><?php echo esc_html( $entry->form_name ); ?></td>
                        <td>
                            <?php if ( $is_ok ) : ?>
                                <span class="wpds-badge wpds-badge--success"><?php esc_html_e( 'Delivered', 'well-web-notify' ); ?></span>
                            <?php else : ?>
                                <span class="wpds-badge wpds-badge--error"><?php esc_html_e( 'Failed', 'well-web-notify' ); ?></span>
                                <?php if ( $entry->error_message !== '' ) : ?>
                                    <span class="wellweb-notify-list__secondary"><?php echo esc_html( $entry->error_message ); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="wellweb-notify-list__secondary">
                            <span title="<?php echo esc_attr( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sent_at ) ); ?>">
                                <?php
                                echo esc_html( sprintf(
                                    /* translators: %s: human-readable time difference, e.g. "3 hours" */
                                    __( '%s ago', 'well-web-notify' ),
                                    human_time_diff( $sent_at )
                                ) );
                                ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ( $log['pages'] > 1 ) : ?>
            <div class="wellweb-notify-list__foot">
                <?php
                $base = add_query_arg( array(
                    'page'    => WELLWEB_NOTIFY_PAGE_ACTIVITY,
                    'channel' => $filter_channel,
                    'status'  => $filter_status,
                    'paged'   => '%#%',
                ), admin_url( 'admin.php' ) );

                echo wp_kses_post( paginate_links( array(
                    'base'    => $base,
                    'format'  => '',
                    'current' => (int) $log['page'],
                    'total'   => (int) $log['pages'],
                ) ) );
                ?>
            </div>
        <?php endif; ?>
    </div>
    </section>

    <?php
    wellweb_notify_page_close();
}

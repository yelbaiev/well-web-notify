<?php
/**
 * Monitoring — whether this is the live site, and how it checks itself.
 *
 * Three settings, one status line:
 *
 *  - Production domain. The daily check and the weekly summary only run on the
 *    domain recorded here, so a staging copy made from the live database stays
 *    quiet. Filled in automatically on the live site; see
 *    wellweb_notify_is_production() for the full rule.
 *  - Alert email. Where a failed check is reported. Empty means the site admin
 *    email, which on a client's site is often the client — hence the field.
 *  - Weekly summary. The only monitoring message posted to the chat itself.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wellweb_notify_section_monitoring(): void {
    $env         = wellweb_notify_environment();
    $alert       = (string) wellweb_notify_get_option( 'alert-email', '' );
    $summary_on  = (bool) get_option( WellWeb_Notify_Health_Check::OPTION_SUMMARY_ON, false );
    $results     = WellWeb_Notify_Health_Check::get_results();
    $last_run    = WellWeb_Notify_Health_Check::get_last_run();
    ?>
    <p class="wpds-panel__intro">
        <?php esc_html_e( 'Once a day the plugin checks that each channel can still deliver, without posting anything to the chat. If one cannot, you get an email.', 'well-web-notify' ); ?>
    </p>

    <div class="wpds-notice <?php echo $env['production'] ? '' : 'wpds-notice--warn'; ?> wellweb-notify-env">
        <div>
            <span class="wpds-notice__title">
                <?php
                echo $env['production']
                    /* translators: %s: domain */
                    ? esc_html( sprintf( __( 'Live site: %s', 'well-web-notify' ), $env['current'] ) )
                    /* translators: %s: domain */
                    : esc_html( sprintf( __( 'Staging copy: %s', 'well-web-notify' ), $env['current'] ) );
                ?>
            </span>
            <div>
                <?php
                switch ( $env['reason'] ) {
                    case 'environment':
                        printf(
                            /* translators: %s: environment type, e.g. "staging" */
                            esc_html__( 'WordPress reports this environment as "%s". Daily checks and the weekly summary are off here. Form notifications are still sent, marked [Staging].', 'well-web-notify' ),
                            esc_html( $env['type'] )
                        );
                        break;
                    case 'domain-mismatch':
                        printf(
                            /* translators: %s: production domain */
                            esc_html__( 'The live site is %s. Daily checks and the weekly summary only run there. Form notifications are still sent from here, marked [Staging].', 'well-web-notify' ),
                            esc_html( $env['recorded'] )
                        );
                        break;
                    case 'host-pattern':
                        esc_html_e( 'This domain looks like a staging or development address, and no live domain is recorded yet. Daily checks and the weekly summary are off. Form notifications are still sent, marked [Staging].', 'well-web-notify' );
                        break;
                    default:
                        esc_html_e( 'Daily checks and the weekly summary run from this domain. A staging copy on any other domain stays quiet, but its form notifications are still sent, marked [Staging].', 'well-web-notify' );
                }
                ?>
            </div>
        </div>
    </div>

    <?php
    wellweb_notify_render_fields( array(
        array(
            'name'        => 'well-web-notify-production-domain',
            'label'       => __( 'Live domain', 'well-web-notify' ),
            'type'        => 'text',
            'value'       => $env['recorded'],
            'placeholder' => 'example.com',
            'hint'        => __( 'Filled in automatically the first time you open the admin on the live site. Change it only if the live site moves to a new domain.', 'well-web-notify' ),
        ),
        array(
            'name'        => 'well-web-notify-alert-email',
            'label'       => __( 'Alert email', 'well-web-notify' ),
            'type'        => 'email',
            'value'       => $alert,
            'placeholder' => (string) get_option( 'admin_email', '' ),
            'hint'        => __( 'Who hears about a broken connection. Leave empty to use the site admin email.', 'well-web-notify' ),
        ),
    ) );
    ?>

    <div class="wellweb-notify-subhead wellweb-notify-summary">
        <div>
            <h3 class="wellweb-notify-subhead__title"><?php esc_html_e( 'Weekly summary in the chat', 'well-web-notify' ); ?></h3>
            <p class="wellweb-notify-subhead__intro">
                <?php esc_html_e( 'Every Monday morning, one short message in each connected chat saying how many notifications arrived that week. Telegram and Discord deliver it without a notification sound. Only sent from the live site.', 'well-web-notify' ); ?>
            </p>
        </div>
        <label class="wpds-toggle">
            <input type="checkbox" name="<?php echo esc_attr( WellWeb_Notify_Health_Check::OPTION_SUMMARY_ON ); ?>" value="1" <?php checked( $summary_on ); ?> />
            <span class="wpds-toggle__track"></span>
            <span class="screen-reader-text"><?php esc_html_e( 'Send a weekly summary', 'well-web-notify' ); ?></span>
        </label>
    </div>

    <?php if ( $last_run !== '' && $results ) : ?>
        <ul class="wellweb-notify-sources wellweb-notify-checks">
            <?php
            $manager = WellWeb_Notify_Channel_Manager::instance();
            foreach ( $results as $slug => $result ) :
                if ( ( $result['status'] ?? '' ) === 'skipped' && ( $result['message'] ?? '' ) === 'Not enabled or not configured' ) {
                    continue;
                }
                $channel = $manager->get_channel( $slug );
                $ok      = ( $result['status'] ?? '' ) === 'ok';
                ?>
                <li class="wellweb-notify-sources__item <?php echo $ok ? 'is-on' : ''; ?>">
                    <span class="wellweb-notify-sources__dot" aria-hidden="true"></span>
                    <span class="wellweb-notify-sources__name"><?php echo esc_html( $channel ? $channel->get_label() : $slug ); ?></span>
                    <span class="wellweb-notify-sources__state">
                        <?php
                        if ( $ok ) {
                            esc_html_e( 'Connected', 'well-web-notify' );
                        } elseif ( ( $result['status'] ?? '' ) === 'error' ) {
                            echo esc_html( $result['message'] ?? '' );
                        } else {
                            esc_html_e( 'Not checked', 'well-web-notify' );
                        }
                        ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="wpds-fields__hint">
            <?php
            printf(
                /* translators: %s: date and time */
                esc_html__( 'Last checked %s.', 'well-web-notify' ),
                esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_run ) )
            );
            ?>
        </p>
    <?php endif; ?>
    <?php
}

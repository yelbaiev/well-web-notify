<?php
/**
 * Sources — what sets a notification off.
 *
 * ── What an earlier version got wrong ───────────────────────────────────────
 *
 * It listed all six supported form plugins as equal rows, each 60px tall, and
 * on a normal site five of them say "Not installed on this site." That is a
 * section whose largest element is a list of software you do not have.
 *
 * What the reader needs is: what IS being watched (usually one thing), and —
 * much smaller — what else would be if it were installed. So the installed
 * ones are rows and the rest is one sentence.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wellweb_notify_section_sources(): void {
    // WooCommerce is registered as a "form" so it can share the dispatch path,
    // but it is an order source with its own block below — listing it here too
    // is what the old screen did, and it showed up twice.
    $forms = array_diff_key(
        WellWeb_Notify_Form_Manager::instance()->get_forms(),
        array( 'woocommerce' => null )
    );

    $watching = array_filter( $forms, static function ( $f ) {
        return $f->is_available();
    } );
    $dormant  = array_diff_key( $forms, $watching );
    ?>

    <?php if ( $watching ) : ?>
        <p class="wpds-panel__intro">
            <?php esc_html_e( 'Every form in these plugins is watched. There is nothing to set up per form.', 'well-web-notify' ); ?>
        </p>

        <ul class="wellweb-notify-sources">
            <?php foreach ( $watching as $form ) : ?>
                <li class="wellweb-notify-sources__item is-on">
                    <span class="wellweb-notify-sources__dot" aria-hidden="true"></span>
                    <span class="wellweb-notify-sources__name"><?php echo esc_html( $form->get_label() ); ?></span>
                    <span class="wellweb-notify-sources__state"><?php esc_html_e( 'Watching', 'well-web-notify' ); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else : ?>
        <div class="wpds-notice wpds-notice--warn">
            <div>
                <span class="wpds-notice__title"><?php esc_html_e( 'No form plugin is installed', 'well-web-notify' ); ?></span>
                <div><?php esc_html_e( 'Nothing will be sent until one of the plugins below is active on this site.', 'well-web-notify' ); ?></div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ( $dormant ) : ?>
        <p class="wellweb-notify-sources__also">
            <?php
            $names = array();
            foreach ( $dormant as $form ) {
                $names[] = $form->get_label();
            }
            printf(
                /* translators: %s: comma-separated list of form plugin names */
                esc_html__( 'Also supported, and picked up automatically if you install one: %s.', 'well-web-notify' ),
                esc_html( implode( ', ', $names ) )
            );
            ?>
        </p>
    <?php endif; ?>

    <?php
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    $woo_enabled = (bool) get_option( 'well-web-notify-woo-enabled', false );
    ?>
    <div class="wellweb-notify-woo <?php echo $woo_enabled ? 'is-on' : ''; ?>">
        <div class="wellweb-notify-subhead">
            <div>
                <h3 class="wellweb-notify-subhead__title"><?php esc_html_e( 'WooCommerce orders', 'well-web-notify' ); ?></h3>
                <p class="wellweb-notify-subhead__intro">
                    <?php esc_html_e( 'The customer, their contact details, the items, the total, the payment method and the billing city — plus a link straight to the order.', 'well-web-notify' ); ?>
                </p>
            </div>
            <label class="wpds-toggle">
                <input type="checkbox" name="well-web-notify-woo-enabled" value="1" <?php checked( $woo_enabled ); ?> />
                <span class="wpds-toggle__track"></span>
                <span class="screen-reader-text"><?php esc_html_e( 'Notify about orders', 'well-web-notify' ); ?></span>
            </label>
        </div>

        <fieldset class="wellweb-notify-events">
            <legend><?php esc_html_e( 'Send a notification when an order is…', 'well-web-notify' ); ?></legend>
            <div class="wellweb-notify-events__grid">
                <?php foreach ( wellweb_notify_woo_events() as $event => $label ) :
                    $option = 'well-web-notify-woo-event-' . $event;
                    ?>
                    <label class="wpds-check">
                        <input type="checkbox" name="<?php echo esc_attr( $option ); ?>" value="1"
                               <?php checked( get_option( $option, false ) ); ?> />
                        <span><?php echo esc_html( $label ); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
    </div>
    <?php
}

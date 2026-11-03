<?php
/**
 * The settings screen.
 *
 * Three sections down one page, with a sticky index beside them — the pattern
 * Well Web SEO uses for its own Settings screen, and for the same reason: this
 * is the stuff you set once, not the stuff you work in.
 *
 * Activity is not one of them. It briefly was, and it did not belong: it is
 * data rather than settings, it is the only thing here with filters and paging,
 * and those are navigations that reload the page — which on a settings screen
 * means quietly discarding whatever you had half-typed. It has a screen of its
 * own in screen-activity.php.
 *
 * ── One form, one settings group ────────────────────────────────────────────
 *
 * All three sections share ONE `<form>` posting to `options.php`, so there is
 * one Save button and one settings group. That is also what removed a
 * workaround: `options.php` walks every option registered to the submitted
 * group and hands `null` to the ones absent from the request — and an unchecked
 * checkbox is absent. Across two screens sharing a group, saving one switched
 * off everything on the other, which is why an earlier version needed two
 * groups. With one form, nothing is absent.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wellweb_notify_render_screen(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'well-web-notify' ) );
    }

    $manager  = WellWeb_Notify_Channel_Manager::instance();
    $channels = $manager->get_channels();
    $live     = $manager->get_active_channels();
    $sections = wellweb_notify_sections();

    $meta = esc_html( wellweb_notify_site_domain() ) . ' · ' . esc_html( sprintf(
        /* translators: 1: number of channels delivering, 2: number of channels available */
        _n( '%1$d of %2$d channel delivering', '%1$d of %2$d channels delivering', count( $channels ), 'well-web-notify' ),
        count( $live ),
        count( $channels )
    ) );

    if ( ! wellweb_notify_is_production() ) {
        $meta .= ' · ' . esc_html__( 'Staging copy', 'well-web-notify' );
    }

    wellweb_notify_page_open( __( 'Well Web Notify', 'well-web-notify' ), array( 'meta' => $meta ) );
    ?>

    <div class="wpds-settings wellweb-notify-screen">
        <nav class="wpds-subnav" aria-label="<?php esc_attr_e( 'Sections', 'well-web-notify' ); ?>">
            <?php foreach ( $sections as $id => $section ) : ?>
                <a href="#<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $section['label'] ); ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="wellweb-notify-body">
            <?php wellweb_notify_render_status_strip(); ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" id="well-web-notify-form">
                <?php settings_fields( WELLWEB_NOTIFY_SETTINGS_GROUP ); ?>

                <?php
                foreach ( $sections as $id => $section ) {
                    wellweb_notify_render_section( $id, $section );
                }
                ?>

                <div class="wellweb-notify-savebar">
                    <button type="submit" class="wpds-btn"><?php esc_html_e( 'Save changes', 'well-web-notify' ); ?></button>
                    <span class="wpds-muted">
                        <?php esc_html_e( 'Credentials are stored encrypted and never shown again after saving.', 'well-web-notify' ); ?>
                    </span>
                </div>
            </form>

            <?php wellweb_notify_render_family(); ?>
        </div>
    </div>

    <?php
    wellweb_notify_page_close();
}

/**
 * One section: an anchor, a heading, and whatever its renderer draws.
 *
 * The anchor is on the `<section>` itself with `scroll-margin-top`, rather than
 * an empty `<a id>` above it — an empty anchor element counts as a first child
 * and takes the first-child styling the heading was supposed to get.
 *
 * @param string $id
 * @param array  $section
 */
function wellweb_notify_render_section( string $id, array $section ): void {
    ?>
    <section class="wpds-panel wellweb-notify-section" id="<?php echo esc_attr( $id ); ?>">
        <h2 class="wpds-panel__title"><?php echo esc_html( $section['label'] ); ?></h2>
        <?php call_user_func( $section['render'] ); ?>
    </section>
    <?php
}

/**
 * The one line at the top that says whether this plugin is doing anything.
 *
 * Only states that need action are shown. A permanent "all good" banner is a
 * congratulation nobody needs twice, and it pushes the thing you came to change
 * below the fold.
 *
 * The failure case is here because Activity moved to its own screen: without
 * it, a site whose notifications had started bouncing would say nothing at all
 * on the screen people actually open.
 */
function wellweb_notify_render_status_strip(): void {
    $manager    = WellWeb_Notify_Channel_Manager::instance();
    $configured = array_filter( $manager->get_channels(), static function ( $c ) {
        return $c->is_configured();
    } );

    if ( ! $configured ) {
        ?>
        <div class="wpds-strip">
            <div>
                <div class="wpds-strip__title"><?php esc_html_e( 'No channel is connected yet', 'well-web-notify' ); ?></div>
                <div class="wpds-muted">
                    <?php esc_html_e( 'Open one below and paste its details. Telegram is the quickest — a bot takes about a minute to create.', 'well-web-notify' ); ?>
                </div>
            </div>
        </div>
        <?php
        return;
    }

    if ( ! $manager->get_active_channels() ) {
        ?>
        <div class="wpds-strip wpds-strip--warn">
            <div>
                <div class="wpds-strip__title"><?php esc_html_e( 'Nothing is being delivered', 'well-web-notify' ); ?></div>
                <div class="wpds-muted">
                    <?php esc_html_e( 'A channel is set up but its switch is off, so submissions are going nowhere.', 'well-web-notify' ); ?>
                </div>
            </div>
        </div>
        <?php
        return;
    }

    $stats = WellWeb_Notify_Log::get_stats( 7 );
    if ( $stats['error'] < 1 ) {
        return;
    }
    ?>
    <div class="wpds-strip wpds-strip--warn">
        <div>
            <div class="wpds-strip__title">
                <?php
                printf(
                    /* translators: %s: number of failed notifications */
                    esc_html( _n( '%s notification failed in the last 7 days', '%s notifications failed in the last 7 days', $stats['error'], 'well-web-notify' ) ),
                    esc_html( number_format_i18n( $stats['error'] ) )
                );
                ?>
            </div>
            <div class="wpds-muted"><?php esc_html_e( 'The chat service gave a reason for each one.', 'well-web-notify' ); ?></div>
        </div>
        <a class="wpds-btn wpds-btn--secondary wpds-btn--sm"
           href="<?php echo esc_url( add_query_arg( array( 'page' => WELLWEB_NOTIFY_PAGE_ACTIVITY, 'status' => 'error' ), admin_url( 'admin.php' ) ) ); ?>">
            <?php esc_html_e( 'Show them', 'well-web-notify' ); ?>
        </a>
    </div>
    <?php
}

/**
 * The other two Well Web plugins.
 *
 * At the foot, below a hairline, outside every form: a pointer to the sibling
 * plugins, which is how somebody running one of them finds out the others
 * exist. A plugin that is already active says so and links into itself instead
 * of offering a mailto, so the block stops selling as soon as there is nothing
 * left to sell.
 */
function wellweb_notify_render_family(): void {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $family = array(
        array(
            'name'    => 'Well Web SEO',
            'icon'    => 'dashicons-search',
            'blurb'   => __( 'Meta titles and descriptions, JSON-LD schema with validation, sitemaps, redirects, and Search Console verdicts.', 'well-web-notify' ),
            'active'  => is_plugin_active( 'wellweb-seo/index.php' ),
            'page'    => 'wellweb-seo',
            'subject' => 'Interested in Well Web SEO',
        ),
        array(
            'name'    => 'Well Web Multilang',
            'icon'    => 'dashicons-translation',
            'blurb'   => __( 'Multilingual content with a language switcher, hreflang tags, and per-language SEO fields.', 'well-web-notify' ),
            'active'  => is_plugin_active( 'wellweb-multilang/index.php' ),
            'page'    => 'wellweb-multilang',
            'subject' => 'Interested in Well Web Multilang',
        ),
    );
    ?>
    <section class="wellweb-notify-family">
        <h2 class="wellweb-notify-family__title"><?php esc_html_e( 'More from Well Web', 'well-web-notify' ); ?></h2>

        <div class="wellweb-notify-family__grid">
            <?php foreach ( $family as $item ) : ?>
                <div class="wellweb-notify-family__card">
                    <div class="wellweb-notify-family__head">
                        <span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
                        <span class="wellweb-notify-family__name"><?php echo esc_html( $item['name'] ); ?></span>
                        <?php if ( $item['active'] ) : ?>
                            <span class="wpds-badge wpds-badge--success"><?php esc_html_e( 'Installed', 'well-web-notify' ); ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="wellweb-notify-family__blurb"><?php echo esc_html( $item['blurb'] ); ?></p>
                    <?php if ( $item['active'] ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $item['page'] ) ); ?>">
                            <?php esc_html_e( 'Open it', 'well-web-notify' ); ?> &rarr;
                        </a>
                    <?php else : ?>
                        <a href="mailto:support@wellweb.marketing?subject=<?php echo rawurlencode( $item['subject'] ); ?>">
                            <?php esc_html_e( 'Ask us about it', 'well-web-notify' ); ?> &rarr;
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}

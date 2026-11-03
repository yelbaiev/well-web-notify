<?php
/**
 * Admin navigation, the page shell, and the design system.
 *
 * ── One item, one screen ────────────────────────────────────────────────────
 *
 * This plugin briefly had three sidebar rows — Channels, Sources, Log — copied
 * from Well Web SEO's shape. SEO has eighteen screens across six sections and
 * earns that; this plugin has four channels, a list of form plugins, six order
 * checkboxes, one country code and a log. That is one screen, and splitting it
 * cost a real workaround: two settings groups, because two forms sharing one
 * group null each other's checkboxes.
 *
 * So: the settings are one screen — Channels, Sources and Message as sections
 * with a sticky index beside them. Activity is the exception and has a page of
 * its own: it is the only thing here that is data rather than settings, and the
 * only thing with filters and paging, which do not belong in a form you save.
 * Two sidebar rows, not four. See docs/plans/v1.0.6-one-screen.md.
 *
 * Before that it was a submenu of a shared "Well Web" top-level item, which
 * this plugin created if no sibling had. That stopped working when Well Web
 * SEO took its own top-level menu: the shared parent was left holding one
 * child, and the two plugins read as one plugin with a missing half.
 *
 * ── The slug does not move ──────────────────────────────────────────────────
 *
 * `well-web-notify-settings` is still the settings page and `well-web-notify-log`
 * is still the log, which are the two slugs this plugin has had since 1.0.0 —
 * so the dashboard widget, the plugin-row action, the Site Health result, the
 * review notice and anything bookmarked all resolve unchanged.
 * `well-web-notify-sources` existed for one unreleased build and redirects to
 * its section anchor rather than 403ing.
 *
 * ── What is NOT here any more ───────────────────────────────────────────────
 *
 * A script that rewrote `#adminmenu` in the browser to force-highlight a
 * parent item. WordPress highlights the sidebar from `parent_file` and
 * `submenu_file`, and that script ran afterwards and undid the result — not
 * only for this plugin but for Well Web SEO, whose nav settles highlighting
 * with exactly those filters. It is deleted, not moved.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** The settings screen. Unchanged since 1.0.0, and it stays that way. */
const WELLWEB_NOTIFY_PAGE = 'well-web-notify-settings';

/** The delivery log. Also the slug it has had since 1.0.0. */
const WELLWEB_NOTIFY_PAGE_ACTIVITY = 'well-web-notify-log';

/**
 * Every screen this plugin owns.
 *
 * @return string[]
 */
function wellweb_notify_pages(): array {
    return array( WELLWEB_NOTIFY_PAGE, WELLWEB_NOTIFY_PAGE_ACTIVITY );
}

/**
 * The brand mark, as a data URI, for add_menu_page().
 */
function wellweb_notify_menu_icon(): string {
    $svg = '<svg viewBox="0 0 18 18" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M2.75 0.75C1.64543 0.75 0.75 1.64543 0.75 2.75V15.25C0.75 16.3546 1.64543 17.25 2.75 17.25H15.25C16.3546 17.25 17.25 16.3546 17.25 15.25V2.75C17.25 1.64543 16.3546 0.75 15.25 0.75H2.75ZM6.30887 6.34001C6.18691 6.06262 5.84088 5.92769 5.53599 6.03865C5.2311 6.14961 5.08281 6.46443 5.20476 6.74183L7.36687 11.6596C7.45716 11.865 7.67579 11.9997 7.91892 11.9997C8.16205 11.9997 8.38068 11.865 8.47097 11.6596L10.081 7.99749L11.6911 11.6596C11.7814 11.865 12 11.9997 12.2431 11.9997C12.4863 11.9997 12.7049 11.865 12.7952 11.6596L14.9573 6.74183C15.0792 6.46443 14.9309 6.14961 14.6261 6.03865C14.3212 5.92769 13.9751 6.06262 13.8532 6.34001L12.2431 10.0021L10.6331 6.34001C10.5428 6.13463 10.3242 5.99996 10.081 5.99996C9.8379 5.99996 9.61927 6.13463 9.52897 6.34001L7.91892 10.0021L6.30887 6.34001ZM3.37391 6.03874C3.6788 5.92779 4.02483 6.06271 4.14679 6.3401L6.30889 11.2579C6.43085 11.5353 6.28255 11.8501 5.97766 11.9611C5.67277 12.072 5.32674 11.9371 5.20479 11.6597L3.04268 6.74192C2.92073 6.46452 3.06902 6.1497 3.37391 6.03874ZM14.9516 11.0741C14.8131 10.7281 14.4204 10.5598 14.0744 10.6982C13.7283 10.8366 13.56 11.2293 13.6984 11.5754C13.8368 11.9214 14.2296 12.0897 14.5756 11.9513C14.9217 11.8129 15.09 11.4202 14.9516 11.0741Z"/></svg>';

    return 'data:image/svg+xml;base64,' . base64_encode( $svg );
}

add_action( 'admin_menu', 'wellweb_notify_build_menu', 20 );
function wellweb_notify_build_menu() {
    add_menu_page(
        __( 'Well Web Notify', 'well-web-notify' ),
        __( 'Well Web Notify', 'well-web-notify' ),
        'manage_options',
        WELLWEB_NOTIFY_PAGE,
        'wellweb_notify_render_screen',
        wellweb_notify_menu_icon(),
        // 76 is Well Web SEO. Sitting next to it keeps the family together
        // without either plugin depending on the other being installed.
        77
    );

    // WordPress mirrors a top-level menu into the first slot of its own
    // submenu, labelled with the parent — "Well Web Notify → Well Web Notify".
    // Registering Settings against the PARENT slug replaces that mirror with
    // something useful rather than leaving it to be stripped afterwards.
    add_submenu_page(
        WELLWEB_NOTIFY_PAGE,
        __( 'Settings', 'well-web-notify' ),
        __( 'Settings', 'well-web-notify' ),
        'manage_options',
        WELLWEB_NOTIFY_PAGE,
        'wellweb_notify_render_screen'
    );

    add_submenu_page(
        WELLWEB_NOTIFY_PAGE,
        __( 'Activity', 'well-web-notify' ),
        __( 'Activity', 'well-web-notify' ),
        'manage_options',
        WELLWEB_NOTIFY_PAGE_ACTIVITY,
        'wellweb_notify_render_activity_screen'
    );
}

/**
 * The slugs the three-screen version introduced, and the section each became.
 *
 * They existed for one release, which is long enough for a bookmark and a
 * support reply. Removing a URL and leaving "Sorry, you are not allowed to
 * access this page" behind is how a tidy-up reads as a broken plugin.
 *
 * 302, not 301: these are admin screens behind a login, and a permanent
 * redirect cached in a browser cannot be undone if a slug is ever reinstated.
 */
add_action( 'admin_init', 'wellweb_notify_redirect_retired_slugs' );
function wellweb_notify_redirect_retired_slugs() {
    $retired = array(
        'well-web-notify-sources' => '#sources',
    );

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the requested screen, not acting on it.
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( $page === '' || ! isset( $retired[ $page ] ) ) {
        return;
    }

    wp_safe_redirect( admin_url( 'admin.php?page=' . WELLWEB_NOTIFY_PAGE ) . $retired[ $page ], 302 );
    exit;
}

/**
 * Is the request on one of this plugin's screens?
 */
function wellweb_notify_is_plugin_screen(): bool {
    $screen = get_current_screen();
    if ( ! $screen ) {
        return false;
    }

    return strpos( (string) $screen->id, 'well-web-notify' ) !== false;
}

/**
 * The sections of the settings screen, in order.
 *
 * A data structure rather than markup, so the index and the body cannot
 * disagree about what exists: both are generated from this. A section whose
 * renderer is missing — WooCommerce is not installed, an add-on was
 * deactivated — drops out of both at once.
 *
 * @return array<string,array{label:string,render:callable}>
 */
function wellweb_notify_sections(): array {
    $sections = array(
        'channels' => array(
            'label'  => __( 'Channels', 'well-web-notify' ),
            'render' => 'wellweb_notify_section_channels',
        ),
        'sources'  => array(
            'label'  => __( 'Sources', 'well-web-notify' ),
            'render' => 'wellweb_notify_section_sources',
        ),
        'message'  => array(
            'label'  => __( 'Message', 'well-web-notify' ),
            'render' => 'wellweb_notify_section_message',
        ),
        'monitoring' => array(
            'label'  => __( 'Monitoring', 'well-web-notify' ),
            'render' => 'wellweb_notify_section_monitoring',
        ),
    );

    /**
     * Add a section to the screen, or take one away.
     *
     * @param array $sections id => { label, render }
     */
    $sections = apply_filters( 'wellweb_notify_sections', $sections );

    return array_filter( $sections, static function ( $section ) {
        return is_callable( $section['render'] ?? null );
    } );
}

add_action( 'admin_enqueue_scripts', 'wellweb_notify_enqueue_admin' );
function wellweb_notify_enqueue_admin() {
    $screen       = get_current_screen();
    $is_plugin    = wellweb_notify_is_plugin_screen();
    $is_dashboard = $screen && $screen->id === 'dashboard';

    if ( ! $is_plugin && ! $is_dashboard ) {
        return;
    }

    // Three sheets, in a declared order. The corrections in wpds-fixes.css are
    // written at the same specificity as the v2 rules they correct, so "after"
    // has to be a dependency rather than a coincidence of enqueue order.
    $sheets = array(
        'well-web-notify-wpds'       => array( 'wpds.css', array() ),
        'well-web-notify-wpds-v2'    => array( 'wpds-v2.css', array( 'well-web-notify-wpds' ) ),
        'well-web-notify-wpds-fixes' => array( 'wpds-fixes.css', array( 'well-web-notify-wpds-v2' ) ),
        'well-web-notify-admin'      => array( 'notify.css', array( 'well-web-notify-wpds-fixes' ) ),
    );

    foreach ( $sheets as $handle => $sheet ) {
        list( $file, $deps ) = $sheet;
        $path = WELLWEB_NOTIFY_DIR . 'assets/css/' . $file;
        if ( ! file_exists( $path ) ) {
            continue;
        }
        wp_enqueue_style( $handle, WELLWEB_NOTIFY_URL . 'assets/css/' . $file, $deps, filemtime( $path ) );
    }

    if ( ! $is_plugin ) {
        return;
    }

    wp_enqueue_script(
        'well-web-notify-admin',
        WELLWEB_NOTIFY_URL . 'assets/js/admin.js',
        array(),
        filemtime( WELLWEB_NOTIFY_DIR . 'assets/js/admin.js' ),
        true
    );

    wp_localize_script( 'well-web-notify-admin', 'wellwebNotify', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'wellweb_notify' ),
        'i18n'    => array(
            'testing' => __( 'Sending…', 'well-web-notify' ),
            'success' => __( 'Sent', 'well-web-notify' ),
            'error'   => __( 'Failed', 'well-web-notify' ),
            'copied'  => __( 'Copied', 'well-web-notify' ),
            'setCode' => __( 'set a code', 'well-web-notify' ),
        ),
    ) );
}

/**
 * Open the page shell: the `.wrap`, the header row, and a "Saved" notice.
 *
 * @param string $title
 * @param array  $args {
 *   @type string $meta  One muted line beside the title.
 *   @type string $right HTML for the right of the header row.
 * }
 */
function wellweb_notify_page_open( string $title, array $args = array() ): void {
    $meta  = $args['meta'] ?? '';
    $right = $args['right'] ?? '';
    ?>
    <div class="wrap wpds wpds-v2 wpds-page">
        <header class="wpds-pagehead">
            <h1 class="wpds-page-title"><?php echo esc_html( $title ); ?></h1>
            <?php if ( $meta !== '' ) : ?>
                <span class="wpds-pagehead__meta"><?php echo wp_kses_post( $meta ); ?></span>
            <?php endif; ?>
            <?php if ( $right !== '' ) : ?>
                <div class="wpds-pagehead__actions"><?php echo wp_kses_post( $right ); ?></div>
            <?php endif; ?>
        </header>
        <?php wellweb_notify_saved_notice(); ?>
    <?php
}

/**
 * "Saved" — which these screens never said.
 *
 * `options.php` redirects back with `settings-updated=true` and leaves it to
 * the screen to say so; only options-general.php does that for free. So the
 * settings saved silently, and the only way to tell whether a change had taken
 * was to reload and look.
 */
function wellweb_notify_saved_notice(): void {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the redirect flag options.php set, not acting on it.
    if ( empty( $_GET['settings-updated'] ) ) {
        return;
    }
    ?>
    <div class="wpds-notice wpds-notice--success">
        <span class="wpds-notice__title"><?php esc_html_e( 'Saved.', 'well-web-notify' ); ?></span>
    </div>
    <?php
}

/**
 * Close the page shell, with the one muted line that used to be a branding
 * block and two promo cards.
 */
function wellweb_notify_page_close(): void {
    ?>
        <p class="wellweb-notify-colophon">
            <?php
            printf(
                /* translators: 1: link to the Well Web site, 2: support email link */
                esc_html__( 'Well Web Notify by %1$s. Something not working? %2$s', 'well-web-notify' ),
                '<a href="https://wellweb.marketing" target="_blank" rel="noopener">wellweb.marketing</a>',
                '<a href="mailto:support@wellweb.marketing">support@wellweb.marketing</a>'
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * Render a list of settings fields into the WPDS two-column grid.
 *
 * Four channels were each writing the same label/input/hint markup by hand,
 * and they had already drifted — different wrappers, different hint elements,
 * one of them missing `autocomplete="off"` on a credential. A field is data;
 * this draws it.
 *
 * @param array $fields Each: name, label, type, value, placeholder, hint, masked.
 */
function wellweb_notify_render_fields( array $fields ): void {
    if ( ! $fields ) {
        return;
    }
    ?>
    <div class="wpds-fields">
        <?php foreach ( $fields as $field ) :
            $id     = 'f-' . sanitize_key( $field['name'] );
            $type   = $field['type'] ?? 'text';
            $masked = ! empty( $field['masked'] );
            ?>
            <label for="<?php echo esc_attr( $id ); ?>">
                <?php echo esc_html( $field['label'] ); ?>
            </label>
            <div>
                <input type="<?php echo esc_attr( $type ); ?>"
                       id="<?php echo esc_attr( $id ); ?>"
                       name="<?php echo esc_attr( $field['name'] ); ?>"
                       class="wpds-input"
                       <?php // A stored credential is never echoed back into the DOM. The
                             // mask goes in the placeholder, so the field reads as "set"
                             // while being genuinely empty. ?>
                       value="<?php echo $masked ? '' : esc_attr( $field['value'] ?? '' ); ?>"
                       placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>"
                       <?php echo $masked ? 'autocomplete="off" spellcheck="false"' : ''; ?> />
                <?php if ( ! empty( $field['hint'] ) ) : ?>
                    <span class="wpds-fields__hint"><?php echo wp_kses_post( $field['hint'] ); ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Link this plugin's own settings from the plugins list.
 */
add_filter( 'plugin_action_links_' . plugin_basename( WELLWEB_NOTIFY_PLUGIN_FILE ), 'wellweb_notify_plugin_action_links' );
function wellweb_notify_plugin_action_links( $links ) {
    array_unshift( $links, sprintf(
        '<a href="%s">%s</a>',
        esc_url( admin_url( 'admin.php?page=' . WELLWEB_NOTIFY_PAGE ) ),
        esc_html__( 'Settings', 'well-web-notify' )
    ) );

    return $links;
}

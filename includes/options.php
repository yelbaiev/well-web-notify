<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sanitize checkbox helper
 */
function wellweb_notify_sanitize_checkbox( $value ) {
    return ! empty( $value );
}

// ─── Admin menu ───────────────────────────────────────────────

add_action( 'admin_menu', 'wellweb_notify_admin_menu', 20 );
function wellweb_notify_admin_menu() {
    global $menu;

    // Check if parent Well Web menu already exists (registered by SEO plugin)
    $wellweb_menu_exists = false;
    foreach ( (array) $menu as $parent_menu ) {
        if ( $parent_menu[2] === 'wellweb' ) {
            $wellweb_menu_exists = true;
            break;
        }
    }

    $submenu_slug = 'well-web-notify-settings';

    if ( ! $wellweb_menu_exists ) {
        add_menu_page(
            'Well Web',
            'Well Web',
            'manage_options',
            'wellweb',
            'wellweb_notify_settings_page',
            'data:image/svg+xml;base64,' . base64_encode( '<svg viewBox="0 0 18 18" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M2.75 0.75C1.64543 0.75 0.75 1.64543 0.75 2.75V15.25C0.75 16.3546 1.64543 17.25 2.75 17.25H15.25C16.3546 17.25 17.25 16.3546 17.25 15.25V2.75C17.25 1.64543 16.3546 0.75 15.25 0.75H2.75ZM6.30887 6.34001C6.18691 6.06262 5.84088 5.92769 5.53599 6.03865C5.2311 6.14961 5.08281 6.46443 5.20476 6.74183L7.36687 11.6596C7.45716 11.865 7.67579 11.9997 7.91892 11.9997C8.16205 11.9997 8.38068 11.865 8.47097 11.6596L10.081 7.99749L11.6911 11.6596C11.7814 11.865 12 11.9997 12.2431 11.9997C12.4863 11.9997 12.7049 11.865 12.7952 11.6596L14.9573 6.74183C15.0792 6.46443 14.9309 6.14961 14.6261 6.03865C14.3212 5.92769 13.9751 6.06262 13.8532 6.34001L12.2431 10.0021L10.6331 6.34001C10.5428 6.13463 10.3242 5.99996 10.081 5.99996C9.8379 5.99996 9.61927 6.13463 9.52897 6.34001L7.91892 10.0021L6.30887 6.34001ZM3.37391 6.03874C3.6788 5.92779 4.02483 6.06271 4.14679 6.3401L6.30889 11.2579C6.43085 11.5353 6.28255 11.8501 5.97766 11.9611C5.67277 12.072 5.32674 11.9371 5.20479 11.6597L3.04268 6.74192C2.92073 6.46452 3.06902 6.1497 3.37391 6.03874ZM14.9516 11.0741C14.8131 10.7281 14.4204 10.5598 14.0744 10.6982C13.7283 10.8366 13.56 11.2293 13.6984 11.5754C13.8368 11.9214 14.2296 12.0897 14.5756 11.9513C14.9217 11.8129 15.09 11.4202 14.9516 11.0741Z"/></svg>' ),
            76
        );

        // Remove the auto-generated duplicate submenu item after all submenus are registered.
        add_action( 'admin_menu', function () {
            remove_submenu_page( 'wellweb', 'wellweb' );
        }, 999 );
    }

    add_submenu_page(
        'wellweb',
        __( 'Well Web Notify', 'well-web-notify' ),
        __( 'Notify', 'well-web-notify' ),
        'manage_options',
        $submenu_slug,
        'wellweb_notify_settings_page'
    );

    // Hidden subpages for tabs
    add_submenu_page( null, __( 'Notify Log', 'well-web-notify' ), '', 'manage_options', 'well-web-notify-log', 'wellweb_notify_log_page' );

    define( 'WELLWEB_NOTIFY_SUBMENU_SLUG', $submenu_slug );
}

// ─── Enqueue assets ───────────────────────────────────────────

add_action( 'admin_enqueue_scripts', 'wellweb_notify_admin_scripts' );
function wellweb_notify_admin_scripts() {
    $screen = get_current_screen();

    $is_plugin_page = $screen && ( strpos( $screen->id, 'well-web-notify' ) !== false || $screen->id === 'toplevel_page_wellweb' );
    $is_dashboard   = $screen && $screen->id === 'dashboard';

    if ( ! $is_plugin_page && ! $is_dashboard ) {
        return;
    }

    wp_enqueue_style(
        'well-web-notify-admin',
        WELLWEB_NOTIFY_URL . 'assets/css/admin.css',
        array(),
        filemtime( WELLWEB_NOTIFY_DIR . 'assets/css/admin.css' )
    );

    if ( $is_plugin_page ) {
        wp_enqueue_script(
            'well-web-notify-admin',
            WELLWEB_NOTIFY_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            filemtime( WELLWEB_NOTIFY_DIR . 'assets/js/admin.js' ),
            true
        );

        wp_localize_script( 'well-web-notify-admin', 'wellwebNotify', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'wellweb_notify' ),
            'i18n'    => array(
                'testing'   => __( 'Sending...', 'well-web-notify' ),
                'success'   => __( 'Sent!', 'well-web-notify' ),
                'error'     => __( 'Failed', 'well-web-notify' ),
                'saved'     => __( 'Saved', 'well-web-notify' ),
                'saving'    => __( 'Saving...', 'well-web-notify' ),
                'confirm'   => __( 'Are you sure?', 'well-web-notify' ),
                'copied'    => __( 'Copied!', 'well-web-notify' ),
            ),
        ) );
    }
}

// ─── Settings registration ────────────────────────────────────

add_action( 'admin_init', 'wellweb_notify_register_settings' );
function wellweb_notify_register_settings() {
    $manager  = WellWeb_Notify_Channel_Manager::instance();
    $channels = $manager->get_channels();

    $group = 'well-web-notify-settings';

    foreach ( $channels as $channel ) {
        foreach ( $channel->get_settings() as $option => $sanitize ) {
            register_setting( $group, $option, array(
                'sanitize_callback' => $sanitize,
            ) );
        }
    }

    // Default country code for phone contact links
    register_setting( $group, 'well-web-notify-default-country-code', array(
        'sanitize_callback' => 'absint',
    ) );

    // WooCommerce Orders
    register_setting( $group, 'well-web-notify-woo-enabled', array(
        'type'              => 'boolean',
        'default'           => false,
        'sanitize_callback' => 'wellweb_notify_sanitize_checkbox',
    ) );
    $woo_events = array( 'new-order', 'processing', 'completed', 'cancelled', 'refunded', 'failed' );
    foreach ( $woo_events as $event ) {
        register_setting( $group, 'well-web-notify-woo-event-' . $event, array(
            'type'              => 'boolean',
            'default'           => false,
            'sanitize_callback' => 'wellweb_notify_sanitize_checkbox',
        ) );
    }

}

// ─── Navigation ───────────────────────────────────────────────

function wellweb_notify_breadcrumbs() {
    ?>
    <div>
        <h1 class="ww-h1"><?php esc_html_e( 'Notify', 'well-web-notify' ); ?></h1>

        <ul class="ww-seo-menu ww-notify-menu">
            <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=well-web-notify-settings' ) ); ?>"><?php esc_html_e( 'Channels', 'well-web-notify' ); ?></a></li>
            <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=well-web-notify-log' ) ); ?>"><?php esc_html_e( 'Log', 'well-web-notify' ); ?></a></li>
        </ul>
    </div>
    <?php
}

// ─── Settings page (Channels tab) ────────────────────────────

function wellweb_notify_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Unauthorized', 'well-web-notify' ) );
    }

    $manager  = WellWeb_Notify_Channel_Manager::instance();
    $channels = $manager->get_channels();

    // Auto-detect forms
    $form_manager = WellWeb_Notify_Form_Manager::instance();
    $all_forms    = $form_manager->get_forms();
    $available    = $form_manager->get_available_forms();

    ?>
    <div class="ww-container ww-container-admin">
    <?php wellweb_notify_breadcrumbs(); ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" id="well-web-notify-form">
        <?php settings_fields( 'well-web-notify-settings' ); ?>

        <!-- Detected Form Plugins -->
        <div class="ww-settings-section">
            <h3 class="ww-settings-section-header">
                <span class="dashicons dashicons-feedback"></span>
                <?php esc_html_e( 'Detected Plugins', 'well-web-notify' ); ?>
            </h3>
            <div class="ww-settings-section-body">
                <ul class="ww-notify-form-list">
                    <?php foreach ( $all_forms as $form ) :
                        $is_active = $form->is_available();
                    ?>
                    <li class="<?php echo $is_active ? '--active' : '--inactive'; ?>">
                        <span class="dashicons <?php echo $is_active ? 'dashicons-yes-alt' : 'dashicons-minus'; ?>"></span>
                        <?php echo esc_html( $form->get_label() ); ?>
                        <?php if ( $is_active ) : ?>
                        <span class="ww-notify-badge --success"><?php esc_html_e( 'Active', 'well-web-notify' ); ?></span>
                        <?php else : ?>
                        <span class="ww-notify-badge --muted"><?php esc_html_e( 'Not installed', 'well-web-notify' ); ?></span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                    <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                    <li class="--active">
                        <span class="dashicons dashicons-yes-alt"></span>
                        WooCommerce
                        <span class="ww-notify-badge --success"><?php esc_html_e( 'Active', 'well-web-notify' ); ?></span>
                    </li>
                    <?php endif; ?>
                </ul>
                <p class="description">
                    <?php esc_html_e( 'Active plugins will automatically send notifications to enabled channels on submission.', 'well-web-notify' ); ?>
                </p>
            </div>
        </div>

        <!-- Default Country Code -->
        <div class="ww-settings-section">
            <h3 class="ww-settings-section-header">
                <span class="dashicons dashicons-phone"></span>
                <?php esc_html_e( 'Default Country Code', 'well-web-notify' ); ?>
            </h3>
            <div class="ww-settings-section-body">
                <label class="ww-field ww-field-labeled">
                    <span class="ww-field-label"><?php esc_html_e( 'Country dialing code', 'well-web-notify' ); ?></span>
                    <input type="number"
                           name="well-web-notify-default-country-code"
                           value="<?php echo esc_attr( wellweb_notify_get_option( 'default-country-code', '' ) ?: '' ); ?>"
                           placeholder="46"
                           class="small-text"
                           min="0"
                           max="999" />
                </label>
                <p class="description">
                    <?php esc_html_e( 'Numeric code without +. Used when forms don\'t include a country code field (e.g. 46 for Sweden, 380 for Ukraine, 1 for USA). Phone numbers in form submissions get clickable contact links (Call, WhatsApp, Telegram, Viber) automatically.', 'well-web-notify' ); ?>
                </p>
            </div>
        </div>

        <!-- Messenger Channels -->
        <?php foreach ( $channels as $channel ) : ?>
        <div class="ww-settings-section ww-collapsible <?php echo $channel->is_enabled() ? '' : 'ww-collapsed'; ?>" data-channel="<?php echo esc_attr( $channel->get_slug() ); ?>">
            <h3 class="ww-settings-section-header">
                <span class="ww-collapse-toggle dashicons dashicons-arrow-right-alt2"></span>
                <span class="dashicons <?php echo esc_attr( $channel->get_icon() ); ?>"></span>
                <?php echo esc_html( $channel->get_label() ); ?>

                <label class="ww-notify-toggle">
                    <input type="checkbox"
                           name="well-web-notify-<?php echo esc_attr( $channel->get_slug() ); ?>-enabled"
                           value="1"
                           <?php checked( $channel->is_enabled() ); ?> />
                    <span class="ww-notify-toggle-slider"></span>
                </label>

                <?php if ( $channel->is_configured() ) : ?>
                <span class="ww-notify-status --configured">
                    <span class="dashicons dashicons-yes-alt"></span>
                </span>
                <?php endif; ?>

                <button type="button"
                        class="button button-small ww-notify-test-btn"
                        data-channel="<?php echo esc_attr( $channel->get_slug() ); ?>"
                        <?php echo ! $channel->is_configured() ? 'disabled' : ''; ?>>
                    <?php esc_html_e( 'Test', 'well-web-notify' ); ?>
                </button>
            </h3>
            <div class="ww-settings-section-body">
                <?php $channel->render_settings(); ?>

                <?php if ( $channel->get_slug() === 'telegram' ) : ?>
                <!-- Telegram Setup Guide -->
                <details class="ww-notify-setup-guide">
                    <summary><?php esc_html_e( 'How to set up Telegram notifications', 'well-web-notify' ); ?></summary>
                    <ol>
                        <li><?php echo wp_kses( __( 'Open Telegram and search for <strong>@BotFather</strong>', 'well-web-notify' ), array( 'strong' => array() ) ); ?></li>
                        <li><?php echo wp_kses( __( 'Send <code>/newbot</code> and follow the prompts to create your bot', 'well-web-notify' ), array( 'code' => array() ) ); ?></li>
                        <li><?php esc_html_e( 'Copy the bot token BotFather gives you and paste it in the "Bot Token" field above', 'well-web-notify' ); ?></li>
                        <li><?php esc_html_e( 'Add your new bot to the Telegram group or channel where you want notifications', 'well-web-notify' ); ?></li>
                        <li><?php echo wp_kses( __( 'To get the Chat ID: send a message in the group, then visit <code>https://api.telegram.org/bot&lt;YOUR_TOKEN&gt;/getUpdates</code> in your browser and look for the "chat" → "id" value', 'well-web-notify' ), array( 'code' => array() ) ); ?></li>
                        <li><?php esc_html_e( 'Paste the Chat ID in the field above and click "Test" to verify', 'well-web-notify' ); ?></li>
                    </ol>
                </details>
                <?php endif; ?>

                <?php if ( $channel->get_slug() === 'slack' ) : ?>
                <!-- Slack Setup Guide -->
                <details class="ww-notify-setup-guide">
                    <summary><?php esc_html_e( 'How to set up Slack notifications', 'well-web-notify' ); ?></summary>
                    <ol>
                        <li><?php echo wp_kses( __( 'Go to <strong>api.slack.com/apps</strong> and click "Create New App"', 'well-web-notify' ), array( 'strong' => array() ) ); ?></li>
                        <li><?php esc_html_e( 'Choose "From scratch", name your app, and select your workspace', 'well-web-notify' ); ?></li>
                        <li><?php echo wp_kses( __( 'In the app settings, go to <strong>Incoming Webhooks</strong> and toggle it on', 'well-web-notify' ), array( 'strong' => array() ) ); ?></li>
                        <li><?php echo wp_kses( __( 'Click <strong>"Add New Webhook to Workspace"</strong> and select the channel for notifications', 'well-web-notify' ), array( 'strong' => array() ) ); ?></li>
                        <li><?php esc_html_e( 'Copy the Webhook URL and paste it in the field above', 'well-web-notify' ); ?></li>
                        <li><?php esc_html_e( 'Click "Test" to verify the connection', 'well-web-notify' ); ?></li>
                    </ol>
                </details>
                <?php endif; ?>

                <?php if ( $channel->get_slug() === 'discord' ) : ?>
                <!-- Discord Setup Guide -->
                <details class="ww-notify-setup-guide">
                    <summary><?php esc_html_e( 'How to set up Discord notifications', 'well-web-notify' ); ?></summary>
                    <ol>
                        <li><?php echo wp_kses( __( 'Open your Discord server and go to <strong>Server Settings → Integrations</strong>', 'well-web-notify' ), array( 'strong' => array() ) ); ?></li>
                        <li><?php echo wp_kses( __( 'Click <strong>"Webhooks"</strong> then <strong>"New Webhook"</strong>', 'well-web-notify' ), array( 'strong' => array() ) ); ?></li>
                        <li><?php esc_html_e( 'Name your webhook and select the channel for notifications', 'well-web-notify' ); ?></li>
                        <li><?php esc_html_e( 'Click "Copy Webhook URL" and paste it in the field above', 'well-web-notify' ); ?></li>
                        <li><?php esc_html_e( 'Click "Test" to verify the connection', 'well-web-notify' ); ?></li>
                    </ol>
                </details>
                <?php endif; ?>

                <?php if ( $channel->get_slug() === 'google-chat' ) : ?>
                <!-- Google Chat Setup Guide -->
                <details class="ww-notify-setup-guide">
                    <summary><?php esc_html_e( 'How to set up Google Chat notifications', 'well-web-notify' ); ?></summary>
                    <ol>
                        <li><?php esc_html_e( 'Open the Google Chat space where you want notifications', 'well-web-notify' ); ?></li>
                        <li><?php echo wp_kses( __( 'Click the space name at the top → <strong>Space settings</strong>', 'well-web-notify' ), array( 'strong' => array() ) ); ?></li>
                        <li><?php echo wp_kses( __( 'Go to <strong>Apps & integrations</strong> and click <strong>"Add webhooks"</strong>', 'well-web-notify' ), array( 'strong' => array() ) ); ?></li>
                        <li><?php esc_html_e( 'Name your webhook and click "Save"', 'well-web-notify' ); ?></li>
                        <li><?php esc_html_e( 'Copy the Webhook URL and paste it in the field above', 'well-web-notify' ); ?></li>
                        <li><?php esc_html_e( 'Click "Test" to verify the connection', 'well-web-notify' ); ?></li>
                    </ol>
                </details>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Pro Channel Teasers -->
        <?php
        /**
         * Filter whether to show Pro channel teasers.
         * The Pro add-on sets this to false to hide teasers when active.
         */
        if ( apply_filters( 'wellweb_notify_show_pro_teasers', true ) ) :

            $pro_channels = array(
                array(
                    'name'        => 'Viber',
                    'icon'        => 'dashicons-smartphone',
                    'description' => __( 'Send notifications via Viber Business bot', 'well-web-notify' ),
                ),
                array(
                    'name'        => 'WhatsApp',
                    'icon'        => 'dashicons-phone',
                    'description' => __( 'Deliver form submissions via WhatsApp Business API', 'well-web-notify' ),
                ),
            );

            foreach ( $pro_channels as $pro ) : ?>
            <div class="ww-settings-section ww-settings-section--teaser">
                <h3 class="ww-settings-section-header">
                    <span class="dashicons <?php echo esc_attr( $pro['icon'] ); ?>"></span>
                    <?php echo esc_html( $pro['name'] ); ?>
                    <span class="ww-notify-badge --pro">Pro</span>
                    <span class="ww-pro-description">
                        <?php echo esc_html( $pro['description'] ); ?>
                    </span>
                </h3>
            </div>
            <?php endforeach; ?>

            <p class="ww-notify-pro-cta">
                <?php
                printf(
                    /* translators: %s: email link */
                    esc_html__( 'Need Viber or WhatsApp? Contact us at %s', 'well-web-notify' ),
                    '<a href="mailto:support@wellweb.marketing?subject=' . rawurlencode( 'Need Viber or WhatsApp for Well Web Notify' ) . '">support@wellweb.marketing</a>'
                );
                ?>
            </p>
        <?php endif; ?>

        <!-- WooCommerce Orders -->
        <?php if ( class_exists( 'WooCommerce' ) ) :
            $woo_enabled = get_option( 'well-web-notify-woo-enabled', false );
        ?>
        <div class="ww-settings-section ww-collapsible <?php echo empty( $woo_enabled ) ? 'ww-collapsed' : ''; ?>">
            <h3 class="ww-settings-section-header">
                <span class="ww-collapse-toggle dashicons dashicons-arrow-right-alt2"></span>
                <span class="dashicons dashicons-cart"></span>
                <?php esc_html_e( 'WooCommerce Orders', 'well-web-notify' ); ?>

                <label class="ww-notify-toggle">
                    <input type="checkbox"
                           name="well-web-notify-woo-enabled"
                           value="1"
                           <?php checked( $woo_enabled ); ?> />
                    <span class="ww-notify-toggle-slider"></span>
                </label>
            </h3>
            <div class="ww-settings-section-body">
                <p class="description ww-woo-description">
                    <?php esc_html_e( 'Choose which order events should trigger notifications:', 'well-web-notify' ); ?>
                </p>
                <?php
                $woo_events = array(
                    'new-order'  => __( 'New order placed', 'well-web-notify' ),
                    'processing' => __( 'Order → Processing', 'well-web-notify' ),
                    'completed'  => __( 'Order → Completed', 'well-web-notify' ),
                    'cancelled'  => __( 'Order → Cancelled', 'well-web-notify' ),
                    'refunded'   => __( 'Order → Refunded', 'well-web-notify' ),
                    'failed'     => __( 'Order → Failed', 'well-web-notify' ),
                );
                foreach ( $woo_events as $event_key => $event_label ) :
                    $option_name = 'well-web-notify-woo-event-' . $event_key;
                ?>
                <label class="ww-field ww-woo-event">
                    <input type="checkbox"
                           name="<?php echo esc_attr( $option_name ); ?>"
                           value="1"
                           <?php checked( get_option( $option_name, false ) ); ?> />
                    <span><?php echo esc_html( $event_label ); ?></span>
                </label>
                <?php endforeach; ?>
                <p class="description ww-woo-description --bottom">
                    <?php esc_html_e( 'Notifications include: customer name, email, phone, items, total, payment method, and location. Phone contact links are generated automatically from billing phone.', 'well-web-notify' ); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <p class="submit">
            <?php submit_button( __( 'Save Settings', 'well-web-notify' ), 'primary', 'submit', false ); ?>
        </p>

        <!-- Cross-promotion: More from Well Web -->
        <div class="ww-settings-section ww-notify-cross-promo">
            <h3 class="ww-settings-section-header">
                <span class="dashicons dashicons-admin-plugins"></span>
                <?php esc_html_e( 'More from Well Web Marketing', 'well-web-notify' ); ?>
            </h3>
            <div class="ww-settings-section-body">
                <div class="ww-notify-promo-grid">
                    <div class="ww-notify-promo-card">
                        <h4>Well Web SEO</h4>
                        <p><?php esc_html_e( 'SEO meta tags, Open Graph, JSON-LD schema, XML sitemaps, and 301 redirects — all in one lightweight plugin.', 'well-web-notify' ); ?></p>
                        <?php if ( is_plugin_active( 'wellweb-seo/index.php' ) ) : ?>
                            <span class="ww-notify-badge --success"><?php esc_html_e( 'Active', 'well-web-notify' ); ?></span>
                        <?php else : ?>
                            <a href="mailto:support@wellweb.marketing?subject=<?php echo rawurlencode( 'Interested in Well Web SEO' ); ?>"><?php esc_html_e( 'Request', 'well-web-notify' ); ?> &rarr;</a>
                        <?php endif; ?>
                    </div>
                    <div class="ww-notify-promo-card">
                        <h4>Well Web Multilang</h4>
                        <p><?php esc_html_e( 'Multilingual content management with language switcher, hreflang tags, and per-language SEO fields.', 'well-web-notify' ); ?></p>
                        <?php if ( is_plugin_active( 'wellweb-multilang/index.php' ) ) : ?>
                            <span class="ww-notify-badge --success"><?php esc_html_e( 'Active', 'well-web-notify' ); ?></span>
                        <?php else : ?>
                            <a href="mailto:support@wellweb.marketing?subject=<?php echo rawurlencode( 'Interested in Well Web Multilang' ); ?>"><?php esc_html_e( 'Request', 'well-web-notify' ); ?> &rarr;</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <aside class="ww-branding-tag">
            <header>
                <img src="<?php echo esc_url( plugins_url( 'assets/img/ww-logo.png', __DIR__ ) ); ?>" alt="Well Web" width="32" height="32" />
                Well Web Marketing
            </header>
            <nav>
                <div><?php esc_html_e( 'Website', 'well-web-notify' ); ?>: <a href="https://wellweb.marketing" target="_blank">wellweb.marketing</a></div>
                <div><?php esc_html_e( 'Email', 'well-web-notify' ); ?>: <a href="mailto:support@wellweb.marketing">support@wellweb.marketing</a></div>
            </nav>
        </aside>
    </form>
    </div>
    <?php
}

// ─── Log page ─────────────────────────────────────────────────

function wellweb_notify_log_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Unauthorized', 'well-web-notify' ) );
    }

    $filter_channel = '';
    $filter_status  = '';
    $page           = 1;

    if ( isset( $_GET['_wpnonce'] ) ) {
        check_admin_referer( 'wellweb_notify_log_filter' );
        $filter_channel = isset( $_GET['channel'] ) ? sanitize_text_field( wp_unslash( $_GET['channel'] ) ) : '';
        $filter_status  = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
        $page           = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
    }

    $log = WellWeb_Notify_Log::get_entries( array(
        'channel'  => $filter_channel,
        'status'   => $filter_status,
        'page'     => $page,
        'per_page' => 25,
    ) );

    $manager  = WellWeb_Notify_Channel_Manager::instance();
    $channels = $manager->get_channels();

    ?>
    <div class="ww-container ww-container-admin">
    <?php wellweb_notify_breadcrumbs(); ?>

    <!-- Filters -->
    <div class="ww-notify-log-filters">
        <form method="get">
            <input type="hidden" name="page" value="well-web-notify-log" />
            <?php wp_nonce_field( 'wellweb_notify_log_filter' ); ?>

            <select name="channel">
                <option value=""><?php esc_html_e( 'All channels', 'well-web-notify' ); ?></option>
                <?php foreach ( $channels as $ch ) : ?>
                <option value="<?php echo esc_attr( $ch->get_slug() ); ?>" <?php selected( $filter_channel, $ch->get_slug() ); ?>>
                    <?php echo esc_html( $ch->get_label() ); ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select name="status">
                <option value=""><?php esc_html_e( 'All statuses', 'well-web-notify' ); ?></option>
                <option value="success" <?php selected( $filter_status, 'success' ); ?>><?php esc_html_e( 'Success', 'well-web-notify' ); ?></option>
                <option value="error" <?php selected( $filter_status, 'error' ); ?>><?php esc_html_e( 'Error', 'well-web-notify' ); ?></option>
            </select>

            <?php submit_button( __( 'Filter', 'well-web-notify' ), 'secondary', '', false ); ?>
        </form>
    </div>

    <!-- Log table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th class="ww-log-col-channel"><?php esc_html_e( 'Channel', 'well-web-notify' ); ?></th>
                <th class="ww-log-col-form"><?php esc_html_e( 'Form', 'well-web-notify' ); ?></th>
                <th class="ww-log-col-status"><?php esc_html_e( 'Status', 'well-web-notify' ); ?></th>
                <th><?php esc_html_e( 'Error', 'well-web-notify' ); ?></th>
                <th class="ww-log-col-date"><?php esc_html_e( 'Date', 'well-web-notify' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $log['items'] ) ) : ?>
            <tr>
                <td colspan="5"><?php esc_html_e( 'No log entries found.', 'well-web-notify' ); ?></td>
            </tr>
            <?php else : ?>
            <?php foreach ( $log['items'] as $entry ) : ?>
            <tr>
                <td>
                    <?php
                    $ch = $manager->get_channel( $entry->channel );
                    if ( $ch ) {
                        echo '<span class="dashicons ' . esc_attr( $ch->get_icon() ) . '"></span> ';
                        echo esc_html( $ch->get_label() );
                    } else {
                        echo esc_html( $entry->channel );
                    }
                    ?>
                </td>
                <td><?php echo esc_html( $entry->form_name ); ?></td>
                <td>
                    <?php if ( $entry->status === 'success' ) : ?>
                    <span class="ww-notify-badge --success"><?php esc_html_e( 'OK', 'well-web-notify' ); ?></span>
                    <?php else : ?>
                    <span class="ww-notify-badge --error"><?php esc_html_e( 'Error', 'well-web-notify' ); ?></span>
                    <?php endif; ?>
                </td>
                <td><code><?php echo esc_html( $entry->error_message ); ?></code></td>
                <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->created_at ) ) ); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( $log['pages'] > 1 ) : ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            $base_url = wp_nonce_url( add_query_arg( 'paged', '%#%' ), 'wellweb_notify_log_filter' );
            echo wp_kses_post( paginate_links( array(
                'base'    => $base_url,
                'format'  => '',
                'current' => $log['page'],
                'total'   => $log['pages'],
            ) ) );
            ?>
        </div>
    </div>
    <?php endif; ?>

    </div>
    <?php
}

// ─── AJAX handlers ────────────────────────────────────────────

add_action( 'wp_ajax_wellweb_notify_test', 'wellweb_notify_ajax_test' );
function wellweb_notify_ajax_test() {
    check_ajax_referer( 'wellweb_notify', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $slug    = sanitize_text_field( wp_unslash( $_POST['channel'] ?? '' ) );
    $manager = WellWeb_Notify_Channel_Manager::instance();
    $channel = $manager->get_channel( $slug );

    if ( ! $channel ) {
        wp_send_json_error( __( 'Unknown channel.', 'well-web-notify' ) );
    }

    if ( ! $channel->is_configured() ) {
        wp_send_json_error( __( 'Channel is not configured.', 'well-web-notify' ) );
    }

    $result = $channel->send_test();

    if ( is_wp_error( $result ) ) {
        WellWeb_Notify_Log::log( $slug, 'Test', 'error', $result->get_error_message() );
        wp_send_json_error( $result->get_error_message() );
    }

    WellWeb_Notify_Log::log( $slug, 'Test', 'success' );
    wp_send_json_success( __( 'Test message sent successfully!', 'well-web-notify' ) );
}

add_action( 'wp_ajax_wellweb_notify_save_channel', 'wellweb_notify_ajax_save_channel' );
function wellweb_notify_ajax_save_channel() {
    check_ajax_referer( 'wellweb_notify', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $slug = sanitize_text_field( wp_unslash( $_POST['channel'] ?? '' ) );
    $data = isset( $_POST['data'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['data'] ) ) : array();

    if ( ! is_array( $data ) ) {
        wp_send_json_error( 'Invalid data' );
    }

    foreach ( $data as $key => $value ) {
        $option = sanitize_text_field( $key );

        // Only allow well-web-notify-* options
        if ( strpos( $option, 'well-web-notify-' ) !== 0 ) {
            continue;
        }

        // Encrypt tokens/webhooks
        if ( preg_match( '/(token|webhook)$/', $option ) ) {
            if ( ! empty( $value ) ) {
                $value = wellweb_notify_encrypt( $value );
            }
            update_option( $option, $value );
        } else {
            update_option( $option, $value );
        }
    }

    wp_send_json_success( array( 'message' => __( 'Saved', 'well-web-notify' ) ) );
}
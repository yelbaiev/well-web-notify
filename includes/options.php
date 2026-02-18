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

    $submenu_slug = 'wellweb-notify-settings';

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
        __( 'Well Web Notify', 'wellweb-notify' ),
        __( 'Notify', 'wellweb-notify' ),
        'manage_options',
        $submenu_slug,
        'wellweb_notify_settings_page'
    );

    // Hidden subpages for tabs
    add_submenu_page( null, __( 'Notify Log', 'wellweb-notify' ), '', 'manage_options', 'wellweb-notify-log', 'wellweb_notify_log_page' );

    define( 'WELLWEB_NOTIFY_SUBMENU_SLUG', $submenu_slug );
}

// ─── Enqueue assets ───────────────────────────────────────────

add_action( 'admin_enqueue_scripts', 'wellweb_notify_admin_scripts' );
function wellweb_notify_admin_scripts() {
    $screen = get_current_screen();

    if ( ! $screen || ( strpos( $screen->id, 'wellweb-notify' ) === false && $screen->id !== 'toplevel_page_wellweb' ) ) {
        return;
    }

    wp_enqueue_style(
        'wellweb-notify-admin',
        WELLWEB_NOTIFY_URL . 'assets/css/admin.css',
        array(),
        filemtime( WELLWEB_NOTIFY_DIR . 'assets/css/admin.css' )
    );

    wp_enqueue_script(
        'wellweb-notify-admin',
        WELLWEB_NOTIFY_URL . 'assets/js/admin.js',
        array( 'jquery' ),
        filemtime( WELLWEB_NOTIFY_DIR . 'assets/js/admin.js' ),
        true
    );

    wp_localize_script( 'wellweb-notify-admin', 'wellwebNotify', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'wellweb_notify' ),
        'i18n'    => array(
            'testing'   => __( 'Sending...', 'wellweb-notify' ),
            'success'   => __( 'Sent!', 'wellweb-notify' ),
            'error'     => __( 'Failed', 'wellweb-notify' ),
            'saved'     => __( 'Saved', 'wellweb-notify' ),
            'saving'    => __( 'Saving...', 'wellweb-notify' ),
            'confirm'   => __( 'Are you sure?', 'wellweb-notify' ),
            'copied'    => __( 'Copied!', 'wellweb-notify' ),
        ),
    ) );
}

// ─── Settings registration ────────────────────────────────────

add_action( 'admin_init', 'wellweb_notify_register_settings' );
function wellweb_notify_register_settings() {
    $manager  = WellWeb_Notify_Channel_Manager::instance();
    $channels = $manager->get_channels();

    $group = 'wellweb-notify-settings';

    foreach ( $channels as $channel ) {
        foreach ( $channel->get_settings() as $option => $sanitize ) {
            register_setting( $group, $option, array(
                'sanitize_callback' => $sanitize,
            ) );
        }
    }

    // Default country code for phone contact links
    register_setting( $group, 'wellweb-notify-default-country-code', array(
        'sanitize_callback' => 'absint',
    ) );

    // WooCommerce Orders
    register_setting( $group, 'wellweb-notify-woo-enabled', array(
        'type'              => 'boolean',
        'default'           => false,
        'sanitize_callback' => 'wellweb_notify_sanitize_checkbox',
    ) );
    $woo_events = array( 'new-order', 'processing', 'completed', 'cancelled', 'refunded', 'failed' );
    foreach ( $woo_events as $event ) {
        register_setting( $group, 'wellweb-notify-woo-event-' . $event, array(
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
        <h1 class="ww-h1"><?php esc_html_e( 'Notify', 'wellweb-notify' ); ?></h1>

        <ul class="ww-seo-menu ww-notify-menu">
            <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wellweb-notify-settings' ) ); ?>"><?php esc_html_e( 'Channels', 'wellweb-notify' ); ?></a></li>
            <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wellweb-notify-log' ) ); ?>"><?php esc_html_e( 'Log', 'wellweb-notify' ); ?></a></li>
        </ul>
    </div>
    <?php
}

// ─── Settings page (Channels tab) ────────────────────────────

function wellweb_notify_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Unauthorized', 'wellweb-notify' ) );
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

    <form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" id="wellweb-notify-form">
        <?php settings_fields( 'wellweb-notify-settings' ); ?>

        <!-- Detected Form Plugins -->
        <div class="ww-settings-section">
            <h3 class="ww-settings-section-header">
                <span class="dashicons dashicons-feedback"></span>
                <?php esc_html_e( 'Detected Plugins', 'wellweb-notify' ); ?>
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
                        <span class="ww-notify-badge --success"><?php esc_html_e( 'Active', 'wellweb-notify' ); ?></span>
                        <?php else : ?>
                        <span class="ww-notify-badge --muted"><?php esc_html_e( 'Not installed', 'wellweb-notify' ); ?></span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                    <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                    <li class="--active">
                        <span class="dashicons dashicons-yes-alt"></span>
                        WooCommerce
                        <span class="ww-notify-badge --success"><?php esc_html_e( 'Active', 'wellweb-notify' ); ?></span>
                    </li>
                    <?php endif; ?>
                </ul>
                <p class="description">
                    <?php esc_html_e( 'Active plugins will automatically send notifications to enabled channels on submission.', 'wellweb-notify' ); ?>
                </p>
            </div>
        </div>

        <!-- Default Country Code -->
        <div class="ww-settings-section">
            <h3 class="ww-settings-section-header">
                <span class="dashicons dashicons-phone"></span>
                <?php esc_html_e( 'Default Country Code', 'wellweb-notify' ); ?>
            </h3>
            <div class="ww-settings-section-body">
                <label class="ww-field ww-field-labeled">
                    <span class="ww-field-label"><?php esc_html_e( 'Country dialing code', 'wellweb-notify' ); ?></span>
                    <input type="number"
                           name="wellweb-notify-default-country-code"
                           value="<?php echo esc_attr( wellweb_notify_get_option( 'default-country-code', '' ) ?: '' ); ?>"
                           placeholder="46"
                           class="small-text"
                           min="0"
                           max="999" />
                </label>
                <p class="description">
                    <?php esc_html_e( 'Numeric code without +. Used when forms don\'t include a country code field (e.g. 46 for Sweden, 380 for Ukraine, 1 for USA). Phone numbers in form submissions get clickable contact links (Call, WhatsApp, Telegram, Viber) automatically.', 'wellweb-notify' ); ?>
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

                <label class="ww-notify-toggle" style="margin-left: auto;">
                    <input type="checkbox"
                           name="wellweb-notify-<?php echo esc_attr( $channel->get_slug() ); ?>-enabled"
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
                    <?php esc_html_e( 'Test', 'wellweb-notify' ); ?>
                </button>
            </h3>
            <div class="ww-settings-section-body">
                <?php $channel->render_settings(); ?>

                <?php if ( $channel->get_slug() === 'telegram' ) : ?>
                <!-- Telegram Setup Guide -->
                <details class="ww-notify-setup-guide" style="margin-top: 16px;">
                    <summary><?php esc_html_e( 'How to set up Telegram notifications', 'wellweb-notify' ); ?></summary>
                    <ol>
                        <li><?php echo wp_kses( __( 'Open Telegram and search for <strong>@BotFather</strong>', 'wellweb-notify' ), array( 'strong' => array() ) ); ?></li>
                        <li><?php echo wp_kses( __( 'Send <code>/newbot</code> and follow the prompts to create your bot', 'wellweb-notify' ), array( 'code' => array() ) ); ?></li>
                        <li><?php esc_html_e( 'Copy the bot token BotFather gives you and paste it in the "Bot Token" field above', 'wellweb-notify' ); ?></li>
                        <li><?php esc_html_e( 'Add your new bot to the Telegram group or channel where you want notifications', 'wellweb-notify' ); ?></li>
                        <li><?php echo wp_kses( __( 'To get the Chat ID: send a message in the group, then visit <code>https://api.telegram.org/bot&lt;YOUR_TOKEN&gt;/getUpdates</code> in your browser and look for the "chat" → "id" value', 'wellweb-notify' ), array( 'code' => array() ) ); ?></li>
                        <li><?php esc_html_e( 'Paste the Chat ID in the field above and click "Test" to verify', 'wellweb-notify' ); ?></li>
                    </ol>
                </details>
                <?php endif; ?>

                <?php if ( $channel->get_slug() === 'slack' ) : ?>
                <!-- Slack Setup Guide -->
                <details class="ww-notify-setup-guide" style="margin-top: 16px;">
                    <summary><?php esc_html_e( 'How to set up Slack notifications', 'wellweb-notify' ); ?></summary>
                    <ol>
                        <li><?php echo wp_kses( __( 'Go to <strong>api.slack.com/apps</strong> and click "Create New App"', 'wellweb-notify' ), array( 'strong' => array() ) ); ?></li>
                        <li><?php esc_html_e( 'Choose "From scratch", name your app, and select your workspace', 'wellweb-notify' ); ?></li>
                        <li><?php echo wp_kses( __( 'In the app settings, go to <strong>Incoming Webhooks</strong> and toggle it on', 'wellweb-notify' ), array( 'strong' => array() ) ); ?></li>
                        <li><?php echo wp_kses( __( 'Click <strong>"Add New Webhook to Workspace"</strong> and select the channel for notifications', 'wellweb-notify' ), array( 'strong' => array() ) ); ?></li>
                        <li><?php esc_html_e( 'Copy the Webhook URL and paste it in the field above', 'wellweb-notify' ); ?></li>
                        <li><?php esc_html_e( 'Click "Test" to verify the connection', 'wellweb-notify' ); ?></li>
                    </ol>
                </details>
                <?php endif; ?>

                <?php if ( $channel->get_slug() === 'discord' ) : ?>
                <!-- Discord Setup Guide -->
                <details class="ww-notify-setup-guide" style="margin-top: 16px;">
                    <summary><?php esc_html_e( 'How to set up Discord notifications', 'wellweb-notify' ); ?></summary>
                    <ol>
                        <li><?php echo wp_kses( __( 'Open your Discord server and go to <strong>Server Settings → Integrations</strong>', 'wellweb-notify' ), array( 'strong' => array() ) ); ?></li>
                        <li><?php echo wp_kses( __( 'Click <strong>"Webhooks"</strong> then <strong>"New Webhook"</strong>', 'wellweb-notify' ), array( 'strong' => array() ) ); ?></li>
                        <li><?php esc_html_e( 'Name your webhook and select the channel for notifications', 'wellweb-notify' ); ?></li>
                        <li><?php esc_html_e( 'Click "Copy Webhook URL" and paste it in the field above', 'wellweb-notify' ); ?></li>
                        <li><?php esc_html_e( 'Click "Test" to verify the connection', 'wellweb-notify' ); ?></li>
                    </ol>
                </details>
                <?php endif; ?>

                <?php if ( $channel->get_slug() === 'google-chat' ) : ?>
                <!-- Google Chat Setup Guide -->
                <details class="ww-notify-setup-guide" style="margin-top: 16px;">
                    <summary><?php esc_html_e( 'How to set up Google Chat notifications', 'wellweb-notify' ); ?></summary>
                    <ol>
                        <li><?php esc_html_e( 'Open the Google Chat space where you want notifications', 'wellweb-notify' ); ?></li>
                        <li><?php echo wp_kses( __( 'Click the space name at the top → <strong>Space settings</strong>', 'wellweb-notify' ), array( 'strong' => array() ) ); ?></li>
                        <li><?php echo wp_kses( __( 'Go to <strong>Apps & integrations</strong> and click <strong>"Add webhooks"</strong>', 'wellweb-notify' ), array( 'strong' => array() ) ); ?></li>
                        <li><?php esc_html_e( 'Name your webhook and click "Save"', 'wellweb-notify' ); ?></li>
                        <li><?php esc_html_e( 'Copy the Webhook URL and paste it in the field above', 'wellweb-notify' ); ?></li>
                        <li><?php esc_html_e( 'Click "Test" to verify the connection', 'wellweb-notify' ); ?></li>
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
                    'description' => __( 'Send notifications via Viber Business bot', 'wellweb-notify' ),
                ),
                array(
                    'name'        => 'WhatsApp',
                    'icon'        => 'dashicons-phone',
                    'description' => __( 'Deliver form submissions via WhatsApp Business API', 'wellweb-notify' ),
                ),
            );

            foreach ( $pro_channels as $pro ) : ?>
            <div class="ww-settings-section ww-settings-section--teaser">
                <h3 class="ww-settings-section-header">
                    <span class="dashicons <?php echo esc_attr( $pro['icon'] ); ?>"></span>
                    <?php echo esc_html( $pro['name'] ); ?>
                    <span class="ww-notify-badge --pro">Pro</span>
                    <span style="margin-left: auto; font-weight: 400; font-size: 13px; color: #646970;">
                        <?php echo esc_html( $pro['description'] ); ?>
                    </span>
                </h3>
            </div>
            <?php endforeach; ?>

            <p class="ww-notify-pro-cta">
                <?php
                printf(
                    /* translators: %s: email link */
                    esc_html__( 'Need Viber or WhatsApp? Contact us at %s', 'wellweb-notify' ),
                    '<a href="mailto:support@wellweb.marketing?subject=' . rawurlencode( 'Need Viber or WhatsApp for Well Web Notify' ) . '">support@wellweb.marketing</a>'
                );
                ?>
            </p>
        <?php endif; ?>

        <!-- WooCommerce Orders -->
        <?php if ( class_exists( 'WooCommerce' ) ) :
            $woo_enabled = get_option( 'wellweb-notify-woo-enabled', false );
        ?>
        <div class="ww-settings-section ww-collapsible <?php echo empty( $woo_enabled ) ? 'ww-collapsed' : ''; ?>">
            <h3 class="ww-settings-section-header">
                <span class="ww-collapse-toggle dashicons dashicons-arrow-right-alt2"></span>
                <span class="dashicons dashicons-cart"></span>
                <?php esc_html_e( 'WooCommerce Orders', 'wellweb-notify' ); ?>

                <label class="ww-notify-toggle" style="margin-left: auto;">
                    <input type="checkbox"
                           name="wellweb-notify-woo-enabled"
                           value="1"
                           <?php checked( $woo_enabled ); ?> />
                    <span class="ww-notify-toggle-slider"></span>
                </label>
            </h3>
            <div class="ww-settings-section-body">
                <p class="description" style="margin-bottom: 12px;">
                    <?php esc_html_e( 'Choose which order events should trigger notifications:', 'wellweb-notify' ); ?>
                </p>
                <?php
                $woo_events = array(
                    'new-order'  => __( 'New order placed', 'wellweb-notify' ),
                    'processing' => __( 'Order → Processing', 'wellweb-notify' ),
                    'completed'  => __( 'Order → Completed', 'wellweb-notify' ),
                    'cancelled'  => __( 'Order → Cancelled', 'wellweb-notify' ),
                    'refunded'   => __( 'Order → Refunded', 'wellweb-notify' ),
                    'failed'     => __( 'Order → Failed', 'wellweb-notify' ),
                );
                foreach ( $woo_events as $event_key => $event_label ) :
                    $option_name = 'wellweb-notify-woo-event-' . $event_key;
                ?>
                <label class="ww-field" style="display: block; margin-bottom: 6px;">
                    <input type="checkbox"
                           name="<?php echo esc_attr( $option_name ); ?>"
                           value="1"
                           <?php checked( get_option( $option_name, false ) ); ?> />
                    <span><?php echo esc_html( $event_label ); ?></span>
                </label>
                <?php endforeach; ?>
                <p class="description" style="margin-top: 12px;">
                    <?php esc_html_e( 'Notifications include: customer name, email, phone, items, total, payment method, and location. Phone contact links are generated automatically from billing phone.', 'wellweb-notify' ); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <p class="submit">
            <?php submit_button( __( 'Save Settings', 'wellweb-notify' ), 'primary', 'submit', false ); ?>
        </p>

        <!-- Cross-promotion: More from Well Web -->
        <div class="ww-settings-section ww-notify-cross-promo">
            <h3 class="ww-settings-section-header">
                <span class="dashicons dashicons-admin-plugins"></span>
                <?php esc_html_e( 'More from Well Web Marketing', 'wellweb-notify' ); ?>
            </h3>
            <div class="ww-settings-section-body">
                <div class="ww-notify-promo-grid">
                    <div class="ww-notify-promo-card">
                        <h4>Well Web SEO</h4>
                        <p><?php esc_html_e( 'SEO meta tags, Open Graph, JSON-LD schema, XML sitemaps, and 301 redirects — all in one lightweight plugin.', 'wellweb-notify' ); ?></p>
                        <?php if ( is_plugin_active( 'wellweb-seo/index.php' ) ) : ?>
                            <span class="ww-notify-badge --success"><?php esc_html_e( 'Active', 'wellweb-notify' ); ?></span>
                        <?php else : ?>
                            <a href="mailto:support@wellweb.marketing?subject=<?php echo rawurlencode( 'Interested in Well Web SEO' ); ?>"><?php esc_html_e( 'Request', 'wellweb-notify' ); ?> &rarr;</a>
                        <?php endif; ?>
                    </div>
                    <div class="ww-notify-promo-card">
                        <h4>Well Web Multilang</h4>
                        <p><?php esc_html_e( 'Multilingual content management with language switcher, hreflang tags, and per-language SEO fields.', 'wellweb-notify' ); ?></p>
                        <?php if ( is_plugin_active( 'wellweb-multilang/index.php' ) ) : ?>
                            <span class="ww-notify-badge --success"><?php esc_html_e( 'Active', 'wellweb-notify' ); ?></span>
                        <?php else : ?>
                            <a href="mailto:support@wellweb.marketing?subject=<?php echo rawurlencode( 'Interested in Well Web Multilang' ); ?>"><?php esc_html_e( 'Request', 'wellweb-notify' ); ?> &rarr;</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <aside class="ww-branding-tag">
            <header>
                <img src="<?php echo esc_url( plugins_url( 'assets/img/ww-logo.png', __DIR__ ) ); ?>" alt="Well Web" width="32" height="32" style="border-radius: 4px;" />
                Well Web Marketing
            </header>
            <nav>
                <div><?php esc_html_e( 'Website', 'wellweb-notify' ); ?>: <a href="https://wellweb.marketing" target="_blank">wellweb.marketing</a></div>
                <div><?php esc_html_e( 'Email', 'wellweb-notify' ); ?>: <a href="mailto:support@wellweb.marketing">support@wellweb.marketing</a></div>
            </nav>
        </aside>
    </form>
    </div>
    <?php
}

// ─── Log page ─────────────────────────────────────────────────

function wellweb_notify_log_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Unauthorized', 'wellweb-notify' ) );
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin page filters, no state change
    $filter_channel = isset( $_GET['channel'] ) ? sanitize_text_field( wp_unslash( $_GET['channel'] ) ) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin page filters, no state change
    $filter_status  = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin page filters, no state change
    $page           = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

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
            <input type="hidden" name="page" value="wellweb-notify-log" />

            <select name="channel">
                <option value=""><?php esc_html_e( 'All channels', 'wellweb-notify' ); ?></option>
                <?php foreach ( $channels as $ch ) : ?>
                <option value="<?php echo esc_attr( $ch->get_slug() ); ?>" <?php selected( $filter_channel, $ch->get_slug() ); ?>>
                    <?php echo esc_html( $ch->get_label() ); ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select name="status">
                <option value=""><?php esc_html_e( 'All statuses', 'wellweb-notify' ); ?></option>
                <option value="success" <?php selected( $filter_status, 'success' ); ?>><?php esc_html_e( 'Success', 'wellweb-notify' ); ?></option>
                <option value="error" <?php selected( $filter_status, 'error' ); ?>><?php esc_html_e( 'Error', 'wellweb-notify' ); ?></option>
            </select>

            <?php submit_button( __( 'Filter', 'wellweb-notify' ), 'secondary', '', false ); ?>
        </form>
    </div>

    <!-- Log table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 120px;"><?php esc_html_e( 'Channel', 'wellweb-notify' ); ?></th>
                <th style="width: 200px;"><?php esc_html_e( 'Form', 'wellweb-notify' ); ?></th>
                <th style="width: 80px;"><?php esc_html_e( 'Status', 'wellweb-notify' ); ?></th>
                <th><?php esc_html_e( 'Error', 'wellweb-notify' ); ?></th>
                <th style="width: 160px;"><?php esc_html_e( 'Date', 'wellweb-notify' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $log['items'] ) ) : ?>
            <tr>
                <td colspan="5"><?php esc_html_e( 'No log entries found.', 'wellweb-notify' ); ?></td>
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
                    <span class="ww-notify-badge --success"><?php esc_html_e( 'OK', 'wellweb-notify' ); ?></span>
                    <?php else : ?>
                    <span class="ww-notify-badge --error"><?php esc_html_e( 'Error', 'wellweb-notify' ); ?></span>
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
            echo wp_kses_post( paginate_links( array(
                'base'    => add_query_arg( 'paged', '%#%' ),
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
        wp_send_json_error( __( 'Unknown channel.', 'wellweb-notify' ) );
    }

    if ( ! $channel->is_configured() ) {
        wp_send_json_error( __( 'Channel is not configured.', 'wellweb-notify' ) );
    }

    $result = $channel->send_test();

    if ( is_wp_error( $result ) ) {
        WellWeb_Notify_Log::log( $slug, 'Test', 'error', $result->get_error_message() );
        wp_send_json_error( $result->get_error_message() );
    }

    WellWeb_Notify_Log::log( $slug, 'Test', 'success' );
    wp_send_json_success( __( 'Test message sent successfully!', 'wellweb-notify' ) );
}

add_action( 'wp_ajax_wellweb_notify_save_channel', 'wellweb_notify_ajax_save_channel' );
function wellweb_notify_ajax_save_channel() {
    check_ajax_referer( 'wellweb_notify', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $slug = sanitize_text_field( wp_unslash( $_POST['channel'] ?? '' ) );
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is sanitized individually below
    $data = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array();

    if ( ! is_array( $data ) ) {
        wp_send_json_error( 'Invalid data' );
    }

    foreach ( $data as $key => $value ) {
        $option = sanitize_text_field( $key );

        // Only allow wellweb-notify-* options
        if ( strpos( $option, 'wellweb-notify-' ) !== 0 ) {
            continue;
        }

        // Encrypt tokens/webhooks
        if ( preg_match( '/(token|webhook)$/', $option ) ) {
            $value = sanitize_text_field( wp_unslash( $value ) );
            if ( ! empty( $value ) ) {
                $value = wellweb_notify_encrypt( $value );
            }
            update_option( $option, $value );
        } else {
            update_option( $option, sanitize_text_field( $value ) );
        }
    }

    wp_send_json_success( array( 'message' => __( 'Saved', 'wellweb-notify' ) ) );
}

// ─── Menu active state script ─────────────────────────────────

add_action( 'admin_footer', 'wellweb_notify_menu_script' );
function wellweb_notify_menu_script() {
    $screen = get_current_screen();
    if ( ! $screen || ( strpos( $screen->id, 'wellweb-notify' ) === false && $screen->id !== 'toplevel_page_wellweb' ) ) {
        return;
    }
    ?>
    <script>
    jQuery(function($){
        // Highlight Well Web parent menu
        $('#adminmenu .toplevel_page_wellweb')
            .removeClass('wp-not-current-submenu')
            .addClass('wp-has-current-submenu wp-menu-open');

        // Highlight Notify submenu
        $('#adminmenu').find('[href*="wellweb-notify"]').first()
            .addClass('current').closest('li').addClass('current');

        // Mark current tab
        var href = window.location.href;
        var menu = $('.ww-notify-menu');
        var current = menu.find('a').filter(function(){
            return href.indexOf($(this).attr('href')) !== -1;
        });
        if (current.length) {
            current.closest('li').addClass('current');
        } else {
            menu.find('li').first().addClass('current');
        }
    });
    </script>
    <?php
}

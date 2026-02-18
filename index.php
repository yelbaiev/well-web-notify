<?php
/**
 * Plugin Name:  Well Web Notify
 * Description:  Multi-channel form & order notifications — Telegram, Slack, Discord, Google Chat
 * Version: 1.0.0
 * Author: Well Web Marketing
 * Author URI: https://wellweb.marketing/
 * Plugin URI: https://wellweb.marketing/notify
 * Text Domain: wellweb-notify
 * Domain Path: /languages
 * Requires PHP: 8.2
 * Requires at least: 6.8
 * Tested up to: 6.9
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WELLWEB_NOTIFY_PLUGIN_FILE', __FILE__ );
define( 'WELLWEB_NOTIFY_VERSION', '1.0.0' );
define( 'WELLWEB_NOTIFY_DIR', plugin_dir_path( __FILE__ ) );
define( 'WELLWEB_NOTIFY_URL', plugin_dir_url( __FILE__ ) );

// Core functions (encryption, helpers)
include WELLWEB_NOTIFY_DIR . 'includes/functions.php';

// Site verification
include WELLWEB_NOTIFY_DIR . 'includes/class-site-verify.php';

// Channel interface and implementations
include WELLWEB_NOTIFY_DIR . 'includes/class-channel-interface.php';
include WELLWEB_NOTIFY_DIR . 'includes/channels/class-telegram.php';
include WELLWEB_NOTIFY_DIR . 'includes/channels/class-slack.php';
include WELLWEB_NOTIFY_DIR . 'includes/channels/class-discord.php';
include WELLWEB_NOTIFY_DIR . 'includes/channels/class-google-chat.php';

// Channel manager
include WELLWEB_NOTIFY_DIR . 'includes/class-channel-manager.php';

// Form interface and integrations
include WELLWEB_NOTIFY_DIR . 'includes/class-form-interface.php';
include WELLWEB_NOTIFY_DIR . 'includes/forms/class-cf7.php';
include WELLWEB_NOTIFY_DIR . 'includes/forms/class-wpforms.php';
include WELLWEB_NOTIFY_DIR . 'includes/forms/class-gravity-forms.php';
include WELLWEB_NOTIFY_DIR . 'includes/forms/class-ninja-forms.php';
include WELLWEB_NOTIFY_DIR . 'includes/forms/class-jetpack.php';
include WELLWEB_NOTIFY_DIR . 'includes/forms/class-woocommerce.php';

// Form manager
include WELLWEB_NOTIFY_DIR . 'includes/class-form-manager.php';

// Notification log
include WELLWEB_NOTIFY_DIR . 'includes/class-log.php';

// Admin settings
include WELLWEB_NOTIFY_DIR . 'includes/options.php';

// Dashboard widget
include WELLWEB_NOTIFY_DIR . 'includes/dashboard-widget.php';

// Site Health integration
include WELLWEB_NOTIFY_DIR . 'includes/site-health.php';

// Daily bot health check
include WELLWEB_NOTIFY_DIR . 'includes/class-health-check.php';

// Activation / Deactivation
register_activation_hook( __FILE__, 'wellweb_notify_activate' );
register_deactivation_hook( __FILE__, 'wellweb_notify_deactivate' );

function wellweb_notify_activate() {
    WellWeb_Notify_Log::create_table();
    WellWeb_Notify_Site_Verify::get_site_token(); // Generate token on first activation
}

function wellweb_notify_deactivate() {
    WellWeb_Notify_Health_Check::on_deactivation();
}

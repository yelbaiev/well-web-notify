=== Well Web Notify ===
Contributors: yelbaiev
Tags: telegram, slack, discord, notifications, forms
Requires at least: 6.8
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 1.0.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send form and WooCommerce order notifications to Telegram, Slack, Discord, and Google Chat. Auto-detects popular form plugins. No limits.

== Description ==

Well Web Notify sends instant notifications to Telegram, Slack, Discord, and Google Chat whenever someone submits a form or places a WooCommerce order on your WordPress site.

**Supported form plugins (auto-detected):**

* Contact Form 7
* WPForms
* Gravity Forms
* Ninja Forms
* Jetpack Contact Form
* WooCommerce orders

**Features:**

* Telegram notifications via Bot API — unlimited, no quotas
* Slack notifications via Incoming Webhooks
* Discord notifications via webhooks
* Google Chat notifications via space webhooks
* Auto-detects installed form plugins — no per-form configuration needed
* WooCommerce order notifications with configurable events (new order, processing, completed, cancelled, refunded, failed)
* Phone numbers in submissions get clickable contact links (Call, WhatsApp, Telegram, Viber)
* Notification log with filtering and pagination
* Daily bot health check verifies your Telegram bot is working
* Lightweight — no bloat, no external CSS/JS dependencies

**Need Viber or WhatsApp?**

Contact us at support@wellweb.marketing to set up Viber or WhatsApp notifications.

== External Services ==

This plugin connects to the following external services:

**Telegram Bot API**

When you configure a Telegram bot token and chat ID, the plugin sends notification messages via the Telegram Bot API at `https://api.telegram.org/`. This happens whenever a form is submitted, a WooCommerce order event occurs, or a daily health check runs.

* [Telegram Terms of Service](https://telegram.org/tos)
* [Telegram Privacy Policy](https://telegram.org/privacy)

**Slack API**

When you configure a Slack Incoming Webhook URL, the plugin sends notification messages to your Slack workspace via `https://hooks.slack.com/`.

* [Slack Terms of Service](https://slack.com/terms-of-service)
* [Slack Privacy Policy](https://slack.com/privacy-policy)

**Discord API**

When you configure a Discord Webhook URL, the plugin sends notification messages to your Discord server via `https://discord.com/api/webhooks/`.

* [Discord Terms of Service](https://discord.com/terms)
* [Discord Privacy Policy](https://discord.com/privacy)

**Google Chat API**

When you configure a Google Chat Webhook URL, the plugin sends notification messages to your Google Chat space via `https://chat.googleapis.com/`.

* [Google Terms of Service](https://policies.google.com/terms)
* [Google Privacy Policy](https://policies.google.com/privacy)

== Installation ==

1. Upload the `well-web-notify` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to Well Web > Notify in the admin menu
4. Configure your channels — Telegram, Slack, Discord, or Google Chat
5. Click "Test" to verify each connection

== Frequently Asked Questions ==

= Are there any limits on notifications? =

No. The free plugin sends unlimited notifications across all four channels with no quotas or monthly caps.

= Do I need to configure each form separately? =

No. The plugin auto-detects supported form plugins and captures all submissions automatically.

= How do I get Viber or WhatsApp notifications? =

Contact us at support@wellweb.marketing to set up Viber or WhatsApp channels.

= Does this work with WooCommerce? =

Yes. Enable WooCommerce order notifications in the settings and choose which order events trigger a notification.

== Screenshots ==

1. Channel settings — configure Telegram, Slack, Discord, and Google Chat
2. Notification log — filter by channel and status, with pagination

== Changelog ==

= 1.0.3 =
* Notification header now shows the site domain instead of the site title for cleaner chat previews
* Daily health-check message reworded to clearly explain what is being verified
* Optional review prompt on the plugin's own admin pages, shown only after the plugin has been in active use

= 1.0.2 =
* Added plugin banners, icons, and screenshots for WordPress.org listing
* Updated screenshots section to match provided assets

= 1.0.1 =
* Renamed plugin file from index.php to well-web-notify.php for WordPress.org compliance
* Updated text domain and admin slugs to well-web-notify
* Fixed dashboard widget styles loading on dashboard screen

= 1.0.0 =
* Initial public release on wordpress.org
* Telegram, Slack, Discord, and Google Chat notifications
* Auto-detects popular form plugins — no per-form configuration
* WooCommerce order notifications with configurable events
* Notification log with filtering and pagination
* Daily bot health check with email alerts
* Setup guides for all channels in admin UI

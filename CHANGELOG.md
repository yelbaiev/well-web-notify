# Well Web Notify - Changelog

## Version 1.0.7

- Fixed: a Jetpack submission can no longer arrive with only the phone number and no name. If the complete field set was not handed over during the request, the plugin now reads it back from Jetpack's saved entry before sending.

## Version 1.0.6

- Fixed: Jetpack form submissions arrived with all the fields sometimes and one field other times. Jetpack fires four signals per submission carrying different data, and the plugin let whichever arrived first send. It now collects them all and sends the most complete one, once, at the end of the request.
- Fixed: notifications no longer repeat a field twice. Jetpack hands the same answer over under two keys (`2_Телефон` and `3_Телефон`), and the plugin was merging both.
- Fixed: `Entry title`, `Entry permalink` and `Feedback id` no longer appear as form fields. The page a form sits on is now one link at the foot of the message instead.
- Changed: field labels are cleaned — Jetpack's `3_` numbering is stripped and Contact Form 7's `your-name` becomes `Your name`.
- Changed: messages now say which form they came from. Previously they gave the site domain and nothing else, so two forms on one site were indistinguishable.
- Changed: order notifications carry a link straight to the order, and form notifications a link to the page the form is on.
- Added: Akismet-flagged spam no longer produces a notification.
- Changed: the plugin has its own top-level admin menu, **Well Web Notify**, with two screens. **Settings** holds Channels, Sources and Message as one page with an index beside them that follows the scroll; **Activity** is the delivery log. It used to sit under a shared "Well Web" menu that no longer exists.
- Changed: the admin was rebuilt on the same design system as Well Web SEO. Every existing link and bookmark still works.
- Changed: the channels are one list with every switch and button in a column, and each row says what it is doing — Delivering, set up but switched off, or what it would be if you connected it.
- Fixed: a channel's on/off switch could not be clicked. The row was a `<details>` and the switch sat in its `<summary>`, where Chrome gives the click to the disclosure and the switch never sees it — so the control was inert, not merely awkward. The row is now an ordinary disclosure with a real button on the name.
- Changed: the Sources section leads with the form plugins you actually have; the ones you do not are one line.
- Changed: the country dialling code moved to Message, where it belongs — it is how the phone number is written into the notification, not something that triggers one.
- Added: the dialling code shows a worked example that updates as you type, so it can be checked without saving and waiting for a real submission.
- Added: Activity opens with a seven-day summary — attempted, delivered, failed — above the log, and its filters no longer reset themselves when you arrive from a link.
- Added: the Settings screen says so when notifications have been failing, with a link straight to them. Previously nothing on the screen you open would tell you.
- Added: saving now says "Saved".
- Fixed: the plugin's admin script no longer rewrites the WordPress sidebar in the browser. It was overriding WordPress's own menu highlighting, including Well Web SEO's.
- Added: the "More from Well Web" cards for Well Web SEO and Well Web Multilang are back, and say when a plugin is already installed.
- Removed: an unused AJAX endpoint that could write any of the plugin's options, including the encrypted credentials.

## Version 1.0.5

- Fixed: Jetpack form submissions now reliably reach all channels on Jetpack 15+. Jetpack 15 dropped the legacy `grunion_pre_message_sent` action and writes `_feedback_extra_fields` as an empty array on the new Forms-block path, which silently broke every previously working integration. The plugin now listens on `grunion_after_message_sent` and the modern feedback meta keys, with a defensive fallback on `transition_post_status`.
- Changed: per-request dedupe across the Jetpack hook listeners now claims the dispatch slot only at the moment of send, so an early-arriving empty meta write cannot block a later hook that has the real payload.

## Version 1.0.4

- Changed: Tested and confirmed compatible with WordPress 7.0
- Changed: Admin accent colors now follow the active admin color scheme (via --wp-admin-theme-color) instead of a fixed blue, so the UI matches the new WP 7.0 "Modern" admin theme
- Changed: Settings cards and the active tab now use rounded corners to match the Modern admin look

## Version 1.0.3

- Changed: Notification header now shows the site domain instead of the site title for cleaner chat previews
- Changed: Daily health-check message reworded to clearly explain what is being verified
- Added: Optional review prompt on the plugin's own admin pages, shown only after at least 14 days of active use

## Version 1.0.2

- Added: Plugin banners, icons, and screenshots for WordPress.org listing
- Changed: Updated screenshots section in readme.txt to match provided assets
- Changed: Excluded .wordpress-org directory from distribution zip

## Version 1.0.1

- Changed: Renamed plugin file from index.php to well-web-notify.php for WordPress.org compliance
- Changed: Updated text domain from wellweb-notify to well-web-notify across all files
- Changed: Updated admin page and asset handle slugs to use well-web-notify prefix
- Fixed: Dashboard widget styles now load on the dashboard screen
- Changed: Refreshed .pot translation file with corrected text domain

## Version 1.0.0

- Initial public release on wordpress.org
- Telegram notifications via Bot API
- Slack notifications via Incoming Webhooks
- Discord notifications via webhooks
- Google Chat notifications via space webhooks
- Auto-detects Contact Form 7, WPForms, Gravity Forms, Ninja Forms, and Jetpack
- WooCommerce order notifications with configurable events
- Phone numbers get clickable contact links (Call, WhatsApp, Telegram, Viber)
- Notification log with filtering and pagination
- Daily bot health check with email alerts
- Setup guides for all channels in admin UI

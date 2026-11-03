# Well Web Notify — how it works

A form is submitted, or an order changes state. The plugin turns that into one
normalised message and sends it to every chat channel the site has connected.

```
form plugin / WooCommerce
        │  raw submitted values, as that plugin stores them
        ▼
WellWeb_Notify_Form_Manager::handle_submission()
        │
        ▼
WellWeb_Notify_Message::build()      ← the only place raw data becomes readable
        │  { title, site, form_name, source_url, fields, phone }
        ▼
WellWeb_Notify_Channel_Manager::send_all()
        │
        ├── Telegram      HTML,      Bot API
        ├── Slack         mrkdwn,    Incoming Webhook
        ├── Discord       markdown,  webhook embed
        └── Google Chat   HTML,      space webhook card
                 │
                 ▼
        WellWeb_Notify_Log   one row per channel per send
```

## The moving parts

| File | Owns |
|---|---|
| `includes/class-message.php` | What a notification contains and how its fields are cleaned |
| `includes/class-channel-manager.php` | The channel registry, and fanning one message out to all of them |
| `includes/channels/*.php` | One channel each: credentials, rendering, transport |
| `includes/class-form-manager.php` | The integration registry, and the one entry point they all call |
| `includes/forms/*.php` | One form plugin each: which hooks, and what the payload looks like |
| `includes/class-log.php` | The `wp_wellweb_notify_log` table: writes, reads, 30-day cleanup |
| `includes/admin-nav.php` | The menu, the retired slugs, the page shell, the design system, the section list |
| `includes/admin-screen.php` | The settings screen: the index, the form, the save bar, the status strip, the family block |
| `includes/section-*.php` | One settings section each |
| `includes/screen-activity.php` | The delivery log screen |
| `includes/admin-settings.php` | Which options exist, how they are sanitised, the test endpoint |
| `includes/class-health-check.php` | The daily silent connection check, and the opt-in weekly summary |
| `includes/section-monitoring.php` | The Monitoring section: live domain, alert email, weekly summary switch, last check |
| `includes/class-site-verify.php` | The `/verify <token>` command that ties a Telegram group to this site |

## Field normalisation, and why it is centralised

Form plugins hand over keys designed for their own storage. Contact Form 7
gives input slugs (`your-name`) and its own `_wpcf7*` bookkeeping. Jetpack
numbers every field (`3_Телефон`) and merges `entry_title`, `entry_permalink`,
`feedback_id` and `email_marketing_consent` into the same array as the real
answers.

`WellWeb_Notify_Message::normalize_fields()` is the single answer to "what did
the person actually type":

- strips a leading `{digits}_` or `{digits} ` from the label
- drops internal keys, and anything beginning with `_`
- collapses duplicate label+value pairs; keeps distinct values under a numbered
  label rather than letting one overwrite the other
- flattens arrays, strips tags, and caps a value at 500 characters

`entry_title` and `entry_permalink` are not discarded — `build()` lifts them
into the message's `form_name` and `source_url`, which render as one link at
the foot.

## Jetpack: why the dispatch is deferred to `shutdown`

Jetpack exposes one submission through four signals carrying different data
(read from Jetpack 16.2, `jetpack-forms/src/contact-form/`):

| Signal | Payload |
|---|---|
| `_feedback_extra_fields` meta write | the *non-standard* fields only |
| `grunion_pre_message_sent` | all compiled fields + Jetpack's entry keys — only when an email will be sent |
| `grunion_after_message_sent` | same, and fires even for spam |
| `transition_post_status` | whatever the meta holds |

The meta write happens first. `get_legacy_extra_values()` re-keys the same
fields with a counter that continues past `get_all_values()`, so one phone
field can appear as both `2_Телефон` and `3_Телефон`.

Letting whichever signal arrives first dispatch produced two different messages
from one form: a single field when the meta listener won, and every field plus
the phone twice plus three internal keys when `grunion_after_message_sent` did.
Which one you got depended on whether `update_post_meta` happened to change the
stored value, since a no-op write fires no hook.

So `WellWeb_Notify_Jetpack` sends from none of them. Each listener *offers* its
payload with a rank (after-sent 30 > pre-sent 20 > meta 10 > transition 5); the
best offer per feedback post wins; the winner is sent once on `shutdown`, which
still runs after Jetpack's redirect-and-exit because WordPress registers it via
`register_shutdown_function`. Spam is skipped at that point, because by then
the feedback post has its final status.

If the winning offer is only a meta or transition one (rank below pre-sent),
`dispatch_pending()` reads the submission back through Jetpack's own
`Feedback::get( $post_id )->get_all_values()` — the same source its hooks are
built from — so a missed hook cannot produce a phone-only message. The call is
guarded by `class_exists`/`method_exists` and a `Throwable` catch; on any
failure the meta payload is sent as before.

`$extra_values` is deliberately never merged into `$all_values`.

## Admin

Two sidebar rows under one top-level item:

| Screen | Slug | Holds |
|---|---|---|
| Settings | `well-web-notify-settings` | Channels, Sources, Message, Monitoring — sections with a sticky index, the pattern Well Web SEO uses for its own Settings page |
| Activity | `well-web-notify-log` | The delivery log: counters, filters, table, pager |

Both slugs are the ones this plugin has had since 1.0.0, so every link that ever
existed still resolves. `well-web-notify-sources`, invented during a short-lived
three-screen build, 302s to `well-web-notify-settings#sources`.

`wellweb_notify_sections()` is the single list of settings sections; the index
and the body are both generated from it, and a section whose renderer is missing
drops out of both. The `wellweb_notify_sections` filter is the extension point.

### Why Activity is a screen and not a fourth section

It was a section, briefly. Its filters and its pager are GET navigations that
reload the page — which on a settings screen means quietly discarding whatever
was half-typed into a credentials field. Data you page through does not belong
inside a form you save.

The settings screen keeps exactly one pointer to it: `wellweb_notify_render_status_strip()`
shows a strip **only when something has actually failed** in the last seven
days, so a site whose notifications have started bouncing still says so on the
screen people open.

### One settings group, and why it was briefly two

The three settings sections share one `<form>` posting to `options.php`, so
there is one Save button and one settings group.

That matters beyond tidiness. `options.php` walks every option **registered** to
the submitted group and calls `update_option()` on each, passing `null` for
those absent from the request — and an unchecked checkbox is absent. While the
settings were split across two screens sharing a group, saving one screen
switched off everything on the other. Splitting the group fixed the symptom;
putting the settings on one form removed the cause.

Activity has its own `<form method="get">` for filtering, on its own screen.

### The section index

`assets/js/admin.js` marks the index entry for **the last section whose top has
passed 100px**, on a rAF-throttled scroll listener, with the bottom of the page
always meaning the last section.

Not an IntersectionObserver band, which was tried first and is wrong in a way
that only shows on a real page: with a band, two sections can be inside it at
once, so the rule has to be "first" (lags a section behind) or "last" (runs a
section ahead of a short one). Jumping to `#message` highlighted Activity,
because Message is four lines tall and Activity's top was in the band too. A
fixed line has one answer at every scroll position whatever the section heights
are.

### The design system

`assets/css/wpds.css` and `wpds-v2.css` are vendored verbatim from
`wellweb-seo/assets/css/`; `wpds-fixes.css` is the generic half of that
plugin's corrections. They load in a declared dependency chain, because a
correction written at the same specificity as the rule it corrects has to come
after it. `notify.css` holds only what is specific to this plugin.

Changes belong in the SEO copy, then come back here as a fresh copy. Editing
the vendored files locally is how the two plugins stop agreeing — which is why
one correction to an upstream rule lives in `notify.css` instead:

- **Page width.** WPDS-v2 declares `.wpds.wpds-v2 .wpds-page`, a *descendant*
  selector, while the class it names goes on the wrapper itself. It has
  therefore never matched, and every screen rendered at the browser's full
  width. Restated as a compound selector. Worth reporting upstream.

### Why the channel list is a grid

Each channel row lays its five parts out on fixed grid tracks
(`8px minmax(0,1fr) auto auto 12px`). An earlier version used a flex row in
which both the controls block and the disclosure chevron carried
`margin-inline-start: auto`; flexbox divides free space between multiple auto
margins, so each row's controls settled at an x determined by the length of its
channel name, and no two toggles, buttons or chevrons lined up. Fixed tracks
cannot do that, which is the point — the alignment is a property of the layout
rather than a coincidence of the content.

The same reasoning applies to the states. Four rows each badged "Not connected"
is a column of identical words; one secondary line per row, saying either what
the channel *is* or what it is *doing*, carries the same information and
teaches something on a first visit.

### A control cannot live inside `<summary>`

The channel row was a `<details>`, with the enable switch and the Send test
button in its `<summary>`. The switch did not work — at all. A `<label>`-wrapped
checkbox inside a `<summary>` is inert in Chrome: the summary's activation
behaviour claims the click, the label never forwards it to the control, and the
`<details>` does not even open. Checked against three variants in a live admin
page: the same label and checkbox outside a `<details>` toggles normally.

The first attempt to rescue it called `preventDefault()` on clicks landing on a
control inside the summary, to stop the row toggling. That cannot work either —
`preventDefault()` on a click whose target is a checkbox is exactly what cancels
the checkbox — and it made a dead switch look like a deliberate one.

So the row is a plain element, the name is a real
`<button aria-expanded aria-controls>`, and `assets/js/admin.js` shows and hides
the panel. The body ships **open** and the script collapses it, so a script that
fails to load leaves the credentials reachable rather than sealed away.

`<details>` is still right for the setup guide inside each row, which contains
only text.

### The switch is never disabled

It was, while a channel had no credentials — on the reasoning that switching on
a channel that cannot deliver promises something false. That reasoning cost more
than it bought: a disabled checkbox is not submitted, so `options.php` wrote it
`false`, and connecting a channel took two saves — paste the token, save,
discover the switch has woken up, switch it on, save again.

There are four states, not three, and the row says which one it is in. Delivery
already requires `is_enabled() && is_configured()`, so an enabled-but-unconnected
channel simply does not send.

### No client-side menu rewriting

Earlier versions shipped a script that rewrote `#adminmenu` on load to
force-highlight a shared parent item. WordPress decides highlighting
server-side via `parent_file` / `submenu_file`, and that script ran afterwards
and overrode it — including for Well Web SEO, whose nav depends on those
filters. Do not reintroduce it.

## Monitoring: live site vs staging, and the silent check

### Which copy is the live site

Most client sites have a staging copy, the plugin is deployed to both by CI,
and staging is refreshed by copying the live database. So a stored "this is
production" flag would be copied to staging on the first sync and be wrong.
`wellweb_notify_is_production()` uses two signals a database copy cannot carry:

1. `wp_get_environment_type()` — from `wp-config.php` or the host. Anything but
   `production` is not production.
2. `well-web-notify-production-domain`, compared with the host this request is
   running on. The copied value names the live domain, which staging is not.

With no domain recorded, a host matching a staging pattern (`staging.*`,
`dev-*`, `*.mystagingwebsite.com`, `*.kinsta.cloud`, `*.wpengine.com`, …, see
`wellweb_notify_host_looks_like_staging()`) is staging. On `admin_init`, a
manager on a production-type, non-staging-looking host with nothing recorded
claims the domain. The filter `wellweb_notify_is_production` has the last word.

What depends on it:

| On staging | Behaviour |
|---|---|
| Form and order notifications | Still sent; `WellWeb_Notify_Message::build()` prefixes the headline with `[Staging]` and sets `$meta['staging']` |
| Daily check | Returns early, stores empty results |
| Weekly summary | Not sent |
| Settings screen | Header meta says "Staging copy"; Monitoring explains why |

### The daily check posts nothing

Cron `wellweb_notify_health_check`, daily ~06:00 UTC. For each active channel:

1. A successful real delivery in the last 24 h (`WellWeb_Notify_Log::count_deliveries()`,
   which excludes the `Test`, `Health Check` and `Weekly summary` rows) counts
   as a pass, and the channel is not contacted.
2. Otherwise `check_connection()`, if the channel class has one. It is
   duck-typed rather than added to `WellWeb_Notify_Channel`, so third-party
   channels keep working; one without it is recorded as `skipped`.

| Channel | `check_connection()` |
|---|---|
| Telegram | `getMe`, `getChat`, then `getChatMember` for the bot: fails if it left, was kicked, was muted, or is not admin in a channel |
| Discord | `GET` on the webhook URL, which returns the webhook without posting |
| Slack | `POST {}`: a live webhook rejects it as 400 `no_text`/`invalid_payload`; a dead one answers 403/404/410 |
| Google Chat | `POST {}`: a live webhook rejects it as 400 `INVALID_ARGUMENT` about the message; a dead one answers 401/403/404 or a 400 about the key |

The Slack and Google Chat probes rely on how those services reject an empty
message, not on a documented read endpoint (neither has one).

Results go to `well-web-notify-health-results`. Only failures are written to
the log. Failures are emailed to `wellweb_notify_alert_email()`:
`well-web-notify-alert-email`, falling back to `admin_email`.

### Weekly summary

Cron `wellweb_notify_weekly_summary`, Mondays ~07:00 UTC, only if
`well-web-notify-weekly-summary` is on and the site is production. Each active
channel gets its own count of real deliveries in 7 days. The message is built
with `silent => true`: Telegram sends with `disable_notification`, Discord with
`flags: 4096` (SUPPRESS_NOTIFICATIONS); Slack and Google Chat have no
equivalent. Sends are logged as `Weekly summary`; a failed send is emailed like
a failed check.

## Credentials

Tokens and webhook URLs are stored encrypted (AES-256-CTR + HMAC-SHA256, key
derived from `LOGGED_IN_KEY` / `LOGGED_IN_SALT`) and never echoed back into the
DOM — a stored value shows as a mask in the field's placeholder while the input
itself is empty. An empty submission leaves the stored value alone, via the
`pre_update_option` guard in `includes/functions.php`.

## Extension points

| Hook | Use |
|---|---|
| `wellweb_notify_register_channels` | Register an additional `WellWeb_Notify_Channel` |
| `wellweb_notify_register_forms` | Register an additional `WellWeb_Notify_Form` |
| `wellweb_notify_message` | Last word on what a notification contains |
| `wellweb_notify_show_pro_teasers` | Hide the Viber/WhatsApp note |
| `wellweb_notify_sections` | Add a section to the screen, or take one away |
| `wellweb_notify_is_production` | Override whether this site counts as the live one |

A channel class may also implement `check_connection(): true|WP_Error` to take
part in the silent daily check.

A third-party channel written against the old `send( $subject, $body, $meta )`
signature still works: `$body` carries a plain-text rendering of the same
message, and `WellWeb_Notify_Message::ensure()` gives any channel a normalised
`$meta` whatever the caller passed.

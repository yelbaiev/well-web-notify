<?php
/**
 * Channels — where notifications go.
 *
 * ── One list, not four cards ────────────────────────────────────────────────
 *
 * An earlier version rendered each channel as its own top-level card. Two
 * things went wrong and both were visible in a screenshot.
 *
 * First, the rows did not line up. The head was a flex row in which BOTH
 * `.wpds-card__meta` and the disclosure chevron carried `margin-inline-start:
 * auto`, so the free space was split between them and every row's controls
 * landed at a different x depending on how long its channel name was. Four
 * toggles, four buttons, four chevrons, no two in a column.
 *
 * Second, four separate cards for four variants of one thing reads as four
 * unrelated panels. They are a list. So they are one list with divided rows,
 * and the row is a GRID with fixed tracks — which is what actually guarantees
 * the alignment, rather than hoping two auto margins resolve the same way.
 *
 * ── Why the row is not a <details> ──────────────────────────────────────────
 *
 * It was one, and the enable switch did not work. A `<label>`-wrapped checkbox
 * inside a `<summary>` is inert in Chrome: the summary's activation behaviour
 * claims the click, the label never forwards it to the control, and the details
 * does not open either — the click does nothing at all. Verified against three
 * variants in a live admin page: the same label and checkbox OUTSIDE a details
 * toggles normally, inside a summary it is dead.
 *
 * That is not a Chrome quirk to work around, it is the spec pointing at a
 * design error: `<summary>` is a button, and putting a checkbox inside a button
 * is asking two controls to own one click. A JS-driven disclosure with a real
 * `<button aria-expanded aria-controls>` on the name is the correct structure,
 * and it is what every settings row of this shape uses.
 *
 * The body is rendered OPEN and collapsed by the script on load, so a failure
 * to load the script leaves the credentials reachable rather than sealed away.
 *
 * ── The state is the subtitle ───────────────────────────────────────────────
 *
 * Every row used to carry a badge, so a fresh install showed "Not connected"
 * four times down the page: a column of identical words is noise, not status.
 * Each row now has one secondary line that is EITHER what the channel is (when
 * there is nothing to report) or what it is doing (when there is). The dot
 * carries the same information for a glance.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wellweb_notify_section_channels(): void {
    ?>
    <p class="wpds-panel__intro">
        <?php esc_html_e( 'Connect one, switch it on, and send yourself a test. Every channel gets every notification — there is no routing to set up.', 'well-web-notify' ); ?>
    </p>

    <div class="wellweb-notify-channels">
        <?php foreach ( WellWeb_Notify_Channel_Manager::instance()->get_channels() as $channel ) : ?>
            <?php wellweb_notify_render_channel_row( $channel ); ?>
        <?php endforeach; ?>
    </div>

    <?php wellweb_notify_render_pro_channels(); ?>
    <?php
}

/**
 * One channel, as a row of the list.
 */
function wellweb_notify_render_channel_row( WellWeb_Notify_Channel $channel ): void {
    $slug       = $channel->get_slug();
    $enabled    = $channel->is_enabled();
    $configured = $channel->is_configured();
    $body_id    = 'wellweb-notify-channel-' . $slug;

    // What a channel is, for a reader who has not connected it. Replaced by
    // what it is doing, once there is something to say.
    $blurbs = array(
        'telegram'    => __( 'Telegram Bot API. Unlimited, no quotas.', 'well-web-notify' ),
        'slack'       => __( 'An incoming webhook, into one Slack channel.', 'well-web-notify' ),
        'discord'     => __( 'A server webhook, into one Discord channel.', 'well-web-notify' ),
        'google-chat' => __( 'A space webhook, into one Google Chat space.', 'well-web-notify' ),
    );

    // Four states, because the switch and the credentials are independent and
    // every combination of them is reachable. The version that collapsed this
    // to three had to disable the switch until there were credentials, which
    // made connecting a channel take two saves: fill in the token, save,
    // discover the switch has woken up, switch it on, save again.
    if ( $enabled && $configured ) {
        $state_class = 'is-live';
        $state_text  = __( 'Delivering', 'well-web-notify' );
    } elseif ( $enabled ) {
        $state_class = 'is-off';
        $state_text  = __( 'Switched on, but not connected yet — add the details below', 'well-web-notify' );
    } elseif ( $configured ) {
        $state_class = 'is-off';
        $state_text  = __( 'Set up, but the switch is off', 'well-web-notify' );
    } else {
        $state_class = 'is-idle';
        $state_text  = $blurbs[ $slug ] ?? __( 'Not connected.', 'well-web-notify' );
    }

    // Anything half-done is worth opening; a channel that is simply delivering,
    // or simply untouched, is not.
    $open = $enabled !== $configured;
    ?>
    <div class="wellweb-notify-channel <?php echo esc_attr( $state_class ); ?><?php echo $open ? ' is-open' : ''; ?>"
         data-channel="<?php echo esc_attr( $slug ); ?>">
        <div class="wellweb-notify-channel__row">
            <span class="wellweb-notify-channel__dot" aria-hidden="true"></span>

            <button type="button"
                    class="wellweb-notify-channel__disclosure"
                    aria-expanded="<?php echo $open ? 'true' : 'false'; ?>"
                    aria-controls="<?php echo esc_attr( $body_id ); ?>">
                <span class="wellweb-notify-channel__name"><?php echo esc_html( $channel->get_label() ); ?></span>
                <span class="wellweb-notify-channel__state"><?php echo esc_html( $state_text ); ?></span>
            </button>

            <label class="wpds-toggle">
                <input type="checkbox"
                       name="well-web-notify-<?php echo esc_attr( $slug ); ?>-enabled"
                       value="1"
                       <?php checked( $enabled ); ?> />
                <span class="wpds-toggle__track"></span>
                <span class="screen-reader-text">
                    <?php
                    printf(
                        /* translators: %s: channel name */
                        esc_html__( 'Send notifications to %s', 'well-web-notify' ),
                        esc_html( $channel->get_label() )
                    );
                    ?>
                </span>
            </label>

            <span class="wellweb-notify-channel__test">
                <span class="wellweb-notify-test-result" role="status"></span>
                <button type="button"
                        class="wpds-btn wpds-btn--ghost wpds-btn--sm wellweb-notify-test"
                        data-channel="<?php echo esc_attr( $slug ); ?>"
                        <?php disabled( ! $configured ); ?>>
                    <?php esc_html_e( 'Send test', 'well-web-notify' ); ?>
                </button>
            </span>

            <span class="wellweb-notify-channel__chevron" aria-hidden="true"></span>
        </div>

        <?php // Rendered open; admin.js collapses it. See the file header. ?>
        <div class="wellweb-notify-channel__body" id="<?php echo esc_attr( $body_id ); ?>">
            <?php $channel->render_settings(); ?>
            <?php wellweb_notify_render_setup_guide( $slug ); ?>
        </div>
    </div>
    <?php
}

/**
 * The steps for connecting one channel.
 *
 * Kept in one place rather than four `if ( $slug === … )` blocks inline, and
 * shortened: every guide used to end with two steps saying "paste it in the
 * field above" and "press Test", which the field and the button are already
 * sitting there saying.
 */
function wellweb_notify_render_setup_guide( string $slug ): void {
    $guides = array(
        'telegram'    => array(
            __( 'Open Telegram, find <strong>@BotFather</strong>, send <code>/newbot</code>, and follow the prompts.', 'well-web-notify' ),
            __( 'Copy the token BotFather gives you into <strong>Bot token</strong>.', 'well-web-notify' ),
            __( 'Add the bot to the group or channel that should receive notifications.', 'well-web-notify' ),
            __( 'Send any message in that group, then open <code>https://api.telegram.org/bot&lt;YOUR_TOKEN&gt;/getUpdates</code> and copy the <code>chat</code> → <code>id</code> value into <strong>Chat ID</strong>.', 'well-web-notify' ),
        ),
        'slack'       => array(
            __( 'At <strong>api.slack.com/apps</strong>, create a new app “from scratch” in your workspace.', 'well-web-notify' ),
            __( 'Turn on <strong>Incoming Webhooks</strong>, then <strong>Add New Webhook to Workspace</strong> and pick a channel.', 'well-web-notify' ),
            __( 'Copy the webhook URL into <strong>Webhook URL</strong>.', 'well-web-notify' ),
        ),
        'discord'     => array(
            __( 'In your server, open <strong>Server Settings → Integrations → Webhooks</strong>.', 'well-web-notify' ),
            __( 'Create a webhook, name it, and choose the channel for notifications.', 'well-web-notify' ),
            __( 'Copy the webhook URL into <strong>Webhook URL</strong>.', 'well-web-notify' ),
        ),
        'google-chat' => array(
            __( 'Open the Google Chat space, click its name, then <strong>Space settings</strong>.', 'well-web-notify' ),
            __( 'Under <strong>Apps &amp; integrations</strong>, choose <strong>Add webhooks</strong> and name it.', 'well-web-notify' ),
            __( 'Copy the webhook URL into <strong>Webhook URL</strong>.', 'well-web-notify' ),
        ),
    );

    if ( empty( $guides[ $slug ] ) ) {
        return;
    }

    $allowed = array( 'strong' => array(), 'code' => array(), 'em' => array() );
    ?>
    <details class="wellweb-notify-guide">
        <summary><?php esc_html_e( 'How to connect this', 'well-web-notify' ); ?></summary>
        <ol>
            <?php foreach ( $guides[ $slug ] as $step ) : ?>
                <li><?php echo wp_kses( $step, $allowed ); ?></li>
            <?php endforeach; ?>
        </ol>
    </details>
    <?php
}

/**
 * Viber and WhatsApp, which this plugin does not do.
 *
 * One muted line under the list. It used to be two empty cards the same size
 * and shape as the four real channels, so the page read as six channels of
 * which two were mysteriously broken.
 */
function wellweb_notify_render_pro_channels(): void {
    /**
     * Filter whether to mention the channels that need a paid account.
     * The Pro add-on sets this to false once it provides them.
     */
    if ( ! apply_filters( 'wellweb_notify_show_pro_teasers', true ) ) {
        return;
    }
    ?>
    <p class="wellweb-notify-pro-note">
        <?php
        printf(
            /* translators: %s: support email link */
            esc_html__( 'Viber and WhatsApp need a business account with the provider, which we set up for you — write to %s.', 'well-web-notify' ),
            '<a href="mailto:support@wellweb.marketing?subject=' . rawurlencode( 'Viber / WhatsApp for Well Web Notify' ) . '">support@wellweb.marketing</a>'
        );
        ?>
    </p>
    <?php
}

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WellWeb_Notify_Discord implements WellWeb_Notify_Channel {

    public function get_slug(): string {
        return 'discord';
    }

    public function get_label(): string {
        return 'Discord';
    }

    public function get_icon(): string {
        return 'dashicons-groups';
    }

    public function is_configured(): bool {
        return ! empty( $this->get_webhook() );
    }

    public function is_enabled(): bool {
        return (bool) wellweb_notify_get_option( 'discord-enabled', false );
    }

    public function send( string $subject, string $body, array $meta = [] ) {
        $webhook = $this->get_webhook();

        if ( empty( $webhook ) ) {
            return new WP_Error( 'not_configured', __( 'Discord is not configured.', 'well-web-notify' ) );
        }

        // Build body from structured fields if available
        if ( ! empty( $meta['fields'] ) ) {
            $lines = array();
            foreach ( $meta['fields'] as $label => $value ) {
                $lines[] = "**{$label}:** {$value}";
            }
            $formatted_body = implode( "\n", $lines );
        } else {
            $formatted_body = $body;
        }

        $embeds = array(
            array(
                'title'       => get_bloginfo( 'name' ),
                'description' => $formatted_body,
                'color'       => 3447003, // Blue
                'timestamp'   => gmdate( 'c' ),
            ),
        );

        // Contact links (Discord only supports https:// clickable links)
        if ( ! empty( $meta['phone'] ) ) {
            $links = wellweb_notify_phone_links( $meta['phone'] );
            $embeds[0]['fields'] = array(
                array(
                    'name'   => "\xE2\x98\x8E\xEF\xB8\x8F Contact",
                    'value'  => sprintf(
                        "`%s`\n\n[WhatsApp](%s)  |  [Telegram](%s)",
                        $meta['phone'],
                        $links['whatsapp'],
                        $links['telegram']
                    ),
                    'inline' => false,
                ),
            );
        }

        $payload = array(
            'content' => null,
            'embeds'  => $embeds,
        );

        // Branded bot name & avatar override
        $bot_name = wellweb_notify_get_option( 'discord-bot-name', '' );
        if ( ! empty( $bot_name ) ) {
            $payload['username'] = $bot_name;
        }

        $avatar_url = wellweb_notify_get_option( 'discord-avatar-url', '' );
        if ( ! empty( $avatar_url ) ) {
            $payload['avatar_url'] = $avatar_url;
        }

        $response = wp_remote_post( $webhook, array(
            'timeout' => 15,
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $payload ),
        ) );

        return $this->handle_response( $response );
    }

    public function send_test() {
        return $this->send(
            __( 'Well Web Notify — Test', 'well-web-notify' ),
            __( 'This is a test message from Well Web Notify plugin.', 'well-web-notify' ),
            array( 'form_name' => 'Test' )
        );
    }

    public function render_settings(): void {
        $webhook    = $this->get_webhook();
        $bot_name   = wellweb_notify_get_option( 'discord-bot-name', '' );
        $avatar_url = wellweb_notify_get_option( 'discord-avatar-url', '' );
        ?>
        <div class="ww-notify-channel-settings" data-channel="discord">
            <label class="ww-field ww-field-labeled">
                <span class="ww-field-label"><?php esc_html_e( 'Webhook URL', 'well-web-notify' ); ?></span>
                <input type="password"
                       name="well-web-notify-discord-webhook"
                       value=""
                       placeholder="<?php echo $webhook ? esc_attr( wellweb_notify_mask( $webhook ) ) : 'https://discord.com/api/webhooks/...'; ?>"
                       class="regular-text"
                       autocomplete="off" />
                <span class="description">
                    <?php esc_html_e( 'Server Settings → Integrations → Webhooks → New Webhook.', 'well-web-notify' ); ?>
                </span>
            </label>

            <label class="ww-field ww-field-labeled">
                <span class="ww-field-label"><?php esc_html_e( 'Bot Display Name', 'well-web-notify' ); ?></span>
                <input type="text"
                       name="well-web-notify-discord-bot-name"
                       value="<?php echo esc_attr( $bot_name ); ?>"
                       placeholder="Well Web Notify"
                       class="regular-text" />
                <span class="description"><?php esc_html_e( 'Overrides the webhook default name. Leave empty to use webhook settings.', 'well-web-notify' ); ?></span>
            </label>

            <label class="ww-field ww-field-labeled">
                <span class="ww-field-label"><?php esc_html_e( 'Bot Avatar URL', 'well-web-notify' ); ?></span>
                <input type="url"
                       name="well-web-notify-discord-avatar-url"
                       value="<?php echo esc_attr( $avatar_url ); ?>"
                       placeholder="https://example.com/avatar.png"
                       class="regular-text" />
                <span class="description"><?php esc_html_e( 'Overrides the webhook default avatar. Must be a public URL.', 'well-web-notify' ); ?></span>
            </label>
        </div>
        <?php
    }

    public function get_settings(): array {
        return array(
            'well-web-notify-discord-enabled'    => 'wellweb_notify_sanitize_checkbox',
            'well-web-notify-discord-webhook'    => 'wellweb_notify_sanitize_encrypted',
            'well-web-notify-discord-bot-name'   => 'sanitize_text_field',
            'well-web-notify-discord-avatar-url' => 'esc_url_raw',
        );
    }

    private function get_webhook(): string {
        return wellweb_notify_get_encrypted( 'discord-webhook' );
    }

    private function handle_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );

        // Discord returns 204 No Content on success
        if ( $code === 204 || $code === 200 ) {
            return true;
        }

        $body = wp_remote_retrieve_body( $response );
        return new WP_Error( 'discord_error', "HTTP {$code}: {$body}" );
    }
}

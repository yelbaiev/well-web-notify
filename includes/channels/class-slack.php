<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WellWeb_Notify_Slack implements WellWeb_Notify_Channel {

    public function get_slug(): string {
        return 'slack';
    }

    public function get_label(): string {
        return 'Slack';
    }

    public function get_icon(): string {
        return 'dashicons-share';
    }

    public function is_configured(): bool {
        return ! empty( $this->get_webhook() );
    }

    public function is_enabled(): bool {
        return (bool) wellweb_notify_get_option( 'slack-enabled', false );
    }

    public function send( string $subject, string $body, array $meta = [] ) {
        $webhook = $this->get_webhook();

        if ( empty( $webhook ) ) {
            return new WP_Error( 'not_configured', __( 'Slack is not configured.', 'well-web-notify' ) );
        }

        // Build body from structured fields if available
        if ( ! empty( $meta['fields'] ) ) {
            $lines = array();
            foreach ( $meta['fields'] as $label => $value ) {
                $lines[] = "*{$label}:* {$value}";
            }
            $formatted_body = implode( "\n", $lines );
        } else {
            $formatted_body = $body;
        }

        $blocks = array(
            array(
                'type' => 'header',
                'text' => array(
                    'type' => 'plain_text',
                    'text' => wellweb_notify_site_domain(),
                ),
            ),
            array(
                'type' => 'section',
                'text' => array(
                    'type' => 'mrkdwn',
                    'text' => $formatted_body,
                ),
            ),
        );

        // Contact links
        if ( ! empty( $meta['phone'] ) ) {
            $links    = wellweb_notify_phone_links( $meta['phone'] );
            $blocks[] = array(
                'type'     => 'context',
                'elements' => array(
                    array(
                        'type' => 'mrkdwn',
                        'text' => sprintf(
                            ':phone: `%s`   <%s|:speech_balloon: WhatsApp>   <%s|:small_airplane: Telegram>   <%s|:iphone: Viber>',
                            $meta['phone'],
                            $links['whatsapp'],
                            $links['telegram'],
                            $links['viber_web']
                        ),
                    ),
                ),
            );
        }

        $payload = array(
            'text'   => $subject . "\n" . $body,
            'blocks' => $blocks,
        );

        // Branded bot name & icon override
        $bot_name = wellweb_notify_get_option( 'slack-bot-name', '' );
        if ( ! empty( $bot_name ) ) {
            $payload['username'] = $bot_name;
        }

        $icon_url = wellweb_notify_get_option( 'slack-icon-url', '' );
        if ( ! empty( $icon_url ) ) {
            $payload['icon_url'] = $icon_url;
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
        $webhook  = $this->get_webhook();
        $bot_name = wellweb_notify_get_option( 'slack-bot-name', '' );
        $icon_url = wellweb_notify_get_option( 'slack-icon-url', '' );
        ?>
        <div class="ww-notify-channel-settings" data-channel="slack">
            <label class="ww-field ww-field-labeled">
                <span class="ww-field-label"><?php esc_html_e( 'Webhook URL', 'well-web-notify' ); ?></span>
                <input type="password"
                       name="well-web-notify-slack-webhook"
                       value=""
                       placeholder="<?php echo $webhook ? esc_attr( wellweb_notify_mask( $webhook ) ) : 'https://hooks.slack.com/services/...'; ?>"
                       class="regular-text"
                       autocomplete="off" />
                <span class="description">
                    <?php esc_html_e( 'Create an Incoming Webhook in your Slack workspace settings.', 'well-web-notify' ); ?>
                </span>
            </label>

            <label class="ww-field ww-field-labeled">
                <span class="ww-field-label"><?php esc_html_e( 'Bot Display Name', 'well-web-notify' ); ?></span>
                <input type="text"
                       name="well-web-notify-slack-bot-name"
                       value="<?php echo esc_attr( $bot_name ); ?>"
                       placeholder="Well Web Notify"
                       class="regular-text" />
                <span class="description"><?php esc_html_e( 'Overrides the webhook default name. Leave empty to use Slack app settings.', 'well-web-notify' ); ?></span>
            </label>

            <label class="ww-field ww-field-labeled">
                <span class="ww-field-label"><?php esc_html_e( 'Bot Icon URL', 'well-web-notify' ); ?></span>
                <input type="url"
                       name="well-web-notify-slack-icon-url"
                       value="<?php echo esc_attr( $icon_url ); ?>"
                       placeholder="https://example.com/icon.png"
                       class="regular-text" />
                <span class="description"><?php esc_html_e( 'Overrides the webhook default icon. Must be a public image URL.', 'well-web-notify' ); ?></span>
            </label>
        </div>
        <?php
    }

    public function get_settings(): array {
        return array(
            'well-web-notify-slack-enabled'  => 'wellweb_notify_sanitize_checkbox',
            'well-web-notify-slack-webhook'  => 'wellweb_notify_sanitize_encrypted',
            'well-web-notify-slack-bot-name' => 'sanitize_text_field',
            'well-web-notify-slack-icon-url' => 'esc_url_raw',
        );
    }

    private function get_webhook(): string {
        return wellweb_notify_get_encrypted( 'slack-webhook' );
    }

    private function handle_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code === 200 && $body === 'ok' ) {
            return true;
        }

        return new WP_Error( 'slack_error', "HTTP {$code}: {$body}" );
    }
}

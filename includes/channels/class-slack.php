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

        $meta = WellWeb_Notify_Message::ensure( $meta, $subject, $body );

        $blocks = array(
            array(
                'type' => 'header',
                'text' => array(
                    'type' => 'plain_text',
                    'text' => $meta['title'],
                ),
            ),
        );

        $context = WellWeb_Notify_Message::context_line( $meta );
        if ( $context !== '' ) {
            $blocks[] = array(
                'type'     => 'context',
                'elements' => array(
                    array( 'type' => 'mrkdwn', 'text' => self::escape( $context ) ),
                ),
            );
        }

        $lines = WellWeb_Notify_Message::field_lines( $meta, '*', '*', array( __CLASS__, 'escape' ) );
        if ( $lines !== '' ) {
            $blocks[] = array(
                'type' => 'section',
                'text' => array( 'type' => 'mrkdwn', 'text' => $lines ),
            );
        }

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

        if ( ! empty( $meta['source_url'] ) ) {
            $blocks[] = array(
                'type'     => 'context',
                'elements' => array(
                    array(
                        'type' => 'mrkdwn',
                        'text' => sprintf(
                            '<%s|%s>',
                            $meta['source_url'],
                            self::escape( WellWeb_Notify_Message::source_label( $meta ) )
                        ),
                    ),
                ),
            );
        }

        $payload = array(
            // Fallback text: what a notification preview and a screen reader get.
            'text'   => $meta['title'] . ( $context !== '' ? ' — ' . $context : '' ),
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

    /**
     * Slack's three reserved characters in mrkdwn text.
     *
     * https://api.slack.com/reference/surfaces/formatting#escaping
     */
    public static function escape( string $text ): string {
        return str_replace( array( '&', '<', '>' ), array( '&amp;', '&lt;', '&gt;' ), $text );
    }

    public function send_test() {
        $meta = wellweb_notify_test_message();
        return $this->send( $meta['title'], $meta['note'], $meta );
    }

    public function render_settings(): void {
        $webhook = $this->get_webhook();

        wellweb_notify_render_fields( array(
            array(
                'name'        => 'well-web-notify-slack-webhook',
                'label'       => __( 'Webhook URL', 'well-web-notify' ),
                'type'        => 'password',
                'masked'      => true,
                'placeholder' => $webhook ? wellweb_notify_mask( $webhook ) : 'https://hooks.slack.com/services/…',
                'hint'        => __( 'An Incoming Webhook, which decides the workspace and the channel.', 'well-web-notify' ),
            ),
            array(
                'name'        => 'well-web-notify-slack-bot-name',
                'label'       => __( 'Display name', 'well-web-notify' ),
                'type'        => 'text',
                'value'       => wellweb_notify_get_option( 'slack-bot-name', '' ),
                'placeholder' => 'Well Web Notify',
                'hint'        => __( 'Optional. Leave empty to use whatever the Slack app is called.', 'well-web-notify' ),
            ),
            array(
                'name'        => 'well-web-notify-slack-icon-url',
                'label'       => __( 'Icon URL', 'well-web-notify' ),
                'type'        => 'url',
                'value'       => wellweb_notify_get_option( 'slack-icon-url', '' ),
                'placeholder' => 'https://example.com/icon.png',
                'hint'        => __( 'Optional. Must be a publicly reachable image.', 'well-web-notify' ),
            ),
        ) );
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

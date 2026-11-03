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

        $meta = WellWeb_Notify_Message::ensure( $meta, $subject, $body );

        $lines = WellWeb_Notify_Message::field_lines( $meta, '**', '**', array( __CLASS__, 'escape' ) );

        $embed = array(
            'title'       => $meta['title'],
            'description' => $lines,
            'color'       => 3447003, // Blue
            'timestamp'   => gmdate( 'c' ),
            'footer'      => array( 'text' => WellWeb_Notify_Message::context_line( $meta ) ),
        );

        if ( ! empty( $meta['source_url'] ) ) {
            $embed['url'] = $meta['source_url'];
        }

        if ( ! empty( $meta['phone'] ) ) {
            $links            = wellweb_notify_phone_links( $meta['phone'] );
            $embed['fields']  = array(
                array(
                    'name'   => "\xE2\x98\x8E\xEF\xB8\x8F " . __( 'Contact', 'well-web-notify' ),
                    'value'  => sprintf(
                        "`%s`\n[WhatsApp](%s)  ·  [Telegram](%s)  ·  [Viber](%s)",
                        $meta['phone'],
                        $links['whatsapp'],
                        $links['telegram'],
                        $links['viber_web']
                    ),
                    'inline' => false,
                ),
            );
        }

        $payload = array(
            'content' => null,
            'embeds'  => array( $embed ),
        );

        // SUPPRESS_NOTIFICATIONS: delivered without a ping, like Telegram's
        // disable_notification. Used for the weekly summary.
        if ( ! empty( $meta['silent'] ) ) {
            $payload['flags'] = 4096;
        }

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

    /**
     * Neutralise Discord markdown in submitted text, so a visitor typing
     * `**` or a backtick cannot restyle the message they arrive in.
     */
    public static function escape( string $text ): string {
        return preg_replace( '/([\\\\*_`~|>])/', '\\\\$1', $text );
    }

    public function send_test() {
        $meta = wellweb_notify_test_message();
        return $this->send( $meta['title'], $meta['note'], $meta );
    }

    public function render_settings(): void {
        $webhook = $this->get_webhook();

        wellweb_notify_render_fields( array(
            array(
                'name'        => 'well-web-notify-discord-webhook',
                'label'       => __( 'Webhook URL', 'well-web-notify' ),
                'type'        => 'password',
                'masked'      => true,
                'placeholder' => $webhook ? wellweb_notify_mask( $webhook ) : 'https://discord.com/api/webhooks/…',
                'hint'        => __( 'Server Settings → Integrations → Webhooks. The webhook decides the channel.', 'well-web-notify' ),
            ),
            array(
                'name'        => 'well-web-notify-discord-bot-name',
                'label'       => __( 'Display name', 'well-web-notify' ),
                'type'        => 'text',
                'value'       => wellweb_notify_get_option( 'discord-bot-name', '' ),
                'placeholder' => 'Well Web Notify',
                'hint'        => __( 'Optional. Leave empty to use the webhook\'s own name.', 'well-web-notify' ),
            ),
            array(
                'name'        => 'well-web-notify-discord-avatar-url',
                'label'       => __( 'Avatar URL', 'well-web-notify' ),
                'type'        => 'url',
                'value'       => wellweb_notify_get_option( 'discord-avatar-url', '' ),
                'placeholder' => 'https://example.com/avatar.png',
                'hint'        => __( 'Optional. Must be a publicly reachable image.', 'well-web-notify' ),
            ),
        ) );
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

    /**
     * Prove the webhook still exists, without posting to it.
     *
     * A GET on a webhook URL returns the webhook's own details and sends
     * nothing; a deleted webhook answers 404 and a bad token 401.
     *
     * @return true|WP_Error
     */
    public function check_connection() {
        $webhook = $this->get_webhook();
        if ( empty( $webhook ) ) {
            return new WP_Error( 'not_configured', __( 'Discord is not configured.', 'well-web-notify' ) );
        }

        $response = wp_remote_get( $webhook, array( 'timeout' => 15 ) );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code === 200 ) {
            return true;
        }

        return new WP_Error( 'discord_error', "HTTP {$code}: " . wp_remote_retrieve_body( $response ) );
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

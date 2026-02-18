<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WellWeb_Notify_Google_Chat implements WellWeb_Notify_Channel {

    public function get_slug(): string {
        return 'google-chat';
    }

    public function get_label(): string {
        return 'Google Chat';
    }

    public function get_icon(): string {
        return 'dashicons-email-alt';
    }

    public function is_configured(): bool {
        return ! empty( $this->get_webhook() );
    }

    public function is_enabled(): bool {
        return (bool) wellweb_notify_get_option( 'google-chat-enabled', false );
    }

    public function send( string $subject, string $body, array $meta = [] ) {
        $webhook = $this->get_webhook();

        if ( empty( $webhook ) ) {
            return new WP_Error( 'not_configured', __( 'Google Chat is not configured.', 'wellweb-notify' ) );
        }

        // Build body from structured fields if available
        if ( ! empty( $meta['fields'] ) ) {
            $lines = array();
            foreach ( $meta['fields'] as $label => $value ) {
                $lines[] = '<b>' . esc_html( $label ) . ':</b> ' . esc_html( $value );
            }
            $formatted_body = implode( '<br>', $lines );
        } else {
            $formatted_body = nl2br( esc_html( $body ) );
        }

        $cards = array(
            array(
                'header' => array(
                    'title' => get_bloginfo( 'name' ),
                ),
                'sections' => array(
                    array(
                        'widgets' => array(
                            array(
                                'textParagraph' => array(
                                    'text' => $formatted_body,
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );

        // Contact links
        if ( ! empty( $meta['phone'] ) ) {
            $links = wellweb_notify_phone_links( $meta['phone'] );
            $cards[0]['sections'][] = array(
                'widgets' => array(
                    array(
                        'textParagraph' => array(
                            'text' => sprintf(
                                '&#9742;&#65039; <b>%s</b><br><br><a href="%s">&#128172; WhatsApp</a> &nbsp; &nbsp; <a href="%s">&#9992;&#65039; Telegram</a> &nbsp; &nbsp; <a href="%s">&#128241; Viber</a>',
                                esc_html( $meta['phone'] ),
                                esc_attr( $links['whatsapp'] ),
                                esc_attr( $links['telegram'] ),
                                esc_attr( $links['viber_web'] )
                            ),
                        ),
                    ),
                ),
            );
        }

        $response = wp_remote_post( $webhook, array(
            'timeout' => 15,
            'headers' => array( 'Content-Type' => 'application/json; charset=UTF-8' ),
            'body'    => wp_json_encode( array( 'cards' => $cards ) ),
        ) );

        return $this->handle_response( $response );
    }

    public function send_test() {
        return $this->send(
            __( 'Well Web Notify — Test', 'wellweb-notify' ),
            __( 'This is a test message from Well Web Notify plugin.', 'wellweb-notify' ),
            array( 'form_name' => 'Test' )
        );
    }

    public function render_settings(): void {
        $webhook = $this->get_webhook();
        ?>
        <div class="ww-notify-channel-settings" data-channel="google-chat">
            <label class="ww-field ww-field-labeled">
                <span class="ww-field-label"><?php esc_html_e( 'Webhook URL', 'wellweb-notify' ); ?></span>
                <input type="password"
                       name="wellweb-notify-google-chat-webhook"
                       value=""
                       placeholder="<?php echo $webhook ? esc_attr( wellweb_notify_mask( $webhook ) ) : 'https://chat.googleapis.com/v1/spaces/...'; ?>"
                       class="regular-text"
                       autocomplete="off" />
                <span class="description">
                    <?php esc_html_e( 'Space settings → Apps & integrations → Webhooks. Set bot name and avatar in Google Cloud Console.', 'wellweb-notify' ); ?>
                </span>
            </label>
        </div>
        <?php
    }

    public function get_settings(): array {
        return array(
            'wellweb-notify-google-chat-enabled' => 'wellweb_notify_sanitize_checkbox',
            'wellweb-notify-google-chat-webhook' => 'wellweb_notify_sanitize_encrypted',
        );
    }

    private function get_webhook(): string {
        return wellweb_notify_get_encrypted( 'google-chat-webhook' );
    }

    private function handle_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( $code === 200 ) {
            return true;
        }

        $body = wp_remote_retrieve_body( $response );
        return new WP_Error( 'google_chat_error', "HTTP {$code}: {$body}" );
    }
}

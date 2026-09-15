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
            return new WP_Error( 'not_configured', __( 'Google Chat is not configured.', 'well-web-notify' ) );
        }

        $meta = WellWeb_Notify_Message::ensure( $meta, $subject, $body );

        $header = array( 'title' => $meta['title'] );

        $context = WellWeb_Notify_Message::context_line( $meta );
        if ( $context !== '' ) {
            $header['subtitle'] = $context;
        }

        $sections = array();

        $lines = WellWeb_Notify_Message::field_lines( $meta, '<b>', '</b>', 'esc_html' );
        if ( $lines !== '' ) {
            $sections[] = array(
                'widgets' => array(
                    array( 'textParagraph' => array( 'text' => str_replace( "\n", '<br>', $lines ) ) ),
                ),
            );
        }

        if ( ! empty( $meta['phone'] ) ) {
            $links      = wellweb_notify_phone_links( $meta['phone'] );
            $sections[] = array(
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

        if ( ! empty( $meta['source_url'] ) ) {
            $sections[] = array(
                'widgets' => array(
                    array(
                        'textParagraph' => array(
                            'text' => sprintf(
                                '<a href="%s">%s &rarr;</a>',
                                esc_attr( $meta['source_url'] ),
                                esc_html( WellWeb_Notify_Message::source_label( $meta ) )
                            ),
                        ),
                    ),
                ),
            );
        }

        $cards = array(
            array(
                'header'   => $header,
                'sections' => $sections,
            ),
        );

        $response = wp_remote_post( $webhook, array(
            'timeout' => 15,
            'headers' => array( 'Content-Type' => 'application/json; charset=UTF-8' ),
            'body'    => wp_json_encode( array( 'cards' => $cards ) ),
        ) );

        return $this->handle_response( $response );
    }

    public function send_test() {
        $meta = wellweb_notify_test_message();
        return $this->send( $meta['title'], $meta['note'], $meta );
    }

    public function render_settings(): void {
        $webhook = $this->get_webhook();

        wellweb_notify_render_fields( array(
            array(
                'name'        => 'well-web-notify-google-chat-webhook',
                'label'       => __( 'Webhook URL', 'well-web-notify' ),
                'type'        => 'password',
                'masked'      => true,
                'placeholder' => $webhook ? wellweb_notify_mask( $webhook ) : 'https://chat.googleapis.com/v1/spaces/…',
                'hint'        => __( 'Space settings → Apps &amp; integrations → Webhooks. The bot\'s name and avatar are set there, not here.', 'well-web-notify' ),
            ),
        ) );
    }

    public function get_settings(): array {
        return array(
            'well-web-notify-google-chat-enabled' => 'wellweb_notify_sanitize_checkbox',
            'well-web-notify-google-chat-webhook' => 'wellweb_notify_sanitize_encrypted',
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

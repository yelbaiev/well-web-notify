<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WellWeb_Notify_Telegram implements WellWeb_Notify_Channel {

    const API_URL = 'https://api.telegram.org/bot';

    public function get_slug(): string {
        return 'telegram';
    }

    public function get_label(): string {
        return 'Telegram';
    }

    public function get_icon(): string {
        return 'dashicons-format-chat';
    }

    public function is_configured(): bool {
        return ! empty( $this->get_token() ) && ! empty( $this->get_chat_id() );
    }

    public function is_enabled(): bool {
        return (bool) wellweb_notify_get_option( 'telegram-enabled', false );
    }

    public function send( string $subject, string $body, array $meta = [] ) {
        $token   = $this->get_token();
        $chat_id = $this->get_chat_id();

        if ( empty( $token ) || empty( $chat_id ) ) {
            return new WP_Error( 'not_configured', __( 'Telegram is not configured.', 'well-web-notify' ) );
        }

        $meta = WellWeb_Notify_Message::ensure( $meta, $subject, $body );

        // Headline, then one line saying which site and which form. Telegram
        // shows the first line of a message in the chat list, so the headline
        // has to be the thing a person is scanning for.
        $text    = '<b>' . esc_html( $meta['title'] ) . '</b>';
        $context = WellWeb_Notify_Message::context_line( $meta );
        if ( $context !== '' ) {
            $text .= "\n" . esc_html( $context );
        }

        $lines = WellWeb_Notify_Message::field_lines( $meta, '<b>', '</b>', 'esc_html' );
        if ( $lines !== '' ) {
            $text .= "\n\n" . $lines;
        }

        // Contact links. Telegram only makes http/https clickable in <a>, so
        // the number itself is <code> — tap to copy on every client.
        if ( ! empty( $meta['phone'] ) ) {
            $links = wellweb_notify_phone_links( $meta['phone'] );
            $text .= "\n\n"
                . "\xE2\x98\x8E\xEF\xB8\x8F" . ' <code>' . esc_html( $meta['phone'] ) . '</code>' . "\n\n"
                . '<a href="' . esc_attr( $links['whatsapp'] ) . '">' . "\xF0\x9F\x92\xAC" . ' WhatsApp</a>'
                . '    '
                . '<a href="' . esc_attr( $links['telegram'] ) . '">' . "\xE2\x9C\x88\xEF\xB8\x8F" . ' Telegram</a>'
                . '    '
                . '<a href="' . esc_attr( $links['viber_web'] ) . '">' . "\xF0\x9F\x93\xB1" . ' Viber</a>';
        }

        if ( ! empty( $meta['source_url'] ) ) {
            $text .= "\n\n" . '<a href="' . esc_attr( $meta['source_url'] ) . '">'
                . esc_html( WellWeb_Notify_Message::source_label( $meta ) ) . ' &rarr;</a>';
        }

        $response = wp_remote_post( self::API_URL . $token . '/sendMessage', array(
            'timeout' => 15,
            'body'    => array(
                'chat_id'                  => $chat_id,
                'text'                     => $text,
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => true,
            ),
        ) );

        return $this->handle_response( $response );
    }

    public function send_test() {
        $meta = wellweb_notify_test_message();
        return $this->send( $meta['title'], $meta['note'], $meta );
    }

    public function render_settings(): void {
        $token = $this->get_token();

        wellweb_notify_render_fields( array(
            array(
                'name'        => 'well-web-notify-telegram-token',
                'label'       => __( 'Bot token', 'well-web-notify' ),
                'type'        => 'password',
                'masked'      => true,
                'placeholder' => $token ? wellweb_notify_mask( $token ) : '123456:ABC-DEF…',
                'hint'        => __( 'From @BotFather. Give the bot a name, photo and description there — that is what people see in the chat.', 'well-web-notify' ),
            ),
            array(
                'name'        => 'well-web-notify-telegram-chat-id',
                'label'       => __( 'Chat ID', 'well-web-notify' ),
                'type'        => 'text',
                'value'       => wellweb_notify_get_option( 'telegram-chat-id', '' ),
                'placeholder' => '-1001234567890',
                'hint'        => __( 'A person, a group, or a channel. Group IDs start with a minus sign.', 'well-web-notify' ),
            ),
        ) );

        $this->render_site_link_info();
    }

    public function get_settings(): array {
        return array(
            'well-web-notify-telegram-enabled' => 'wellweb_notify_sanitize_checkbox',
            'well-web-notify-telegram-token'   => 'wellweb_notify_sanitize_encrypted',
            'well-web-notify-telegram-chat-id' => 'sanitize_text_field',
        );
    }

    /**
     * Show site verification token info
     */
    private function render_site_link_info(): void {
        if ( ! $this->is_configured() ) {
            return;
        }

        $site_token = WellWeb_Notify_Site_Verify::get_site_token();
        ?>
        <div class="wellweb-notify-verify">
            <div class="wpds-fields__hint">
                <?php esc_html_e( 'Optional. Send this in the group once, so the bot knows which site it belongs to:', 'well-web-notify' ); ?>
            </div>
            <code class="wellweb-notify-copy" role="button" tabindex="0"
                  title="<?php esc_attr_e( 'Click to copy', 'well-web-notify' ); ?>">/verify <?php echo esc_html( $site_token ); ?></code>
        </div>
        <?php
    }

    private function get_token(): string {
        return wellweb_notify_get_encrypted( 'telegram-token' );
    }

    private function get_chat_id(): string {
        return wellweb_notify_get_option( 'telegram-chat-id', '' );
    }

    private function handle_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 && ! empty( $body['ok'] ) ) {
            return true;
        }

        $error = $body['description'] ?? "HTTP {$code}";
        return new WP_Error( 'telegram_error', $error );
    }
}

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
            return new WP_Error( 'not_configured', __( 'Telegram is not configured.', 'wellweb-notify' ) );
        }

        // Build body from structured fields if available, else use legacy body
        if ( ! empty( $meta['fields'] ) ) {
            $lines = array();
            foreach ( $meta['fields'] as $label => $value ) {
                $lines[] = "<b>" . esc_html( $label ) . ":</b> " . esc_html( $value );
            }
            $formatted_body = implode( "\n", $lines );
        } else {
            $formatted_body = $body;
        }

        $text = "<b>" . esc_html( get_bloginfo( 'name' ) ) . "</b>\n\n{$formatted_body}";

        // Contact links (Telegram only supports http/https in <a> tags)
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
        return $this->send(
            __( 'Well Web Notify — Test', 'wellweb-notify' ),
            __( 'This is a test message from Well Web Notify plugin.', 'wellweb-notify' ),
            array( 'form_name' => 'Test' )
        );
    }

    public function render_settings(): void {
        $token   = $this->get_token();
        $chat_id = wellweb_notify_get_option( 'telegram-chat-id', '' );
        ?>
        <div class="ww-notify-channel-settings" data-channel="telegram">
            <label class="ww-field ww-field-labeled">
                <span class="ww-field-label"><?php esc_html_e( 'Bot Token', 'wellweb-notify' ); ?></span>
                <input type="password"
                       name="wellweb-notify-telegram-token"
                       value=""
                       placeholder="<?php echo $token ? esc_attr( wellweb_notify_mask( $token ) ) : '123456:ABC-DEF...'; ?>"
                       class="regular-text"
                       autocomplete="off" />
                <span class="description">
                    <?php esc_html_e( 'Create a branded bot via @BotFather — set custom name, photo, and description there.', 'wellweb-notify' ); ?>
                </span>
            </label>

            <label class="ww-field ww-field-labeled">
                <span class="ww-field-label"><?php esc_html_e( 'Chat ID', 'wellweb-notify' ); ?></span>
                <input type="text"
                       name="wellweb-notify-telegram-chat-id"
                       value="<?php echo esc_attr( $chat_id ); ?>"
                       placeholder="-1001234567890"
                       class="regular-text" />
                <span class="description">
                    <?php esc_html_e( 'User, group, or channel ID. Send /start to your bot first, then use @userinfobot to find your ID.', 'wellweb-notify' ); ?>
                </span>
            </label>

            <?php $this->render_site_link_info(); ?>
        </div>
        <?php
    }

    public function get_settings(): array {
        return array(
            'wellweb-notify-telegram-enabled' => 'wellweb_notify_sanitize_checkbox',
            'wellweb-notify-telegram-token'   => 'wellweb_notify_sanitize_encrypted',
            'wellweb-notify-telegram-chat-id' => 'sanitize_text_field',
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
        <div class="ww-notify-site-verify">
            <span class="ww-field-label"><?php esc_html_e( 'Site Verification', 'wellweb-notify' ); ?></span>
            <p class="description">
                <?php esc_html_e( 'When adding the bot to a group, send this command in the group to verify ownership:', 'wellweb-notify' ); ?>
            </p>
            <code class="ww-notify-verify-code">/verify <?php echo esc_html( $site_token ); ?></code>
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

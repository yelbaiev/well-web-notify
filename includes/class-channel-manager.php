<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WellWeb_Notify_Channel_Manager {

    private static $instance = null;
    private $channels = array();

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->register_channel( new WellWeb_Notify_Telegram() );
        $this->register_channel( new WellWeb_Notify_Slack() );
        $this->register_channel( new WellWeb_Notify_Discord() );
        $this->register_channel( new WellWeb_Notify_Google_Chat() );

        /**
         * Allow third-party plugins to register additional channels.
         *
         * @param WellWeb_Notify_Channel_Manager $manager
         */
        do_action( 'wellweb_notify_register_channels', $this );
    }

    public function register_channel( WellWeb_Notify_Channel $channel ) {
        $this->channels[ $channel->get_slug() ] = $channel;
    }

    /**
     * @return WellWeb_Notify_Channel[]
     */
    public function get_channels(): array {
        return $this->channels;
    }

    /**
     * @return WellWeb_Notify_Channel[]
     */
    public function get_active_channels(): array {
        return array_filter( $this->channels, function( $ch ) {
            return $ch->is_enabled() && $ch->is_configured();
        } );
    }

    public function get_channel( string $slug ): ?WellWeb_Notify_Channel {
        return $this->channels[ $slug ] ?? null;
    }

    /**
     * Send notification to all active channels.
     *
     * @return array [ 'slug' => true|WP_Error ]
     */
    public function send_all( string $subject, string $body, array $meta = [] ): array {
        $results = array();

        foreach ( $this->get_active_channels() as $slug => $channel ) {
            $result = $channel->send( $subject, $body, $meta );
            $results[ $slug ] = $result;

            // Log the send attempt
            WellWeb_Notify_Log::log(
                $slug,
                $meta['form_name'] ?? 'Unknown',
                is_wp_error( $result ) ? 'error' : 'success',
                is_wp_error( $result ) ? $result->get_error_message() : ''
            );
        }

        return $results;
    }
}

// Initialize on plugins_loaded to allow other plugins to hook in
add_action( 'plugins_loaded', function() {
    WellWeb_Notify_Channel_Manager::instance();
}, 20 );

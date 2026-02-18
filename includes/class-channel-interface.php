<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface for all notification channels
 */
interface WellWeb_Notify_Channel {

    /**
     * Unique slug for this channel (e.g. 'telegram', 'slack')
     */
    public function get_slug(): string;

    /**
     * Human-readable label
     */
    public function get_label(): string;

    /**
     * Dashicon class for admin UI
     */
    public function get_icon(): string;

    /**
     * Whether the channel is configured (has valid credentials)
     */
    public function is_configured(): bool;

    /**
     * Whether the channel is enabled by the user
     */
    public function is_enabled(): bool;

    /**
     * Send a notification message
     *
     * @param string $subject Short subject/title
     * @param string $body    Full message body
     * @param array  $meta    Additional metadata (form name, fields, etc.)
     * @return bool|WP_Error
     */
    public function send( string $subject, string $body, array $meta = [] );

    /**
     * Send a test message
     *
     * @return bool|WP_Error
     */
    public function send_test();

    /**
     * Render settings fields for admin page
     */
    public function render_settings(): void;

    /**
     * Get settings fields to register
     *
     * @return array Array of [ option_name => sanitize_callback ]
     */
    public function get_settings(): array;
}

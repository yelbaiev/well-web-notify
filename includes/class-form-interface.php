<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface for form plugin integrations
 */
interface WellWeb_Notify_Form {

    /**
     * Unique slug for this form plugin
     */
    public function get_slug(): string;

    /**
     * Human-readable label
     */
    public function get_label(): string;

    /**
     * Check if this form plugin is installed and active
     */
    public function is_available(): bool;

    /**
     * Hook into the form submission event
     */
    public function register_hooks(): void;
}

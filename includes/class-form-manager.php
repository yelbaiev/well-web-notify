<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WellWeb_Notify_Form_Manager {

    private static $instance = null;
    private $forms = array();

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->register_form( new WellWeb_Notify_CF7() );
        $this->register_form( new WellWeb_Notify_WPForms() );
        $this->register_form( new WellWeb_Notify_Gravity_Forms() );
        $this->register_form( new WellWeb_Notify_Ninja_Forms() );
        $this->register_form( new WellWeb_Notify_Jetpack() );
        $this->register_form( new WellWeb_Notify_WooCommerce() );

        do_action( 'wellweb_notify_register_forms', $this );

        // Register hooks for available forms
        foreach ( $this->forms as $form ) {
            if ( $form->is_available() ) {
                $form->register_hooks();
            }
        }
    }

    public function register_form( WellWeb_Notify_Form $form ) {
        $this->forms[ $form->get_slug() ] = $form;
    }

    /**
     * @return WellWeb_Notify_Form[]
     */
    public function get_forms(): array {
        return $this->forms;
    }

    /**
     * @return WellWeb_Notify_Form[]
     */
    public function get_available_forms(): array {
        return array_filter( $this->forms, function( $f ) {
            return $f->is_available();
        } );
    }

    /**
     * Common handler called by all form integrations after parsing submission data.
     */
    public static function handle_submission( string $form_name, array $fields ) {
        $manager = WellWeb_Notify_Channel_Manager::instance();
        $active  = $manager->get_active_channels();

        if ( empty( $active ) ) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: form name */
            __( 'New submission: %s', 'well-web-notify' ),
            $form_name
        );

        // Clean field values
        $clean_fields = array();
        foreach ( $fields as $label => $value ) {
            if ( is_array( $value ) ) {
                $value = implode( ', ', $value );
            }
            $value = wp_strip_all_tags( (string) $value );
            if ( $value !== '' ) {
                $clean_fields[ $label ] = $value;
            }
        }

        $phone = wellweb_notify_extract_phone( $fields );
        $meta  = array(
            'form_name' => $form_name,
            'site_url'  => home_url(),
            'phone'     => $phone,
            'fields'    => $clean_fields,
        );

        // Legacy body for backward-compat with third-party channels
        $lines = array();
        foreach ( $clean_fields as $label => $value ) {
            $lines[] = "<b>{$label}:</b> {$value}";
        }
        $body = implode( "\n", $lines );

        $manager->send_all( $subject, $body, $meta );
    }
}

add_action( 'plugins_loaded', function() {
    WellWeb_Notify_Form_Manager::instance();
}, 25 );

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
     *
     * The integrations hand over whatever their form plugin gave them, keys and
     * all. Everything that turns that into something a person can read —
     * dropping the plugin's own bookkeeping keys, stripping index prefixes,
     * collapsing duplicates — happens in WellWeb_Notify_Message, so it happens
     * the same way for all six integrations.
     *
     * @param string $form_name Which form this was.
     * @param array  $fields    Raw submitted values, as the form plugin had them.
     * @param array  $args      Optional extras: `source_url`, `title`, `kind`.
     */
    public static function handle_submission( string $form_name, array $fields, array $args = array() ) {
        $manager = WellWeb_Notify_Channel_Manager::instance();
        $active  = $manager->get_active_channels();

        if ( empty( $active ) ) {
            return;
        }

        $meta = WellWeb_Notify_Message::build( array(
            'kind'       => $args['kind'] ?? 'form',
            'title'      => $args['title'] ?? __( 'New form submission', 'well-web-notify' ),
            'form_name'  => $form_name,
            'fields'     => $fields,
            'source_url' => $args['source_url'] ?? '',
        ) );

        if ( empty( $meta['fields'] ) ) {
            // Everything the form sent was bookkeeping. A message with a
            // heading and no answers tells the reader nothing and trains them
            // to ignore the next one.
            return;
        }

        $subject = sprintf(
            /* translators: %s: form name */
            __( 'New submission: %s', 'well-web-notify' ),
            $meta['form_name'] !== '' ? $meta['form_name'] : $form_name
        );

        // Plain body, for third-party channels written against the old signature.
        $manager->send_all( $subject, WellWeb_Notify_Message::plain_body( $meta ), $meta );
    }
}

add_action( 'plugins_loaded', function() {
    WellWeb_Notify_Form_Manager::instance();
}, 25 );

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WellWeb_Notify_WPForms implements WellWeb_Notify_Form {

    public function get_slug(): string {
        return 'wpforms';
    }

    public function get_label(): string {
        return 'WPForms';
    }

    public function is_available(): bool {
        return defined( 'WPFORMS_VERSION' );
    }

    public function register_hooks(): void {
        add_action( 'wpforms_process_complete', array( $this, 'on_submit' ), 10, 4 );
    }

    public function on_submit( $fields, $entry, $form_data, $entry_id ) {
        $form_name = $form_data['settings']['form_title'] ?? __( 'WPForms', 'well-web-notify' );

        $parsed = array();
        foreach ( $fields as $field ) {
            $label = $field['name'] ?? $field['id'] ?? '';
            $value = $field['value'] ?? '';
            if ( $label && $value !== '' ) {
                $parsed[ $label ] = $value;
            }
        }

        WellWeb_Notify_Form_Manager::handle_submission( $form_name, $parsed );
    }
}

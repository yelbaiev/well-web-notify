<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WellWeb_Notify_Ninja_Forms implements WellWeb_Notify_Form {

    public function get_slug(): string {
        return 'ninja-forms';
    }

    public function get_label(): string {
        return 'Ninja Forms';
    }

    public function is_available(): bool {
        return class_exists( 'Ninja_Forms' );
    }

    public function register_hooks(): void {
        add_action( 'ninja_forms_after_submission', array( $this, 'on_submit' ) );
    }

    public function on_submit( $form_data ) {
        $form_name = $form_data['settings']['title'] ?? __( 'Ninja Forms', 'well-web-notify' );

        $fields = array();
        foreach ( $form_data['fields'] as $field ) {
            // Skip submit buttons and hidden fields
            if ( in_array( $field['type'] ?? '', array( 'submit', 'html', 'hr', 'divider' ), true ) ) {
                continue;
            }

            $label = $field['label'] ?? $field['key'] ?? '';
            $value = $field['value'] ?? '';

            if ( $label && $value !== '' ) {
                $fields[ $label ] = is_array( $value ) ? implode( ', ', $value ) : $value;
            }
        }

        WellWeb_Notify_Form_Manager::handle_submission( $form_name, $fields );
    }
}

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WellWeb_Notify_Gravity_Forms implements WellWeb_Notify_Form {

    public function get_slug(): string {
        return 'gravity-forms';
    }

    public function get_label(): string {
        return 'Gravity Forms';
    }

    public function is_available(): bool {
        return class_exists( 'GFForms' );
    }

    public function register_hooks(): void {
        add_action( 'gform_after_submission', array( $this, 'on_submit' ), 10, 2 );
    }

    public function on_submit( $entry, $form ) {
        $form_name = $form['title'] ?? __( 'Gravity Forms', 'well-web-notify' );

        $fields = array();
        foreach ( $form['fields'] as $field ) {
            $value = rgar( $entry, (string) $field->id );

            // Handle multi-input fields (name, address, etc.)
            if ( empty( $value ) && ! empty( $field->inputs ) ) {
                $parts = array();
                foreach ( $field->inputs as $input ) {
                    $part = rgar( $entry, (string) $input['id'] );
                    if ( $part !== '' ) {
                        $parts[] = $part;
                    }
                }
                $value = implode( ' ', $parts );
            }

            if ( $value !== '' ) {
                $fields[ $field->label ] = $value;
            }
        }

        WellWeb_Notify_Form_Manager::handle_submission( $form_name, $fields );
    }
}

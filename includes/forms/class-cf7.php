<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WellWeb_Notify_CF7 implements WellWeb_Notify_Form {

    public function get_slug(): string {
        return 'cf7';
    }

    public function get_label(): string {
        return 'Contact Form 7';
    }

    public function is_available(): bool {
        return defined( 'WPCF7_VERSION' );
    }

    public function register_hooks(): void {
        add_action( 'wpcf7_mail_sent', array( $this, 'on_submit' ) );
    }

    public function on_submit( $contact_form ) {
        $submission = WPCF7_Submission::get_instance();
        if ( ! $submission ) {
            return;
        }

        $data      = $submission->get_posted_data();
        $form_name = $contact_form->title();

        // Filter out internal CF7 fields
        $fields = array();
        foreach ( $data as $key => $value ) {
            if ( strpos( $key, '_wpcf7' ) === 0 ) {
                continue;
            }
            $label = ucfirst( str_replace( array( '-', '_' ), ' ', $key ) );
            $fields[ $label ] = is_array( $value ) ? implode( ', ', $value ) : $value;
        }

        WellWeb_Notify_Form_Manager::handle_submission( $form_name, $fields );
    }
}

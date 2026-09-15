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

        // Handed over as CF7 stores them — input slugs (`your-name`) and its own
        // `_wpcf7*` bookkeeping. WellWeb_Notify_Message turns the first into a
        // label and drops the second, the same way it does for every other
        // integration.
        $source_url = '';
        $post_id    = (int) $submission->get_meta( 'container_post_id' );
        if ( $post_id > 0 ) {
            $source_url = (string) get_permalink( $post_id );
        }

        WellWeb_Notify_Form_Manager::handle_submission(
            $form_name,
            (array) $data,
            array( 'source_url' => $source_url )
        );
    }
}

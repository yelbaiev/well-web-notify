<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WellWeb_Notify_Jetpack implements WellWeb_Notify_Form {

    public function get_slug(): string {
        return 'jetpack';
    }

    public function get_label(): string {
        return 'Jetpack Forms';
    }

    public function is_available(): bool {
        return defined( 'JETPACK__VERSION' ) || class_exists( 'Automattic\Jetpack\Forms\ContactForm\Contact_Form' );
    }

    public function register_hooks(): void {
        add_action( 'grunion_pre_message_sent', array( $this, 'on_submit' ), 10, 3 );
    }

    /**
     * @param int    $post_id    Post ID the form is on.
     * @param array  $all_values All submitted field values.
     * @param array  $extra_values Extra metadata.
     */
    public function on_submit( $post_id, $all_values, $extra_values ) {
        $post      = get_post( $post_id );
        $form_name = $post ? $post->post_title : __( 'Jetpack Form', 'wellweb-notify' );

        $fields = array();
        foreach ( $all_values as $key => $value ) {
            $label = ucfirst( str_replace( array( '-', '_' ), ' ', $key ) );
            $fields[ $label ] = is_array( $value ) ? implode( ', ', $value ) : $value;
        }

        WellWeb_Notify_Form_Manager::handle_submission( $form_name, $fields );
    }
}

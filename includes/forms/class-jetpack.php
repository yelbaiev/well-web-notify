<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WellWeb_Notify_Jetpack implements WellWeb_Notify_Form {

    /**
     * Per-request dedupe map keyed by feedback / form post ID, so multiple
     * listeners can't double-send for the same submission.
     */
    private static $dispatched = array();

    public function get_slug(): string {
        return 'jetpack';
    }

    public function get_label(): string {
        return 'Jetpack Forms';
    }

    public function is_available(): bool {
        return defined( 'JETPACK__VERSION' ) || class_exists( 'Automattic\Jetpack\Forms\ContactForm\Contact_Form' );
    }

    /**
     * Meta keys Jetpack uses to stamp the submitted field payload onto a
     * feedback post. Modern Jetpack (15.x) uses `_feedback_extra_fields`;
     * older versions used `_feedback_all_fields`. Both are listened on for
     * cross-version coverage; the per-request dedupe map ensures only the
     * first one that arrives triggers a dispatch.
     */
    private const FIELD_META_KEYS = array( '_feedback_extra_fields', '_feedback_all_fields' );

    public function register_hooks(): void {
        // Modern Jetpack (15.x and forward): the only hook that fires on
        // Forms-block submissions AND carries the field payload as an arg.
        // `grunion_pre_message_sent` was dropped; meta keys like
        // `_feedback_extra_fields` are written empty in this branch.
        // Signature: ($post_id, $to, $subject, $message, $headers, $all_values, $extra_values).
        add_action( 'grunion_after_message_sent', array( $this, 'on_after_message_sent' ), 10, 7 );

        // Legacy / classic [contact-form] shortcode path (pre-Jetpack 15).
        add_action( 'grunion_pre_message_sent', array( $this, 'on_submit' ), 10, 3 );

        // Older Forms block (Jetpack 13–14): field payload was stamped to
        // `_feedback_extra_fields` or `_feedback_all_fields` as a real array.
        // Kept as a defensive fallback; dedupe ensures no doubles when
        // `grunion_after_message_sent` fires in the same request.
        add_action( 'added_post_meta',   array( $this, 'on_feedback_fields_meta' ), 10, 4 );
        add_action( 'updated_post_meta', array( $this, 'on_feedback_fields_meta' ), 10, 4 );

        // Last-resort fallback for flows that create a populated feedback
        // post in one shot (importers, scripted creation).
        add_action( 'transition_post_status', array( $this, 'on_feedback_transition' ), 10, 3 );
    }

    /**
     * @param int    $post_id     Post ID the form is on.
     * @param array  $all_values  All submitted field values.
     * @param array  $extra_values Extra metadata.
     */
    public function on_submit( $post_id, $all_values, $extra_values ) {
        $fields = (array) $all_values;
        if ( empty( $fields ) ) {
            return;
        }

        if ( ! $this->claim_dispatch( (int) $post_id ) ) {
            return;
        }

        $post      = get_post( $post_id );
        $form_name = $post ? $post->post_title : __( 'Jetpack Form', 'well-web-notify' );

        WellWeb_Notify_Form_Manager::handle_submission( $form_name, $this->parse_fields( $fields ) );
    }

    /**
     * Modern Jetpack (15+). Fires after Jetpack has built the email payload
     * for a submission. $all_values contains the form field values keyed by
     * label. This is the only signal in 15.x that arrives with the data
     * populated — the pre-message hook was removed and the corresponding
     * meta key is now stamped empty.
     *
     * Signature matches the canonical Grunion hook:
     *   $post_id, $to, $subject, $message, $headers, $all_values, $extra_values
     */
    public function on_after_message_sent( $post_id, $to, $subject, $message, $headers, $all_values, $extra_values ) {
        $fields = (array) $all_values;
        if ( ! empty( $extra_values ) && is_array( $extra_values ) ) {
            $fields = array_merge( $fields, $extra_values );
        }
        if ( empty( $fields ) ) {
            return;
        }

        if ( ! $this->claim_dispatch( (int) $post_id ) ) {
            return;
        }

        $post = $post_id ? get_post( $post_id ) : null;
        if ( $post instanceof WP_Post ) {
            $form_name = $this->resolve_form_name( $post );
        } else {
            $form_name = is_string( $subject ) && $subject !== ''
                ? $subject
                : __( 'Jetpack Form', 'well-web-notify' );
        }

        WellWeb_Notify_Form_Manager::handle_submission( $form_name, $this->parse_fields( $fields ) );
    }

    /**
     * Catches the new Forms block flow: dispatch the moment Jetpack writes
     * `_feedback_all_fields` onto a feedback post.
     *
     * @param int    $meta_id    Meta row ID.
     * @param int    $object_id  Post ID.
     * @param string $meta_key   Meta key.
     * @param mixed  $meta_value Meta value (already unserialized).
     */
    public function on_feedback_fields_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
        if ( ! in_array( $meta_key, self::FIELD_META_KEYS, true ) ) {
            return;
        }

        // Defensive read: get_post_meta returns the unserialized PHP value
        // regardless of how the meta was stored, so we don't depend on the
        // shape of the $meta_value parameter handed to the callback.
        $fields = $this->read_field_meta( (int) $object_id );
        if ( empty( $fields ) && is_array( $meta_value ) && ! empty( $meta_value ) ) {
            $fields = $meta_value;
        }
        if ( empty( $fields ) ) {
            // Jetpack 15 hits this branch every time — `_feedback_extra_fields`
            // is written as an empty array on the modern Forms-block path.
            // Critically, we MUST NOT claim the dispatch slot here, or we'd
            // block `grunion_after_message_sent` from dispatching with the
            // real payload that arrives ~50 ms later.
            return;
        }

        $post = get_post( $object_id );
        if ( ! $post instanceof WP_Post || $post->post_type !== 'feedback' ) {
            return;
        }
        if ( in_array( $post->post_status, array( 'spam', 'trash', 'draft' ), true ) ) {
            return;
        }

        if ( ! $this->claim_dispatch( (int) $object_id ) ) {
            return;
        }

        WellWeb_Notify_Form_Manager::handle_submission(
            $this->resolve_form_name( $post ),
            $this->parse_fields( $fields )
        );
    }

    /**
     * Defensive fallback: a feedback post is transitioned to publish and
     * already has its field payload at insert time. Rare in modern Jetpack
     * but catches importer / scripted creation paths.
     */
    public function on_feedback_transition( $new_status, $old_status, $post ) {
        if ( ! $post instanceof WP_Post || $post->post_type !== 'feedback' ) {
            return;
        }
        if ( $new_status !== 'publish' || $new_status === $old_status ) {
            return;
        }

        $all_values = $this->read_field_meta( $post->ID );
        if ( empty( $all_values ) ) {
            // Modern Jetpack hits this branch — meta isn't stamped yet, or
            // is stamped empty. The grunion_after_message_sent / meta
            // listeners will pick it up later. Do NOT claim the dispatch
            // slot here.
            return;
        }

        if ( ! $this->claim_dispatch( (int) $post->ID ) ) {
            return;
        }

        WellWeb_Notify_Form_Manager::handle_submission(
            $this->resolve_form_name( $post ),
            $this->parse_fields( $all_values )
        );
    }

    /**
     * Atomic check-and-claim. Returns true if THIS call gets to dispatch for
     * the given post; false if a prior call has already claimed it. Only call
     * this AFTER all skip / empty-payload checks — calling it earlier would
     * "steal" the dispatch slot from a later, better-populated arrival
     * (e.g. an empty `_feedback_extra_fields` meta write stealing the slot
     * from `grunion_after_message_sent`).
     */
    private function claim_dispatch( int $key ): bool {
        if ( isset( self::$dispatched[ $key ] ) ) {
            return false;
        }
        self::$dispatched[ $key ] = true;
        return true;
    }

    private function parse_fields( array $all_values ): array {
        $fields = array();
        foreach ( $all_values as $key => $value ) {
            $label = ucfirst( str_replace( array( '-', '_' ), ' ', (string) $key ) );
            if ( is_array( $value ) ) {
                $value = implode( ', ', array_map(
                    static function ( $v ) {
                        return is_scalar( $v ) ? (string) $v : '';
                    },
                    $value
                ) );
            }
            $fields[ $label ] = $value;
        }
        return $fields;
    }

    private function resolve_form_name( WP_Post $post ): string {
        // Modern Jetpack stamps the source post ID directly — cheapest, most reliable.
        $source_id = (int) get_post_meta( $post->ID, '_feedback_source_post_id', true );
        if ( $source_id > 0 ) {
            $source = get_post( $source_id );
            if ( $source && $source->post_title !== '' ) {
                return $source->post_title;
            }
        }

        // Legacy Jetpack: resolve via the parent URL.
        $parent_url = get_post_meta( $post->ID, '_feedback_parent_url', true );
        if ( is_string( $parent_url ) && $parent_url !== '' ) {
            $parent_id = url_to_postid( $parent_url );
            if ( $parent_id ) {
                $parent = get_post( $parent_id );
                if ( $parent && $parent->post_title !== '' ) {
                    return $parent->post_title;
                }
            }
        }

        if ( $post->post_title !== '' ) {
            return $post->post_title;
        }

        return __( 'Jetpack Form', 'well-web-notify' );
    }

    private function read_field_meta( int $post_id ): array {
        foreach ( self::FIELD_META_KEYS as $key ) {
            $value = get_post_meta( $post_id, $key, true );
            if ( is_array( $value ) && ! empty( $value ) ) {
                return $value;
            }
        }
        return array();
    }
}

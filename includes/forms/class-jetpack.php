<?php
/**
 * Jetpack Forms → Well Web Notify.
 *
 * ── Why this file does not send from a hook ─────────────────────────────────
 *
 * Jetpack exposes one submission through four signals, and they do not carry
 * the same data. Reading Jetpack 16.2
 * (`jetpack-forms/src/contact-form/class-feedback.php`):
 *
 *   `get_all_values()`    every compiled field, keyed `"{n}_{Label}"`, MERGED
 *                         with `get_entry_values()` — which adds `entry_title`,
 *                         `entry_permalink`, `feedback_id` and
 *                         `email_marketing_consent` to the same array.
 *
 *   `get_legacy_extra_values()`
 *                         only the fields Jetpack does not treat as standard
 *                         (so: not name, email, url, subject, textarea), keyed
 *                         `"{n}_{Label}"` with a counter that CONTINUES past
 *                         the ones already in all-values. A form with Имя +
 *                         Телефон gives `1_Имя`, `2_Телефон` in all-values and
 *                         `3_Телефон` in extra-values: the same field, twice,
 *                         under two keys.
 *
 * And `class-contact-form.php` writes `_feedback_extra_fields` — the extra
 * values ONLY — before it fires `grunion_pre_message_sent`, which is before
 * `grunion_after_message_sent`.
 *
 * The previous version listened on all four and let whichever arrived first
 * dispatch. That produced two different messages from the same form:
 *
 *   - meta listener first  → one field, `3 Телефон`, and nothing else
 *   - after-message first  → every field, the phone twice, plus three internal
 *                            keys (`Entry title`, `Entry permalink`,
 *                            `Feedback id`)
 *
 * Which one you got depended on whether `update_post_meta` happened to change
 * the stored value — a no-op write fires no hook. That is the "sometimes all
 * the fields, sometimes not" this file was rewritten to fix.
 *
 * ── What it does instead ────────────────────────────────────────────────────
 *
 * Nothing sends from a hook. Each listener OFFERS its payload with a rank, the
 * best offer per feedback post is kept, and the winner is sent once on
 * `shutdown`. That runs even though Jetpack redirects and exits, because
 * WordPress registers `shutdown_action_hook` with `register_shutdown_function`.
 *
 * `$extra_values` is deliberately NOT merged into `$all_values` any more: it is
 * a subset of it under different keys, and merging the two is what printed the
 * phone number twice.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WellWeb_Notify_Jetpack implements WellWeb_Notify_Form {

    /**
     * Best offer so far, per feedback post ID.
     *
     * @var array<int,array{rank:int,fields:array,post_id:int}>
     */
    private static $pending = array();

    /** Feedback posts already sent this request, so a late offer cannot double-send. */
    private static $sent = array();

    /**
     * How much each signal is trusted, highest first.
     *
     * `grunion_after_message_sent` is top because it is the only one that
     * arrives with the complete compiled field set as an argument. The meta
     * keys are bottom because on the modern Forms-block path they hold the
     * extra fields only — a real payload, but a partial one.
     */
    private const RANK_AFTER_SENT = 30;
    private const RANK_PRE_SENT   = 20;
    private const RANK_META       = 10;
    private const RANK_TRANSITION = 5;

    /**
     * Meta keys Jetpack has used to stamp the field payload onto a feedback
     * post, newest first. Read defensively — both are still written by some
     * version in the wild.
     */
    private const FIELD_META_KEYS = array( '_feedback_extra_fields', '_feedback_all_fields' );

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
        // Modern Jetpack (15+): fires on Forms-block submissions and carries
        // the complete field set.
        // ($post_id, $to, $subject, $message, $headers, $all_values, $extra_values)
        add_action( 'grunion_after_message_sent', array( $this, 'on_after_message_sent' ), 10, 7 );

        // Classic [contact-form] shortcode path. Still fires in 16.x, but only
        // when Jetpack is actually going to send the email.
        add_action( 'grunion_pre_message_sent', array( $this, 'on_pre_message_sent' ), 10, 3 );

        // The feedback post's own meta. Partial on the modern path, complete on
        // older ones, so it is an offer rather than a send.
        add_action( 'added_post_meta', array( $this, 'on_feedback_fields_meta' ), 10, 4 );
        add_action( 'updated_post_meta', array( $this, 'on_feedback_fields_meta' ), 10, 4 );

        // Importers and scripted creation, which insert a populated feedback
        // post in one shot and fire none of the above.
        add_action( 'transition_post_status', array( $this, 'on_feedback_transition' ), 10, 3 );

        // Whatever won, sent once, after Jetpack has finished with the request.
        add_action( 'shutdown', array( $this, 'dispatch_pending' ), 5 );
    }

    /**
     * @param int   $post_id      Feedback post ID.
     * @param array $all_values   Every compiled field, plus Jetpack's entry keys.
     * @param array $extra_values The non-standard subset. Not used — see the file header.
     */
    public function on_pre_message_sent( $post_id, $all_values, $extra_values ) {
        $this->offer( (int) $post_id, (array) $all_values, self::RANK_PRE_SENT );
    }

    /**
     * @param int    $post_id
     * @param mixed  $to
     * @param string $subject
     * @param string $message
     * @param mixed  $headers
     * @param array  $all_values
     * @param array  $extra_values Not used — a subset of $all_values under different keys.
     */
    public function on_after_message_sent( $post_id, $to, $subject, $message, $headers, $all_values, $extra_values ) {
        $this->offer( (int) $post_id, (array) $all_values, self::RANK_AFTER_SENT );
    }

    /**
     * Jetpack stamped the field payload onto the feedback post.
     *
     * @param int    $meta_id
     * @param int    $object_id  Post ID.
     * @param string $meta_key
     * @param mixed  $meta_value Already unserialized.
     */
    public function on_feedback_fields_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
        if ( ! in_array( $meta_key, self::FIELD_META_KEYS, true ) ) {
            return;
        }

        $post = get_post( $object_id );
        if ( ! $post instanceof WP_Post || $post->post_type !== 'feedback' ) {
            return;
        }
        if ( in_array( $post->post_status, array( 'spam', 'trash', 'draft' ), true ) ) {
            return;
        }

        // Read through get_post_meta rather than trusting the callback's
        // argument, which is the pre-serialisation value on some paths.
        $fields = $this->read_field_meta( (int) $object_id );
        if ( ! $fields && is_array( $meta_value ) ) {
            $fields = $meta_value;
        }

        $this->offer( (int) $object_id, (array) $fields, self::RANK_META );
    }

    /**
     * A feedback post published with its payload already attached.
     */
    public function on_feedback_transition( $new_status, $old_status, $post ) {
        if ( ! $post instanceof WP_Post || $post->post_type !== 'feedback' ) {
            return;
        }
        if ( $new_status !== 'publish' || $new_status === $old_status ) {
            return;
        }

        $this->offer( (int) $post->ID, $this->read_field_meta( (int) $post->ID ), self::RANK_TRANSITION );
    }

    /**
     * Record a candidate payload. Highest rank wins; at equal rank, the one
     * with more fields wins, because two listeners of the same rank differing
     * in size means one of them caught a partial write.
     *
     * An empty payload is not an offer at all — recording it would let an
     * empty `_feedback_extra_fields` write (which Jetpack 15+ does on every
     * Forms-block submission) sit in the slot the real payload needs.
     */
    private function offer( int $post_id, array $fields, int $rank ): void {
        if ( ! $fields || isset( self::$sent[ $post_id ] ) ) {
            return;
        }

        $existing = self::$pending[ $post_id ] ?? null;
        if ( $existing !== null ) {
            if ( $existing['rank'] > $rank ) {
                return;
            }
            if ( $existing['rank'] === $rank && count( $existing['fields'] ) >= count( $fields ) ) {
                return;
            }
        }

        self::$pending[ $post_id ] = array(
            'rank'    => $rank,
            'fields'  => $fields,
            'post_id' => $post_id,
        );
    }

    /**
     * Send every submission collected this request, once each.
     *
     * Public because it is a hook callback, and because the tests call it
     * directly rather than triggering a real shutdown.
     */
    public function dispatch_pending(): void {
        if ( ! self::$pending ) {
            return;
        }

        $pending        = self::$pending;
        self::$pending  = array();

        foreach ( $pending as $post_id => $offer ) {
            if ( isset( self::$sent[ $post_id ] ) ) {
                continue;
            }
            self::$sent[ $post_id ] = true;

            $post = $post_id ? get_post( $post_id ) : null;

            // `grunion_after_message_sent` fires for spam too — it sits outside
            // Jetpack's `$will_send` branch. By shutdown the feedback post has
            // its final status, so this is the first point at which the answer
            // is actually known.
            if ( $post instanceof WP_Post && in_array( $post->post_status, array( 'spam', 'trash' ), true ) ) {
                continue;
            }

            $fields = $offer['fields'];
            if ( $offer['rank'] < self::RANK_PRE_SENT ) {
                $fields = $this->read_complete_values( (int) $post_id ) ?: $fields;
            }

            WellWeb_Notify_Form_Manager::handle_submission(
                $post instanceof WP_Post ? $this->resolve_form_name( $post ) : __( 'Jetpack Form', 'well-web-notify' ),
                $fields,
                array(
                    'source_url' => $post instanceof WP_Post ? $this->resolve_source_url( $post ) : '',
                )
            );
        }
    }

    /**
     * Which form was this, in the words the site owner would use.
     */
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

    /**
     * The page the form sits on — one link at the foot of the message, rather
     * than an `Entry permalink:` row in the middle of the answers.
     */
    private function resolve_source_url( WP_Post $post ): string {
        $source_id = (int) get_post_meta( $post->ID, '_feedback_source_post_id', true );
        if ( $source_id > 0 ) {
            $permalink = get_permalink( $source_id );
            if ( $permalink ) {
                return $permalink;
            }
        }

        $parent_url = get_post_meta( $post->ID, '_feedback_parent_url', true );

        return is_string( $parent_url ) ? esc_url_raw( $parent_url ) : '';
    }

    /**
     * Every field of a stored submission, read back through Jetpack itself.
     *
     * Only needed when neither hook with the full payload fired and the best
     * offer is the meta write — which on Jetpack 15+ is the extra fields only,
     * i.e. a phone number with no name. The feedback post holds the whole
     * submission, and `Feedback::get()->get_all_values()` is what Jetpack's
     * own hooks are built from, so it is the same field set they would carry.
     */
    private function read_complete_values( int $post_id ): array {
        $class = 'Automattic\Jetpack\Forms\ContactForm\Feedback';
        if ( ! $post_id || ! class_exists( $class ) || ! method_exists( $class, 'get' ) ) {
            return array();
        }

        try {
            $feedback = $class::get( $post_id );
            if ( ! $feedback || ! method_exists( $feedback, 'get_all_values' ) ) {
                return array();
            }
            $values = $feedback->get_all_values();
        } catch ( \Throwable $e ) {
            return array();
        }

        return is_array( $values ) ? $values : array();
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

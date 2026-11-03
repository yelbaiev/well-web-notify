<?php
/**
 * Well Web Notify — the shape of a notification, and how it reads.
 *
 * Every integration hands this class raw form data; every channel renders what
 * it hands back. Before it existed, four channels each rebuilt a field list
 * from `$meta['fields']` in slightly different ways, and the form's name was
 * computed into a `$subject` that no channel ever printed — so a message said
 * which SITE it came from but not which FORM.
 *
 * The normalisation matters more than the rendering. Form plugins hand over
 * keys meant for their own storage, not for a person to read:
 *
 *   - Jetpack keys every field `"{n}_{Label}"` and merges four internal keys
 *     (`entry_title`, `entry_permalink`, `feedback_id`,
 *     `email_marketing_consent`) into the same array as the real ones.
 *   - Contact Form 7 hands over input slugs (`your-name`) and its own
 *     `_wpcf7*` bookkeeping.
 *   - Several plugins can hand the same field over twice under two keys.
 *
 * All of that is one problem — "what did the person actually type" — so it is
 * answered once, here, rather than five times in five integrations.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WellWeb_Notify_Message {

    /**
     * Keys that exist for the form plugin's benefit, not the reader's.
     *
     * Compared against the raw key, lowercased, with spaces and dashes folded
     * to underscores — so `Entry Title`, `entry-title` and `entry_title` are
     * all the same key. Anything starting with `_` is dropped too, which
     * covers `_wpcf7`, `_wpnonce` and every private meta key without listing
     * them.
     *
     * `entry_title` and `entry_permalink` are not thrown away: build() lifts
     * them into the message's source and source_url, where they become one
     * link at the foot instead of two rows in the middle of the answers.
     */
    private const INTERNAL_KEYS = array(
        'entry_title',
        'entry_permalink',
        'entry_page',
        'feedback_id',
        'email_marketing_consent',
        'is_block',
        'ip',
        'user_agent',
        'referrer',
        'g_recaptcha_response',
        'h_captcha_response',
    );

    /** A single value longer than this is truncated; chat clients hard-wrap the rest anyway. */
    private const MAX_VALUE_LENGTH = 500;

    /**
     * Turn a form plugin's raw payload into fields a person can read.
     *
     * Order is preserved — the order the fields appear on the form is the
     * order the reader expects them in.
     *
     * @param array $raw label/key => value, as the form plugin had it.
     * @return array<string,string> clean label => clean value.
     */
    public static function normalize_fields( array $raw ): array {
        $fields = array();
        $seen   = array();

        foreach ( $raw as $key => $value ) {
            $key = (string) $key;

            if ( self::is_internal_key( $key ) ) {
                continue;
            }

            $value = self::flatten_value( $value );
            if ( $value === '' ) {
                continue;
            }

            $label = self::clean_label( $key );
            if ( $label === '' ) {
                $label = __( 'Field', 'well-web-notify' );
            }

            // The same answer under two keys — Jetpack hands the phone over as
            // both `2_Телефон` (all values) and `3_Телефон` (extra values) —
            // is one answer, and printing it twice reads as two phone numbers.
            $fingerprint = mb_strtolower( $label, 'UTF-8' ) . "\0" . $value;
            if ( isset( $seen[ $fingerprint ] ) ) {
                continue;
            }
            $seen[ $fingerprint ] = true;

            // Two different answers under the same label keep both, numbered,
            // rather than one silently overwriting the other.
            if ( isset( $fields[ $label ] ) ) {
                $n = 2;
                while ( isset( $fields[ $label . ' ' . $n ] ) ) {
                    $n++;
                }
                $label = $label . ' ' . $n;
            }

            $fields[ $label ] = $value;
        }

        return $fields;
    }

    /**
     * Is this a key the form plugin kept for itself?
     */
    private static function is_internal_key( string $key ): bool {
        $key = trim( $key );

        if ( $key === '' || strpos( $key, '_' ) === 0 ) {
            return true;
        }

        $normal = strtolower( preg_replace( '/[\s\-]+/', '_', $key ) );
        $normal = preg_replace( '/^\d{1,3}_/', '', $normal );

        return in_array( $normal, self::INTERNAL_KEYS, true );
    }

    /**
     * A field key as a heading a person would write.
     *
     *   `3_Телефон`  → `Телефон`     (Jetpack's index prefix)
     *   `your-name`  → `Your name`   (Contact Form 7's input slug)
     *   `Full name`  → `Full name`   (already a label — left alone)
     *
     * The humanising step only runs on keys that look like slugs, i.e. have no
     * spaces. A real label containing a hyphen ("E-mail") keeps it.
     */
    public static function clean_label( string $key ): string {
        $label = trim( $key );

        // Jetpack numbers every field so its keys stay unique across a form
        // where two fields share a label. The number is bookkeeping.
        $label = preg_replace( '/^\d{1,3}[_\s]+/u', '', $label );
        $label = trim( (string) $label );

        if ( $label === '' ) {
            return '';
        }

        if ( strpos( $label, ' ' ) === false && preg_match( '/[_\-]/', $label ) ) {
            $label = str_replace( array( '-', '_' ), ' ', $label );
            $label = trim( preg_replace( '/\s+/', ' ', $label ) );
            // ucfirst is byte-based, so it is a no-op on a label that starts
            // with a multi-byte character rather than a corruption of it.
            $label = ucfirst( $label );
        }

        return $label;
    }

    /**
     * One submitted value as a single clean line of text.
     */
    private static function flatten_value( $value ): string {
        if ( is_array( $value ) ) {
            $parts = array();
            foreach ( $value as $item ) {
                $item = self::flatten_value( $item );
                if ( $item !== '' ) {
                    $parts[] = $item;
                }
            }
            $value = implode( ', ', $parts );
        } elseif ( is_bool( $value ) ) {
            $value = $value ? __( 'Yes', 'well-web-notify' ) : __( 'No', 'well-web-notify' );
        } elseif ( ! is_scalar( $value ) ) {
            return '';
        }

        $value = wp_strip_all_tags( (string) $value );
        $value = trim( preg_replace( '/[ \t]+/', ' ', $value ) );

        if ( $value === '' ) {
            return '';
        }

        if ( mb_strlen( $value, 'UTF-8' ) > self::MAX_VALUE_LENGTH ) {
            $value = mb_substr( $value, 0, self::MAX_VALUE_LENGTH, 'UTF-8' ) . '…';
        }

        return $value;
    }

    /**
     * Build the `$meta` payload channels render.
     *
     * @param array $args {
     *   @type string $kind       'form' | 'order' | 'test' | 'health' | 'summary'
     *   @type bool   $silent     Ask the channel to deliver without a sound, where it can.
     *   @type string $title      Headline, e.g. "New form submission".
     *   @type string $form_name  Which form or order this is.
     *   @type array  $fields     Raw fields, pre-normalisation.
     *   @type string $source_url Page the form sits on, if known.
     *   @type string $note       One trailing line, for test/health messages.
     * }
     * @return array
     */
    public static function build( array $args ): array {
        $fields = self::normalize_fields( $args['fields'] ?? array() );
        $raw    = $args['fields'] ?? array();

        // Jetpack hands the page the form is on over as two ordinary-looking
        // fields. They are the answer to "where did this come from", which the
        // message already has a place for, so they go there.
        $source_url = (string) ( $args['source_url'] ?? '' );
        $form_name  = (string) ( $args['form_name'] ?? '' );

        foreach ( $raw as $key => $value ) {
            $normal = strtolower( preg_replace( '/[\s\-]+/', '_', trim( (string) $key ) ) );
            $normal = preg_replace( '/^\d{1,3}_/', '', $normal );

            if ( $normal === 'entry_permalink' && $source_url === '' && is_string( $value ) ) {
                $source_url = esc_url_raw( $value );
            }
            if ( $normal === 'entry_title' && $form_name === '' && is_string( $value ) ) {
                $form_name = wp_strip_all_tags( $value );
            }
        }

        $title = (string) ( $args['title'] ?? __( 'New form submission', 'well-web-notify' ) );

        // A staging copy still delivers — forms there get tested — but says so
        // in the headline, so a test enquiry in the client's group is never
        // mistaken for a real lead.
        $staging = ! wellweb_notify_is_production();
        if ( $staging ) {
            /* translators: %s: notification headline */
            $title = sprintf( __( '[Staging] %s', 'well-web-notify' ), $title );
        }

        $meta = array(
            'kind'       => (string) ( $args['kind'] ?? 'form' ),
            'title'      => $title,
            'staging'    => $staging,
            'silent'     => ! empty( $args['silent'] ),
            'form_name'  => $form_name,
            'site'       => wellweb_notify_site_domain(),
            'site_url'   => home_url(),
            'source_url' => $source_url,
            'source_label' => (string) ( $args['source_label'] ?? '' ),
            'fields'     => $fields,
            'phone'      => wellweb_notify_extract_phone( $fields ),
            'note'       => (string) ( $args['note'] ?? '' ),
        );

        /**
         * Last word on what a notification contains.
         *
         * @param array $meta The normalised message.
         */
        return apply_filters( 'wellweb_notify_message', $meta );
    }


    /**
     * A message built by build(), or one assembled on the spot.
     *
     * Channels receive `$meta` from three kinds of caller: a form integration,
     * which passes a built message; the test button and the daily health
     * check, which pass a line of text; and any third-party code hooked onto
     * `wellweb_notify_register_channels`, which may pass almost anything. This
     * gives all three the same shape so a channel renders one thing.
     */
    public static function ensure( array $meta, string $subject = '', string $body = '' ): array {
        if ( isset( $meta['title'], $meta['site'] ) ) {
            return $meta;
        }

        $built = self::build( array(
            'kind'         => $meta['kind'] ?? 'notice',
            'title'        => $subject !== '' ? $subject : __( 'Notification', 'well-web-notify' ),
            'form_name'    => $meta['form_name'] ?? '',
            'fields'       => $meta['fields'] ?? array(),
            'source_url'   => $meta['source_url'] ?? '',
            'source_label' => $meta['source_label'] ?? '',
            'note'         => $body,
        ) );

        // A caller that worked the phone out itself — WooCommerce derives the
        // dialling code from the billing country — knows better than the
        // label-matching in extract_phone().
        if ( ! empty( $meta['phone'] ) ) {
            $built['phone'] = $meta['phone'];
        }

        return $built;
    }

    /**
     * The text of the one link at the foot: where this came from.
     */
    public static function source_label( array $meta ): string {
        if ( ! empty( $meta['source_label'] ) ) {
            return (string) $meta['source_label'];
        }
        return __( 'Open page', 'well-web-notify' );
    }

    /**
     * The line under the headline: which site, and which form.
     *
     * "lawfirmimperial.com · Военные дела" — one line answering both questions
     * a person asks when a message arrives in a group that watches several
     * sites.
     */
    public static function context_line( array $meta ): string {
        $parts = array_filter( array(
            $meta['site'] ?? '',
            $meta['form_name'] ?? '',
        ) );

        return implode( ' · ', $parts );
    }

    /**
     * The submitted answers, one per line, in a channel's own emphasis syntax.
     *
     * @param array    $meta
     * @param string   $bold_open  e.g. '<b>' or '*' or '**'
     * @param string   $bold_close
     * @param callable $escape     Applied to every label and value.
     * @return string
     */
    public static function field_lines( array $meta, string $bold_open, string $bold_close, callable $escape ): string {
        $lines = array();

        foreach ( (array) ( $meta['fields'] ?? array() ) as $label => $value ) {
            $lines[] = $bold_open . $escape( $label ) . ':' . $bold_close . ' ' . $escape( $value );
        }

        if ( ! $lines && ! empty( $meta['note'] ) ) {
            $lines[] = $escape( $meta['note'] );
        }

        return implode( "\n", $lines );
    }

    /**
     * A legacy plain-text body, for third-party channels registered through
     * `wellweb_notify_register_channels` that were written against the old
     * `$body` string.
     */
    public static function plain_body( array $meta ): string {
        $lines = array();

        foreach ( (array) ( $meta['fields'] ?? array() ) as $label => $value ) {
            $lines[] = $label . ': ' . $value;
        }

        if ( ! $lines && ! empty( $meta['note'] ) ) {
            $lines[] = $meta['note'];
        }

        if ( ! empty( $meta['source_url'] ) ) {
            $lines[] = $meta['source_url'];
        }

        return implode( "\n", $lines );
    }
}

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Data Encryption class for securing API keys/tokens.
 *
 * Uses AES-256-CTR with HMAC-SHA256 authentication.
 * Encryption key derived from WordPress LOGGED_IN_KEY / LOGGED_IN_SALT.
 *
 * Format v2 (enc2:): base64( IV + ciphertext + HMAC-SHA256 )
 * Format v1 (enc:):  base64( IV + ciphertext ) — legacy, read-only
 */
class WellWeb_Notify_Encryption {

    private $method = 'aes-256-ctr';
    private $key;
    private $salt;
    private $key_available;
    private $openssl_available;

    public function __construct() {
        $this->openssl_available = extension_loaded( 'openssl' );
        $this->key_available     = defined( 'LOGGED_IN_KEY' ) && '' !== LOGGED_IN_KEY;

        $this->key  = $this->key_available ? LOGGED_IN_KEY : 'well-web-notify-fallback-key';
        $this->salt = ( defined( 'LOGGED_IN_SALT' ) && '' !== LOGGED_IN_SALT )
            ? LOGGED_IN_SALT : 'well-web-notify-fallback-salt';
    }

    /**
     * Whether proper WordPress security keys are configured.
     */
    public function has_secure_key(): bool {
        return $this->key_available && $this->openssl_available;
    }

    /**
     * Encrypt a value (v2 format with HMAC).
     *
     * Returns empty string on failure — never stores plaintext.
     */
    public function encrypt( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        if ( ! $this->openssl_available ) {
            return ''; // Never store plaintext
        }

        $iv_length = openssl_cipher_iv_length( $this->method );
        $iv        = openssl_random_pseudo_bytes( $iv_length );
        $raw       = openssl_encrypt( $value . $this->salt, $this->method, $this->key, 0, $iv );

        if ( ! $raw ) {
            return '';
        }

        $payload = $iv . $raw;
        $hmac    = hash_hmac( 'sha256', $payload, $this->key, true ); // 32 bytes

        return 'enc2:' . base64_encode( $payload . $hmac );
    }

    /**
     * Decrypt a value — supports both v2 (enc2:) and v1 (enc:) formats.
     */
    public function decrypt( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        if ( strpos( $value, 'enc2:' ) === 0 ) {
            return $this->decrypt_v2( $value );
        }

        if ( strpos( $value, 'enc:' ) === 0 ) {
            return $this->decrypt_v1( $value );
        }

        return $value; // Unencrypted legacy value
    }

    /**
     * Decrypt v2 format (with HMAC authentication).
     */
    private function decrypt_v2( $value ) {
        if ( ! $this->openssl_available ) {
            return '';
        }

        $raw = base64_decode( substr( $value, 5 ), true ); // 5 = strlen('enc2:')
        if ( ! $raw ) {
            return '';
        }

        // Extract HMAC (last 32 bytes)
        $hmac_length = 32;
        if ( strlen( $raw ) <= $hmac_length ) {
            return '';
        }

        $payload   = substr( $raw, 0, -$hmac_length );
        $stored_hmac = substr( $raw, -$hmac_length );

        // Verify HMAC — constant-time comparison
        $expected_hmac = hash_hmac( 'sha256', $payload, $this->key, true );
        if ( ! hash_equals( $expected_hmac, $stored_hmac ) ) {
            return ''; // Tampered or wrong key
        }

        // Decrypt payload (IV + ciphertext)
        $iv_length = openssl_cipher_iv_length( $this->method );
        $iv        = substr( $payload, 0, $iv_length );
        $ciphertext = substr( $payload, $iv_length );

        $decrypted = openssl_decrypt( $ciphertext, $this->method, $this->key, 0, $iv );
        if ( ! $decrypted ) {
            return '';
        }

        // Verify and strip salt
        $salt_length = strlen( $this->salt );
        if ( substr( $decrypted, -$salt_length ) === $this->salt ) {
            return substr( $decrypted, 0, -$salt_length );
        }

        return '';
    }

    /**
     * Decrypt v1 format (legacy, no HMAC).
     */
    private function decrypt_v1( $value ) {
        if ( ! $this->openssl_available ) {
            return '';
        }

        $raw = base64_decode( substr( $value, 4 ), true ); // 4 = strlen('enc:')
        if ( ! $raw ) {
            return '';
        }

        $iv_length = openssl_cipher_iv_length( $this->method );
        $iv        = substr( $raw, 0, $iv_length );
        $raw       = substr( $raw, $iv_length );

        $decrypted = openssl_decrypt( $raw, $this->method, $this->key, 0, $iv );
        if ( ! $decrypted ) {
            return '';
        }

        $salt_length = strlen( $this->salt );
        if ( substr( $decrypted, -$salt_length ) === $this->salt ) {
            return substr( $decrypted, 0, -$salt_length );
        }

        return '';
    }
}

function wellweb_notify_encryption() {
    static $instance = null;
    if ( null === $instance ) {
        $instance = new WellWeb_Notify_Encryption();
    }
    return $instance;
}

function wellweb_notify_get_option( $name, $default = '' ) {
    return get_option( 'well-web-notify-' . $name, $default );
}

function wellweb_notify_get_encrypted( $name ) {
    $val = get_option( 'well-web-notify-' . $name, '' );
    return empty( $val ) ? '' : wellweb_notify_encryption()->decrypt( $val );
}

function wellweb_notify_encrypt( $value ) {
    if ( empty( $value ) ) {
        return '';
    }
    if ( strpos( $value, 'enc:' ) === 0 || strpos( $value, 'enc2:' ) === 0 ) {
        return $value;
    }
    return wellweb_notify_encryption()->encrypt( $value );
}

function wellweb_notify_sanitize_encrypted( $value ) {
    $value = sanitize_text_field( $value );
    return empty( $value ) ? $value : wellweb_notify_encrypt( $value );
}

function wellweb_notify_mask( $key ) {
    if ( empty( $key ) || strlen( $key ) < 16 ) {
        return $key ? str_repeat( '•', 8 ) : '';
    }
    return substr( $key, 0, 8 ) . str_repeat( '•', 8 ) . substr( $key, -4 );
}

// ─── Phone contact links ─────────────────────────────────────

/**
 * Check if a field label looks like a phone number field.
 */
function wellweb_notify_is_phone_label( string $label ): bool {
    $label    = mb_strtolower( trim( $label ), 'UTF-8' );
    $patterns = array(
        // English
        'phone', 'tel', 'telephone', 'phone number', 'phone no',
        'your phone', 'your tel', 'mobile', 'mobile number',
        'cell', 'cell phone', 'contact number',
        // German / Swedish / Nordic
        'telefon', 'mobilnummer', 'telefonnummer',
        // Spanish
        'teléfono', 'móvil', 'celular',
        // French
        'téléphone', 'portable',
        // Portuguese
        'telefone', 'telemóvel',
        // Italian
        'cellulare',
        // Dutch
        'telefoon', 'mobiel',
        // Polish
        'komórka', 'numer telefonu',
        // Turkish
        'cep telefonu',
        // Ukrainian / Russian
        'телефон', 'тел', 'мобільний', 'мобильный',
        'номер телефону', 'номер телефона',
    );

    foreach ( $patterns as $pattern ) {
        if ( $label === $pattern || strpos( $label, $pattern ) !== false ) {
            return true;
        }
    }
    return false;
}

/**
 * Check if a field label looks like a country / dial code field.
 */
function wellweb_notify_is_country_label( string $label ): bool {
    $label    = mb_strtolower( trim( $label ), 'UTF-8' );
    $patterns = array(
        // English
        'country code', 'dial code', 'dialing code', 'phone code', 'country',
        // German / Swedish / Nordic
        'landskod', 'landesvorwahl', 'landcode',
        // Spanish
        'código de país', 'código país', 'país',
        // French
        'indicatif', 'code pays', 'pays',
        // Portuguese
        'código do país',
        // Italian
        'prefisso', 'paese',
        // Dutch
        'landcode', 'landnummer',
        // Polish
        'numer kierunkowy', 'kod kraju',
        // Turkish
        'ülke kodu',
        // Ukrainian / Russian
        'код країни', 'код страни', 'країна', 'страна',
    );

    foreach ( $patterns as $pattern ) {
        if ( $label === $pattern || strpos( $label, $pattern ) !== false ) {
            return true;
        }
    }
    return false;
}

/**
 * Normalize a phone number into E.164-like format (+XXXXXXXXXXX).
 *
 * @param string $phone        Raw phone value from form.
 * @param string $country_code Country/dial code from form field (may be empty).
 * @return string Normalized number like "+380501234567" or "" if ambiguous.
 */
function wellweb_notify_normalize_phone( string $phone, string $country_code = '' ): string {
    $has_plus = ( strpos( $phone, '+' ) === 0 );
    $digits   = preg_replace( '/[^0-9]/', '', $phone );

    if ( $digits === '' ) {
        return '';
    }

    // Already international with +
    if ( $has_plus ) {
        return '+' . $digits;
    }

    // International 00 prefix (e.g. 0038050...)
    if ( strpos( $digits, '00' ) === 0 && strlen( $digits ) > 6 ) {
        return '+' . substr( $digits, 2 );
    }

    // Separate country code field from the form
    if ( $country_code !== '' ) {
        $cc = preg_replace( '/[^0-9]/', '', $country_code );
        if ( $cc !== '' ) {
            return '+' . $cc . ltrim( $digits, '0' );
        }
    }

    // Plugin default country code setting
    $default_cc = wellweb_notify_get_option( 'default-country-code', '' );
    $default_cc = preg_replace( '/[^0-9]/', '', (string) $default_cc );

    if ( $default_cc !== '' ) {
        return '+' . $default_cc . ltrim( $digits, '0' );
    }

    // No country code at all — if long enough, assume international
    if ( strlen( $digits ) >= 10 ) {
        return '+' . $digits;
    }

    return '';
}

/**
 * Check if a value looks like a phone number based on its format.
 */
function wellweb_notify_is_phone_value( string $value ): bool {
    // Must contain only digits and common phone separators.
    if ( ! preg_match( '/^[\d\s\-\(\)\.\/+]+$/', $value ) ) {
        return false;
    }

    $digits = preg_replace( '/[^0-9]/', '', $value );
    $len    = strlen( $digits );

    // E.164: 7–15 digits.
    if ( $len < 7 || $len > 15 ) {
        return false;
    }

    // At least 50% of the string should be digits (filters out "12/34/5678" date-like).
    if ( $len / strlen( trim( $value ) ) < 0.5 ) {
        return false;
    }

    return true;
}

/**
 * Scan form fields for a phone number and normalize it.
 *
 * Phase 1: label-based detection (fast, precise).
 * Phase 2: value-based fallback (language-agnostic).
 *
 * @param array $fields Associative label => value pairs from the form.
 * @return string Normalized phone like "+380501234567" or "".
 */
function wellweb_notify_extract_phone( array $fields ): string {
    $phone_value   = '';
    $country_value = '';

    // Phase 1: label-based detection.
    foreach ( $fields as $label => $value ) {
        $val = trim( (string) ( is_array( $value ) ? implode( ', ', $value ) : $value ) );

        if ( $phone_value === '' && wellweb_notify_is_phone_label( $label ) ) {
            $phone_value = $val;
        } elseif ( $country_value === '' && wellweb_notify_is_country_label( $label ) ) {
            $country_value = $val;
        }
    }

    if ( $phone_value !== '' ) {
        return wellweb_notify_normalize_phone( $phone_value, $country_value );
    }

    // Phase 2: value-based fallback — scan for phone-shaped values.
    foreach ( $fields as $label => $value ) {
        $val = trim( (string) ( is_array( $value ) ? implode( ', ', $value ) : $value ) );

        // Skip emails, URLs, and long text.
        if ( strpos( $val, '@' ) !== false || strpos( $val, '://' ) !== false || strlen( $val ) > 30 ) {
            continue;
        }

        if ( wellweb_notify_is_phone_value( $val ) ) {
            return wellweb_notify_normalize_phone( $val, $country_value );
        }
    }

    return '';
}

/**
 * Site host without scheme or "www." prefix — used as the notification header.
 */
function wellweb_notify_site_domain(): string {
    $host = wp_parse_url( home_url(), PHP_URL_HOST );
    if ( ! is_string( $host ) || $host === '' ) {
        return get_bloginfo( 'name' );
    }
    return preg_replace( '/^www\./i', '', $host );
}

// ─── Production or staging ───────────────────────────────────

/**
 * A host as the plugin compares it: lowercased, no "www.", no port.
 */
function wellweb_notify_normalize_host( string $host ): string {
    $host = strtolower( trim( $host ) );
    $host = preg_replace( '#^[a-z]+://#', '', $host );
    $host = preg_replace( '#[/:?].*$#', '', $host );
    return preg_replace( '/^www\./', '', $host );
}

/**
 * Does this hostname look like a staging or development copy?
 *
 * Only consulted while no production domain is stored, to decide whether this
 * site may claim the role. A host that matches is never auto-claimed.
 */
function wellweb_notify_host_looks_like_staging( string $host ): bool {
    $host = wellweb_notify_normalize_host( $host );

    if ( preg_match( '/^(staging|stage|stg|dev|develop|development|test|testing|qa|uat|preview|sandbox)[\.\-0-9]/', $host ) ) {
        return true;
    }

    $suffixes = array(
        '.local', '.test', '.localhost', '.invalid', '.example', 'localhost',
        '.mystagingwebsite.com', // Pressable
        '.wpengine.com', '.wpenginepowered.com',
        '.kinsta.cloud', '.kinsta.com',
        '.flywheelstaging.com', '.flywheelsites.com',
        '.cloudwaysapps.com',
        '.wpcomstaging.com',
        '.instawp.xyz', '.tastewp.com',
    );
    foreach ( $suffixes as $suffix ) {
        if ( substr( $host, -strlen( $suffix ) ) === $suffix ) {
            return true;
        }
    }

    return (bool) preg_match( '/(^|\.)(staging|stage|dev)\./', $host );
}

/**
 * The domain recorded as this site's live one, or '' if none is yet.
 */
function wellweb_notify_production_domain(): string {
    return wellweb_notify_normalize_host( (string) wellweb_notify_get_option( 'production-domain', '' ) );
}

/**
 * Is this the live site, rather than a staging or development copy?
 *
 * Two signals, both of which survive a production database being copied onto
 * staging — which is how most staging sites are refreshed, and why a stored
 * "this is production" checkbox would be wrong the day after the first sync:
 *
 *  1. `wp_get_environment_type()` — set in wp-config.php or by the host, never
 *     in the database. Anything other than `production` is not production.
 *  2. The stored production domain, compared against the domain this request
 *     is actually running on. A copied database carries the live domain with
 *     it, so on staging the two do not match.
 *
 * With no production domain stored yet, a host that looks like staging is
 * treated as staging.
 */
function wellweb_notify_is_production(): bool {
    $is_production = true;

    if ( function_exists( 'wp_get_environment_type' ) && wp_get_environment_type() !== 'production' ) {
        $is_production = false;
    } else {
        $current    = wellweb_notify_normalize_host( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
        $production = wellweb_notify_production_domain();

        if ( $production !== '' ) {
            $is_production = ( $current === $production );
        } else {
            $is_production = ! wellweb_notify_host_looks_like_staging( $current );
        }
    }

    /**
     * Last word on whether this site counts as production.
     *
     * @param bool $is_production
     */
    return (bool) apply_filters( 'wellweb_notify_is_production', $is_production );
}

/**
 * Why this site is, or is not, treated as production — for the admin screen.
 *
 * @return array{ production: bool, current: string, recorded: string, type: string, reason: string }
 */
function wellweb_notify_environment(): array {
    $current  = wellweb_notify_normalize_host( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
    $recorded = wellweb_notify_production_domain();
    $type     = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';

    if ( $type !== 'production' ) {
        $reason = 'environment';
    } elseif ( $recorded !== '' ) {
        $reason = $current === $recorded ? 'domain-match' : 'domain-mismatch';
    } else {
        $reason = wellweb_notify_host_looks_like_staging( $current ) ? 'host-pattern' : 'unrecorded';
    }

    return array(
        'production' => wellweb_notify_is_production(),
        'current'    => $current,
        'recorded'   => $recorded,
        'type'       => $type,
        'reason'     => $reason,
    );
}

/**
 * Record the live domain the first time an admin opens WordPress on it.
 *
 * Only when nothing is recorded yet, the environment type is `production` and
 * the host does not look like staging — so a staging copy never claims the
 * role. After that the field is the admin's to change.
 */
add_action( 'admin_init', 'wellweb_notify_claim_production_domain' );
function wellweb_notify_claim_production_domain() {
    if ( ! current_user_can( 'manage_options' ) || wellweb_notify_production_domain() !== '' ) {
        return;
    }
    if ( function_exists( 'wp_get_environment_type' ) && wp_get_environment_type() !== 'production' ) {
        return;
    }

    $current = wellweb_notify_normalize_host( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
    if ( $current === '' || wellweb_notify_host_looks_like_staging( $current ) ) {
        return;
    }

    update_option( 'well-web-notify-production-domain', $current );
}

/**
 * Sanitise the production domain field: a bare host, or empty.
 */
function wellweb_notify_sanitize_domain( $value ): string {
    return wellweb_notify_normalize_host( sanitize_text_field( (string) $value ) );
}

/**
 * Where a failed check is reported. Falls back to the site admin email.
 */
function wellweb_notify_alert_email(): string {
    $email = sanitize_email( (string) wellweb_notify_get_option( 'alert-email', '' ) );
    return $email !== '' ? $email : (string) get_option( 'admin_email', '' );
}

/**
 * Build contact link URLs for a normalized phone number.
 *
 * @param string $phone E.164 phone like "+380501234567".
 * @return array Associative array of link URLs.
 */
function wellweb_notify_phone_links( string $phone ): array {
    $digits = ltrim( $phone, '+' );

    return array(
        'tel'       => 'tel:' . $phone,
        'whatsapp'  => 'https://wa.me/' . $digits,
        'telegram'  => 'https://t.me/' . $phone,
        'viber'     => 'viber://chat?number=' . urlencode( $phone ),
        'viber_web' => 'https://viber.click/' . $phone,
    );
}

/**
 * The message the "Test" button sends.
 *
 * One definition for all four channels: they were sending four slightly
 * different sentences, and a test whose wording depends on which channel you
 * pressed it in is a test you cannot compare.
 *
 * @return array
 */
function wellweb_notify_test_message(): array {
    return WellWeb_Notify_Message::build( array(
        'kind'  => 'test',
        'title' => __( 'Test message', 'well-web-notify' ),
        'note'  => __( 'Connected. New form submissions from this site will arrive here.', 'well-web-notify' ),
    ) );
}

// ─── Encryption health admin notice ──────────────────────────

add_action( 'admin_notices', 'wellweb_notify_encryption_notice' );
function wellweb_notify_encryption_notice() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $enc = wellweb_notify_encryption();

    if ( ! extension_loaded( 'openssl' ) ) {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>' . esc_html__( 'Well Web Notify:', 'well-web-notify' ) . '</strong> ';
        echo esc_html__( 'The OpenSSL PHP extension is not available. API credentials cannot be encrypted and will not be saved. Please enable OpenSSL in your PHP configuration.', 'well-web-notify' );
        echo '</p></div>';
        return;
    }

    if ( ! $enc->has_secure_key() ) {
        echo '<div class="notice notice-warning"><p>';
        echo '<strong>' . esc_html__( 'Well Web Notify:', 'well-web-notify' ) . '</strong> ';
        echo esc_html__( 'WordPress security keys (LOGGED_IN_KEY) are not properly configured. Your API credentials are stored with weak encryption. Please add unique security keys to wp-config.php.', 'well-web-notify' );
        echo '</p></div>';
    }
}

/**
 * Prevent overwriting encrypted values with empty submissions
 */
add_filter( 'pre_update_option', 'wellweb_notify_preserve_encrypted', 10, 3 );
function wellweb_notify_preserve_encrypted( $value, $option, $old_value ) {
    $protected = array(
        'well-web-notify-telegram-token',
        'well-web-notify-slack-webhook',
        'well-web-notify-discord-webhook',
        'well-web-notify-google-chat-webhook',
    );

    if ( in_array( $option, $protected, true ) && empty( $value ) && ! empty( $old_value ) ) {
        return $old_value;
    }

    return $value;
}

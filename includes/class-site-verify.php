<?php
/**
 * Site Verification for Well Web Notify
 *
 * Generates a unique per-site token that proves the WordPress admin
 * who configured the bot also has access to the messenger group/channel.
 *
 * Flow:
 * 1. Plugin generates a site token on activation (stored in wp_options)
 * 2. Admin sees the token in channel settings
 * 3. After adding the bot to a group, admin sends: /verify <token>
 * 4. The plugin's test button verifies the connection works
 * 5. Token can be regenerated if compromised
 *
 * This is a simple but effective ownership proof — the person who has
 * access to both the WordPress admin AND the messenger group is the
 * legitimate owner.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WellWeb_Notify_Site_Verify {

    const OPTION_TOKEN = 'wellweb-notify-site-token';

    /**
     * Get or generate the site verification token
     */
    public static function get_site_token(): string {
        $token = get_option( self::OPTION_TOKEN, '' );

        if ( empty( $token ) ) {
            $token = self::generate_token();
            update_option( self::OPTION_TOKEN, $token, false );
        }

        return $token;
    }

    /**
     * Regenerate the site token (e.g., if compromised)
     */
    public static function regenerate_token(): string {
        $token = self::generate_token();
        update_option( self::OPTION_TOKEN, $token, false );
        return $token;
    }

    /**
     * Verify a token matches this site
     */
    public static function verify_token( string $input_token ): bool {
        $site_token = self::get_site_token();
        return hash_equals( $site_token, trim( $input_token ) );
    }

    /**
     * Generate a short, human-friendly token
     * Format: XXXX-XXXX-XXXX (12 alphanumeric chars with dashes)
     */
    private static function generate_token(): string {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // No 0/O/1/I to avoid confusion
        $token = '';

        for ( $i = 0; $i < 12; $i++ ) {
            if ( $i > 0 && $i % 4 === 0 ) {
                $token .= '-';
            }
            $token .= $chars[ wp_rand( 0, strlen( $chars ) - 1 ) ];
        }

        return $token;
    }

    /**
     * Get the verify command for display
     */
    public static function get_verify_command(): string {
        return '/verify ' . self::get_site_token();
    }
}

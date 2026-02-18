<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WellWeb_Notify_Log {

    const TABLE_NAME = 'wellweb_notify_log';

    /**
     * Create the log table on plugin activation
     */
    public static function create_table() {
        global $wpdb;

        $table   = $wpdb->prefix . self::TABLE_NAME;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            channel VARCHAR(50) NOT NULL DEFAULT '',
            form_name VARCHAR(200) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'success',
            error_message TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_channel (channel),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Log a notification send attempt
     */
    public static function log( string $channel, string $form_name, string $status, string $error = '' ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom log table
        $wpdb->insert(
            $wpdb->prefix . self::TABLE_NAME,
            array(
                'channel'       => $channel,
                'form_name'     => $form_name,
                'status'        => $status,
                'error_message' => $error,
                'created_at'    => current_time( 'mysql', true ),
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Get log entries with filtering and pagination
     */
    public static function get_entries( array $args = [] ): array {
        global $wpdb;

        $defaults = array(
            'channel'  => '',
            'status'   => '',
            'per_page' => 20,
            'page'     => 1,
        );
        $args = wp_parse_args( $args, $defaults );

        $table         = $wpdb->prefix . self::TABLE_NAME;
        $where_clauses = array( '1=1' );

        if ( $args['channel'] ) {
            $where_clauses[] = $wpdb->prepare( 'channel = %s', $args['channel'] );
        }
        if ( $args['status'] ) {
            $where_clauses[] = $wpdb->prepare( 'status = %s', $args['status'] );
        }

        $where_sql = implode( ' AND ', $where_clauses );
        $offset    = ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'];

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom log table; $where_sql is pre-prepared above
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" );

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                (int) $args['per_page'],
                $offset
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return array(
            'items'    => $items ?: array(),
            'total'    => $total,
            'pages'    => ceil( $total / max( 1, $args['per_page'] ) ),
            'page'     => $args['page'],
            'per_page' => $args['per_page'],
        );
    }

    /**
     * Get counts grouped by status for the last N days
     */
    public static function get_stats( int $days = 7 ): array {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        $since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom log table; $table uses $wpdb->prefix + hardcoded constant
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT status, COUNT(*) as cnt FROM {$table} WHERE created_at >= %s GROUP BY status",
            $since
        ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $stats = array( 'success' => 0, 'error' => 0, 'total' => 0 );
        foreach ( $results as $row ) {
            $stats[ $row->status ] = (int) $row->cnt;
            $stats['total']       += (int) $row->cnt;
        }

        return $stats;
    }

    /**
     * Clean up old entries (keeps last 30 days)
     */
    public static function cleanup( int $days = 30 ) {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        $before = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom log table; $table uses $wpdb->prefix + hardcoded constant
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s",
            $before
        ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }
}

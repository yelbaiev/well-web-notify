<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WellWeb_Notify_WooCommerce implements WellWeb_Notify_Form {

    /**
     * Flag to prevent duplicate notifications when both new-order
     * and status-changed hooks fire on the same request.
     */
    private static $new_order_sent = false;

    public function get_slug(): string {
        return 'woocommerce';
    }

    public function get_label(): string {
        return 'WooCommerce Orders';
    }

    public function is_available(): bool {
        return class_exists( 'WooCommerce' );
    }

    public function register_hooks(): void {
        $enabled = get_option( 'well-web-notify-woo-enabled', false );
        if ( empty( $enabled ) ) {
            return;
        }

        // New order hook (fires at checkout before status transitions)
        if ( get_option( 'well-web-notify-woo-event-new-order', false ) ) {
            add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_new_order' ), 10, 1 );
        }

        // Generic status change hook — handles all status transitions with granular filtering
        add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 10, 4 );
    }

    /**
     * Fired when a new order is placed via checkout.
     */
    public function on_new_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        self::$new_order_sent = true;

        $this->dispatch(
            $order,
            sprintf(
                /* translators: %s: order number */
                __( 'New order #%s', 'well-web-notify' ),
                $order->get_order_number()
            )
        );
    }

    /**
     * Fired on any order status change with old/new status info.
     */
    public function on_status_changed( $order_id, $old_status, $new_status, $order ) {
        // Skip if this is a new order and we already sent a notification
        if ( self::$new_order_sent && $old_status === 'pending' ) {
            return;
        }

        // Check if this specific status transition is enabled
        if ( ! get_option( 'well-web-notify-woo-event-' . $new_status, false ) ) {
            return;
        }

        // Master toggle
        if ( ! get_option( 'well-web-notify-woo-enabled', false ) ) {
            return;
        }

        if ( ! $order instanceof WC_Order ) {
            $order = wc_get_order( $order_id );
        }
        if ( ! $order ) {
            return;
        }

        $this->dispatch(
            $order,
            sprintf(
                /* translators: 1: order number, 2: previous status, 3: new status */
                __( 'Order #%1$s: %2$s → %3$s', 'well-web-notify' ),
                $order->get_order_number(),
                wc_get_order_status_name( $old_status ),
                wc_get_order_status_name( $new_status )
            )
        );
    }

    /**
     * Build structured order fields.
     */
    private function build_order_fields( WC_Order $order ): array {
        $fields = array();

        $name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        if ( $name ) {
            $fields['Customer'] = $name;
        }

        $email = $order->get_billing_email();
        if ( $email ) {
            $fields['Email'] = $email;
        }

        $phone = $order->get_billing_phone();
        if ( $phone ) {
            $fields['Phone'] = $phone;
        }

        // Order items
        $items = array();
        foreach ( $order->get_items() as $item ) {
            $qty   = $item->get_quantity();
            $total = $order->get_formatted_line_subtotal( $item );
            $items[] = "{$item->get_name()} × {$qty} — {$total}";
        }
        if ( ! empty( $items ) ) {
            $fields['Items'] = implode( "\n", $items );
        }

        $fields['Total'] = $order->get_formatted_order_total();

        $payment = $order->get_payment_method_title();
        if ( $payment ) {
            $fields['Payment'] = $payment;
        }

        $city    = $order->get_billing_city();
        $country = $order->get_billing_country();
        $location_parts = array_filter( array( $city, $country ) );
        if ( ! empty( $location_parts ) ) {
            $fields['Location'] = implode( ', ', $location_parts );
        }

        return $fields;
    }

    /**
     * Build the message and hand it to every active channel.
     *
     * One place, because the two entry points above differ only in their
     * headline and were otherwise the same nine lines twice.
     *
     * @param WC_Order $order
     * @param string   $title Headline, e.g. "New order #1042".
     */
    private function dispatch( WC_Order $order, string $title ): void {
        $manager = WellWeb_Notify_Channel_Manager::instance();
        if ( ! $manager->get_active_channels() ) {
            return;
        }

        $meta = WellWeb_Notify_Message::build( array(
            'kind'         => 'order',
            'title'        => $title,
            'form_name'    => '',
            'fields'       => $this->build_order_fields( $order ),
            'source_url'   => $order->get_edit_order_url(),
            'source_label' => __( 'Open order', 'well-web-notify' ),
        ) );

        // The billing country gives a dialling code the label-matching in
        // extract_phone() has no way to know, so the order's own answer wins.
        $phone = $this->resolve_phone( $order );
        if ( $phone !== '' ) {
            $meta['phone'] = $phone;
        }

        $manager->send_all( $title, WellWeb_Notify_Message::plain_body( $meta ), $meta );
    }

    /**
     * The customer's phone in E.164, using the billing country's dialling code
     * when the number was typed in national form.
     */
    private function resolve_phone( WC_Order $order ): string {
        $phone_raw = $order->get_billing_phone();
        if ( ! $phone_raw ) {
            return '';
        }

        $calling_codes = array(
            'US' => '1', 'CA' => '1', 'GB' => '44', 'AU' => '61',
            'DE' => '49', 'FR' => '33', 'SE' => '46', 'NO' => '47',
            'DK' => '45', 'FI' => '358', 'UA' => '380', 'PL' => '48',
            'IT' => '39', 'ES' => '34', 'NL' => '31', 'BE' => '32',
            'AT' => '43', 'CH' => '41', 'IE' => '353', 'PT' => '351',
            'CZ' => '420', 'RO' => '40', 'HU' => '36', 'BG' => '359',
            'HR' => '385', 'SK' => '421', 'SI' => '386', 'LT' => '370',
            'LV' => '371', 'EE' => '372', 'GR' => '30', 'IL' => '972',
            'IN' => '91', 'JP' => '81', 'KR' => '82', 'CN' => '86',
            'BR' => '55', 'MX' => '52', 'AR' => '54', 'NZ' => '64',
            'ZA' => '27', 'RU' => '7', 'TR' => '90', 'AE' => '971',
            'SA' => '966', 'EG' => '20', 'NG' => '234', 'KE' => '254',
            'TH' => '66', 'VN' => '84', 'PH' => '63', 'MY' => '60',
            'SG' => '65', 'ID' => '62', 'PK' => '92', 'BD' => '880',
            'CL' => '56', 'CO' => '57', 'PE' => '51',
        );

        $country = $order->get_billing_country();

        return wellweb_notify_normalize_phone( $phone_raw, $calling_codes[ $country ] ?? '' );
    }
}

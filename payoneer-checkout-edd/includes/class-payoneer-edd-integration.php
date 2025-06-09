<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Payoneer_EDD_Integration {

    public function __construct() {
        // Hook into EDD to process payments with the Payoneer gateway.
        add_action( 'edd_gateway_payoneer', array( $this, 'process_payment' ) );
        // Handle any return parameters (e.g., after a 3DS authentication) from Payoneer.
        add_action( 'init', array( $this, 'handle_return' ) );
    }

    /**
     * Process the Payoneer payment.
     *
     * @param array $purchase_data The purchase data from EDD.
     */
    public function process_payment( $purchase_data ) {
        $api_token        = edd_get_option( 'payoneer_api_token', '' );
        $integration_mode = edd_get_option( 'payoneer_integration_mode', 'hosted' );
        $test_mode        = edd_get_option( 'payoneer_test_mode', false ) ? true : false;

        // Build session data for the LIST request.
        $session_data = array(
            'transactionId' => $purchase_data['purchase_key'],
            'country'       => isset( $purchase_data['user_info']['address']['country'] ) ? $purchase_data['user_info']['address']['country'] : '',
            'language'      => substr( get_locale(), 0, 2 ),
            'customer'      => array(
                'email'           => $purchase_data['user_email'],
                'billingAddress'  => $purchase_data['user_info']['address'],
                'shippingAddress' => isset( $purchase_data['user_info']['shipping'] ) ? $purchase_data['user_info']['shipping'] : $purchase_data['user_info']['address'],
            ),
            // Optionally include additional product cart data.
        );

        $api = new Payoneer_API( array(
            'api_token' => $api_token,
            'test_mode' => $test_mode
        ) );

        // Create a new payment session.
        $list_response = $api->create_list_session( $session_data );
        if ( ! $list_response['success'] ) {
            edd_record_gateway_error( 'Payoneer Checkout Error', print_r( $list_response, true ) );
            edd_send_back_to_checkout( '?payment-mode=payoneer' );
            exit;
        }

        $list_data = $list_response['data'];
        $list_id   = isset( $list_data['listId'] ) ? $list_data['listId'] : '';

        // Build charge data for the CHARGE request.
        $charge_data = array(
            'amount'      => $purchase_data['price'],
            'currency'    => edd_currency(),
            'description' => 'Purchase from ' . get_bloginfo( 'name' ),
            'browserInfo' => array(
                'screenHeight' => isset( $_COOKIE['screenHeight'] ) ? sanitize_text_field( $_COOKIE['screenHeight'] ) : '',
                'screenWidth'  => isset( $_COOKIE['screenWidth'] ) ? sanitize_text_field( $_COOKIE['screenWidth'] ) : '',
                'colorDepth'   => isset( $_COOKIE['colorDepth'] ) ? sanitize_text_field( $_COOKIE['colorDepth'] ) : '',
                'javaEnabled'  => isset( $_COOKIE['javaEnabled'] ) ? sanitize_text_field( $_COOKIE['javaEnabled'] ) : '',
            ),
            'trial'       => isset( $purchase_data['trial'] ) ? $purchase_data['trial'] : null,
        );

        // Check if this is a recurring subscription purchase.
        $is_subscription = ! empty( $purchase_data['subscription'] );
        if ( $is_subscription ) {
            $subscription_data = array(
                'transactionId'       => $purchase_data['purchase_key'],
                'amount'              => $purchase_data['price'],
                'currency'            => edd_currency(),
                'subscriptionDetails' => $purchase_data['subscription'],
            );
            $subscription_response = $api->create_subscription( $subscription_data );
            if ( ! $subscription_response['success'] ) {
                edd_record_gateway_error( 'Payoneer Subscription Error', print_r( $subscription_response, true ) );
                edd_send_back_to_checkout( '?payment-mode=payoneer' );
                exit;
            }
            // Optionally, store subscription meta data.
        }

        if ( 'hosted' === $integration_mode ) {
            // For hosted mode, redirect to the Payoneer-hosted payment page.
            if ( isset( $list_data['links']['redirect'] ) ) {
                wp_redirect( esc_url( $list_data['links']['redirect'] ) );
                exit;
            }
        } else {
            // For native mode, process the CHARGE request directly.
            $charge_response = $api->process_charge( $list_id, $charge_data );
            if ( ! $charge_response['success'] ) {
                edd_record_gateway_error( 'Payoneer Charge Error', print_r( $charge_response, true ) );
                edd_send_back_to_checkout( '?payment-mode=payoneer' );
                exit;
            }

            // If 3DS authentication is required, redirect to the provided 3DS URL.
            if ( isset( $charge_response['redirect_3ds'] ) && ! empty( $charge_response['redirect_3ds'] ) ) {
                wp_redirect( esc_url( $charge_response['redirect_3ds'] ) );
                exit;
            }

            // Insert the payment into EDD as complete.
            $payment_args = array(
                'price'         => $purchase_data['price'],
                'purchase_key'  => $purchase_data['purchase_key'],
                'user_email'    => $purchase_data['user_email'],
                'currency'      => edd_currency(),
                'downloads'     => $purchase_data['downloads'],
                'cart_details'  => $purchase_data['cart_details'],
                'user_info'     => $purchase_data['user_info'],
                'status'        => 'complete'
            );
            $payment_id = edd_insert_payment( $payment_args );
            edd_update_payment_status( $payment_id, 'complete' );

            // If EDD Software Licensing is active, handle license key generation.
            if ( function_exists( 'edd_software_licensing' ) ) {
                $this->handle_license_keys( $payment_id, $purchase_data );
            }
            edd_send_to_success_page();
            exit;
        }
        exit;
    }

    /**
     * Generate license keys for licensed products if EDD Software Licensing is active.
     *
     * @param int   $payment_id   The EDD payment ID.
     * @param array $purchase_data The purchase data.
     */
    public function handle_license_keys( $payment_id, $purchase_data ) {
        if ( ! empty( $purchase_data['downloads'] ) ) {
            foreach ( $purchase_data['downloads'] as $download ) {
                if ( isset( $download['download_id'] ) && edd_software_licensing()->is_licensed_item( $download['download_id'] ) ) {
                    $license_data = array(
                        'payment_id'  => $payment_id,
                        'download_id' => $download['download_id'],
                        'user_email'  => $purchase_data['user_email'],
                    );
                    edd_software_licensing()->generate_license( $license_data );
                }
            }
        }
    }

    /**
     * Handle return parameters from Payoneer (e.g., after 3DS authentication or hosted payment)
     */
    public function handle_return() {
        if ( isset( $_GET['payoneer_return'] ) ) {
            edd_send_to_success_page();
            exit;
        }
    }
}
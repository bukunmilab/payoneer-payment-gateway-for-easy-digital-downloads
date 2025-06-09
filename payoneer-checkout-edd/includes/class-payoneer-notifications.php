<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Payoneer_Notifications {

    public function __construct() {
        // Initialization code if needed.
    }

    /**
     * Process the notification sent by Payoneer.
     */
    public function process_notification() {
        // Get the raw POST payload (assuming a JSON payload).
        $raw_post = file_get_contents( 'php://input' );
        $data     = json_decode( $raw_post, true );

        // Log the notification data for debugging.
        error_log( '[Payoneer Notification] Data: ' . print_r( $data, true ) );

        // Example processing: map notification data to order status changes.
        if ( isset( $data['order_id'] ) && isset( $data['status'] ) ) {
            $order_id = sanitize_text_field( $data['order_id'] );
            $status   = sanitize_text_field( $data['status'] );

            // Map Payoneer statuses to EDD payment statuses.
            if ( function_exists( 'edd_update_payment_status' ) ) {
                switch ( $status ) {
                    case 'APPROVED':
                        edd_update_payment_status( $order_id, 'complete' );
                        break;
                    case 'DECLINED':
                        edd_update_payment_status( $order_id, 'failed' );
                        break;
                    case 'PENDING':
                        edd_update_payment_status( $order_id, 'pending' );
                        break;
                    case 'CHARGEBACK':
                        edd_update_payment_status( $order_id, 'failed' );
                        // Additional chargeback handling can be added here.
                        break;
                    default:
                        // Handle other cases or subscription updates.
                        break;
                }
            }
        }

        // Return a success response.
        header( 'Content-Type: application/json' );
        echo json_encode( array( 'success' => true ) );
    }
}
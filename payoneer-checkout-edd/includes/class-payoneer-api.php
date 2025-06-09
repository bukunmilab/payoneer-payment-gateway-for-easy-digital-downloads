<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Payoneer_API {

    private $api_url;
    private $api_token;
    private $test_mode;

    public function __construct( $args = array() ) {
        $defaults = array(
            'api_url'   => 'https://api.payoneer.com', // Production endpoint.
            'api_token' => '',
            'test_mode' => true,
        );
        $args           = wp_parse_args( $args, $defaults );
        $this->api_url  = $args['api_url'];
        $this->api_token = $args['api_token'];
        $this->test_mode = $args['test_mode'];

        // If test mode is enabled, use the sandbox endpoint.
        if ( $this->test_mode ) {
            $this->api_url = 'https://sandbox.payoneer.com';
        }
    }

    /**
     * Create a new payment session (LIST request).
     *
     * @param array $session_data Data to send in the LIST request.
     * @return array Response array.
     */
    public function create_list_session( $session_data ) {
        $endpoint = $this->api_url . '/lists';
        $args = array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_token,
            ),
            'body'    => json_encode( $session_data ),
            'timeout' => 20,
        );
        $response = wp_remote_post( $endpoint, $args );
        return $this->handle_response( $response );
    }

    /**
     * Process a CHARGE request.
     *
     * @param string $list_id     The ID of the created LIST session.
     * @param array  $charge_data Data for the CHARGE request.
     * @return array Response array.
     */
    public function process_charge( $list_id, $charge_data ) {
        $endpoint = $this->api_url . '/lists/' . $list_id . '/charge';
        $args = array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_token,
            ),
            'body'    => json_encode( $charge_data ),
            'timeout' => 20,
        );
        $response = wp_remote_post( $endpoint, $args );
        $result = $this->handle_response( $response );
        // If a 3DS redirect URL is returned, add it to the response.
        if ( $result['success'] && isset( $result['data']['links']['3ds'] ) ) {
            $result['redirect_3ds'] = $result['data']['links']['3ds'];
        }
        return $result;
    }

    /**
     * Process a refund (full or partial).
     *
     * @param string $charge_id   Charge identifier.
     * @param array  $refund_data Data for the refund (e.g., array('amount' => <refund_amount>, 'reason' => 'Reason')).
     * @return array Response array.
     */
    public function process_refund( $charge_id, $refund_data ) {
        $endpoint = $this->api_url . '/charges/' . $charge_id . '/payout';
        $args = array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_token,
            ),
            'body'    => json_encode( $refund_data ),
            'timeout' => 20,
        );
        $response = wp_remote_post( $endpoint, $args );
        return $this->handle_response( $response );
    }

    /**
     * Create a recurring subscription on Payoneer.
     *
     * @param array $subscription_data Data for the subscription.
     * @return array Response array.
     */
    public function create_subscription( $subscription_data ) {
        $endpoint = $this->api_url . '/subscriptions';
        $args = array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_token,
            ),
            'body'    => json_encode( $subscription_data ),
            'timeout' => 20,
        );
        $response = wp_remote_post( $endpoint, $args );
        return $this->handle_response( $response );
    }

    /**
     * Dummy method to simulate chargeback processing.
     *
     * @param string $charge_id Charge identifier.
     * @param array  $data      Additional data.
     * @return array Response array.
     */
    public function process_chargeback( $charge_id, $data = array() ) {
        $endpoint = $this->api_url . '/charges/' . $charge_id . '/chargeback';
        $args = array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_token,
            ),
            'body'    => json_encode( $data ),
            'timeout' => 20,
        );
        $response = wp_remote_post( $endpoint, $args );
        return $this->handle_response( $response );
    }

    /**
     * Handle and decode API responses.
     *
     * @param mixed $response WP HTTP response.
     * @return array Parsed response.
     */
    private function handle_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'error'   => $response->get_error_message()
            );
        }
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $decoded = json_decode( $body, true );
        if ( $code != 200 ) {
            return array(
                'success' => false,
                'error'   => isset( $decoded['message'] ) ? $decoded['message'] : 'Unknown error'
            );
        }
        return array(
            'success' => true,
            'data'    => $decoded
        );
    }
}
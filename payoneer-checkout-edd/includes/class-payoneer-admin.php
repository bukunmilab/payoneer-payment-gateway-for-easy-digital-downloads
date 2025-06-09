<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Payoneer_Admin {

    public function __construct() {
        // Add settings fields to the EDD gateways settings.
        add_filter( 'edd_settings_gateways', array( $this, 'register_payoneer_settings' ) );
        add_filter( 'edd_payment_gateways', array( $this, 'register_payoneer_gateway' ) );
    }

    /**
     * Register settings for Payoneer.
     *
     * @param array $settings Current EDD settings.
     * @return array Modified settings with Payoneer options.
     */
    public function register_payoneer_settings( $settings ) {
        $default_notification_url = site_url() . '/?pce_notification=1';

        $payoneer_settings = array(
            array(
                'id'   => 'payoneer_section',
                'name' => '<strong>' . __( 'Payoneer Checkout Settings', 'payoneer-checkout-edd' ) . '</strong>',
                'desc' => __( 'Configure Payoneer Checkout integration options.', 'payoneer-checkout-edd' ),
                'type' => 'header'
            ),
            array(
                'id'   => 'payoneer_api_token',
                'name' => __( 'API Token', 'payoneer-checkout-edd' ),
                'desc' => __( 'Enter your Payoneer API token.', 'payoneer-checkout-edd' ),
                'type' => 'text',
                'size' => 'regular'
            ),
            array(
                'id'      => 'payoneer_integration_mode',
                'name'    => __( 'Integration Mode', 'payoneer-checkout-edd' ),
                'desc'    => __( 'Select the integration mode: Hosted (redirect to Payoneer) or Native (process charge directly).', 'payoneer-checkout-edd' ),
                'type'    => 'radio',
                'options' => array(
                    'hosted' => __( 'Hosted', 'payoneer-checkout-edd' ),
                    'native' => __( 'Native', 'payoneer-checkout-edd' ),
                ),
                'std'     => 'hosted'
            ),
            array(
                'id'   => 'payoneer_test_mode',
                'name' => __( 'Test Mode', 'payoneer-checkout-edd' ),
                'desc' => __( 'Enable test mode to use the Payoneer sandbox.', 'payoneer-checkout-edd' ),
                'type' => 'checkbox'
            ),
            array(
                'id'          => 'payoneer_notification_url',
                'name'        => __( 'Notification URL', 'payoneer-checkout-edd' ),
                'desc'        => __( 'Enter the webhook endpoint URL. Format: https://your-domain.com/?pce_notification=1. Copy and paste this exact URL into your Payoneer dashboard by navigating to the API or Developer settings section – look for an area labeled “Webhooks” or “Notifications', 'payoneer-checkout-edd' ),
                'desc_tip'    => true,
                'type'        => 'text',
                'size'        => 'regular',
                'std'         => $default_notification_url,
            ),
        );

        return array_merge( $settings, $payoneer_settings );
    }

    /**
     * Register the Payoneer Checkout payment gateway.
     *
     * @param array $gateways Current EDD gateways.
     * @return array Modified gateways list including Payoneer.
     */
    public function register_payoneer_gateway( $gateways ) {
        $gateways['payoneer'] = array(
            'admin_label'    => __( 'Payoneer Checkout', 'payoneer-checkout-edd' ),
            'checkout_label' => __( 'Pay via Payoneer', 'payoneer-checkout-edd' )
        );
        return $gateways;
    }
}
?>

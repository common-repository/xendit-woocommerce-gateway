<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handles orders for redirect flow.
 *
 * @since 1.3.0
 */
class WC_Xendit_Redirect_Handler extends WC_Gateway_Xendit
{
    private static $_this;

    /**
     * Constructor.
     *
     * @since 1.3.0
     * @version 1.3.0
     */
    public function __construct()
    {
        self::$_this = $this;

        add_action('wp', array( $this, 'maybe_process_redirect_order' ));
    }

    /**
     * Processses the orders that are redirected.
     *
     * @since 1.3.0
     * @version 1.3.0
     */
    public function maybe_process_redirect_order()
    {
        if (! is_order_received_page() || empty($_GET['hosted_3ds_id'])) {
            return;
        }

        $order_id = wc_clean($_GET['order_id']);

        $this->process_redirect_payment($order_id);
    }

    /**
     * Processes payments.
     * Note at this time the original source has already been
     * saved to a customer card (if applicable) from process_payment.
     *
     * @since 1.3.0
     * @param int $order_id
     * @param bool $retry
     * @param mix $previous_error Any error message from previous request.
     */
    public function process_redirect_payment($order_id)
    {
        try {
            $hosted_3ds_id  = wc_clean($_GET['hosted_3ds_id']);

            if (empty($hosted_3ds_id)) {
                WC_Xendit_Logger::log('Error: Empty Hosted 3DS ID');
                return;
            }

            if (empty($order_id)) {
                return;
            }
            
            $order = wc_get_order($order_id);

            if (! is_object($order)) {
                return;
            }

            if ('processing' === $order->get_status() || 'completed' === $order->get_status() || 'on-hold' === $order->get_status()) {
                return;
            }

            $hosted_3ds	        = WC_Xendit_API::request(
                array(
                    'id'	=> $hosted_3ds_id
                ),
                'hosted-3ds/' . $hosted_3ds_id,
                'GET',
                array(
                    'should_use_public_key'	=> true
                )
            );
            $token_id 			= $hosted_3ds->token_id;
            $auth_id 			= $hosted_3ds->authentication_id;
            $hosted_3ds_status 	= $hosted_3ds->status;

            if ('VERIFIED' !== $hosted_3ds_status) {
                throw new Exception(__('Authentication process failed. Please try again.', 'woocommerce-gateway-xendit'));
            }

            $response = WC_Xendit_API::request($this->generate_payment_request($order, $token_id, $auth_id));

            if ($response->error_code === 'EXTERNAL_ID_ALREADY_USED_ERROR') {
                $response = WC_Xendit_API::request($this->generate_payment_request($order, $token_id, $auth_id, true));
            }

            $this->process_response($response, $order);
        } catch (Exception $e) {
            WC_Xendit_Logger::log('Error: During process redirect payment. ' . $e->getMessage(), LogDNA_Level::ERROR, true);

            /* translators: error message */
            $order->update_status('failed', sprintf(__('Payment failed: %s', 'woocommerce-gateway-xendit'), $e->getMessage()));

            wc_add_notice($e->getMessage(), 'error');
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }
    }
}

new WC_Xendit_Redirect_Handler();

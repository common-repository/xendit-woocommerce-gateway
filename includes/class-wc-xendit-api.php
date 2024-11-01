<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Xendit_API class.
 *
 * Communicates with Xendit API.
 */
class WC_Xendit_API
{

    /**
     * Xendit API Endpoint
     */
    const ENDPOINT = 'https://tpi.xendit.co/payment/xendit/credit-card/';
    const PLUGIN_NAME = 'WOOCOMMERCE';
    const DEFAULT_STORE_NAME = 'XENDIT_WOOCOMMERCE_STORE';

    /**
     * Secret API Key.
     * @var string
     */
    private static $secret_key = '';

    /**
     * Set secret API Key.
     * @param string $key
     */
    public static function set_secret_key($secret_key)
    {
        self::$secret_key = $secret_key;
    }

    /**
     * Get secret key.
     * @return string
     */
    public static function get_secret_key()
    {
        if (!self::$secret_key) {
            $options = get_option('woocommerce_xendit_settings');

            if (isset($options['testmode'], $options['secret_key'], $options['test_secret_key'])) {
                self::set_secret_key('yes' === $options['testmode'] ? $options['test_secret_key'] : $options['secret_key']);
            }
        }
        return self::$secret_key;
    }

    /**
     * Public API Key.
     * @var string
     */
    private static $public_key = '';

    /**
     * Set public API Key.
     * @param string $key
     */
    public static function set_public_key($public_key)
    {
        self::$public_key = $public_key;
    }

    /**
     * Get public key.
     * @return string
     */
    public static function get_public_key()
    {
        if (!self::$public_key) {
            $options = get_option('woocommerce_xendit_settings');

            if (isset($options['testmode'], $options['publishable_key'], $options['test_publishable_key'])) {
                self::set_public_key('yes' === $options['testmode'] ? $options['test_publishable_key'] : $options['publishable_key']);
            }
        }
        return self::$public_key;
    }

    /**
     * Generates header for API request
     *
     * @since 1.2.3
     * @version 1.2.3
     */
    public static function get_headers($options)
    {
        WC_Xendit_Logger::log("INFO: Building Request Header..");

        $should_use_public_key = isset($options['should_use_public_key']) ? $options['should_use_public_key'] : false;
        $auth = $should_use_public_key ? self::get_public_key() : self::get_secret_key();

        return apply_filters(
            'woocommerce_xendit_request_headers',
            array(
                'Authorization' => 'Basic ' . base64_encode($auth . ':'),
                'x-plugin-name' => self::PLUGIN_NAME,
                'x-plugin-store-name' => isset($options['store_name']) ? $options['store_name'] : get_option('blogname'),
            )
        );
    }

    /**
     * Send the request to Xendit's API
     *
     * @param array $request
     * @param string $api
     * @return array|WP_Error
     */
    public static function request($request, $api = 'charges', $method = 'POST', $options = array())
    {
        WC_Xendit_Logger::log("$method /{$api} request: " . print_r($request, true) . PHP_EOL);

        $headers = self::get_headers($options);

        $response = wp_safe_remote_post(
            self::ENDPOINT . $api,
            array(
                'method' => $method,
                'headers' => $headers,
                'body' => apply_filters('woocommerce_xendit_request_body', $request, $api),
                'timeout' => 70,
                'user-agent' => 'WooCommerce ' . WC()->version,
            )
        );

        if (is_wp_error($response) || empty($response['body'])) {
            WC_Xendit_Logger::log('API Error Response: ' . print_r($response, true), LogDNA_Level::ERROR, true);
            return new WP_Error('xendit_error', __('There was a problem connecting to the payment gateway.', 'woocommerce-gateway-xendit'));
        }

        $parsed_response = json_decode($response['body']);

        // Handle response
        if (!empty($parsed_response->error)) {
            if (!empty($parsed_response->error->code)) {
                $code = $parsed_response->error->code;
            } else {
                $code = 'xendit_error';
            }
            WC_Xendit_Logger::log('API Error Parsed Response: ' . $parsed_response, LogDNA_Level::ERROR, true);
            return new WP_Error($code, $parsed_response->error->message);
        } else {
            return $parsed_response;
        }
    }
}

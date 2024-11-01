<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Gateway_Xendit class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Xendit extends WC_Payment_Gateway_CC
{
    const DEFAULT_MINIMUM_AMOUNT = 10000;
    const DEFAULT_MAXIMUM_AMOUNT = 200000000;

    /**
     * Should we capture Credit cards
     *
     * @var bool
     */
    public $capture;

    /**
     * Alternate credit card statement name
     *
     * @var bool
     */
    public $statement_descriptor;

    /**
     * Checkout enabled
     *
     * @var bool
     */
    public $xendit_checkout;

    /**
     * Checkout Locale
     *
     * @var string
     */
    public $xendit_checkout_locale;

    /**
     * Credit card image
     *
     * @var string
     */
    public $xendit_checkout_image;

    /**
     * Should we store the users credit cards?
     *
     * @var bool
     */
    public $saved_cards;

    /**
     * API access secret key
     *
     * @var string
     */
    public $secret_key;

    /**
     * Api access publishable key
     *
     * @var string
     */
    public $publishable_key;


    /**
     * Is test mode active?
     *
     * @var bool
     */
    public $testmode;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->id                   = 'xendit';
        $this->method_title         = __('Xendit', 'woocommerce-gateway-xendit');

        $cache_message              = get_transient('xendit_cards_deprecated_message');
        $this->deprecated_message   = ($cache_message ? $cache_message : $this->get_message('woocommerce_cc_deprecation_warning'));

        $this->method_description   = sprintf(__('Collect payment from Credit Cards on checkout page and get the report realtime on your Xendit Dashboard. <a href="%1$s" target="_blank">Sign In</a> or <a href="%2$s" target="_blank">sign up</a> on Xendit and integrate with <a href="%3$s" target="_blank">your Xendit keys</a>. %4$s', 'woocommerce-gateway-xendit'), 'https://dashboard.xendit.co/auth/login', 'https://dashboard.xendit.co/auth/register', 'https://dashboard.xendit.co/settings/developers#api-keys', $this->deprecated_message);
        $this->has_fields           = true;
        $this->view_transaction_url = 'https://dashboard.xendit.co/dashboard/credit_cards';
        $this->supports             = array(
            'subscriptions',
            'products',
            'refunds',
            'subscription_cancellation',
            'subscription_reactivation',
            'subscription_suspension',
            'subscription_amount_changes',
            'subscription_payment_method_change', // Subs 1.n compatibility.
            'subscription_payment_method_change_admin',
            'subscription_date_changes',
            'multiple_subscriptions',
        );
        $this->supported_currencies = array(
            'IDR'
        );

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Get setting values.
        $this->default_title           = 'Credit Card (Xendit)';
        $this->title                   = !empty($this->get_option('channel_name')) ? $this->get_option('channel_name') : $this->default_title;
        $this->default_description     = 'Pay with your credit card via xendit.';
        $this->description             = !empty($this->get_option('payment_description')) ? nl2br($this->get_option('payment_description')) : $this->default_description;
        $this->enabled                 = $this->get_option('enabled');
        $this->testmode                = 'yes' === $this->get_option('testmode');
        $this->capture                 = true;
        $this->statement_descriptor    = $this->get_option('statement_descriptor');
        $this->xendit_checkout         = 'yes' === $this->get_option('xendit_checkout');
        $this->xendit_checkout_locale  = $this->get_option('xendit_checkout_locale');
        $this->xendit_checkout_image   = '';
        $this->saved_cards             = 'yes' === $this->get_option('saved_cards');

        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $main_settings = get_option('woocommerce_xendit_gateway_settings');

        if (is_plugin_active(plugin_basename(WC_XENDIT_PG_MAIN_FILE))) {
            $this->secret_key              = $this->testmode ? $main_settings['secret_key_dev'] : $main_settings['secret_key'];
            $this->publishable_key         = $this->testmode ? $main_settings['api_key_dev'] : $main_settings['api_key'];
        } else {
            $this->secret_key              = $this->testmode ? $this->get_option('test_secret_key') : $this->get_option('secret_key');
            $this->publishable_key         = $this->testmode ? $this->get_option('test_publishable_key') : $this->get_option('publishable_key');
        }

        if ($this->xendit_checkout) {
            $this->order_button_text = __('Continue to payment', 'woocommerce-gateway-xendit');
        }

        if ($this->testmode) {
            $this->description .= '<br/>' . sprintf(__('TEST MODE. Try card "4000000000000002" with any CVN and future expiration date, or see <a href="%s">Xendit Docs</a> for more test cases.', 'woocommerce-gateway-xendit'), 'https://dashboard.xendit.co/docs/');
            $this->description  = trim($this->description);
        }

        WC_Xendit_API::set_secret_key($this->secret_key);
        WC_Xendit_API::set_public_key($this->publishable_key);

        // Hooks.
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_filter('woocommerce_available_payment_gateways', array(&$this, 'xendit_status_payment_gateways'));

        wp_register_script('sweetalert', 'https://unpkg.com/sweetalert@2.1.2/dist/sweetalert.min.js', null, null, true);
        wp_enqueue_script('sweetalert');
    }

    /**
     * Get_icon function. This is called by WC_Payment_Gateway_CC when displaying payment option
     * on checkout page.
     *
     * @access public
     * @return string
     */
    public function get_icon()
    {
        $ext   = version_compare(WC()->version, '2.6', '>=') ? '.svg' : '.png';
        $style = version_compare(WC()->version, '2.6', '>=') ? 'style="margin-left: 0.3em; max-width: 32px;"' : '';

        $icon  = '<img src="' . WC_HTTPS::force_https_url(WC()->plugin_url() . '/assets/images/icons/credit-cards/visa' . $ext) . '" alt="Visa" ' . $style . ' />';
        $icon .= '<img src="' . WC_HTTPS::force_https_url(WC()->plugin_url() . '/assets/images/icons/credit-cards/mastercard' . $ext) . '" alt="Mastercard" ' . $style . ' />';

        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    /**
     * Check if this gateway is enabled
     * Return false if secret key and public key config is empty
     */
    public function is_available()
    {
        if ('yes' === $this->enabled) {
            if (!$this->testmode && is_checkout()) {
                return true;
            }
            if (!$this->secret_key || !$this->publishable_key) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Render admin settings HTML
     * 
     * Host some PHP reliant JS to make the form dynamic
     */
    public function admin_options()
    {
?>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table>

        <script>
            jQuery(document).ready(function($) {
                var isSubmitCheckDone = false;

                $("button[name='save']").on('click', function(e) {
                    if (isSubmitCheckDone) {
                        isSubmitCheckDone = false;
                        return;
                    }

                    e.preventDefault();

                    var newValue = {
                        api_key: $(
                            "#woocommerce_<?= $this->id; ?>_publishable_key"
                        ).val(),
                        secret_key: $(
                            "#woocommerce_<?= $this->id; ?>_secret_key"
                        ).val(),
                        api_key_dev: $(
                            "#woocommerce_<?= $this->id; ?>_test_publishable_key"
                        ).val(),
                        secret_key_dev: $(
                            "#woocommerce_<?= $this->id; ?>_test_secret_key"
                        ).val()
                    };
                    var oldValue = {
                        api_key: '<?= $this->get_option('publishable_key'); ?>',
                        secret_key: '<?= $this->get_option('secret_key'); ?>',
                        api_key_dev: '<?= $this->get_option('test_publishable_key'); ?>',
                        secret_key_dev: '<?= $this->get_option('test_secret_key'); ?>'
                    };

                    if (!_.isEqual(newValue, oldValue)) {
                        return swal({
                            title: 'Are you sure?',
                            text: 'Changing your API key will affect your transaction.',
                            buttons: {
                                confirm: {
                                    text: 'Change my API key',
                                    value: true
                                },
                                cancel: 'Cancel'
                            }
                        }).then(function(value) {
                            if (value) {
                                isSubmitCheckDone = true;
                                $("button[name='save']").trigger('click');
                            } else {
                                e.preventDefault();
                            }
                        });
                    }

                    var paymentDescription = $(
                        "#woocommerce_<?= $this->id; ?>_payment_description"
                    ).val();

                    if (paymentDescription.length > 250) {
                        return swal({
                            text: 'Text is too long, please reduce the message and ensure that the length of the character is less than 250.',
                            buttons: {
                                cancel: 'Cancel'
                            }
                        }).then(function(value) {
                            e.preventDefault();
                        });
                    }

                    isSubmitCheckDone = true;
                    $("button[name='save']").trigger('click');
                });


                $("#channel-name-format").text(
                    "<?= $this->title ?>");
                $("#channel-name-format-description").text(
                    "<?= $this->title ?>");

                $("#woocommerce_<?= $this->id; ?>_channel_name").change(
                    function() {
                        $("#channel-name-format").text($(this).val());
                        $("#channel-name-format-description").text($(this).val());
                    });
            });
        </script>
<?php
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = include('settings-xendit.php');
    }

    /**
     * Payment form on checkout page. This is called by WC_Payment_Gateway_CC when displaying
     * payment form on checkout page.
     */
    public function payment_fields()
    {
        $user                 = wp_get_current_user();
        $display_tokenization = $this->supports('tokenization') && is_checkout();
        $total                = WC()->cart->total;

        if ($user->ID) {
            $user_email = get_user_meta($user->ID, 'billing_email', true);
            $user_email = $user_email ? $user_email : $user->user_email;
        } else {
            $user_email = '';
        }

        echo '<div
			id="xendit-payment-data"
			data-description=""
			data-email="' . esc_attr($user_email) . '"
			data-amount="' . esc_attr($total) . '"
			data-name="' . esc_attr($this->statement_descriptor) . '"
			data-currency="' . esc_attr(strtolower(get_woocommerce_currency())) . '"
			data-locale="' . esc_attr('en') . '"
			data-image="' . esc_attr($this->xendit_checkout_image) . '"
			data-allow-remember-me="' . esc_attr($this->saved_cards ? 'true' : 'false') . '">';

        if ($this->description) {
            echo apply_filters('wc_xendit_description', wpautop(wp_kses_post($this->description)));
        }

        if ($display_tokenization) {
            /**
             * This loads WC_Payment_Gateway tokenization script, which enqueues script to update
             * payment form.
             */
            $this->tokenization_script();
        }

        // Load the fields. Source: https://woocommerce.wp-a2z.org/oik_api/wc_payment_gateway_ccform/
        $this->form();
        echo '</div>';
    }

    /**
     * Localize Xendit messages based on code
     *
     * @since 3.0.6
     * @version 3.0.6
     * @return array
     */
    public function get_localized_messages()
    {
        return apply_filters('wc_xendit_localized_messages', array(
            'invalid_number'        => __('The card number is not a valid credit card number.', 'xendit-woocommerce-gateway'),
            'invalid_expiry_month'  => __('The card\'s expiration month is invalid.', 'xendit-woocommerce-gateway'),
            'invalid_expiry_year'   => __('The card\'s expiration year is invalid.', 'xendit-woocommerce-gateway'),
            'invalid_cvc'           => __('The card\'s security code is invalid.', 'xendit-woocommerce-gateway'),
            'incorrect_number'      => __('The card number is incorrect.', 'xendit-woocommerce-gateway'),
            'expired_card'          => __('The card has expired.', 'xendit-woocommerce-gateway'),
            'incorrect_cvc'         => __('The card\'s security code is incorrect.', 'xendit-woocommerce-gateway'),
            'incorrect_zip'         => __('The card\'s zip code failed validation.', 'xendit-woocommerce-gateway'),
            'card_declined'         => __('The card was declined.', 'xendit-woocommerce-gateway'),
            'missing'               => __('There is no card on a subscription that is being charged.', 'xendit-woocommerce-gateway'),
            'processing_error'      => __('An error occurred while processing the card.', 'xendit-woocommerce-gateway'),
            'invalid_request_error' => __('Could not find payment information.', 'xendit-woocommerce-gateway'),
        ));
    }

    /**
     * Load admin scripts.
     *
     * @since 3.1.0
     * @version 3.1.0
     */
    public function admin_scripts()
    {
        if ('woocommerce_page_wc-settings' !== get_current_screen()->id) {
            return;
        }

        wp_enqueue_script('woocommerce_xendit_admin', plugins_url('assets/js/xendit-admin.js', WC_XENDIT_MAIN_FILE), array(), WC_XENDIT_VERSION, true);

        $xendit_admin_params = array(
            'localized_messages' => array(
                'not_valid_live_key_msg' => __('This is not a valid live key. Live keys start with "x".', 'woocommerce-gateway-xendit'),
                'not_valid_test_key_msg' => __('This is not a valid test key. Test keys start with "x".', 'woocommerce-gateway-xendit'),
                're_verify_button_text'  => __('Re-verify Domain', 'woocommerce-gateway-xendit'),
                'missing_secret_key'     => __('Missing Secret Key. Please set the secret key field above and re-try.', 'woocommerce-gateway-xendit'),
            ),
            'ajaxurl'            => admin_url('admin-ajax.php')
        );

        wp_localize_script('woocommerce_xendit_admin', 'wc_xendit_admin_params', apply_filters('wc_xendit_admin_params', $xendit_admin_params));
    }


    /**
     * payment_scripts function.
     *
     * Outputs scripts used for xendit payment
     *
     * @access public
     */
    public function payment_scripts()
    {
        WC_Xendit_Logger::log("WC_Gateway_Xendit::payment_scripts called");

        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }

        echo '<script>var total = 5000</script>';

        wp_enqueue_script('xendit', 'https://js.xendit.co/v1/xendit.min.js', '', WC_XENDIT_VERSION, true);
        wp_enqueue_script('woocommerce_xendit', plugins_url('assets/js/xendit.js', WC_XENDIT_MAIN_FILE), array('jquery', 'xendit'), WC_XENDIT_VERSION, true);

        $xendit_params = array(
            'key'                  => $this->publishable_key
        );

        // If we're on the pay page we need to pass xendit.js the address of the order.
        // TODO: implement direct payments from the order
        if (isset($_GET['pay_for_order']) && 'true' === $_GET['pay_for_order']) {
            $order_id = wc_get_order_id_by_order_key(urldecode($_GET['key']));
            $order    = wc_get_order($order_id);

            $xendit_params['billing_first_name'] = $order->get_billing_first_name();
            $xendit_params['billing_last_name']  = $order->get_billing_last_name();
            $xendit_params['billing_address_1']  = $order->get_billing_address_1();
            $xendit_params['billing_address_2']  = $order->get_billing_address_2();
            $xendit_params['billing_state']      = $order->get_billing_state();
            $xendit_params['billing_city']       = $order->get_billing_city();
            $xendit_params['billing_postcode']   = $order->get_billing_postcode();
            $xendit_params['billing_country']    = $order->get_billing_country();
            $xendit_params['amount']              = $order->get_total() * 100;
        }

        // merge localized messages to be use in JS
        $xendit_params = array_merge($xendit_params, $this->get_localized_messages());

        wp_localize_script('woocommerce_xendit', 'wc_xendit_params', apply_filters('wc_xendit_params', $xendit_params));
    }

    /**
     * Generate the request for the payment.
     * @param  WC_Order $order
     * @param  object $source
     * @return array()
     */
    protected function generate_payment_request($order, $xendit_token, $auth_id = null, $duplicated = false)
    {
        $amount = $order->get_total();
        $token_id = $_POST['xendit_token'] ? wc_clean($_POST['xendit_token']) : $xendit_token; // do isset first

        //TODO: Find out how can we pass CVN on redirected flow
        $cvn = $_POST['card_cvn'] ? wc_clean($_POST['card_cvn']) : null; // do isset first

        $default_external_id = "woocommerce_xendit_" . $order->get_id();
        $external_id = $duplicated ? $default_external_id . "_" . uniqid() : $default_external_id;

        $post_data                                = array();
        $post_data['amount']                      = $amount;
        $post_data['token_id']                    = $token_id;
        $post_data['authentication_id']            = $auth_id;
        $post_data['card_cvn']                    = $cvn;
        $post_data['external_id']                 = $external_id;
        $post_data['store_name']                = get_option('blogname');

        return $post_data;
    }

    /**
     * Get payment source. This can be a new token or existing token.
     *
     * @throws Exception When card was not added or for and invalid card.
     * @return object
     */
    protected function get_source()
    {
        $xendit_source   = false;
        $token_id        = false;

        // New CC info was entered and we have a new token to process
        if (isset($_POST['xendit_token'])) {
            WC_Xendit_Logger::log('xendit_token available ' . print_r($_POST['xendit_token'], true));

            $xendit_token     = wc_clean($_POST['xendit_token']);
            // Not saving token, so don't define customer either.
            $xendit_source   = $xendit_token;
        } elseif (isset($_POST['wc-xendit-payment-token']) && 'new' !== $_POST['wc-xendit-payment-token']) {
            // Use an EXISTING multiple use token, and then process the payment
            WC_Xendit_Logger::log('wc-xendit-payment-token available');
            $token_id = wc_clean($_POST['wc-xendit-payment-token']);
            $token    = WC_Payment_Tokens::get($token_id);

            // associates payment token with WP user_id
            if (!$token || $token->get_user_id() !== get_current_user_id()) {
                WC()->session->set('refresh_totals', true);
                throw new Exception(__('Invalid payment method. Please input a new card number.', 'woocommerce-gateway-xendit'));
            }

            $xendit_source = $token->get_token();
        }

        return (object) array(
            'token_id' => $token_id,
            'source'   => $xendit_source,
        );
    }

    /**
     * Get payment source from an order. This could be used in the future for
     * a subscription as an example, therefore using the current user ID would
     * not work - the customer won't be logged in :)
     *
     * Not using 2.6 tokens for this part since we need a customer AND a card
     * token, and not just one.
     *
     * @param object $order
     * @return object
     */
    protected function get_order_source($order = null)
    {
        WC_Xendit_Logger::log('WC_Gateway_Xendit::get_order_source');

        $xendit_source   = false;
        $token_id        = false;

        if ($order) {
            $order_id = version_compare(WC_VERSION, '3.0.0', '<') ? $order->id : $order->get_id();

            if ($meta_value = get_post_meta($order_id, '_xendit_card_id', true)) {
                $xendit_source = $meta_value;
            }
        }

        return (object) array(
            'token_id' => $token_id,
            'source'   => $xendit_source,
        );
    }

    /**
     * Process the payment.
     *
     * NOTE 2019/03/22: The key to have 3DS after order creation is calling it after this is called.
     * Currently still can't do it somehow. Need to dig deeper on this!
     *
     * @param int  $order_id Reference.
     * @param bool $retry Should we retry on fail.
     *
     * @throws Exception If payment will not be accepted.
     *
     * @return array|void
     */
    public function process_payment($order_id, $retry = true)
    {
        WC_Xendit_Logger::log('WC_Gateway_Xendit::process_payment order_id ==> ' . print_r($order_id, true));

        try {
            $order  = wc_get_order($order_id);

            if ($order->get_total() < WC_Gateway_Xendit::DEFAULT_MINIMUM_AMOUNT) {
                $this->cancel_order($order, 'Cancelled because amount is below minimum amount');

                throw new Exception(sprintf(__(
                    'The minimum amount for using this payment is %1$s. Please put more item to reach the minimum amount. <br />' .
                        '<a href="%2$s">Your Cart</a>',
                    'xendit-woocommerce-gateway'
                ), wc_price(WC_Gateway_Xendit::DEFAULT_MINIMUM_AMOUNT), wc_get_cart_url()));
            }

            if ($order->get_total() > WC_Gateway_Xendit::DEFAULT_MAXIMUM_AMOUNT) {
                $this->cancel_order($order, 'Cancelled because amount is above maximum amount');

                throw new Exception(sprintf(__(
                    'The maximum amount for using this payment is %1$s. Please remove one or more item(s) from your cart. <br />' .
                        '<a href="%2$s">Your Cart</a>',
                    'xendit-woocommerce-gateway'
                ), wc_price(WC_Gateway_Xendit::DEFAULT_MAXIMUM_AMOUNT), wc_get_cart_url()));
            }
            //token
            $source = $this->get_source();

            // so it enters the if block and throws an error.
            if (empty($source->source)) {
                WC_Xendit_Logger::log('ERROR: Empty token for order ID' . $order_id, LogDNA_Level::ERROR, true);
                $error_msg = __('Please enter your card details to make a payment.', 'xendit-woocommerce-gateway');
                $error_msg .= ' ' . __('Developers: Please make sure that you are including jQuery and there are no JavaScript errors on the page.', 'xendit-woocommerce-gateway');
                throw new Exception($error_msg);
            }

            // Store source to order meta.
            $this->save_source($order, $source);

            // Result from Xendit API request.
            $response = null;

            // Handle payment.
            if ($order->get_total() > 0) {
                WC_Xendit_Logger::log("Info: Begin processing payment for order $order_id for the amount of {$order->get_total()}");
                // Make the request.

                if (isset($_POST['wc-xendit-payment-token']) && 'new' !== $_POST['wc-xendit-payment-token']) {
                    $token_id = wc_clean($_POST['wc-xendit-payment-token']);
                    $token    = WC_Payment_Tokens::get($source->source);

                    $xendit_token = $token->get_token();
                }

                if (isset($_POST['xendit_token'])) {
                    $xendit_token = $_POST['xendit_token'];
                }

                $response = WC_Xendit_API::request($this->generate_payment_request($order, $xendit_token));

                if ($response->error_code === 'EXTERNAL_ID_ALREADY_USED_ERROR') {
                    $response = WC_Xendit_API::request($this->generate_payment_request($order, $xendit_token, null, true));
                }

                // Redirect URL
                if ($response->error_code === 'AUTHENTICATION_ID_MISSING_ERROR') {
                    $hosted_3ds_response = $this->create_hosted_3ds($order, $xendit_token);

                    if ('IN_REVIEW' === $hosted_3ds_response->status) {
                        WC_Xendit_Logger::log('Info: Redirecting to 3DS... ' . print_r($hosted_3ds_response, true));

                        return array(
                            'result'   => 'success',
                            'redirect' => esc_url_raw($hosted_3ds_response->redirect->url),
                        );
                    } else {
                        $error_msg = 'Bank card issuer is not available or the connection is timed out, please try again with another card in a few minutes';
                        throw new Exception($error_msg);
                    }
                }

                // Process valid response.
                $this->process_response($response, $order);
            } else {
                $order->payment_complete();
            }

            // Remove cart.
            WC()->cart->empty_cart();

            do_action('wc_gateway_xendit_process_payment', $response, $order);

            // Return thank you page redirect.
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order)
            );
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');

            if ($order->has_status(array('pending', 'failed'))) {
                $this->send_failed_order_email($order_id);
            }

            do_action('wc_gateway_xendit_process_payment_error', $e, $order);

            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }
    }

    /**
     * Save source to order.
     *
     * @param WC_Order $order For to which the source applies.
     * @param stdClass $source Source information.
     */
    protected function save_source($order, $source)
    {
        WC_Xendit_Logger::log('WC_Gateway_Xendit::save_source called in Xendit with order ==> ' . print_r($order, true) . 'and source ==> ' . print_r($source, true));

        $order_id = version_compare(WC_VERSION, '3.0.0', '<') ? $order->id : $order->get_id();

        // Store source in the order.
        if ($source->source) {
            version_compare(WC_VERSION, '3.0.0', '<') ? update_post_meta($order_id, '_xendit_card_id', $source->source) : $order->update_meta_data('_xendit_card_id', $source->source);
        }

        if (is_callable(array($order, 'save'))) {
            $order->save();
        }
    }

    /**
     * Store extra meta data for an order from a Xendit Response.
     */
    public function process_response($response, $order)
    {

        /** NOTE: 2019/04/05. Before commenting this part, I've assessed that this part is unused
         * and therefore should be safe to be removed for now. Subscription feature use a field
         * inside order object instead of payment token.
         *
         * @deprecated 1.2.4
         */
        // if ( get_current_user_id() && class_exists( 'WC_Payment_Token_CC' ) ) {
        // 	$token = new WC_Payment_Token_CC();
        // 	$token->set_token( $response->id );
        // 	$token->set_last4( substr($response->masked_card_number, -4) );
        // 	$token->set_expiry_year( wc_clean($_POST['year']) );
        // 	$token->set_expiry_month( wc_clean($_POST['month']) );
        // 	$token->set_gateway_id( 'xendit' );
        // 	$token->set_card_type( $response->card_brand ); //visa, mastercard, etc.
        // 	$token->set_user_id( get_current_user_id() );
        // 	WC_Xendit_Logger::log( 'validation' . $token->validate() );
        // 	$token->save();
        // 	WC_Xendit_Logger::log( 'saving wc payment token cc -> ' . $token );
        // }

        if (is_wp_error($response)) {
            if ('source' === $response->get_error_code() && $source->token_id) {
                $token = WC_Payment_Tokens::get($source->token_id);
                $token->delete();
                $message = __('This card is no longer available and has been removed.', 'xendit-woocommerce-gateway');
                $order->add_order_note($message);

                WC_Xendit_Logger::log('ERROR: Card removed error. ' . $message, LogDNA_Level::ERROR, true);
                throw new Exception($message);
            }

            $localized_messages = $this->get_localized_messages();

            $message = isset($localized_messages[$response->get_error_code()]) ? $localized_messages[$response->get_error_code()] : $response->get_error_message();

            $order->add_order_note($message);

            WC_Xendit_Logger::log('ERROR: Response error. ' . $message, LogDNA_Level::ERROR, true);
            throw new Exception($message);
        }

        $error_code = isset($response->error_code) ? $response->error_code : null;

        if ($error_code !== null) {
            $message = 'Card charge error. Reason: ' . $this->failure_reason_insight($error_code);

            WC_Xendit_Logger::log('ERROR: Error charge. Message: ' . $message, LogDNA_Level::ERROR, true);
            throw new Exception($message);
        }

        if ($response->status !== 'CAPTURED') {
            $localized_messages = $this->get_localized_messages();

            $order->update_status('failed', sprintf(__('Xendit charges (Charge ID:' . $response->id . ').', 'woocommerce-gateway-xendit'), $response->id));
            $message = $this->failure_reason_insight($response->failure_reason);
            $order->add_order_note($message);

            throw new Exception($message);
        }

        $order_id = version_compare(WC_VERSION, '3.0.0', '<') ? $order->id : $order->get_id();

        // Store charge data
        update_post_meta($order_id, '_xendit_charge_id', $response->id);
        update_post_meta($order_id, '_xendit_charge_captured', $response->status == 'CAPTURED'  ? 'yes' : 'no');

        // Store other data such as fees
        if (isset($response->balance_transaction) && isset($response->balance_transaction->fee)) {
            // Fees and Net needs to both come from Xendit to be accurate as the returned
            // values are in the local currency of the Xendit account, not from WC.
            $fee = !empty($response->balance_transaction->fee) ? WC_Xendit::format_number($response->balance_transaction, 'fee') : 0;
            $net = !empty($response->balance_transaction->net) ? WC_Xendit::format_number($response->balance_transaction, 'net') : 0;
            update_post_meta($order_id, 'Xendit Fee', $fee);
            update_post_meta($order_id, 'Net Revenue From Xendit', $net);
        }

        $order->payment_complete($response->id);
        $message = sprintf(__('Xendit charge complete (Charge ID: %s)', 'woocommerce-gateway-xendit'), $response->id);
        $order->add_order_note($message);

        do_action('wc_gateway_xendit_process_response', $response, $order);

        return $response;
    }

    /**
     * Refund a charge
     * @param  int $order_id
     * @param  float $amount
     * @return bool
     */
    public function process_refund($order_id, $amount = null, $reason = '', $duplicated = false)
    {
        $order = wc_get_order($order_id);

        if (!$order || !$order->get_transaction_id()) {
            return false;
        }

        $default_external_id = 'woocommerce_xendit_' . $order->get_transaction_id();
        $body = array(
            'store_name'    => get_option('blogname'), 'external_id'    => $duplicated ? $default_external_id . '_' . uniqid() : $default_external_id
        );

        if (is_null($amount)) {
            return false;
        }

        if ((float) $amount < 1) {
            return false;
        }

        if (!is_null($amount)) {
            $body['amount']    = $amount;
        }

        if ($reason) {
            $body['metadata'] = array(
                'reason'    => $reason,
            );
        }

        WC_Xendit_Logger::log("Info: Beginning refund for order $order_id for the amount of {$amount}");

        $response = WC_Xendit_API::request($body, 'charges/' . $order->get_transaction_id() . '/refund');

        if (is_wp_error($response)) {
            WC_Xendit_Logger::log('Error: ' . $response->get_error_message(), LogDNA_Level::ERROR, true);
            return false;
        } elseif (!empty($response->id)) {
            $refund_message = sprintf(__('Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'xendit-woocommerce-gateway'), wc_price($response->amount), $response->id, $reason);
            $order->add_order_note($refund_message);
            WC_Xendit_Logger::log('Success: ' . html_entity_decode(strip_tags($refund_message)));
            return true;
        } elseif (!empty($response->error_code)) {
            if ($response->error_code === 'DUPLICATE_REFUND_ERROR') {
                return $this->process_refund($order_id, $amount, $reason, true);
            }

            WC_Xendit_Logger::log('Error: ' . $response->message, LogDNA_Level::ERROR, true);
            return false;
        }
    }

    /**
     * Sends the failed order email to admin
     *
     * @version 3.1.0
     * @since 3.1.0
     * @param int $order_id
     * @return null
     */
    public function send_failed_order_email($order_id)
    {
        $emails = WC()->mailer()->get_emails();
        if (!empty($emails) && !empty($order_id)) {
            $emails['WC_Email_Failed_Order']->trigger($order_id);
        }
    }

    public function create_hosted_3ds($order)
    {
        $hosted_3ds_data = array(
            'token_id'        => wc_clean($_POST['xendit_token'] ? $_POST['xendit_token'] : $xendit_token->source),
            'amount'        => $order->get_total(),
            'return_url'    => $this->get_hosted_3ds_return_url($order),
            'external_id'    => "WC_Hosted_3DS_" . $order->get_id()
        );

        WC_Xendit_Logger::log('INFO: Starting Hosted 3DS Process');

        $hosted_3ds_response = WC_Xendit_API::request($hosted_3ds_data, 'hosted-3ds', 'POST', array(
            'should_use_public_key'    => true
        ));

        if (!empty($hosted_3ds_response->error)) {
            WC_Xendit_Logger::log('ERROR: Hosted 3DS error' . $hosted_3ds_response, LogDNA_Level::ERROR, true);
            $localized_message = $hosted_3ds_response->error->message;

            $order->add_order_note($localized_message);

            throw new WP_Error(print_r($hosted_3ds_response, true), $localized_message);
        }

        if (WC_Xendit_Helper::is_wc_lt('3.0')) {
            update_post_meta($order_id, '_xendit_hosted_3ds_id', $hosted_3ds_response->id);
        } else {
            $order->update_meta_data('_xendit_hosted_3ds_id', $hosted_3ds_response->id);
            $order->save();
        }

        return $hosted_3ds_response;
    }

    public function get_hosted_3ds_return_url($order)
    {
        if (is_object($order)) {
            if (empty($id)) {
                $id = uniqid();
            }

            $order_id = WC_Xendit_Helper::is_wc_lt('3.0') ? $order->id : $order->get_id();

            $args = array(
                'utm_nooverride' => '1',
                'order_id'       => $order_id,
            );

            return esc_url_raw(add_query_arg($args, $this->get_return_url($order)));
        }

        return esc_url_raw(add_query_arg(array('utm_nooverride' => '1'), $this->get_return_url()));
    }

    public function is_valid_for_use()
    {
        return in_array(get_woocommerce_currency(), apply_filters(
            'woocommerce_' . $this->id . '_supported_currencies',
            $this->supported_currencies
        ));
    }

    public function xendit_status_payment_gateways($gateways)
    {
        global $wpdb, $woocommerce;
        //WC()->cart->total;
        if (is_null($woocommerce->cart)) {
            return $gateways;
        }

        if ($this->enabled == 'no') {
            unset($gateways[$this->id]);
            return $gateways;
        }

        if ($this->secret_key == "") :
            unset($gateways[$this->id]);

            return $gateways;
        endif;

        if (!$this->is_valid_for_use()) {
            unset($gateways[$this->id]);

            return $gateways;
        }

        return $gateways;
    }

    /**
     * Map card's failure reason to more detailed explanation based on current insight.
     *
     * @param $failure_reason
     * @return string
     */
    private function failure_reason_insight($failure_reason)
    {
        switch ($failure_reason) {
            case 'CARD_DECLINED':
            case 'STOLEN_CARD':
                return 'CARD_DECLINED - The bank that issued this card declined the payment but didn\'t tell us why.
                Try another card, or try calling your bank to ask why the card was declined.';
            case 'INSUFFICIENT_BALANCE':
                return $failure_reason . ' - Your bank declined this payment due to insufficient balance. Ensure
                that sufficient balance is available, or try another card';
            case 'INVALID_CVN':
                return $failure_reason . ' - Your bank declined the payment due to incorrect card details entered. Try to
                enter your card details again, including expiration date and CVV';
            case 'INACTIVE_CARD':
                return $failure_reason . ' - This card number does not seem to be enabled for eCommerce payments. Try
                another card that is enabled for eCommerce, or ask your bank to enable eCommerce payments for your card.';
            case 'EXPIRED_CARD':
                return $failure_reason . ' - Your bank declined the payment due to the card being expired. Please try
                another card that has not expired.';
            case 'PROCESSOR_ERROR':
                return 'We encountered issue in processing your card. Please try again with another card';
            default:
                return $failure_reason;
        }
    }

    private function cancel_order($order, $note)
    {
        $order->update_status('wc-cancelled');
        $order->add_order_note($note);
    }

    private function get_message($key)
    {
        $args = array(
            'headers' => array(
                'content-type' => 'application/json',
                'x-plugin-name' => 'WOOCOMMERCE'
            )
        );

        $cache_message = wp_remote_get('https://tpi.xendit.co/messages?key=' . $key, $args);

        if (!is_wp_error($cache_message) && isset($cache_message['body'])) {
            $response = json_decode($cache_message['body'], true);

            if (isset($response['value'])) {
                set_transient('xendit_cards_deprecated_message', $response['value'], 43200); //expire in 12 hours
                return $response['value'];
            }
        }

        return "This plugin will not be updated in the future and will be <strong>deprecated</strong> soon. Please update or install <a href='https://wordpress.org/plugins/woo-xendit-virtual-accounts/'>WooCommerce - Xendit</a> plugin to accept single transaction and subscription with Credit Cards and Online Debit Card without breaking your current payment flow.";
    }
}

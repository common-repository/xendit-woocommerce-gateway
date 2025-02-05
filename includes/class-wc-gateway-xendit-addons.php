<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Xendit_Addons class.
 *
 * @extends WC_Gateway_Xendit
 */
class WC_Gateway_Xendit_Addons extends WC_Gateway_Xendit {

	public $wc_pre_30;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
			add_action( 'wcs_resubscribe_order_created', array( $this, 'delete_resubscribe_meta' ), 10 );
			add_action( 'wcs_renewal_order_created', array( $this, 'delete_renewal_meta' ), 10 );
			add_action( 'woocommerce_subscription_failing_payment_method_updated_xendit', array( $this, 'update_failing_payment_method' ), 10, 2 );

			// display the credit card used for a subscription in the "My Subscriptions" table
			add_filter( 'woocommerce_my_subscriptions_payment_method', array( $this, 'maybe_render_subscription_payment_method' ), 10, 2 );

			// allow store managers to manually set Xendit as the payment method on a subscription
			add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_subscription_payment_meta' ), 10, 2 );
			add_filter( 'woocommerce_subscription_validate_payment_meta', array( $this, 'validate_subscription_payment_meta' ), 10, 2 );
		}

		if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
			add_action( 'wc_pre_orders_process_pre_order_completion_payment_' . $this->id, array( $this, 'process_pre_order_release_payment' ) );
		}

		$this->wc_pre_30 = version_compare( WC_VERSION, '3.0.0', '<' );
	}

	/**
	 * Is $order_id a subscription?
	 * @param  int  $order_id
	 * @return boolean
	 */
	protected function is_subscription( $order_id ) {
		$this->log('is_subscription called in Xendit addons' . PHP_EOL);
		return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
	}

	/**
	 * Is $order_id a pre-order?
	 * @param  int  $order_id
	 * @return boolean
	 */
	protected function is_pre_order( $order_id ) {
		return ( class_exists( 'WC_Pre_Orders_Order' ) && WC_Pre_Orders_Order::order_contains_pre_order( $order_id ) );
	}

	/**
	 * Process the payment based on type.
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment( $order_id, $retry = true ) {
		$this->log('process_payment called in Xendit addons' . PHP_EOL);
		if ( $this->is_subscription( $order_id ) ) {
			// Regular payment with force subscription enabled
			$this->log('this order ' . print_r($order_id, true) . ' is a subscription' . PHP_EOL);
			return parent::process_payment( $order_id, true );

		} elseif ( $this->is_pre_order( $order_id ) ) {
			return $this->process_pre_order( $order_id, $retry, $force_customer );

		} else {
			return parent::process_payment( $order_id, $retry );
		}
	}

	/**
	 * Updates other subscription sources.
	 */
	protected function save_source( $order, $source ) {
		$this->log('save_source called in Xendit addons' . PHP_EOL);
		parent::save_source( $order, $source );

		$order_id  = $this->wc_pre_30 ? $order->id : $order->get_id();

		// Also store it on the subscriptions being purchased or paid for in the order
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id );

		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
		} else {
			$subscriptions = array();
		}

		foreach ( $subscriptions as $subscription ) {
			$this->log('first subscription.getID() -> ' . print_r($subscription->get_id(), true));

			$subscription_id = $this->wc_pre_30 ? $subscription->id : $subscription->get_id();
			update_post_meta( $subscription_id, '_xendit_subscription_id', $source->customer );
			update_post_meta( $subscription_id, '_xendit_card_id', $source->source );
		}
	}

	/**
	 * process_subscription_payment function.
	 * @param mixed $order
	 * @param int $amount (default: 0)
	 * @param string $xendit_token (default: '')
	 * @param  bool initial_payment
	 */
	public function process_subscription_payment( $order = '', $amount = 0 ) {
		$this->log('process_subscription_payment called in Xendit addons');

		if ( $amount * 100 < WC_Xendit::get_minimum_amount() ) {
			return new WP_Error( 'xendit_error', sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-xendit' ), wc_price( WC_Xendit::get_minimum_amount() / 100 ) ) );
		}

		// Get source from order
		$source = $this->get_order_source( $order );
		$this->log('process_subscription_payment -> get_order_source -> ' . print_r($source, true));

		// If no order source was defined, use user source instead.
		if ( ! $source->source ) {
			$source = $this->get_source( ( $this->wc_pre_30 ? $order->customer_user : $order->get_customer_id() ) );
		}

		// Or fail :(
		if ( ! $source->source ) {
			return new WP_Error( 'xendit_error', __( 'Customer not found', 'woocommerce-gateway-xendit' ) );
		}

		$order_id = $this->wc_pre_30 ? $order->id : $order->get_id();
		$this->log( "Info: Begin processing subscription payment for order {$order_id} for the amount of {$amount}" );

		// Make the request
		$request             = $this->generate_payment_request( $order, $source->source );

		$this->log('subscription request is -> ' . print_r($request, true));

		$response            = WC_Xendit_API::request( $request );

		// Process valid response
		if ( is_wp_error( $response ) ) {
			$this->log('CAUGHT ON IS_WP_ERRO');
			// if ( 'missing' === $response->get_error_code() ) {
			// 	// If we can't link customer to a card, we try to charge by customer ID.
			// 	$request             = $this->generate_sub_request( $order, $this->get_source( ( $this->wc_pre_30 ? $order->customer_user : $order->get_customer_id() ) ) );
			// 	$request['capture']  = 'true';
			// 	$request['amount']   = $this->get_xendit_amount( $amount, $request['currency'] );
			// 	$request['metadata'] = array(
			// 		'payment_type'   => 'recurring',
			// 		'site_url'       => esc_url( get_site_url() ),
			// 	);
			// 	$response          = WC_Xendit_API::request( $request );
			// } else {
			// 	return $response; // Default catch all errors.
			// }
		}

		$this->process_response( $response, $order );

		return $response;
	}

	/**
	 * Process the pre-order
	 * @param int $order_id
	 * @return array
	 */
	public function process_pre_order( $order_id, $retry, $force_customer ) {
		if ( WC_Pre_Orders_Order::order_requires_payment_tokenization( $order_id ) ) {
			try {
				$order = wc_get_order( $order_id );

				if ( $order->get_total() * 100 < WC_Xendit::get_minimum_amount() ) {
					throw new Exception( sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-xendit' ), wc_price( WC_Xendit::get_minimum_amount() / 100 ) ) );
				}

				$source = $this->get_source( get_current_user_id(), true );

				// We need a source on file to continue.
				if ( empty( $source->customer ) || empty( $source->source ) ) {
					throw new Exception( __( 'Unable to store payment details. Please try again.', 'woocommerce-gateway-xendit' ) );
				}

				// Store source to order meta
				$this->save_source( $order, $source );

				// Remove cart
				WC()->cart->empty_cart();

				// Is pre ordered!
				WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );

				// Return thank you page redirect
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			} catch ( Exception $e ) {
				wc_add_notice( $e->getMessage(), 'error' );
				return;
			}
		} else {
			return parent::process_payment( $order_id, $retry, $force_customer );
		}
	}

	/**
	 * Process a pre-order payment when the pre-order is released
	 * @param WC_Order $order
	 * @return void
	 */
	public function process_pre_order_release_payment( $order ) {
		try {
			// Define some callbacks if the first attempt fails.
			$retry_callbacks = array(
				'remove_order_source_before_retry',
				'remove_order_customer_before_retry',
			);

			while ( 1 ) {
				$source   = $this->get_order_source( $order );
				$response = WC_Xendit_API::request( $this->generate_payment_request( $order ) );

				if ( is_wp_error( $response ) ) {
					if ( 0 === sizeof( $retry_callbacks ) ) {
						throw new Exception( $response->get_error_message() );
					} else {
						$retry_callback = array_shift( $retry_callbacks );
						call_user_func( array( $this, $retry_callback ), $order );
					}
				} else {
					// Successful
					$this->process_response( $response, $order );
					break;
				}
			}
		} catch ( Exception $e ) {
			$order_note = sprintf( __( 'Xendit Transaction Failed (%s)', 'woocommerce-gateway-xendit' ), $e->getMessage() );

			// Mark order as failed if not already set,
			// otherwise, make sure we add the order note so we can detect when someone fails to check out multiple times
			if ( ! $order->has_status( 'failed' ) ) {
				$order->update_status( 'failed', $order_note );
			} else {
				$order->add_order_note( $order_note );
			}
		}
	}

	/**
	 * Don't transfer Xendit customer/token meta to resubscribe orders.
	 * @param int $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 */
	public function delete_resubscribe_meta( $resubscribe_order ) {
		delete_post_meta( ( $this->wc_pre_30 ? $resubscribe_order->id : $resubscribe_order->get_id() ), '_xendit_customer_id' );
		delete_post_meta( ( $this->wc_pre_30 ? $resubscribe_order->id : $resubscribe_order->get_id() ), '_xendit_card_id' );
		$this->delete_renewal_meta( $resubscribe_order );
	}

	/**
	 * Don't transfer Xendit fee/ID meta to renewal orders.
	 * @param int $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 */
	public function delete_renewal_meta( $renewal_order ) {
		delete_post_meta( ( $this->wc_pre_30 ? $renewal_order->id : $renewal_order->get_id() ), 'Xendit Fee' );
		delete_post_meta( ( $this->wc_pre_30 ? $renewal_order->id : $renewal_order->get_id() ), 'Net Revenue From Xendit' );
		delete_post_meta( ( $this->wc_pre_30 ? $renewal_order->id : $renewal_order->get_id() ), 'Xendit Payment ID' );
		return $renewal_order;
	}

	/**
	 * scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $renewal_order WC_Order A WC_Order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$this->log('called scheduled_subscription_payment in XENDIT ADDONS');
		$response = $this->process_subscription_payment( $renewal_order, $amount_to_charge );

		if ( is_wp_error( $response ) ) {
			$renewal_order->update_status( 'failed', sprintf( __( 'Xendit Transaction Failed (%s)', 'woocommerce-gateway-xendit' ), $response->get_error_message() ) );
		}
	}

	/**
	 * Remove order meta
	 * @param  object $order
	 */
	public function remove_order_source_before_retry( $order ) {
		$order_id = $this->wc_pre_30 ? $order->id : $order->get_id();
		delete_post_meta( $order_id, '_xendit_card_id' );
	}

	/**
	 * Remove order meta
	 * @param  object $order
	 */
	public function remove_order_customer_before_retry( $order ) {
		$order_id = $this->wc_pre_30 ? $order->id : $order->get_id();
		delete_post_meta( $order_id, '_xendit_customer_id' );
	}

	/**
	 * Update the customer_id for a subscription after using Xendit to complete a payment to make up for
	 * an automatic renewal payment which previously failed.
	 *
	 * @access public
	 * @param WC_Subscription $subscription The subscription for which the failing payment method relates.
	 * @param WC_Order $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 * @return void
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {
		if ( $this->wc_pre_30 ) {
			update_post_meta( $subscription->id, '_xendit_customer_id', $renewal_order->xendit_customer_id );
			update_post_meta( $subscription->id, '_xendit_card_id', $renewal_order->xendit_card_id );
		} else {
			$subscription->update_meta_data( '_xendit_customer_id', $renewal_order->get_meta( '_xendit_customer_id', true ) );
			$subscription->update_meta_data( '_xendit_card_id', $renewal_order->get_meta( '_xendit_card_id', true ) );
		}
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @since 2.5
	 * @param array $payment_meta associative array of meta data required for automatic payments
	 * @param WC_Subscription $subscription An instance of a subscription object
	 * @return array
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$payment_meta[ $this->id ] = array(
			'post_meta' => array(
				'_xendit_customer_id' => array(
					'value' => get_post_meta( ( $this->wc_pre_30 ? $subscription->id : $subscription->get_id() ), '_xendit_customer_id', true ),
					'label' => 'Xendit Customer ID',
				),
				'_xendit_card_id' => array(
					'value' => get_post_meta( ( $this->wc_pre_30 ? $subscription->id : $subscription->get_id() ), '_xendit_card_id', true ),
					'label' => 'Xendit Card ID',
				),
			),
		);
		return $payment_meta;
	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @since 2.5
	 * @param string $payment_method_id The ID of the payment method to validate
	 * @param array $payment_meta associative array of meta data required for automatic payments
	 * @return array
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta ) {
		if ( $this->id === $payment_method_id ) {

			if ( ! isset( $payment_meta['post_meta']['_xendit_customer_id']['value'] ) || empty( $payment_meta['post_meta']['_xendit_customer_id']['value'] ) ) {
				throw new Exception( 'A "_xendit_customer_id" value is required.' );
			} elseif ( 0 !== strpos( $payment_meta['post_meta']['_xendit_customer_id']['value'], 'cus_' ) ) {
				throw new Exception( 'Invalid customer ID. A valid "_xendit_customer_id" must begin with "cus_".' );
			}

			if ( ! empty( $payment_meta['post_meta']['_xendit_card_id']['value'] ) && 0 !== strpos( $payment_meta['post_meta']['_xendit_card_id']['value'], 'card_' ) ) {
				throw new Exception( 'Invalid card ID. A valid "_xendit_card_id" must begin with "card_".' );
			}
		}
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @since 1.7.5
	 * @param string $payment_method_to_display the default payment method text to display
	 * @param WC_Subscription $subscription the subscription details
	 * @return string the subscription payment method
	 */
	public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {
		$customer_user = $this->wc_pre_30 ? $subscription->customer_user : $subscription->get_customer_id();

		// bail for other payment methods
		if ( $this->id !== ( $this->wc_pre_30 ? $subscription->payment_method : $subscription->get_payment_method() ) || ! $customer_user ) {
			return $payment_method_to_display;
		}

		$xendit_customer    = new WC_Xendit_Customer();
		$xendit_customer_id = get_post_meta( ( $this->wc_pre_30 ? $subscription->id : $subscription->get_id() ), '_xendit_customer_id', true );
		$xendit_card_id     = get_post_meta( ( $this->wc_pre_30 ? $subscription->id : $subscription->get_id() ), '_xendit_card_id', true );

		// If we couldn't find a Xendit customer linked to the subscription, fallback to the user meta data.
		if ( ! $xendit_customer_id || ! is_string( $xendit_customer_id ) ) {
			$user_id            = $customer_user;
			$xendit_customer_id = get_user_meta( $user_id, '_xendit_customer_id', true );
			$xendit_card_id     = get_user_meta( $user_id, '_xendit_card_id', true );
		}

		// If we couldn't find a Xendit customer linked to the account, fallback to the order meta data.
		if ( ( ! $xendit_customer_id || ! is_string( $xendit_customer_id ) ) && false !== $subscription->order ) {
			$xendit_customer_id = get_post_meta( ( $this->wc_pre_30 ? $subscription->order->id : $subscription->get_parent_id() ), '_xendit_customer_id', true );
			$xendit_card_id     = get_post_meta( ( $this->wc_pre_30 ? $subscription->order->id : $subscription->get_parent_id() ), '_xendit_card_id', true );
		}

		$xendit_customer->set_id( $xendit_customer_id );
		$cards = $xendit_customer->get_cards();

		if ( $cards ) {
			$found_card = false;
			foreach ( $cards as $card ) {
				if ( $card->id === $xendit_card_id ) {
					$found_card                = true;
					$payment_method_to_display = sprintf( __( 'Via %1$s card ending in %2$s', 'woocommerce-gateway-xendit' ), ( isset( $card->type ) ? $card->type : $card->brand ), $card->last4 );
					break;
				}
			}
			if ( ! $found_card ) {
				$payment_method_to_display = sprintf( __( 'Via %1$s card ending in %2$s', 'woocommerce-gateway-xendit' ), ( isset( $cards[0]->type ) ? $cards[0]->type : $cards[0]->brand ), $cards[0]->last4 );
			}
		}

		return $payment_method_to_display;
	}

	/**
	 * Logs
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 *
	 * @param string $message
	 */
	// public function log( $message ) {
	// 	$options = get_option( 'woocommerce_xendit_settings' );
	//
	// 	if ( 'yes' === $options['logging'] ) {
	// 		WC_Xendit::log( $message );
	// 	}
	// }
	public function log( $message ){
	  if (!file_exists(dirname( __FILE__ ).'/log.txt')) {
		  file_put_contents(dirname( __FILE__ ).'/log.txt', 'Xendit Logs'."\r\n");
	  }

	  $debug_log_file_name = dirname( __FILE__ ) . '/log.txt';
	  $fp = fopen( $debug_log_file_name, "a" );
	  fwrite( $fp, $message );
	  fclose( $fp );
   }
}

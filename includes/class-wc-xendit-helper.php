<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Xendit Helper function
 * 
 * @since 1.2.3
 */
class WC_Xendit_Helper {
	/**
	 * Woocommerce version checker
	 *
	 * @since 1.2.3
	 * @version 1.2.3
   * @return bool
	 */
	public static function is_wc_lt( $version ) {
		return version_compare( WC_VERSION, $version, '<' );
	}
}

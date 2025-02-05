<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Xendit Logger
 *
 * @since 1.2.3
 */
class WC_Xendit_Logger
{

    public static $logger;
    const WC_LOG_FILENAME = 'xendit-woocommerce-gateway';

    /**
     * Utilize WC logger class
     *
     * @since 1.2.3
     * @version 1.2.3
     */
    public static function log( $message, $level = LogDNA_Level::INFO, $forward = false )
    {
        if (!class_exists('WC_Logger')) {
            return;
        }

        if (apply_filters('wc_xendit_logging', true, $message)) {
            if (empty(self::$logger)) {
                if (WC_Xendit_Helper::is_wc_lt('3.0')) {
                    self::$logger = new WC_Logger();
                } else {
                    self::$logger = wc_get_logger();
                }
            }

            $log_entry = "\n" . '<<Plugin Version: ' . WC_XENDIT_VERSION . '>>' . "\n";
            $log_entry .= '====Start Log====' . "\n" . $message . "\n" . '====End Log====' . "\n\n";

            if (WC_Xendit_Helper::is_wc_lt('3.0')) {
                self::$logger->add(self::WC_LOG_FILENAME, $log_entry);
            } else {
                self::$logger->debug($log_entry, array('source' => self::WC_LOG_FILENAME));
            }

            if ( $forward === true ) {
                WC_Xendit_LogDNA::log( $level, $log_entry );
            }
        }
    }
}

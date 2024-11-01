<?php
// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

delete_option("woocommerce_xendit_settings");
delete_option("_transient_timeout_xendit_cards_deprecated_message");
delete_option("_transient_xendit_cards_deprecated_message");
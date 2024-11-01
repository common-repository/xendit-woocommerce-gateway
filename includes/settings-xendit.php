<?php
if (! defined('ABSPATH')) {
    exit;
}

return apply_filters(
    'wc_xendit_settings',
    array(
        'enabled' => array(
            'title'       => __('Enable/Disable', 'woocommerce-gateway-xendit'),
            'label'       => __('Enable xendit', 'woocommerce-gateway-xendit'),
            'type'        => 'checkbox',
            'description' => '',
            'default'     => 'no',
        ),
        'channel_name' => array(
            'title' => __('Payment Channel Name', 'woocommerce-gateway-xendit'),
            'type' => 'text',
            'description' => __('Your payment channel name will be changed into <strong><span id="channel-name-format"></span></strong>', 'woocommerce-gateway-xendit'),
            'placeholder' => 'Credit Card (Xendit)',
        ),
        'payment_description' => array(
            'title' => __('Payment Description', 'woocommerce-gateway-xendit'),
            'type' => 'textarea',
            'css' => 'width: 400px;',
            'description' => __('Change your payment description for <strong><span id="channel-name-format-description"></span></strong>', 'woocommerce-gateway-xendit'),
            'placeholder' => 'Pay with your credit card via xendit.',
        ),
        'testmode' => array(
            'title'       => __('Test mode', 'woocommerce-gateway-xendit'),
            'label'       => __('Enable Test Mode', 'woocommerce-gateway-xendit'),
            'type'        => 'checkbox',
            'description' => __('Place the payment gateway in test mode using test API keys.', 'woocommerce-gateway-xendit'),
            'default'     => 'yes',
            'desc_tip'    => true,
        ),
        'test_publishable_key' => array(
            'title'       => __('Test Public Key', 'woocommerce-gateway-xendit'),
            'type'        => 'password',
            'description' => __('Get your API keys from your xendit account.', 'woocommerce-gateway-xendit'),
            'default'     => '',
            'desc_tip'    => true,
        ),
        'test_secret_key' => array(
            'title'       => __('Test Secret Key', 'woocommerce-gateway-xendit'),
            'type'        => 'password',
            'description' => __('Get your API keys from your xendit account.', 'woocommerce-gateway-xendit'),
            'default'     => '',
            'desc_tip'    => true,
        ),
        'publishable_key' => array(
            'title'       => __('Live Public Key', 'woocommerce-gateway-xendit'),
            'type'        => 'password',
            'description' => __('Get your API keys from your xendit account.', 'woocommerce-gateway-xendit'),
            'default'     => '',
            'desc_tip'    => true,
        ),
        'secret_key' => array(
            'title'       => __('Live Secret Key', 'woocommerce-gateway-xendit'),
            'type'        => 'password',
            'description' => __('Get your API keys from your xendit account.', 'woocommerce-gateway-xendit'),
            'default'     => '',
            'desc_tip'    => true,
        ),
        'statement_descriptor' => array(
            'title'       => __('Statement Descriptor', 'woocommerce-gateway-xendit'),
            'type'        => 'text',
            'description' => __('Extra information about a charge. This will appear on your customer’s credit card statement.', 'woocommerce-gateway-xendit'),
            'default'     => '',
            'desc_tip'    => true,
        )
    )
);

<?php
/**
 * WC_Gateway_scpayment class
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  SC Payment Gateway Plugin for WooCommerce
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * scpayment Gateway.
 *
 * @class    WC_Gateway_SCPayment
 * @version  1.0.7
 */
class WC_Gateway_SCPayment extends WC_Payment_Gateway {

	/**
	 * Payment gateway instructions.
	 * @var string
	 *
	 */
	protected $instructions;

	/**
	 * Whether the gateway is visible for non-admin users.
	 * @var boolean
	 *
	 */
	protected $hide_for_non_admin_users;

	/**
	 * Unique id for the gateway.
	 * @var string
	 *
	 */
	public $id = 'scpayment';

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		
		
		$this->icon               = 'https://scpayment-oss-ap-southeast-3.scpayments.com.my/logo/scpayment-logo.png';
		$this->has_fields         = true;
		$this->supports           = array(
			'products',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'multiple_subscriptions'
		);

		$this->method_title       = _x( 'SC Payment Payment', 'scpayment payment method', 'woocommerce-gateway-scpayment' );
		$this->method_description = __( 'SC Payment Payment Gateway Plug-in for WooCommerce', 'woocommerce-gateway-scpayment' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title                    = $this->get_option( 'title' );
		$this->description              = $this->get_option( 'description' );
		$this->instructions             = $this->get_option( 'instructions', $this->description );
		$this->hide_for_non_admin_users = $this->get_option( 'hide_for_non_admin_users' );
        $this->redirecturl              = $this->get_option('redirecturl');
        $this->merchantid               = $this->get_option('merchantid');
        $this->secretkey                = $this->get_option('secretkey');
        $this->hash_type = $this->get_option('hash_type');

        $this->environment_mode = $this->get_option('environment_mode');

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_scheduled_subscription_payment_scpayment', array( $this, 'process_subscription_payment' ), 10, 2 );
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-scpayment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable scpayment Payments', 'woocommerce-gateway-scpayment' ),
				'default' => 'yes',
			),
			'title' => array(
                'title' => __('Title', 'woocommerce-gateway-scpayment'),
                'type' => 'text',
                'desc_tip' => __('Payment title the customer will see during the checkout process.', 'woocommerce-gateway-scpayment'),
                'default' => __('SC Payment Payment', 'woocommerce-gateway-scpayment'),
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce-gateway-scpayment'),
                'type' => 'textarea',
                'desc_tip' => __('Payment description the customer will see during the checkout process.', 'woocommerce-gateway-scpayment'),
                'default' => __('', 'woocommerce-gateway-scpayment'),
                'css' => 'max-width:350px;',
            ),
            'merchantid' => array(
                'title' => __('Merchant ID', 'woocommerce-gateway-scpayment'),
                'type' => 'text',
                'desc_tip' => __('Merchant ID can obtain from SC Payment', 'woocommerce-gateway-scpayment'),
            ),
            'secretkey' => array(
                'title' => __('Secret Key', 'woocommerce-gateway-scpayment'),
                'type' => 'text',
                'desc_tip' => __('Merchant Key can obtain from SC Payment', 'woocommerce-gateway-scpayment'),
            ),
            'hash_type' => array(
                'title' => __('Hash Type', 'woocommerce-gateway-scpayment'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'description' => __('Signing method, currently only support sha256.', 'woocommerce-gateway-scpayment'),
                'default' => 'sha256',
                'desc_tip' => true,
                'options' => array(
                    'sha256' => __('sha256', 'woocommerce-gateway-scpayment'),
                ),
            ),
            'redirecturl' => array(
                'title' => __('Redirect URL', 'woocommerce-gateway-scpayment'),
                'type' => 'text',
                'desc_tip' => __('This is the payment return url', 'woocommerce-gateway-scpayment'),
                'default' => __('https://domain.com/wc-api/woocommerce-gateway-scpayment_redirect', 'woocommerce-gateway-scpayment'),
            ),
            'environment_mode' => array(
                'title' => __('Environment Mode', 'woocommerce-gateway-scpayment'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'description' => __('Choose environment mode, testing or production mode.', 'woocommerce-gateway-scpayment'),
                'default' => 'test',
                'desc_tip' => true,
                'options' => array(
                    'live' => __('Live', 'woocommerce-gateway-scpayment'),
                    'test' => __('Test', 'woocommerce-gateway-scpayment'),
                ),
            ),
		);
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param  int  $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {

		# Get Customer Order Detail
        $customer_order = wc_get_order($order_id);


		# Get Customer Order Detail
        $customer_order = wc_get_order($order_id);

        $old_wc = version_compare(WC_VERSION, '3.0', '<');

        if ($old_wc) {
            $order_id = $customer_order->id;
            $amount = $customer_order->order_total;
            $name = $customer_order->billing_first_name . ' ' . $customer_order->billing_last_name;
            $email = $customer_order->billing_email;
            $phone = $customer_order->billing_phone;
            $billingaddress1 = $customer_order->billing_address_1;
            $billingaddress2 = $customer_order->billing_address_2;
            $billingcity = $customer_order->billing_city;
            $billingstate = $customer_order->billing_state;
            $billingcountry = $customer_order->billing_country;
        } else {
            $order_id = $customer_order->get_id();
            $amount = $customer_order->get_total();
            $name = $customer_order->get_billing_first_name() . ' ' . $customer_order->get_billing_last_name();
            $email = $customer_order->get_billing_email();
            $phone = $customer_order->get_billing_phone();
            $billingaddress1 = $customer_order->get_billing_address_1();
            $billingaddress2 = $customer_order->get_billing_address_2();
            $billingcity = $customer_order->get_billing_city();
            $billingstate = $customer_order->get_billing_state();
            $billingcountry = $customer_order->get_billing_country();
        }

        // echo "<PRE>";
        // print_r($customer_order);
        // exit();

        $post_args = array(
            'MerchantID' => $this->merchantid,
            'CurrencyCode' => 'MYR',
            'TxnAmount' => $amount,
            'MerchantOrderNo' => $order_id . '_' . time(),
            'MerchantOrderDesc' => "Payment for Order No. : " . $order_id,
            'MerchantRef1' => $order_id,
            'MerchantRef2' => 'WooCommerce Version: ' . WC_VERSION,
            'MerchantRef3' => '',
            'CustReference' => '',
            'CustName' => $name,
            'CustEmail' => $email,
            'CustPhoneNo' => str_replace("+", "", $phone),
            'CustAddress1' => $billingaddress1,
            'CustAddress2' => $billingaddress2,
            'CustCountryCode' => $billingcountry,
            'CustAddressState' => $billingstate,
            'CustAddressCity' => $billingcity,
            'RedirectUrl' => $this->redirecturl,
        );

        // echo "<PRE>";
        // print_r($post_args);
        // exit();

        # make sign
        $signstr = "";
        foreach ($post_args as $key => $value) {
            $signstr .= $value;
        }

        if ($this->hash_type == 'sha256') {
            $post_args['SCSign'] = hash_hmac('sha256', $signstr, $this->secretkey);
        }

        # make query string
        $query_string = '';
        foreach ($post_args as $key => $value) {
            $query_string .= $key . "=" . urlencode($value) . '&';
        }

        # Remove Last &
        $query_string = substr($query_string, 0, -1);

        if ($this->environment_mode == 'test') {
            $environment_url = 'https://stagingpayment.scpayments.com.my/Payment';
        } else {
            $environment_url = 'https://payment.scpayments.com.my/Payment';
        }

        return array(
            'result' => 'success',
            'redirect' => $environment_url . '?' . $query_string,
        );


		// $payment_result = $this->get_option( 'result' );

		// if ( 'success' === $payment_result ) {
		// 	$order = wc_get_order( $order_id );

		// 	$order->payment_complete();

		// 	// Remove cart
		// 	WC()->cart->empty_cart();

		// 	// Return thankyou redirect
		// 	return array(
		// 		'result' 	=> 'success',
		// 		'redirect'	=> $this->get_return_url( $order )
		// 	);
		// } else {
		// 	$message = __( 'Order payment failed. To make a successful payment using scpayment Payments, please review the gateway settings.', 'woocommerce-gateway-scpayment' );
		// 	throw new Exception( $message );
		// }
	}





	public function scpayment_gateway_redirect()
    {
		$var = $_GET;

        $logger = wc_get_logger();
        $logger->info( wc_print_r( $var, true ), array( 'source' => 'scpayment_gateway_redirect' ));

        if (isset($var['RespCode']) && isset($var['RespDesc']) && isset($var['MerchantOrderNo']) && isset($var['MerchantRef1']) && isset($var['TxnRefNo']) && isset($var['SCSign'])) {
            global $woocommerce;

            $order = wc_get_order($var['MerchantRef1']);

            $old_wc = version_compare(WC_VERSION, '3.0', '<');

            $order_id = $old_wc ? $order->id : $order->get_id();

            if ($order && $order_id != 0) {
                # Check Sign
                $signstr = "";
                foreach ($var as $key => $value) {
                    if ($key == 'SCSign') {
                        continue;
                    }

                    $signstr .= $value;
                }
                $sign = "";
                if ($this->hash_type == 'sha256') {
                    $sign = hash_hmac('sha256', $signstr, $this->secretkey);
                }

                if ($sign == $var['SCSign']) {
                    if ($var['RespCode'] == '00' || $var['RespDesc'] == 'Success') {
                        if (strtolower($order->get_status()) == 'pending' || strtolower($order->get_status()) == 'processing') {
                            # only update if order is pending
                            if (strtolower($order->get_status()) == 'pending') {
                                $order->payment_complete();

                                $order->add_order_note('Payment successfully made through SC Payment with Transaction Reference ' . $var['TxnRefNo']);
                            }


                            

                            wp_redirect($order->get_checkout_order_received_url());
                            exit();
                        }
                        else if(strtolower($order->get_status()) == 'completed'){
                            wp_redirect($order->get_checkout_order_received_url());
                            exit();
                        }
                    } else {

                        if (strtolower($order->get_status()) == 'pending') {
                            
                            // $order->update_status('failed');
                            $order->add_order_note('Payment was unsuccessful');
                            add_filter('the_content', 'scpay_payment_declined_msg');
                        }
                        // $woocommerce->cart->empty_cart();

                        wp_redirect($woocommerce->cart->get_checkout_url());
                        exit();
                    }
                } else {
                    add_filter('the_content', 'scpayment_hash_error_msg');
                }
            }
        }
    }


    public function scpayment_gateway_callback()
    {
        $json = file_get_contents('php://input');
        $var = json_decode($json, true);

        $logger = wc_get_logger();
        $logger->info( wc_print_r( $var, true ), array( 'source' => 'scpayment_gateway_callback' ));

        if (isset($var['RespCode']) && isset($var['RespDesc']) && isset($var['MerchantOrderNo']) && isset($var['MerchantRef1']) && isset($var['TxnRefNo']) && isset($var['SCSign'])) {
            global $woocommerce;

            $order = wc_get_order($var['MerchantRef1']);

            if($order){
                $old_wc = version_compare(WC_VERSION, '3.0', '<');

                $order_id = $old_wc ? $order->id : $order->get_id();

                if ($order_id != 0) {
                    # Check Sign
                    $signstr = "";
                    foreach ($var as $key => $value) {
                        if ($key == 'SCSign') {
                            continue;
                        }

                        $signstr .= $value;
                    }
                    $sign = "";
                    if ($this->hash_type == 'sha256') {
                        $sign = hash_hmac('sha256', $signstr, $this->secretkey);
                    }

                    if ($sign == $var['SCSign']) {
                        if ($var['RespCode'] == '00' || $var['RespDesc'] == 'Success') {
                            if (strtolower($order->get_status()) == 'pending' || strtolower($order->get_status()) == 'processing') {
                                
                                # only update if order is pending
                                if (strtolower($order->get_status()) == 'pending') {
                                    $order->payment_complete();

                                    $order->add_order_note('Payment successfully made through SC Payment with Transaction Reference ' . $var['TxnRefNo']);
                                }

                                echo 'OK';
                                exit();
                            }
                        } else {
                            if (strtolower($order->get_status()) == 'pending') {
                                $order->add_order_note('Payment was unsuccessful');
                                add_filter('the_content', 'scpayment_payment_declined_msg');
                            }
                        }
                    } else {
                        add_filter('the_content', 'scpayment_hash_error_msg');
                    }
                }
            }
            else{
                // $logger->info( wc_print_r("order not found"), array( 'source' => 'scpay_callback' ));
            }

            echo "OK";
            exit();
        }
    }
}

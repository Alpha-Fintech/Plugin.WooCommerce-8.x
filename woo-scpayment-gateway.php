<?php
/**
 * Plugin Name: SC Payment Gateway 
 * Plugin URI: https://www.scpayments.com.my/
 * Description: SC Payment Gateway Plugin for WooCommerce.
 * Version: 1.0.8
 *
 * Author: SC Payment
 * Author URI: https://www.scpayments.com.my/
 *
 * Text Domain: woocommerce-gateway-scpayment
 * Domain Path: /i18n/languages/
 *
 * Requires at least: 4.0
 * Tested up to: 6.5.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC scpayment Payment gateway plugin class.
 *
 * @class WC_scpayment_Payments
 */
class WC_SCPayment_Payments {

	/**
	 * Plugin bootstrapping.
	 */
	public static function init() {

		// SC Payment Payments gateway class.
		add_action( 'plugins_loaded', array( __CLASS__, 'includes' ), 0 );

		// Make the SC Payment Payments gateway available to WC.
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ) );

		// Registers WooCommerce Blocks integration.
		add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'scpayment_gateway_block_support' ) );

	}


	

	/**
	 * Add the SC Payment Payment gateway to the list of available gateways.
	 *
	 * @param array
	 */
	public static function add_gateway( $gateways ) {

		$gateways[] = 'WC_Gateway_scpayment';
		return $gateways;
	}

	/**
	 * Plugin includes.
	 */
	public static function includes() {

		// Make the WC_Gateway_scpayment class available.
		if ( class_exists( 'WC_Payment_Gateway' ) ) {
			require_once 'includes/class-wc-gateway-scpayment.php';
		}
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_abspath() {
		return trailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Registers WooCommerce Blocks integration.
	 *
	 */
	public static function scpayment_gateway_block_support() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			require_once 'includes/blocks/class-wc-scpayment-payments-blocks.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new WC_Gateway_scpayment_Blocks_Support() );
				}
			);
		}
	}
}

WC_scpayment_Payments::init();


add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'scpayment_woo_plugin_links');

function scpayment_woo_plugin_links($links)
{
	$plugin_links = array(
		'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=scpayment' ) . '">' . __( 'Settings', 'woocommerce-gateway-scpayment' ) . '</a>',
	);

	# Merge our new link with the default ones
	return array_merge($links, $plugin_links);
	// return $plugin_links;
}


# redirect 
add_action( 'init', 'scpayment_gateway_redirect', 15 );

function scpayment_gateway_redirect() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	include_once( 'includes/class-wc-gateway-scpayment.php' );

	$func = new WC_Gateway_scpayment();
	$func->scpayment_gateway_redirect();
}



# callback 
add_action( 'init', 'scpayment_gateway_callback', 15 );

function scpayment_gateway_callback() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	include_once( 'includes/class-wc-gateway-scpayment.php' );

	$func = new WC_Gateway_scpayment();
	$func->scpayment_gateway_callback();
}




function scpayment_hash_error_msg( $content ) {
	return '<div class="woocommerce-error">The data that we received is invalid. Thank you.</div>' . $content;
}

function scpayment_payment_declined_msg( $content ) {
	return '<div class="woocommerce-error">The payment was declined. Please check with your bank. Thank you.</div>' . $content;
}

function scpayment_success_msg( $content ) {
	return '<div class="woocommerce-info">The payment was successful. Thank you.</div>' . $content;
}
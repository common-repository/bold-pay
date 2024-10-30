<?php
/**
 * Plugin Name: BOLD.Pay for WooCommerce
 * Plugin URI: https://boldpay.cc/login
 * Description: BOLD.Pay is a cloud-based multi-channel payment access plugin for WooCommerce.
 * Version: 1.5.8
 * Author: MACROKIOSK
 * Author URI: https://www.macrokiosk.com/
 * WooCommerce requires at least: 2.6.0
 * WooCommerce tested up to: 9.1.4
 **/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

# Include boldpay Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'boldpay_init', 0 );

function boldpay_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	include_once( 'src/boldpay.php' );

	add_filter( 'woocommerce_payment_gateways', 'add_boldpay_to_woocommerce' );
	function add_boldpay_to_woocommerce( $methods ) {
		$methods[] = 'boldpay';

		return $methods;
	}

	//remove payment gateway description at checkout page
	add_filter( 'woocommerce_gateway_description', 'payment_gateway_description', 25, 2 );

	function payment_gateway_description( $description, $gateway_id ) {

		if( 'boldpay' === $gateway_id ) {
			$description = null;
		}

		return $description;
	}
}

# Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'boldpay_links' );

function boldpay_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=boldpay' ) . '">' . __( 'Settings', 'boldpay' ) . '</a>',
	);

	# Merge our new link with the default ones
	return array_merge( $plugin_links, $links );
}

add_action( 'init', 'boldpay_check_response', 15 );

function boldpay_check_response() {
	# If the parent WC_Payment_Gateway class doesn't exist it means WooCommerce is not installed on the site, so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	include_once( 'src/boldpay.php' );

	$boldpay = new boldpay();
	$boldpay->check_boldpay_response();
}

function boldpay_hash_error_msg( $content ) {
	return '<div class="woocommerce-error">The data that we received is invalid. Thank you.</div>' . $content;
}

function boldpay_payment_declined_msg( $content ) {
	return '<div class="woocommerce-error">The payment was declined. Please check with your bank. Thank you.</div>' . $content;
}

function boldpay_payment_fail_connection_msg( $content ) {
	return '<div class="woocommerce-error">Were sorry. Please try again or contact your merchant for assistance.</div>' . $content;
}

function boldpay_success_msg( $content ) {
	return '<div class="woocommerce-info">The payment was successful. Thank you.</div>' . $content;
}

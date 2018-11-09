<?php
/**
 * Plugin Name: Woocommerce QIWI payment gateway
 * Plugin URI:  https://github.com/QIWI-API/woocommerce-payment-qiwi
 * Description: QIWI universal payment API integration for Woocommerce
 * Version:     0.0.1
 * Author:      QIWI
 * Author URI:  https://qiwi.com/
 * License:     MIT
 * License URI: https://www.gnu.org/licenses/mit.html
 * Text Domain: woocommerce_payment_qiwi
 * Domain Path: /languages
 * Depends:     WooCommerce
 *
 * @package woocommerce-payment-qiwi
 */

defined( 'ABSPATH' ) || exit;

// On Woocommerce ready.
add_action( 'woocommerce_init', function () {
	// Autoload.
	if ( file_exists( __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php' ) ) {
		include_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
	} else {
		include_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-gateway.php';
	}

	// Translations.
	load_plugin_textdomain( 'woocommerce_payment_qiwi', false, basename( __DIR__ ) . DIRECTORY_SEPARATOR . 'languages' );

	// Add menu link.
	add_action( 'admin_menu', function () {
		/*
		 * translators:
		 * ru_RU: QIWI Касса
		 */
		$title = __( 'QIWI cash', 'woocommerce_payment_qiwi' );
		add_submenu_page( 'woocommerce', $title, $title, 'manage_woocommerce', 'admin.php?page=wc-settings&tab=checkout&section=qiwi' );
	}, 51 );

	// Add gateway.
	add_filter( 'woocommerce_payment_gateways', function ( $methods ) {
		$methods[] = \Qiwi\Payment\Gateway::class;
		return $methods;
	} );
} );

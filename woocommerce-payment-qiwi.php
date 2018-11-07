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

add_action( 'woocommerce_init', function () {
	if ( file_exists( __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php' ) ) {
		include_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
	} else {
		include_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-gateway.php';
	}
	load_plugin_textdomain( 'woocommerce_payment_qiwi', false, basename( __DIR__ ) . DIRECTORY_SEPARATOR . 'languages' );
	add_action('admin_menu', function () {
		add_submenu_page(
			'woocommerce',
			__( 'QIWI', 'woocommerce_payment_qiwi' ),
			__( 'QIWI', 'woocommerce_payment_qiwi' ),
			'manage_woocommerce',
			'admin.php?page=wc-settings&tab=checkout&section=qiwi'
		);
	}, 51);
	add_filter( 'woocommerce_payment_gateways', function ( $methods ) {
		$methods[] = \Qiwi\Payment\Gateway::class;
		return $methods;
	} );
} );

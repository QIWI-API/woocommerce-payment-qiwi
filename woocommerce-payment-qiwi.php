<?php
/**
 * Plugin Name: Woocommerce QIWI payment gateway
 * Plugin URI:  https://github.com/QIWI-API/woocommerce-payment-qiwi
 * Description: QIWI universal payment API integration for Woocommerce
 * Version:     0.0.9
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

	// Require plugin function's.
	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	/**
	 * Plugin data.
	 *
	 * @var array $data
	 */
	$data = get_plugin_data( __FILE__, true, true );

	// Translations.
	load_plugin_textdomain( 'woocommerce_payment_qiwi', false, basename( __DIR__ ) . $data['DomainPath'] );

	/**
	 * The client name.
	 *
	 * @var string CLIENT_NAME
	 */
	if ( ! defined( 'CLIENT_NAME' ) ) {
		define( 'CLIENT_NAME', 'WordPress Woccomerce' );
	}

	/**
	 * The client version.
	 *
	 * @var string CLIENT_VERSION
	 */
	if ( ! defined( 'CLIENT_VERSION' ) ) {
		define( 'CLIENT_VERSION', $data['Version'] );
	}

	// Autoload for standalone composer build or composer's WordPress instalations.
	if ( ! class_exists( 'Curl\Curl' ) ) {
		require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'curl' . DIRECTORY_SEPARATOR . 'curl' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Curl' . DIRECTORY_SEPARATOR . 'Curl.php';
	}

	if ( ! class_exists( 'Qiwi\Api\BillPaymentsException' ) ) {
		require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'qiwi' . DIRECTORY_SEPARATOR . 'bill-payments-php-sdk' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'BillPaymentsException.php';
	}

	if ( ! class_exists( 'Qiwi\Api\BillPayments' ) ) {
		require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'qiwi' . DIRECTORY_SEPARATOR . 'bill-payments-php-sdk' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'BillPayments.php';
	}

	if ( ! class_exists( 'Qiwi\Payment\Gateway' ) ) {
		require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-gateway.php';
	}

	// Ready for use or noticie broken installation.
	if ( class_exists( 'Qiwi\Payment\Gateway' ) ) {
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
	} else {
		add_action( 'admin_notices', function () use ( $data ) {
			printf(

				/*
				 * translators:
				 * ru_RU: <div class="error"><p><strong>Ошибка:</strong> плагин «%1$s» установлен неправильно.</p><p>Пожалуйста, ознакомьтесь с инструкциями по <a href="%2$s" target="_blank">установке в ручную</a>.</p></div>
				 * %1$s: Localised plugin name
				 * %2$s: URL to instalation manual
				 */
				__( '<div class="error"><p><strong>Error:</strong> Plugin "%1$s" is not installed correctly.</p><p>Please, see instructions for <a href="%2$s" target="_blank">manual instalation</a>.</p></div>', 'woocommerce_payment_qiwi' ),
				esc_html( $data['Name'] ),
				esc_url( $data['PluginURI'] . '#manual-installation' )
			); // WPCS: XSS OK.
		} );
	}

} );

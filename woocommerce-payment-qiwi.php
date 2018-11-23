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
	// Translations.
	load_plugin_textdomain( 'woocommerce_payment_qiwi', false, basename( __DIR__ ) . DIRECTORY_SEPARATOR . 'languages' );

	// Composer autoload.
	if ( file_exists( __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php' ) ) {
		include_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
	}
	// if it's composer wordpress instalations.
	else if ( class_exists( 'Qiwi\Api\BillPayments' ) ) {
		include_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-gateway.php';
	}

	// Ready for use.
	if ( class_exists( 'Qiwi\Payment\Gateway' ) )
	{
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
	}
	// Broken installation.
	else {
		add_action( 'admin_notices', function () {
			/** @var array $data Plugin data. */
			$data = get_plugin_data( __FILE__, true, true );
			/*
             * translators:
             * ru_RU: <div class="error"><p><strong>Ошибка:</strong> плагин «%1$s» установлен неправильно.</p><p>Пожалуйста, ознакомьтесь с инструкциями по <a href="%2$s" target="_blank">установке в ручную</a>.</p></div>
			 * %1$s: Localised plugin name
			 * %2$s: URL to instalation manual
             */
			printf(
				__( '<div class="error"><p><strong>Error:</strong> Plugin "%1$s" is not installed correctly.</p><p>Please, see instructions for <a href="%2$s" target="_blank">manual instalation</a>.</p></div>', 'woocommerce_payment_qiwi' ),
				$data['Name'],
				$data['PluginURI'] . '#manual-installation'
			);
		} );
	}

} );

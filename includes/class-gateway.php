<?php
/**
 * Interaction witch Woocommerce.
 *
 * @package woocommerce-payment-qiwi
 */

namespace Qiwi\Payment;

defined( 'ABSPATH' ) || exit;

use WC_HTTPS;
use WC_Order;
use WC_Payment_Gateway;
use WP_REST_Server;
use WP_Error;
use Exception;
use ErrorException;
use Qiwi\Api\BillPayments;

/**
 * QIWI payment gateway.
 *
 * @package Qiwi\Payment
 */
class Gateway extends WC_Payment_Gateway {
	/**
	 * The signature header.
	 */
	const HEADER_SIGNATURE = 'X-Api-Signature-SHA256';

	/**
	 * ID of the class extending the settings API. Used in option names.
	 *
	 * @var string
	 */
	public $id = 'qiwi';

	/**
	 * The QIWI API.
	 *
	 * @var BillPayments
	 */
	protected $bill_payments;

	/**
	 * The notification URL.
	 *
	 * @var string
	 */
	protected $notification_url;

	/**
	 * The secret key.
	 *
	 * @var string
	 */
	protected $secret_key;

	/**
	 * The supported ways.
	 *
	 * @var string
	 */
	protected $method_supports;

	/**
	 * The title icon.
	 *
	 * @var string
	 */
	protected $title_icon;

	/**
	 * Gateway constructor.
	 */
	public function __construct() {
		/*
		 * translators:
		 * ru_RU: QIWI Касса
		 */
		$this->method_title = __( 'QIWI cash', 'woocommerce_payment_qiwi' );

		/*
		 * translators:
		 * ru_RU: Оплата через: VISA, MasterCard, МИР, Баланс телефона, QIWI Кошелек
		 */
		$this->method_supports = __( 'Payment over: VISA, MasterCard, MIR, Phone balance, QIWI Wallet', 'woocommerce_payment_qiwi' );

		/*
		 * translators:
		 * ru_RU: Оплата с банковских карт, QIWI Кошелька и баланса телефона. Гарантия безопасности ваших платежей.
		 */
		$this->method_description = __( 'Payment from bank cards, QIWI Wallet and phone balance. Guaranteed security of your payments.', 'woocommerce_payment_qiwi' );

		// Getaway accept billing and refunding.
		$this->supports = [ 'products', 'refunds' ];

		// Setup icons.
		$this->icon       = plugins_url( 'assets/payments.svg', __DIR__ );
		$this->title_icon = plugins_url( 'assets/logo_kassa.svg', __DIR__ );

		// Setup readonly props.
		$this->notification_url = site_url() . '/?wc-api=' . $this->id;

		// Initialise config and form.
		$this->init_form_fields();
		$this->init_settings();

		// Set up from options.
		$this->title       = $this->get_option( 'title', $this->method_title );
		$this->description = $this->get_option( 'description', $this->method_description );
		$this->secret_key  = $this->get_option( 'secret_key' );

		// Setup CURL options.
		$options = [];
		$ca_path = dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'cacert.pem';
		if ( is_file( $ca_path ) ) {
			$options[ CURLOPT_SSL_VERIFYPEER ] = true;
			$options[ CURLOPT_SSL_VERIFYHOST ] = 2;
			$options[ CURLOPT_CAINFO         ] = $ca_path;
		}

		// Initialise API.
		try {
			$this->bill_payments = new BillPayments( $this->secret_key, $options );
		} catch ( ErrorException $exception ) {
			wc_add_wp_error_notices( new WP_Error(
				$exception->getCode(),
				$exception->getMessage(),
				$exception
			) );
		}

		// Capture API callback.
		add_action( "woocommerce_api_{$this->id}", [ $this, 'woocommerce_api' ] );

		// Prevent logo escape
		add_filter('esc_html', [ $this, 'title_esc_html' ], 50, 2);

		if ( is_admin() ) {
			// Capture options change.
			add_action( "woocommerce_update_options_payment_gateways_{$this->id}", [ $this, 'process_admin_options' ] );
		} else {
			// Add frontend style.
			wp_enqueue_style(
				'woocommerce-payment-qiwi',
				plugins_url( '/assets/qiwi.css', __DIR__ ),
				[],
				filemtime( dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'qiwi.css' )
			);
		}
	}

	/**
	 * Get method description.
	 *
	 * @return string
	 */
	public function get_method_description() {
		$method_description = $this->method_description;

		/*
		 * translators:
		 * ru_RU: Для начала работы с сервисом QIWI Касса необходима <a href="https://kassa.qiwi.com/" target="_blank">регистрация магазина</a>.
		 */
		$method_description .= PHP_EOL . __( 'To start working with the QIWI cash service, you need to <a href="https://kassa.qiwi.com/" target="_blank">register a store</a>.', 'woocommerce_payment_qiwi' );

		/*
		 * translators:
		 * ru_RU: Так же, для вас доступен <a href="https://developer.qiwi.com/demo/" target="_blank">демонстрационный стенд</a>.
		 */
		$method_description .= PHP_EOL . __( 'Also, a <a href="https://kassa.qiwi.com/" target="_blank">demonstration stand</a> is available for you.', 'woocommerce_payment_qiwi' );
		return apply_filters( 'woocommerce_gateway_method_description', $method_description, $this );
	}

	/**
	 * Return the gateway's title.
	 *
	 * @return string
	 */
	public function get_title() {
		if ( is_admin() ) {
			return parent::get_title();
		}
		$title = $this->icon ? '<img src="' . WC_HTTPS::force_https_url( $this->title_icon ) . '" alt="' . esc_attr( $this->title ) . '" class="qiwi" />' : '';
		return apply_filters( 'woocommerce_gateway_title', $title, $this->id );
	}

	/**
	 * Return title unescaped always.
	 *
	 * @param $safe_text
	 * @param $text
	 *
	 * @return mixed
	 */
	public function title_esc_html( $safe_text, $text ) {
		static $title_text;
		if ( is_null( $title_text ) ) {
			$title_text = $this->get_title();
		}
		return $text == $title_text ? $text : $safe_text;
	}

	/**
	 * Return the gateway's icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon = $this->icon ? '<span><img src="' . WC_HTTPS::force_https_url( $this->icon ) . '" alt="' . esc_attr( $this->method_supports ) . '" /></span>' : '';
		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	/**
	 * Check gateway not ready for use.
	 *
	 * @return bool
	 */
	public function needs_setup() {
		return ! empty( $this->secret_key ) || ! empty( $this->bill_payments );
	}

	/**
	 * Initialise settings.
	 */
	public function init_settings() {
		parent::init_settings();

		// Readonly option.
		$this->settings['notification_url'] = $this->notification_url;
	}

	/**
	 * Set up config page fields.
	 */
	public function init_form_fields() {
		$this->form_fields = [
			'enabled'          => [

				/*
				 * translators:
				 * ru_RU: Включение / выключение
				 */
				'title'   => __( 'Turn On/off', 'woocommerce_payment_qiwi' ),

				/*
				 * translators:
				 * ru_RU: Активировать модуль оплаты %1$s
				 * 1: Название модуля
				 */
				'label'   => sprintf( __( 'Activate payment module %1$s', 'woocommerce_payment_qiwi' ), $this->method_title ),
				'type'    => 'checkbox',
				'default' => 'no',
			],
			'title'            => [

				/*
				 * translators:
				 * ru_RU: Заголовок
				 */
				'title'       => __( 'Title', 'woocommerce_payment_qiwi' ),

				/*
				 * translators:
				 * ru_RU: Название метода оплаты, отображаемое клиентам.
				 */
				'description' => __( 'The name of the payment method displayed to customers.', 'woocommerce_payment_qiwi' ),
				'type'        => 'text',
				'default'     => $this->method_title,
			],
			'description'      => [

				/*
				 * translators:
				 * ru_RU: Описание
				 */
				'title'       => __( 'Description', 'woocommerce_payment_qiwi' ),

				/*
				 * translators:
				 * ru_RU: Описание метода оплаты, отображаемое клиентам.
				 */
				'description' => __( 'Description of the payment method displayed to customers.', 'woocommerce_payment_qiwi' ),
				'type'        => 'textarea',
				'default'     => $this->method_description,
			],
			'secret_key'       => [

				/*
				 * translators:
				 * ru_RU: Секретный ключ
				 */
				'title'       => __( 'Secret key', 'woocommerce_payment_qiwi' ),

				/*
				 * translators:
				 * ru_RU: Ключ к платежной системе для вашего магазина.
				 */
				'description' => __( 'The key to the payment system for your store.', 'woocommerce_payment_qiwi' ),
				'type'        => 'password',
			],
			'notification_url' => [

				/*
				 * translators:
				 * ru_RU: Адрес для уведомлений
				 */
				'title'       => __( 'Notification address', 'woocommerce_payment_qiwi' ),

				/*
				 * translators:
				 * ru_RU: Установите это значение в настройках магазина платежной системы.
				 */
				'description' => __( 'Set this value in the payment system store settings.', 'woocommerce_payment_qiwi' ),
				'type'        => 'text',
				'disabled'    => true,
				'default'     => $this->notification_url,
			],
			'theme_code'       => [

				/*
				 * translators:
				 * ru_RU: Код стиля
				 */
				'title'       => __( 'Theme style code', 'woocommerce_payment_qiwi' ),

				/*
				 * translators:
				 * ru_RU: Код персонализации стиля платежной формы полученный в настройках магазина платежной системы.
				 */
				'description' => __( 'Personalization code of the payment form style is presented in the payment system store settings.', 'woocommerce_payment_qiwi' ),
				'type'        => 'text',
			],
		];
	}

	/**
	 * Process QIWI notification.
	 */
	public function woocommerce_api() {
		// Get request data.
		$sign   = array_key_exists( 'HTTP_X_API_SIGNATURE_SHA256', $_SERVER ) ? wp_unslash( $_SERVER['HTTP_X_API_SIGNATURE_SHA256'] ) : '';  // phpcs:ignore WordPress.VIP
		$body   = WP_REST_Server::get_raw_data();
		$notice = json_decode( $body, true );

		// Check signature.
		if ( ! $this->bill_payments->checkNotificationSignature( $sign, $notice, $this->secret_key ) ) {
			wp_send_json( [ 'error' => 403 ], 403 );
		}

		// Get order.
		$orders = wc_get_orders( [
			'limit'          => 1,
			'transaction_id' => $notice['bill']['billId'],
		] );
		/**
		 * The order.
		 *
		 * @var WC_Order $order
		 */
		$order = reset( $orders );

		// Process status.
		switch ( $notice['bill']['status']['value'] ) {
			case 'WAITING':
				$order->update_status( 'pending' );
				break;
			case 'PAID':
				$order->payment_complete();
				break;
			case 'REJECTED':
				$order->update_status( 'canceled' );
				break;
			case 'EXPIRED':
				$order->update_status( 'failed' );
				break;
			case 'PARTIAL':
				$order->update_status( 'processing' );
				break;
			case 'FULL':
				$order->update_status( 'refunded' );
				break;
		}
		wp_send_json( [ 'error' => 0 ], 200 );
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id The order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		// Get processed data.
		$order   = wc_get_order( $order_id );
		$bill_id = $order->get_transaction_id();

		// Return notice on trow.
		try {
			// Need to create bill transaction.
			if ( empty( $bill_id ) ) {
				$bill_id = $this->bill_payments->generateId();
				$bill    = $this->bill_payments->createBill( $bill_id, [
					'amount'             => $order->get_total(),
					'currency'           => $order->get_currency(),
					'expirationDateTime' => $this->bill_payments->getLifetimeByDay(),
					'phone'              => $order->get_billing_phone(),
					'email'              => $order->get_billing_email(),
					'account'            => $order->get_user_id(),
					'successUrl'         => $this->get_return_url( $order ),
					'customFields'       => array_filter([
						'themeCode' => $this->get_option( 'theme_code' ),
					]),
				] );
				$order->set_transaction_id( $bill_id );
				$order->save();

				// Reduce stock levels.
				wc_reduce_stock_levels( $order->get_id() );

				// Remove cart.
				wc_empty_cart();
			} elseif ( ! $order->is_paid() && $order->get_status() === 'cancelled' ) {
				$bill = $this->bill_payments->cancelBill( $bill_id );
			} else {
				$bill = $this->bill_payments->getBillInfo( $bill_id );
			}
		} catch ( Exception $exception ) {
			wc_add_wp_error_notices( new WP_Error(
				$exception->getCode(),
				$exception->getMessage(),
				$exception
			) );
			return [ 'result' => 'fail' ];
		}

		// Return thank you redirect.
		return [
			'result'   => 'success',
			'redirect' => $this->bill_payments->getPayUrl( $bill, $this->get_return_url( $order ) ),
		];
	}

	/**
	 * Process refunding.
	 *
	 * @param int    $order_id The order ID.
	 * @param null   $amount   The refund amount.
	 * @param string $reason   The refund reason.
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = null ) {
		// Extract data.
		$order   = wc_get_order( $order_id );
		$bill_id = $order->get_transaction_id();

		// Generated refund transaction ID.
		try {
			$refund_id = $this->bill_payments->generateId();
		} catch ( Exception $exception ) {
			return new WP_Error(
				$exception->getCode(),
				$exception->getMessage(),
				$exception
			);
		}

		// Refund transaction.
		try {
			$refund = $this->bill_payments->refund( $bill_id, $refund_id, $amount, $order->get_currency() );
		} catch ( Exception $exception ) {
			return new WP_Error(
				$exception->getCode(),
				$exception->getMessage(),
				$exception
			);
		}

		// Process result.
		switch ( $refund['status'] ) {
			case 'PARTIAL':
			case 'FULL':
				$order->add_order_note( sprintf(

					/*
					 * translators:
					 * ru_RU: Выполнен возврат %1$s
					 * 1: ID транзакции
					 */
					__( 'Completed refund  %1$s', 'woocommerce_payment_qiwi' ),
					$refund_id
				) );
				return true;
		}
		return false;
	}
}

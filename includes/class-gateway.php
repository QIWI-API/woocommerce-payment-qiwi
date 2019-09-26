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
	 * The theme code.
	 *
	 * @var string
	 */
	protected $theme_code;

	/**
	 * The alive time.
	 *
	 * @var int
	 */
	protected $alive_time;

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
	 * The use HTML flag.
	 *
	 * @var bool
	 */
	protected $use_html;

	/**
	 * The use popup flag.
	 *
	 * @var bool
	 */
	protected $use_popup;

	/**
	 * The use debug flag.
	 *
	 * @var bool
	 */
	protected $use_debug;

	/**
	 * The title icon HTML.
	 *
	 * @var string
	 */
	protected $title_icon_html;

	/**
	 * The icon HTML.
	 *
	 * @var string
	 */
	protected $icon_html;

	/**
	 * The method description HTML.
	 *
	 * @var string
	 */
	protected $method_description_html;

	/**
	 * Woocommerce logger.
	 *
	 * @var /WC_Logger_Interface
	 */
	protected $logger;

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

		// Setup logger.
		$this->logger = wc_get_logger();

		// Setup icons.
		$this->icon       = plugins_url( 'assets/payments.svg', __DIR__ );
		$this->title_icon = plugins_url( 'assets/logo_kassa.svg', __DIR__ );

		// Setup readonly props.
		$this->notification_url = site_url() . '/?wc-api=' . $this->id;

		// Initialise config and form.
		$this->init_form_fields();
		$this->init_settings();
		$this->init_options();

		// Set up HTML fragments.
		$this->title_icon_html         = '<img src="' . WC_HTTPS::force_https_url( $this->title_icon ) . '" alt="' . esc_attr( $this->title ) . '" class="qiwi" />';
		$this->icon_html               = '<img src="' . WC_HTTPS::force_https_url( $this->icon ) . '" alt="' . esc_attr( $this->method_supports ) . '" />';
		$this->method_description_html = $this->method_description . PHP_EOL .

			/*
			 * translators:
			 * ru_RU: Для начала работы с сервисом QIWI Касса необходима <a href="https://kassa.qiwi.com/" target="_blank">регистрация магазина</a>.
			 */
			__( 'To start working with the QIWI cash service, you need to <a href="https://kassa.qiwi.com/" target="_blank">register a store</a>.', 'woocommerce_payment_qiwi' ) . PHP_EOL .

			/*
			 * translators:
			 * ru_RU: Так же, для вас доступен <a href="https://developer.qiwi.com/demo/" target="_blank">демонстрационный стенд</a>.
			 */
			__( 'Also, a <a href="https://kassa.qiwi.com/" target="_blank">demonstration stand</a> is available for you.', 'woocommerce_payment_qiwi' );

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

		// Prevent html escape.
		add_filter( 'esc_html', [ $this, 'title_esc_html' ], 50, 2 );

		if ( is_admin() ) {
			// Capture options change.
			add_action( "woocommerce_update_options_payment_gateways_{$this->id}", [ $this, 'process_admin_options' ] );
		} else {
			// Add frontend scripts.
			if ( $this->use_popup ) {
				wp_register_script( 'qiwi-oplata-popup', 'https://oplata.qiwi.com/popup/v1.js' );
				wp_enqueue_script(
					'woocommerce-payment-qiwi-popup',
					plugins_url( '/assets/popup.js', __DIR__ ),
					[ 'qiwi-oplata-popup', 'wc-checkout' ],
					filemtime( dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'popup.js' ),
					true
				);
			}

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
	 * Log debug message.
	 *
	 * @param string $message The message.
	 * @param array  $context The context.
	 */
	protected function log( $message, $context ) {
		if ( $this->use_debug ) {
			//phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Because on debug mode only.
			$this->logger->debug( $message . ': ' . print_r( $context, true ) );
			//phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}
	}

	/**
	 * Load options.
	 */
	protected function init_options() {
		$this->title       = $this->get_option( 'title', $this->method_title );
		$this->use_html    = $this->get_option( 'use_html', 'yes' ) === 'yes';
		$this->description = $this->get_option( 'description', $this->method_description );
		$this->secret_key  = $this->get_option( 'secret_key' );
		$this->theme_code  = $this->get_option( 'theme_code' );
		$this->alive_time  = intval( $this->get_option( 'alive_time', 45 ) );
		$this->use_popup   = $this->get_option( 'use_popup', 'not' ) === 'yes';
		$this->use_debug   = $this->get_option( 'use_debug' ) === 'yes';
	}

	/**
	 * Process settings change.
	 *
	 * @return bool
	 */
	public function process_admin_options() {
		/**
		 * The WP DB instance.
		 *
		 * @var \wpdb $wpdb
		 */
		global $wpdb;

		$result = parent::process_admin_options();
		$this->init_options();

		/**
		 * Query for update payment title on old bills.
		 *
		 * @noinspection SqlResolve
		 */
		$wpdb->query( $wpdb->prepare(
			"UPDATE $wpdb->postmeta AS t1 LEFT JOIN $wpdb->postmeta AS t2 ON t1.`post_id` = t2.`post_id` SET t1.`meta_value` = %s WHERE t1.`meta_key` = '_payment_method_title' AND t2.`meta_key` = '_payment_method' AND t2.`meta_value` = %s",
			$this->get_title(),
			$this->id
		) );

		return $result;
	}

	/**
	 * Get method description.
	 *
	 * @return string
	 */
	public function get_method_description() {
		return apply_filters( 'woocommerce_gateway_method_description', $this->method_description_html, $this );
	}

	/**
	 * Return the gateway's title.
	 *
	 * @return string
	 */
	public function get_title() {
		return apply_filters( 'woocommerce_gateway_title', $this->use_html ? $this->title_icon_html : $this->title, $this->id );
	}

	/**
	 * Return title unescaped always.
	 *
	 * @param string $safe_text The safe text.
	 * @param string $text The original text.
	 *
	 * @return mixed
	 */
	public function title_esc_html( $safe_text, $text ) {
		return $text === $this->title_icon_html ? $text : $safe_text;
	}

	/**
	 * Return the gateway's icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return apply_filters( 'woocommerce_gateway_icon', $this->icon_html, $this->id );
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
			'use_html'         => [

				/*
				 * translators:
				 * ru_RU: Использовать HTML
				 */
				'title'   => __( 'Use logo', 'woocommerce_payment_qiwi' ),

				/*
				 * translators:
				 * ru_RU: Модуль оплаты %1$s будет отображать логотип вместо названия
				 * 1: Название модуля
				 */
				'label'   => sprintf( __( 'Payment module %1$s will show logo instead name', 'woocommerce_payment_qiwi' ), $this->method_title ),
				'type'    => 'checkbox',
				'default' => 'yes',
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
			'alive_time'       => [

				/*
				 * translators:
				 * ru_RU: Время ожидания оплаты
				 */
				'title'       => __( 'Waiting payment time', 'woocommerce_payment_qiwi' ),

				/*
				 * translators:
				 * ru_RU: Максимальное время ожидания оплаты по счету в днях.
				 */
				'description' => __( 'The maximum waiting time for payment on the order in days.', 'woocommerce_payment_qiwi' ),
				'type'        => 'text',
			],
			'use_popup'        => [

				/*
				 * translators:
				 * ru_RU: Всплывающая форма оплаты
				 */
				'title'       => __( 'Payment form popup', 'woocommerce_payment_qiwi' ),

				/*
				 * translators:
				 * ru_RU: Использовать всплывающую форму оплаты на странице магазина, вместо перехода на сайт QIWI Касса.
				 */
				'description' => __( 'Use the pop-up payment form on the store page, instead of going to the QIWI Kassa website.', 'woocommerce_payment_qiwi' ),
				'type'        => 'checkbox',
				'default'     => 'not',
			],
			'use_debug'        => [

				/*
				 * translators:
				 * ru_RU: Режим отладки
				 */
				'title'       => __( 'Debug mode', 'woocommerce_payment_qiwi' ),

				/*
				 * translators:
				 * ru_RU: Включить логирование запросов API QIWI Касса.
				 */
				'description' => __( 'Enable logging QIWI Kassa API requests.', 'woocommerce_payment_qiwi' ),
				'type'        => 'checkbox',
				'default'     => 'not',
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
		$result = $this->bill_payments->checkNotificationSignature( $sign, $notice, $this->secret_key );
		$this->log(
			$result ?

				/*
				 * translators:
				 * ru_RU: Получено действительное уведомление
				 */
				__( 'Received valid notification', 'woocommerce_payment_qiwi' ) :

				/*
				 * translators:
				 * ru_RU: Получено недействительное уведомление
				 */
				__( 'Received invalid notification', 'woocommerce_payment_qiwi' ),
			[
				'sign'   => $sign,
				'notice' => $notice,
			]
		);
		if ( ! $result ) {
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
			if ( $order->get_status() === 'pending' ) {
				$bill_id = $this->bill_payments->generateId();
				$params  = [
					'amount'             => $order->get_total(),
					'currency'           => $order->get_currency(),
					'expirationDateTime' => $this->bill_payments->getLifetimeByDay( $this->alive_time ),
					'phone'              => $order->get_billing_phone(),
					'email'              => $order->get_billing_email(),
					'account'            => $order->get_user_id(),
					'successUrl'         => $this->get_return_url( $order ),
					'customFields'       => array_filter([
						'themeCode' => $this->theme_code,
					]),
				];
				$bill    = $this->bill_payments->createBill( $bill_id, $params );
				$this->log(

					/*
					 * translators:
					 * ru_RU: Создан счет
					 */
					__( 'Create bill', 'woocommerce_payment_qiwi' ),
					[
						'params' => $params,
						'bill'   => $bill,
					]
				);
				$order->set_transaction_id( $bill_id );
				$order->save();

				// Reduce stock levels.
				wc_reduce_stock_levels( $order->get_id() );

				// Remove cart.
				wc_empty_cart();
			} elseif ( ! $order->is_paid() && $order->get_status() === 'cancelled' ) {
				$bill = $this->bill_payments->cancelBill( $bill_id );
				$this->log(

					/*
					 * translators:
					 * ru_RU: Завершен счет
					 */
					__( 'Cancel bill', 'woocommerce_payment_qiwi' ),
					[
						'bill_id' => $bill_id,
						'bill'    => $bill,
					]
				);
			} else {
				$bill = $this->bill_payments->getBillInfo( $bill_id );
				$this->log(

					/*
					 * translators:
					 * ru_RU: Получена информация о счете
					 */
					__( 'Get bill info', 'woocommerce_payment_qiwi' ),
					[
						'bill_id' => $bill_id,
						'bill'    => $bill,
					]
				);
			}
		} catch ( Exception $exception ) {
			/*
			 * translators:
			 * ru_RU: Ошибка обращения к QIWI Касса
			 */
			$message = __( 'QIWI Kassa request error', 'woocommerce_payment_qiwi' );
			wc_add_wp_error_notices( new WP_Error(
				$exception->getCode(),
				$message . '<br>' . $exception->getMessage(),
				$exception
			) );
			return [ 'result' => 'fail' ];
		}

		// Return thank you redirect.
		$result = [
			'result'   => 'success',
			'success'  => $this->get_return_url( $order ),
			'redirect' => $this->bill_payments->getPayUrl( $bill, $this->get_return_url( $order ) ),
		];

		// Detect AJAX.
		$request = array_key_exists( 'HTTP_X_REQUESTED_WITH', $_SERVER ) ? wp_unslash( $_SERVER['HTTP_X_REQUESTED_WITH'] ) : ''; // phpcs:ignore WordPress.VIP
		if ( strtolower( $request ) === 'xmlhttprequest' ) {
			wp_send_json( apply_filters( 'woocommerce_payment_successful_result', $result, $order_id ) );
		}

		return $result;
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
			$this->log(

				/*
				 * translators:
				 * ru_RU: Создан возврат по счету
				 */
				__( 'Create bill refund', 'woocommerce_payment_qiwi' ),
				[
					'bill_id'   => $bill_id,
					'refund_id' => $refund_id,
					'amount'    => $amount,
					'currency'  => $order->get_currency(),
					'refund'    => $refund,
				]
			);
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

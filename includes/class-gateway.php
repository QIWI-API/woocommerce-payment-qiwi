<?php
/**
 * Interaction witch Woocommerce.
 *
 * @package woocommerce-payment-qiwi
 */

namespace Qiwi\Payment;

defined( 'ABSPATH' ) || exit;

use WC_Order;
use WC_Payment_Gateway;
use WC_Order_Refund;
use WP_REST_Server;
use WP_Error;
use Exception;
use ErrorException;
use Qiwi\Api\BillPayments;
use Qiwi\Api\BillPaymentsException;

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
	 * Gateway constructor.
	 */
	public function __construct() {
		$this->method_title       = __( 'QIWI', 'woocommerce_payment_qiwi' );
		$this->method_description = __( 'Processing via QIWI Universal Payment Protocol', 'woocommerce_payment_qiwi' );
		$this->icon               = plugins_url( 'assets/icon.png', __DIR__ );
		$this->notification_url   = site_url() . '/?wc-api=' . $this->id;

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', $this->method_title );
		$this->description = $this->get_option( 'description', $this->method_description );
		$this->secret_key  = $this->get_option( 'secret_key' );

		$options = [];
		$ca_path = dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'cacert.pem';
		if ( is_file( $ca_path ) ) {
			$options[ CURLOPT_SSL_VERIFYPEER ] = true;
			$options[ CURLOPT_SSL_VERIFYHOST ] = 2;
			$options[ CURLOPT_CAINFO         ] = $ca_path;
		}

		try {
			$this->bill_payments = new BillPayments( $this->secret_key, $options );
		} catch ( ErrorException $exception ) {
			wc_add_wp_error_notices(new WP_Error(
				$exception->getCode(),
				$exception->getMessage(),
				$exception
			));
		}

		add_action( 'woocommerce_update_options_payment_gateways', [ $this, 'process_admin_options' ] );
		add_action( 'woocommerce_order_refunded', [ $this, 'woocommerce_order_refunded' ], 10, 2 );
		add_action( 'woocommerce_order_status_cancelled', [ $this, 'woocommerce_order_status_cancelled' ] );
		add_action( "woocommerce_api_{$this->id}", [ $this, 'woocommerce_api' ] );

		$this->display_errors();

		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		}
	}

	/**
	 * Initialise settings.
	 */
	public function init_settings() {
		parent::init_settings();
		$this->settings['notification_url'] = $this->notification_url;
	}

	/**
	 * Set up config page fields.
	 */
	public function init_form_fields() {
		$this->form_fields = [
			'enabled'          => [
				'title'   => __( 'Turn On/off', 'woocommerce_payment_qiwi' ),
				'type'    => 'checkbox',
				'label'   => __( 'Activate the payment module for QIWI Universal Payment Protocol ', 'woocommerce_payment_qiwi' ),
				'default' => 'no',
			],
			'title'            => [
				'title'       => __( 'Title', 'woocommerce_payment_qiwi' ),
				'type'        => 'text',
				'description' => __( 'The name that the user sees when selecting the payment type', 'woocommerce_payment_qiwi' ),
				'default'     => $this->method_title,
			],
			'description'      => [
				'title'       => __( 'Description', 'woocommerce_payment_qiwi' ),
				'type'        => 'textarea',
				'description' => __( 'Description that the user sees when selecting the payment type', 'woocommerce_payment_qiwi' ),
				'default'     => $this->method_description,
			],
			'secret_key'       => [
				'title'       => __( 'Secret key', 'woocommerce_payment_qiwi' ),
				'type'        => 'password',
				'description' => __( 'Key to authenticate your requests', 'woocommerce_payment_qiwi' ),
			],
			'notification_url' => [
				'title'       => __( 'Notification URL', 'woocommerce_payment_qiwi' ),
				'type'        => 'text',
				'disabled'    => true,
				'description' => __( 'Set the field in the store settings', 'woocommerce_payment_qiwi' ),
				'default'     => $this->notification_url,
			],
			'success_url'      => [
				'title'       => __( 'Success URL', 'woocommerce_payment_qiwi' ),
				'type'        => 'select',
				'description' => __( 'Page transition on successful payment (successURL)', 'woocommerce_payment_qiwi' ),
				'options'     => [
					'success'  => __( 'Page "Order received" from WooCommerce', 'woocommerce_payment_qiwi' ),
					'checkout' => __( 'Checkout page from WooCommerce', 'woocommerce_payment_qiwi' ),
				],
			],
		];
	}

	/**
	 * Refund QIWI bill.
	 *
	 * @param string $order_id The order ID.
	 * @param string $refund_id The refund ID.
	 */
	public function woocommerce_order_refunded( $order_id, $refund_id ) {
		$self    = new self();
		$order   = wc_get_order( $order_id );
		$refunds = $order->get_refunds();
		$refund  = null;
		if ( $refunds ) {
			/**
			 * The order refund.
			 *
			 * @var WC_Order_Refund $refund
			 */
			foreach ( $refunds as $refund ) {
				if ( $refund->get_id() === $refund_id ) {
					$refund = wc_get_order( $refund_id );
					break;
				}
			}
		}
		$bill_id = $order->get_transaction_id( $order_id );
		try {
			$bill_refund_id = $self->bill_payments->generateId();
			$self->bill_payments->refund(
				$bill_id,
				$bill_refund_id,
				$refund->get_amount(),
				$refund->get_currency()
			);
			/* translators: Take note of refund ID - a uuid v4 string. */
			$order->add_order_note( sprintf( __( 'Make refund ID # %1$s', 'woocommerce_payment_qiwi' ), $bill_refund_id ) );
		} catch ( Exception $exception ) {
			wc_add_wp_error_notices( new \WP_Error(
				$exception->getCode(),
				$exception->getMessage(),
				$exception
			) );
		}
	}

	/**
	 * Cancel QIWI bill.
	 *
	 * @param string $order_id The order ID.
	 */
	public function woocommerce_order_status_cancelled( $order_id ) {
		$self    = new self();
		$order   = wc_get_order( $order_id );
		$bill_id = $order->get_transaction_id( $order_id );
		try {
			$bill = $self->bill_payments->getBillInfo( $bill_id );
			if ( 'WAITING' === $bill['status']['value'] ) {
				$bill = $self->bill_payments->cancelBill( $bill_id );
				wp_redirect( $bill['payUrl'], 301 );
			}
		} catch ( BillPaymentsException $exception ) {
			wc_add_wp_error_notices(new \WP_Error(
				$exception->getCode(),
				$exception->getMessage(),
				$exception
			));
		}
	}

	/**
	 * Process QIWI notification.
	 */
	public function woocommerce_api() {
		$headers = headers_list();
		$body    = WP_REST_Server::get_raw_data();

		if (
			! is_array( $headers ) ||
			! array_key_exists( 'X-Api-Signature-SHA256', $headers ) ||
			! is_string( $body )
		) {
			wp_send_json( [
				'error'  => 1,
				'notice' => __( 'Expected invoice payment notification.', 'woocommerce_payment_qiwi' ),
			], 400 );
		}

		$notice = json_decode( $body, true );

		if ( ! $this->bill_payments->checkNotificationSignature(
			$headers['X-Api-Signature-SHA256'],
			$notice,
			$this->secret_key
		) ) {
			wp_send_json( [
				'error'  => 1,
				'notice' => __( 'Invalid notification signature.', 'woocommerce_payment_qiwi' ),
			], 403 );
		}

		$orders = wc_get_orders([
			'limit'          => 1,
			'transaction_id' => $notice['bill']['billId'],
		]);

		if (
			! is_array( $orders ) ||
			empty( $orders )
		) {
			wp_send_json( [
				'error'  => 1,
				'notice' => __( 'Order not found.', 'woocommerce_payment_qiwi' ),
			], 404 );
		}

		$order = reset( $orders );

		switch ( $notice['bill']['status']['value'] ) {
			case 'PAID':
				$order->update_status( 'completed' );
				break;
			case 'rejected':
				$order->update_status( 'failed', __( 'Invoice rejected by customer.', 'woocommerce_payment_qiwi' ) );
				break;
			case 'unpaid':
				$order->update_status( 'failed', __( 'Error when making a payment. The bill is not paid.', 'woocommerce_payment_qiwi' ) );
				break;
			case 'expired':
				$order->update_status( 'failed', __( 'Payment session expired. The bill is not paid.', 'woocommerce_payment_qiwi' ) );
				break;
			default:
				$order->add_order_note( __( 'Order payment not done. Order status Unknown.', 'woocommerce_payment_qiwi' ) );
		}
		$order->save();
		wp_send_json( [ 'error' => 0 ], 200 );
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param string $order_id The order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		global $woocommerce;

		$order = new WC_Order( $order_id );

		try {
			$bill_id = $this->bill_payments->generateId();
			$bill    = $this->bill_payments->createBill( $bill_id, [
				'amount'             => $order->get_total(),
				'currency'           => $order->get_currency(),
				'expirationDateTime' => $this->bill_payments->getLifetimeByDay(),
				'phone'              => $order->get_billing_phone(),
				'email'              => $order->get_billing_email(),
				'account'            => $order->get_user_id(),
				'successUrl'         => $this->get_return_url( $order ),
			]);
		} catch ( Exception $exception ) {
			wc_add_wp_error_notices(new WP_Error(
				$exception->getCode(),
				$exception->getMessage(),
				$exception
			));
			return [ 'result' => 'fail' ];
		}

		// Mark as on-hold (we're awaiting the cheque).
		$order->update_status( 'on-hold', __( 'Awaiting payment', 'woocommerce_payment_qiwi' ) );

		// Reduce stock levels.
		wc_reduce_stock_levels( $order->get_id() );

		// Remove cart.
		$woocommerce->cart->empty_cart();

		// Return thank you redirect.
		return [
			'result'   => 'success',
			'redirect' => $this->bill_payments->getPayUrl( $bill, $this->get_return_url( $order ) ),
		];
	}
}

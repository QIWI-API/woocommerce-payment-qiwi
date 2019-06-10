/**
 * QIWI Kassa popup payment.
 *
 * @package woocommerce-payment-qiwi
 */

jQuery( function ($) {

	// wc_checkout_params is required to continue, ensure the object exists
	if ( typeof wc_checkout_params === 'undefined' ) {
		return false;
	}

	// QiwiCheckout is required to continue, ensure the object exists
	if ( typeof QiwiCheckout === 'undefined' ) {
		return false;
	}

	let qiwi_popup = {
		$order_review: $( '#order_review' ),
		$checkout_form: $( 'form.checkout' ),
		paymentOrderNoDefault: true,
		init: function () {
			if ( $( document.body ).hasClass( 'woocommerce-order-pay' ) ) {
				this.$order_review.off( 'submit' );
				this.$order_review.on( 'submit', qiwi_popup.paymentOrder );
			}

			// change checkout logic for popup
			this.$checkout_form.on( 'checkout_place_order_qiwi', qiwi_popup.placeOrder);
		},
		paymentOrder: function (event) {
			if (qiwi_popup.paymentOrderNoDefault) {
				event.preventDefault();

				var $form = $( this );
				$form.addClass( 'processing' );

				qiwi_popup.blockOnSubmit( $form );

				$.ajaxSetup( { dataFilter: qiwi_popup.dataFilter } );

				$.ajax( {
					type:		'POST',
					url:		window.location,
					data:		$form.serialize(),
					dataType:	'json',
					success:	qiwi_popup.submitSuccess,
					error:		function () {
						qiwi_popup.paymentOrderNoDefault = false;
						$form.trigger('submit');
					}
				} );
			}
		},
		placeOrder: function () {
			var $form = $( this );
			$form.addClass( 'processing' );

			qiwi_popup.blockOnSubmit( $form );

			$.ajaxSetup( { dataFilter: qiwi_popup.dataFilter } );

			$.ajax( {
				type:		'POST',
				url:		wc_checkout_params.checkout_url,
				data:		$form.serialize(),
				dataType:	'json',
				success:	qiwi_popup.submitSuccess,
				error:		qiwi_popup.submitError
			} );

			return false;
		},
		dataFilter: function( raw_response, dataType ) {
			// We only want to work with JSON
			if ( 'json' !== dataType ) {
				return raw_response;
			}

			if ( qiwi_popup.is_valid_json( raw_response ) ) {
				return raw_response;
			} else {
				// Attempt to fix the malformed JSON
				var maybe_valid_json = raw_response.match( /{"result.*}/ );

				if ( null === maybe_valid_json ) {
					console.log( 'Unable to fix malformed JSON' );
				} else if ( qiwi_popup.is_valid_json( maybe_valid_json[0] ) ) {
					console.log( 'Fixed malformed JSON. Original:' );
					console.log( raw_response );
					raw_response = maybe_valid_json[0];
				} else {
					console.log( 'Unable to fix malformed JSON' );
				}
			}

			return raw_response;
		},
		submitError: function( jqXHR, textStatus, errorThrown ) {
			qiwi_popup.submit_error( '<div class="woocommerce-error">' + errorThrown + '</div>' );
		},
		submitSuccess: function( result ) {
			try {
				if ( 'success' === result.result ) {
					// Shop popup
					QiwiCheckout.openInvoice( {
						payUrl: result.redirect
					} ).then( function () {
						if ( -1 === result.success.indexOf( 'https://' ) || -1 === result.success.indexOf( 'http://' ) ) {
							window.location = result.success;
						} else {
							window.location = decodeURI( result.success );
						}
					} ).catch( function () {
						qiwi_popup.submit_error( '<div class="woocommerce-error">' + wc_checkout_params.i18n_checkout_error + '</div>' );
					} )
				} else if ( 'failure' === result.result ) {
					throw 'Result failure';
				} else {
					throw 'Invalid response';
				}
			} catch( err ) {
				// Reload page
				if ( true === result.reload ) {
					window.location.reload();
					return;
				}

				// Trigger update in case we need a fresh nonce
				if ( true === result.refresh ) {
					$( document.body ).trigger( 'update_checkout' );
				}

				// Add new errors
				if ( result.messages ) {
					qiwi_popup.submit_error( result.messages );
				} else {
					qiwi_popup.submit_error( '<div class="woocommerce-error">' + wc_checkout_params.i18n_checkout_error + '</div>' );
				}
			}
		},
		submit_error: function( error_message ) {
			$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
			qiwi_popup.$checkout_form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' );
			qiwi_popup.$checkout_form.removeClass( 'processing' ).unblock();
			qiwi_popup.$checkout_form.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
			qiwi_popup.scroll_to_notices();
			$( document.body ).trigger( 'checkout_error' );
		},
		scroll_to_notices: function() {
			var scrollElement = $( '.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout' );

			if ( ! scrollElement.length ) {
				scrollElement = $( '.form.checkout' );
			}
			$.scroll_to_notices( scrollElement );
		},
		blockOnSubmit: function( $form ) {
			var form_data = $form.data();

			if ( 1 !== form_data['blockUI.isBlocked'] ) {
				$form.block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});
			}
		},
		is_valid_json: function( raw_json ) {
			try {
				var json = $.parseJSON( raw_json );

				return ( json && 'object' === typeof json );
			} catch ( e ) {
				return false;
			}
		},
	};

	qiwi_popup.init();

});

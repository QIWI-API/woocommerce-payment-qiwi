/**
 * QIWI Kassa invoices sync.
 *
 * @package woocommerce-payment-qiwi
 */

jQuery( function ( $ ) {

	let qiwi_sync = {
		window: $( window ),
		button: $( '#page-sync button' ),
		progress: $( '#page-sync progress' ),
		output: $( '#page-sync output' ),
		list: [ ],
		index: 0,
		add: function ( text, ...params ) {
			for ( let key in params ) {
				text = text.replace( "{" + key + "}", params[key] );
			}

			qiwi_sync.output.prepend( $( '<p>', { text: text } ) );
		},
		end: function ( text ) {
			qiwi_sync.button.toggleClass('hidden');
			qiwi_sync.progress.toggleClass('hidden');
			qiwi_sync.add( text );
			qiwi_sync.window.remove( 'beforeunload' );
		},
		request: function ( id, success ) {
			$.ajax( {
				type:     'POST',
				url:      woocommerce_payment_qiwi_sync.url,
				data:     {
					nonce:    woocommerce_payment_qiwi_sync.nonce,
					action:   'woocommerce_payment_qiwi_sync',
					order_id: id,
				},
				dataType: 'json',
				success:  function (data) {
					if (data.nonce) {
						woocommerce_payment_qiwi_sync.nonce = data.nonce;
					}

					if (data.message) {
						if (data.message === 'success') {
							qiwi_sync.output.first().text(
								qiwi_sync.output.first().text() + woocommerce_payment_qiwi_sync.success
							);
						} else {
							qiwi_sync.add(data.message);
						}
					}

					if (data.list) {
						qiwi_sync.list = data.list;
						qiwi_sync.index = 0;
					}

					if (success) {
						success();
					}
				},
				error:    function () {
					qiwi_sync.end(woocommerce_payment_qiwi_sync.error);
				}
			} );
		},
		each: function ( ) {
			if (qiwi_sync.index === qiwi_sync.list.length) {
				qiwi_sync.end( woocommerce_payment_qiwi_sync.end );

				return;
			}

			qiwi_sync.progress.attr( 'value', qiwi_sync.index / qiwi_sync.list.length * 100 );
			qiwi_sync.add( woocommerce_payment_qiwi_sync.single, qiwi_sync.index + 1, qiwi_sync.list.length, qiwi_sync.list[qiwi_sync.index] );
			qiwi_sync.request(qiwi_sync.list[qiwi_sync.index], qiwi_sync.each);
			qiwi_sync.index++;
		},
		beforeunload: function ( ) {
			return woocommerce_payment_qiwi_sync.beforeunload;
		},
		sync: function ( event ) {
			event.preventDefault( );
			qiwi_sync.button.toggleClass( 'hidden' );
			qiwi_sync.progress.toggleClass( 'hidden' );
			qiwi_sync.progress.attr( 'value', null );
			qiwi_sync.output.empty();
			qiwi_sync.window.on( 'beforeunload', qiwi_sync.beforeunload );
			qiwi_sync.request( null, qiwi_sync.each );
		},
		init: function ( ) {
			qiwi_sync.button.on( 'click', qiwi_sync.sync );
		},
	};

	qiwi_sync.init( );

} );

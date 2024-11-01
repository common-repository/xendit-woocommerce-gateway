/* global wc_xendit_params */
Xendit.setPublishableKey( wc_xendit_params.key );

jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle Xendit payment forms.
	 */
	var wc_xendit_form = {

		/**
		 * Initialize event handlers and UI state.
		 */
		init: function() {
			// checkout page
			if ( $( 'form.woocommerce-checkout' ).length ) {
				this.form = $( 'form.woocommerce-checkout' );
			}

			$( 'form.woocommerce-checkout' )
				.on(
					'checkout_place_order_xendit',
					this.onSubmit
				);

			// pay order page
			if ( $( 'form#order_review' ).length ) {
				this.form = $( 'form#order_review' );
			}

			$( 'form#order_review' )
				.on(
					'submit',
					this.onSubmit
				);

			// add payment method page
			if ( $( 'form#add_payment_method' ).length ) {
				this.form = $( 'form#add_payment_method' );
			}

			$( 'form#add_payment_method' )
				.on(
					'submit',
					this.onSubmit
				);

			$( document )
				.on(
					'change',
					'#wc-xendit-cc-form :input',
					this.onCCFormChange
				)
				.on(
					'xenditError',
					this.onError
				)
				.on(
					'checkout_error',
					this.clearToken
				)
				.ready(function () {
					$('body').append('<div class="overlay" style="display: none;"></div>' +
		            	'<div id="three-ds-container" style="display: none;">' +
		                	'<iframe height="450" width="550" id="sample-inline-frame" name="sample-inline-frame"> </iframe>' +
		            	'</div>');

					$('.entry-content .woocommerce').prepend('<div id="woocommerce-error-custom-my" class="woocommerce-error" style="display:none"></div>');


					$('.overlay').css({'position': 'absolute','top': '0','left': '0','height': '100%','width': '100%','background-color': 'rgba(0,0,0,0.5)','z-index': '10'});
					$('#three-ds-container').css({'width': '550px','height': '450px','line-height': '200px','position': 'fixed','top': '25%','left': '40%','margin-top': '-100px','margin-left': '-150px','background-color': '#ffffff','border-radius': '5px','text-align': 'center','z-index': '9999'});
				});
		},

		isXenditChosen: function() {
			return $( '#payment_method_xendit' ).is( ':checked' ) && ( ! $( 'input[name="wc-xendit-payment-token"]:checked' ).length || 'new' === $( 'input[name="wc-xendit-payment-token"]:checked' ).val() );
		},

		hasToken: function() {
			return 0 < $( 'input.xendit_token' ).length;
		},

		block: function() {
			wc_xendit_form.form.block({
				message: null,
				overlayCSS: {
					background: '#000',
					opacity: 0.5
				}
			});
		},

		unblock: function() {
			wc_xendit_form.form.unblock();
		},

		onError: function( e, response ) {
			var failure_reason
			if(typeof response != 'undefined') {
				failure_reason = JSON.stringify(response.err.error_code || response.err.message, null, 4)
			} else {
				failure_reason = JSON.stringify(e.failure_reason || e.status, null, 4)
			}
			$('#three-ds-container').hide();
			$('.overlay').hide();
			$('#woocommerce-error-custom-my').html('Failed. Failure Reason: ' + failure_reason);
			$('#woocommerce-error-custom-my').show();
			$('html, body').animate({
				scrollTop: $(".entry-content").offset().top
			}, 2000);
			wc_xendit_form.unblock();
			wc_xendit_form.clearToken();
		},

		onSubmit: function( e ) {

			if ( wc_xendit_form.isXenditChosen() && ! wc_xendit_form.hasToken()) {
				e.preventDefault();
				wc_xendit_form.block();

				// #xendit- prefix comes from wc-gateway-xendit->id field
				var card       = $( '#xendit-card-number' ).val().replace(/\s/g, ''),
					cvn        = $( '#xendit-card-cvc' ).val(),
					expires    = $( '#xendit-card-expiry' ).payment( 'cardExpiryVal' ),
					first_name = $( '#billing_first_name' ).length ? $( '#billing_first_name' ).val() : wc_xendit_params.billing_first_name,
					last_name  = $( '#billing_last_name' ).length ? $( '#billing_last_name' ).val() : wc_xendit_params.billing_last_name,
					data       = {
						"card_number"   : card,
						"card_exp_month": String(expires.month).length === 1 ? '0' + String(expires.month) : String(expires.month),
						"card_exp_year" : String(expires.year),
						"card_cvn"      : cvn,
						"is_multiple_use": true
					};

				wc_xendit_form.form.append( "<input type='hidden' class='year' name='year' value='" + data.card_exp_year + "'/>" );
				wc_xendit_form.form.append( "<input type='hidden' class='month' name='month' value='" + data.card_exp_month + "'/>" );
				wc_xendit_form.form.append( "<input type='hidden' class='card_cvn' name='card_cvn' value='" + data.card_cvn + "'/>" );

				Xendit.card.createToken( data, wc_xendit_form.onTokenizationResponse );
				// Prevent form submitting
				return false;
			}
		},

		onCCFormChange: function() {
			$( '.wc-xendit-error, .xendit_token', '.xendit_3ds_url', '.masked_card_number').remove();
		},

		onTokenizationResponse: function(err, response) {
			if (err) {
				$( document ).trigger( 'xenditError', { err: err } );

				return;
			}
			var token_id = response.id;

			wc_xendit_form.form.append( "<input type='hidden' class='xendit_token' name='xendit_token' value='" + token_id + "'/>" );
			wc_xendit_form.form.append( "<input type='hidden' class='masked_card_number' name='masked_card_number' value='" + response.masked_card_number + "'/>" );
			wc_xendit_form.form.submit();

			return;
		},

		clearToken: function() {
			$( '.xendit_token' ).remove();
			$( '.xendit_3ds_url' ).remove();
			$( '.masked_card_number' ).remove();
		}
	};

	wc_xendit_form.init();
} );

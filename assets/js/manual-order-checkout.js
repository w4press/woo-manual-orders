/*global woo_manual_order_params */
jQuery( function( $ ) {
    window.woo_manual_order_checkout = {
        init: function(){
            var _this = this;
            
            $(':input.wc-customer-search').on('change', function (e) {
                var $this = $(this);
                _this.customer_change($this);
            });

            // trigger on change product
            $(':input.wc-product-search').on('select2:select', function (e) {
                var $this = $(this), data = e.params.data;

                if( !$this.val() ) return;

                $this.val(null).trigger('change'); // clear selected value
                
                if( !data.class){
                    var link = data.url + '&width=' + $( window ).width()*(0.8) + '&height=' + $( window ).height()*(0.8);
                    
                    tb_show( data.text, link, false );
                } else {
                    
                    var args = {
                        product_id: data.id,
                        quantity: 1,
                    };

                    // Ajax action.
                    $.post( wc_add_to_cart_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'add_to_cart' ), args, function( response ) {
                        if ( ! response ) {
                            return;
                        }
                        
                        $this.addClass('added_to_cart'); // do not display button view cart

                        // Update checkout total
                        $( document.body ).trigger( 'update_checkout' );
            
                        // Trigger event so themes can refresh other areas.
                        $( document.body ).trigger( 'added_to_cart', [ response.fragments, response.cart_hash, $this ] );

                        _this.scroll_to( $('form.woocommerce-cart-form') );
                    });
                }
            });
        },
        customer_change: function(el){
            var _this = this;
            $.ajax({
                type:		'POST',
                url:         woo_manual_order_params.ajax_url,
                data: {
                    action       : 'woocommerce_manual_order_assign_customer',
                    security     : woo_manual_order_params.assign_customer_nonce,
                    customer_id  : $('#wc-customer-search').val(),
                },
                dataType:   'json',
                success:	function( data ) {
    
                    $('#customer_details').html(data.customer_details);
    
                    if( false == data.is_empty_cart ){
                        // Update checkout total
                        $( document.body ).trigger( 'update_checkout' );
                    }

                    _this.scroll_to($('#customer_details'));

                    $( 'input#createaccount' ).change( _this.toggle_create_account ).change();

                },
                error:	function( jqXHR, status, err ) {
                    console.log(err);
                }
            });
        },
        
        toggle_create_account: function() {
            
			$( 'div.create-account' ).hide();

			if ( $( this ).is( ':checked' ) ) {
				// Ensure password is not pre-populated.
				$( '#account_password' ).val( '' ).change();
				$( 'div.create-account' ).slideDown();
			}
		},
        scroll_to: function( elem ){
			if( ! $.scroll_to_notices ) { // support woo <= 3.2.6.0
				// Scroll to top
				$( 'html, body' ).animate( {
					scrollTop: ( elem.offset().top - 100 )
				}, 1000 );
			} else {
				$.scroll_to_notices( elem );
			}
		},
    };
    
    woo_manual_order_checkout.init();
    
});

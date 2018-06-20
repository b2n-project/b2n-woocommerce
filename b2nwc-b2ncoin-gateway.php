<?php
/*
B2Ncoin for WooCommerce
https://github.com/b2n-project/b2n-woocommerce/
*/


//---------------------------------------------------------------------------
add_action('plugins_loaded', 'B2NWC__plugins_loaded__load_B2Ncoin_gateway', 0);
//---------------------------------------------------------------------------

//###########################################################################
// Hook payment gateway into WooCommerce

function B2NWC__plugins_loaded__load_B2Ncoin_gateway ()
{

    if (!class_exists('WC_Payment_Gateway'))
    	// Nothing happens here because WooCommerce is not loaded
    	return;

	//=======================================================================
	/**
	 * B2Ncoin Payment Gateway
	 *
	 * Provides a B2Ncoin Payment Gateway
	 *
	 * @class 		B2NWC_B2Ncoin
	 * @extends		WC_Payment_Gateway
	 * @version
	 * @package
	 * @author 		KittyCatTech
	 */
	class B2NWC_B2Ncoin extends WC_Payment_Gateway
	{
		//-------------------------------------------------------------------
	    /**
	     * Constructor for the gateway.
	     *
	     * @access public
	     * @return void
	     */
		public function __construct()
		{
			$this->id				= 'B2Ncoin';
			$this->icon 			= plugins_url('/images/b2n_buyitnow_32x.png', __FILE__);	// 32 pixels high
			$this->has_fields 		= false;
			$this->method_title     = __( 'B2Ncoin', 'woocommerce' );

			// Load B2NWC settings.
			$b2bwc_settings = B2NWC__get_settings ();
			$this->service_provider = $b2bwc_settings['service_provider']; // This need to be before $this->init_settings otherwise it generate PHP Notice: "Undefined property: B2NWC_B2Ncoin::$service_provider" down below.

			// Load the form fields.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->title 		= $this->settings['title'];	// The title which the user is shown on the checkout – retrieved from the settings which init_settings loads.
			$this->B2Ncoin_addr_merchant = $this->settings['B2Ncoin_addr_merchant'];	// Forwarding address where all product payments will aggregate.
			
			$this->confs_num = $b2bwc_settings['confs_num'];  //$this->settings['confirmations'];
			$this->description 	= $this->settings['description'];	// Short description about the gateway which is shown on checkout.
			$this->instructions = $this->settings['instructions'];	// Detailed payment instructions for the buyer.
			$this->instructions_multi_payment_str  = __('You may send payments from multiple accounts to reach the total required.', 'woocommerce');
			$this->instructions_single_payment_str = __('You must pay in a single payment in full.', 'woocommerce');

			// Actions
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) )
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			else
				add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options')); // hook into this action to save options in the backend

		    add_action('woocommerce_thankyou_' . $this->id, array($this, 'B2NWC__thankyou_page')); // hooks into the thank you page after payment

	    	// Customer Emails
		    add_action('woocommerce_email_before_order_table', array($this, 'B2NWC__email_instructions'), 10, 2); // hooks into the email template to show additional details

			// Validate currently set currency for the store. Must be among supported ones.
			if (!B2NWC__is_gateway_valid_for_use()) $this->enabled = false;
	    }
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	    /**
	     * Check if this gateway is enabled and available for the store's default currency
	     *
	     * @access public
	     * @return bool
	     */
	    function is_gateway_valid_for_use(&$ret_reason_message=NULL)
	    {
	    	$valid = true;

	    	//----------------------------------
	    	// Validate settings
	    	if (!$this->service_provider)
	    	{
	    		$reason_message = __("B2Ncoin Service Provider is not selected", 'woocommerce');
	    		$valid = false;
	    	}
	    	
	    	else if ($this->service_provider=='local_wallet')
	    	{
	    		$wallet_api = New ForkNoteWalletd("http://127.0.0.1:19070");
	    		$b2bwc_settings = B2NWC__get_settings();
          		$address = $b2bwc_settings['address'];
	    		if (!$address)
	    		{
		    		$reason_message = __("Please specify Wallet Address in B2Ncoin plugin settings.", 'woocommerce');
		    		$valid = false;
		    	}
	    		// else if (!preg_match ('/^xpub[a-zA-Z0-9]{98}$/', $address))
	    		// {
		    	// 	$reason_message = __("B2Ncoin Address ($address) is invalid. Must be 98 characters long, consisting of digits and letters.", 'woocommerce');
		    	// 	$valid = false;
		    	// }
		    	else if ($wallet_api->getBalance($address) === false)
		    	{
		    		$reason_message = __("B2Ncoin address is not found in wallet.", 'woocommerce');
		    		$valid = false;
		    	}
	    	}

	    	if (!$valid)
	    	{
	    		if ($ret_reason_message !== NULL)
	    			$ret_reason_message = $reason_message;
	    		return false;
	    	}
	    	//----------------------------------

	    	//----------------------------------
	    	// Validate connection to exchange rate services

	   		$store_currency_code = get_woocommerce_currency();
	   		if ($store_currency_code != 'B2N')
	   		{
					$currency_rate = B2NWC__get_exchange_rate_per_B2Ncoin ($store_currency_code, 'getfirst', false);
					if (!$currency_rate)
					{
						$valid = false;

						// Assemble error message.
						$error_msg = "ERROR: Cannot determine exchange rates (for '$store_currency_code')! {{{ERROR_MESSAGE}}} Make sure your PHP settings are configured properly and your server can (is allowed to) connect to external WEB services via PHP.";
						$extra_error_message = "";
						$fns = array ('file_get_contents', 'curl_init', 'curl_setopt', 'curl_setopt_array', 'curl_exec');
						$fns = array_filter ($fns, 'B2NWC__function_not_exists');
						$extra_error_message = "";
						if (count($fns))
							$extra_error_message = "The following PHP functions are disabled on your server: " . implode (", ", $fns) . ".";

						$reason_message = str_replace('{{{ERROR_MESSAGE}}}', $extra_error_message, $error_msg);

		    		if ($ret_reason_message !== NULL)
		    			$ret_reason_message = $reason_message;
		    		return false;
					}
				}

	     	return true;
	    	//----------------------------------
	    }
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	    /**
	     * Initialise Gateway Settings Form Fields
	     *
	     * @access public
	     * @return void
	     */
	    function init_form_fields()
	    {
		    // This defines the settings we want to show in the admin area.
		    // This allows user to customize payment gateway.
		    // Add as many as you see fit.
		    // See this for more form elements: http://wcdocs.woothemes.com/codex/extending/settings-api/

	    	//-----------------------------------
	    	// Assemble currency ticker.
	   		$store_currency_code = get_woocommerce_currency();
	   		if ($store_currency_code == 'B2N')
	   			$currency_code = 'USD';
	   		else
	   			$currency_code = $store_currency_code;

				$currency_ticker = B2NWC__get_exchange_rate_per_B2Ncoin ($currency_code, 'getfirst', true);
	    	//-----------------------------------

	    	//-----------------------------------
	    	// Payment instructions
	    	$payment_instructions = '
<table class="b2bwc-payment-instructions-table" id="b2bwc-payment-instructions-table">
  <tr class="bpit-table-row">
    <td colspan="2">' . __('Please send your B2Ncoin payment as follows:', 'woocommerce') . '</td>
  </tr>
  <tr class="bpit-table-row">
    <td style="vertical-align:middle;" class="bpit-td-name bpit-td-name-amount">
      ' . __('Amount', 'woocommerce') . ' (<strong>B2N</strong>):
    </td>
    <td class="bpit-td-value bpit-td-value-amount">
      <div style="border:1px solid #FCCA09;padding:2px 6px;margin:2px;background-color:#FCF8E3;border-radius:4px;color:#CC0000;font-weight: bold;font-size: 120%;">
      	{{{B2NCOINS_AMOUNT}}}
      </div>
    </td>
  </tr>
    <tr class="bpit-table-row">
    <td style="vertical-align:middle;" class="bpit-td-name bpit-td-name-b2naddr">
      ' . __('Payment ID:', 'woocommerce') . '
    </td>
    <td class="bpit-td-value bpit-td-value-b2naddr">
      <div style="border:1px solid #FCCA09;padding:2px 6px;margin:2px;background-color:#FCF8E3;border-radius:4px;color:#555;font-weight: bold;font-size: 120%;">
        {{{B2NCOINS_PAYMENTID}}}
      </div>
    </td>
  </tr>
  <tr class="bpit-table-row">
    <td style="vertical-align:middle;" class="bpit-td-name bpit-td-name-b2naddr">
      ' . __('Address:', 'woocommerce') . '
    </td>
    <td class="bpit-td-value bpit-td-value-b2naddr">
      <div style="border:1px solid #FCCA09;padding:2px 6px;margin:2px;background-color:#FCF8E3;border-radius:4px;color:#555;font-weight: bold;font-size: 120%;">
        {{{B2NCOINS_ADDRESS}}}
      </div>
    </td>
  </tr>
</table>

' . __('Please note:', 'woocommerce') . '
<ol class="bpit-instructions">
    <li>' . __('You must make a payment within 1 hour, or your order will be cancelled', 'woocommerce') . '</li>
    <li>' . __('As soon as your payment is received in full you will receive email confirmation with order delivery details.', 'woocommerce') . '</li>
    <li>{{{EXTRA_INSTRUCTIONS}}}</li>
</ol>
';

			$payment_instructions = trim ($payment_instructions);

	    	$payment_instructions_description = '
						  <p class="description" style="width:50%;float:left;width:49%;">
					    	' . __( 'Specific instructions given to the customer to complete B2Ncoins payment.<br />You may change it, but make sure these tags will be present: <b>{{{B2NCOINS_AMOUNT}}}</b>, <b>{{{B2NCOINS_PAYMENTID}}}</b>, <b>{{{B2NCOINS_ADDRESS}}}</b> and <b>{{{EXTRA_INSTRUCTIONS}}}</b> as these tags will be replaced with customer - specific payment details.', 'woocommerce' ) . '
						  </p>
						  <p class="description" style="width:50%;float:left;width:49%;">
					    	Payment Instructions, original template (for reference):<br />
					    	<textarea rows="2" onclick="this.focus();this.select()" readonly="readonly" style="width:100%;background-color:#f1f1f1;height:4em">' . $payment_instructions . '</textarea>
						  </p>
					';
				$payment_instructions_description = trim ($payment_instructions_description);
	    	//-----------------------------------

	    	$this->form_fields = array(
				'enabled' => array(
								'title' => __( 'Enable/Disable', 'woocommerce' ),
								'type' => 'checkbox',
								'label' => __( 'Enable B2Ncoin', 'woocommerce' ),
								'default' => 'yes'
							),
				'title' => array(
								'title' => __( 'Title', 'woocommerce' ),
								'type' => 'text',
								'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
								'default' => __( 'B2Ncoin Payment', 'woocommerce' )
							),

				'B2Ncoin_addr_merchant' => array(
								'title' => __( 'B2Ncoin Address', 'woocommerce' ),
								'type' => 'text',
								'css'     => '',
								'disabled' => false,
								'description' => __( 'Your B2Ncoin address where customer sends you payment for the product. It must be in your walletd container.', 'woocommerce' ),
								'default' => '',
							),

				'description' => array(
								'title' => __( 'Customer Message', 'woocommerce' ),
								'type' => 'text',
								'description' => __( 'Initial instructions for the customer at checkout screen', 'woocommerce' ),
								'default' => __( 'Please proceed to the next screen to see necessary payment details.', 'woocommerce' )
							),
				'instructions' => array(
								'title' => __( 'Payment Instructions (HTML)', 'woocommerce' ),
								'type' => 'textarea',
								'description' => $payment_instructions_description,
								'default' => $payment_instructions,
							),
				);
	    }

		//-------------------------------------------------------------------
		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 *
		 * @access public
		 * @return void
		 */
		public function admin_options()
		{
			$validation_msg = "";
			$store_valid    = B2NWC__is_gateway_valid_for_use ($validation_msg);

			// After defining the options, we need to display them too; thats where this next function comes into play:
	    	?>
	    	<h3><?php _e('B2Ncoin Payment', 'woocommerce'); ?></h3>
	    	<p>
	    		<?php _e('Allows WooCommerce to accept payments in B2Ncoin.',
	    				'woocommerce'); ?>
	    	</p>
	    	<?php
	    		echo $store_valid ? ('<p style="border:1px solid #DDD;padding:5px 10px;font-weight:bold;color:#004400;background-color:#CCFFCC;">' .
            __('B2Ncoin payment gateway is operational','woocommerce') .
            '</p>') : ('<p style="border:1px solid #DDD;padding:5px 10px;font-weight:bold;color:#EE0000;background-color:#FFFFAA;">' .
            __('B2Ncoin payment gateway is not operational (try to re-enter and save B2Ncoin Plugin settings): ','woocommerce') . $validation_msg . '</p>');
	    	?>
	    	<table class="form-table">
	    	<?php
	    		// Generate the HTML For the settings form.
	    		$this->generate_settings_html();
	    	?>
			</table><!--/.form-table-->
	    	<?php
	    }
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	  // Hook into admin options saving.
    public function process_admin_options()
    {
    	// Call parent
    	parent::process_admin_options();

      return;
    }
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	    /**
	     * Process the payment and return the result
	     *
	     * @access public
	     * @param int $order_id
	     * @return array
	     */
		function process_payment ($order_id)
		{
      $b2bwc_settings = B2NWC__get_settings ();
			$order = new WC_Order ($order_id);

			// TODO: Implement CRM features within store admin dashboard
			$order_meta = array();
			$order_meta['b2n_order'] = $order;
			$order_meta['b2n_items'] = $order->get_items();
			$order_meta['b2n_b_addr'] = $order->get_formatted_billing_address();
			$order_meta['b2n_s_addr'] = $order->get_formatted_shipping_address();
			$order_meta['b2n_b_email'] = $order->billing_email;
			$order_meta['b2n_currency'] = $order->order_currency;
			$order_meta['b2n_settings'] = $b2bwc_settings;
			$order_meta['b2n_store'] = plugins_url ('' , __FILE__);


			//-----------------------------------
			// Save B2Ncoin payment info together with the order.
			// Note: this code must be on top here, as other filters will be called from here and will use these values ...
			//
			// Calculate realtime B2Ncoin price (if exchange is necessary)

			$exchange_rate = B2NWC__get_exchange_rate_per_B2Ncoin (get_woocommerce_currency(), 'getfirst');
			/// $exchange_rate = B2NWC__get_exchange_rate_per_B2Ncoin (get_woocommerce_currency(), $this->exchange_rate_retrieval_method, $this->exchange_rate_type);
			if (!$exchange_rate)
			{
				$msg = 'ERROR: Cannot determine B2Ncoin exchange rate. Possible issues: store server does not allow outgoing connections, exchange rate servers are blocking incoming connections or down. ' .
					   'You may avoid that by setting store currency directly to B2Ncoin(B2N)';
      			B2NWC__log_event (__FILE__, __LINE__, $msg);
      			exit ('<h2 style="color:red;">' . $msg . '</h2>');
			}

			$order_total_in_b2n   = ($order->get_total() / $exchange_rate);
			if (get_woocommerce_currency() != 'B2N')
				// @TODO Apply exchange rate multiplier only for stores with non-B2Ncoin default currency.
				$order_total_in_b2n = $order_total_in_b2n;

			$order_total_in_b2n   = sprintf ("%.2f", $order_total_in_b2n); // round price to 2 Decimal Places

  		$B2Ncoins_address = false;

  		$order_info =
  			array (
  				'order_meta'							=> $order_meta,
  				'order_id'								=> $order_id,
  				'order_total'			    	 	=> $order_total_in_b2n,  // Order total in B2N
  				'order_datetime'  				=> date('Y-m-d H:i:s T'),
  				'requested_by_ip'					=> @$_SERVER['REMOTE_ADDR'],
  				'requested_by_ua'					=> @$_SERVER['HTTP_USER_AGENT'],
  				'requested_by_srv'				=> B2NWC__base64_encode(serialize($_SERVER)),
  				);

  		$ret_info_array = array();


               $wallet_api = New ForkNoteWalletd("http://127.0.0.1:19070");

               $b2ncoin_payment_id = B2NWC__generate_new_B2Ncoin_payment_id($b2bwc_settings, $order_info);

               $b2ncoin_address = $b2bwc_settings['address'];


   			B2NWC__log_event (__FILE__, __LINE__, "     Generated unique B2Ncoin Payment ID: '{$b2ncoin_payment_id}' Address: '{$b2ncoin_address}' for order_id " . $order_id);

     	update_post_meta (
     		$order_id, 			// post id ($order_id)
     		'order_total_in_b2n', 	// meta key
     		$order_total_in_b2n 	// meta value. If array - will be auto-serialized
     		);
     	update_post_meta (
     		$order_id, 			// post id ($order_id)
     		'B2Ncoins_payment_id',	// meta key
     		$b2ncoin_payment_id 	// meta value. If array - will be auto-serialized
     		);
     	update_post_meta (
     		$order_id, 			// post id ($order_id)
     		'B2Ncoins_address',	// meta key
     		$b2ncoin_address 	// meta value. If array - will be auto-serialized
     		);
     	update_post_meta (
     		$order_id, 			// post id ($order_id)
     		'B2Ncoins_paid_total',	// meta key
     		"0" 	// meta value. If array - will be auto-serialized
     		);
     	update_post_meta (
     		$order_id, 			// post id ($order_id)
     		'B2Ncoins_refunded',	// meta key
     		"0" 	// meta value. If array - will be auto-serialized
     		);
     	update_post_meta (
     		$order_id, 				// post id ($order_id)
     		'_incoming_payments',	// meta key. Starts with '_' - hidden from UI.
     		array()					// array (array('datetime'=>'', 'from_addr'=>'', 'amount'=>''),)
     		);
     	update_post_meta (
     		$order_id, 				// post id ($order_id)
     		'_payment_completed',	// meta key. Starts with '_' - hidden from UI.
     		0					// array (array('datetime'=>'', 'from_addr'=>'', 'amount'=>''),)
     		);
			//-----------------------------------


			// The B2Ncoin gateway does not take payment immediately, but it does need to change the orders status to on-hold
			// (so the store owner knows that B2Ncoin payment is pending).
			// We also need to tell WooCommerce that it needs to redirect to the thankyou page – this is done with the returned array
			// and the result being a success.
			//
			global $woocommerce;

			//	Updating the order status:

			// Mark as on-hold (we're awaiting for B2Ncoins payment to arrive)
			$order->update_status('on-hold', __('Awaiting B2Ncoin payment to arrive', 'woocommerce'));

/*
			///////////////////////////////////////
			// timbowhite's suggestion:
			// -----------------------
			// Mark as pending (we're awaiting for B2Ncoins payment to arrive), not 'on-hold' since
			// woocommerce does not automatically cancel expired on-hold orders. Woocommerce handles holding the stock
		    // for pending orders until order payment is complete.
			$order->update_status('pending', __('Awaiting B2Ncoin payment to arrive', 'woocommerce'));

			// Me: 'pending' does not trigger "Thank you" page and neither email sending. Not sure why.
			//			Also - I think cancellation of unpaid orders needs to be initiated from cron job, as only we know when order needs to be cancelled,
			//			by scanning "on-hold" orders through 'assigned_address_expires_in_mins' timeout check.
			///////////////////////////////////////
*/
			// Remove cart
			$woocommerce->cart->empty_cart();

			// Empty awaiting payment session
			unset($_SESSION['order_awaiting_payment']);

			// Return thankyou redirect
			if (version_compare (WOOCOMMERCE_VERSION, '2.1', '<'))
			{
				return array(
					'result' 	=> 'success',
					'redirect'	=> add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(woocommerce_get_page_id('thanks'))))
				);
			}
			else
			{
				return array(
						'result' 	=> 'success',
						'redirect'	=> add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, $this->get_return_url( $order )))
					);
			}
		}
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	    /**
	     * Output for the order received page.
	     *
	     * @access public
	     * @return void
	     */
		function B2NWC__thankyou_page($order_id)
		{
			// B2NWC__thankyou_page is hooked into the "thank you" page and in the simplest case can just echo’s the description.

			// Get order object.
			// http://wcdocs.woothemes.com/apidocs/class-WC_Order.html
			$order = new WC_Order($order_id);

			// Assemble detailed instructions.
			$order_total_in_b2n = get_post_meta($order->id, 'order_total_in_b2n',   true); // set single to true to receive properly unserialized array
			$B2Ncoins_payment_id = get_post_meta($order->id, 'B2Ncoins_payment_id', true); // set single to true to receive properly unserialized array
			$B2Ncoins_address = get_post_meta($order->id, 'B2Ncoins_address', true); // set single to true to receive properly unserialized array


			$instructions = $this->instructions;
			$instructions = str_replace ('{{{B2NCOINS_AMOUNT}}}',  $order_total_in_b2n, $instructions);
			$instructions = str_replace ('{{{B2NCOINS_PAYMENTID}}}', $B2Ncoins_payment_id, 	$instructions);
			$instructions = str_replace ('{{{B2NCOINS_ADDRESS}}}', $B2Ncoins_address, 	$instructions);
			$instructions =
				str_replace (
					'{{{EXTRA_INSTRUCTIONS}}}',

					$this->instructions_multi_payment_str,
					$instructions
					);
            $order->add_order_note( __("Order instructions: price: {$order_total_in_b2n} B2N, incoming account: {$B2Ncoins_address} payment id: {$B2Ncoins_payment_id}", 'woocommerce'));

	        echo wpautop (wptexturize ($instructions));
		}
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	    /**
	     * Add content to the WC emails.
	     *
	     * @access public
	     * @param WC_Order $order
	     * @param bool $sent_to_admin
	     * @return void
	     */
		function B2NWC__email_instructions ($order, $sent_to_admin)
		{
	    	if ($sent_to_admin) return;
	    	if (!in_array($order->status, array('pending', 'on-hold'), true)) return;
	    	if ($order->payment_method !== 'B2Ncoin') return;

	    	// Assemble payment instructions for email
			$order_total_in_b2n = get_post_meta($order->id, 'order_total_in_b2n',   true); // set single to true to receive properly unserialized array
			$B2Ncoins_payment_id = get_post_meta($order->id, 'B2Ncoins_payment_id', true); // set single to true to receive properly unserialized array
			$B2Ncoins_address = get_post_meta($order->id, 'B2Ncoins_address', true); // set single to true to receive properly unserialized array


			$instructions = $this->instructions;
			$instructions = str_replace ('{{{B2NCOINS_AMOUNT}}}',  $order_total_in_b2n, 	$instructions);
			$instructions = str_replace ('{{{B2NCOINS_PAYMENTID}}}', $B2Ncoins_payment_id, 	$instructions);
			$instructions = str_replace ('{{{B2NCOINS_ADDRESS}}}', $B2Ncoins_address, 	$instructions);
			$instructions =
				str_replace (
					'{{{EXTRA_INSTRUCTIONS}}}',

					$this->instructions_multi_payment_str,
					$instructions
					);

			echo wpautop (wptexturize ($instructions));
		}
		//-------------------------------------------------------------------

	}
	//=======================================================================


	//-----------------------------------------------------------------------
	// Hook into WooCommerce - add necessary hooks and filters
	add_filter ('woocommerce_payment_gateways', 	'B2NWC__add_B2Ncoin_gateway' );

	// Disable unnecessary billing fields.
	/// Note: it affects whole store.
	/// add_filter ('woocommerce_checkout_fields' , 	'B2NWC__woocommerce_checkout_fields' );

	add_filter ('woocommerce_currencies', 			'B2NWC__add_b2n_currency');
	add_filter ('woocommerce_currency_symbol', 		'B2NWC__add_b2n_currency_symbol', 10, 2);

	// Change [Order] button text on checkout screen.
    /// Note: this will affect all payment methods.
    /// add_filter ('woocommerce_order_button_text', 	'B2NWC__order_button_text');
	//-----------------------------------------------------------------------

	//=======================================================================
	/**
	 * Add the gateway to WooCommerce
	 *
	 * @access public
	 * @param array $methods
	 * @package
	 * @return array
	 */
	function B2NWC__add_B2Ncoin_gateway( $methods )
	{
		$methods[] = 'B2NWC_B2Ncoin';
		return $methods;
	}
	//=======================================================================

	//=======================================================================
	// Our hooked in function - $fields is passed via the filter!
	function B2NWC__woocommerce_checkout_fields ($fields)
	{
	     unset($fields['order']['order_comments']);
	     unset($fields['billing']['billing_first_name']);
	     unset($fields['billing']['billing_last_name']);
	     unset($fields['billing']['billing_company']);
	     unset($fields['billing']['billing_address_1']);
	     unset($fields['billing']['billing_address_2']);
	     unset($fields['billing']['billing_city']);
	     unset($fields['billing']['billing_postcode']);
	     unset($fields['billing']['billing_country']);
	     unset($fields['billing']['billing_state']);
	     unset($fields['billing']['billing_phone']);
	     return $fields;
	}
	//=======================================================================

	//=======================================================================
	function B2NWC__add_b2n_currency($currencies)
	{
	     $currencies['B2N'] = __( 'B2Ncoin', 'woocommerce' );
	     return $currencies;
	}
	//=======================================================================

	//=======================================================================
	function B2NWC__add_b2n_currency_symbol($currency_symbol, $currency)
	{
		switch( $currency )
		{
			case 'B2N':
				$currency_symbol = '$B2N'; // ฿
				break;
		}

		return $currency_symbol;
	}
	//=======================================================================

	//=======================================================================
 	function B2NWC__order_button_text () { return 'Continue'; }
	//=======================================================================
}
//###########################################################################

//===========================================================================
function B2NWC__process_payment_completed_for_order ($order_id, $B2Ncoins_paid=false)
{

	if ($B2Ncoins_paid)
		update_post_meta ($order_id, 'B2Ncoins_paid_total', $B2Ncoins_paid);

	// Payment completed
	// Make sure this logic is done only once, in case customer keeps sending payments :)
	if (!get_post_meta($order_id, '_payment_completed', true))
	{
		update_post_meta ($order_id, '_payment_completed', '1');

		B2NWC__log_event (__FILE__, __LINE__, "Success: order '{$order_id}' paid in full. Processing and notifying customer ...");

		// Instantiate order object.
		$order = new WC_Order($order_id);
		$order->add_order_note( __('Order paid in full', 'woocommerce') );

	  $order->payment_complete();

    $b2bwc_settings = B2NWC__get_settings();
		if ($b2bwc_settings['autocomplete_paid_orders'])
		{
  		// Ensure order is completed.
			$order->update_status('completed', __('Order marked as completed according to B2Ncoin plugin settings', 'woocommerce'));
		}

		// Notify admin about payment processed
		$email = get_settings('admin_email');
		if (!$email)
		  $email = get_option('admin_email');
		if ($email)
		{
			// Send email from admin to admin
			B2NWC__send_email ($email, $email, "Full payment received for order ID: '{$order_id}'",
				"Order ID: '{$order_id}' paid in full. <br />Received B2N: '$B2Ncoins_paid'.<br />Please process and complete order for customer."
				);
		}
	}
}
//===========================================================================

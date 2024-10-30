<?php

/**
 * BOLD.Pay Payment Gateway Class
 */
class boldpay extends WC_Payment_Gateway {
	function __construct() {
		$this->id = "boldpay";

		$this->method_title = __( "BOLD.Pay", 'boldpay' );

		$this->method_description = __( "Secured payment gateway plug-in for WooCommerce", 'boldpay' );

		$this->title = __( "boldpay", 'boldpay' );

		$this->icon = 'https://boldpay.cc/assets/img/Payment_Method.png';

		$this->has_fields = true;

		$this->init_form_fields();

		$this->init_settings();

		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
				$this,
				'process_admin_options'
			) );
		}
	}

	# Build the administration fields for this specific Gateway
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __( 'Enable / Disable', 'boldpay' ),
				'label'   => __( 'Enable this payment gateway', 'boldpay' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'title'          => array(
				'title'    => __( 'Title', 'boldpay' ),
				'type'     => 'text',
				'desc_tip' => __( 'Payment gateway title that customer will see at checkout page.', 'boldpay' ),
				'default'  => __( 'BOLD.Pay', 'boldpay' ),
				'custom_attributes' => array('readonly' => 'readonly'),
			),
			'user_name' => array(
				'title'    => __( 'User Name', 'boldpay' ),
				'type'     => 'text',
				'desc_tip' => __( '(Compulsory) Username that you sign up in Bold.Pay.', 'boldpay' ),
				'custom_attributes' => array( 'required' => 'required' ),
			),
			'apikey'      => array(
				'title'    => __( 'API Key', 'boldpay' ),
				'type'     => 'text',
				'desc_tip' => __( '(Compulsory) You can obtain this API Key in business settings in Bold.Pay.', 'boldpay' ),
				'custom_attributes' => array( 'required' => 'required' ),
			),
			'callbackurl'      => array(
				'title'    => __( 'CallBack URL', 'boldpay' ),
				'type'     => 'text',
				'desc_tip' => __( '(Compulsory) This is to fill in your website domain as http://your_domain/checkout/. Example, https://macrokiosk.com/checkout/.', 'boldpay' ),
				'custom_attributes' => array( 'required' => 'required' ),
			),
			'currency'          => array(
				'title'    => __( 'Currency', 'boldpay' ),
				'type'     => 'select',
				'options'       => array(
					'1'	=> __( 'MYR' ),
					'3'	=> __( 'THB' )
				),
				'desc_tip' => __( 'Payment currency.', 'boldpay' ),
				'default'  => __( '1' ),
				'custom_attributes' => array('required' => 'required'),
			),
			'environment' => array(
				'title'   => __( 'Environment', 'boldpay' ),
				'type'    => 'select',
				'options' => array(
					'sandbox' => __( 'Sandbox' ),
					'live'    => __( 'Live' )
				),
				'desc_tip' => __( 'Payment environment.', 'boldpay' ),
				'default'  => __( 'live' ),
				'custom_attributes' => array('required' => 'required'),
			),
		);
	}

	function filter_shipping( $fields_array ) {
		$fields_array = array_replace($fields_array, $this->update_shipping);
		return array_diff_key($fields_array, array_flip($this->disabled_shipping));
	}
	
	# Submit payment
	public function process_payment( $order_id ) {
		# Get this order's information so that we know who to charge and how much
		$customer_order = wc_get_order( $order_id );

		# Prepare the data to send to boldpay
		$detail = "order " . $order_id;

		$old_wc = version_compare( WC_VERSION, '3.0', '<' );

		if ( $old_wc ) {
			$order_id = $customer_order->id;
			$amount   = $customer_order->order_total;
			$name     = $customer_order->billing_first_name . ' ' . $customer_order->billing_last_name;
			$email    = $customer_order->billing_email;
			$phone    = $customer_order->billing_phone;
		} else {
			$order_id = $customer_order->get_id();
			$amount   = wc_format_decimal( $customer_order->get_total(), 2 );
			$name     = $customer_order->get_billing_first_name() . ' ' . $customer_order->get_billing_last_name();
			$email    = $customer_order->get_billing_email();
			$phone    = $customer_order->get_billing_phone();
		}
		
		# Generate payid.
		$payid = strtoupper(substr(sanitize_text_field( $this->user_name ),0,4)) . substr(sanitize_text_field( $this->apikey ) ,0,4) . date("ymdHis");  

		$ip = gethostbyname(gethostname());

		$hash_value = md5(strtoupper(sanitize_text_field( $this->user_name ) . '4' . $amount . $ip . $payid . esc_url_raw( $this->callbackurl ) . esc_url_raw( $this->callbackurl ) . sanitize_text_field( $this->apikey ) ));
		
		// Set default currency code (1 = MYR, 3 = THB)
		$currCode = 1;
		if ( isset( $this->currency ) ) {
			$currCode = sanitize_text_field( $this->currency );
		}

		$post_args = array(
			'AccessToken' => strtoupper($hash_value),
			'Username' => sanitize_text_field( $this->user_name ),
			'CustIP' => $ip, 
			'PymtMethod' => '4', 
			'Amount' => $amount,
			'Description' => $detail,
			'CustName' => urlencode( $name ),
			'CustEmail' => $email,
			'CustPhone' => $phone,
			'OrderNumber' => $order_id,
			'PayID' =>  $payid,
			'CallbackURL' => urlencode(esc_url_raw( $this->callbackurl )),
			'NotificationURL' => urlencode(esc_url_raw( $this->callbackurl )),
			'ApiKey' => sanitize_text_field( $this->apikey ),
			'Origin' => 'PLUGIN_WC',
			'CurrencyCode' => $currCode 
		);

		# Format it properly using get
		$boldpay_args = '';
		foreach ( $post_args as $key => $value ) {
			if ( $boldpay_args != '' ) {
				$boldpay_args .= '&';
			}
			$boldpay_args .= $key . "=" . $value;
		}

		// Set payment request api based on environment configuration (Default = Live)
		$environment_url = 'https://pay.etracker.cc/BoldPayApi/PaymentRequest';
		if ( isset( $this->environment ) ) {
			if ( sanitize_text_field( $this->environment ) == 'sandbox' ) {
				$environment_url = 'http://uat.pay.etracker.cc/BoldPayApi/PaymentRequest';
			}
		}

		return array(
			'result'   => 'success',
			'redirect' => $environment_url . '?' . $boldpay_args
		);
	}

	public function check_boldpay_response() {
		if ( isset( $_REQUEST['AccessToken'] ) && isset( $_REQUEST['TransactionID'] ) && isset( $_REQUEST['OrderNumber'] ) && isset( $_REQUEST['PayID'] ) && isset( $_REQUEST['Status'] ) ) {
			global $woocommerce;

			$is_callback = isset( $_POST['OrderNumber'] ) ? true : false;

			$order = wc_get_order(sanitize_text_field( $_REQUEST['OrderNumber'] ));

			$old_wc = version_compare( WC_VERSION, '3.0', '<' );

			$order_id = $old_wc ? $order->id : $order->get_id();

			$status = $order->get_status();

			if ( $order && $order_id != 0 ) {
				$amount   = wc_format_decimal( $order->get_total(), 2 );
				$ip = gethostbyname(gethostname()); 
				$payid = sanitize_text_field( $_REQUEST['PayID'] );

				# Check if the data sent is valid based on the hash value
				$hash_value = md5(strtoupper(sanitize_text_field( $this->user_name ) . '4' . $amount . $ip . $payid . esc_url_raw( $this->callbackurl ) . esc_url_raw( $this->callbackurl ) . sanitize_text_field( $this->apikey ) ));

				if ( strtoupper($hash_value) == strtoupper(sanitize_text_field( $_REQUEST['AccessToken'] ))) {
				
					if ( strtoupper(sanitize_text_field( $_REQUEST['Status'] )) == 'SUCCESS' ) {
						# Query request for final status
						# Set query request api based on environment configuration (Default = Live)
						$query_request_url = 'https://pay.etracker.cc/BoldPayApi/QueryRequest';
						if ( isset( $this->environment ) ) {
							if ( sanitize_text_field( $this->environment ) == 'sandbox' ) {
								$query_request_url = 'http://uat.pay.etracker.cc/BoldPayApi/QueryRequest';
							}
						}

						# Append query request params
						$query_request_url .= '?AccessToken=' . $_REQUEST['AccessToken'];
						$query_request_url .= '&APIKey=' . sanitize_text_field( $this->apikey );
						$query_request_url .= '&Username=' . sanitize_text_field( $this->user_name );
						$query_request_url .= '&PayID=' . $_REQUEST['PayID'];
						$query_request_url .= '&CallBackUrl=' .urlencode(esc_url_raw( $this->callbackurl ));

						if ($is_callback) {
							sleep(2);
						}

						# Initialize cURL
						$ch = curl_init();

						# Set the URL that you want to GET by using the CURLOPT_URL option
						curl_setopt($ch, CURLOPT_URL, $query_request_url);
 
						# Set CURLOPT_RETURNTRANSFER so that the content is returned as a variable
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);

						# Set CURLOPT_FOLLOWLOCATION to true to follow redirects
						curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

						# Bypass SSL certificate as is calling from backend (S2S)
						curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

						# Execute the request
						$data = curl_exec($ch);
						
						# Get status from header redirect_url value
						$header  = curl_getinfo( $ch );
						$parts = parse_url($header['redirect_url']);
						parse_str($parts['query'], $query);

						# Close the cURL handle
						curl_close($ch);

						# Check status returned
						if ( strtolower( $query['Status'] ) == 'success' ) {
							# only update if order is pending or failed or on-hold
							if ( strtolower( $order->get_status() ) == 'pending' || strtolower( $order->get_status() ) == 'failed' || strtolower( $order->get_status() ) == 'on-hold' ) {
								
								$order->payment_complete();								
								$order->add_order_note( 'Payment successfully made through BOLD.Pay. Transaction ID is ' . sanitize_text_field( $_REQUEST['TransactionID'] ));
								
								if ( $is_callback ) {
									echo 'OK';
								} else {
									# redirect to order receive page
									wp_redirect( $order->get_checkout_order_received_url() );
								}

								exit();
							}
						}
						else if ( strtolower( $query['Status'] ) == 'pending' ) {
							# only update if order is pending or failed or on-hold
							if ( strtolower( $order->get_status() ) == 'pending' || strtolower( $order->get_status() ) == 'failed' || strtolower( $order->get_status() ) == 'on-hold' ) {
									
								$order->update_status( 'on-hold' );								
								$order->add_order_note( 'Payment pending made through BOLD.Pay. Transaction ID is ' . sanitize_text_field( $_REQUEST['TransactionID'] ));
								
								if ( $is_callback ) {
									echo 'OK';
								} else {
									# redirect to order receive page
									wp_redirect( $order->get_checkout_order_received_url() );
								}

								exit();
							}
						}

					} else if ( strtoupper(sanitize_text_field( $_REQUEST['Status'] )) == 'PENDING' ) {

						# only update if order is pending or failed or on-hold
						if ( strtolower( $order->get_status() ) == 'pending' || strtolower( $order->get_status() ) == 'failed' || strtolower( $order->get_status() ) == 'on-hold' ) {
								
							$order->update_status( 'on-hold' );								
							$order->add_order_note( 'Payment pending made through BOLD.Pay. Transaction ID is ' . sanitize_text_field( $_REQUEST['TransactionID'] ));
							
							if ( $is_callback ) {
								echo 'OK';
							} else {
								# redirect to order receive page
								wp_redirect( $order->get_checkout_order_received_url() );
							}

							exit();
						}

					} else if ( strtoupper(sanitize_text_field( $_REQUEST['Status'] )) == 'FAIL' ) {
						
						# only update if order is pending or failed or on-hold
						if ( strtolower( $order->get_status() ) == 'pending' || strtolower( $order->get_status() ) == 'failed' || strtolower( $order->get_status() ) == 'on-hold' ) {
																	
							$order->update_status( 'failed' );								
							if ( isset( $_REQUEST['Reason'] ) ){
								$order->add_order_note( sanitize_text_field( $_REQUEST['Reason'] ) . '. Transaction ID is ' . sanitize_text_field( $_REQUEST['TransactionID'] ));								
							}
							else{
								$order->add_order_note( 'Payment failed made through BOLD.Pay. Transaction ID is ' . sanitize_text_field( $_REQUEST['TransactionID'] ));								
							}
							
							if ( $is_callback ) {
								echo 'OK';
								exit();
							} else {
								add_filter( 'the_content', 'boldpay_payment_declined_msg' );
								wc_add_notice( __( 'The payment was declined. Please check with your bank. Thank you.', 'gateway' ), 'error' );
							}
						}

					} 
					else if ( strtoupper(sanitize_text_field( $_REQUEST['Status'] )) == 'REFUNDED' || strtoupper(sanitize_text_field( $_REQUEST['Status'] )) == 'REVERSED') {
						
						# only update if order is processing or on-hold
						if ( strtolower( $order->get_status() ) == 'processing' || strtolower( $order->get_status() ) == 'on-hold' ) {
																	
							$order->update_status( 'refunded' );								
							$order->add_order_note( 'Payment refunded/reversed made through BOLD.Pay. Transaction ID is ' . sanitize_text_field( $_REQUEST['TransactionID'] ));

							if ( $is_callback ) {
								echo 'OK';
							} else {
								# redirect to order receive page
								wp_redirect( $order->get_checkout_order_received_url() );
							}
							exit();
						}

					} else {
						if ( $is_callback ) {
							echo 'OK';
						} else {
							add_filter( 'the_content', 'boldpay_hash_error_msg' );
						}
					}
				} else {

					add_filter( 'the_content', 'boldpay_hash_error_msg' );
				}
			}

			if ( $is_callback ) {
				echo 'OK';

				exit();
			}
		}
		else if (isset( $_REQUEST['Status'] ) && isset( $_REQUEST['PayID'] ) && isset( $_REQUEST['OrderNumber'] )) {

			$is_callback = isset( $_POST['OrderNumber'] ) ? true : false;
			
			$order = wc_get_order(sanitize_text_field( $_REQUEST['OrderNumber'] ));

			# if receive (417 - Exceeded transaction limit) remain order status as pending payment	
			if ( sanitize_text_field( $_REQUEST['Status'] ) == '417' ) {
				$order->add_order_note( 'Payment request rejected. Exceeded transaction limit allowed. Error Code: 417' );
			} else {
				$order->add_order_note( 'Unable connect to BOLD.Pay API. Error code: ' . sanitize_text_field( $_REQUEST['Status'] ));
			}

			add_filter( 'the_content', 'boldpay_payment_fail_connection_msg' );

			if ( $is_callback ) {
				echo 'OK';

				exit();
			}
		}
	}

	# Validate fields, do nothing for the moment
	public function validate_fields() {
		return true;
	}

	# Check if we are forcing SSL on checkout pages, Custom function not required by the Gateway for now
	public function do_ssl_check() {
		if ( $this->enabled == "yes" ) {
			if ( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
				echo "<div class=\"error\"><p>" . sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . "</p></div>";
			}
		}
	}

	/**
	 * Check if this gateway is enabled and available in the user's country.
	 * Note: Not used for the time being
	 * @return bool
	 */
	public function is_valid_for_use() {
		return in_array( get_woocommerce_currency(), array( 'MYR' ) );
	}
}
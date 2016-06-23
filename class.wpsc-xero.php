<?php

final class Plugify_WPSC_Xero {

	private $xero_config = array();

	private $basename;
	private $title;

	/**
	* Class constructor
	*
	* @since 1.0
	* @return void
	*/
	public function __construct() {

		// Setup vars
		$this->basename = WPSC_XERO_BASENAME;
		$this->title = 'WP e-Commerce - Xero';

		// Register hooks
		$this->initialize();

		// Setup languages
		$this->load_textdomain();

	}

	/**
	* Function to initialize everything the plugin needs to operate. WP Hooks and OAuth library
	*
	* @since 0.9
	* @return void
	*/
	public function initialize() {

		// Create Xero settings tab
		add_action( 'wpsc_register_settings_tabs', array( $this, 'wpsc_xero_register_settings' ) );

		// Listen for purchase log changes
		add_action( 'wpsc_update_purchase_log_status', array( $this, 'purchase_log_change' ), 10, 4 );

		// Setup actions for invoice creation success/fail
		add_action( 'wpsc_xero_invoice_creation_success', array( $this, 'xero_invoice_success' ), 99, 4 );
		add_action( 'wpsc_xero_invoice_creation_fail'   , array( $this, 'xero_invoice_fail' )   , 10, 4 );
		add_action( 'wpsc_xero_payment_success'         , array( $this, 'xero_payment_success' ), 10, 3 );
		add_action( 'wpsc_xero_payment_fail'            , array( $this, 'xero_payment_fail' )   , 10, 3 );

		// Admin hooks
		add_action( 'admin_init'           , array( $this, 'admin_init' ) );
		add_action( 'admin_notices'        , array( $this, 'admin_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// Admin hooks related specifically to displaying Xero details
		add_action( 'wpsc_purchlogitem_metabox_end', array( $this, 'xero_purchase_invoice_details' ), 10, 1 );

	}

	/**
	* Register hooks which are needed for the admin area, such as the 'Generate Invoice' button and automatically
	* displaying invoice details in the metabox
	*
	* @since 1.0
	*
	* @return void
	*/
	public function admin_init() {

		// Admin AJAX hooks
		add_action( 'wp_ajax_invoice_lookup'      , array( $this, 'ajax_xero_invoice_lookup' ) );
		add_action( 'wp_ajax_generate_invoice'    , array( $this, 'ajax_generate_invoice' ) );
		add_action( 'wp_ajax_disassociate_invoice', array( $this, 'ajax_disassociate_invoice' ) );

	}

	/**
	* Queue up styles and scripts that WPEC Xero uses in the admin area
	*
	* @since 0.9
	*
	* @return void
	*/
	public function admin_enqueue_scripts() {

		$screen = get_current_screen();

		if ( 'dashboard_page_wpsc-purchase-logs' == $screen->id ) {

			// Enqueue styles for WPSC Xero
			wp_enqueue_style( 'wpsc-xero', plugin_dir_url( __FILE__ ) . 'assets/css/wpsc-xero.css' );

			// Enqueue scripts for WPSC Xero
			wp_enqueue_script( 'wpsc-xero-js', plugin_dir_url( __FILE__ ) . 'assets/js/wpsc-xero.js', array( 'jquery' ) );

		}

	}

	/**
	* Handle displaying any required admin notices.
	* Since 0.9, displays whether WPEC needs to be activated or if it's not of a high enough version
	*
	* @since 0.9
	*
	* @return void
	*/
	public function admin_notices() {

		// Display a notice if WPEC is not installed and deactivate plugin
		if ( !class_exists( 'WP_eCommerce' ) ) {

			// Make sure this plugin is active
			if ( is_plugin_active( $this->basename ) ) {

				// Deactivate WPEC Xero
				deactivate_plugins( $this->basename );

				// Turn off activation admin notice
				if ( isset( $_GET['activate'] ) ) {
					unset( $_GET['activate'] );
				}

				echo '<div class="error"><p>' . sprintf( __( '%s has been deactivated because it requires WP e-Commerce to be installed and activated', 'wpsc-xero' ), $this->title ) . '</p></div>';

			}

		}
		else {

			// If WPEC is installed but version is too low, display a notice

			if ( version_compare( WPSC_VERSION, '3.8.14', '<' ) ) {
				echo '<div class="error"><p>' . sprintf( __( '%s requires WP e-Commerce version 3.8 or greater. Please update WP e-Commerce.', 'wpsc-xero' ), $this->title ) . '</p></div>';
			}

		}

	}

	/**
	* Display Xero invoice details when viewing a single order in the admin area
	*
	* @since 0.9
	*
	* @return void
	*/
	public function xero_purchase_invoice_details( $log_id ) {

		// Get invoice ID and number from purchase meta
		$invoice_id     = wpsc_get_purchase_meta( $log_id, 'xero_id', true );
		$invoice_number = wpsc_get_purchase_meta( $log_id, 'xero_number', true );

		// Determine if Xero settings have been configured
		$valid_settings = $this->settings_are_valid();

		?>
		<div id="wpsc-xero">
			<div class="metabox-holder">

				<div id="purchlogs_xero" class="postbox">

					<h3 class="hndle">
						<img src="<?php echo plugin_dir_url( __FILE__ ) . 'assets/art/xero-logo@2x.png'; ?>" width="12" height="12" style="position:relative;top:1px;" />&nbsp;
						<?php _e( 'Xero Invoice Details' , 'wpsc-xero' ); ?>
					</h3>

					<div class="inside">

						<?php if ( !$valid_settings ): ?>

						<p><?php _e( 'Xero settings not configured', 'wpsc-xero' ); ?></p>
						<p>
							<?php _e( 'Looks like you need to configure your Xero settings!', 'wpsc-xero' ); ?>
							<?php _e( 'You can <a href="' . admin_url( 'options-general.php?page=wpsc-settings&tab=xero' ) . '">click here</a> to do so', 'wpsc-xero' ); ?>
						</p>

						<?php else: ?>

							<?php if ( '' != $invoice_id ): ?>

								<h3 class="invoice-number"><?php echo esc_html( $invoice_number ); ?></h3>

							<?php else: ?>

								<h3 class="invoice-number"><?php _e( 'No associated invoice found', 'wpsc-xero' ); ?></h3>
								<p>
									<a id="wpsc-xero-generate-invoice" class="button-primary" href="#"><?php _e( 'Generate Invoice Now', 'wpsc-xero' ); ?></a>
								</p>

							<?php endif; ?>

						<?php endif; ?>

						<div id="wpsc_xero_invoice_details">
							<p class="ajax-loader">
								<img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'assets/art/ajax-loader.gif' ); ?>" alt="<?php _e( 'Loading', 'wpsc-xero' ); ?>" />
							</p>
							<input type="hidden" id="wpsc_xero_invoice_number" name="wpsc_xero_invoice_number" value="<?php echo esc_attr( $invoice_number ); ?>" />
							<input type="hidden" id="wpsc_purchase_log_id" name="wpsc_purchase_log_id" value="<?php echo absint( $_GET['id'] ); ?>" />
						</div>

						<?php if ( $valid_settings ): ?>
						<div class="wpsc-order-update-box wpsc-admin-box wpsc-invoice-actions">
							<div class="major-publishing-actions">
								<div class="publishing-action">
									<a id="wpsc-xero-disassociate-invoice" class="button-secondary right" href="#"><?php _e( 'Disassociate Invoice', 'wpsc-xero' ); ?></a>
									<a id="wpsc-view-invoice-in-xero" class="button-primary right" target="_blank" href="https://go.xero.com/AccountsReceivable/Edit.aspx?InvoiceID=<?php echo esc_attr( $invoice_id ); ?>"><?php _e( 'View in Xero', 'wpsc-xero' ); ?></a>
								</div>
								<div class="clear"></div>
							</div>
						</div>
						<?php endif; ?>

					</div>

				</div>

			</div>
		</div>

		<?php

	}

	/**
	* Load language files
	*
	* @since 1.0
	*
	* @return void
	*/
	public function load_textdomain() {

		// Set filter for plugin's languages directory
		$lang_dir = plugin_dir_path( __FILE__ ) . 'languages/';

		// Traditional WordPress plugin locale filter
		$locale = apply_filters( 'plugin_locale', get_locale(), 'wpsc-xero' );
		$mofile = sprintf( '%1$s-%2$s.mo', 'wpsc-xero', $locale );

		// Setup paths to current locale file
		$mofile_local = $lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/wpsc-xero/' . $mofile;

		if ( file_exists( $mofile_global ) ) {
			load_textdomain( 'wpsc-xero', $mofile_global );
		}
		elseif ( file_exists( $mofile_local ) ) {
			load_textdomain( 'wpsc-xero', $mofile_local );
		}
		else {
			// Load the default language files
			load_plugin_textdomain( 'wpsc-xero', false, $lang_dir );
		}

	}

	/**
	* When WPeC is loading settings tabs, tell it to also load our Xero settings tab
	*
	* @since 1.0
	*
	* @return void
	*/
	public function wpsc_xero_register_settings( $settings_page ) {

		// Load required class
		require_once 'class.wpsc-settings-tab-xero.php';

		// Register the tab
		$settings_page->register_tab( 'xero', 'Xero' );

	}

	/**
	* Leverage the Xero invoice creation success action to
	* save critical invoice data such as Number and ID as meta
	* against the WPEC Payment whenever an invoice is generated
	*
	* @since 1.0
	*
	* @param Xero_Invoice $invoice        Xero_Invoice object of newly generated invoice
	* @param string       $invoice_number Number of Xero invoice as automatically assigned by Xero. EG, "INV-123"
	* @param guid         $invoice_id     Unique ID of invoice as in Xero. EG "851b2f09-36f8-4df8-a32e-da8c4c451ff0"
	* @param int          $payment_id     ID of WPeC Payment
	*
	* @return void
	*/
	public function xero_payment_success( $xero_payment, $response, $purchase_log ) {

		// Add a success note to the payment
		$this->prepend_note( $purchase_log, __( 'Payment successfully applied.', 'wpsc-xero' ) );

	}

	public function xero_payment_fail( $xero_payment, $response, $purchase_log ) {

		// Add a failure notice to the payment
		$this->prepend_note( $purchase_log, sprintf( __( 'Payment could not be applied to Xero invoice. Please ensure your account codes are correct and that the invoice has been authorized. %s', 'wpsc-xero' ), $response->Elements->DataContractBase->ValidationErrors->ValidationError[0]->Message ) );

	}

	/**
	* Leverage the Xero invoice creation success action to save critical invoice data such as Number and ID as meta
	* against the WPEC Payment whenever an invoice is generated
	*
	* @since 1.0
	*
	* @param Xero_Invoice $invoice        Xero_Invoice object of newly generated invoice
	* @param string       $invoice_number Number of Xero invoice as automatically assigned by Xero. EG, "INV-123"
	* @param guid         $invoice_id     Unique ID of invoice as in Xero. EG "851b2f09-36f8-4df8-a32e-da8c4c451ff0"
	* @param int          $payment_id     ID of WPEC Payment
	* @return void
	*/
	public function xero_invoice_success ( $invoice, $invoice_number, $invoice_id, $purchase_log ) {

		// Get plugin settings
		$settings = get_option( 'xero' );

		// Save invoice number and ID against the purchase log
		wpsc_add_purchase_meta( $purchase_log->get( 'id' ), 'xero_id'    , $invoice_id );
		wpsc_add_purchase_meta( $purchase_log->get( 'id' ), 'xero_number', $invoice_number );

		// Prepend a note to the purchase log indicating success
		$this->prepend_note( $purchase_log, sprintf( __( '%s successfully created', 'wpsc-xero' ), $invoice_number ) );

		// If automatic payments are turned on, do eeet!
		if ( $settings['invoice_payments'] ) {
			@$this->create_payment( $invoice_id, $purchase_log );
		}

	}

	/**
	* Leverage the Xero invoice creation failure action to add an error note to the payment
	*
	* @since 1.0
	*
	* @param Xero_Invoice $invoice        Xero_Invoice object of invoice which failed to generate in Xero
	* @param int          $payment_id     ID of WPEC Payment
	* @param mixed        $error_obj      (optional) An object which was used in the context of creating a Xero invoice which subsequently failed
	* @param string       $custom_message (optional) Allow a developer to pass in the error message they want written on the WPEC payment note
	* @return void
	*/
	public function xero_invoice_fail( $invoice, $purchase_log, $request, $custom_message = null ) {

		// Insert a note on the payment informing merchant that Xero invoice generation failed, and why
		$message = __( 'Invoice could not be created.', 'wpsc-xero' );

		if ( !is_null( $custom_message ) ) {
			$message = $custom_message;
		}
		elseif ( isset( $request['response'] ) ) {
			$message .= ' ' . urldecode( $request['response'] );
		}

		$this->prepend_note( $purchase_log, $message );

	}

	/**
	* AJAX handler to do an invoice lookup. Uses parameter "invoice_number"
	*
	* @since 1.0
	*
	* @return HTTP
	*/
	public function ajax_xero_invoice_lookup() {

		if ( !$_REQUEST['invoice_number'] ) {
			wp_send_json_error();
		}

		if ( $response = @$this->get_invoice( $_REQUEST['invoice_number'] ) ) {
			$return = $this->get_invoice_excerpt( $response );
			wp_send_json_success( $return );
		}

		wp_send_json_error( array(
			'error_message' => sprintf( '<p>%s <a href="' . admin_url('options-general.php?page=wpsc-settings&tab=xero') . '" target="_blank">%s</a>', __( 'We could not get the invoice details', 'wpsc-xero' ), __( 'Are your Xero settings configured correctly?', 'wpsc-xero' ) )
		) );

	}

	/**
	* AJAX handler to generate an invoice in Xero. Uses parameter "payment_id" which represents the WPEC Payment
	*
	* @since 1.0
	*
	* @return HTTP
	*/
	public function ajax_generate_invoice() {

		if ( !isset( $_REQUEST['payment_id'] ) ) {
			wp_send_json_error();
		}

		if ( $response = @$this->create_invoice( $_REQUEST['payment_id'] ) ) {
			$return = $this->get_invoice_excerpt( $response );
			wp_send_json_success( $return );
		}
		else {
			wp_send_json_error( array(
				'error_message' => __( 'Xero invoice could not be created. Please refresh the page and check Payment Notes.', 'wpsc-xero' )
			) );
		}

		// Fall back to error response
		wp_send_json_error();

	}

	/**
	* AJAX handler to disassociate the invoice attached to a payment. Uses parameter "payment_id" which represents the WPSC Purchase Log ID
	*
	* @since 1.0
	*
	* @return HTTP
	*/
	public function ajax_disassociate_invoice() {

		// Abort if no purchase log id was sent
		if ( ! isset( $_REQUEST['payment_id'] ) ) {
			wp_send_json_error();
		}

		//Reference $wpdb
		global $wpdb;

		// Cache payment_id
		$payment_id = $_REQUEST['payment_id'];

		// Delete the necessary rows to disassociate the invoice
		$result = $wpdb->delete( "{$wpdb->prefix}wpsc_purchase_meta", array( 'wpsc_purchase_id' => $payment_id, 'meta_key' => 'xero_id' ) ) && $wpdb->delete( "{$wpdb->prefix}wpsc_purchase_meta", array( 'wpsc_purchase_id' => $payment_id, 'meta_key' => 'xero_number' ) );

		// Add a note to the purchase log
		$this->prepend_note( new WPSC_Purchase_Log( $payment_id ), __( 'Invoice was successfully disassociated' ) );

		// Send back the result
		if ( $result ) {
			wp_send_json_success();
		}
		else {
			wp_send_json_error();
		}

	}

	/**
	* Generate an array containing a snapshot of a Xero invoice
	*
	* @since 1.0
	*
	* @param $response SimpleXMLObject An XML response for a particular invoice from Xero
	* @return array
	*/
	public function get_invoice_excerpt( $response ) {

		$invoice_excerpt = array();

		foreach ( $response->Invoices as $invoice_tag ) {

			$invoice_excerpt['ID']            = (string) $invoice_tag->Invoice->InvoiceID;
			$invoice_excerpt['InvoiceNumber'] = (string) $invoice_tag->Invoice->InvoiceNumber;
			$invoice_excerpt['CurrencyCode']  = (string) $invoice_tag->Invoice->CurrencyCode;
			$invoice_excerpt['Total']         = (string) $invoice_tag->Invoice->Total;
			$invoice_excerpt['TotalTax']      = (string) $invoice_tag->Invoice->TotalTax;
			$invoice_excerpt['Status']        = (string) $invoice_tag->Invoice->Status;

		}

		$invoice_excerpt['Contact']['Name']  = (string) $invoice_tag->Invoice->Contact->Name;
		$invoice_excerpt['Contact']['Email'] = (string) $invoice_tag->Invoice->Contact->EmailAddress;

		return $invoice_excerpt;

	}

	/**
	* Listen for order status changes. When a status switches to "Accepted Payment", generate an invoice if applicable
	*
	* @since 1.0
	*
	* @param string $invoice_id ID of Xero invoice. NOT the number, looks like an MD5 hash, not the "pretty" ID
	* @param int    $payment_id WPEC payment ID this payment will be mirroring
	* @return void
	*/
	public function purchase_log_change( $log_id, $current_status, $previous_status, $purchase_log ) {

		global $wpsc_purchlog_statuses, $wpdb;

		// Abort if the current (new) status is not "Accepted Payment"
		if ( 3 != $current_status ) {
			return false;
		}

		// Abort if an invoice has already been generated for this purchase
		if ( $xero_id = $wpdb->get_var( "SELECT `meta_value` FROM {$wpdb->prefix}wpsc_purchase_meta WHERE `wpsc_purchase_id` = $log_id AND `meta_key` = 'xero_id' LIMIT 1;" ) ) {
			return false;
		}

		// Abort if "Automatically create invoices" is not turned on
		$settings = get_option( 'xero' );

		if ( ! array_key_exists( 'automatic_invoices', $settings ) ) {
			return false;
		}

		// Conditions should be good. Kick off invoice generation
		@$this->create_invoice( $purchase_log );

	}

	/**
	* Apply a payment to a Xero invoice
	*
	* @since 1.0
	*
	* @param string            $invoice_id   ID of Xero invoice. NOT the number, looks like an MD5 hash, not the "pretty" ID
	* @param WPSC_Purchase_Log $purchase_log Purchase log object by which a Xero payment will be derived from
	* @return void
	*/
	public function create_payment( $invoice_id, $purchase_log ) {

		// Get total for order
		$tax    = $purchase_log->get( 'wpec_taxes_total' );
		$total  = $purchase_log->get( 'totalprice' );

		// Get WPSC Xero settings
		$settings = get_option( 'xero' );

		// Build Xero_Payment object
		$xero_payment = new Xero_Payment( array(
			'invoice_id' => $invoice_id,
			'account_code' => $settings['payments_account'],
			'date' => date( 'Y-m-d', $purchase_log->get('date') ),
			'amount' => $total
		) );

		// Send the invoice to Xero
		if ( $this->settings_are_valid() && $settings['invoice_status'] == 'AUTHORISED' ) {
			return @$this->put_payment( $xero_payment, $purchase_log );
		}
		else {
			do_action( 'wpsc_xero_payment_fail', $xero_payment, $purchase_log, NULL, __( 'Xero settings have not been configured correctly for payments', 'wpsc-xero' ) );
		}

	}

	/**
	* Send a payment to Xero
	*
	* @since 1.0
	*
	* @param Xero_Invoice	$invoice Xero_Payment object which contains payment data, which will be applied to the invoice
	* @return SimpleXMLObject
	*/
	private function put_payment( $xero_payment, $purchase_log ) {

		// Abort if a Xero_Invoice object was not passed
		if ( ! ( $xero_payment instanceof Xero_Payment ) ) {
			return false;
		}

		// Prepare payload
		$xml = $xero_payment->get_xml();

		// Create oAuth object and send request
		try {

			// Load oauth lib
			$this->load_oauth_lib();

			// Create object and send to Xero
			$XeroOAuth = new XeroOAuth( $this->xero_config );

			$request  = $XeroOAuth->request( 'PUT', $XeroOAuth->url( 'Payments', 'core' ), array(), $xml );
			$response = $XeroOAuth->parseResponse( $request['response'], 'xml' );

			// Parse the response from Xero and fire appropriate actions
			if ( 200 == $request['code'] ) {
				do_action( 'wpsc_xero_payment_success', $xero_payment, $response, $purchase_log );
			}
			else {
				do_action( 'wpsc_xero_payment_fail', $xero_payment, $response, $purchase_log );
			}

			return $response;

		}
		catch( Exception $e ) {
			do_action( 'wpsc_xero_payment_fail', $xero_payment, $response, $e );
		}

	}

	/**
	* Generates a Xero_Invoice object and then sends that object to the Xero API as XML for creation
	*
	* @since 1.0
	*
	* @param mixed $purchase_log Either a WPSC_Purchase_Log object or an ID of a purchase log from which the Xero invoice should be created
	* @return void
	*/
	public function create_invoice( $purchase_log ) {

		global $wpdb;

		// If a purchase log ID has been passed, convert it to an actual purchase_log object
		// This is necessary if an AJAX request is passing a purchase log ID
		if ( is_numeric( $purchase_log ) ) {
			$purchase_log = new WPSC_Purchase_Log( $purchase_log );
		}

		// Get plugin settings
		$settings = get_option( 'xero' );

		// Prepare required data such as customer details and cart contents
		$cart = $purchase_log->get_cart_contents();

		try {

			// Instantiate new invoice object
			$invoice = new Xero_Invoice();

			// Set creation and due dates
			$time = date( 'Y-m-d', $purchase_log->get('date') );

			$invoice->set_date( $time );
			$invoice->set_due_date( $time );

			// Set the currency code as per WPEC settings
			// If nothing is set, Xero will automatically assign a currency based on Xero account settings.
			if ( $currency_type = get_option( 'currency_type' ) ) {

				// Get currency code from wp_wpsc_currency_list
				if ( $code = $wpdb->get_var( "SELECT `code` FROM `{$wpdb->prefix}wpsc_currency_list` WHERE `id` = $currency_type;" ) ) {
					$invoice->set_currency_code( $code );
				}

			}

			// Get the user ID
			$user_id = $wpdb->get_var( "SELECT `meta_value` FROM `{$wpdb->prefix}wpsc_purchase_meta` WHERE `meta_key` = 'visitor_id';" );

			// From that user ID, get name and email
			$user_data = $wpdb->get_results( "SELECT `meta_key`, `meta_value` FROM `{$wpdb->prefix}wpsc_visitor_meta` WHERE `wpsc_visitor_id` = '$user_id';", OBJECT );

			// Init user data array
			$user_info = array();

			// Grab the user data that we need
			foreach( $user_data as $data ) {
				$user_info[$data->meta_key] = $data->meta_value;
			}

			// Set contact (invoice recipient) details
			$invoice->set_contact( new Xero_Contact( array(
				'first_name' => $user_info['billingfirstname'],
				'last_name'  => $user_info['billinglastname'],
				'email'      => $user_info['billingemail']
			) ) );

			// Add purchased items to invoice
			foreach( $cart as $line_item ) {

				$invoice->add( new Xero_Line_Item( array(
					'description' => $line_item->name,
					'quantity'    => $line_item->quantity,
					'unitamount'  => $line_item->price,
					'tax'         => $line_item->tax_charged,
					'total'       => $line_item->quantity * $line_item->price,
					'accountcode' => $settings['sales_account']
				) ) );

			}

			// Set invoice status
			if ( isset( $settings['invoice_status'] ) && ! empty( $settings['invoice_status'] ) ) {
				$invoice->set_status( $settings['invoice_status'] );
			}

			// Send the invoice to Xero
			if ( $this->settings_are_valid() ) {
				return @$this->put_invoice( $invoice, $purchase_log );
			}
			else {
				do_action( 'wpsc_xero_invoice_creation_fail', $invoice, $purchase_log, NULL, __( 'Xero settings have not been configured', 'wpsc-xero' ) );
			}

		}
		catch( Exception $e ) {
			return false;
		}

	}

	/**
	* Handler for sending an invoice creation request to Xero once all processing has been completed.
	*
	* @since 1.0
	*
	* @param Xero_Invoice $invoice     Xero_Invoice object which the new invoice will be generated from
	* @param int          $payment_id	 ID of WPEC payment on which to base the Xero invoice.
	* @return SimpleXMLObject
	*/
	private function put_invoice( $invoice, $purchase_log ) {

		// Abort if a Xero_Invoice object was not passed
		if ( ! ( $invoice instanceof Xero_Invoice ) ) {
			return false;
		}

		// Prepare payload and API endpoint URL
		$xml = $invoice->get_xml();

		// Create oAuth object and send request
		try {

			// Load oauth lib
			$this->load_oauth_lib();

			// Create object and send to Xero
			$XeroOAuth = new XeroOAuth( $this->xero_config );

			$request	= $XeroOAuth->request( 'PUT', $XeroOAuth->url( 'Invoices', 'core' ), array(), $xml );
			$response	= $XeroOAuth->parseResponse( $request['response'], 'xml' );

			// Parse the response from Xero and fire appropriate actions
			if ( 200 == $request['code'] ) {
				do_action( 'wpsc_xero_invoice_creation_success', $invoice, (string) $response->Invoices->Invoice->InvoiceNumber, (string) $response->Invoices->Invoice->InvoiceID, $purchase_log );
			}
			else {
				do_action( 'wpsc_xero_invoice_creation_fail', $invoice, $purchase_log, $request );
			}

			return $response;

		}
		catch( Exception $e ) {
			do_action( 'wpsc_xero_invoice_creation_fail', $invoice, $purchase_log, $e );
		}

	}

	/**
	* Query the Xero API for a specific invoice by number (as opposed to the ID)
	*
	* @since 1.0
	*
	* @param int $invoice_number Automatically generated human friendly invoice number. EG "INV-123"
	* @return SimpleXMLObject
	*/
	private function get_invoice( $invoice_number ) {

		try {

			// Load oauth lib
			$this->load_oauth_lib();

			// Get Invoice via Xero API
			$XeroOAuth = new XeroOAuth( $this->xero_config );

			$request	= $XeroOAuth->request( 'GET', $XeroOAuth->url( 'Invoices/' . $invoice_number, 'core' ), array(), NULL );
			$response	= $XeroOAuth->parseResponse( $request['response'] ,'xml' );

			return $response;

		}
		catch( Exception $e ) {
			return false;
		}

	}

	/**
	* Private helper function to check whether Xero settings exist
	*
	* @since 1.0
	*
	* @return bool Returns true if valid data is configured, false if any fields are missing
	*/
	private function settings_are_valid() {

		// Get the plugin settings
		$settings = get_option('xero');

		// Define what is required
		$required = array(
			'sales_account', 'consumer_key', 'consumer_secret', 'private_key', 'public_key'
		);

		if ( isset( $settings['invoice_payments'] ) ) {
			$required[] = 'payments_account';
		}

		// Loop through and send back false if a required setting is missed
		foreach( $required as $setting ) {
			if ( !isset( $settings[ $setting ] ) || empty( $settings[ $setting ] ) ) {
				return false;
			}
		}

		return true;

	}

	/**
	* Function for prepending a line to Order Notes
	*
	* @since 1.0
	*
	* @param WPSC_Purchase_Log   $purchase_log  Purchase log object on which the note will be added
	* @param string              $note          The line of text to prepend to Order Notes
	* @return void
	*/
	private function prepend_note( $purchase_log, $note ) {

		global $wpdb;

		// Get existing notes
		$notes = $purchase_log->get( 'notes' );

		// Prepend new note
		$date = date( 'Y-m-d h:i A' );
		$notes = "[Xero] $note [$date]\n" . $notes;

		// Write notes back to WPSC_Purchase_Log instance
		$purchase_log->set( 'notes', $notes );

		// Write WPSC_Purchase_Log back to db
		$purchase_log->save();

	}

	/**
	* Private helper function to load oauth lib when a request is about to be made to Xero
	*
	* @since 0.8
	*
	* @return void
	*/
	private function load_oauth_lib() {

		// Don't load twice
		if ( class_exists( 'XeroOAuth' ) ) {
			return;
		}

		// Load Xero PHP library
		$path = trailingslashit( dirname( __FILE__ ) );

		require_once $path . 'lib/xero/oauth/_config.php';
		require_once $path . 'lib/xero/oauth/lib/OAuthSimple.php';
		require_once $path . 'lib/xero/oauth/lib/XeroOAuth.php';

		$this->xero_config = array_merge (

			array(
				'application_type' => XRO_APP_TYPE,
				'oauth_callback'   => OAUTH_CALLBACK,
				'user_agent'       => 'Plugify-wpsc-xero'
			),

			$signatures

		);

	}

}

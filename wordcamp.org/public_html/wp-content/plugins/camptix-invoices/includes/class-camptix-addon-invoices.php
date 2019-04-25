<?php
/**
 * Addon class that extends Camptix with invoicing functionalities.
 *
 * @package    Camptix_Invoices
 * @subpackage Camptix_invoices/includes
 */

/**
 * This class defines all code necessary to include invoices into Camptix.
 *
 * @package    Camptix_Invoices
 * @subpackage Camptix_invoices/includes
 */
class CampTix_Addon_Invoices extends \CampTix_Addon {

	/**
	 * Init invoice addon
	 */
	public function camptix_init() {
		global $camptix;
		global $camptix_invoice_custom_error;

		$camptix_invoice_custom_error = false;

		add_filter( 'camptix_setup_sections', array( __CLASS__, 'invoice_settings_tab' ) );
		add_action( 'camptix_menu_setup_controls', array( __CLASS__, 'invoice_settings' ) );
		add_filter( 'camptix_validate_options', array( __CLASS__, 'validate_options' ), 10, 2 );
		add_action( 'camptix_payment_result', array( __CLASS__, 'maybe_create_invoice' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_assets' ) );
		add_filter( 'camptix_checkout_attendee_info', array( __CLASS__, 'attendee_info' ) );
		add_action( 'camptix_notices', array( __CLASS__, 'error_flag' ), 0 );
		add_filter( 'camptix_form_register_complete_attendee_object', array( __CLASS__, 'attendee_object' ), 10, 2 );
		add_action( 'camptix_checkout_update_post_meta', array( __CLASS__, 'add_meta_invoice_on_attendee' ), 10, 2 );
		add_filter( 'camptix_metabox_attendee_info_additional_rows', array( __CLASS__, 'add_invoice_meta_on_attendee_metabox' ), 10, 2 );
	}

	/**
	 * Add a new tab in camptix settings.
	 *
	 * @param array $sections Sections of the Camptix settings.
	 */
	public static function invoice_settings_tab( $sections ) {
		$sections['invoice'] = __( 'Invoicing', 'wordcamporg' );
		return $sections;
	}

	/**
	 * Tab content.
	 *
	 * @param string $section Section.
	 */
	public static function invoice_settings( $section ) {
		if ( 'invoice' !== $section ) {
			return false;
		}//end if

		$opt = get_option( 'camptix_options' );
		add_settings_section( 'invoice', __( 'Invoices settings', 'wordcamporg' ), '__return_false', 'camptix_options' );
		global $camptix;

		$camptix->add_settings_field_helper(
			'invoice-active',
			__( 'Activate invoice requests', 'wordcamporg' ),
			'field_yesno',
			'invoice',
			// translators: %1$s is a date.
			sprintf( __( 'Allow ticket buyers to ask for an invoice when purchasing their tickets.', 'wordcamporg' ), date( 'Y' ) )
		);

		$camptix->add_settings_field_helper(
			'invoice-new-year-reset',
			__( 'Yearly reset', 'wordcamporg' ),
			'field_yesno',
			'invoice',
			// translators: %1$s is a date.
			sprintf( __( 'Invoice numbers are prefixed with the year, and will be reset on the 1st of January (e.g. %1$s-125)', 'wordcamporg' ), date( 'Y' ) )
		);

		add_settings_field(
			'invoice-date-format',
			__( 'Date format', 'wordcamporg' ),
			array( __CLASS__, 'date_format_callback' ),
			'camptix_options',
			'invoice',
			array(
				'id'    => 'invoice-date-format',
				'value' => ! empty( $opt['invoice-date-format'] ) ? $opt['invoice-date-format'] : 'd F Y',
			)
		);

		$camptix->add_settings_field_helper(
			'invoice-vat-number',
			__( 'VAT number', 'wordcamporg' ),
			'field_yesno',
			'invoice',
			// translators: %1$s is a date.
			sprintf( __( 'Add a "VAT Number" field to the invoice request form', 'wordcamporg' ), date( 'Y' ) )
		);

		add_settings_field(
			'invoice-logo',
			__( 'Logo', 'wordcamporg' ),
			array( __CLASS__, 'type_file_callback' ),
			'camptix_options',
			'invoice',
			array(
				'id'    => 'invoice-logo',
				'value' => ! empty( $opt['invoice-logo'] ) ? $opt['invoice-logo'] : '',
			)
		);

		$camptix->add_settings_field_helper( 'invoice-company', __( 'Company address', 'wordcamporg' ), 'field_textarea', 'invoice' );
		$camptix->add_settings_field_helper( 'invoice-tac', __( 'Terms and Conditions', 'wordcamporg' ), 'field_textarea', 'invoice' );
		$camptix->add_settings_field_helper( 'invoice-thankyou', __( 'Note below invoice total', 'wordcamporg' ), 'field_textarea', 'invoice' );
	}

	/**
	 * Date format setting callback.
	 *
	 * @param array $args Arguments.
	 */
	public static function date_format_callback( $args ) {

		$id          = $args['id'];
		$value       = $args['value'];
		$description = sprintf(
			// translators: %s is a date.
			__( 'Date format to use on the invoice, as a PHP Date formatting string (default \'d F Y\' formats dates as %s)', 'wordcamporg' ),
			date( 'd F Y' )
		);

		include CTX_INV_DIR . '/includes/views/date-format-field.php';
	}

	/**
	 * Input type file.
	 *
	 * @param object $args Arguments.
	 */
	public static function type_file_callback( $args ) {
		wp_enqueue_media();
		wp_enqueue_script( 'admin-camptix-invoices' );
		wp_localize_script(
			'admin-camptix-invoices',
			'camptixInvoiceBackVars',
			array(
				'selectText'  => __( 'Pick a logo to upload', 'wordcamporg' ),
				'selectImage' => __( 'Pick this logo', 'wordcamporg' ),
			)
		);

		$id    = $args['id'];
		$value = $args['value'];

		include CTX_INV_DIR . '/includes/views/logo-field.php';
	}

	/**
	 * Validate our custom options.
	 *
	 * @param object $output Output options.
	 * @param object $input  Input options.
	 */
	public static function validate_options( $output, $input ) {
		if ( isset( $input['invoice-active'] ) ) {
			$output['invoice-active'] = (int) $input['invoice-active'];
		}//end if
		if ( isset( $input['invoice-new-year-reset'] ) ) {
			$output['invoice-new-year-reset'] = (int) $input['invoice-new-year-reset'];
		}//end if
		if ( isset( $input['invoice-date-format'] ) ) {
			$output['invoice-date-format'] = $input['invoice-date-format'];
		}//end if
		if ( isset( $input['invoice-vat-number'] ) ) {
			$output['invoice-vat-number'] = (int) $input['invoice-vat-number'];
		}//end if
		if ( isset( $input['invoice-logo'] ) ) {
			$output['invoice-logo'] = (int) $input['invoice-logo'];
		}//end if
		if ( isset( $input['invoice-company'] ) ) {
			$output['invoice-company'] = sanitize_textarea_field( $input['invoice-company'] );
		}//end if
		if ( isset( $input['invoice-tac'] ) ) {
			$output['invoice-tac'] = sanitize_textarea_field( $input['invoice-tac'] );
		}//end if
		if ( isset( $input['invoice-thankyou'] ) ) {
			$output['invoice-thankyou'] = sanitize_textarea_field( $input['invoice-thankyou'] );
		}//end if
		return $output;
	}

	/**
	 * Listen payment result to create invoice.
	 *
	 * @param string $payment_token The payment token.
	 * @param int    $result        The result.
	 */
	public static function maybe_create_invoice( $payment_token, $result ) {
		if ( 2 !== $result ) {
			return;
		}//end if

		$attendees = get_posts(
			array(
				'posts_per_page' => -1,
				'post_type'      => 'tix_attendee',
				'post_status'    => 'any',
				'meta_query'     => array( // @codingStandardsIgnoreLine
					array(
						'key'     => 'tix_payment_token',
						'compare' => ' = ',
						'value'   => $payment_token,
						'type'    => 'CHAR',
					),
				),
			)
		);
		if ( ! $attendees ) {
			return;
		}//end if

		$metas = get_post_meta( $attendees[0]->ID, 'invoice_metas', true );
		if ( $metas ) {
			$order      = get_post_meta( $attendees[0]->ID, 'tix_order', true );
			$invoice_id = self::create_invoice( $attendees[0], $order, $metas );
			if ( ! is_wp_error( $invoice_id ) && ! empty( $invoice_id ) ) {
				self::send_invoice( $invoice_id );
			}//end if
		}//end if
	}

	/**
	 * Get, increment and return invoice number.
	 */
	public static function create_invoice_number() {
		$opt     = get_option( 'camptix_options' );
		$current = get_option( 'invoice_current_number', 1 );

		$year = date( 'Y' );
		if ( ! empty( $opt['invoice-new-year-reset'] ) ) {
			if ( ! empty( $opt['invoice-current-year'] ) && $opt['invoice-current-year'] !== $year ) {
				$current                     = 1;
				$opt['invoice-current-year'] = $year;
				update_option( 'camptix_options', $opt );
			}//end if
		}//end if

		/**
		 * Sets the current invoice number.
		 *
		 * @param int $current current invoice number.
		 */
		$current = apply_filters( 'tix_invoice_current_number', $current );
		update_option( 'invoice_current_number', $current + 1 );

		if ( empty( $opt['invoice-new-year-reset'] ) ) {
			return sprintf( '%s-%s', get_current_blog_id(), $current );
		} else {
			return sprintf( '%s-%s-%s', get_current_blog_id(), $year, $current );
		}
	}

	/**
	 * Create invoice.
	 *
	 * @param object $attendee The attendee.
	 * @param object $order    The order.
	 * @param object $metas    The metas.
	 *
	 * @todo Link invoice and corresponding attendees
	 */
	public static function create_invoice( $attendee, $order, $metas ) {
		$number         = self::create_invoice_number();
		$attendee_email = get_post_meta( $attendee->ID, 'tix_email', true );
		$txn_id         = get_post_meta( $attendee->ID, 'tix_transaction_id', true );

		// Prevent invoice_number from being assigned twice.
		remove_action( 'publish_tix_invoice', 'ctx_assign_invoice_number', 10 );

		// $txn_id may be null if no transaction was created (100% coupon used).
		if ( $txn_id ) {
			$invoice_title = sprintf(
				// translators: 1: invoice number, 2: email, 3: transaction id, 4. date.
				__( 'Invoice #%1$s for %2$s (order #%3$s) on %4$s', 'wordcamporg' ),
				$number,
				$attendee_email,
				$txn_id,
				get_the_time( 'd/m/Y', $attendee )
			);
		} else {
			$invoice_title = sprintf(
				// translators: 1: invoice number, 2: email, 3. date.
				__( 'Invoice #%1$s for %2$s on %3$s', 'wordcamporg' ),
				$number,
				$attendee_email,
				get_the_time( 'd/m/Y', $attendee )
			);
		}//end if

		$invoice = array(
			'post_type'   => 'tix_invoice',
			'post_status' => 'draft',
			'post_title'  => $invoice_title,
			'post_name'   => sprintf( 'invoice-%s', $number ),
		);

		$invoice_id = wp_insert_post( $invoice );
		if ( ! $invoice_id || is_wp_error( $invoice_id ) ) {
			return;
		}//end if
		update_post_meta( $invoice_id, 'invoice_number', $number );
		update_post_meta( $invoice_id, 'invoice_metas', $metas );
		update_post_meta( $invoice_id, 'original_order', $order );
		update_post_meta( $invoice_id, 'transaction_id', $txn_id );

		wp_update_post(
			array(
				'ID'          => $invoice_id,
				'post_status' => 'publish',
			)
		);

		return $invoice_id;
	}

	/**
	 * Send invoice by mail.
	 *
	 * @param int $invoice_id The invoice ID.
	 *
	 * @todo Add a template for $message in the settings.
	 */
	public static function send_invoice( $invoice_id ) {
		$invoice_metas = get_post_meta( $invoice_id, 'invoice_metas', true );
		if ( empty( $invoice_metas['email'] ) && is_email( $invoice_metas['email'] ) ) {
			return false;
		}//end if
		$invoice_pdf = ctx_get_invoice( $invoice_id );
		$attachments = array( $invoice_pdf );
		$opt         = get_option( 'camptix_options' );

		/* translators: The name of the event */
		$subject = apply_filters( 'camptix_invoices_mail_subject', sprintf( __( 'Your Invoice to %s', 'wordcamporg' ), $opt['event_name'] ), $opt['event_name'] );
		$from    = apply_filters( 'camptix_invoices_mail_from', get_option( 'admin_email' ) );
		$headers = apply_filters(
			'camptix_invoices_mail_headers',
			array(
				"From: {$opt['event_name']} <{$from}>",
				'Content-type: text/html; charset=UTF-8',
			)
		);
		$message = array(
			__( 'Hello,', 'wordcamporg' ),
			// translators: event name.
			sprintf( __( 'As requested during your purchase, please find attached an invoice for your tickets to "%s".', 'wordcamporg' ), sanitize_text_field( $opt['event_name'] ) ),
			// translators: email.
			sprintf( __( 'Please let us know if we can be of any further assistance at %s.', 'wordcamporg' ), $from ),
			__( 'Kind regards', 'wordcamporg' ),
			'',
			// translators: event name.
			sprintf( __( 'The %s team', 'wordcamporg' ), sanitize_text_field( $opt['event_name'] ) ),
		);
		$message = implode( PHP_EOL, $message );
		$message = '<p>' . nl2br( $message ) . '</p>';
		wp_mail( $invoice_metas['email'], $subject, $message, $headers, $attachments );
	}

	/**
	 * Create a PDF document for the given invoice.
	 *
	 * @param int $invoice_id The invoice ID.
	 */
	public static function create_invoice_document( $invoice_id ) {

		$camptix_opts   = get_option( 'camptix_options' );
		$invoice_number = get_post_meta( $invoice_id, 'invoice_number', true );
		$invoice_date   = get_the_date( $camptix_opts['invoice-date-format'], $invoice_id );
		$invoice_metas  = get_post_meta( $invoice_id, 'invoice_metas', true );
		$invoice_order  = get_post_meta( $invoice_id, 'original_order', true );

		$logo = CTX_INV_DIR . '/admin/images/wp-community-support.png';
		if ( ! empty( $camptix_opts['invoice-logo'] ) ) {
			$attachment = wp_get_attachment_image_src( $camptix_opts['invoice-logo'], 'full' );
			$logo       = $attachment[0];
		}

		$template = locate_template( 'invoice-template.php' ) ? locate_template( 'invoice-template.php' ) : CTX_INV_DIR . '/includes/views/invoice-template.php';

		ob_start();
		include $template;
		$invoice_content = ob_get_clean();

		if ( ! class_exists( 'WordCamp_Docs_PDF_Generator' ) ) {
			wp_die( esc_html__( 'WordCamp_Docs_PDF_Generator is missing', 'wordcamporg' ) );
		}

		$filename = get_post_meta( $invoice_id, 'invoice_document', true );
		if ( empty( $filename ) ) {
			$filename = $invoice_number . '-' . wp_generate_password( 12, false, false ) . '.pdf';
		}

		$pdf_generator = new WordCamp_Docs_PDF_Generator();
		$upload_dir    = wp_upload_dir();
		$tmp_path      = $pdf_generator->generate_pdf_from_string( $invoice_content, $filename );

		if ( ! empty( $upload_dir['basedir'] ) ) {
			$invoices_dirname = $upload_dir['basedir'] . '/camptix-invoices';
			if ( ! file_exists( $invoices_dirname ) ) {
				wp_mkdir_p( $invoices_dirname );
			}
		}

		rename( $tmp_path, $invoices_dirname . '/' . $filename );

		update_post_meta( $invoice_id, 'invoice_document', $filename );
	}

	/**
	 * Check whether the invoice has the required fields or not.
	 *
	 * @param int $invoice_id The invoice ID.
	 */
	public static function is_invoice_incomplete( $invoice_id ) {
		$invoice_metas = get_post_meta( $invoice_id, 'invoice_metas', true );
		$invoice_order = get_post_meta( $invoice_id, 'original_order', true );

		if ( empty( $invoice_metas['name'] ) ) {
			return true;
		}

		if ( empty( $invoice_metas['address'] ) ) {
			return true;
		}

		if ( empty( $invoice_order['items'] ) ) {
			return true;
		}

		foreach ( $invoice_order['items'] as $item ) {
			if ( empty( $item['quantity'] ) ) {
				return true;
			}
			if ( empty( $item['name'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Delete the invoice document of a given invoice.
	 *
	 * @param int $invoice_id The invoice ID.
	 */
	public static function delete_invoice_document( $invoice_id ) {
		$filename = get_post_meta( $invoice_id, 'invoice_document', true );
		if ( empty( $filename ) ) {
			return;
		}

		delete_post_meta( $invoice_id, 'invoice_document' );

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['basedir'] ) ) {
			$invoices_dirname = $upload_dir['basedir'] . '/camptix-invoices';
			$filename         = $invoices_dirname . '/' . $filename;
			if ( file_exists( $filename ) ) {
				wp_delete_file( $filename );
			}
		}
	}

	/**
	 * Format currency to display in invoice.
	 */
	public static function format_currency( $amount, $currency_key ) {

		$camptix_currencies = CampTix_Currency::get_currency_list();
		if ( isset( $camptix_currencies[ $currency_key ] ) === false ) {
			$currency_key = 'USD';
		}

		$currency = $camptix_currencies[ $currency_key ];

		if ( isset( $currency['locale'] ) === true ) {
			setlocale( LC_MONETARY, $currency['locale'] );
			$formatted_amount = money_format( "%.{$currency['decimal_point']}n", $amount );
		} elseif ( isset( $currency['format'] ) && $currency['format'] ) {
			$formatted_amount = sprintf( $currency['format'], number_format( $amount, $currency['decimal_point'] ) );
		} else {
			$formatted_amount = $currency_key . ' ' . number_format( $amount, $currency['decimal_point'] );
		}

		return $formatted_amount;
	}

	/**
	 * Enqueue assets
	 *
	 * @todo enqueue only on [camptix] shortcode
	 */
	public static function enqueue_assets() {

		$opt = get_option( 'camptix_options' );
		if ( ! empty( $opt['invoice-active'] ) ) {

			wp_register_script( 'camptix-invoices', CTX_INV_ADMIN_URL . '/js/camptix-invoices.js', array( 'jquery' ), CTX_INV_VER, true );
			wp_enqueue_script( 'camptix-invoices' );

		}//end if

		wp_register_style( 'camptix-invoices-css', CTX_INV_ADMIN_URL . '/css/camptix-invoices.css', array(), CTX_INV_VER );
		wp_enqueue_style( 'camptix-invoices-css' );
	}

	/**
	 * Register assets on admin side
	 */
	public static function admin_enqueue_assets() {
		wp_register_script( 'admin-camptix-invoices', CTX_INV_ADMIN_URL . '/js/camptix-invoices-back.js', array( 'jquery' ), CTX_INV_VER, true );
	}

	/**
	 * Attendee invoice information
	 * (also check for missing invoice infos).
	 *
	 * @param array $attendee_info The attendee info.
	 */
	public static function attendee_info( $attendee_info ) {

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		global $camptix;
		if ( empty( $_POST['camptix-need-invoice'] ) ) {
			return $attendee_info;
		}//end if

		if ( empty( $_POST['invoice-email'] )
			|| empty( $_POST['invoice-name'] )
			|| empty( $_POST['invoice-address'] )
			|| ! is_email( wp_unslash( $_POST['invoice-email'] ) ) ) {

			$camptix->error_flag( 'nope' );

		} else {

			$attendee_info['invoice-email']   = sanitize_email( wp_unslash( $_POST['invoice-email'] ) );
			$attendee_info['invoice-name']    = sanitize_text_field( wp_unslash( $_POST['invoice-name'] ) );
			$attendee_info['invoice-address'] = sanitize_textarea_field( wp_unslash( $_POST['invoice-address'] ) );

			$opt = get_option( 'camptix_options' );

			if ( ! empty( $opt['invoice-vat-number'] ) ) {
				$attendee_info['invoice-vat-number'] = sanitize_text_field( wp_unslash( $_POST['invoice-vat-number'] ) );
			}//end if
		}//end if

		// phpcs:enable
		return $attendee_info;
	}

	/**
	 * Define custom attributes for an attendee object.
	 *
	 * @param object $attendee      The attendee.
	 * @param array  $attendee_info The attendee info.
	 */
	public static function attendee_object( $attendee, $attendee_info ) {
		if ( ! empty( $attendee_info['invoice-email'] ) ) {
			$attendee->invoice = array(
				'email'   => $attendee_info['invoice-email'],
				'name'    => $attendee_info['invoice-name'],
				'address' => $attendee_info['invoice-address'],
			);

			$opt = get_option( 'camptix_options' );
			if ( ! empty( $opt['invoice-vat-number'] ) ) {
				$attendee->invoice['vat-number'] = $attendee_info['invoice-vat-number'];
			}//end if
		}//end if
		return $attendee;
	}

	/**
	 * Add Invoice meta on an attendee post.
	 *
	 * @param int    $post_id  The post ID.
	 * @param object $attendee The attendee.
	 */
	public static function add_meta_invoice_on_attendee( $post_id, $attendee ) {

		if ( ! empty( $attendee->invoice ) ) {
			update_post_meta( $post_id, 'invoice_metas', $attendee->invoice );
			global $camptix;
			$camptix->log( __( 'This attendee requested an invoice.', 'wordcamporg' ), $post_id, $attendee->invoice );
		}//end if
	}

	/**
	 * My custom errors flags.
	 */
	public static function error_flag() {

		global $camptix;
		/**
		 * Hack
		 */
		$rp = new ReflectionProperty( 'CampTix_Plugin', 'error_flags' );
		$rp->setAccessible( true );
		$error_flags = $rp->getValue( $camptix );
		if ( ! empty( $error_flags['nope'] ) ) {
			$camptix->error( __( 'As you have requested an invoice, please fill in the required fields.', 'wordcamporg' ) );
		}//end if
	}

	/**
	 * Display invoice meta on attendee admin page.
	 *
	 * @param array  $rows The rows.
	 * @param object $post The post.
	 */
	public static function add_invoice_meta_on_attendee_metabox( $rows, $post ) {
		$invoice_meta = get_post_meta( $post->ID, 'invoice_metas', true );
		if ( ! empty( $invoice_meta ) ) {
			$rows[] = array( __( 'Requested an invoice', 'wordcamporg' ), __( 'Yes', 'wordcamporg' ) );
			$rows[] = array( __( 'Invoice recipient', 'wordcamporg' ), $invoice_meta['name'] );
			$rows[] = array( __( 'Invoice to be sent to', 'wordcamporg' ), $invoice_meta['email'] );
			$rows[] = array( __( 'Customer address', 'wordcamporg' ), $invoice_meta['address'] );

			$opt = get_option( 'camptix_options' );
			if ( ! empty( $opt['invoice-vat-number'] ) ) {
				$rows[] = array( __( 'VAT number', 'wordcamporg' ), $invoice_meta['vat-number'] );
			}//end if
		} else {
			$rows[] = array( __( 'Requested an invoice', 'wordcamporg' ), __( 'No', 'wordcamporg' ) );
		}//end if
		return $rows;
	}
}

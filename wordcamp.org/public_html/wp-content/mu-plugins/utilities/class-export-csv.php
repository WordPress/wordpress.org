<?php

namespace WordCamp\Utilities;
defined( 'WPINC' ) || die();

/**
 * Class Export_CSV
 */
class Export_CSV {
	/**
	 * @var string The name of the CSV file.
	 */
	protected $filename = '';

	/**
	 * @var array The column headers for the CSV file.
	 */
	protected $header_row = array();

	/**
	 * @var array The data rows for the CSV file.
	 */
	protected $data_rows = array();

	/**
	 * @var \WP_Error|null Container for errors.
	 */
	public $error = null;


	public function __construct( array $options = array() ) {
		$this->error = new \WP_Error();

		$options = wp_parse_args( $options, array(
			'filename' => array(),
			'headers'  => array(),
			'data'     => array(),
		) );

		if ( ! empty( $options['filename'] ) ) {
			$this->set_filename( $options['filename'] );
		}

		if ( ! empty( $options['headers'] ) ) {
			$this->set_column_headers( $options['headers'] );
		}

		if ( ! empty( $options['data'] ) ) {
			$this->add_data_rows( $options['data'] );
		}
	}

	/**
	 * Specify the name for the CSV file.
	 *
	 * This method takes an array of string segments that will be concatenated into a single file name string.
	 * It is not necessary to include the file name suffix (.csv).
	 *
	 * Example:
	 *
	 *   array( 'Payment Activity', '2017-01-01', '2017-12-31' )
	 *
	 *   will become:
	 *
	 *   payment-activity_2017-01-01_2017-12-31.csv
	 *
	 * @param array|string $name_segments One or more string segments that will comprise the CSV file name.
	 *
	 * @return bool True if the file name was successfully set. Otherwise false.
	 */
	public function set_filename( $name_segments ) {
		if ( ! is_array( $name_segments ) ) {
			$name_segments = (array) $name_segments;
		}

		$name_segments = array_map( function( $segment ) {
			$segment = strtolower( $segment );
			$segment = str_replace( '_', '-', $segment );
			$segment = sanitize_file_name( $segment );
			$segment = str_replace( '.csv', '', $segment );

			return $segment;
		}, $name_segments );

		if ( ! empty( $name_segments ) ) {
			$this->filename = implode( '_', $name_segments ) . '.csv';

			return true;
		}

		return false;
	}

	/**
	 * Set the first row of the CSV file as headers for each column.
	 *
	 * If used, this also determines how many columns each row should have. Note that, while optional, this method
	 * must be used before data rows are added.
	 *
	 * @param array $headers The column header strings.
	 *
	 * @return bool True if the column headers were successfully set. Otherwise false.
	 */
	public function set_column_headers( array $headers ) {
		if ( ! empty( $this->data_rows ) ) {
			$this->error->add(
				'csv_error',
				'Column headers cannot be set after data rows have been added.'
			);

			return false;
		}

		$this->header_row = array_map( 'sanitize_text_field', $headers );

		return true;
	}

	/**
	 * Add a single row of data to the CSV file.
	 *
	 * @param array $row A single row of data.
	 *
	 * @return bool True if the data row was successfully added. Otherwise false.
	 */
	public function add_row( array $row ) {
		$column_count = 0;

		if ( ! empty( $this->header_row ) ) {
			$column_count = count( $this->header_row );
		} elseif ( ! empty( $this->data_rows ) ) {
			$column_count = count( $this->data_rows[0] );
		}

		if ( $column_count && count( $row ) !== $column_count ) {
			$this->error->add(
				'csv_error',
				sprintf(
					'Could not add row because it has %d columns, when it should have %d.',
					absint( count( $row ) ),
					absint( $column_count )
				)
			);

			return false;
		}

		$this->data_rows[] = array_map( 'sanitize_text_field', $row );

		return true;
	}

	/**
	 * Wrapper method for adding multiple data rows at once.
	 *
	 * @param array $data
	 *
	 * @return void
	 */
	public function add_data_rows( array $data ) {
		foreach ( $data as $row ) {
			$result = $this->add_row( $row );

			if ( ! $result ) {
				break;
			}
		}
	}

	/**
	 * Escape an array of strings to be used in a CSV context.
	 *
	 * Malicious input can inject formulas into CSV files, opening up the possibility for phishing attacks,
	 * information disclosure, and arbitrary command execution.
	 *
	 * @see http://www.contextis.com/resources/blog/comma-separated-vulnerabilities/
	 * @see https://hackerone.com/reports/72785
	 *
	 * Derived from CampTix_Plugin::esc_csv.
	 *
	 * Note that this method is not recursive, so should only be used for individual data rows, not an entire data set.
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	public static function esc_csv( array $fields ) {
		$active_content_triggers = array( '=', '+', '-', '@' );

		/*
		 * Formulas that follow all common delimiters need to be escaped, because the user may choose any delimiter
		 * when importing a file into their spreadsheet program. Different delimiters are also used as the default
		 * in different locales. For example, Windows + Russian uses `;` as the delimiter, rather than a `,`.
		 *
		 * The file encoding can also effect the behavior; e.g., opening/importing as UTF-8 will enable newline
		 * characters as delimiters.
		 */
		$delimiters = array(
			',', ';', ':', '|', '^',
			"\n", "\t", " "
		);

		foreach( $fields as $index => $field ) {
			// Escape trigger characters at the start of a new field
			$first_cell_character = mb_substr( $field, 0, 1 );
			$is_trigger_character = in_array( $first_cell_character, $active_content_triggers, true );
			$is_delimiter         = in_array( $first_cell_character, $delimiters,              true );

			if ( $is_trigger_character || $is_delimiter ) {
				$field = "'" . $field;
			}

			// Escape trigger characters that follow delimiters
			foreach ( $delimiters as $delimiter ) {
				foreach ( $active_content_triggers as $trigger ) {
					$field = str_replace( $delimiter . $trigger, $delimiter . "'" . $trigger, $field );
				}
			}

			$fields[ $index ] = $field;
		}

		return $fields;
	}

	/**
	 * Generate the contents of the CSV file.
	 *
	 * @return string
	 */
	protected function generate_file_content() {
		if ( empty( $this->data_rows ) ) {
			$this->error->add(
				'csv_error',
				'No data.'
			);

			return '';
		}

		ob_start();

		$csv = fopen( 'php://output', 'w' );

		if ( ! empty( $this->header_row ) ) {
			fputcsv( $csv, self::esc_csv( $this->header_row ) );
		}

		foreach ( $this->data_rows as $row ) {
			fputcsv( $csv, self::esc_csv( $row ) );
		}

		fclose( $csv );

		return ob_get_clean();
	}

	/**
	 * Output the CSV file, or a text file with error messages.
	 */
	public function emit_file() {
		if ( ! $this->filename ) {
			$this->error->add(
				'csv_error',
				'Could not generate a CSV file without a file name.'
			);
		}

		$content = $this->generate_file_content();

		header( 'Cache-control: private' );
		header( 'Pragma: private' );
		header( 'Expires: Mon, 26 Jul 1997 05:00:00 GMT' ); // As seen in CampTix_Plugin::summarize_admin_init.

		if ( ! empty( $this->error->get_error_messages() ) ) {
			header( 'Content-Type: text' );
			header( 'Content-Disposition: attachment; filename="error.txt"' );

			foreach ( $this->error->get_error_codes() as $code ) {
				foreach ( $this->error->get_error_messages( $code ) as $message ) {
					echo "$code: $message\n";
				}
			}

			die();
		}

		header( 'Content-Type: text/csv' );
		header( sprintf( 'Content-Disposition: attachment; filename="%s"', sanitize_file_name( $this->filename ) ) );

		echo $content;

		die();
	}

	/**
	 * Save the CSV file to a local directory.
	 *
	 * @param string $location The path of the directory to save the file in.
	 *
	 * @return bool|string
	 */
	public function save_file( $location ) {
		if ( ! $this->filename ) {
			$this->error->add(
				'csv_error',
				'Could not generate a CSV file without a file name.'
			);
		}

		if ( ! wp_is_writable( $location ) ) {
			$this->error->add(
				'filesystem_error',
				'The specified location is not writable.'
			);

			return false;
		}

		$full_path = trailingslashit( $location ) . $this->filename;
		$content   = $this->generate_file_content();

		$file = fopen( $full_path, 'w' );
		fwrite( $file, $content );
		fclose( $file );

		return $full_path;
	}
}

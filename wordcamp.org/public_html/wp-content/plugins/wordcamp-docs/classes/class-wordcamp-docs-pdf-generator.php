<?php
/**
 * Helper class to generate PDFs from HTML strings or files using wkhtmltopdf.
 *
 * Thanks to Viper007Bond ( http://www.viper007bond.com )
 */
class WordCamp_Docs_PDF_Generator {

	/**
	 * Full path to your tmp folder where files should be created. Include a trailing slash.
	 * You should probably just leave this as "/tmp/" as you should move your PDF on your own.
	 *
	 * @var string
	 */
	public $tmp_folder_base = '/tmp/';

	/**
	 * Stores a unique folder name that the class instance will work out of.
	 * Generated dynamically using uniqid().
	 *
	 * @var string
	 */
	public $working_folder_name;

	/**
	 * Constructor. No parameters.
	 */
	function __construct() {
		$this->working_folder_name = uniqid( 'wcdocs_' );
	}

	/**
	 * Generates a PDF from a string of HTML.
	 *
	 * @param string $string   A string of HTML.
	 * @param string $filename Filename of the PDF, including the extension.
	 * @param array  $args     Optional. An associative array of additional parameters.
	 *                         assets:  optional array of full paths to local assets (images for example).
	 *                         dpi:     optional integer to be passed directly to wkhtmltopdf.
	 *                         margins: optional array of 4 integers specifying PDF margins. Top, right, bottom, left.
	 *
	 * @return string The path to the generated PDF.
	 */
	public function generate_pdf_from_string( $string, $filename, $args = array() ) {
		$file = $this->write_file( $filename . '.html', $string );
		return $this->generate_pdf_from_file( $file, $filename, $args );
	}

	/**
	 * Generates a PDF from an HTML file.
	 *
	 * @param string  $source_file Full path to the HTML file.
	 * @param  string $filename    Filename of the PDF, including the extension.
	 * @param array   $args        Optional. An associative array of additional parameters.
	 *                             assets:  optional array of full paths to local assets (images for example).
	 *                             dpi:     optional integer to be passed directly to wkhtmltopdf.
	 *                             margins: optional array of 4 integers specifying PDF margins. Top, right, bottom, left.
	 *
	 * @return string The path to the generated PDF.
	 */
	public function generate_pdf_from_file( $source_file, $filename, $args = array() ) {
		if ( ! empty( $args['assets'] ) && is_array( $args['assets'] ) ) {
			foreach ( $args['assets'] as $asset ) {
				copy( $asset, $this->get_tmp_folder( basename( $asset ) ) );
			}
		}

		$dpi = ( ! empty( $args['dpi'] ) ) ? absint( $args['dpi'] ) : 300;

		$margins = ( ! empty( $args['margins'] ) && is_array( $args['margins'] ) && 4 == count( $args['margins'] ) ) ? $args['margins'] : array( 0, 0, 0, 0 );

		$file = $this->get_tmp_folder( $filename );

		$command = sprintf(
			'wkhtmltopdf -d %d -T %s -R %s -B %s -L %s %s %s',
			$dpi,
			escapeshellarg( $margins[0] ),
			escapeshellarg( $margins[1] ),
			escapeshellarg( $margins[2] ),
			escapeshellarg( $margins[3] ),
			escapeshellarg( $source_file ),
			escapeshellarg( $file )
		);

		exec( $command );

		return $file;
	}

	/**
	 * Serves a file from the tmp folder up to the browser.
	 * Does NOT exit when finished so that you can still call
	 * A8C_PDF_Generator::delete_tmp_folder() when you're all done.
	 *
	 * @param string $filename The filename within this instance's temporary folder to serve up.
	 * @param bool $download Whether to prompt downloading or serve it natively within the browser.
	 */
	public function serve_pdf_to_browser( $filename, $download = false ) {
		$filename = basename( $filename );

		$file = $this->get_tmp_folder( $filename );

		nocache_headers();

		if ( ! file_exists( $file ) ) {
			status_header( 404 );
			echo 'File not found.';
		}

		header( 'Content-Type: application/pdf' );

		if ( $download ) {
			header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
		}

		echo file_get_contents( $file );
	}

	/**
	 * Writes out a file to this instance's temporary file.
	 *
	 * @param string $filename The filename (not the path) that should be written to.
	 * @param string $data The contents of the file, such as HTML.
	 *
	 * @return string The full path to the newly created file.
	 */
	public function write_file( $filename, $data ) {
		if ( ! is_dir( $this->get_tmp_folder() ) ) {
			mkdir( $this->get_tmp_folder() );
		}

		$path = $this->get_tmp_folder( $filename );

		file_put_contents( $path, $data );

		return $path;
	}

	/**
	 * Returns the path to this instance's temporary folder.
	 * Optionally you can include a filename that will be relative to that path.
	 *
	 * @param string $filename Optional. Filename to be appended onto the end of the path.
	 *
	 * @return string Full path to the temporary folder or file.
	 */
	public function get_tmp_folder( $filename = '' ) {
		return trailingslashit( $this->tmp_folder_base ) . $this->working_folder_name . '/' . $filename;
	}

	/**
	 * Run this when you're all done. Deletes the instance's temporary folder and all files within it.
	 * You should make sure to move the generated PDF out of the folder first.
	 *
	 * @return bool True on success or false on failure.
	 */
	public function delete_tmp_folder() {
		$tmp_folder = $this->get_tmp_folder();

		$files = array_diff( scandir( $tmp_folder ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			unlink( $tmp_folder . $file );
		}

		return rmdir( $tmp_folder );
	}
}

<?php
/**
 * Make sure your custom template implements this interface.
 */
interface WordCamp_Docs_Template {

	/**
	 * This is the name that will be displayed in the WordCamp Docs UI.
	 */
	public function get_name();

	/**
	 * This is the PDF filename which will be used when serving the
	 * generated PDF file to the browser.
	 */
	public function get_filename();

	/**
	 * This function will be called with POST data. It should render a
	 * form for the WordCamp Docs UI.
	 *
	 * @param array $data POST-ed data (if any)
	 */
	public function form( $data );

	/**
	 * This function is called with the POST-ed data, should return
	 * clean input.
	 *
	 * @param array $input POST-ed data.
	 */
	public function sanitize( $input );

	/**
	 * This function is called when generating a PDF, should return
	 * HTML and CSS. You can use ob_* functions for convenience.
	 *
	 * @param array $input POST-ed and self::sanitized() data.
	 */
	public function render( $data );

	/**
	 * This function should return an array of absolute paths to assets.
	 */
	public function get_assets();
}
<?php

namespace WordPressdotorg\Rosetta\Admin;

interface Admin_Page_View {

	/**
	 * Gets the title of the admin page.
	 *
	 * @return string The title.
	 */
	public function get_title();

	/**
	 * Renders the admin page.
	 */
	public function render();

	/**
	 * Sets associated page controller.
	 *
	 * @param \WordPressdotorg\Rosetta\Admin\Admin_Page $page The page instance.
	 */
	public function set_page( Admin_Page $page );
}

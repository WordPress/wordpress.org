<?php

namespace WordPressdotorg\Rosetta\Filter;

class Options {

	/**
	 * @var Option[]
	 */
	private $options = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
	}

	/**
	 * @param Option $option The option.
	 */
	public function add_option( Option $option ) {
		$this->options[] = $option;
	}

	/**
	 * Registers the filters.
	 */
	public function setup() {
		foreach ( $this->options as $option ) {
			add_filter(
				"pre_option_{$option->get_name()}",
				$option->get_callback(),
				$option->get_priority(),
				$option->get_num_args()
			);
		}
	}
}

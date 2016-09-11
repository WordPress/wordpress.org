<?php

namespace WordPressdotorg\Rosetta\Jetpack;

class Module_Manager {

	/**
	 * Array of Jetpack modules.
	 *
	 * @var array
	 */
	private $modules;

	/**
	 * Whether modules should be auto activated.
	 *
	 * @var bool
	 */
	private $auto_activate_modules;

	/**
	 * Constructor.
	 *
	 * @param array $modules       Array of modules.
	 * @param bool  $auto_activate Optional. Whether modules should be auto activated. Default true.
	 */
	public function __construct( array $modules, $auto_activate = true ) {
		$this->modules = $modules;
		$this->auto_activate_modules = $auto_activate;
	}

	/**
	 * Registers the filters.
	 */
	public function setup() {
		add_filter( 'jetpack_get_available_modules', [ $this, 'filter_available_modules' ] );

		if ( $this->auto_activate_modules ) {
			add_filter( 'jetpack_get_default_modules', [ $this, 'filter_default_modules' ], 10, 0 );
		}
	}

	/**
	 * Filters available modules.
	 *
	 * @param $modules Array of modules.
	 * @return array Array of filtered modules.
	 */
	public function filter_available_modules( $modules ) {
		$filtered_modules = [];

		foreach ( $this->modules as $module_name ) {
			if ( isset( $modules[ $module_name ] ) ) {
				$filtered_modules[ $module_name ] = $modules[ $module_name ];
			}
		}

		return $filtered_modules;
	}

	/**
	 * Filters auto activated modules.
	 *
	 * @return array Array of filtered modules.
	 */
	public function filter_default_modules() {
		return $this->modules;
	}
}

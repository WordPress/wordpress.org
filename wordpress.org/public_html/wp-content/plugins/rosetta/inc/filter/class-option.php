<?php

namespace WordPressdotorg\Rosetta\Filter;

class Option {

	/**
	 * The name of the option.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * The callback which filters the option.
	 *
	 * @var callable
	 */
	private $callback;

	/**
	 * The filter priority.
	 *
	 * @see add_filter()
	 *
	 * @var int
	 */
	private $priority = 10;

	/**
	 * The number of arguments which should be passed to the callback.
	 *
	 * @see add_filter()
	 *
	 * @var int
	 */
	private $num_args;

	/**
	 * Retrieves the name of this option.
	 *
	 * @return string The name.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Sets the name of this option.
	 *
	 * @param string $name The name.
	 * @return Option $this This option.
	 */
	public function set_name( $name ) {
		$this->name = $name;
		return $this;
	}

	/**
	 * Retrieves the callback of this option.
	 *
	 * @return callable The callback.
	 */
	public function get_callback() {
		return $this->callback;
	}

	/**
	 * Sets the callback for this option.
	 *
	 * @param callable $callback The callback.
	 * @return Option $this
	 */
	public function set_callback( $callback ) {
		$this->callback = $callback;
		return $this;
	}

	/**
	 * Retrieves the priority of this option.
	 *
	 * @return int The priority.
	 */
	public function get_priority() {
		return $this->priority;
	}

	/**
	 * Sets the priority of this option.
	 *
	 * @param int $priority The priority.
	 * @return Option $this This option.
	 */
	public function set_priority( $priority ) {
		$this->priority = $priority;
		return $this;
	}

	/**
	 * Retrieves the number of arguments for the callback.
	 *
	 * @return int The number of arguments.
	 */
	public function get_num_args() {
		return $this->num_args;
	}

	/**
	 * Sets the number of arguments for the callback.
	 *
	 * @param int $num_args The number of arguments.
	 * @return Option $this This option.
	 */
	public function set_num_args( $num_args ) {
		$this->num_args = $num_args;
		return $this;
	}
}

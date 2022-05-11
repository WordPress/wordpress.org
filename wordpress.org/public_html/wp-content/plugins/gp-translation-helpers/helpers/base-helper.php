<?php
/**
 * Base class, extended by all other helpers
 *
 * @package gp-translation-helpers
 * @since 0.0.1
 */
class GP_Translation_Helper {

	/**
	 * The folder where the assets are stored.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	public $assets_dir;

	/**
	 * The data coming from the route.
	 *
	 * @since 0.0.1
	 * @var array
	 */
	public $data;

	/**
	 * GP_Translation_Helper constructor.
	 *
	 * Will throw a LogicException if the title property is not set.
	 *
	 * @since 0.0.1
	 *
	 * @throws LogicException If the class has not a must-have property.
	 */
	final public function __construct() {
		$this->assets_dir = dirname( dirname( __FILE__ ) ) . '/helpers-assets/';

		$required_properties = array(
			'title',
		);

		foreach ( $required_properties as $prop ) {
			if ( ! isset( $this->{$prop} ) ) {
				throw new LogicException( get_class( $this ) . ' must have a property ' . $prop );
			}
		}

		if ( method_exists( $this, 'after_constructor' ) ) {
			$this->after_constructor();
		}
	}

	/**
	 * Sets the data coming from the route.
	 *
	 * Sets values like project_id, locale_slug, set_slug, original_id, translation_id, etc.
	 *
	 * @since 0.0.1
	 *
	 * @param array $args   Data coming from the route.
	 */
	public function set_data( array $args ) {
		$this->data = $args;
	}

	/**
	 * Gets the priority of a helper. Defaults to 1 if not set.
	 *
	 * @since 0.0.1
	 *
	 * @return int
	 */
	public function get_priority(): int {
		return $this->priority ?? 1;
	}

	/**
	 * Indicates whether the helper loads asynchronous content or not.
	 *
	 * Defaults to false, but uses the class property if set.
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public function has_async_content(): bool {
		return $this->has_async_content ?? false;
	}
	/**
	 * Indicates whether the helper should initally load inline
	 *
	 * @since 0.0.2
	 *
	 * @return bool
	 */
	public function load_inline(): bool {
		return $this->load_inline ?? false;
	}

	/**
	 * Gets the class name for the helper div.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function get_div_classname(): string {
		if ( isset( $this->classname ) ) {
			return $this->classname;
		}

		return sanitize_html_class( str_replace( '_', '-', strtolower( get_class( $this ) ) ), 'default-translation-helper' );
	}

	/**
	 * Gets the HTML id for the div.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function get_div_id(): string {
		$div_id = $this->get_div_classname() . '-' . $this->data['original_id'];

		if ( isset( $this->data['translation_id'] ) ) {
			$div_id .= '-' . $this->data['translation_id'];
		} elseif ( isset( $this->data['translation'] ) ) {
			$div_id .= '-' . $this->data['translation']->id;
		}

		return $div_id;
	}

	/**
	 * Returns the title of the helper.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function get_title(): string {
		return $this->title;
	}

	/**
	 * Indicates whether the helper should be active or not.
	 *
	 * Overwrite in the inheriting class to make this vary depending on class args.
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public function activate() {
		return true;
	}

	/**
	 * Sets the count of items returned by the helper.
	 *
	 * @since 0.0.1
	 *
	 * @param mixed $list   Elements to count.
	 */
	public function set_count( $list ) {
		if ( is_array( $list ) ) {
			$this->count = count( $list );
		} else {
			$this->count = $list ? 1 : 0;
		}
	}

	/**
	 * Gets the number of items returned by the helper.
	 *
	 * @since 0.0.1
	 *
	 * @return int
	 */
	public function get_count(): int {
		return $this->count ?? 0;
	}

	/**
	 * Gets the content/string to return when a helper has no results.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function empty_content() {
		return __( 'No results found.' );
	}

	/**
	 * Default callback to render items returned by the helper.
	 *
	 * Gets an unordered list of the items that will be rendered by the helper.
	 *
	 * @since 0.0.1
	 *
	 * @param array $items  Elements to be grouped in an unordered list.
	 *
	 * @return string
	 */
	public function async_output_callback( array $items ) {
		$output = '<ul>';
		foreach ( $items as $item ) {
			$output .= '<li>' . $item . '</li>';
		}
		$output .= '</ul>';
		return $output;
	}

	/**
	 * Gets content that is returned asynchronously.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function get_async_output(): string {
		$items = $this->get_async_content();
		$this->set_count( $items );

		if ( ! $items ) {
			return $this->empty_content();
		}

		return $this->async_output_callback( $items );
	}

	/**
	 * Gets the (non-async) output for the helper.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function get_output(): string {
		if ( ! $this->load_inline() ) {
			return '<div class="loading">Loading&hellip;</div>';
		}
		return $this->async_output_callback( $this->get_async_content() );
	}

	/**
	 * Gets additional CSS required by the helper.
	 *
	 * @since 0.0.1
	 *
	 * @return bool|string
	 */
	public function get_css() {
		return false;
	}

	/**
	 * Gets additional JavaScript required by the helper.
	 *
	 * @since 0.0.1
	 *
	 * @return bool|string
	 */
	public function get_js() {
		return false;
	}
}

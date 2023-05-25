<?php
namespace WordPressdotorg\Plugin_Directory\Standalone;

/**
 * Holds the information about the request payload, and massages it into a format we can use.
 */
class Plugins_Info_API_Request {
	public $locale              = 'en_US';
	protected $args             = array();
	protected $requested_fields = array();

	static $fields = array(
		'active_installs'     => false,
		'added'               => false,
		'banners'             => false,
		'compatibility'       => false,
		'contributors'        => false,
		'description'         => false,
		'donate_link'         => false,
		'downloaded'          => false,
		'download_link'       => false,
		'homepage'            => false,
		'icons'               => false,
		'last_updated'        => false,
		'rating'              => false,
		'ratings'             => false,
		'reviews'             => false, // NOTE: sub-key of 'sections'.
		'requires'            => false,
		'requires_php'        => false,
		'sections'            => false,
		'short_description'   => false,
		'tags'                => false,
		'tested'              => false,
		'stable_tag'          => false,
		'blocks'              => false,
		'block_assets'        => false,
		'author_block_count'  => false,
		'author_block_rating' => false,
		'language_packs'      => false,
		'versions'            => false,
		'screenshots'         => false,
	);

	static $plugins_info_fields_defaults = array(
		'added'             => true,
		'compatibility'     => true,
		'contributors'      => false,
		'bare_contributors' => true,
		'downloaded'        => true,
		'download_link'     => true,
		'donate_link'       => true,
		'homepage'          => true,
		'last_updated'      => true,
		'rating'            => true,
		'ratings'           => true,
		'requires'          => true,
		'requires_php'      => true,
		'sections'          => true,
		'tags'              => true,
		'tested'            => true,
		'versions'          => true,
		'screenshots'       => true,
	);

	// Alterations made to default fields in the info/1.2 API.
	static $plugins_info_fields_defaults_12 = array(
		'downloaded'        => false,
		'bare_contributors' => false,
		'compatibility'     => false,
		'description'       => false,
		'banners'           => true,
		'reviews'           => true,
		'active_installs'   => true,
		'contributors'      => true,
	);

	static $query_plugins_fields_defaults = array(
		'added'             => true,
		'compatibility'     => true,
		'downloaded'        => true,
		'description'       => true,
		'download_link'     => true,
		'donate_link'       => true,
		'homepage'          => true,
		'last_updated'      => true,
		'rating'            => true,
		'ratings'           => true,
		'requires'          => true,
		'requires_php'      => true,
		'sections'          => true,
		'short_description' => true,
		'tags'              => true,
		'tested'            => true,
		'versions'          => true,
		'screenshots'       => true,
	);

	// Alterations made to the default fields in the info/1.2 API.
	static $query_plugins_fields_defaults_12 = array(
		'compatibility'   => false,
		'sections'        => false,
		'contributors'    => false,
		'versions'        => false,
		'screenshots'     => false,
		'last_updated'    => true,
		'icons'           => true,
		'active_installs' => true,
	);

	// Default fields for block queries
	static $query_plugins_fields_defaults_block = array(
		'blocks'               => true,
		'block_assets'         => true,
		'block_translations'   => true,
		'author_block_count'   => true,
		'author_block_rating'  => true,
	);

	// Typo/incorrect field name transforms.
	static $field_aliases = array(
		// from => to
		'downloadlink' => 'download_link', // Incorrectly documented in plugins_api().
	);

	// Fields that affect other fields.
	// If the key is disabled, disable all of the values here unless client turns them on.
	static $field_interconnected = array(
		'sections' => [ 'reviews' ], // If sections is disabled, reviews should be disabled unless explicitly enabled.
	);

	public function __construct( $args ) {
		$args = (object) $args;

		if ( ! empty( $args->locale ) ) {
			$this->locale = $args->locale; // TODO: sanitize?
		}
		if ( ! empty( $args->fields ) ) {
			$this->requested_fields = $this->parse_requested_fields( $args->fields );
		}
		unset( $args->locale, $args->fields );

		$this->args = $args;
	}

	public function __get( $field ) {
		if ( isset( $this->args->{$field} ) ) {
			return $this->args->{$field};
		}
		return null;
	}

	public function __set( $field, $value ) {
		$this->args->{$field} = $value;
	}

	public function __unset( $field ) {
		unset( $this->args->{$field} );
	}

	public function get_expected_fields( $method ) {
		$fields = self::$fields;

		if ( 'plugin_information' == $method ) {
			$fields = array_merge(
				$fields,
				self::$plugins_info_fields_defaults,
				( defined( 'PLUGINS_API_VERSION' ) && PLUGINS_API_VERSION >= 1.2 ) ? self::$plugins_info_fields_defaults_12 : array()
			);
		} elseif ( 'query_plugins' == $method ) {
			$fields = array_merge(
				$fields,
				self::$query_plugins_fields_defaults,
				( defined( 'PLUGINS_API_VERSION' ) && PLUGINS_API_VERSION >= 1.2 ) ? self::$query_plugins_fields_defaults_12 : array()
			);
		} else {
			return array();
		}

		// In WordPress 4.0+ we request the icons field however we don't use the
		// description and compatibility fields so we exclude those by default unless requested.
		if ( ! empty( $this->requested_fields['icons'] ) ) {
			$fields['compatibility'] = false;
			$fields['description']   = false;
		}

		// If it's a block search, include blocks in the response by default
		if ( ! empty( $this->args->block ) ) {
			$fields = array_merge( $fields, self::$query_plugins_fields_defaults_block );
		}

		$fields = array_merge( $fields, $this->requested_fields );

		return $fields;
	}

	/**
	 * Sanitizes/parses the given fields parameter into a standard format.
	 */
	protected function parse_requested_fields( $fields ) {
		$fields = is_string( $fields ) ? explode( ',', $fields ) : (array) $fields;

		$requested_fields = array();
		foreach ( $fields as $field => $include ) {
			if ( is_int( $field ) ) {
				$field   = $include;
				$include = true;
				if ( '-' == substr( $field, 0, 1 ) ) {
					$include = false;
					$field   = substr( $field, 1 );
				}
			}

			// If the field is an aliased field, redirect to the proper field.
			$field = self::$field_aliases[ $field ] ?? $field;

			// Disable linked fields if required.
			if ( ! $include && isset( self::$field_interconnected[ $field ] ) ) {
				foreach ( self::$field_interconnected[ $field ] as $linked_field ) {
					if ( ! isset( $fields[ $linked_field ] ) ) {
						$requested_fields[ $linked_field ] = false;
					}
				}
			}

			// If it's a valid field, include it.
			if ( isset( self::$fields[ $field ] ) ) {
				$requested_fields[ $field ] = (bool) $include;
			}
		}

		return $requested_fields;
	}

	/**
	 * Converts a request data object into the format expected by WP_Query & the WordPress wp-api rest endpoint.
	 */
	public function query_plugins_params_for_query() {
		$query = array();
		// Paging
		$query['paged']          = isset( $this->args->page ) ? $this->args->page : 1;
		$query['posts_per_page'] = isset( $this->args->per_page ) ? $this->args->per_page : 24;

		// Views
		if ( ! empty( $this->args->browse ) ) {
			$query['browse'] = $this->args->browse;
			if ( ! empty( $this->args->installed_plugins ) ) {
				$query['installed_plugins'] = is_array( $this->args->installed_plugins ) ? $this->args->installed_plugins : array();
			}
		} elseif ( ! empty( $this->args->user ) ) {
			$query['browse']         = 'favorites';
			$query['favorites_user'] = $this->args->user;

		} else {

			// Tags
			if ( ! empty( $this->args->tag ) ) {
				$query['plugin_tags'] = $this->args->tag;
			}

			// Search
			if ( ! empty( $this->args->search ) ) {
				$query['s'] = $this->args->search;
			}

			// Author
			if ( ! empty( $this->args->author ) ) {
				$query['author_name'] = $this->args->author;
			}

			// Block
			if ( ! empty( $this->args->block ) ) {
				$query['block'] = $this->args->block;
			}
		}

		// Locale
		$query['locale'] = $this->locale;

		return $query;
	}

	/**
	 * Validate that the request is valid.
	 */
	public function is_valid_params( $method ) {
		if ( 'query_plugins' === $method ) {
			$scalar_only_fields = [
				'page', 'per_page',
				'browse', 'user',
				'tag', 'search',
				'author', 'block',
				'wp_version'
			];

			foreach ( $scalar_only_fields as $field ) {
				if ( isset( $this->args->$field ) && ! is_scalar( $this->args->$field ) ) {
					return false;
				}
			}

			if ( isset( $this->args->installed_plugins ) && ! is_array( $this->args->installed_plugins ) ) {
				return false;
			}

			if ( ! is_string( $this->locale ) ) {
				return false;
			}

		} else if ( 'plugin_information' === $method ) {
			if ( empty( $this->args->slug ) && empty( $this->args->slugs ) ) {
				return false;
			}

			if ( ! empty( $this->args->slug ) && ! is_string( $this->args->slug ) ) {
				return false;
			}

			if ( ! is_string( $this->locale ) ) {
				return false;
			}
		}

		return true;
	}
}

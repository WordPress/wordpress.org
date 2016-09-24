<?php
namespace WordPressdotorg\Plugin_Directory\Standalone;

/**
 * Holds the information about the request payload, and massages it into a format we can use.
 */
class Plugins_Info_API_Request {
	public $locale = 'en_US';
	protected $args = array();
	protected $requested_fields = array();

	static $fields = array(
		'active_installs'   => false,
		'added'             => false,
		'banners'           => false,
		'compatibility'     => false,
		'contributors'      => false,
		'description'       => false,
		'donate_link'       => false,
		'downloaded'        => false,
		'downloadlink'      => false,
		'homepage'          => false,
		'icons'             => false,
		'last_updated'      => false,
		'rating'            => false,
		'ratings'           => false,
		'reviews'           => false,
		'requires'          => false,
		'sections'          => false,
		'short_description' => false,
		'tags'              => false,
		'tested'            => false,
	);

	static $plugins_info_fields_defaults = array(
		'added'             => true,
		'compatibility'     => true,
		'contributors'      => true,
		'downloaded'        => true,
		'downloadlink'      => true,
		'donate_link'       => true,
		'homepage'          => true,
		'last_updated'      => true,
		'rating'            => true,
		'ratings'           => true,
		'requires'          => true,				
		'sections'          => true,
		'tags'              => true,
		'tested'            => true,		
	);

	static $query_plugins_fields_defaults = array(
		'added'             => true,
		'compatibility'     => true,
		'downloaded'        => true,
		'description'       => true,
		'downloadlink'      => true,
		'donate_link'       => true,
		'homepage'          => true,
		'last_updated'      => true,
		'rating'            => true,
		'ratings'           => true,
		'requires'          => true,				
		'sections'          => true,
		'short_description' => true,
		'tags'              => true,
		'tested'            => true,	
	);

	public function __construct( $args ) {
		$args = (object) $args;

		if ( !empty( $args->locale ) ) {
			$this->locale = $args->locale; // TODO: sanitize?
		}
		if ( !empty( $args->fields ) ) {
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

	public function get_expected_fields( $method ) {
		$fields = self::$fields;

		if ( 'plugin_information' == $method ) {
			$fields = array_merge( $fields, self::$plugins_info_fields_defaults );
		} elseif ( 'query_plugins' == $method ) {
			$fields = array_merge( $fields, self::$query_plugins_fields_defaults );
		} else {
			return array();
		}

		// In WordPress 4.0+ we request the icons field however we don't use the
		// description and compatibility fields so we exclue those by default unless requested.
		if ( ! empty( $this->requested_fields['icons'] ) ) {
			$fields['compatibility'] = false;
			$fields['description']   = false;
		}

		// In WordPress x.y we start sending 'contributors' which means respond with a more detailed list of contributors.
		if ( !isset( $this->requested_fields['contributors'] ) ) {
			$fields['bare_contributors'] = true;
		}

		$fields = array_merge( $fields, $this->requested_fields );

		return $fields;
	}

	/**
	 * Sanitizes/parses the given fields parameter into a standard format. 
	 */
	protected function parse_requested_fields( $fields ) {
		$fields = is_string( $fields ) ? explode( ',', $fields ) : (array)$fields;

		$requested_fields = array();
		foreach ( $fields as $field => $include ) {
			if ( is_int( $field ) ) {
				$field = $include;
				$include = true;
				if ( '-' == substr( $field, 0, 1 ) ) {
					$include = false;
					$field = substr( $field, 1 );
				}
			}
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
		$query['paged']          = isset( $this->args->page )     ? $this->args->page     : 1;
		$query['posts_per_page'] = isset( $this->args->per_page ) ? $this->args->per_page : 24;

		// Views
		if ( ! empty( $this->args->browse ) ) {
			$query['browse'] = $this->args->browse;
			if ( !empty( $this->args->installed_plugins ) )
				$query['installed_plugins'] = is_array( $this->args->installed_plugins ) ? $this->args->installed_plugins : array();

		} elseif ( ! empty( $this->args->user ) ) {
			$query['browse'] = 'favorites';
			$query['favorites_user'] = $this->args->user;

		} else {

			// Tags
			if ( ! empty( $this->args->tag ) ) {
				$query['plugin_tag'] = is_array( $this->args->tag ) ? reset( $this->args->tag ) : $this->args->tag;
			}

			// Search
			if ( ! empty( $this->args->search ) ) {
				$query['s'] = $this->args->search;
			}

			// Author
			if ( ! empty( $this->args->author ) ) {
				$query['author_name'] = $this->args->author;
				//$query['contributor_search'] = $this->args->author; // TODO
			}

		}

		return $query;
	}

}

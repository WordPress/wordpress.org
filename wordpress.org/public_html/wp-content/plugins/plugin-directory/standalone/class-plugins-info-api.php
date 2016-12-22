<?php
namespace WordPressdotorg\Plugin_Directory\Standalone;

// The API caches here expire every 24~25hours, avoids cache races when multiple change at the same time.
define( 'API_CACHE_EXPIRY', 24*60*60 + rand( 0, 60*60 ) );
class Plugins_Info_API {

	const CACHE_GROUP  = 'plugin_api_info';
	const CACHE_EXPIRY = API_CACHE_EXPIRY;

	protected $format = 'json';
	protected $formats = array(
		'jsonp' => 'application/javascript',
		'json'  => 'application/json',
		'php'   => 'text/plain',
		'xml'   => 'application/xml',
	);

	function __construct( $format = 'json' ) {
		if ( is_array( $format ) && 'jsonp' == $format[0] ) {
			$this->jsonp = $format[1];
			$format = 'json';
		}
		$this->format = $format;
	}

	/**
	 * Initiate the API and output the result.
	 *
	 * @param string $method  The method in the API to trigger.
	 * @param mixed  $request The request data/payload for the API handler.
	 */
	public function handle_request( $method, $request ) {
		$request = new Plugins_Info_API_Request( $request );
		switch ( $method ) {
			case 'plugin_information':
				$this->plugin_information( $request );
				break;

			case 'query_plugins':
				$this->query_plugins( $request );
				break;

			case 'popular_tags':
			case 'hot_tags':
				$this->popular_tags( $request );
				break;

			default:
				if ( 'POST' != strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
					die( '<p>Action not implemented. <a href="http://codex.wordpress.org/WordPress.org_API">API Docs</a>.</p>' );
				} else {
					$this->output( (object) array(
						'error' => 'Action not implemented'
					) );
				}
				break;
		}
	}

	/**
	 * API Endpoint that handles the Plugin_Information route.
	 *
	 * @param Plugins_Info_API_Request $request    The Request object for this request.
	 * @param bool                     $return_raw Whether this is for another request (query_plugins) or an API request.
	 */
	function plugin_information( $request, $return_raw = false ) {
		if ( false === ( $response = wp_cache_get( $cache_key = $this->plugin_information_cache_key( $request ), self::CACHE_GROUP ) ) ) {
			$response = $this->internal_rest_api_call( 'plugins/v1/plugin/' . $request->slug, array( 'locale' => $request->locale ) );

			if ( 200 != $response->status ) {
				$response = array( 'error' => 'Plugin not found.' );
				wp_cache_set( $cache_key, $response, self::CACHE_GROUP, 15*60 ); // shorter TTL for missing/erroring plugins.
			} else {
				$response = $response->data;
				wp_cache_set( $cache_key, $response, self::CACHE_GROUP, self::CACHE_EXPIRY );
			}
		}

		if ( $return_raw ) {
			return $response;
		}

		// Backwards compatibility; the API returns null in case of error..
		if ( isset( $response['error'] ) ) {
			$this->output( null );
			return;
		}


		// Only include the fields requested.
		if ( ! isset( $response['error'] ) ) {
			$response = $this->remove_unexpected_fields( $response, $request, 'plugin_information' );
		}

		$this->output( (object) $response );
	}

	/**
	 * Generates a Cache key for a plugin based on the request.
	 */
	protected function plugin_information_cache_key( $request ) {
		return 'plugin_information:' . $request->slug . ':' . ( $request->locale ?: 'en_US' );
	}

	/**
	 * Removes any extra fields which the API client doesn't need to be sent.
	 *
	 * @param array  $response The plugin_information response to remove fields from
	 * @param mixed  $request  The request object for the request.
	 * @param string $method   The requested method, used to determine the default fields to include.
	 *
	 * @return array The $resonse with the extra fields removed.
	 */
	protected function remove_unexpected_fields( $response, $request, $method = '' ) {
		$fields = $request->get_expected_fields( $method );
		foreach ( $fields as $field => $include ) {
			if ( ! $include ) {
				unset( $response[ $field ] );
			}
			if ( 'reviews' === $field && ! $include ) {
				unset( $response['sections']['reviews'] );
			}
		}

		// Back-compatible routines.
		// WordPress 4.x and older need a "bare" contributor map
		if ( !empty( $fields['bare_contributors'] ) ) {
			$contribs = $response['contributors'];
			$response['contributors'] = array();
			if ( $contribs ) {
				foreach ( $contribs as $user => $data ) {
					$response['contributors'][ $user ] = $data['profile'];
				}
			}
		}

		return $response;
	}

	/**
	 * API Endpoint to handle the 'query_plugins' action.
	 */
	public function query_plugins( $request ) {
		$response = array(
			'info' => array(
				'page' => 0,
				'pages' => 0,
				'results' => 0,
			),
			'plugins' => array()
		);

		$cache_key = $this->query_plugins_cache_key( $request );

		if ( false === ( $response = wp_cache_get( $cache_key, self::CACHE_GROUP ) ) ) {
			$response = $this->internal_rest_api_call( 'plugins/v1/query-plugins', $request->query_plugins_params_for_query() );
			if ( 200 != $response->status ) {
				$response = array( 'error' => 'Query Failed.' );
				wp_cache_set( $cache_key, $response, self::CACHE_GROUP, 30 ); // Short expiry for when we've got issues
			} else {
				$response = $response->data;
				wp_cache_set( $cache_key, $response, self::CACHE_GROUP, self::CACHE_EXPIRY );
			}
		}

		if ( isset( $response['error'] ) ) {
			$this->output( $response );
			return;
		}

		// Fill in the plugin details
		foreach ( $response['plugins'] as $i => $plugin_slug ) {
			$plugin = $this->plugin_information( new Plugins_Info_API_Request( array( 'slug' => $plugin_slug, 'locale' => $request->locale ) ), true );
			if ( isset( $plugin['error'] ) ) {
				unset( $response['plugins'][ $i ] );
				continue;
			}

			$response['plugins'][ $i ] = $plugin;
		}

		// Trim fields and cast to object
		foreach ( $response['plugins'] as $i => $plugin_data ) {
			$response['plugins'][$i] = (object) $this->remove_unexpected_fields( $plugin_data, $request, 'query_plugins' );
		}

		$this->output( $response );
	}

	/**
	 * Generates a cache key for a given query_plugins request.
	 */
	public function query_plugins_cache_key( $request ) {
		return 'query_plugins:' . md5( serialize( $request->query_plugins_params_for_query() ) ) . ':' . ( $request->locale ?: 'en_US' );
	}

	/**
	 * API Endpoint for the 'popular_tags' and 'hot_tags' API endpoints.
	 */
	public function popular_tags( $request ) {
		if ( false === ( $response = wp_cache_get( $cache_key = $this->popular_tags_cache_key( $request ), self::CACHE_GROUP ) ) ) {
			$response = $this->internal_rest_api_call( 'plugins/v1/popular-tags', array( 'locale' => $request->locale ) );

			if ( 200 != $response->status ) {
				$response = array( 'error' => 'Temporarily Unavailable' );
				wp_cache_set( $cache_key, $response, self::CACHE_GROUP, 30 ); // Short expiry for when we've got issues
			} else {
				$response = $response->data;
				wp_cache_set( $cache_key, $response, self::CACHE_GROUP, self::CACHE_EXPIRY );
			}
		}

		if ( isset( $response['error'] ) ) {
			$this->output( (object) $response );
			return;
		}

		$number_items_requested = 100;
		if ( !empty( $request->number ) ) {
			$number_items_requested = $request->number;
		}

		if ( count( $response ) > $number_items_requested ) {
			$response = array_slice( $response, 0, $number_items_requested, true );
		}

		$this->output( (object) $response );
	}

	/**
	 * Generates a cache key for a 'hot_tags' API request.
	 */
	protected function popular_tags_cache_key( $request ) {
		return 'hot_tags:' . $request->locale;
	}

	/**
	 * Output a given $response to the API client in the format specified by $this->format.
	 */
	function output( $response ) {
		header( 'Content-Type: ' . $this->formats[ $this->format ] );

		switch ( $this->format ) {
			default:
			case 'json' :
			case 'jsonp' :
				if ( ! function_exists( 'wp_json_encode' ) && defined( 'WPORGAPIPATH' ) ) {
					require WPORGAPIPATH . '/includes/wp-json-encode.php';
				}
				$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $response ) : json_encode( $response );
				if ( 'jsonp' == $this->format ) {
					echo "{$this->jsonp}($json)";
				} else {
					echo $json;
				}
				break;
	
			case 'php' :
				echo serialize( (object) $response );
				break;
	
			case 'xml' :
				echo '<' . '?xml version="1.0" encoding="utf-8"?' . ">\n";
				echo "<plugin>\n";
				$this->php_to_xml( $response );
				echo '</plugin>';
				break;
		}

		exit;
	}

	/**
	 * In the event that the Query needs to hit WordPress, this method can be used
	 * to load WordPress in the context of the correct site.
	 *
	 * Because "reasons" WordPress is told that it's a REST_REQUEST. remove it at
	 * your own risk.
	 */
	public function load_wordpress() {
		global $wpdb;
		define( 'REST_REQUEST', true );

		$host = $_SERVER['HTTP_HOST'];
		$request_uri = $_SERVER['REQUEST_URI'];
		$_SERVER['HTTP_HOST'] = 'wordpress.org';
		$_SERVER['REQUEST_URI'] = '/plugins/';

		require_once WPORGPATH . '/wp-load.php';

		$_SERVER['HTTP_HOST'] = $host;
		$_SERVER['REQUEST_URI'] = $request_uri;

		return true;
	}

	/**
	 * Performs a 'GET' based REST API call without making a HTTP request.
	 */
	public function internal_rest_api_call( $route, $query_params = array() ) {
		if ( ! class_exists( '\WP_Rest_Request' ) ) {
			$this->load_wordpress();
		}

		$route = ltrim( $route, '/' );

		$request = new \WP_REST_Request( 'GET', "/$route" );
		if ( $query_params ) {
			$request->set_query_params( $query_params );
		}

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Outputs the result as XML.
	 *
	 * @param mixed $data The PHP structure to output as XML.
	 */
	protected function php_to_xml( $data, $tabs = 0, $key = '' ) {
		static $xml_tag = null;
		if ( is_null( $xml_tag ) ) {
			$xml_tag = function( $tag, $type, $empty = false ) {
				static $NameStartChar = ':A-Z_a-z\xC0-\xD6\xD8-\xF6\xF8-\x{2FF}\x{370}-\x{37D}\x{37F}-\x{1FFF}\x{200C}-\x{200D}\x{2070}-\x{218F}\x{2C00}-\x{2FEF}\x{3001}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFFD}\x{10000}-\x{EFFFF}';
				static $NameChar = '.0-9\xB7\x{0300}-\x{036F}\x{203F}-\x{2040}-';

				$start_right = $empty ? ' />' : '>';

				$tag = preg_replace( '/[^a-z0-9_.-]/i', '', $tag );

				if ( $tag && preg_match( "/^[$NameStartChar][{$NameStartChar}$NameChar]*\$/u", $tag ) ) {
					return array( "<$tag type=\"$type\"$start_right", "</$tag>" );
				} elseif ( $tag ) {
					return array( "<$type key=\"$tag\"$start_right", "</$type>" );
				}

				return array( "<$type$start_right", "</$type>" );
			};
		}

		echo str_repeat( "\t", $tabs );
		switch ( $type = gettype( $data ) ) {
			case 'string' :
				$data = '<![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', $data ) . ']]>';
			case 'boolean' :
			case 'integer' :
			case 'double' :
			case 'float' :
				list( $start, $close ) = $xml_tag( $key, $type, false );
				echo "$start$data$close";
				break;
			case 'NULL' :
				list( $start, $close ) = $xml_tag( $key, $type, true );
				echo $start;
				break;
			case 'array' :
				if ( empty( $data ) ) {
					list( $start, $close ) = $xml_tag( $key, $type, true );
					echo $start;
					break;
				}

				list( $start, $close ) = $xml_tag( $key, $type, false );
				echo "$start\n";
				foreach ( $data as $k => $v ) {
					$this->php_to_xml( $v, $tabs + 1, is_int( $k ) ? '' : $k );
				}
				echo str_repeat( "\t", $tabs );
				echo $close;
				break;
			case 'object' :
				if ( !$array = get_object_vars( $data ) ) {
					if ( !$tabs ) {
						break;
					}

					list( $start, $close ) = $xml_tag( $key, $type, true);
					echo $start;
					break;
				}

				list( $start, $close ) = $xml_tag( $key, $type, false );
				if ( $tabs ) {
					echo $start;
				}
				foreach ( $array as $k => $v ) {
					$this->php_to_xml( $v, $tabs + 1, $k );
				}
				echo str_repeat( "\t", $tabs );
				if ( $tabs ) {
					echo $close;
				}
				break;
			case 'resource' :
			case 'unknown type' :
			default :
				break;
		}
		if ( $tabs ) {
			echo "\n";
		}
	}
}

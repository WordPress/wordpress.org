<?php

abstract class WP_Credits {

	abstract protected function groups();

	abstract protected function props();

	abstract protected function external_libraries();

	public static $use_cache = true;
	public static $set_cache = true;
	const cache_group = 'core-credits-api';
	const cache_life = 43200; // 12 hours

	protected $version;

	protected $branch;

	private $groups;

	private $translators;
	private $validators;

	private $names_in_groups = array();

	// These are only for the purposes of determining translation contributions, hence they don't always line up.
	private static $cycle_dates = array(
		'3.2' => '2011-02-23 00:00:00',
		'3.3' => '2011-07-05 00:00:00',
		'3.4' => '2011-12-10 00:00:00',
		'3.5' => '2012-07-01 00:00:00',
		'3.6' => '2012-12-15 00:00:00',
		'3.7' => '2013-07-28 00:00:00',
		'3.8' => '2013-11-01 00:00:00',
		'3.9' => '2013-12-13 00:00:00',
		'4.0' => '2014-07-01 00:00:00',
		'4.1' => '2014-10-01 00:00:00',
		'4.2' => '2014-12-19 00:00:00',
	);

	final public static function factory( $version, $gp_locale ) {
		$branch = intval( str_replace( '.', '', self::calculate_branch( $version ) ) );
		$file = dirname( __FILE__ ) . '/wp-' . $branch . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
			$class = 'WP_' . $branch . '_Credits';
			$credits = new $class( $version, $gp_locale );
			return $credits;
		} elseif ( version_compare( $branch, WP_CORE_STABLE_BRANCH, '>' ) ) {
			// Grab latest cycle listed.
			$cycles = self::$cycle_dates;
			end( $cycles );
			$branch = str_replace( '.', '', key( $cycles ) );
			$file = dirname( __FILE__ ) . '/wp-' . $branch . '.php';
			if ( file_exists( $file ) ) {
				require_once $file;
				$class = 'WP_' . $branch . '_Credits';
				$credits = new $class( $version, $gp_locale );
				return $credits;
			}
		}
		die();
	}

	private function __construct( $version, $gp_locale ) {
		wp_cache_init();
		$this->version = $version;
		$this->branch  = self::calculate_branch( $this->version );
		if ( $gp_locale )
			$this->set_locale_data( $gp_locale );
	}

	private function cache_set( $key, $value ) {
		if ( self::$set_cache ) {
			return wp_cache_set( $key, $value, self::cache_group, self::cache_life );
		}
		return false;
	}

	private function cache_get( $key ) {
		if ( self::$use_cache ) {
			return wp_cache_get( $key, self::cache_group );
		}
		return false;
	}

	private static function calculate_branch( $version ) {
		preg_match( '#^(\d+)\.(\d+)#', $version, $match );
		return $match[1] . '.' . $match[2];
	}

	private function set_locale_data( $gp_locale ) {
		if ( version_compare( WP_CORE_STABLE_BRANCH, $this->branch, '<' ) )
			$path = 'dev';
		else
			$path = $this->branch . '.x';

		// Override the code above. Currently, all history is retained in wp/dev. The branches are w/o history.
		$path = 'dev';

		$cache_key = array(
			'translators' => 'translators-' . $gp_locale->slug . '-' . $this->version . '-' . $path,
			'validators'  => 'validators-' . $gp_locale->slug . '-' . $this->version . '-changed-2',
		);

		$translators = $this->cache_get( $cache_key['translators'] );
		$validators  = $this->cache_get( $cache_key['validators'] );

		if ( false === $translators ) {
			$translators = $this->_grab_translators( $gp_locale, $path );
			$this->cache_set( $cache_key['translators'], $translators  );
		}

		if ( false === $validators ) {
			$validators = $this->_grab_validators( $gp_locale );
			$this->cache_set( $cache_key['validators'], $validators );
		}

		$translators = array_diff_key( $translators, $validators );

		uasort( $validators, function( $a, $b ) {
			return strnatcasecmp( $a[0], $b[0] ); // Sort by display name.
		} );
		natcasesort( $translators );

		$this->validators = $validators;
		$this->translators = $translators;
	}

	private function _grab_translators( $gp_locale, $path ) {
		global $wpdb;
		$path = 'wp/' . $path;

		$locale = $gp_locale->slug;

		$path   = $wpdb->escape( like_escape( $path ) . '%' );
		$locale = $wpdb->escape( $locale );

		$date = $wpdb->prepare( "AND tt.date_added > %s", $this->get_start_date() );
		if ( $end_date = $this->get_end_date() )
			$date .= $wpdb->prepare( " AND tt.date_added <= %s", $end_date );

		$users = $wpdb->get_col(  "SELECT DISTINCT tt.user_id
			FROM translate_translations tt
				INNER JOIN translate_translation_sets tts
				ON tt.translation_set_id = tts.id
				INNER JOIN translate_projects tp
				ON tp.id = tts.project_id
			WHERE tp.path LIKE '$path'
				AND tts.locale = '$locale'
				AND tts.slug = 'default'
				AND ( tt.status = 'current' || tt.status = 'old' )
				AND tt.user_id IS NOT NULL
				$date" );

		if ( ! $users )
			return array();

		$translator_data = $wpdb->get_results( "SELECT user_nicename, display_name FROM $wpdb->users WHERE ID IN (" . implode( ',', $users ) . ")" );

		$translators = array();

		foreach ( $translator_data as $user ) {
			if ( $user->user_nicename == 'nacin' )
				continue;
			if ( $user->display_name && $user->display_name != $user->user_nicename && false === strpos( $user->display_name , '?') )
				$translators[ $user->user_nicename ] = utf8_encode( remove_accents( $user->display_name ) );
			else
				$translators[ $user->user_nicename ] = $user->user_nicename;
		}

		return $translators;
	}

	private function _grab_validators( $gp_locale ) {
		global $wpdb;
		$users = $this->grab_validators( $gp_locale );

		if ( ! $users )
			return array();

		$validator_data = $wpdb->get_results( "SELECT user_nicename, display_name, user_email FROM $wpdb->users WHERE ID IN (" . implode( ',', $users ) . ")" );

		$validators = array();

		foreach ( $validator_data as $user ) {
			if ( $user->user_nicename == 'nacin' ) // I stopped taking Spanish in 11th grade, don't show me as a validator when I'm testing things.
				continue;
			if ( $user->display_name && $user->display_name != $user->user_nicename && false === strpos( $user->display_name , '?') )
				$validators[ $user->user_nicename ] = array( utf8_encode( remove_accents( $user->display_name ) ), md5( $user->user_email ), $user->user_nicename );
			else
				$validators[ $user->user_nicename ] = array( $user->user_nicename, md5( $user->user_email ), $user->user_nicename );
		}

		return $validators;
	}

	protected function grab_validators( $gp_locale ) {
		global $wpdb;
		$subdomain = $wpdb->get_var( $wpdb->prepare( "SELECT subdomain FROM locales WHERE locale = %s", $gp_locale->wp_locale ) );
		$blog_id = $wpdb->get_var( $wpdb->prepare( "SELECT blog_id FROM wporg_blogs WHERE domain = %s AND path = '/'", "$subdomain.wordpress.org" ) );
		if ( $blog_id ) {
			$meta_key = 'wporg_' . intval( $blog_id ) . '_capabilities';
			return $wpdb->get_col( "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = '$meta_key' AND meta_value LIKE '%translation_editor%'" );
		}
		return array();
	}

	final protected function get_start_date() {
		if ( isset( self::$cycle_dates[ $this->branch ] ) )
			return self::$cycle_dates[ $this->branch ];
	}

	final protected function get_end_date() {
		$next = false;
		foreach ( self::$cycle_dates as $branch => $date ) {
			if ( $next )
				return $date;
			if ( self::$cycle_dates[ $this->branch ] == $date )
				$next = true;
		}
		return false;
	}

	private function _data() {
		return array(
			'profiles' => 'https://profiles.wordpress.org/%s',
			'version' => $this->branch,
		);
	}

	private function _groups() {
		global $wpdb;

		if ( isset( $this->groups ) )
			return $this->groups;

		$groups = $this->groups();
		$fetch_emails_from_user_cache = $fetch_emails_from_db = array();

		foreach ( $groups as $group_slug => $group_data ) {
			if ( 'list' == $group_data['type'] )
				continue;
			foreach ( $group_data['data'] as $k => $person ) {
				$person = (array) $person;
				$new_data = array( 'name' => $person[0], 'hash' => '', 'username' => $k, 'title' => '' );
				$this->names_in_groups[] = strtolower( $k );

				if ( ! empty( $person[2] ) ) {
					// array( 'Andrew Nacin', 'Lead Developer', 'md5 hash' )
					$new_data['title'] = $person[1];
					$new_data['hash'] = $person[2];
				} elseif ( empty( $person[1] ) ) {
					// array( 'Andrew Nacin' )
					$fetch_emails_from_user_cache[ $k ] = $group_slug;
				} elseif ( strlen( $person[1] ) === 32 && preg_match('/^[a-f0-9]{32}$/', $person[1] ) ) {
					// array( 'Andrew Nacin', 'md5 hash' )
					$new_data['hash'] = $person[1];
				} else {
					// array( 'Andrew Nacin', 'Lead Developer' )
					$new_data['title'] = $person[1];
					$fetch_emails_from_user_cache[ $k ] = $group_slug;
				}

				// Temporary:
				if ( strlen( $new_data['hash'] ) != 32 || strpos( $new_data['hash'], '@' ) ) {
					$new_data['hash'] = md5( $new_data['hash'] );
				}

				$group_data['data'][ $k ] = array_values( $new_data );
			}

			$groups[ $group_slug ]['data'] = $group_data['data'];
		}

		if ( $fetch_emails_from_user_cache ) {
			foreach ( $fetch_emails_from_user_cache as $username => $group ) {
				$user_id = wp_cache_get( $username, 'userlogins' );
				if ( $user_id ) {
					if ( $user_object = wp_cache_get( $user_id, 'users' ) ) {
						$groups[ $group ]['data'][ $username ][1] = md5( strtolower( $user_object->user_email ) );
					} else {
						$fetch_emails_from_db[ $username ] = $group;
					}
				} else {
					$fetch_emails_from_db[ $username ] = $group;
				}
			}
			if ( $fetch_emails_from_db ) {
				$fetched = $wpdb->get_results( "SELECT user_login, ID, user_email FROM $wpdb->users WHERE user_login IN ('" . implode( "', '", array_keys( $fetch_emails_from_db ) ) . "')", OBJECT_K );
				foreach ( $fetched as $username => $row ) {
					$groups[ $fetch_emails_from_db[ $username ] ]['data'][ $username ][1] = md5( strtolower( $row->user_email ) );
					wp_cache_add( $username, $row->ID, 'userlogins' );
				}
			}
		}

		$this->groups = $groups;
		return $groups;
	}

	private function _props() {
		global $wpdb;
		$props = $this->cache_get( 'props-' . $this->version );
		if ( $props !== false )
			return $props;

		$this->_groups(); // Cache groups now.

		$users = $this->props();
		$users = array_diff( $users, $this->names_in_groups );

		$user_data = $wpdb->get_results( "SELECT user_nicename, display_name FROM $wpdb->users WHERE user_nicename IN ('" . implode( "', '", $users ) . "')" );

		$props = array();

		foreach ( $user_data as $user ) {
			if ( $user->display_name && $user->display_name != $user->user_nicename && false === strpos( $user->display_name , '?') )
				$props[ $user->user_nicename ] = utf8_encode( remove_accents( $user->display_name ) );
			else
				$props[ $user->user_nicename ] = $user->user_nicename;
		}

		natcasesort( $props );

		$this->cache_set( 'props-' . $this->version, $props );

		return $props;
	}

	private function _external_libraries() {
		return $this->external_libraries();
	}

	private function _translators() {
		return $this->translators;
	}

	private function _validators() {
		return $this->validators;
	}

	final public function get_results() {
		$groups = $this->_groups();

		$groups['props'] = array(
			'name'         => 'Core Contributors to WordPress %s',
			'placeholders' => array( $this->branch ),
			'type'         => 'list',
			'data'         => $this->_props(),
		);

		if ( $this->validators || $this->translators ) {
			$groups['validators'] = array(
				'name'    => 'Translators',
				'type'    => 'compact',
				'shuffle' => true,
				'data'    => $this->_validators(),
			);

			$groups['translators'] = array(
				'name' => false,
				'type' => 'list',
				'data' => $this->_translators(),
			);
		}

		$groups['libraries'] = array(
			'name' => 'External Libraries',
			'type' => 'libraries',
			'data' => $this->_external_libraries(),
		);

		$data = $this->_data();

		return compact( 'groups', 'data' );
	}

	final public function execute() {
		$results = $this->get_results();

		if ( 'cli' === php_sapi_name() ) {
			print_r( $results );
		} elseif ( defined( 'JSON_RESPONSE' ) && JSON_RESPONSE ) {
			echo json_encode( $results );
		} else {
			echo serialize( $results );
		}
	}

}

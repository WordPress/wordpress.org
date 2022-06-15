<?php
namespace WordPressdotorg\Plugin_Directory\Tools;

/**
 * Various SVN methods
 *
 * @package WordPressdotorg\Plugin_Directory\Tools
 */
class SVN {
	/**
	 * Get SVN info about a URL.
	 *
	 * @static
	 *
	 * @param string $url     The URL of the svn repo.
	 * @param array  $options Optional. A list of options to pass to SVN. Default: empty array.
	 *
	 * @return array {
	 *     @type bool|array $result False on failure. Otherwise an associative array.
	 *     @type bool|array $errors Whether any errors or warnings were encountered.
	 * }
	 */
	public static function info( $url, $options = array() ) {
		$esc_url = escapeshellarg( $url );

		$options[]   = 'non-interactive';
		if ( empty( $options['username'] ) ) {
			$options['username'] = PLUGIN_SVN_MANAGEMENT_USER;
			$options['password'] = PLUGIN_SVN_MANAGEMENT_PASS;
		}
		$esc_options = self::parse_esc_parameters( $options );

		$output = self::shell_exec( "svn info $esc_options $esc_url 2>&1" );
		if ( preg_match( '!URL: ' . untrailingslashit( $url ) . '\n!i', $output ) ) {
			$lines  = explode( "\n", $output );
			$result = array_filter( array_reduce(
				$lines,
				function( $carry, $item ) {
					$pair = explode( ':', $item, 2 );
					if ( isset( $pair[1] ) ) {
						$key = trim( $pair[0] );
						$carry[ $key ] = trim( $pair[1] );
					} else {
						$carry[] = trim( $pair[0] );
					}

					return $carry;
				},
				array()
			) );
			$errors = false;
		} else {
			$result   = false;
			$errors   = self::parse_svn_errors( $output );
		}

		return compact( 'result', 'errors' );
	}

	/**
	 * Import a local directory to a SVN path.
	 *
	 * @static
	 *
	 * @param string $path     The local folder to import into SVN.
	 * @param string $url      The URL to import to.
	 * @param string $message  The commit message.
	 * @return array {
	 *     @type bool        $result   The result of the operation.
	 *     @type int         $revision The revision imported.
	 *     @type false|array $errors   Whether any errors or warnings were encountered.
	 * }
	 */
	public static function import( $path, $url, $message, $options = array() ) {
		$options[] = 'non-interactive';
		$options['m'] = $message;
		if ( empty( $options['username'] ) ) {
			$options['username'] = PLUGIN_SVN_MANAGEMENT_USER;
			$options['password'] = PLUGIN_SVN_MANAGEMENT_PASS;
		}

		$esc_options = self::parse_esc_parameters( $options );

		$esc_path = escapeshellarg( $path );
		$esc_url  = escapeshellarg( $url );

		$output = self::shell_exec( "svn import $esc_options $esc_path $esc_url 2>&1" );
		if ( preg_match( '/Committed revision (?P<revision>\d+)[.]/i', $output, $m ) ) {
			$revision = (int) $m['revision'];
			$result   = true;
			$errors   = false;
		} else {
			$result   = false;
			$revision = false;
			$errors   = self::parse_svn_errors( $output );
		}

		return compact( 'result', 'revision', 'errors' );
	}

	/**
	 * Create an SVN Export of a URL to a local directory.
	 *
	 * @static
	 *
	 * @param string $url         The URL to export.
	 * @param string $destination The local folder to export into.
	 * @param array  $options     Optional. A list of options to pass to SVN. Default: empty array.
	 * @return array {
	 *     @type bool        $result   The result of the operation.
	 *     @type int         $revision The revision exported.
	 *     @type false|array $errors   Whether any errors or warnings were encountered.
	 * }
	 */
	public static function export( $url, $destination, $options = array() ) {
		$options[]   = 'non-interactive';
		$esc_options = self::parse_esc_parameters( $options );

		$esc_url         = escapeshellarg( $url );
		$esc_destination = escapeshellarg( $destination );

		$output = self::shell_exec( "svn export $esc_options $esc_url $esc_destination 2>&1" );
		if ( preg_match( '/Exported revision (?P<revision>\d+)[.]/i', $output, $m ) ) {
			$revision = (int) $m['revision'];
			$result   = true;
			$errors   = false;
		} else {
			$result   = false;
			$revision = false;
			$errors   = self::parse_svn_errors( $output );
		}

		return compact( 'result', 'revision', 'errors' );
	}

	/**
	 * Create an SVN Checkout of a URL to a local directory.
	 *
	 * @static
	 *
	 * @param string $url         The URL to export.
	 * @param string $destination The local folder to checkout into.
	 * @param array  $options     Optional. A list of options to pass to SVN. Default: empty array.
	 * @return array {
	 *     @type bool        $result   The result of the operation.
	 *     @type int         $revision The revision exported.
	 *     @type false|array $errors   Whether any errors or warnings were encountered.
	 * }
	 */
	public static function checkout( $url, $destination, $options = array() ) {
		$options[]   = 'non-interactive';
		$esc_options = self::parse_esc_parameters( $options );

		$esc_url         = escapeshellarg( $url );
		$esc_destination = escapeshellarg( $destination );

		$output = self::shell_exec( "svn checkout $esc_options $esc_url $esc_destination 2>&1" );
		if ( preg_match( '/Checked out revision (?P<revision>\d+)[.]/i', $output, $m ) ) {
			$revision = (int) $m['revision'];
			$result   = true;
			$errors   = false;
		} else {
			$result   = false;
			$revision = false;
			$errors   = self::parse_svn_errors( $output );
		}

		return compact( 'result', 'revision', 'errors' );
	}

	/**
	 * Update a SVN checkout.
	 *
	 * @static
	 *
	 * @param string $checkout The path of the SVN checkout to update.
	 * @param array  $options  Optional. A list of options to pass to SVN. Default: empty array.
	 * @return array {
	 *     @type bool        $result   The result of the operation.
	 *     @type int         $revision The revision exported.
	 *     @type false|array $errors   Whether any errors or warnings were encountered.
	 * }
	 */
	public static function up( $checkout, $options = array() ) {
		$options[]   = 'non-interactive';
		$esc_options = self::parse_esc_parameters( $options );

		$esc_checkout = escapeshellarg( $checkout );

		$output = self::shell_exec( "svn up $esc_options $esc_checkout 2>&1" );
		if ( preg_match( '/Updated to revision (?P<revision>\d+)[.]/i', $output, $m ) ) {
			$revision = (int) $m['revision'];
			$result   = true;
			$errors   = false;
		} else {
			$result   = false;
			$revision = false;
			$errors   = self::parse_svn_errors( $output );
		}

		return compact( 'result', 'revision', 'errors' );
	}

	/**
	 * Add a file in a SVN checkout to be revisioned.
	 *
	 * @static
	 *
	 * @param string $checkout The path of the file to add to SVN.
	 * @return array {
	 *     @type bool        $result The result of the operation.
	 *     @type false|array $errors Whether any errors or warnings were encountered.
	 * }
	 */
	public static function add( $file ) {
		$options[]   = 'non-interactive';
		$esc_options = self::parse_esc_parameters( $options );

		$esc_file     = escapeshellarg( $file );

		$output = self::shell_exec( "svn add $esc_options $esc_file 2>&1" );
		if ( preg_match( "/^A/i", $output ) ) {;
			$result   = true;
			$errors   = false;
		} else {
			$result = false;
			$errors = self::parse_svn_errors( $output );
		}

		return compact( 'result', 'errors' );
	}

	/**
	 * Commit changes in a SVN checkout.
	 *
	 * @static
	 *
	 * @param string $checkout The local folder to import into SVN.
	 * @param string $message  The commit message.
	 * @param array  $options  Any specific options to pass to SVN.
	 * @return array {
	 *     @type bool        $result   The result of the operation.
	 *     @type int         $revision The revision imported.
	 *     @type false|array $errors   Whether any errors or warnings were encountered.
	 * }
	 */
	public static function commit( $checkout, $message, $options = array() ) {
		$options[] = 'non-interactive';
		$options['m'] = $message;
		if ( empty( $options['username'] ) ) {
			$options['username'] = PLUGIN_SVN_MANAGEMENT_USER;
			$options['password'] = PLUGIN_SVN_MANAGEMENT_PASS;
		}

		$esc_options = self::parse_esc_parameters( $options );

		$esc_checkout = escapeshellarg( $checkout );

		$output = self::shell_exec( "svn commit $esc_options $esc_checkout 2>&1" );
		if ( preg_match( '/Committed revision (?P<revision>\d+)[.]/i', $output, $m ) ) {
			$revision = (int) $m['revision'];
			$result   = true;
			$errors   = false;
		} else {
			$result   = false;
			$revision = false;
			$errors   = self::parse_svn_errors( $output );
		}

		return compact( 'result', 'revision', 'errors' );
	}

	/**
	 * Create a folder at a specified SVN location.
	 *
	 * @static
	 *
	 * @param string $url      The remote URL to create.
	 * @param string $message  The commit message.
	 * @param array  $options  Any specific options to pass to SVN.
	 * @return array {
	 *     @type bool        $result   The result of the operation.
	 *     @type int         $revision The revision imported.
	 *     @type false|array $errors   Whether any errors or warnings were encountered.
	 * }
	 */
	public static function mkdir( $url, $message, $options = array() ) {
		$options[] = 'non-interactive';
		$options['m'] = $message;
		if ( empty( $options['username'] ) ) {
			$options['username'] = PLUGIN_SVN_MANAGEMENT_USER;
			$options['password'] = PLUGIN_SVN_MANAGEMENT_PASS;
		}

		$esc_options = self::parse_esc_parameters( $options );

		$esc_checkout = escapeshellarg( $url );

		$output = self::shell_exec( "svn mkdir $esc_options $esc_checkout 2>&1" );
		if ( preg_match( '/Committed revision (?P<revision>\d+)[.]/i', $output, $m ) ) {
			$revision = (int) $m['revision'];
			$result   = true;
			$errors   = false;
		} else {
			$result   = false;
			$revision = false;
			$errors   = self::parse_svn_errors( $output );
		}

		return compact( 'result', 'revision', 'errors' );
	}

	/**
	 * List the files in a remote SVN destination.
	 *
	 * @static
	 *
	 * @param string $url     The URL to export.
	 * @param bool   $verbose Optional. Whether to list the files verbosely with extra metadata. Default: false.
	 * @return array If non-verbose a list of files, if verbose an array of items containing the filename, date,
	 *               filesize, author and revision.
	 */
	public static function ls( $url, $verbose = false ) {
		$options = array(
			'non-interactive',
			'xml',
		);

		$esc_options = self::parse_esc_parameters( $options );
		$esc_url     = escapeshellarg( $url );

		$output = self::shell_exec( "svn ls $esc_options $esc_url 2>&1" );
		$errors = self::parse_svn_errors( $output );
		if ( $errors ) {
			return false;
		}

		// Parse the output
		$errors = libxml_use_internal_errors( true );
		$xml    = simplexml_load_string( $output );
		libxml_use_internal_errors( $errors );

		$files = [];
		foreach ( $xml->list->children() as $entry ) {
			$files[] = [
				'revision' => (int) $entry->commit['revision'],
				'author'   => (string) $entry->commit->author,
				'filesize' => (int) $entry->size,
				'date'     => gmdate( 'Y-m-d H:i:s', strtotime( (string) $entry->commit->date ) ),
				'filename' => (string) $entry->name,
				'kind'     => (string) $entry['kind'],
			];
		}

		if ( ! $verbose ) {
			return wp_list_pluck( $files, 'filename' );
		}

		return $files;
	}

	/**
	 * Fetch SVN revisions for a given revision or range of revisions.
	 *
	 * @static
	 *
	 * @param string       $url      The URL to fetch.
	 * @param string|array $revision Optional. The revision to get information about. Default HEAD.
	 * @param array        $options  Optional. A list of options to pass to SVN. Default: empty array.
	 * @return array {
	 *     @type array|false $errors Whether any errors or warnings were encountered.
	 *     @type array       $log    The SVN log data struct.
	 * }
	 */
	public static function log( $url, $revision = 'HEAD', $options = array() ) {
		$options[]           = 'non-interactive';
		$options[]           = 'verbose';
		$options[]           = 'xml';
		$options['revision'] = is_array( $revision ) ? "{$revision[0]}:{$revision[1]}" : $revision;
		$esc_options         = self::parse_esc_parameters( $options );

		$esc_url = escapeshellarg( $url );

		$output = self::shell_exec( "svn log $esc_options $esc_url 2>&1" );
		$errors = self::parse_svn_errors( $output );

		$log = array();

		/*
		 * We use funky string mangling here to extract the XML as it may have been truncated by a SVN error,
		 * or suffixed by a SVN warning.
		 */
		$xml = substr( $output, $start = stripos( $output, '<?xml' ), $end = ( strripos( $output, '</log>' ) - $start + 6 ) );
		if ( $xml && false !== $start && false !== $end ) {

			$user_errors = libxml_use_internal_errors( true );
			$simple_xml  = simplexml_load_string( $xml );
			libxml_use_internal_errors( $user_errors );

			if ( ! $simple_xml ) {
				$errors[] = "SimpleXML failed to parse input";
			} else {
				foreach ( $simple_xml->logentry as $entry ) {
					$revision = (int) $entry->attributes()['revision'];
					$paths    = array();

					foreach ( $entry->paths->children() as $child_path ) {
						$paths[] = (string) $child_path;
					}

					$log[ $revision ] = array(
						'revision' => $revision,
						'author'   => (string) $entry->author,
						'date'     => strtotime( (string) $entry->date ),
						'paths'    => $paths,
						'message'  => (string) $entry->msg,
					);
				}
			}
		}

		return compact( 'log', 'errors' );
	}

	/**
	 * Parse and escape the provided SVN arguements for usage on the CLI.
	 *
	 * Parameters can be passed as [ param ] or [ param = value ], if the argument is not
	 * prefixed with -, it'll be prefixed with 1 or 2 dashes as appropriate.
	 *
	 * @static
	 * @access protected
	 *
	 * @param array $params The array of parameters provided.
	 * @return string The parameters formatted and escaped for CLI usage.
	 */
	protected static function parse_esc_parameters( $params ) {
		$result = array();

		foreach ( $params as $key => $value ) {
			$no_parameters = is_numeric( $key );
			if ( $no_parameters ) {
				$key = $value;
			}

			// Prefix options with `-` or `--` if they're longer than 2 characters.
			if ( ! str_starts_with( $key, '-' ) ) {
				$key = '-' . ( strlen( $key ) > 2 ? '-' : '' ) . $key;
			}

			$result[] = escapeshellarg( $key ) . ( $no_parameters ? '' : ' ' . escapeshellarg( $value ) );
		}

		return implode( ' ', $result );
	}

	/**
	 * Parses SVN output to detect SVN errors and throw an exception.
	 *
	 * @static
	 * @access protected
	 *
	 * @param string $output The output from SVN.
	 * @return false|array False if no errors/warnings detected in output, An array of arrays containing
	 *                     warning/error_code/error_message if detected.
	 */
	protected static function parse_svn_errors( $output ) {
		if ( preg_match_all( '!^svn: (?P<warning>warning:)?\s*(?<error_code>[EW]\d+):\s*(?P<error_message>.+)$!im', $output, $messages, PREG_SET_ORDER ) ) {

			// We only want the string keys - strip out the numeric keys
			$messages = array_map( function ( $item ) {
				return array_filter( $item, 'is_string', ARRAY_FILTER_USE_KEY );
			}, $messages );

			return $messages;
		}

		return false;
	}

	/**
	 * Executes a command with 'proper' locale/language settings
	 * so that utf8 strings are handled correctly.
	 *
	 * WordPress.org uses the en_US.UTF-8 locale.
	 *
	 * @static
	 * @access protected
	 *
	 * @param string $command The command to be executed.
	 * @return mixed The output from the executed command or NULL if an error occurred or the command produces no
	 *               output.
	 */
	protected static function shell_exec( $command ) {
		return shell_exec( 'export LC_CTYPE="en_US.UTF-8" LANG="en_US.UTF-8"; ' . $command );
	}
}


<?php
namespace WordPressdotorg\Trac\Watcher\Props;
use function WordPressdotorg\Trac\Watcher\SVN\get_svns;

/**
 * Parse a log message and extract the props.
 *
 * The $include_old parameter can be used to run much more lenient matchers against the log, if the primary prop regexes
 * do not match. These are mostly from the early days of commits when they were less structured, but can have a higher
 * false-positive match. Such as "updates from github".
 *
 * @param string $log         The log message
 * @param bool   $include_old Whether to include the "really old matchers". See Note above.
 * @return array
 */
function from_log( $log, $include_old = false ) {
	$props        = '\s*(?P<props>.+?)';
	$props_greedy = '\s*(?P<props>.+)';
	$props_short  = '\s*(?P<props>\S{4,}((\s*and)?\s+\S{4,})?)'; // One or two words 4char+
	$props_one    = '\s*(?P<props>\S{4,})'; // Single prop, 4char+
	$sol          = '(^|[.]|\pP)\s*';       // Start of line, actual start of line or sentence start.
	$real_sol     = '(^|[.])\s*';
	$eol          = '([.]([^.]|$)|\pP|$)';          // EOL that's a full stop, new line, or random punctuation.
	$real_eol     = '([.][^.]|[.]?$)';              // EOL that's actually full stop or new line.

	// These matchers are regular expressions, put inside `#..#im`
	$matchers = [
		// These matchers apply in order, once one matches that's the parser used for the commit.
		'once' => [
			"^props(?! to ) {$props_greedy}.?$", // Super basic initial primary matcher, trumps even the next one.
			"{$real_sol}props(?! to ) {$props_greedy}{$real_eol}", // Primary matcher, "Props abc, def". Excludes "props to "
			"([,.] )props {$props_greedy}{$eol}", // Simple inline props matcher, ". props abc." / ", props abc, fixes ..."
			".{$sol}props([:]| to ) {$props_greedy}{$eol}", // `.` prefix is to ensure that the SOL is not the start of the message, to avoid triggering for https://meta.trac.wordpress.org/changeset/11790
			"([0-9]|\pP)\s+props([:]|\s+to)? {$props_one}( in .+)?(,|{$eol})",
			"{$sol}(h/t|hat tip:) {$props_short}{$eol}",
		],

		// These are starting to get real old... like three-digit core commit old.. only run when needed (See the $include_old parameter)
		'old' => [
			"with help from {$props}{$real_eol}",
			"\S\sprops([:]|\s+to)? {$props_short}{$eol}", // Inline props
			"\scredit(?! card)([:]|\sto)? {$props_short}", // Credit: ... or Credit to ...
			"\s(diff|patch|patches|fix|fixes) from {$props_short}{$eol}", // Fixes from Ryan
			"\smad props to {$props_short}{$eol}", // mad props to johny
			"\sfrom {$props_short}$", // Super last-ditch effort
		],

		// These matchers apply no matter which ones above have matched.
		// They're intended to catch additional inline mentions.
		'multiple' => [
			"{$sol}unprops:? {$props_greedy}{$eol}", 
			"{$sol}(dev)?reviewed[- ]by {$props}{$eol}",
			"\sthanks {$props_one}",
		]
	];

	// Cleanup the log

	// Remove anything non-printable.
	$log = preg_replace( "![^[:print:]\n]!", '', $log );

	// Replace all whitespace with standard spaces.
	$log = preg_replace( '![\pZ]+$!u', ' ', $log );

	// remove any ticket references and other irrelevant things.
	$log = preg_replace( '!(fixes|fixed|see|closes|merges):?\s*([[]\d{3,}[]]|r\d{2,}|#[a-z]*\d{2,}|https?://\S+)( for (trunk|[0-9.]+))?!i', '', $log ); // fixes [1234] for 5.6, closes #1234, see #meta12345
	$log = preg_replace( '!(#\d+|[[]\d+[]])!', '', $log ); // Tickets, Revisions not matched above
	$log = preg_replace( '!merges\s+\S+!i', '', $log );
	$log = preg_replace( '![ ]{2,}!', ' ', $log );
	
	// Trim out any URLs.
	$log = preg_replace( '!https?://\S+!i', '', $log );
	$log = trim( $log, ",. \n\t" );

	// Fetch any lines related.
	$data = [];
	foreach ( $matchers['once'] as $regex ) {
		if ( preg_match_all( '#' . $regex . '#im', $log, $m ) ) {
			$data = array_merge( $data, $m['props'] );
			break;
		}
	}

	if ( ! $data && $include_old ) {
		foreach ( $matchers['old'] as $regex ) {
			if ( preg_match_all( '#' . $regex . '#im', $log, $m ) ) {
				$data = array_merge( $data, $m['props'] );
				break;
			}
		}
	}

	foreach ( $matchers['multiple'] as $regex ) {
		if ( preg_match_all( '#' . $regex . '#im', $log, $m ) ) {
			$data = array_merge( $data, $m['props'] );
		}
	}

	if ( ! $data ) {
		return [];
	}

	$users = [];
	foreach ( $data as $d ) {
		// Trim out any 'for ...' & '(for...)' & '( fo ...)'
		$d = preg_replace( '!\b[(]?for? (now|.{4,}|[0-9.]{2,})([.]|$)!is', '', $d );

		// Trim out any '$user with...'
		$d = preg_replace( '!^(.{5,})\s*with .{4,}([.][^.]|$)!is', '$1', $d );

		// Trim any odd whitespace and punctuation.
		$d = trim( $d, ",. \n\t" );

		// Reduce any " and " down to a comma list.
		$d = preg_replace( '!(\w) and !i', '$1, ', $d );

		// Commas or Slashes? Assume separated users.
		if ( false !== strpos( $d, ',' ) || false !== strpos( $d, '/' ) ) {
			$users = array_merge(
				$users,
				preg_split( '![,/]\s*!', $d )
			);
		}
		// Words prefixed by @? User list.
		else if ( preg_match( '!^(@(\S+\s*))*$!u', $d ) ) {
			$users = array_merge(
				$users,
				// We'll trim the @ off later.
				preg_split( '!\s+!u', $d )
			);
		}
		// no spaces? Just a username?
		else if ( false === strpos( $d, ' ' ) ) {
			$users[] = $d;
		}
		else {
			// At this point, we have to decide if this is a list of "user user user" or "user with spaces"

			$user_list = preg_split( '!\s+!u', $d );

			// If it's only 2 words, and we can find a matching user, trust it.
			if (
				2 == count( $user_list ) &&
				preg_match( '!^[a-z ]+$!i', $d ) &&
				find_user_id( $d )
			) {
				$users[] = $d;
			} else {
				// Multiple spaces is more likely a list of users.
				$users = array_merge(
					$users,
					$user_list
				);
			}
		}

	}

	// Cleanup users.
	$users = array_map(
		function( $u ) {
			// Trim leading @
			$u = ltrim( $u, '@' );

			// Trim leading words.. these should never be here, but sometimes slip in with duplicate `props a props b`
			$words = [
				'and', 'props',
			];
			$u = preg_replace( '!((' . implode( '|', $words ) . ')\s+)*!i', '', $u );

			// Trim trailing punctuation (if it starts without punctuation)
			$u = preg_replace( '!^([a-z0-9].{4,}?)[\pP\s]+?$!iu', '$1', $u );

			// Does it start with a expected character, but have space followed by rand punctuation?
			$u = preg_replace( '!^([a-z0-9]\S+)\s\pP.*$!i', '$1', $u );

			return $u;
		},
		$users
	);

	// Filter the users.
	$users = array_filter(
		$users,
		function( $u ) use( $users ) {
			static $blacklist = [
				'list', 'for', 'fo', 'in', 'to', 'and', 'as', 'an', 'up', 'and', '&',
				'contributors', 'others',
				'et al', 'et alii', 'via',
				'fixes', 'fix', 'see', 'closes', 'props',
				'`public`', // r31078
				'Team', // r31975
				'Gandalf', // r31975
				'dependabot', // r48501
				'everyone-in-the-core-updates-channel', // r48678
			];

			if ( in_array( $u, $blacklist, true ) ) {
				return false;
			}

			// Exclude purely numeric users.
			// There exist a few, but they're so far between.
			if ( is_numeric( $u ) ) {
				return false;
			}

			// Ignore super-short names, probably mis-match.
			if ( strlen( $u ) < 3 ) {
				return false;
			}

			// Ignore pure punctuation.
			if ( preg_match( '!^[\pP]+$!u', $u ) ) {
				return false;
			}

			// If a user has more than 2 spaces, lets consider that invalid?
			if ( substr_count( $u, ' ' ) > 2 ) {
				return false;
			}

			// If the user is the start of another user, skip it.
			// ie. [ 'john', 'john.smith' ] => [ 'john.smith' ]
			foreach ( $users as $u2 ) {
				if (
					$u != $u2 &&
					substr( $u2, 0, strlen( $u ) ) === $u
				) {
					return false;
				}
			}

			return true;
		}
	);

	return array_unique( $users );
}

/**
 * Find a user ID for the user being prop'd.
 */
function find_user_id( $prop ) {
	global $wpdb;

	if ( ! $prop ) {
		return false;
	}

	// Profile URL - This is primarily used via the Admin UI to assign ownership of a prop.
	if (
		preg_match( '!^https?://profiles.wordpress.org/(?P<user>[^/]+)/?$!', $prop, $m ) &&
		( $user = get_user_by( 'slug', $m['user'] ) )
	) {
		return $user->ID;
	}

	// User login
	if ( $user = get_user_by( 'login', $prop ) ) {
		return $user->ID;
	}

	// User nicename
	if ( $user = get_user_by( 'slug', $prop ) ) {
		return $user->ID;
	}

	// Email
	if (
		is_email( $prop ) &&
		( $user = get_user_by( 'email', $prop ) )
	) {
		return $user->ID;
	}

	// User ID?
	if (
		is_numeric( $prop ) &&
		( $user = get_user_by( 'id', $prop ) )
	) {
		return $user->ID;
	}

	// previous props?
	// This works great to catch typo's, correct it manually once and it'll be caught next time.
	foreach ( get_svns() as $svn ) {
		if ( empty( $svn['props_table'] ) ) {
			continue;
		}
		$props_table = $svn['props_table'];
		if (
			$props_table &&
			( $id = $wpdb->get_var( $sql = $wpdb->prepare( "SELECT user_id FROM {$props_table} WHERE prop_name = %s AND user_id IS NOT NULL LIMIT 1", $prop ) ) )
		) {
			return (int) $id;
		}
	}

	// GitHub?
	$github = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM wporg_github_users WHERE github_user = %s LIMIT 1", $prop ) );
	if ( $github ) {
		return (int) $github;
	}

	// Last resort, full name of someone who has been prop'd before?
	// Display_Name isn't indexed, so can't be queried without a user list to reference against.
	foreach ( get_svns() as $svn ) {
		if ( empty( $svn['props_table'] ) ) {
			continue;
		}

		$props_table = $svn['props_table'];
		$user_id = $wpdb->get_var( $sql = $wpdb->prepare(
			"SELECT u.ID FROM {$props_table} p JOIN {$wpdb->users} u ON p.user_id = u.ID WHERE u.display_name = %s LIMIT 1",
			$prop
		) );

		if ( $user_id ) {
			return $user_id;
		}
	}

	return false;
}

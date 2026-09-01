<?php
namespace WordPressdotorg\Plugin_Directory;

use WordPressdotorg\Plugin_Directory\CLI\Import;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Tools\SVN;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;
use WordPressdotorg\Plugin_Directory\Readme\Parser as Readme_Parser;
#use WordPressdotorg\Two_Factor\Two_Factor_Core as Two_Factor_Core;
use Two_Factor_Core;

// This script should only be called in a CLI environment.
if ( 'cli' != php_sapi_name() ) {
	die();
}

ob_start();

$opts = getopt( '', array( 'url:', 'abspath:', 'plugin:', 'changed-tags:', 'top:', 'new:', 'installs:', 'async', 'create', 'author:', 'caped', 'mapfile:' ) );

// Guess the default parameters:
if ( empty( $opts ) && $argc == 2 ) {
	$opts['plugin'] = $argv[1];
	$argv[1]        = '--plugin ' . $argv[1];
}
if ( empty( $opts['url'] ) ) {
	$opts['url'] = 'https://wordpress.org/plugins/';
}
if ( empty( $opts['abspath'] ) && false !== strpos( __DIR__, 'wp-content' ) ) {
	$opts['abspath'] = substr( __DIR__, 0, strpos( __DIR__, 'wp-content' ) );
}

if ( empty( $opts['changed-tags'] ) ) {
	$opts['changed-tags'] = array( 'trunk' );
} else {
	$opts['changed-tags'] = explode( ',', $opts['changed-tags'] );
}

$opts['async']  = isset( $opts['async'] );
$opts['create'] = isset( $opts['create'] );

foreach ( array( 'url', 'abspath' ) as $opt ) {
	if ( empty( $opts[ $opt ] ) ) {
		fwrite( STDERR, "Missing Parameter: $opt\n" );
		fwrite( STDERR, "Usage: php {$argv[0]} --plugin hello-dolly --abspath /home/example/public_html --url https://wordpress.org/plugins/\n" );
		fwrite( STDERR, "Optional: --async to queue a job to import, --create to create a Post if none exist.\n" );
		fwrite( STDERR, "--url and --abspath will be guessed if possible.\n" );
		die();
	}
}

// Bootstrap WordPress
$_SERVER['HTTP_HOST']   = parse_url( $opts['url'], PHP_URL_HOST );
$_SERVER['REQUEST_URI'] = parse_url( $opts['url'], PHP_URL_PATH );

require rtrim( $opts['abspath'], '/' ) . '/wp-load.php';

if ( ! class_exists( '\WordPressdotorg\Plugin_Directory\Plugin_Directory' ) ) {
	fwrite( STDERR, "Error! This site doesn't have the Plugin Directory plugin enabled.\n" );
	if ( defined( 'WPORG_PLUGIN_DIRECTORY_BLOGID' ) ) {
		fwrite( STDERR, "Run the following command instead:\n" );
		fwrite( STDERR, "\tphp " . implode( ' ', $argv ) . ' --url ' . get_site_url( WPORG_PLUGIN_DIRECTORY_BLOGID, '/' ) . "\n" );
	}
	die();
}

function clear_memory_caches() {
    global $wpdb, $wp_object_cache;

    $wpdb->queries = [];

    if ( is_object( $wp_object_cache ) ) {
        $wp_object_cache->cache          = [];
        $wp_object_cache->group_ops      = [];
        $wp_object_cache->memcache_debug = [];
        $wp_object_cache->stats          = [ 'get' => 0, 'delete' => 0, 'add' => 0 ];
    }
}

function is_caped_user_login( $user_login ) {
	$user = get_user_by( 'login', $user_login );
	return is_caped( $user->ID );
}

function get_cves_for_plugin( $slug ) {
	static $cve_data;

	if ( is_null( $cve_data ) ) {
		$fp = fopen( __DIR__ . '/cve-all.csv', 'r' );
		while ( $line = fgetcsv( $fp ) ) {
			list( $cve_id, $date, $plugin_slug, $comparator, $version, $url ) = $line;
			@$cve_data[ $plugin_slug ][] = $line;
		}
		fclose( $fp );
	}

	return $cve_data[ $slug ] ?? [];

}

function find_version_after_cve( $cve, $releases ) {
	$last = null;
	if ( !$releases || !is_array( $releases ) ) {
		return;
	}

	// Loop backwards through releases and return the one before the first affected version
	usort( $releases, function( $a, $b ) { return version_compare( $b['version'], $a['version'] ); } );

	foreach ( $releases as $release ) {
		if ( check_version_against_cve( $release['version'], $cve ) ) {
			#var_dump( $release['version'] . ' affected at ' . $cve[3] . ' ' . $cve[4] );
			return $last;
		}
		$last = $release;
		#var_dump( $release['version'] . ' unaffected at ' . $cve[3] . ' ' . $cve[4] );
	}
}

function check_version_against_cve( $version, $cve ) {
	$ops = [
		'lt' => '<',
		'lte' => '<=',
		'gt' => '>',
		'gte' => '>=',
		'eq' => '=',
	];

	// true = affected, false = unaffected
	return version_compare( $version, $cve[4], $ops[ $cve[3] ] );
}

function check_version_against_all_cves( $version, $cves ) {
	foreach ( $cves as $cve ) {
		if ( !$cve[4] ) {
			continue; // skip cves with unknown versions
		};
		$affected = check_version_against_cve( $version, $cve );
		if ( $affected ) {
			return $cve[0]; // Return the CVE ID
		}
	}
	return false;
}

function get_trunk_readme_txt( $slug ) {
	$readme_url = 'https://plugins.svn.wordpress.org/' . urlencode( $slug ) . '/trunk/';

	$tmpdir = Filesystem::temp_directory( "readme-{$slug}" );

	$r = SVN::export( $readme_url, $tmpdir . '/trunk' );

	$readme = Import::find_readme_file( $tmpdir . '/trunk' );

	return new Readme_Parser( $readme );
}

function print_checklist_item( $checked, $message, $indent = 0 ) {
	echo str_repeat( '    ', $indent );
	echo ( $checked ? '✅' : '❌' );
	if ( is_array( $message ) ) {
		echo '  ' . ( $checked ? $message[0] : $message[1] ) . "\n";
	} else {
		echo '  ' . $message . "\n";
	}
}

function display_checklist_for_plugin( $plugin_slug, $include_closed = true ) {
	global $action_required_2fa;
	global $action_required_2fa_plugins;
	global $action_required_dormant;
	global $action_required_old_committer;
	global $action_required_unmapped_committer;
	global $last_login;
	global $map_usernames;

	$post = Plugin_Directory::get_plugin_post( $plugin_slug );

	if ( !$post || is_wp_error( $post ) ) {
		die( "Unknown post $plugin_slug\n" );
	}

	if ( 'closed' === $post->post_status && !$include_closed ) {
		return false;
	}

	echo "Plugin:   {$post->post_title}\n";
	echo "Slug:     {$post->post_name}\n";
	echo "Status:   {$post->post_status}\n";
	echo "Installs: " . number_format( $post->active_installs ) . "\n";

	print_checklist_item( $post->release_confirmation, "Release Confirmation" );
	print_checklist_item( $post->stable_tag && 'trunk' !== $post->stable_tag, "Stable tag {$post->stable_tag}" );
	$readme =  get_trunk_readme_txt( $post->post_name );
	if ( $readme ) {
		if ( $readme->stable_tag !== 'trunk' && $post->stable_tag === 'trunk' ) {
			print_checklist_item( false, "Readme Stable Tag {$readme->stable_tag} does not exist, current release is trunk!", 1 );
		}
	}

	print_checklist_item( !Template::is_plugin_outdated( $post ), "Tested Up To WP {$post->tested}" );

	$committers = Tools::get_plugin_committers( $post );
	$has_2fa = [];
	$last_login = [];

	foreach ( $committers as $committer ) {
		$user = get_user_by( 'login', $committer );
		$has_2fa[ $committer ] = Two_Factor_Core::is_user_using_two_factor( $user );
		$last_login[ $committer ] = $user->last_logged_in;
		if ( $map_usernames && !isset( $map_usernames[ strtolower( $committer ) ] ) ) {
			$action_required_unmapped_committer[ $committer ][] = $post->post_name;
		}
	}

	print_checklist_item( count( array_filter( $has_2fa ) ) === count( $committers ), "All committers use 2FA" );
	if ( count( array_filter( $has_2fa ) ) !== count( $committers ) ) {
		foreach ( $has_2fa as $login => $_has_2fa ) {
			print_checklist_item( $_has_2fa, "$login 2FA", 1 );
			if ( !$_has_2fa ) {
				$action_required_2fa[] = $login;
				$action_required_2fa_plugins[] = $post->post_name;
			}
		}
	}

	foreach ( $last_login as $committer => $last_logged_in ) {
		if ( !$last_logged_in ) {
			print_checklist_item( false, "$committer never logged in!" );
		} else {
			$now = new \DateTime( 'now' );
			$last = new \DateTime( $last_logged_in );
			$since = date_diff( $last, $now );
			if ( $since->days > 180 ) {
				print_checklist_item( false, "$committer last logged in {$since->days} days ago" );
				$action_required_dormant[] = $committer;
			}
		}
	}

	global $wpdb;
	$commit_dates = $wpdb->get_results( $wpdb->prepare(
		"SELECT username, MAX(pubdate) as most_recent_commit FROM trac_plugins WHERE `slug` = %s AND category = 'changeset' GROUP BY username ORDER BY username",
		$post->post_name
	) );

	if ( $commit_dates ) {
		$committer_dates = wp_list_pluck( $commit_dates, 'most_recent_commit', 'username' );
		foreach ( $committers as $committer ) {
			if ( empty( $committer_dates[ $committer ] ) ) {
				print_checklist_item( false, "$committer has no commits" );
			} else {
				$now = new \DateTime( 'now' );
				$last = new \DateTime( $committer_dates[ $committer ] );
				$since = date_diff( $last, $now );
				if ( $since->days > 365 ) {
					print_checklist_item( false, "$committer last commit was {$since->days} days ago" );
				}
			}
		}
	}

	$emails = Tools::get_helpscout_emails( $post );
	$last_email_date = null;
	$last_email_login = null;

	foreach( $emails as $email ) {
		if ( $email->user_id ) {
			$email_user = get_user_by( 'id', $email->user_id );
			if ( in_array( $email_user->user_login, $committers ) ) {
				$last_email_date = $email->modified;
				$last_email_login = $email_user->user_login;
				break; // Stop at the most recent
			}
		}
	}

	$now = new \DateTime( 'now' );
	$last = new \DateTime( $last_email_date );
	$since = date_diff( $last, $now );
	print_checklist_item( $last_email_date && $since->days < 180, "Recent reply to PRT $last_email_login" );

	foreach ( get_cves_for_plugin( $post->post_name ) as $cve ) {
		print_checklist_item( !check_version_against_cve( $post->version, $cve ), "CVE {$cve[0]}" );	
		$fixed_in_release = find_version_after_cve( $cve, $post->releases );
		if ( $fixed_in_release ) {
			$cve_date = new \DateTime( $cve[1] );
			$fix_date = new \DateTime( '@' . $fixed_in_release['date'] );
			$time_to_fix = date_diff( $fix_date, $cve_date );
			$fix_version = $fixed_in_release['version'];
			print_checklist_item( $time_to_fix->days < 30, "CVE fixed in $time_to_fix->days days in $fix_version", 1 );
		} else {
			print_checklist_item( false, "fix unknown {$cve[4]} {$cve[3]}",  1 );
		}

	}

	#var_dump( $post->_import_warnings );

	$recent_releases = array_slice( $post->releases ?: [], 0, 10 );
	#var_dump( $recent_releases );
	echo "Current version $post->version\n";
	echo human_time_diff( get_post_modified_time( 'U', true, $post ), current_time( 'U', true ) ) . "\n";
	display_version_matrix( $post->post_name, $post->version );

	echo "Recent Releases:\n";
	foreach ( $recent_releases as $release ) {
		echo strftime( '%Y-%m-%d %H:%M', $release['date'] ) . "\t" . $release['tag'] . "\n";
		if ( $release['tag'] !== $release['version'] ) {
			print_checklist_item( false, "Tag '{$release['tag']}' does not match version '{$release['version']}'", 1 );
		}
		print_checklist_item( $release['confirmed'], [ 'Confirmed', 'Unconfirmed' ], 1 );
		print_checklist_item( !empty( $release['committer'] ), [ 'Committer: ' . join( ', ', $release['committer'] ), 'Unknown committer' ], 1 );
	}

	#var_dump( $post->tags );
	#
	$yesterday = (new \DateTime('yesterday'))->format( 'Y-m-d' );
	$version_usage = $wpdb->get_results( $wpdb->prepare( "SELECT rev2_plugin_daily_stats.plugin_id, plugin_name, name, `value`, `count` FROM `plugin_list` LEFT JOIN `rev2_plugin_daily_stats` USING (plugin_id) WHERE plugin_name= %s AND `date` = %s AND name='usage' GROUP BY rev2_plugin_daily_stats.plugin_id, name, `value` ORDER BY `value` DESC", $post->post_name, $yesterday ) );
	#var_dump( $version_usage );
	$all_cves = get_cves_for_plugin( $post->post_name );
	#var_dump( 'all_cves', $all_cves );
	$total_usage = 0;
	$usage_secure = 0;
	$usage_insecure = 0;
	$cve_stats = [];
	foreach ( $version_usage as $usage ) {
		$total_usage += $usage->count;
		if ( $cve_id = check_version_against_all_cves( $usage->value, $all_cves ) ) {
			$usage_insecure += $usage->count;
			#print_checklist_item( false, "Insecure version {$usage->value} ($usage->count)" );
			@$cve_stats[ $cve_id ] += $usage->count;
		} else {
			$usage_secure += $usage->count;
		}
	}
	echo number_format( $usage_secure / $total_usage * 100.0 ) . "% running secure versions\n";
	echo number_format( $usage_insecure / $total_usage * 100.0 ) . "% running insecure versions\n";
	foreach ( $cve_stats as $cve_id => $cve_count ) {
		echo "\t" . $cve_id . ': ' . number_format( $cve_count / $total_usage * 100.0 ) . "%\n";
	}

	return true;
}

function display_version_matrix( $plugin_slug, $plugin_version ) {
	global $wpdb;

	#$version_usage = $wpdb->get_results( $wpdb->prepare( "SELECT rev2_plugin_daily_stats.plugin_id, plugin_name, name, `value`, `count` FROM `plugin_list` LEFT JOIN                     `rev2_plugin_daily_stats` USING (plugin_id) WHERE plugin_name= %s AND `date` = %s AND name='usage' GROUP BY rev2_plugin_daily_stats.plugin_id, name, `value` ORDER BY `value` DESC",     $post->post_name, $yesterday ) );

	$plugin_id = $wpdb->get_var( $wpdb->prepare( "SELECT plugin_id FROM plugin_list WHERE plugin_name = %s", $plugin_slug ) );
	if ( !$plugin_id ) {
		#var_dump( $plugin_id, $wpdb->last_query, $wpdb->last_error );
		return false;
	}
	#$versions = $wpdb->get_results( $wpdb->prepare( "SELECT version, version_desc, count(*) FROM `plugins_installed` INNER JOIN wp_versions USING(url_id) INNER JOIN wp_version_list USING(wp_version_id) WHERE plugin_id = %d GROUP BY version, wp_version_id ORDER BY version, version_desc DESC 
	$versions = $wpdb->get_results( $wpdb->prepare( "SELECT version_desc, count(*) AS cc FROM `plugins_installed` INNER JOIN wp_versions USING(url_id) INNER JOIN wp_version_list USING(wp_version_id) WHERE plugin_id = %d AND version = %s GROUP BY wp_version_id ORDER BY version_desc DESC", $plugin_id, $plugin_version ) );
	#var_dump( $wpdb->last_query, $wpdb->last_error, $versions );
	$max_cc = max( wp_list_pluck( $versions, 'cc' ) );
	$total = array_sum( wp_list_pluck( $versions, 'cc' ) );
	$skipped = 0;
	for ( $i=0; $i < min( 10, count($versions) ); $i++ ) {
		if ( $versions[$i]->cc / $total < 0.001 ) {
			$skipped += $versions[$i]->cc;
			continue; // skip very small numbers
		}
		echo str_pad( $versions[$i]->version_desc, 20 );
		echo str_pad( number_format( $versions[$i]->cc / $total * 100.0, 1 ) . '%', 8);
		echo str_repeat( '▧', $versions[$i]->cc / $max_cc * 50 );
		echo "\n";
	}
	// Everything not explicitly displayed
	$remainder = $skipped + array_sum( wp_list_pluck( array_slice( $versions, 10 ), 'cc' ) );
	if ( $remainder ) {
		echo "Others              ";
		echo str_pad( number_format( $remainder / $total * 100.0, 1 ) . '%', 8 );
		echo "\n";
	}
	echo number_format( $total ), " total\n";

}

$action_required_2fa = [];
$action_required_dormant = [];
$map_usernames = [];

if ( isset( $opts['mapfile'] ) ) {
	$fp = fopen( $opts['mapfile'], 'r' );
	while ( $line = fgetcsv( $fp ) ) {
		$map_usernames[ strtolower( $line[3] ) ] = strtolower( $line[1] );
	}
	fclose( $fp );
}

if ( isset( $opts['caped'] ) ) {
	global $comitters;
	global $supes;
	global $emeritus_committers;
	global $watch_passwords;

	$caped_users = array_merge( $supes, array_keys($committers), $emeritus_committers, $watch_passwords );
	sort( $caped_users );
	$caped_users = array_unique( $caped_users );
	foreach ( $caped_users as $caped_user ) {
		$user = get_user_by( 'login', $caped_user );
		print_checklist_item( Two_Factor_Core::is_user_using_two_factor( $user ), "caped user $caped_user 2FA" );
	}
	exit;
}

if ( $opts['plugin'] ) {
	display_checklist_for_plugin( $opts['plugin'] );
	echo "\n\n";
	if ( count( $action_required_unmapped_committer ) ) {
		echo "Users with unmapped usernames:\n";
		foreach ( $action_required_unmapped_committer as $committer => $plugins ) {
			echo $committer . ' (' . join( ', ', $plugins ) . ")\n";
		}
		echo "\n\n";
	}

}
if ( $opts['author'] ) {
	$plugins = [];
	$authors = explode( ',', $opts['author'] );
	foreach ( $authors as $author ) {
		$author_plugins = Tools::get_users_write_access_plugins( $author );
		if ( $author_plugins ) {
			$plugins = array_merge( $plugins, $author_plugins );
		}
	}
	$plugins = array_unique( $plugins );
	foreach ( $plugins as $plugin ) {
		if ( display_checklist_for_plugin( $plugin, false ) ) {
			echo "\n\n";
		}
	}

	if ( $action_required_2fa ) {
		sort( $action_required_2fa );
		echo "Users requiring action (2FA):\n\n";
		foreach ( array_unique( $action_required_2fa ) as $username ) {
			$username = strtolower( $username );
			echo is_caped_user_login( $username ) ? "$username (caped!)" : "$username";
			if ( isset( $map_usernames[ $username ] ) ) {
				echo " (@{$map_usernames[$username]})";
			}
			if ( in_array( $username, $action_required_dormant ) ) {
				echo " *";
			}
			echo "\n";
		}
		echo "\n\n";
	}

	if ( $action_required_dormant ) {
		sort( $action_required_dormant );
		echo "Users requiring action (dormant committer account):\n\n";
		foreach ( array_unique( $action_required_dormant ) as $username ) {
			$username = strtolower( $username );
			echo is_caped_user_login( $username ) ? "$username (caped!)" : "$username";
			if ( isset( $map_usernames[ $username ] ) ) {
				echo " @{$map_usernames[$username]}";
			}
			echo "\n";
		}
		echo "\n\n";
	}

	if ( count( $action_required_unmapped_committer ) ) {
		echo "Users with unmapped usernames:\n";
		foreach ( $action_required_unmapped_committer as $committer => $plugins ) {
			echo $committer . ' (' . join( ', ', $plugins ) . ")\n";
		}
		echo "\n\n";
	}

}
if ( $opts['top'] || $opts['new'] ) {
	while (@ob_end_flush());

	if ( $opts['top'] ) {
		$args = [
			'post_status' => 'publish',
			'post_type' => 'plugin',
			'meta_key' => 'active_installs',
			'orderby' => 'meta_value_num',
			'order' => 'DESC',
			'posts_per_page' => intval( $opts['top'] ?: 5 )
		];
	} else {
		$args = [
			'post_status' => 'publish',
			'post_type' => 'plugin',
			'orderby' => 'post_modified_gmt',
			'order' => 'DESC',
			'posts_per_page' => intval( $opts['new'] ?: 5 )
		];
		if ( $opts['installs'] > 0 ) {
			$args[ 'meta_key' ] = 'active_installs';
			$args[ 'meta_value' ] = intval( $opts['installs'] );
			$args[' meta_compare' ] = '>=';
		}
		var_dump( $args );
	}

	$last_active_installs = null;
	$q = new \WP_Query( $args );
	var_dump( $q->request );
	foreach ( $q->posts as $i => $post ) {
		echo "#$i\n";
		display_checklist_for_plugin( $post->post_name );
		$last_active_installs = $post->active_installs;
		echo "\n\n";
		clear_memory_caches();
		flush();
	}

	if ( $action_required_2fa ) {
		sort( $action_required_2fa );
		echo "Users requiring action (2FA):\n\n";
		foreach ( array_unique( $action_required_2fa ) as $username ) {
			echo is_caped_user_login( $username ) ? "@$username (caped!)\n" : "@$username\n";
		}
		echo number_format( count( $action_required_2fa ) ) . " users\n";
		echo "\n\n";
	}

	$action_required_2fa_plugins = array_unique( $action_required_2fa_plugins );
	if ( count( $action_required_2fa_plugins ) ) {
		foreach ( $action_required_2fa_plugins as $plugin ) {
			echo $plugin . "\n";
		}
		echo number_format( count( array_unique( $action_required_2fa_plugins ) ) ) . " plugins require 2FA action\n";
		echo "\n\n";
	}

	echo number_format( $last_active_installs ) . " last active installs\n";
}

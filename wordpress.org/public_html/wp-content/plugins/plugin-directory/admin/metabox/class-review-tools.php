<?php
/**
 * The Plugin Review metabox.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */

namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * The Plugin Review metabox.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Review_Tools {
	/**
	 * Contains all flags.
	 *
	 * @var array
	 */
	public static $flagged = [
		'low'  => [],
		'med'  => [],
		'high' => [],
	];

	/**
	 * List of commonly abused/misused terms.
	 *
	 * @var array
	 */
	public static $reserved_slugs = [
		'apple',
		'contact-form-7',
		'facebook',
		'google',
		'instagram',
		'ios',
		'jetpack',
		'jquery',
		'microsoft',
		'paypal',
		'twitter',
		'woocommerce',
		'wordpress',
		'yoast',
		'youtube',
	];

	/**
	 * List of restricted plugin slugs.
	 *
	 * @var array
	 */
	public static $restricted_slugs = [
		// High-value plugin genres due to their popularity, often abused by spammers.
		'booking',
		'bookmark',
		'cookie',
		'gallery',
		'lightbox',
		'seo',
		'sitemap',
		'slide',
		'social',

		// Plugins we generally don't allow.
		'autoblog',
		'auto-blog',
		'framework',
		'library',
		'plugin',
		'spinning',
	];

	/**
	 * List of suspicious URLs.
	 *
	 * @var array
	 */
	public static $weird_urls = [
		'blogger',
		'blogspot',
		'example.com',
		'weebly',
		'squarespace',
		'medium.com',
		'yahoo.com',
		'@mail.com',
		'example.org',
		'wordpress.com',
	];

	/**
	 * Displays links to plugin assets and automated flags.
	 */
	public static function display() {
		$post   = get_post();
		$author = get_user_by( 'id', $post->post_author );
		$slug   = $post->post_name;

		$author_commit = Tools::get_users_write_access_plugins( $author );
		// phpcs:ignore WordPress.VIP.RestrictedFunctions.get_posts_get_posts
		$author_plugins = get_posts( [
			'author'           => $author->ID,
			'post_type'        => 'plugin',
			'post__not_in'     => [ $post->ID ],
			'suppress_filters' => false,
		] );

		if ( in_array( $post->post_status, [ 'draft', 'pending', 'new' ], true ) ) {

			$zip_files = array();
			foreach ( get_attached_media( 'application/zip', $post ) as $zip_file ) {
				$zip_files[ $zip_file->post_date ] = array( wp_get_attachment_url( $zip_file->ID ), $zip_file );
			}
			uksort( $zip_files, function ( $a, $b ) {
				return strtotime( $a ) < strtotime( $b );
			} );

			$zip_url = get_post_meta( $post->ID, '_submitted_zip', true );
			if ( $zip_url ) {
				// Back-compat only.
				$zip_files['User provided URL'] = array( $zip_url, null );
			}

			echo '<p><strong>Zip files:</strong></p>';
			echo '<ul class="plugin-zip-files">';
			foreach ( $zip_files as $zip_date => $zip ) {
				list( $zip_url, $zip_file ) = $zip;
				$zip_size                   = is_object( $zip_file ) ? size_format( filesize( get_attached_file( $zip_file->ID ) ), 1 ) : 'unknown size';

				printf( '<li>%s <a href="%s">%s</a> (%s)</li>', esc_html( $zip_date ), esc_url( $zip_url ), esc_html( $zip_url ), esc_html( $zip_size ) );
			}
			echo '</ul>';

			$slug_restricted = [];
			$slug_reserved   = [];

			// String length checks.
			if ( strlen( $slug ) < 5 ) {
				array_push( self::$flagged['med'], 'slug is less than 5 characters' );
			}
			if ( strlen( $slug ) > 50 ) {
				array_push( self::$flagged['med'], 'slug is more than 50 characters' );
			}

			// Check if any term in the restricted/reserved is in the plugin slug.
			$slug_string = str_replace( '-', ' ', $slug );

			foreach ( self::$restricted_slugs as $bad_slug ) {
				if ( false !== stristr( $slug_string, $bad_slug ) ) {
					array_push( $slug_restricted, $bad_slug );
				}
			}
			foreach ( self::$reserved_slugs as $bad_slug ) {
				if ( false !== stristr( $slug_string, $bad_slug ) ) {
					array_push( $slug_reserved, $bad_slug );
				}
			}
			if ( ! empty( $slug_restricted ) ) {
				array_push( self::$flagged['med'], 'plugin slug contains restricted term(s): ' . implode( ', ', $slug_restricted ) );
			}
			if ( ! empty( $slug_reserved ) ) {
				array_push( self::$flagged['high'], 'plugin slug contains reserved term(s): ' . implode( ', ', $slug_reserved ) );
			}

			// Check slug usage.
			$plugin_api_usage = intval( get_post_meta( $post->ID, 'active_installs', true ) );
			if ( $plugin_api_usage >= '5000' ) {
				array_push( self::$flagged['high'], 'slug used by more than 5000 users' );
			} elseif ( $plugin_api_usage >= '1000' ) {
				array_push( self::$flagged['med'], 'slug used by 1000-5000 users' );
			} elseif ( $plugin_api_usage >= '500' ) {
				array_push( self::$flagged['low'], 'slug used by 500-1000 users' );
			}

			// Check Active_Installs, prior to sync?
			if ( function_exists( 'wporg_stats_get_plugin_name_install_count' ) ) {

				$title_matches = wporg_stats_get_plugin_name_install_count( $post->post_title );

				if ( $post->post_name == sanitize_title( $post->post_title ) ) {
					$slug_matches = false;
				} else {
					$slug = str_replace( '-', ' ', $post->post_name );
					$slug_matches = wporg_stats_get_plugin_name_install_count( $slug );
				}

				if ( $title_matches ) {
					array_push( self::$flagged['high'], sprintf(
						'As of %s, %s had %s+ active installs.',
						esc_html( $title_matches->date ),
						esc_html( $post->post_title ),
						number_format( $title_matches->count )
					) );
				}
				if ( $slug_matches ) {
					array_push( self::$flagged['high'], sprintf(
						'As of %s, %s had %s+ active installs.',
						esc_html( $slug_matches->date ),
						esc_html( $slug ),
						number_format( $slug_matches->count )
					) );
				}
			}

			// User account was registered less than 2 weeks ago (but longer than 3 days) (user is still fairly new).
			$two_weeks_ago  = time() - ( 2 * WEEK_IN_SECONDS );
			$three_days_ago = time() - ( 3 * DAY_IN_SECONDS );
			if ( strtotime( $author->user_registered ) > $two_weeks_ago && strtotime( $author->user_registered ) < $three_days_ago ) {
				array_push( self::$flagged['low'], 'account registered less than 2 weeks ago' );
			}
			if ( strtotime( $author->user_registered ) > $three_days_ago ) {
				array_push( self::$flagged['low'], 'account registered less than 3 days ago' );
			}

			// Username ends in numbers.
			if ( is_numeric( substr( $author->user_login, - 1, 1 ) ) ) {
				array_push( self::$flagged['low'], 'username ends in numbers' );
			}

			// User has no URL.
			if ( empty( $author->user_url ) ) {
				array_push( self::$flagged['low'], 'account has no URL' );
			}

			// URLs and domains that are often abused.
			foreach ( self::$weird_urls as $url ) {
				if ( false !== stripos( $author->user_url, $url ) ) {
					array_push( self::$flagged['med'], 'account URL contains ' . $url );
				}
				if ( false !== stripos( $author->user_email, $url ) ) {
					array_push( self::$flagged['med'], 'account email contains ' . $url );
				}
			}

			// Reserved slugs are also often abused domain names (trademark law sucks).
			foreach ( self::$reserved_slugs as $url ) {
				if ( false !== stripos( $author->user_url, $url ) ) {
					array_push( self::$flagged['high'], 'account URL contains ' . $url );
				}
				if ( false !== stripos( $author->user_email, $url ) ) {
					array_push( self::$flagged['med'], 'account email contains ' . $url );
				}
			}

			// User Behavior.
			// If FORUM ROLE is blocked.
			if ( defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) ) {
				$user = new \WP_User( $post->post_author, '', WPORG_SUPPORT_FORUMS_BLOGID );
				if ( ! empty( $user->allcaps['bbp_blocked'] ) ) {
					array_push( self::$flagged['high'], 'user is blocked' );
				}
			}

			// No plugins.
			if ( empty( $author_commit ) && empty( $author_plugins ) ) {
				array_push( self::$flagged['low'], 'user has no plugins' );
			}

			// Echo flag results (everyone pretty much has at least one).
			echo '<ul class="plugin-flagged">';
			foreach ( self::$flagged as $flag => $reasons ) {
				if ( count( $reasons ) ) {
					echo '<li class="plugin-flagged-' . esc_attr( $flag ) . '"><strong>' . esc_html( strtoupper( $flag ) ) . ' (' . esc_html( count( $reasons ) ) . '):</strong> ' . esc_html( implode( '; ', $reasons ) ) . '</li>';
				}
			}
			echo '</ul>';
		} else {
			?>
			<ul>
				<li><a href='https://plugins.trac.wordpress.org/log/<?php echo esc_attr( $post->post_name ); ?>/'>Development Log</a></li>
				<li><a href='https://plugins.svn.wordpress.org/<?php echo esc_attr( $post->post_name ); ?>/'>Subversion Repository</a></li>
				<li><a href='https://plugins.trac.wordpress.org/browser/<?php echo esc_attr( $post->post_name ); ?>/'>Browse in Trac</a></li>
				<li><a href='<?php echo esc_url( Template::download_link() ); ?>'>Download Current Version</a></li>
				<?php if ( has_term( 'block', 'plugin_section', $post ) ) : ?>
					<li><a href='https://wordpress.org/plugins/developers/block-plugin-validator/?plugin_url=<?php echo esc_attr( $post->post_name ); ?>'>Block Plugin Checker</a></li>
				<?php endif; ?>
			</ul>
			<?php
		}

		add_filter( 'wp_comment_reply', function ( $string ) use ( $post, $author ) {
			$committers = Tools::get_plugin_committers( $post->post_name );
			$committers = array_map( function ( $user_login ) {
				return get_user_by( 'login', $user_login );
			}, $committers );

			$cc_emails = wp_list_pluck( $committers, 'user_email' );
			$cc_emails = implode( ', ', array_diff( $cc_emails, array( $author->user_email ) ) );

			if ( 'new' === $post->post_status || 'pending' === $post->post_status ) {
				/* translators: %s: Plugin Title */
				$subject = sprintf( __( '[WordPress Plugin Directory] Review in Progress: %s', 'wporg-plugins' ), $post->post_title );
			} elseif ( 'rejected' === $post->post_status ) {
				/* translators: %s: Plugin Title */
				$subject = sprintf( __( '[WordPress Plugin Directory] Rejection Explanation: %s', 'wporg-plugins' ), $post->post_title );
			} elseif ( 'closed' === $post->post_status || 'disabled' === $post->post_status ) {
				$close_reason  = (string) get_post_meta( $post->ID, '_close_reason', true );
				$close_reasons = array(
					'security-issue'                => __( 'Security', 'wporg-plugins' ),
					'guideline-violation'           => __( 'Guideline Violation', 'wporg-plugins' ),
					'licensing-trademark-violation' => __( 'Licensing/Trademark Violation', 'wporg-plugins' ),
				);

				if ( isset( $close_reasons[ $close_reason ] ) ) {
					/* translators: 1: Closure Reason, 2: Plugin Title */
					$subject = sprintf( __( '[WordPress Plugin Directory] Closure Notice - %1$s: %2$s', 'wporg-plugins' ), $close_reasons[ $close_reason ], $post->post_title );
				} else {
					/* translators: %s: Plugin Title */
					$subject = sprintf( __( '[WordPress Plugin Directory] Closure Notice: %s', 'wporg-plugins' ), $post->post_title );
				}
			} else {
				/* translators: %s: Plugin Title */
				$subject = sprintf( __( '[WordPress Plugin Directory] Notice: %s', 'wporg-plugins' ), $post->post_title );
			}

			// HelpScout requires urlencode() because it wants spaces as + signs.
			$contact_author = 'https://secure.helpscout.net/mailbox/ad3e85554c5bd064/new-ticket/?name=' . $author->display_name . '&email=' . urlencode( $author->user_email ) . '&cc=' . urlencode( $cc_emails ) . '&subject=' . urlencode( $subject );
			?>
			<a id="contact-author" class="button button-primary" href="<?php echo esc_url( $contact_author ); ?>">Contact plugin committer(s)</a>
			<?php

			return $string;
		} );
	}
}

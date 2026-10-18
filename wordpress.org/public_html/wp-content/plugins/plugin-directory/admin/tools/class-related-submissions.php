<?php
/**
 * Related Submissions tool.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Tools
 */

namespace WordPressdotorg\Plugin_Directory\Admin\Tools;

/**
 * Finds plugins by different authors that were submitted from the same or similar IPs, or that share a domain.
 *
 * Authors may only have one plugin in review at a time, so the plugins in the review queue
 * are compared with each other and with recently published plugins.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Tools
 */
class Related_Submissions {

	/**
	 * The transient that holds the matches.
	 */
	const CACHE_KEY = 'plugin_related_submissions';

	/**
	 * Published plugins are compared when they went live within this period.
	 */
	const PUBLISHED_WITHIN = '-4 months';

	/**
	 * Prefix lengths, in bits, of the IPv4 and IPv6 ranges whose addresses are considered similar.
	 */
	const IPV4_RANGE = 24;
	const IPV6_RANGE = 64;

	/**
	 * Domains used by unrelated people, such as email providers, URL shorteners, code hosts, and social networks.
	 *
	 * Compared with the registrable domain, so their subdomains are ignored too. Entries ending in `.*`
	 * match the name with any suffix, such as yahoo.fr and yahoo.co.uk.
	 *
	 * @var array
	 */
	public static $shared_domains = array(
		// Email.
		'163.com',
		'aol.*',
		'gmail.com',
		'gmx.*',
		'googlemail.com',
		'hotmail.*',
		'icloud.com',
		'live.*',
		'mail.com',
		'mail.ru',
		'me.com',
		'msn.com',
		'outlook.*',
		'pm.me',
		'proton.me',
		'protonmail.*',
		'qq.com',
		'web.de',
		'wp.pl',
		'yahoo.*',
		'yandex.*',
		'ymail.com',
		'zoho.*',

		// URL shorteners.
		'bit.ly',
		'bitly.com',
		'buff.ly',
		'clck.ru',
		'cutt.ly',
		'cutt.us',
		'goo.gl',
		'is.gd',
		'lnkd.in',
		'ow.ly',
		'rb.gy',
		'rebrand.ly',
		'rotf.lol',
		's.id',
		'short.gy',
		'shorte.st',
		'shorturl.at',
		'soo.gd',
		'spoo.me',
		'surl.li',
		't.co',
		't.ly',
		'tiny.cc',
		'tiny.one',
		'tinyurl.com',
		'urlz.fr',
		'v.gd',

		// Code.
		'bitbucket.org',
		'codecanyon.net',
		'envato.com',
		'fiverr.com',
		'github.com',
		'gitlab.com',
		'upwork.com',
		'w.org',
		'wordpress.org',

		// Social.
		'about.me',
		'facebook.com',
		'gravatar.com',
		'instagram.com',
		'linkedin.com',
		'medium.com',
		'pinterest.*',
		't.me',
		'tiktok.com',
		'twitter.com',
		'wa.me',
		'x.com',
		'youtube.com',

		// Payments.
		'buymeacoffee.com',
		'paypal.*',
		'stripe.com',

		// Reserved for documentation.
		'example.*',
	);

	/**
	 * Domains on which each subdomain is a different site, such as {user}.github.io.
	 *
	 * The domains themselves are ignored, like the shared ones.
	 *
	 * @var array
	 */
	public static $hosting_domains = array(
		'blogspot.com',
		'gitbook.io',
		'github.io',
		'gitlab.io',
		'herokuapp.com',
		'netlify.app',
		'pages.dev',
		'vercel.app',
		'web.id',
		'wixsite.com',
		'wordpress.com',
		'wpcomstaging.com',
	);

	/**
	 * Fetch the instance of the Related_Submissions class.
	 */
	public static function instance() {
		static $instance = null;

		return $instance ?: $instance = new self();
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_to_menu' ) );
	}

	/**
	 * Adds the "Related Submissions" link to the admin menu under "Plugin Tools".
	 */
	public function add_to_menu() {
		add_submenu_page(
			'plugin-tools',
			__( 'Related Submissions', 'wporg-plugins' ),
			__( 'Related Submissions', 'wporg-plugins' ),
			'plugin_review',
			'related-submissions',
			array( $this, 'render' )
		);
	}

	/**
	 * Refreshes the cached matches, so that author cards don't have to find them.
	 */
	public static function cron_trigger() {
		self::get_matches( true );
	}

	/**
	 * Gets the groups of plugins by different authors that share an IP, an IP range, or a domain.
	 *
	 * Each group has at least one plugin in the review queue.
	 *
	 * @param bool $refresh Optional. Whether to ignore the cached matches. Default false.
	 * @return array {
	 *     @type int   $time    When the matches were found.
	 *     @type array $plugins The grouped plugins, keyed by post ID.
	 *     @type array $groups  Lists of post IDs, keyed by the match type ('ip', 'ip_range', or 'domain')
	 *                          and the shared value. Each post ID holds the values that matched.
	 * }
	 */
	public static function get_matches( $refresh = false ) {
		global $wpdb;

		$matches = $refresh ? false : get_transient( self::CACHE_KEY );
		if ( $matches ) {
			return $matches;
		}

		// Newest first, so that the groups are ordered by their newest plugin.
		$plugins = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_author, p.post_name, p.post_status, p.post_date_gmt, u.user_login, u.user_email, u.user_url
				FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->users} u ON u.ID = p.post_author
				WHERE p.post_type = 'plugin'
					AND ( p.post_status IN ( 'new', 'pending', 'approved' ) OR ( p.post_status = 'publish' AND p.post_date >= %s ) )
				ORDER BY p.post_date DESC",
				wp_date( 'Y-m-d H:i:s', strtotime( self::PUBLISHED_WITHIN ) )
			),
			OBJECT_K
		);

		// One pass over the meta, as a join per key is several times slower.
		$meta = array();
		if ( $plugins ) {
			$post_ids = implode( ',', array_keys( $plugins ) );
			$meta     = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer post IDs from the query above.
				"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ( {$post_ids} ) AND meta_key IN ( '_author_ip', 'header_author_uri', 'header_plugin_uri' )"
			);
		}
		foreach ( $meta as $row ) {
			$plugins[ $row->post_id ]->{ $row->meta_key } = $row->meta_value;
		}

		$buckets = array();
		foreach ( $plugins as $id => $plugin ) {
			foreach ( preg_split( '/[\s,]+/', $plugin->_author_ip ?? '', -1, PREG_SPLIT_NO_EMPTY ) as $ip ) {
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					$ip = inet_ntop( inet_pton( $ip ) );

					$buckets['ip'][ $ip ][ $id ] = array();

					$buckets['ip_range'][ self::get_ip_range( $ip ) ][ $id ][] = $ip;
				}
			}

			$sources = array(
				'user_email'        => substr( strrchr( (string) $plugin->user_email, '@' ), 1 ),
				'user_url'          => (string) $plugin->user_url,
				'header_author_uri' => $plugin->header_author_uri ?? '',
				'header_plugin_uri' => $plugin->header_plugin_uri ?? '',
			);
			foreach ( $sources as $source => $value ) {
				$domain = self::get_domain( $value );
				if ( $domain ) {
					$buckets['domain'][ $domain ][ $id ][] = $source;
				}
			}
		}

		$groups = array();
		foreach ( $buckets as $type => $values ) {
			foreach ( $values as $value => $matched ) {
				$authors  = array();
				$in_queue = false;
				foreach ( array_keys( $matched ) as $id ) {
					$authors[ $plugins[ $id ]->post_author ] = true;

					// Published plugins are only listed alongside a plugin in the queue.
					$in_queue = $in_queue || 'publish' !== $plugins[ $id ]->post_status;
				}

				// A range with a single IP in it is already listed as the same IP.
				if ( 'ip_range' === $type && 1 === count( array_unique( array_merge( ...array_values( $matched ) ) ) ) ) {
					continue;
				}

				if ( count( $authors ) > 1 && $in_queue ) {
					$groups[ $type ][ $value ] = $matched;
				}
			}
		}

		// Only keep what's displayed, to keep the cache small.
		$fields  = array( 'ID', 'post_author', 'post_name', 'post_status', 'post_date_gmt', 'user_login' );
		$grouped = array();
		foreach ( $groups as $values ) {
			foreach ( $values as $matched ) {
				foreach ( array_keys( $matched ) as $id ) {
					$grouped[ $id ] = (object) wp_array_slice_assoc( (array) $plugins[ $id ], $fields );
				}
			}
		}

		$matches = array(
			'time'    => time(),
			'plugins' => $grouped,
			'groups'  => $groups,
		);

		set_transient( self::CACHE_KEY, $matches, HOUR_IN_SECONDS );

		return $matches;
	}

	/**
	 * Gets the range of an IP, such as 192.0.2.0/24.
	 *
	 * @param string $ip IP address.
	 * @return string
	 */
	protected static function get_ip_range( $ip ) {
		$packed = inet_pton( $ip );
		$bits   = 4 === strlen( $packed ) ? self::IPV4_RANGE : self::IPV6_RANGE;

		return inet_ntop( str_pad( substr( $packed, 0, $bits / 8 ), strlen( $packed ), "\0" ) ) . '/' . $bits;
	}

	/**
	 * Gets the registrable domain of a URL or a host, such as example.co.uk for https://www.example.co.uk/.
	 *
	 * @param string $url URL or host.
	 * @return string The domain, or an empty string when there's none or it's used by unrelated people.
	 */
	protected static function get_domain( $url ) {
		$host   = wp_parse_url( str_contains( $url, '//' ) ? $url : "//{$url}", PHP_URL_HOST );
		$host   = strtolower( trim( (string) $host, '.' ) );
		$labels = explode( '.', $host );
		if ( count( $labels ) < 2 || filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return '';
		}

		// Keep one label before the suffix: example.com, example.co.uk, or user.github.io.
		$suffix = 1;
		$tld    = $labels[ count( $labels ) - 1 ];
		$sld    = $labels[ count( $labels ) - 2 ];
		if (
			in_array( "{$sld}.{$tld}", self::$hosting_domains, true ) ||
			( 2 === strlen( $tld ) && in_array( $sld, array( 'ac', 'co', 'com', 'edu', 'gov', 'ne', 'net', 'or', 'org' ), true ) )
		) {
			$suffix = 2;
		}

		if ( count( $labels ) <= $suffix ) {
			return '';
		}

		$name   = $labels[ count( $labels ) - 1 - $suffix ];
		$domain = implode( '.', array_slice( $labels, -1 - $suffix ) );
		if ( in_array( $domain, self::$shared_domains, true ) || in_array( "{$name}.*", self::$shared_domains, true ) ) {
			return '';
		}

		return $domain;
	}

	/**
	 * Gets the headings of each match type.
	 *
	 * @return array
	 */
	protected static function get_type_labels() {
		return array(
			'ip'       => __( 'Same IP', 'wporg-plugins' ),
			'ip_range' => __( 'Similar IP', 'wporg-plugins' ),
			'domain'   => __( 'Same domain', 'wporg-plugins' ),
		);
	}

	/**
	 * Gets a link to a plugin, along with its status, author, and the values that matched.
	 *
	 * @param object $plugin  Plugin, as found by get_matches().
	 * @param array  $details The values that matched.
	 * @return string HTML.
	 */
	protected static function get_plugin_summary( $plugin, $details ) {
		$sources = array(
			'user_email'        => __( 'email', 'wporg-plugins' ),
			'user_url'          => __( 'profile URL', 'wporg-plugins' ),
			'header_author_uri' => __( 'Author URI', 'wporg-plugins' ),
			'header_plugin_uri' => __( 'Plugin URI', 'wporg-plugins' ),
		);
		$details = array_map( fn( $detail ) => $sources[ $detail ] ?? $detail, array_unique( $details ) );

		return sprintf(
			'<a href="%1$s">%2$s</a> (%3$s, %4$s, %5$s)%6$s',
			esc_url( get_edit_post_link( $plugin->ID, '' ) ),
			esc_html( $plugin->post_name ),
			esc_html( get_post_status_object( $plugin->post_status )->label ),
			esc_html( $plugin->user_login ),
			/* translators: %s: Time since the plugin was submitted or published. */
			esc_html( sprintf( __( '%s ago', 'wporg-plugins' ), human_time_diff( strtotime( $plugin->post_date_gmt ) ) ) ),
			$details ? ' &mdash; ' . esc_html( implode( ', ', $details ) ) : ''
		);
	}

	/**
	 * Displays the plugins by other authors that are related to an author's plugins, for the author card.
	 *
	 * @param int $user_id The author's user ID.
	 */
	public static function display_author_matches( $user_id ) {
		// Finding the matches is too slow for the author card, so they're only shown once cached.
		$matches = get_transient( self::CACHE_KEY );
		if ( ! $matches ) {
			return;
		}

		$labels = self::get_type_labels();
		$items  = array();

		foreach ( $matches['groups'] as $type => $values ) {
			foreach ( $values as $value => $matched ) {
				$others = array();
				foreach ( $matched as $id => $details ) {
					if ( (int) $user_id !== (int) $matches['plugins'][ $id ]->post_author ) {
						$others[] = self::get_plugin_summary( $matches['plugins'][ $id ], $details );
					}
				}

				if ( count( $others ) < count( $matched ) ) {
					$items[] = sprintf(
						'<li class="plugin-flagged-%s"><strong>%s:</strong> %s<ul><li>%s</li></ul></li>',
						'ip' === $type ? 'high' : 'med',
						esc_html( $labels[ $type ] ),
						esc_html( $value ),
						implode( '</li><li>', $others )
					);
				}
			}
		}

		if ( $items ) {
			printf(
				'<strong><a href="%s">%s</a></strong><ul class="plugin-flagged">%s</ul>',
				esc_url( admin_url( 'admin.php?page=related-submissions' ) ),
				esc_html__( 'Related Submissions:', 'wporg-plugins' ),
				wp_kses_post( implode( '', $items ) )
			);
		}
	}

	/**
	 * Renders the Related Submissions tool.
	 */
	public function render() {
		if ( ! current_user_can( 'plugin_review' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only finds the matches again.
		$matches = self::get_matches( ! empty( $_GET['refresh'] ) );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Related Submissions', 'wporg-plugins' ) . '</h1>';
		$description = sprintf(
			/* translators: 1: IPv4 prefix length, 2: IPv6 prefix length. */
			__( 'Plugins in the review queue that share an IP, a similar IP (in the same /%1$d IPv4 or /%2$d IPv6 range), or a domain (of the email, profile URL, Author URI, or Plugin URI) with plugins by other authors. Plugins published in the last 4 months are compared too.', 'wporg-plugins' ),
			self::IPV4_RANGE,
			self::IPV6_RANGE
		);
		echo '<p>' . esc_html( $description ) . '</p>';
		printf(
			'<p>%s <a href="%s">%s</a></p>',
			/* translators: %s: Time since the matches were found. */
			esc_html( sprintf( __( 'Checked %s ago.', 'wporg-plugins' ), human_time_diff( $matches['time'] ) ) ),
			esc_url( add_query_arg( 'refresh', 1 ) ),
			esc_html__( 'Check again', 'wporg-plugins' )
		);

		foreach ( self::get_type_labels() as $type => $label ) {
			echo '<h2>' . esc_html( $label ) . '</h2>';

			if ( empty( $matches['groups'][ $type ] ) ) {
				echo '<p>' . esc_html__( 'None found.', 'wporg-plugins' ) . '</p>';
				continue;
			}

			echo '<table class="widefat striped"><tbody>';
			foreach ( $matches['groups'][ $type ] as $value => $matched ) {
				$value_html = esc_html( $value );
				if ( 'ip' === $type ) {
					$search_url = add_query_arg( array( 's' => $value ), admin_url( 'edit.php?post_type=plugin' ) );
					$value_html = '<a href="' . esc_url( $search_url ) . '">' . $value_html . '</a>';
				}

				$logins     = array_unique( wp_list_pluck( array_intersect_key( $matches['plugins'], $matched ), 'user_login' ) );
				$author_url = add_query_arg( array( 'users' => rawurlencode( implode( ',', $logins ) ) ), admin_url( 'admin.php?page=authorcards' ) );

				printf(
					'<tr><td>%s<br><a href="%s">%s</a></td><td><ul>',
					wp_kses_post( $value_html ),
					esc_url( $author_url ),
					esc_html__( 'Compare author cards', 'wporg-plugins' )
				);
				foreach ( $matched as $id => $details ) {
					echo '<li>' . wp_kses_post( self::get_plugin_summary( $matches['plugins'][ $id ], $details ) ) . '</li>';
				}
				echo '</ul></td></tr>';
			}
			echo '</tbody></table>';
		}

		echo '</div>';
	}
}

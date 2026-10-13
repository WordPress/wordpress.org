<?php
/**
 * Create the meeting post type and assorted filters for https://make.wordpress.org/meetings
 *
 * @package WordPressdotorg\Meeting_Calendar
 */

declare( strict_types = 1 );

if ( ! class_exists( 'Meeting_Post_Type' ) ) :
	/**
	 * The meeting post type: registration, meta, occurrence logic, and the [meeting_time] shortcode.
	 */
	class Meeting_Post_Type {

		/**
		 * The single instance of the class.
		 *
		 * @var Meeting_Post_Type|null
		 */
		protected static $instance = null;

		/**
		 * Get the single instance of the class.
		 *
		 * @return Meeting_Post_Type
		 */
		public static function getInstance(): self { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName -- CamelCase name OK.
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Register the hooks for the meeting post type.
		 */
		public static function init(): void {
			$mpt = self::getInstance();
			add_action( 'init', array( $mpt, 'register_meeting_post_type' ) );
			add_action( 'init', array( $mpt, 'register_meta' ) );
			add_filter( 'the_posts', array( $mpt, 'meeting_set_next_meeting' ) );
			add_filter( 'the_posts', array( $mpt, 'meeting_sort_upcoming_meetings' ), 10, 2 );
			add_shortcode( 'meeting_time', array( $mpt, 'meeting_time_shortcode' ) );
			add_filter( 'widget_text', array( $mpt, 'process_widget_shortcode' ) );
		}

		/**
		 * Set the next occurrence on each meeting, as `next_date`.
		 *
		 * @param WP_Post[] $posts Queried posts.
		 * @return WP_Post[]
		 */
		public function meeting_set_next_meeting( array $posts ): array {
			if ( ! $this->is_meeting_list( $posts ) ) {
				return $posts;
			}

			foreach ( $posts as $post ) {
				$next            = $this->get_next_occurrence( $post );
				$post->next_date = $next ? $next : $post->start_date;
			}

			return $posts;
		}

		/**
		 * Sort meetings by their next occurrence on the frontend.
		 *
		 * @param WP_Post[]     $posts Queried posts.
		 * @param WP_Query|null $query The query.
		 * @return WP_Post[]
		 */
		public function meeting_sort_upcoming_meetings( array $posts, ?WP_Query $query = null ): array {
			// The admin and queries that ask for an order keep theirs.
			if ( is_admin() || ( $query && $query->get( 'orderby' ) ) || ! $this->is_meeting_list( $posts ) ) {
				return $posts;
			}

			usort(
				$posts,
				static function ( $a, $b ) {
					$adate = strtotime( $a->next_date . ' ' . $a->time );
					$bdate = strtotime( $b->next_date . ' ' . $b->time );
					if ( $adate === $bdate ) {
						return 0;
					}
					return ( $adate < $bdate ) ? -1 : 1;
				}
			);

			return $posts;
		}

		/**
		 * Whether every post in a list is a meeting.
		 *
		 * @param WP_Post[] $posts Posts.
		 * @return bool
		 */
		private function is_meeting_list( array $posts ): bool {
			foreach ( $posts as $post ) {
				if ( 'meeting' !== $post->post_type ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Returns the date of the next occurrence of the meeting.
		 *
		 * @param object $post           A meeting post object.
		 * @param string $after_datetime Find the next occurrence after this date. Defaults to 30 minutes ago, so an ongoing meeting still counts.
		 *
		 * @return string|false A date string representing the date of the next occurrence; false if there is no next meeting.
		 */
		public function get_next_occurrence( object $post, string $after_datetime = '-30 minutes' ): string|false {
			if ( 'meeting' !== $post->post_type ) {
				return false;
			}

			$next_date = false;

			if ( 'weekly' === $post->recurring || '1' === $post->recurring ) {
				try {
					$start = new DateTime( sprintf( '%s %s GMT', $post->start_date, $post->time ) );
					$next  = $start;
					$now   = new DateTime( $after_datetime );

					if ( $next <= $now ) {
						$interval = $start->diff( $now );
						// add one to days to account for events that happened earlier today.
						$weekdiff = ceil( ( $interval->days + 1 ) / 7 );
						$next->modify( '+ ' . $weekdiff . ' weeks' );
					}

					$next_date = $next->format( 'Y-m-d' );
				} catch ( Exception $e ) {
					$next_date = false;
				}
			} elseif ( 'biweekly' === $post->recurring ) {
				try {
					$start = new DateTime( sprintf( '%s %s GMT', $post->start_date, $post->time ) );
					$next  = $start;
					$now   = new DateTime( $after_datetime );

					while ( $next <= $now ) {
						$next->modify( '+2 weeks' );
					}

					$next_date = $next->format( 'Y-m-d' );
				} catch ( Exception $e ) {
					$next_date = false;
				}
			} elseif ( 'occurrence' === $post->recurring ) {
				try {
					$start = new DateTime( sprintf( '%s %s GMT', $post->start_date, $post->time ) );
					$now   = new DateTime( $after_datetime );

					// Relative date formats only accept English weekday names, so don't use the translated ones.
					$day_name = $start->format( 'l' );
					$numerals = array( 'first', 'second', 'third', 'fourth' );

					// Don't return occurrences before the meeting's first date.
					$earliest = ( clone $start )->modify( '-1 second' );
					if ( $earliest > $now ) {
						$now = $earliest;
					}

					$next       = clone $now;
					$occurrence = $post->ID ? get_post_meta( $post->ID, 'occurrence', true ) : $post->occurrence;
					$occurrence = $occurrence ? $occurrence : array();

					// Sort so numbered weeks (1-4) come first, -1 (last) comes last.
					$sorted = array_intersect( array_map( 'intval', (array) $occurrence ), array( -1, 1, 2, 3, 4 ) );
					sort( $sorted );
					if ( reset( $sorted ) === -1 ) {
						array_shift( $sorted );
						$sorted[] = -1;
					}

					// Without any weeks there's no occurrence; the caller falls back to the start date.
					if ( ! $sorted ) {
						return false;
					}

					$limit = 12;
					do {
						$month_year = $next->format( 'F Y' );
						foreach ( $sorted as $index ) {
							if ( -1 === $index ) {
								$next = new DateTime( sprintf( 'last %s of %s %s GMT', $day_name, $month_year, $post->time ) );
							} else {
								$next = new DateTime( sprintf( '%s %s of %s %s GMT', $numerals[ $index - 1 ], $day_name, $month_year, $post->time ) );
							}
							if ( $next > $now ) {
								break 2;
							}
						}
						// Not '+1 month': from the last weekday (up to the 31st) that skips shorter months.
						$next->modify( 'first day of next month' );
					} while ( --$limit > 0 );
					$next_date = $next->format( 'Y-m-d' );
				} catch ( Exception $e ) {
					$next_date = false;
				}
			} elseif ( 'monthly' === $post->recurring ) {
				try {
					$start = new DateTime( sprintf( '%s %s GMT', $post->start_date, $post->time ) );
					$next  = $start;
					$now   = new DateTime( $after_datetime );

					while ( $next <= $now ) {
						$next->modify( '+1 month' );
					}

					$next_date = $next->format( 'Y-m-d' );
				} catch ( Exception $e ) {
					$next_date = false;
				}
			} else {
				$next_date = $post->start_date;
			}

			return $next_date;
		}

		/**
		 * Register the meeting post type.
		 */
		public function register_meeting_post_type(): void {
			$labels = array(
				'name'               => _x( 'Meetings', 'Post Type General Name', 'wporg-meeting-calendar' ),
				'singular_name'      => _x( 'Meeting', 'Post Type Singular Name', 'wporg-meeting-calendar' ),
				'menu_name'          => __( 'Meetings', 'wporg-meeting-calendar' ),
				'name_admin_bar'     => __( 'Meeting', 'wporg-meeting-calendar' ),
				'parent_item_colon'  => __( 'Parent Meeting:', 'wporg-meeting-calendar' ),
				'all_items'          => __( 'All Meetings', 'wporg-meeting-calendar' ),
				'add_new_item'       => __( 'Add New Meeting', 'wporg-meeting-calendar' ),
				'add_new'            => __( 'Add New', 'wporg-meeting-calendar' ),
				'new_item'           => __( 'New Meeting', 'wporg-meeting-calendar' ),
				'edit_item'          => __( 'Edit Meeting', 'wporg-meeting-calendar' ),
				'update_item'        => __( 'Update Meeting', 'wporg-meeting-calendar' ),
				'view_item'          => __( 'View Meeting', 'wporg-meeting-calendar' ),
				'view_items'         => __( 'View Meetings', 'wporg-meeting-calendar' ),
				'search_items'       => __( 'Search Meeting', 'wporg-meeting-calendar' ),
				'not_found'          => __( 'Not found', 'wporg-meeting-calendar' ),
				'not_found_in_trash' => __( 'Not found in Trash', 'wporg-meeting-calendar' ),
			);
			$args   = array(
				'label'               => __( 'meeting', 'wporg-meeting-calendar' ),
				'description'         => __( 'Meeting', 'wporg-meeting-calendar' ),
				'labels'              => $labels,
				'supports'            => array( 'title', 'custom-fields' ),
				'hierarchical'        => false,
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_position'       => 20,
				'menu_icon'           => 'dashicons-calendar',
				'show_in_admin_bar'   => true,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => true,
				'can_export'          => false,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => true,
				'capability_type'     => 'post',
				'rewrite'             => false,
			);
			register_post_type( 'meeting', $args );
		}

		/**
		 * Register the meeting meta fields.
		 */
		public function register_meta(): void {
			$text_keys = array( 'team', 'start_date', 'end_date', 'time', 'location' );
			foreach ( $text_keys as $key ) {
				register_meta(
					'post',
					$key,
					array(
						'object_subtype'    => 'meeting',
						'type'              => 'string',
						'single'            => true,
						'show_in_rest'      => true,
						'sanitize_callback' => 'sanitize_text_field',
					)
				);
			}

			$url_keys = array( 'link', 'wptv_url' );
			foreach ( $url_keys as $key ) {
				register_meta(
					'post',
					$key,
					array(
						'object_subtype'    => 'meeting',
						'type'              => 'string',
						'single'            => true,
						'show_in_rest'      => true,
						'sanitize_callback' => 'esc_url_raw',
					)
				);
			}

			register_meta(
				'post',
				'recurring',
				array(
					'object_subtype' => 'meeting',
					'type'           => 'string',
					'single'         => true,
					'show_in_rest'   => array(
						'schema' => array(
							'type' => 'string',
							'enum' => array( '', 'weekly', 'biweekly', 'occurrence', 'monthly' ),
						),
					),
				)
			);

			register_meta(
				'post',
				'occurrence',
				array(
					'object_subtype' => 'meeting',
					'type'           => 'array',
					'single'         => true,
					'show_in_rest'   => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'integer',
								'enum' => array( -1, 1, 2, 3, 4 ),
							),
						),
					),
				)
			);
		}

		/**
		 * Whether a meeting's occurrence is cancelled.
		 *
		 * @param int    $meeting_id Meeting ID.
		 * @param string $date       Occurrence date, Y-m-d.
		 * @return bool
		 */
		public function is_meeting_cancelled( int $meeting_id, string $date ): bool {
			// Note: this assumes the meeting does occur on $date.
			$cancellations = get_post_meta( $meeting_id, 'meeting_cancelled', false );
			return in_array( $date, $cancellations, true );
		}

		/**
		 * Checks that a route parameter is a real calendar date in Y-m-d format.
		 *
		 * @param mixed $value The parameter value.
		 * @return bool
		 */
		public function is_valid_date( mixed $value ): bool {
			if ( ! is_string( $value ) ) {
				return false;
			}

			$date = DateTime::createFromFormat( '!Y-m-d', $value );

			return $date && $date->format( 'Y-m-d' ) === $value;
		}

		/**
		 * Whether a meeting occurs on a date.
		 *
		 * @param int    $meeting_id Meeting ID.
		 * @param string $date       Date, Y-m-d.
		 * @return bool
		 */
		public function is_valid_occurrence( int $meeting_id, string $date ): bool {
			$meeting = get_post( $meeting_id );
			if ( ! $meeting || 'meeting' !== $meeting->post_type ) {
				return false;
			}

			if ( empty( $meeting->recurring ) ) {
				return $date === $meeting->start_date;
			}

			// The meeting occurs on the date if that's its next occurrence from just before it.
			$before = strtotime( $date . ' ' . $meeting->time . ' -1 minute' );
			if ( false === $before ) {
				return false;
			}

			return $this->get_next_occurrence( $meeting, gmdate( 'Y-m-d H:i:s P', $before ) ) === $date;
		}

		/**
		 * Get a meeting's upcoming occurrences.
		 *
		 * Also the `future_occurrences` REST field callback.
		 *
		 * @param WP_Post|array              $meeting Meeting, or its prepared REST data.
		 * @param string|null                $attr    REST field name.
		 * @param WP_REST_Request|array|null $request Request, whose `month` sets the start of the period.
		 * @return string[] Occurrence dates, Y-m-d.
		 */
		public function get_future_occurrences( WP_Post|array $meeting, ?string $attr = null, WP_REST_Request|array|null $request = null ): array {
			if ( is_array( $meeting ) && ! empty( $meeting['id'] ) ) {
				// The register_rest_field callback passes a prepared array but we need the post object.
				$meeting = get_post( $meeting['id'] );
			}

			// A `month` param, as in /meetings?month=2020-09-01, starts the period there.
			$now = time();
			if ( isset( $request['month'] ) && $this->is_valid_date( $request['month'] ) ) {
				$now = strtotime( $request['month'] );
			}

			$from = DateTime::createFromFormat( 'U', (string) strtotime( '-30 minutes', $now ) );
			$end  = DateTime::createFromFormat( 'U', (string) strtotime( '+2 month', $now ) );
			if ( $meeting->end_date ) {
				$end = DateTime::createFromFormat( '!Y-m-d', $meeting->end_date );
				// An unparseable end date ends the meeting, as it does in meeting_meta_query().
				if ( ! $end ) {
					return array();
				}
				// The meeting still occurs on its end date.
				$end->setTime( 23, 59, 59 );
			}
			$max         = 12;
			$occurrences = array();
			do {
				$next = $this->get_next_occurrence( $meeting, $from->format( 'Y-m-d H:i:s P' ) );
				if ( $next ) {
					$from = new DateTime( "{$next} {$meeting->time} GMT" );
					if ( $from <= $end ) {
						$occurrences[] = $next;
					}
				}
			} while ( --$max > 0 && $next && $from && $from < $end && $meeting->recurring );

			return $occurrences;
		}

		/**
		 * Process only the [meeting_time] shortcode in widget text.
		 *
		 * @param string $text Widget text.
		 * @return string
		 */
		public function process_widget_shortcode( string $text ): string {
			if ( has_shortcode( $text, 'meeting_time' ) ) {
				$pattern = get_shortcode_regex( array( 'meeting_time' ) );
				$text    = preg_replace_callback( "/$pattern/", 'do_shortcode_tag', $text );
			}
			return $text;
		}

		/**
		 * Renders meeting information with the next meeting time based on user's local timezone. Used in Make homepage.
		 *
		 * @param array|string $attr Shortcode attributes.
		 * @return string
		 */
		public function meeting_time_shortcode( array|string $attr ): string {
			$attr = shortcode_atts(
				array(
					'team'     => null,
					'limit'    => 1,
					'before'   => __( 'Next meeting: ', 'wporg-meeting-calendar' ),
					'titletag' => 'strong',
					'more'     => true,
				),
				$attr
			);

			if ( empty( $attr['team'] ) ) {
				return '';
			}

			if ( 'Documentation' === $attr['team'] ) {
				$attr['team'] = 'Docs';
			}

			wp_enqueue_script(
				'wporg-meeting-time-conversion',
				plugins_url( 'time-conversion.js', __FILE__ ),
				array(),
				(string) filemtime( __DIR__ . '/time-conversion.js' ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);

			// If we're on a network, assume the calendar exists on the main site.
			if ( function_exists( 'switch_to_blog' ) ) {
				switch_to_blog( get_main_site_id() );
			}

			$query = new WP_Query(
				array(
					'post_type'  => 'meeting',
					'nopaging'   => true,
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Meeting data is stored in post meta.
						'relation' => 'AND',
						array(
							'key'     => 'team',
							'value'   => $attr['team'],
							'compare' => 'EQUALS',
						),
						$this->meeting_meta_query(),
					),
				)
			);

			$limit = (int) $attr['limit'];
			$limit = $limit > 0 ? $limit : count( $query->posts );

			$out = '';
			foreach ( array_slice( $query->posts, 0, $limit ) as $post ) {
				// Skip meetings without a usable date or time; strtotime() reads a bare time as today.
				$time = strtotime( $post->time );
				if ( empty( $post->next_date ) || false === $time ) {
					continue;
				}
				$utc_time               = gmdate( 'H:i:s', $time );
				$next_meeting_iso       = $post->next_date . 'T' . $utc_time . '+00:00';
				$next_meeting_timestamp = strtotime( $post->next_date . ' ' . $utc_time );
				if ( false === $next_meeting_timestamp ) {
					continue;
				}
				$date_time = new DateTime( '@' . $next_meeting_timestamp );
				$date_time->setTimezone( new DateTimeZone( 'UTC' ) );
				$next_meeting_display = $date_time->format( 'D M d H:i:s Y T' );

				$slack_channel = null;
				if ( $post->location && preg_match( '/^#([-\w]+)$/', trim( $post->location ), $match ) ) {
					$slack_channel = sanitize_title( $match[1] );
				}

				$cancelled = $this->is_meeting_cancelled( $post->ID, $post->next_date );

				$out .= '<p class="wporg-meeting-shortcode' . ( $cancelled ? ' meeting-cancelled' : '' ) . '">';
				$out .= '<span class="wporg-meeting-detail">';

				$out         .= esc_html( $attr['before'] );
				$out         .= '<strong class="meeting-title">' . esc_html( $post->post_title ) . '</strong>';
				$display_more = $query->found_posts - intval( $limit );
				if ( $display_more > 0 ) {
					/* translators: %s: Number of additional meetings. */
					$out .= ' <a title="Click to view all meetings for this team" href="' . esc_url( home_url( '/meetings/#' . strtolower( $attr['team'] ) ) ) . '">' . sprintf( esc_html__( '(+%s more)', 'wporg-meeting-calendar' ), $display_more ) . '</a>';
				}
				$out .= '<br/>';
				$out .= '<time class="date" datetime="' . esc_attr( $next_meeting_iso ) . '" title="' . esc_attr( $next_meeting_iso ) . '">' . esc_html( $next_meeting_display ) . '</time> ';
				/* translators: %s: Time until the meeting, e.g. "2 hours". */
				$out .= sprintf( esc_html__( '(%s from now)', 'wporg-meeting-calendar' ), human_time_diff( $next_meeting_timestamp, time() ) );
				if ( $post->location && $slack_channel ) {
					/* translators: 1: Slack channel URL. 2: Slack channel name. */
					$out .= ' ' . sprintf( wp_kses( __( 'accessible via <a href="%1$s">%2$s</a> on Slack', 'wporg-meeting-calendar' ), array( 'a' => array( 'href' => array() ) ) ), 'https://wordpress.slack.com/messages/' . $slack_channel, esc_html( $post->location ) );
				}
				$out .= '</span>';

				if ( $cancelled ) {
					$out               .= '<br>';
					$future_occurrences = $this->get_future_occurrences( $post, null, array() );
					$next_meeting       = null;
					foreach ( $future_occurrences as $occurrence ) {
						if ( ! $this->is_meeting_cancelled( $post->ID, $occurrence )
							&& $occurrence > $post->next_date ) {
							$next_meeting = $occurrence;
							break;
						}
					}
					if ( $next_meeting ) {
						/* translators: %s: Date of the next meeting. */
						$out .= '<i>' . sprintf( esc_html__( 'This event is cancelled. The next meeting is scheduled for %s.', 'wporg-meeting-calendar' ), esc_html( $next_meeting ) ) . '</i>';
					} else {
						$out .= '<i>' . esc_html__( 'This event is cancelled.', 'wporg-meeting-calendar' ) . '</i>';
					}
				}
				$out .= '</p>';
			}

			if ( function_exists( 'restore_current_blog' ) ) {
				restore_current_blog();
			}

			return $out;
		}

		/**
		 * Get the meta query for meetings that haven't ended.
		 *
		 * @return array
		 */
		public function meeting_meta_query(): array {
			return array(
				'relation' => 'OR',
				// not recurring  AND start_date >= CURDATE() = one-time meeting today or still in future.
				array(
					'relation' => 'AND',
					array(
						'key'     => 'recurring',
						'value'   => array( 'weekly', 'biweekly', 'occurrence', 'monthly', '1' ),
						'compare' => 'NOT IN',
					),
					array(
						'key'     => 'start_date',
						'type'    => 'DATE',
						'compare' => '>=',
						'value'   => gmdate( 'Y-m-d' ),
					),
				),
				// recurring = 1 AND ( end_date = '' OR end_date >= CURDATE() ) = recurring meeting that has no end or has not ended yet.
				array(
					'relation' => 'AND',
					array(
						'key'     => 'recurring',
						'value'   => array( 'weekly', 'biweekly', 'occurrence', 'monthly', '1' ),
						'compare' => 'IN',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => 'end_date',
							'value'   => '',
							'compare' => '=',
						),
						array(
							'key'     => 'end_date',
							'type'    => 'DATE',
							'compare' => '>=',
							'value'   => gmdate( 'Y-m-d' ),
						),
					),
				),
			);
		}
	}
endif;

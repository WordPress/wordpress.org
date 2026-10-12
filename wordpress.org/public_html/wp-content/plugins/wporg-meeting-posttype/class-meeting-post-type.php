<?php
/**
 * Create the meeting post type and assorted filters for https://make.wordpress.org/meetings
 *
 * @package WordPressdotorg\Meeting_Calendar
 */

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
		public static function getInstance() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName -- CamelCase name OK.
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Register the hooks for the meeting post type.
		 */
		public static function init() {
			$mpt = self::getInstance();
			add_action( 'init', array( $mpt, 'register_meeting_post_type' ) );
			add_action( 'init', array( $mpt, 'register_meta' ) );
			add_filter( 'the_posts', array( $mpt, 'meeting_set_next_meeting' ) );
			add_filter( 'the_posts', array( $mpt, 'meeting_sort_upcoming_meetings' ) );
			add_shortcode( 'meeting_time', array( $mpt, 'meeting_time_shortcode' ) );

			// Process only the [meeting_time] shortcode in text widgets.
			add_filter( 'widget_text', array( $mpt, 'process_widget_shortcode' ) );
		}

		/**
		 * Set the next occurrence on each meeting, as `next_date`.
		 *
		 * @param WP_Post[] $posts Queried posts.
		 * @return WP_Post[]
		 */
		public function meeting_set_next_meeting( $posts ) {
			$is_meeting_list = array_reduce(
				$posts,
				static function ( $is_meeting, $post ) {
					return $is_meeting && 'meeting' === $post->post_type;
				},
				true
			);
			if ( ! $is_meeting_list ) {
				return $posts;
			}

			// for each entry, set a fake meta value to show the next date for recurring meetings.
			array_walk(
				$posts,
				function ( &$post ) {
					if ( 'meeting' !== $post->post_type ) {
						return false;
					}
					$next = $this->get_next_occurrence( $post );
					if ( $next ) {
						$post->next_date = $next;
					} else {
						// if the datetime is invalid, then set the post->next_date to the start date instead.
						$post->next_date = $post->start_date;
					}
				}
			);

			return $posts;
		}

		/**
		 * Sort meetings by their next occurrence on the frontend.
		 *
		 * @param WP_Post[] $posts Queried posts.
		 * @return WP_Post[]
		 */
		public function meeting_sort_upcoming_meetings( $posts ) {
			// Avoid reordering posts in the admin.
			if ( is_admin() || ! is_array( $posts ) ) {
				return $posts;
			}

			$is_meeting_list = array_reduce(
				$posts,
				static function ( $is_meeting, $post ) {
					return $is_meeting && 'meeting' === $post->post_type;
				},
				true
			);
			if ( ! $is_meeting_list ) {
				return $posts;
			}

			// reorder the posts by next_date + time.
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
		 * Returns the date of the next occurrence of the meeting.
		 *
		 * @param object $post A meeting post object.
		 * @param string $after_datetime Find the next occurrence after this date.
		 *
		 * @return string|bool A date string representing the date of the next occurrence; false if there is no next meeting.
		 */
		public function get_next_occurrence( $post, $after_datetime = '-30 minutes' ) {
			if ( ! is_object( $post ) || 'meeting' !== $post->post_type ) {
				return false;
			}

			$next_date = false;

			if ( 'weekly' === $post->recurring || '1' === $post->recurring ) {
				try {
					// from the start date, advance the week until it's past now.
					$start = new DateTime( sprintf( '%s %s GMT', $post->start_date, $post->time ) );
					$next  = $start;
					// minus 30 minutes to account for currently ongoing meetings.
					$now = new DateTime( $after_datetime );

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
					// advance the start date 2 weeks at a time until it's past now.
					$start = new DateTime( sprintf( '%s %s GMT', $post->start_date, $post->time ) );
					$next  = $start;
					// minus 30 minutes to account for currently ongoing meetings.
					$now = new DateTime( $after_datetime );

					while ( $next <= $now ) {
						$next->modify( '+2 weeks' );
					}

					$next_date = $next->format( 'Y-m-d' );
				} catch ( Exception $e ) {
					$next_date = false;
				}
			} elseif ( 'occurrence' === $post->recurring ) {
				try {
					// advance the occurrence day in the current month until it's past now.
					$start = new DateTime( sprintf( '%s %s GMT', $post->start_date, $post->time ) );
					$next  = $start;
					// minus 30 minutes to account for currently ongoing meetings.
					$now = new DateTime( $after_datetime );

					// Relative date formats only accept English weekday names, so don't use the translated ones.
					$day_name = gmdate( 'l', strtotime( sprintf( '%s %s GMT', $post->start_date, $post->time ) ) );
					$numerals = array( 'first', 'second', 'third', 'fourth' );

					$next       = clone $now;
					$occurrence = $post->ID ? get_post_meta( $post->ID, 'occurrence', true ) : $post->occurrence;
					$occurrence = $occurrence ? $occurrence : array();

					// Sort so numbered weeks (1-4) come first, -1 (last) comes last.
					$sorted = $occurrence;
					sort( $sorted );
					// Move -1 from the front to the end.
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
					// advance the start date 1 month at a time until it's past now.
					$start = new DateTime( sprintf( '%s %s GMT', $post->start_date, $post->time ) );
					$next  = $start;
					// minus 30 minutes to account for currently ongoing meetings.
					$now = new DateTime( $after_datetime );

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
		public function register_meeting_post_type() {
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
		public function register_meta() {
			// Text fields.
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

			// URL fields.
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

			// Recurring field.
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

			// 'occurrence' is an array of integers (1-4, representing week numbers in a month)
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
		public function is_meeting_cancelled( $meeting_id, $date ) {
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
		public function is_valid_date( $value ) {
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
		public function is_valid_occurrence( $meeting_id, $date ) {
			$meeting = get_post( $meeting_id );
			if ( ! $meeting || 'meeting' !== $meeting->post_type ) {
				return false;
			}

			// Non-recurring meetings only occur on their start date.
			if ( empty( $meeting->recurring ) ) {
				return $date === $meeting->start_date;
			}

			// Check if the date is a valid occurrence by finding the next occurrence
			// from just before the target date and seeing if it matches.
			$before = gmdate( 'Y-m-d H:i:s P', strtotime( $date . ' ' . $meeting->time . ' -1 minute' ) );
			$next   = $this->get_next_occurrence( $meeting, $before );

			return $next === $date;
		}

		/**
		 * Get a meeting's upcoming occurrences.
		 *
		 * Also the `future_occurrences` REST field callback.
		 *
		 * @param WP_Post|array        $meeting     Meeting, or its prepared REST data.
		 * @param string|null          $attr        REST field name.
		 * @param WP_REST_Request|null $request     Request, whose `month` sets the start of the period.
		 * @return string[] Occurrence dates, Y-m-d.
		 */
		public function get_future_occurrences( $meeting, $attr = null, $request = null ) {
			if ( is_array( $meeting ) && ! empty( $meeting['id'] ) ) {
				// The register_rest_field callback passes a prepared array but we need the post object.
				$meeting = get_post( $meeting['id'] );
			}

			// The month the occurrences should be within.
			// Passed to the API endpoint as /meetings?month=2020-09-01.
			$now = time();
			if ( isset( $request['month'] ) && $this->is_valid_date( $request['month'] ) ) {
				$now = strtotime( $request['month'] );
			}

			$from = DateTime::createFromFormat( 'U', strtotime( '-30 minutes', $now ) );
			$end  = DateTime::createFromFormat( 'U', strtotime( '+2 month', $now ) );
			if ( $meeting->end_date ) {
				$end = DateTime::createFromFormat( 'Y-m-d', $meeting->end_date );
			}
			$max         = 12;
			$occurrences = array();
			do {
				$next = $this->get_next_occurrence( $meeting, $from->format( 'Y-m-d H:i:s P' ) );
				if ( $next ) {
					$from = new DateTime( "{$next} {$meeting->time}" );
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
		public function process_widget_shortcode( $text ) {
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
		public function meeting_time_shortcode( $attr ) {
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

			$limit = $attr['limit'] > 0 ? $attr['limit'] : count( $query->posts );

			$out = '';
			foreach ( array_slice( $query->posts, 0, $limit ) as $post ) {
				$next_meeting_datestring = $post->next_date;
				$utc_time                = gmdate( 'H:i:s', strtotime( $post->time ) );
				$next_meeting_iso        = $next_meeting_datestring . 'T' . $utc_time . '+00:00';
				$next_meeting_timestamp  = strtotime( $next_meeting_datestring . ' ' . $utc_time );
				// Skip meetings without a usable date; strtotime() reads a bare time as today.
				if ( empty( $next_meeting_datestring ) || false === $next_meeting_timestamp ) {
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
					$out .= ' <a title="Click to view all meetings for this team" href="/meetings/#' . esc_attr( strtolower( $attr['team'] ) ) . '">' . sprintf( __( '(+%s more)', 'wporg-meeting-calendar' ), $display_more ) . '</a>';
				}
				$out .= '<br/>';
				$out .= '<time class="date" datetime="' . esc_attr( $next_meeting_iso ) . '" title="' . esc_attr( $next_meeting_iso ) . '">' . $next_meeting_display . '</time> ';
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
						$out .= '<i>' . sprintf( esc_html__( 'This event is cancelled. The next meeting is scheduled for %s.', 'wporg-meeting-calendar' ), $next_meeting ) . '</i>';
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
		public function meeting_meta_query() {
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
				// recurring = 1 AND ( end_date = '' OR end_date > CURDATE() ) = recurring meeting that has no end or has not ended yet.
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
							'compare' => '>',
							'value'   => gmdate( 'Y-m-d' ),
						),
					),
				),
			);
		}
	}
endif;

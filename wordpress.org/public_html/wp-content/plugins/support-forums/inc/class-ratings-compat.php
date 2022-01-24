<?php

namespace WordPressdotorg\Forums;

class Ratings_Compat {

	var $compat   = null;
	var $slug     = null;
	var $object   = null;
	var $taxonomy = null;

	var $filter = false;

	var $old_title = null;

	public function __construct( $args ) {
		if ( ! class_exists( 'WPORG_Ratings' ) ) {
			return;
		}

		$args = wp_parse_args( $args, array(
			'compat'       => '',
			'slug'         => '',
			'taxonomy'     => '',
			'object'       => '',
		) );

		if ( ! $args['compat'] || ! $args['slug'] || ! $args['taxonomy'] || ! $args['object'] ) {
			return;
		}

		$this->compat   = $args['compat'];
		$this->slug     = $args['slug'];
		$this->taxonomy = $args['taxonomy'];
		$this->object   = $args['object'];

		$this->ratings_counts = \WPORG_Ratings::get_rating_counts( $this->compat, $this->slug );
		$this->avg_rating = \WPORG_Ratings::get_avg_rating( $this->compat, $this->slug );

		// Set up a filter on star rating number.
		if ( isset( $_GET['filter'] ) ) {
			$filter = absint( $_GET['filter'] );
			if ( $filter > 5 || ! $filter ) {
				$filter = 5;
			}
			$this->filter = $filter;
			add_filter( 'posts_clauses', array( $this, 'add_filter_to_posts_clauses' ) );
			add_action( 'pre_get_posts', array( $this, 'no_found_rows' ) );
			add_filter( 'bbp_topic_pagination', array( $this, 'add_filter_topic_pagination' ) );

			// <meta robots="noindex,follow"> for filtered views.
			add_filter( 'wp_head', 'wp_no_robots' );
		}

		// Total reviews count. Can be altered using $this->filter if needed.
		$this->reviews_count = \WPORG_Ratings::get_rating_count( $this->compat, $this->slug, 0 );

		// Set up the single topic.
		add_action( 'bbp_template_before_lead_topic', array( $this, 'add_topic_stars' ) );

		// Add a notice with an edit link for an individual review topic.
		add_action( 'bbp_theme_before_topic_content', array( $this, 'add_edit_review_notice' ) );

		// Set up ratings view.
		add_action( 'wporg_compat_before_single_view', array( $this, 'do_view_header' ) );

		// Add the topic form as either a new or edit form.
		add_action( 'wporg_compat_new_review_notice', array( $this, 'do_template_notice' ) );
		add_action( 'wporg_compat_after_single_view', array( $this, 'add_topic_form' ) );
		add_action( 'bbp_theme_before_topic_form_content', array( $this, 'add_topic_form_stars' ), 8 );

		// Check to see if a topic is being created/edited.
		add_action( 'bbp_new_topic_post_extras', array( $this, 'topic_post_extras' ) );
		add_action( 'bbp_edit_topic_post_extras', array( $this, 'topic_post_extras' ) );

		// Checks for review topics.
		add_action( 'bbp_new_topic_pre_extras', array( $this, 'topic_pre_extras' ) );

		// Add the rating to the Feed body/title
		if ( did_action( 'bbp_feed' ) ) {
			add_filter( 'bbp_get_topic_content', array( $this, 'feed_prepend_rating' ), 10, 2 );
			add_filter( 'bbp_get_topic_title', array( $this, 'feed_append_rating' ), 10, 2 );
		}
	}

	/**
	 * Whenever possible, unset SQL_CALC_FOUND_ROWS using this filter.
	 */
	public function no_found_rows( $q ) {
		$q->set( 'no_found_rows', true );
	}

	/**
	 * Allow ratings view to be filtered by star rating.
	 */
	public function add_filter_to_posts_clauses( $clauses ) {
		global $wpdb;

		$clauses['join']  .= " INNER JOIN ratings ON ( $wpdb->posts.ID = ratings.post_id )";
		$clauses['where'] .= $wpdb->prepare( " AND ratings.rating = %d", $this->filter );

		return $clauses;
	}

	public function add_filter_topic_pagination( $r ) {
		$count = $this->ratings_counts[ $this->filter ];
		$r['total'] = ceil( (int) $count / (int) bbp_get_topics_per_page() );
		return $r;
	}

	/**
	 * Display star ratings for an individual review topic.
	 */
	public function add_topic_stars() {
		if ( bbp_is_single_topic() && Plugin::REVIEWS_FORUM_ID == bbp_get_topic_forum_id() ) {
			$user_id = bbp_get_topic_author_id();
			$rating = \WPORG_Ratings::get_user_rating( $this->compat, $this->slug, $user_id );
			if ( $rating > 0 ) {
				echo \WPORG_Ratings::get_dashicons_stars( $rating );
			}
		}
	}

	/**
	 * Add a notice with an edit link for an individual review topic.
	 */
	public function add_edit_review_notice() {
		if ( bbp_is_single_topic() && Plugin::REVIEWS_FORUM_ID == bbp_get_topic_forum_id() ) {
			if ( bbp_get_topic_author_id() != get_current_user_id() ) {
				return;
			}

			$notice = $object_link = $edit_link = '';
			switch( $this->compat ) {
				case 'plugin' :
					/* translators: 1: link to the plugin, 2: review edit URL */
					$notice = _x( 'This is your review of %1$s, you can <a href="%2$s">edit your review</a> at any time.', 'plugin', 'wporg-forums' );
					$object_link = sprintf( '<a href="//wordpress.org/plugins/%s/">%s</a>', esc_attr( $this->slug ), esc_html( $this->object->post_title ) );
					$edit_url = sprintf( home_url( '/plugin/%s/reviews/#new-post' ), esc_attr( $this->slug ) );
					break;
				case 'theme' :
					/* translators: 1: link to the theme, 2: review edit URL */
					$notice = _x( 'This is your review of %1$s, you can <a href="%2$s">edit your review</a> at any time.', 'theme', 'wporg-forums' );
					$object_link = sprintf( '<a href="//wordpress.org/themes/%s/">%s</a>', esc_attr( $this->slug ), esc_html( $this->object->post_title ) );
					$edit_url = sprintf( home_url( '/theme/%s/reviews/#new-post' ), esc_attr( $this->slug ) );
					break;
			}

			printf(
				'<div class="bbp-template-notice info"><p>%s</p></div>',
				sprintf( $notice, $object_link, $edit_url )
			);
		}
	}

	/**
	 * Display star ratings next to topic titles in the review forum view.
	 *
	 * @param string $title The topic title
	 * @param int $topic_id The topic id
	 */
	public function get_topic_title( $title, $topic_id ) {

		// save the title
		$this->old_title = $title;

		if ( bbp_is_single_view() && 'reviews' == bbp_get_view_id() ) {
			$user_id = bbp_get_topic_author_id( $topic_id );
			$rating = \WPORG_Ratings::get_user_rating( $this->compat, $this->slug, $user_id );
			if ( $rating > 0 ) {
				$title .= ' ' . \WPORG_Ratings::get_dashicons_stars( $rating );
			}
		}
		return $title;
	}

	/**
	 * Undo the above topic title change for specific cases
	 *
	 * @param string $title The topic title
	 */
	public function undo_topic_title( $title ) {
		if ( !empty( $this->old_title ) ) {
			return $this->old_title;
		}
		return $title;
	}

	public function do_view_header() {
		if ( ! bbp_is_single_view() || 'reviews' != bbp_get_view_id() ) {
			return;
		}

		// Add the filter for topic titles here.
		add_filter( 'bbp_get_topic_title', array( $this, 'get_topic_title' ), 10, 2 );

		// Undo the above filter, for titles of replies to reviews. See #meta4254
		add_filter( 'bbp_get_topic_last_topic_title', array( $this, 'undo_topic_title' ), 10, 1 );
?>
<div class="review-ratings">
	<div class="col-3">
		<div class="reviews-about" style="display:none;"><?php echo esc_html( $this->object->post_title ); ?></div>
		<div class="reviews-total-count"><?php
			printf(
				/* translators: %s: number of reviews */
				_n( '%s review', '%s reviews', $this->reviews_count, 'wporg-forums' ),
				'<span>' . number_format_i18n( $this->reviews_count ) . '</span>'
			);
		?></div>
		<?php
			foreach ( array( 5, 4, 3, 2, 1 ) as $rating ) {
				$ratings_count = isset( $this->ratings_counts[ $rating ] ) ? $this->ratings_counts[ $rating ] : 0;
				$ratings_count_total = isset( $this->ratings_counts ) ? array_sum( $this->ratings_counts) : 0;
				$stars_title = sprintf(
					/* translators: %s: number of stars */
					_n( 'Click to see reviews that provided a rating of %d star',
					    'Click to see reviews that provided a rating of %d stars',
					    $rating,
					    'wporg-forums' ),
					$rating
				);
				/* translators: %d: number of stars */
				$stars_text = sprintf(
					/* translators: %d: number of stars */
					_n( '%d star', '%d stars', $rating, 'wporg-forums' ),
					$rating
				);
				$width = 0;
				if ( $ratings_count && $ratings_count_total ) {
					$width = 100 * ( $ratings_count / $ratings_count_total );
				}
				?>
				<div class="counter-container">
				<a href="<?php echo esc_url( sprintf( home_url( '/%s/%s/reviews/?filter=%s' ), $this->compat, $this->slug, $rating ) ); ?>"
					title="<?php echo esc_attr( $stars_title ); ?>">
					<span class="counter-label" style="float:left;margin-right:5px;min-width:58px;"><?php echo esc_html( $stars_text ); ?></span>
					<span class="counter-back" style="height:17px;width:100px;background-color:#ececec;float:left;">
						<span class="counter-bar" style="width:<?php echo esc_attr( $width ); ?>px;height:17px;background-color:#ffc733;float:left;"></span>
					</span>
				</a>
				<span class="counter-count" style="margin-left:5px;"><?php echo esc_html( $ratings_count ); ?></span>
				</div>
				<?php
			}
		?>
	</div>
	<div class="col-5">
		<div style="font-weight:bold;"><?php _e( 'Average Rating', 'wporg-forums' ); ?></div>
		<?php
			echo \WPORG_Ratings::get_dashicons_stars( $this->avg_rating );
			printf(
				/* translators: 1: number of stars in rating, 2: total number of stars (5) */
				__( '%1$s out of %2$s stars', 'wporg-forums' ),
				round( isset( $this->avg_rating ) ? $this->avg_rating : 0, 1 ),
				'<span>5</span>'
			);
		?>
		<div class="reviews-submit-link">
		<?php
			if ( is_user_logged_in() ) {
				echo '<a href="#new-post" class="btn">';
				if ( $this->review_exists() ) {
					_e( 'Edit your review', 'wporg-forums' );
				} else {
					_e( 'Add your own review', 'wporg-forums' );
				}
				echo '</a>';
			} else {
				echo '<span class="reviews-need-login">';
				printf(
					/* translators: %s: login URL */
					__( 'You must be <a href="%s" rel="nofollow">logged in</a> to submit a review.', 'wporg-forums' ),
					add_query_arg(
						'redirect_to',
						urlencode( esc_url_raw( sprintf( home_url( '/%s/%s/reviews/' ), $this->compat, $this->slug ) ) ),
						'https://login.wordpress.org/'
					)
				);
				echo '</span>';
			}
		?>
		</div>
	</div>
</div>
		<?php
		// If current listing is filtered by rating, display message indicating this,
		// along with an explicit link to return them to the unfiltered view.
		$filter = isset( $_GET['filter'] ) ? absint( $_GET['filter'] ) : 0;
		if ( $filter > 0 && $filter < 6 ) {
			echo '<p class="reviews-filtered-msg" style="margin-top:12px;font-size:0.8rem;">';
			printf(
				/* translators: %d: number of stars */
				_n( 'You are currently viewing the reviews that provided a rating of <strong>%d star</strong>.',
				    'You are currently viewing the reviews that provided a rating of <strong>%d stars</strong>.',
			        $filter,
				        'wporg-forums' ) . ' ',
				$filter
			);
			printf(
				/* translators: %s: plugin/theme reviews URL */
				__( '<a href="%s">See all reviews</a>.', 'wporg-forums' ),
				esc_url( sprintf( home_url( '/%s/%s/reviews/' ), $this->compat, $this->slug ) )
			);
			echo "</p>\n";
		}
	}

	/**
	 * Set the rating on topic creation or edit.
	 *
	 * @param int $topic_id The topic id
	 */
	public function topic_post_extras( $topic_id ) {

		// if this is in the reviews forum, and the user is editing their own post
		if ( Plugin::REVIEWS_FORUM_ID == bbp_get_topic_forum_id( $topic_id )
			&& bbp_get_topic_author_id( $topic_id ) == get_current_user_id() ) {

			// if the rating is set, get it
			if ( isset( $_POST['rating'] ) ) {
				$rating = absint( $_POST[ 'rating' ] );
			} else {
				$rating = 5;
			}

			// if the rating isn't 1-5, then set it to a default value (prevent zero star ratings and the like)
			if ( ! in_array( $rating, array( 1, 2, 3, 4, 5 ) ) ) {
					$rating = 5; // default is 5
			}

			// set the rating
			\WPORG_Ratings::set_rating(
				$topic_id,
				$this->compat,
				$this->slug,
				bbp_get_topic_author_id( $topic_id ),
				$rating
			);
		}
	}

	/**
	 * Replace warnings about 'topics' with warnings about 'reviews' when submitting a new review.
	 * Add warnings about reviews containing links.
	 */
	public function topic_pre_extras( $forum_id ) {
		if ( Plugin::REVIEWS_FORUM_ID != $forum_id ) {
			return;
		}

		if (
			! empty( $_POST['bbp_topic_content'] ) &&
			(
				false !== stripos( $_POST['bbp_topic_content'], 'https://' ) ||
				false !== stripos( $_POST['bbp_topic_content'], 'http://' )
			)
		) {
			// Send this review to pending after it gets published. 
			setcookie( 'wporg_review_to_pending', 'links_blocked', time() + HOUR_IN_SECONDS, '/support/', 'wordpress.org', true, true );

			bbp_add_error(
				'no_links_please',
				sprintf(
					/* translators: %s: Link to forum user guide explaining this. */
					__( '<strong>Error</strong>: Please <a href="%s">do not add links to your review</a>, keep the review about your experience in text only.', 'wporg-forums' ),
					'https://wordpress.org/support/forum-user-guide/faq/#why-are-links-not-allowed-in-reviews'
				)
			);
		} elseif ( ! empty( $_COOKIE['wporg_review_to_pending'] ) ) {
			add_filter( 'bbp_new_topic_pre_insert', function( $data ) {
				// If this is still for a review..
				if ( Plugin::REVIEWS_FORUM_ID == $data['post_parent'] ) {
					// Clear the cookie.
					setcookie( 'wporg_review_to_pending', '', time() - HOUR_IN_SECONDS, '/support/', 'wordpress.org', true, true );

					$data['post_status'] = bbp_get_pending_status_id();

					// Add a meta field to find these moderated reviews later.
					$data['meta_input']['_wporg_moderation_reason'] = $_COOKIE['wporg_review_to_pending'];
				}

				return $data;
			} );
		}

		if ( ! bbp_has_errors() ) {
			return;
		}

		// Replace the warnings about topics being too short with reviews.

		// bbPress doesn't have a wrapper to check for specific errors, so we'll reach into bbPress.
		if ( bbpress()->errors->get_error_message( 'bbp_topic_title' ) && empty( $_POST['bbp_topic_title'] ) ) {
			bbpress()->errors->remove( 'bbp_topic_title' );
			bbp_add_error( 'bbp_topic_title', __( '<strong>Error</strong>: Your review needs a title.', 'wporg-forums' ) );
		}

		if ( bbpress()->errors->get_error_message( 'bbp_topic_content' ) ) {
			bbpress()->errors->remove( 'bbp_topic_content' );
			bbp_add_error( 'bbp_topic_content', __( '<strong>Error</strong>: Your review cannot be empty.', 'wporg-forums' ) );
		}
	}

	/**
	 * Check if the current user already has a review for the plugin or theme being viewed.
	 *
	 * @return bool True if review already exists, false otherwise.
	 */
	public function review_exists() {
		if ( ! isset( $this->review_exists ) ) {
			$this->review_exists = bbp_has_topics( array(
				'author'       => get_current_user_id(),
				'post_status'  => 'any',
				'post_type'    => bbp_get_topic_post_type(),
				'post_parent'  => Plugin::REVIEWS_FORUM_ID,
				'tax_query'    => array( array(
					'taxonomy' => $this->taxonomy,
					'field'    => 'slug',
					'terms'    => $this->slug,
				) ),
				'no_found_rows' => true,
				'orderby'       => 'ID',
			) );

			$this->review_topic_query = bbpress()->topic_query;
		}

		return $this->review_exists;
	}

	public function add_topic_form() {
		if ( ! $this->is_rating_view() ) {
			return;
		}
		remove_filter( 'bbp_get_topic_title', array( $this, 'get_topic_title' ), 10 );

		if ( ! is_user_logged_in() ) {
			echo '<div class="bbp-template-notice"><p>';
			printf(
				/* translators: %s: login URL */
				__( 'You must be <a href="%s" rel="nofollow">logged in</a> to submit a review.', 'wporg-forums' ),
				add_query_arg(
					'redirect_to',
					urlencode( esc_url_raw( sprintf( home_url( '/%s/%s/reviews/' ), $this->compat, $this->slug ) ) ),
					'https://login.wordpress.org/'
				)
			);
			echo '</p></div>';
			return;
		}

		if ( $this->review_exists() ) {
			bbpress()->topic_query = $this->review_topic_query;
			bbp_the_topic();
			add_filter( 'bbp_is_topic_edit', '__return_true' );
		} else {
			add_filter( 'bbp_is_topic_edit', '__return_false' );
		}

		bbp_get_template_part( 'form', 'topic' );
		add_filter( 'bbp_is_topic_edit', '__return_false' );
	}

	public function do_template_notice() {
		if ( $this->object->post_author == get_current_user_id() ) { ?>
			<p><?php _e( 'A review should be the review of an experience a user has with your project, not for self-promotion.', 'wporg-forums' ); ?></p>

			<?php if ( 'plugin' === $this->compat ) : ?>
				<p><?php _e( 'Since you work on this plugin, please consider <em>not</em> leaving a review on your own work. You were probably going to give it five stars anyway.', 'wporg-forums' ); ?></p>
			<?php elseif ( 'theme' === $this->compat ) : ?>
				<p><?php _e( 'Since you work on this theme, please consider <em>not</em> leaving a review on your own work. You were probably going to give it five stars anyway.', 'wporg-forums' ); ?></p>
			<?php endif;

			return;
		}

		$report = $rate = '';
		switch( $this->compat ) {
			case 'plugin' :
				/* translators: %s: plugin support forum URL */
				$report = __( 'If you are reporting an issue with this plugin, please post in the <a href="%s">plugin support forum</a> instead.', 'wporg-forums' );
				$rate   = __( 'In order to rate a plugin, you must also submit a review.', 'wporg-forums' );
				break;
			case 'theme' :
				/* translators: %s: theme support forum URL */
				$report = __( 'If you are reporting an issue with this theme, please post in the <a href="%s">theme support forum</a> instead.', 'wporg-forums' );
				$rate   = __( 'In order to rate a theme, you must also submit a review.', 'wporg-forums' );
				break;
		}
		?>
		<p><?php _e( 'When posting a review, follow these guidelines:', 'wporg-forums' ); ?></p>
		<ul>
			<li><?php printf( $report, esc_url( sprintf( home_url( '/%s/%s/' ), $this->compat, $this->slug ) ) ); ?></li>
			<li><?php echo esc_html( $rate ); ?></li>
			<li><?php esc_html_e( 'Please provide as much detail as you can to justify your rating and to help others.', 'wporg-forums' ); ?></li>
			<li><?php
				printf(
					/* translators: %s: Forum user guide URL */
					__( 'Please <a href="%s">do not add links to your review</a>, keep the review about your experience in text only.', 'wporg-forums' ),
					esc_url( __( 'https://wordpress.org/support/forum-user-guide/faq/#why-are-links-not-allowed-in-reviews', 'wporg-forums' ) )
				);
			?></li>
		</ul>
		<?php
	}

	public function add_topic_form_stars() {
		if ( ! $this->is_rating_view() ) {
			return;
		}

		if ( bbp_is_topic_edit() && bbp_get_topic_author_id() != get_current_user_id() ) {
			return;
		}

		printf(
			'<label for="rating">%s</label>',
			__( 'Your Rating:', 'wporg-forums' )
		);

		\WPORG_Ratings::get_dashicons_form( $this->compat, $this->slug, true );
	}

	public function is_rating_view() {
		if ( bbp_is_single_view() && 'reviews' == bbp_get_view_id() ) {
			return true;
		}

		if ( bbp_is_topic_edit() && bbp_get_topic_forum_id() == Plugin::REVIEWS_FORUM_ID ) {
			return true;
		}

		return false;
	}

	/**
	 * Prepend the Rating to the feed content.
	 */
	public function feed_prepend_rating( $content, $topic_id ) {
		if ( Plugin::REVIEWS_FORUM_ID == bbp_get_topic_forum_id( $topic_id ) ) {
			$user_id = bbp_get_topic_author_id( $topic_id );
			$rating = \WPORG_Ratings::get_user_rating( $this->compat, $this->slug, $user_id );

			if ( $rating ) {
				$content = sprintf(
					"<p>%s</p>\n%s",
					sprintf(
						__( 'Rating: %s', 'wporg-forums' ),	
						sprintf(
							_n( '%s star', '%s stars', $rating, 'wporg-forums' ),
							$rating
						)
					),
					$content
				);
			}
		}

		return $content;
	}

	/**
	 * Append the Rating to the feed title.
	 */
	public function feed_append_rating( $title, $topic_id ) {
		if ( Plugin::REVIEWS_FORUM_ID == bbp_get_topic_forum_id( $topic_id ) ) {
			$user_id = bbp_get_topic_author_id( $topic_id );
			$rating = \WPORG_Ratings::get_user_rating( $this->compat, $this->slug, $user_id );

			if ( $rating ) {
				$title = sprintf(
					"%s (%s)",
					$title,
					sprintf(
						_n( '%s star', '%s stars', $rating, 'wporg-forums' ),
						$rating
					)
				);
			}
		}

		return $title;
	}
}

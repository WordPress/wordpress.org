<?php

namespace WordPressdotorg\Forums;

class Ratings_Compat {

	var $compat   = null;
	var $slug     = null;
	var $object   = null;
	var $taxonomy = null;

	var $filter = false;

	public function __construct( $compat, $slug, $taxonomy, $object ) {
		if ( ! class_exists( 'WPORG_Ratings' ) ) {
			return;
		}

		if ( empty( $compat ) || empty( $slug ) || empty( $taxonomy ) || empty( $object ) ) {
			return;
		}

		$this->compat   = $compat;
		$this->slug     = $slug;
		$this->taxonomy = $taxonomy;
		$this->object   = $object;

		$this->ratings_counts = \WPORG_Ratings::get_rating_counts( $this->compat, $this->slug );
		$this->avg_rating = \WPORG_Ratings::get_avg_rating( $this->compat, $this->slug );

		// Set up a filter on star rating number.
		if ( isset( $_GET['filter'] ) ) {
			$filter = absint( $_GET['filter'] );
			if ( $filter > 5 ) {
				$filter = 5;
			}
			$this->filter = $filter;
			add_filter( 'posts_clauses', array( $this, 'add_filter_to_posts_clauses' ) );
			add_action( 'pre_get_posts', array( $this, 'no_found_rows' ) );
			add_filter( 'bbp_topic_pagination', array( $this, 'add_filter_topic_pagination' ) );
		}

		// Total reviews count. Can be altered using $this->filter if needed.
		$this->reviews_count = \WPORG_Ratings::get_rating_count( $this->compat, $this->slug, 0 );

		// Set up the single topic.
		add_action( 'bbp_template_before_lead_topic', array( $this, 'add_topic_stars' ) );

		// Set up ratings view.
		add_action( 'wporg_compat_before_single_view', array( $this, 'do_view_header' ) );

		// Add the topic form as either a new or edit form.
		add_action( 'wporg_compat_new_review_notice', array( $this, 'do_template_notice' ) );
		add_action( 'wporg_compat_after_single_view', array( $this, 'add_topic_form' ) );
		add_action( 'bbp_theme_before_topic_form_content', array( $this, 'add_topic_form_stars' ), 8 );

		// Check to see if a topic is being created/edited.
		add_action( 'bbp_new_topic_post_extras', array( $this, 'topic_post_extras' ) );
		add_action( 'bbp_edit_topic_post_extras', array( $this, 'topic_post_extras' ) );
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
	 * Display star ratings next to topic titles in the review forum view.
	 *
	 * @param string $title The topic title
	 * @param int $topic_id The topic id
	 */
	public function get_topic_title( $title, $topic_id ) {
		if ( bbp_is_single_view() && 'reviews' == bbp_get_view_id() ) {
			$user_id = bbp_get_topic_author_id( $topic_id );
			$rating = \WPORG_Ratings::get_user_rating( $this->compat, $this->slug, $user_id );
			if ( $rating > 0 ) {
				$title .= ' ' . \WPORG_Ratings::get_dashicons_stars( $rating );
			}
		}
		return $title;
	}

	public function do_view_header() {
		if ( ! bbp_is_single_view() || 'reviews' != bbp_get_view_id() ) {
			return;
		}

		// Add the filter for topic titles here.
		add_filter( 'bbp_get_topic_title', array( $this, 'get_topic_title' ), 10, 2 );
		?>
<link itemprop="applicationCategory" href="http://schema.org/OtherApplication" />
<span itemprop="offers" itemscope itemtype="http://schema.org/Offer">
	<meta itemprop="price" content="0.00" />
	<meta itemprop="priceCurrency" content="USD" />
	<span itemprop="seller" itemscope itemtype="http://schema.org/Organization">
		<span itemprop="name" content="WordPress.org"></span>
	</span>
</span>

<div class="review-ratings" itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">
	<div class="col-3">
		<div class="reviews-about" style="display:none;" itemprop="itemReviewed"><?php echo esc_html( $this->object->post_title ); ?></div>
		<div class="reviews-total-count"><?php printf( _n( '<span itemprop="reviewCount">%d</span> review', '<span itemprop="reviewCount">%d</span> reviews', $this->reviews_count ), $this->reviews_count ); ?></div>
		<?php
			foreach ( array( 5, 4, 3, 2, 1 ) as $rating ) {
				$ratings_count = isset( $this->ratings_counts[ $rating ] ) ? $this->ratings_counts[ $rating ] : 0;
				$ratings_count_total = isset( $this->ratings_counts ) ? array_sum( $this->ratings_counts) : 0;
				$stars_title = sprintf( _n( 'Click to see reviews that provided a rating of %d star', 'Click to see reviews that provided a rating of %d stars', $rating, 'wporg-forums' ), $rating );
				$stars_text = sprintf( __( '%d stars', 'wporg-forums' ), $rating );
				$width = 0;
				if ( $ratings_count && $ratings_count_total ) {
					$width = 92 * ( $ratings_count / $ratings_count_total );
				}
				?>
				<div class="counter-container">
				<a href="<?php echo esc_url( sprintf( 'https://wordpress.org/support/%s/%s/reviews/?filter=%s', $this->compat, $this->slug, $rating ) ); ?>"
					title="<?php echo esc_attr( $stars_title ); ?>">
					<span class="counter-label" style="float:left;margin-right:5px;"><?php echo esc_html( $stars_text ); ?></span>
					<span class="counter-back" style="height:17px;width:92px;background-color:#ececec;float:left;">
						<span class="counter-bar" style="width:<?php echo esc_attr( $width ); ?>px;height:17px;background-color:#ffc733;float:left;"></span>
					</span>
				</a>
				<span class="counter-count" style="margin-left:5px;"><?php echo esc_html( $ratings_count ); ?></span>
				</div>
				<?php
			}
		?>
	</div>
	<div class="col-3">
		<div style="font-weight:bold;"><?php _e( 'Average Rating', 'wporg-forums' ); ?></div>
		<?php echo \WPORG_Ratings::get_dashicons_stars( $this->avg_rating ); ?><?php echo sprintf( __( '%s out of <span itemprop="bestRating">5</span> stars', 'wporg-forums' ), round( isset( $this->avg_rating ) ? $this->avg_rating : 0, 1 ) ); ?>
		<div class="reviews-submit-link">
		<?php
			if ( is_user_logged_in() ) {
				echo '<a href="#new-post" class="btn">';
				_e( 'Add your own review', 'wporg-forums' );
				echo '</a>';
			} else {
				echo '<span class="reviews-need-login">';
				printf(
					__( 'You must %s to submit a review. You can also log in or register using the form near the top of this page.' ),
					sprintf( '<a href="https://login.wordpress.org/">%s</a>', esc_html_x( 'log in', 'verb: You must log in to submit a review.', 'wporg-forums' ) ) );
				echo '</span>';
			}
		?>
		</div>
	</div>
	<div class="col-1">
	</div>
	<?php
	// If current listing is filtered by rating, display message indicating this,
	// along with an explicit link to return them to the unfiltered view.
	$filter = isset( $_GET['filter'] ) ? absint( $_GET['filter'] ) : 0;
	if ( $filter > 0 && $filter < 6 ) {
		echo '<div class="col-9 reviews-filtered-msg" style="font-style:italic;margin-top:24px;">';
		printf(
			_n( 'You are currently viewing the reviews that provided a rating of <strong>%d star</strong>. <a href="%s">Click here</a> to see all reviews.',
			    'You are currently viewing the reviews that provided a rating of <strong>%d stars</strong>. <a href="%s">Click here</a> to see all reviews.',
				'wporg-forums' ), $filter, esc_url ( sprintf( '//wordpress.org/support/%s/%s/reviews/', $this->compat, $this->slug ) ) );
		echo "</div>\n";
	}
	?>
</div>
	<?php
	}

	/**
	 * Set the rating on topic creation or edit.
	 *
	 * @param int $topic_id The topic id
	 */
	public function topic_post_extras( $topic_id ) {
		if ( isset( $_POST['rating'] ) && in_array( (int) $_POST['rating'], array( 1, 2, 3, 4, 5 ) ) ) {
			if (
				Plugin::REVIEWS_FORUM_ID == bbp_get_topic_forum_id( $topic_id )
			&&
				bbp_get_topic_author_id( $topic_id ) == get_current_user_id()
			) {
				\WPORG_Ratings::set_rating( $topic_id, $this->compat, $this->slug, bbp_get_topic_author_id( $topic_id ), absint( $_POST['rating'] ) );
			}
		}
	}

	public function add_topic_form() {
		if ( ! $this->is_rating_view() ) {
			return;
		}
		remove_filter( 'bbp_get_topic_title', array( $this, 'get_topic_title' ), 10 );

		if ( ! is_user_logged_in() ) {
			echo '<p>';
			printf(
			__( 'You must %s to submit a review. You can also log in or register using the form near the top of this page.' ),
			sprintf( '<a href="https://login.wordpress.org/">%s</a>', esc_html_x( 'log in', 'verb: You must log in to submit a review.', 'wporg-forums' ) ) );
			echo '</p>';
			return;
		}

		if ( bbp_has_topics( array(
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
		) ) ) {
			bbp_the_topic();
			add_filter( 'bbp_is_topic_edit', '__return_true' );
		} else {
			add_filter( 'bbp_is_topic_edit', '__return_false' );
		}

		bbp_get_template_part( 'form', 'topic' );
		add_filter( 'bbp_is_topic_edit', '__return_false' );
	}

	public function do_template_notice() {
		$report = $rate = '';
		switch( $this->compat ) {
			case 'plugin' :
				$report = __( 'If you are reporting an issue with this plugin, please post %s instead.', 'wporg-forums' );
				$rate   = __( 'In order to rate a plugin, you must also submit a review.', 'wporg-forums' );
				break;
			case 'theme' :
				$report = __( 'If you are reporting an issue with this theme, please post %s instead.', 'wporg-forums' );
				$rate   = __( 'In order to rate a theme, you must also submit a review.', 'wporg-forums' );
				break;
		}
		?>
		<p><?php _e( 'When posting a review, follow these guidelines:', 'wporg-forums' ); ?></p>
		<ul>
			<li><?php printf( esc_html( $report ), sprintf( '<a href="%s">%s</a>',
					esc_url( sprintf( 'https://wordpress.org/support/%s/%s/', $this->compat, $this->slug ) ),
					_x( 'here', 'please post here instead', 'wporg-forums' )
				) ); ?></li>
			<li><?php echo esc_html( $rate ); ?></li>
			<li><?php esc_html_e( 'Please provide as much detail as you can to justify your rating and to help others.', 'wporg-forums' ); ?></li>
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
}

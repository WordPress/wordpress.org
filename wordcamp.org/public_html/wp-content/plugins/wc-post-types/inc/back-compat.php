<?php
/**
 * Backwards compatibility for the base themes with the new (and old) post types.
 * See __construct on how this class is loaded. It's designed to overwrite default
 * functionality via actions and filters, or by unhooking existing action/filters/shortcodes
 * and hooking its owr callbacks.
 */
class WordCamp_Post_Types_Plugin_Back_Compat {
	protected $stylesheet = '';

	function __construct() {

		// Array of themes that should work with this class.
		$compat_themes = array(
			'wordcamp-base',
			'wordcamp-base-v2',
		);

		$this->stylesheet = wp_get_theme()->get_stylesheet();
		$this->template = wp_get_theme()->get_template();

		// Initialize only if theme requires.
		if ( in_array( $this->stylesheet, $compat_themes ) || in_array( $this->template, $compat_themes ) ) {
			add_action( 'wcpt_back_compat_init', array( $this, 'wcpt_back_compat_init' ) );

			// Base theme should not load the following modules.
			add_filter( 'wcb_load_component_speakers', '__return_false' );
			add_filter( 'wcb_load_component_sessions', '__return_false' );
			add_filter( 'wcb_load_component_sponsors', '__return_false' );
		}
	}

	/**
	 * Runs somewhere around WordPress init, but after all/most of the
	 * actions and filters have been assigned in the main plugin file.
	 */
	function wcpt_back_compat_init() {
		global $wcpt_plugin;

		// Keep a link to the main plugin object.
		$this->wcpt =& $wcpt_plugin;

		add_filter( 'wcb_entry_meta', array( $this, 'wcb_session_entry_meta' ) );

		// Remove real shortcodes and add some of our own back-compat ones.
		remove_shortcode( 'speakers' );
		remove_shortcode( 'sponsors' );
		remove_shortcode( 'sessions' );

		// Back-compat shortcode handlers.
		add_shortcode( 'speakers', array( $this, 'shortcode_speakers' ) );
		add_shortcode( 'sessions', array( $this, 'shortcode_sessions' ) );
		add_shortcode( 'sponsors', array( $this, 'shortcode_sponsors' ) );
	}

	/**
	 * The [speakers] shortcode handler.
	 */
	function shortcode_speakers( $attr, $content ) {
		global $post;

		$speakers = new WP_Query( array(
			'post_type' => 'wcb_speaker',
			'orderby' => 'title',
			'order' => 'ASC',
			'posts_per_page' => -1,
		) );

		if ( ! $speakers->have_posts() )
			return '';

		ob_start();
		?>

		<div class="cpt-loop speaker-gravatar-list clearfix">
			<p>
			<?php while ( $speakers->have_posts() ) : $speakers->the_post(); ?>
			<?php
				$href  = '#' . esc_attr( $post->post_name );
				$title = esc_attr( get_the_title() );
				echo "<a href='$href' title='$title'>";
				echo get_avatar( get_post_meta( get_the_ID(), '_wcb_speaker_email', true ), 48 );
				echo '</a>';
			?>
			<?php endwhile; ?>
			</p>
		</div><!-- .cpt-loop -->

		<?php $speakers->rewind_posts(); ?>
		<?php $half_id = $this->wcb_optimal_column_split( $speakers, 200, 200 ); ?>

		<div class="cpt-loop speakers">

			<div class="grid_6 alpha">

				<?php while ( $speakers->have_posts() ) : $speakers->the_post(); ?>

					<?php
						if ( get_the_ID() == $half_id )
							echo '</div><div class="grid_6 omega">';

						$odd = ( ( $speakers->current_post + 1 ) % 2 ) ? 'odd' : 'even';
					?>

					<div id="<?php echo esc_attr( $post->post_name ); ?>" <?php post_class( 'speaker clearfix ' . $odd ); ?> >
						<h3 class="entry-title speaker-name"><?php the_title(); ?></h3>
						<div class="entry-content speaker-bio">
						<?php
							echo get_avatar( get_post_meta( get_the_ID(), '_wcb_speaker_email', true ), 102 );
							the_content();
						?>
						</div>
					</div>

				<?php endwhile; ?>

			</div>
		</div><!-- .cpt-loop -->
		<?php

		wp_reset_postdata();

		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}

	/**
	 * The [sessions] shortcode handler
	 */
	function shortcode_sessions( $attr, $content ) {
		global $post;

		$sessions = new WP_Query( array(
			'post_type' => 'wcb_session',
			'orberby' => 'title',
			'order' => 'DESC',
			'posts_per_page' => -1,
		) );

		if ( ! $sessions->have_posts() )
			return;

		ob_start();
		?>

		<div class="cpt-loop sessions">

			<?php $half_id = $this->wcb_optimal_column_split( $sessions, 200 ); ?>

			<div class="grid_6 alpha">

			<?php while ( $sessions->have_posts() ) : $sessions->the_post(); ?>

				<?php
					// Close the first column, open the second.
					if ( get_the_ID() == $half_id )
						echo '</div><div class="grid_6 omega">';

					$odd = ( ( $sessions->current_post +1 ) % 2 ) ? 'odd' : 'even';
				?>
				<div id="post-<?php the_ID(); ?>" <?php post_class( 'session ' . $odd ); ?> >
					<h3 class="entry-title session-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>

					<?php
						$meta = array();
						$speakers = get_post_meta( get_the_ID(), '_wcb_session_speakers', true );
						$track = get_the_terms( get_the_ID(), 'wcb_track' );

						if ( empty( $track ) ) {
							$track = '';
						} else {
							$track = array_values( $track );
							$track = $track[0]->name;
						}

						if ( ! empty( $speakers ) )
							$meta['speakers'] = sprintf( __( 'Presented by %s', 'wordcamporg' ), esc_html( rtrim( $speakers, ',' ) ) );

						if ( ! empty( $track ) )
							$meta['track'] = sprintf( __( '%s Track', 'wordcamporg' ), esc_html( $track ) );

						$track_url = get_term_link( $track, 'wcb_track' );
						if ( ! is_wp_error( $track_url ) )
							$meta['track'] = sprintf( '<a href="%s">%s</a>', esc_url( $track_url ), $meta['track'] );

						// Output the meta
						if ( ! empty( $meta ) ) {
							$meta = implode( ' <span class="meta-sep meta-sep-bull">&bull;</span> ', $meta );
							printf( '<div class="entry-meta session-speakers session-meta">%s</div>', $meta );
						}
					?>
					<div class="entry-content session-description">
						<?php the_post_thumbnail(); ?>
						<?php the_content(); ?>
					</div>
				</div>

			<?php endwhile; ?>

			</div><!-- .grid_6 -->
		</div><!-- .cpt-loop -->

		<?php

		wp_reset_postdata();

		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}

	/**
	 * The [sponsors] shortcode handler.
	 */
	function shortcode_sponsors( $attr, $content ) {
		global $post;

		$terms = $this->wcpt->get_sponsor_levels();

		ob_start();
		?>
		<div class="sponsors">
		<?php foreach ( $terms as $term ) : ?>
			<?php
				$sponsors = new WP_Query( array(
					'post_type' => 'wcb_sponsor',
					'order' => 'ASC',
					'posts_per_page' => -1,
					'taxonomy' => $term->taxonomy,
					'term' => $term->slug,
				) );

				if ( ! $sponsors->have_posts() )
					continue;
			?>

			<div class="sponsor-level <?php echo $term->slug; ?>">
				<h2 class="sponsor-level-title"><?php echo esc_html( $term->name ); ?></h2>

				<?php while ( $sponsors->have_posts() ) : $sponsors->the_post(); ?>
				<div id="post-<?php the_ID(); ?>" <?php post_class( 'sponsor' ); ?> >
					<h3 class="entry-title sponsor-title"><a href="<?php the_permalink(); ?>">
						<?php ( has_post_thumbnail() ) ? the_post_thumbnail() : the_title(); ?>
					</a></h3>
					<div class="entry-content sponsor-description">
						<?php the_content(); ?>
					</div>
				</div><!-- #post -->
				<?php endwhile; ?>

			</div><!-- .sponsor-level -->
		<?php endforeach; ?>
		</div><!-- .sponsors -->
		<?php

		wp_reset_postdata();

		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}

	/**
	 * Splits the query into two columns based upon content length. Ported from base theme functions.
	 *
	 * @todo Move to compat
	 * @param WP_Query $query
	 * @param integer $post_cost A character cost attributed to rendering a post. Helps for approximations.
	 * @param integer $min_chars The minimum number of characters per post. Helps for approximations.
	 * @return Object The starting post ID of the second column.
	 */
	public function wcb_optimal_column_split( $query, $post_cost=0, $min_chars=0 ) {
		$query->rewind_posts();

		$total  = 0;
		$totals = array();

		while ( $query->have_posts() ) {
			$post     = $query->next_post();
			$length   = strlen( $post->post_content );
			$total   += ( $length < $min_chars) ? $min_chars : $length;
			$total   += $post_cost;
			$totals[] = array( $total, $post->ID );
		}

		$optimum = $total / 2;

		foreach ( $totals as $arr ) {
			list( $current, $post_id ) = $arr;

			// When the total starts increasing, we've found the beginning of the new column.
			if ( isset( $last ) && abs( $optimum - $last ) < abs( $optimum - $current ) ) {
				return $post_id;
			}

			$last = $current;
		}
	}

	/**
	 * A filter for base theme's wcb_entry_meta.
	 */
	function wcb_session_entry_meta( $meta ) {
		if ( get_post_type() != 'wcb_session' )
			return $meta;

		$speakers = get_post_meta( get_the_ID(), '_wcb_session_speakers', true );
		$track = get_the_terms( get_the_ID(), 'wcb_track' );

		if ( empty( $track ) ) {
			$track = '';
		} else {
			$track = array_values( $track );
			$track = $track[0]->name;
		}

		if ( ! empty( $speakers ) )
			$meta['speakers'] = sprintf( __( 'Presented by %s', 'wordcamporg' ), esc_html( $speakers ) );

		if ( ! empty( $track ) )
			$meta['track'] = sprintf( __( '%s Track', 'wordcamporg' ), esc_html( $track ) );

		$track_url = get_term_link( $track, 'wcb_track' );
		if ( ! is_wp_error( $track_url ) )
			$meta['track'] = sprintf( '<a href="%s">%s</a>', esc_url( $track_url ), $meta['track'] );

		$order = array();

		if ( ! empty( $meta['speakers'] ) )
			$order[] = 'speakers';

		if ( ! empty( $meta['track'] ) ) {
			$order[] = 'sep';
			$order[] = 'track';
		}

		$order[] = 'edit';

		$meta['order'] = $order;

		return $meta;
	}
}
new WordCamp_Post_Types_Plugin_Back_Compat;
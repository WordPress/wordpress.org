<?php
/**
 * Template for My Events.
 */

namespace Wporg\TranslationEvents;

use DateTime;
use WP_Query;

/** @var WP_Query $events_i_created_query */
/** @var WP_Query $events_i_attended_query */

gp_title( esc_html__( 'Translation Events', 'gp-translation-events' ) . ' - ' . esc_html__( 'My Events', 'gp-translation-events' ) );
gp_breadcrumb_translation_events( array( esc_html__( 'My Events', 'gp-translation-events' ) ) );
gp_tmpl_header();
gp_tmpl_load( 'events-header', get_defined_vars(), __DIR__ );
?>

<div class="event-page-wrapper">
	<h1 class="event_page_title"><?php esc_html_e( 'My Events', 'gp-translation-events' ); ?> </h1>
	<h2 class="event_page_title"><?php esc_html_e( 'Events I have created', 'gp-translation-events' ); ?> </h2>
	<?php if ( $events_i_created_query->have_posts() ) : ?>
		<ul>
		<?php
		while ( $events_i_created_query->have_posts() ) :
			$events_i_created_query->the_post();
			$event_id                      = get_the_ID();
			$event_start                   = get_post_meta( $event_id, '_event_start', true );
			list( $permalink, $post_name ) = get_sample_permalink( $event_id );
			$permalink                     = str_replace( '%pagename%', $post_name, $permalink );
			$event_url                     = gp_url( wp_make_link_relative( $permalink ) );
			$event_edit_url                = gp_url( 'events/edit/' . $event_id );
			$event_status                  = get_post_status( $event_id );
			$event_start                   = ( new DateTime( get_post_meta( get_the_ID(), '_event_start', true ) ) )->format( 'M j, Y' );
			$event_end                     = ( new DateTime( get_post_meta( get_the_ID(), '_event_end', true ) ) )->format( 'M j, Y' );
			?>
			<li class="event-list-item">
				<a class="event-link-<?php echo esc_attr( $event_status ); ?>" href="<?php echo esc_url( $event_url ); ?>"><?php the_title(); ?></a>
				<a href="<?php echo esc_url( $event_edit_url ); ?>" class="button is-small action edit">Edit</a>
				<?php if ( 'draft' === $event_status ) : ?>
					<span class="event-label-<?php echo esc_attr( $event_status ); ?>"><?php echo esc_html( $event_status ); ?></span>
				<?php endif; ?>
				<?php if ( $event_start === $event_end ) : ?>
					<span class="event-list-date events-i-am-attending"><?php echo esc_html( $event_start ); ?></span>
				<?php else : ?>
					<span class="event-list-date events-i-am-attending"><?php echo esc_html( $event_start ); ?> - <?php echo esc_html( $event_end ); ?></span>
				<?php endif; ?>
				<p><?php the_excerpt(); ?></p>
			</li>
		<?php endwhile; ?>
		</ul>

		<?php
		echo wp_kses_post(
			paginate_links(
				array(
					'total'     => $events_i_created_query->max_num_pages,
					'current'   => max( 1, $events_i_created_query->query_vars['events_i_created_paged'] ),
					'format'    => '?events_i_created_paged=%#%',
					'prev_text' => '&laquo; Previous',
					'next_text' => 'Next &raquo;',
				)
			) ?? ''
		);

		wp_reset_postdata();
	else :
		echo 'No events found.';
	endif;
	?>

	<h2 class="event_page_title"><?php esc_html_e( 'Events I attended', 'gp-translation-events' ); ?> </h2>
	<?php if ( $events_i_attended_query->have_posts() ) : ?>
		<ul>
		<?php
		while ( $events_i_attended_query->have_posts() ) :
			$events_i_attended_query->the_post();
			$event_id                      = get_the_ID();
			$event_start                   = get_post_meta( $event_id, '_event_start', true );
			list( $permalink, $post_name ) = get_sample_permalink( $event_id );
			$permalink                     = str_replace( '%pagename%', $post_name, $permalink );
			$event_url                     = gp_url( wp_make_link_relative( $permalink ) );
			$event_edit_url                = gp_url( 'events/edit/' . $event_id );
			$event_status                  = get_post_status( $event_id );
			$event_start                   = ( new DateTime( get_post_meta( get_the_ID(), '_event_start', true ) ) )->format( 'M j, Y' );
			$event_end                     = ( new DateTime( get_post_meta( get_the_ID(), '_event_end', true ) ) )->format( 'M j, Y' );
			?>
			<li class="event-list-item">
				<a class="event-link-<?php echo esc_attr( $event_status ); ?>" href="<?php echo esc_url( $event_url ); ?>"><?php the_title(); ?></a>
				<?php if ( $event_start === $event_end ) : ?>
					<span class="event-list-date events-i-am-attending"><?php echo esc_html( $event_start ); ?></span>
				<?php else : ?>
					<span class="event-list-date events-i-am-attending"><?php echo esc_html( $event_start ); ?> - <?php echo esc_html( $event_end ); ?></span>
				<?php endif; ?>
				<p><?php the_excerpt(); ?></p>
			</li>
		<?php endwhile; ?>
		</ul>

		<?php
		echo wp_kses_post(
			paginate_links(
				array(
					'total'     => $events_i_attended_query->max_num_pages,
					'current'   => max( 1, $events_i_attended_query->query_vars['events_i_attended_paged'] ),
					'format'    => '?events_i_attended_paged=%#%',
					'prev_text' => '&laquo; Previous',
					'next_text' => 'Next &raquo;',
				)
			) ?? ''
		);

		wp_reset_postdata();
	else :
		echo 'No events found.';
	endif;
	?>
</div>
<?php
	gp_tmpl_footer();
?>

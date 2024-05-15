<?php get_header(); ?>

<?php get_template_part( 'masthead' ); ?>

<?php get_template_part( 'subhead' ); ?>

<section class="get-involved">
	<div class="wrapper">
		<h2 class="section-title"><?php _e( 'There are many different ways for you to get involved with WordPress:', 'make-wporg' ); ?></h2>

		<div class="wizard-callout">
		<?php printf(
			__( 'Not sure which contributor teams match your interests and abilities? Check out our <a href="%s">contributor wizard</a>.', 'make-wporg' ),
			esc_url( 'https://make.wordpress.org/contribute/' )
		); ?>
		</div>

		<div class="make-sites">
		<?php
			$sites_query = new WP_Query( 'post_type=make_site&posts_per_page=-1&order=ASC' );
			$makesites = make_site_get_network_sites();
		?>
		<?php while ( $sites_query->have_posts() ) : $sites_query->the_post(); ?>
			<?php
				$make_site_id = get_post_meta( $post->ID, 'make_site_id', true );
				$url          = $makesites[ $make_site_id ];
			?>
			<article id="site-<?php the_ID(); ?>" <?php post_class(); ?>>
				<h2>
					<?php if ( $url ) : ?>
						<a
							title="<?php printf( esc_attr( 'Learn more about %s.', 'make-wporg' ), esc_html( get_the_title() ) ); ?>"
							href="<?php echo esc_url( $url ); ?>"
						><?php the_title(); ?></a>
					<?php else : ?>
						<?php the_title(); ?>
					<?php endif; ?>
				</h2>

				<div class="team-description">
					<?php the_content(); ?>
				</div>

				<div class="team-meeting">
					<?php
						echo do_shortcode( sprintf( '[meeting_time team="%s"][/meeting_time]', $post->post_title ) );
					?>
				</div>
			</article>
		<?php endwhile; ?>
		</div>
	</div>
</section>

<script type="text/javascript">

	var parse_date = function (text) {
		var m = /^([0-9]{4})-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})\+00:00$/.exec(text);
		var d = new Date();
		d.setUTCFullYear(+m[1]);
		d.setUTCDate(+m[3]);
		d.setUTCMonth(+m[2]-1);
		d.setUTCHours(+m[4]);
		d.setUTCMinutes(+m[5]);
		d.setUTCSeconds(+m[6]);
		return d;
	}
	var format_time = function (d) {
		return d.toLocaleTimeString(navigator.language, {weekday: 'long', hour: '2-digit', minute: '2-digit'});
	}

	var nodes = document.getElementsByTagName('time');
	for (var i=0; i<nodes.length; ++i) {
		var node = nodes[i];
		if (node.className === 'date') {
			var d = parse_date(node.getAttribute('date-time'));
			if (d) {
				node.textContent = format_time(d);
			}
		}
	}
</script>
<?php get_footer(); ?>

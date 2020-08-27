<?php get_header(); ?>

<div class="wrapper">
	<div class="header-section">
		<h2 class="title all"><?php _e( 'Upcoming WordPress Meetings', 'make-wporg' ); ?></h2>
		<h2 class="title team"><?php _e( 'Upcoming Team Meetings', 'make-wporg' ); ?></h2>
		<a class="team" href="/meetings/"><?php _e( 'Show meetings for other teams', 'make-wporg' ); ?></a>
	</div>
<table class="schedule">
	<thead>
		<tr>
		<th><?php _e( 'Team', 'make-wporg' ); ?></th>
		<th><?php _e( 'Name', 'make-wporg' ); ?></th>
		<th><?php _e( 'Next Meeting', 'make-wporg' ); ?></th>
		<th><?php _e( 'Location', 'make-wporg' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php while( have_posts() ): the_post(); ?>
		<tr id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<td class="team"><?php
				printf(
					'<a href="#%s">%s</a>',
					esc_attr( strtolower( $post->team ) ),
					$post->team
				);
			?></td>
			<td><?php
				$title = get_the_title();
				printf(
					'<a href="%s">%s</a>',
					esc_url( $post->link ),
					$title
				);
				if ( current_user_can( 'edit_post', get_the_ID() ) ) {
					printf(
						' <a class="edit" href="%s" aria-label="%s">%s</a>',
						get_edit_post_link(),
						/* translators: %s: post title */
						esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;', 'make-wporg' ), $title ) ),
						__( '(Edit)', 'make-wporg' )
					);
				}
			?></td>
			<td><?php
			// convert the date time to a pretty format
			$time = strtotime( $post->next_date.' '. $post->time.' GMT' ); // note, strtotime doesn't grok UTC very well, GMT works fine though
			echo '<a href="http://www.timeanddate.com/worldclock/fixedtime.html?iso='.gmdate('Ymd\THi', $time).'"><abbr class="date" title="'.gmdate('c', $time).'">';
			echo date( 'F j, Y H:i \U\T\C', $time );
			echo '</abbr></a>';
			?></td>
			<td><?php echo $post->location; ?></td>
		</tr>
	<?php endwhile; ?>
	</tbody>
</table>
</div><!-- /wrapper -->
<?php

// convert the displayed date time to a local one to the viewing browser, if possible
function wporg_makehome_time_converter_script() {
	$timestrings = array(
		'months' => array(
			__( 'January',   'make-wporg' ),
			__( 'February',  'make-wporg' ),
			__( 'March',     'make-wporg' ),
			__( 'April',     'make-wporg' ),
			__( 'May',       'make-wporg' ),
			__( 'June',      'make-wporg' ),
			__( 'July',      'make-wporg' ),
			__( 'August',    'make-wporg' ),
			__( 'September', 'make-wporg' ),
			__( 'October',   'make-wporg' ),
			__( 'November',  'make-wporg' ),
			__( 'December',  'make-wporg' )
		),
	);
?>
	<script type="text/javascript">
	jQuery(document).ready( function ($) {
		var timestrings = <?php echo json_encode($timestrings); ?>

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
			return d.toLocaleTimeString(navigator.language, {weekday: 'long', month: 'long', day: 'numeric', year: 'numeric', hour: '2-digit', minute:'2-digit', timeZoneName: 'short'});
		}
		var format_date = function (d) {
			return d.toLocaleDateString(navigator.language, {weekday: 'long', month: 'long', day: 'numeric', year: 'numeric'});
		}
		/* Not all browsers, particularly Safari, support arguments to .toLocaleTimeString(). */
		var toLocaleTimeStringSupportsLocales = (function() {
			try {
				new Date().toLocaleTimeString('i');
			} catch (e) {
				return e.name === 'RangeError';
			}
		})();
		var nodes = document.getElementsByTagName('abbr');
		for (var i=0; i<nodes.length; ++i) {
			var node = nodes[i];
			if (node.className === 'date') {
				var d = parse_date(node.getAttribute('title'));
				if (d) {
					var new_text = '';
					if ( ! toLocaleTimeStringSupportsLocales ) {
						new_text += format_date(d);
						new_text += ' ';
					}
					new_text += format_time(d);
					node.textContent = new_text;
				}
			}
		}

		// Allow client side filtering using # in url
		var filterByTeam = function(team) {
			team = team.replace('#','');

			var rowsToRemove = $('.schedule').find('tr td:nth-child(1)').filter(function() {
				var reg = new RegExp(team, "i");
				return !reg.test($(this).text());
			});

			for (var i = 0; i < rowsToRemove.length; i++) {
				$(rowsToRemove[i]).parent().hide();
			}

			$('.header-section').find('.all').hide();
			$('.header-section').find('.team').show();
		}

		var showAllMeetings = function() {
			$('.schedule').find('tr').each(function() {
				$(this).show();
			});

			$('.header-section').find('.team').hide();
			$('.header-section').find('.all').show();
		}

		if (window.location.hash) {
			filterByTeam(window.location.hash);
		}
		else {
			$('.header-section').find('.all').show();
		}

		$('.schedule .team a').click(function() {
			var team = $(this).attr('href');
			filterByTeam(team);
			window.location.hash = team;
			return false;
		});

		$('.header-section a.team').click(function() {
			showAllMeetings();
			window.location.hash = '';
			return false;
		});

		// avoid page flickers on load
		$('.schedule').show();
	});
	</script>
<?php
}
add_action('wp_footer', 'wporg_makehome_time_converter_script');
?>
<?php get_footer(); ?>


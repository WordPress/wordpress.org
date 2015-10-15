<?php get_header(); ?>

<div class="wrapper">
	<h2 class="title"><?php _e('Upcoming WordPress Meetings','wporg'); ?></h2>
<table class="schedule">
	<thead>
		<tr>
		<th><?php _e('Team','wporg'); ?></th>
		<th><?php _e('Name','wporg'); ?></th>
		<th><?php _e('Next Meeting','wporg'); ?></th>
		<th><?php _e('Location','wporg'); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php while( have_posts() ): the_post(); ?>
		<tr id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<td><?php echo $post->team; ?></td>
			<td><a href="<?php echo $post->link; ?>"><?php the_title(); ?></a></td>
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
        'months' => array(__('January'), __('February'), __('March'),__('April'),__('May'),__('June'),__('July'),__('August'),__('September'),__('October'),__('November'),     __('December')),
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
		var format_date = function (d) {
			return d.toLocaleTimeString(navigator.language, {weekday: 'long', month: 'long', day: 'numeric', year: 'numeric', hour: '2-digit', minute:'2-digit', timeZoneName: 'short'});
        }
        var nodes = document.getElementsByTagName('abbr');
        for (var i=0; i<nodes.length; ++i) {
            var node = nodes[i];
            if (node.className === 'date') {
                var d = parse_date(node.getAttribute('title'));
                if (d) {
                    node.textContent = format_date(d);
                }
            }
        }
    });
    </script>
<?php
}
add_action('wp_footer', 'wporg_makehome_time_converter_script');
?>
<?php get_footer(); ?>


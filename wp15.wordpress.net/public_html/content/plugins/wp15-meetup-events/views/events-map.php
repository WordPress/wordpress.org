<?php

namespace WP15\Meetup_Events;
defined( 'WPINC' ) || die();

?>

<div id="wp15-events-map">
	<div class="wp15-spinner spinner spinner-visible"></div>
</div>

<script id="tmpl-wp15-map-marker" type="text/html">
	<div id="wp15-map-marker-{{event.id}}" class="wp15-map-marker">
		<h3 class="wp15-event-group">
			{{event.group}}
		</h3>

		<p class="wp15-event-title">
			<a href="{{event.url}}">
				{{event.name}}
			</a>
		</p>

		<p class="wp15-event-date-time">
			{{event.time}}
		</p>
	</div>
</script>

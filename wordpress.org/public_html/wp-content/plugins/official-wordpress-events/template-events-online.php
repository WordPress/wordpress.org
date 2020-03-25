<script type="text/template" id="tmpl-owe-timezone">
	<p class="owe-timezone">
		All times displayed in {{ data }}
	</p>
</script>

<script type="text/template" id="tmpl-official-events">
	<h3>
		{{ data.date }}
		<span class="owe-day-of-week">{{ data.dayOfWeek }}</span>
	</h3>
	<ul class="ofe-event-list">
		{{{ data.eventMarkup }}}
	</ul>
</script>

<script type="text/template" id="tmpl-official-event">
	<li>
		<a href="{{ data.url }}">
			{{ data.title }}
		</a>
		<span class="owe-separator"></span>
		{{ data.startTime }}
	</li>
</script>

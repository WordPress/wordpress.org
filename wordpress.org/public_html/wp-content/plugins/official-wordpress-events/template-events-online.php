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
		{{ data.startTime }}
		<span class="owe-timezone">{{ data.timezone }}</span>
		<br>
		<a href="{{ data.url }}">
			{{ data.title }}
		</a>
		<br>
		{{ data.type }}
	</li>
</script>

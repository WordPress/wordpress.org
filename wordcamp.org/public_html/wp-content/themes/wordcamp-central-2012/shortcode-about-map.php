<div id="wcc-map-<?php echo esc_attr( $attributes['id'] ); ?>" class="wcc-map">
	<div id="wcc-map-spinner" class="spinner spinner-visible"></div>
</div>

<script id="tmpl-wcc-map-marker" type="text/html">
	<# var items = [ 'wccDates', 'location', 'venueName' ]; #>

	<div id="wcc-map-marker-{{wordcamp.wccID}}" class="wcc-map-marker">
		<h3>
			<a href="{{wordcamp.wccURL}}">{{wordcamp.title}}</a>
		</h3>

		<ul>
			<# for ( var item in items ) { #>
				<# if ( '' != wordcamp[ items[ item ] ] ) { #>
					<li>{{wordcamp[ items[ item ] ]}}</li>
				<# } #>
			<# } #>
		</ul>
	</div>
</script>

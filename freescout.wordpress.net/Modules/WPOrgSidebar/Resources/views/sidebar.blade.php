<div class="wporg-sidebar">
	@foreach ( $panels as $panel_id => $panel )
		<div class="conv-sidebar-block">
			<div class="panel-group accordion accordion-empty">
				<div class="panel panel-default">
					<div class="panel-heading">
						<h4 class="panel-title">{{ $panel['title'] }}</h4>
					</div>
					<div class="panel-body wporg-sidebar-panel" data-url="{{ route( 'wporgsidebar.panel', array( 'conversation_id' => $conversation_id, 'panel' => $panel_id ) ) }}">
						<img src="{{ asset( 'img/loader-tiny.gif' ) }}" alt="" />
					</div>
				</div>
			</div>
		</div>
	@endforeach
</div>

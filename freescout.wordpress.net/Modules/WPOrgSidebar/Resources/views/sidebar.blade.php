<div class="wporg-sidebar" data-strings="{{ json_encode(
	array(
		'view_all' => __( 'View all :number' ),
		'failed'   => __( 'Could not load this panel.' ),
	)
) }}">
	@foreach ( $panels as $panel_id => $panel )
		{{-- FreeScout's own sidebar block, like Previous Conversations. --}}
		<div class="conv-sidebar-block">
			<div class="panel-group accordion accordion-empty">
				<div class="panel panel-default">
					<div class="panel-heading">
						<h4 class="panel-title">
							<a data-toggle="collapse" href=".collapse-wporg-{{ $panel_id }}">{{ $panel['title'] }}
								<b class="caret"></b>
							</a>
						</h4>
					</div>
					<div class="collapse-wporg-{{ $panel_id }} panel-collapse collapse in">
						<div class="panel-body">
							<div class="sidebar-block-header2"><strong>{{ $panel['title'] }}</strong> (<a data-toggle="collapse" href=".collapse-wporg-{{ $panel_id }}">{{ __( 'close' ) }}</a>)</div>
							<div class="wporg-sidebar-panel" data-url="{{ route( 'wporgsidebar.panel', array( 'conversation_id' => $conversation_id, 'panel' => $panel_id ) ) }}">
								<img src="{{ asset( 'img/loader-tiny.gif' ) }}" alt="" />
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	@endforeach
</div>

<form class="form-horizontal margin-top" method="POST" action="">
	{{ csrf_field() }}

	<p class="help-block">{{ __( 'Shows the :panel panel in the conversation sidebar of the mailboxes checked here.', array( 'panel' => $panel['title'] ) ) }}</p>

	<div class="form-group">
		<label class="col-sm-2 control-label">{{ __( 'Mailboxes' ) }}</label>

		<div class="col-sm-6">
			@forelse ( $mailboxes as $mailbox )
				<div class="checkbox">
					<label>
						<input type="checkbox" name="settings[{{ $option }}][]" value="{{ $mailbox->id }}" @if ( in_array( (int) $mailbox->id, $settings[ $option ], true ) ) checked @endif>
						{{ $mailbox->name }} <span class="text-help">{{ $mailbox->email }}</span>
					</label>
				</div>
			@empty
				<p class="form-control-static">{{ __( 'There are no mailboxes yet.' ) }}</p>
			@endforelse
		</div>
	</div>

	<div class="form-group margin-top">
		<div class="col-sm-6 col-sm-offset-2">
			<button type="submit" class="btn btn-primary">{{ __( 'Save' ) }}</button>
		</div>
	</div>
</form>

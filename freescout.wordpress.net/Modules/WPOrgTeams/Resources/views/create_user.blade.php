{{-- The script moves it where core's mailboxes were, and removes those: access comes from teams. --}}
<div class="form-group" id="wporgteams-create-user">
	<label class="col-sm-4 control-label">{{ __('Teams') }}</label>

	<div class="col-sm-6 control-padded">
		@forelse ( $teams as $team )
			<div class="control-group">
				<div class="controls">
					<label class="checkbox margin-top-0 margin-bottom-0" for="wporgteams-team-{{ $team->id }}">
						<input type="checkbox" name="{{ \Modules\WPOrgTeams\Http\Middleware\TeamAccess::TEAMS_FIELD }}[]" id="wporgteams-team-{{ $team->id }}" value="{{ $team->id }}" @if ( in_array( (string) $team->id, array_map( 'strval', (array) old( \Modules\WPOrgTeams\Http\Middleware\TeamAccess::TEAMS_FIELD, array() ) ), true ) ) checked="checked" @endif> {{ $team->first_name }}
					</label>
				</div>
			</div>
		@empty
			<p class="form-control-static text-help">{{ __('There are no teams yet.') }}</p>
		@endforelse
	</div>
</div>

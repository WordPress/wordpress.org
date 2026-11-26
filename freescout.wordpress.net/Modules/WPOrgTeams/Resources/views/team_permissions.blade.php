<div class="form-group margin-bottom-0" id="wporgteams-permissions">
	<label class="col-sm-4 control-label">{{ __('Permissions') }}</label>

	<div class="col-sm-6 control-padded">
		<input type="hidden" name="{{ \Modules\WPOrgTeams\Http\Middleware\TeamAccess::PERMISSIONS_SHOWN_FIELD }}" value="1">

		{{-- Not core's sel-all and sel-none classes, which check the mailboxes. --}}
		<div><a href="#" class="wporgteams-sel-all">{{ __('all') }}</a> / <a href="#" class="wporgteams-sel-none">{{ __('none') }}</a></div>

		<fieldset>
			@foreach ( $permissions as $permission )
				<div class="control-group">
					<div class="controls">
						<label class="checkbox margin-top-0 margin-bottom-0" for="wporgteams-permission-{{ $permission }}">
							<input type="checkbox" name="{{ \Modules\WPOrgTeams\Http\Middleware\TeamAccess::PERMISSIONS_FIELD }}[]" id="wporgteams-permission-{{ $permission }}" value="{{ $permission }}" @if ( in_array( (string) $permission, $checked, true ) ) checked="checked" @endif> {{ App\User::getUserPermissionName( $permission ) }}
						</label>
					</div>
				</div>
			@endforeach
		</fieldset>
	</div>
</div>

<div class="col-sm-6 col-sm-offset-4">
	<hr class="form-divider margin-top margin-bottom">
</div>

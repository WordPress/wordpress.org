<div class="form-group{{ $errors->has( 'wporg_username' ) ? ' has-error' : '' }}" id="wporgsso-account" data-connected="{{ $username ? 1 : 0 }}" data-password-login="{{ $password_login ? 1 : 0 }}">
	<label for="wporgsso-username" class="col-sm-2 control-label">{{ __( 'WordPress.org Username' ) }}</label>

	<div class="col-sm-6">
		@if ( $username )
			<p class="form-control-static">{{ $username }}</p>
		@elseif ( $can_connect )
			<input id="wporgsso-username" type="text" class="form-control input-sized" name="wporg_username" value="{{ old( 'wporg_username', '' ) }}" maxlength="60" autocomplete="off">
			<p class="help-block">{{ __( 'Connect this user to their WordPress.org account, so they can log in. This can’t be changed later.' ) }}</p>
		@else
			<p class="form-control-static text-help">{{ __( 'Not connected to a WordPress.org account, so this user can’t log in.' ) }}</p>
		@endif

		@include( 'partials/field_error', array( 'field' => 'wporg_username' ) )
	</div>
</div>

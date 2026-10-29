<div class="form-group{{ $errors->has( 'wporg_username' ) ? ' has-error' : '' }}" id="wporgsso-user" data-passwords="{{ $passwords ? 1 : 0 }}" data-strings="{{ json_encode(
	array(
		'heading'       => __( 'Add a User' ),
		'submit'        => __( 'Add User' ),
		'core_title'    => __( 'New User' ),
		'title'         => __( 'Add a User' ),
		'connected_to'  => __( 'This account already belongs to :name.' ),
		'blocked'       => __( 'This account is blocked on WordPress.org.' ),
		'no_two_factor' => __( 'This account needs two-factor authentication before it can log in.' ),
		'lookup_failed' => __( 'Could not look up this account.' ),
	)
) }}">
	<label for="wporgsso-username" class="col-sm-4 control-label">{{ __( 'WordPress.org Username' ) }}</label>

	<div class="col-sm-6">
		<input id="wporgsso-username" type="text" class="form-control input-sized" name="wporg_username" value="{{ old( 'wporg_username', '' ) }}" maxlength="60" required autocomplete="off" data-lookup-url="{{ route( 'wporgsso.lookup' ) }}">

		<p class="help-block" id="wporgsso-status"></p>

		@include( 'partials/field_error', array( 'field' => 'wporg_username' ) )
	</div>
</div>

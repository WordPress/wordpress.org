<div class="wporgsite-env-settings" data-settings="{{ json_encode( $wporgsite_env_settings['keys'] ) }}" data-note="{{ __( 'Set in the server’s configuration. Ask the systems team to change it.' ) }}">
	@include( $wporgsite_env_settings['view'] )
</div>

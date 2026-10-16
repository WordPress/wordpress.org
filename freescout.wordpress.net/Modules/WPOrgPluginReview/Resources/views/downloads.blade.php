{{-- In the header's menu: copies the download URLs of the plugins in review the agent has open, shown once there are any; downloads.js fills it. --}}
<li class="wporg-downloads hidden" data-strings="{{ json_encode( array( 'one' => __( 'Copy 1 URL' ), 'many' => __( 'Copy :count URLs' ), 'copied' => __( 'Copied' ), 'add' => __( 'Add to the URLs to copy' ), 'remove' => __( 'Remove from the URLs to copy' ) ) ) }}">
	<a href="#" class="wporg-downloads-copy" role="button" title="{{ __( 'Copy the download URLs of the plugins in review you have open, in the order you opened them' ) }}"><i class="glyphicon glyphicon-download-alt"></i> <span class="wporg-downloads-label"></span></a>
</li>
<li class="wporg-downloads hidden">
	<a href="#" class="wporg-downloads-clear" role="button" title="{{ __( 'Remove all the URLs' ) }}" aria-label="{{ __( 'Remove all the URLs' ) }}"><i class="glyphicon glyphicon-trash"></i></a>
</li>

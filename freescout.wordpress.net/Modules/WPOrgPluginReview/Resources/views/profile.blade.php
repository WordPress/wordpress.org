{{-- In the user's profile, like core's fields, with what it does behind an info icon: how many conversations My Reviews and My Cases open at a time. --}}
<div class="form-group">
	<label for="wporgpluginreview_open_limit" class="col-sm-2 control-label">{{ __( 'Conversations to open' ) }}</label>

	<div class="col-sm-6">
		<div class="flexy">
			<input id="wporgpluginreview_open_limit" type="number" class="form-control wporg-open-limit" name="wporgpluginreview_open_limit" value="{{ $limit }}" min="1" max="{{ $max }}" step="1">
			<i class="glyphicon glyphicon-info-sign icon-info" data-toggle="popover" data-trigger="hover" data-html="true" data-placement="left" data-title="{{ __( 'Conversations to open' ) }}" data-content="{{ __( 'How many conversations the Open buttons in My Reviews and My Cases open in new tabs at once, up to :max.', array( 'max' => $max ) ) }}"></i>
		</div>
	</div>
</div>

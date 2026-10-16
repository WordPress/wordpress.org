{{-- In the user's profile, like core's fields: how many conversations My Reviews and My Cases open at a time. --}}
<div class="form-group">
	<label for="wporgpluginreview_open_limit" class="col-sm-2 control-label">{{ __( 'Conversations to open' ) }}</label>

	<div class="col-sm-6">
		<input id="wporgpluginreview_open_limit" type="number" class="form-control wporg-open-limit" name="wporgpluginreview_open_limit" value="{{ $limit }}" min="1" max="{{ $max }}" step="1">
		<p class="block-help">{{ __( 'How many conversations the Open buttons of My Reviews and My Cases open in new tabs at a time, up to :max.', array( 'max' => $max ) ) }}</p>
	</div>
</div>

<form method="POST" action="{{ route( 'wporgfreescoutimport.settings' ) }}" class="form-horizontal margin-top">
	{{ csrf_field() }}
	<div class="form-group">
		<label for="wporgfreescoutimport-url" class="col-sm-2 control-label">{{ __('Address') }}</label>
		<div class="col-sm-6">
			<input type="url" id="wporgfreescoutimport-url" name="url" class="form-control" value="{{ $url }}" placeholder="https://scout.wc-us.org" required>
		</div>
	</div>
	<div class="form-group">
		<label for="wporgfreescoutimport-api-key" class="col-sm-2 control-label">{{ __('API key') }}</label>
		<div class="col-sm-6">
			<input type="password" id="wporgfreescoutimport-api-key" name="api_key" class="form-control" autocomplete="off" @if ( $has_key ) placeholder="{{ __('Saved; leave empty to keep it') }}" @else required @endif>
			<p class="help-block">{{ __('The global API key, under Manage » API & Webhooks there. It reads everything there, so it’s kept encrypted, and isn’t shown again; forget it after the last import.') }}</p>
		</div>
	</div>
	<div class="form-group">
		<div class="col-sm-offset-2 col-sm-6">
			<button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
		</div>
	</div>
</form>
@if ( $has_key )
	<form method="POST" action="{{ route( 'wporgfreescoutimport.settings.forget' ) }}" class="form-horizontal">
		{{ csrf_field() }}
		<div class="form-group">
			<div class="col-sm-offset-2 col-sm-6">
				<button type="submit" class="btn btn-link">{{ __('Forget the API key') }}</button>
			</div>
		</div>
	</form>
@endif

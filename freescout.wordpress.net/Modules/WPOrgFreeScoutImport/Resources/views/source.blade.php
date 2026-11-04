@php( $prefix = 'wporgfreescoutimport-source-' . ( $source ? $source->id : 'new' ) )
<form method="POST" action="{{ $action }}" class="form-horizontal margin-top text-left">
	{{ csrf_field() }}
	<div class="form-group">
		<label for="{{ $prefix }}-url" class="col-sm-3 control-label">{{ __('Address') }}</label>
		<div class="col-sm-8">
			<input type="url" id="{{ $prefix }}-url" name="url" class="form-control" value="{{ $source ? $source->url : '' }}" placeholder="https://scout.wc-us.org" required>
		</div>
	</div>
	<div class="form-group">
		<label for="{{ $prefix }}-name" class="col-sm-3 control-label">{{ __('Name') }}</label>
		<div class="col-sm-8">
			<input type="text" id="{{ $prefix }}-name" name="name" class="form-control" value="{{ $source ? $source->name : '' }}" placeholder="{{ __('Its address’s host, unless you name it') }}">
		</div>
	</div>
	<div class="form-group">
		<label for="{{ $prefix }}-api-key" class="col-sm-3 control-label">{{ __('API key') }}</label>
		<div class="col-sm-8">
			<input type="password" id="{{ $prefix }}-api-key" name="api_key" class="form-control" autocomplete="off" @if ( $source && $source->has_key() ) placeholder="{{ __('Saved; leave empty to keep it') }}" @else required @endif>
			<p class="help-block">{{ __('Its global API key, under Manage » API & Webhooks there. It reads everything there, so it’s kept encrypted, isn’t shown again, and is forgotten when the address changes to another host; forget it after the last import.') }}</p>
		</div>
	</div>
	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-8">
			<button type="submit" class="btn btn-primary">{{ $source ? __('Save') : __('Add') }}</button>
		</div>
	</div>
</form>

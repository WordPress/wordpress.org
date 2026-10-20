{{-- In the user's profile, like core's checkboxes: the choices of how conversations show. --}}
@foreach ( $choices as $choice )
	<div class="form-group">
		<label for="{{ $choice['field'] }}" class="col-sm-2 control-label">{{ $choice['label'] }}</label>

		<div class="col-sm-6">
			<div class="controls">
				{{-- Unchecked boxes aren't sent; this says the form had it. --}}
				<input type="hidden" name="{{ $choice['field'] }}" value="0">
				<label for="{{ $choice['field'] }}" class="checkbox inline plain">
					<input type="checkbox" name="{{ $choice['field'] }}" value="1" id="{{ $choice['field'] }}" @if ( old( $choice['field'], $choice['enabled'] ) ) checked="checked" @endif> <span class="text-help">{{ $choice['text'] }}</span>
				</label>
			</div>
			<p class="block-help">{{ $choice['help'] }}</p>
		</div>
	</div>
@endforeach

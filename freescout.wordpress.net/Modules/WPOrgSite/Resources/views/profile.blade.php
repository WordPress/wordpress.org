{{-- In the user's profile, like core's Time Format choices, with what each does behind an info icon, as core's fields have it; unlike core's, the icon opens on focus too, and reads out what it says. --}}
@foreach ( $choices as $choice )
	<div class="form-group">
		<label for="{{ $choice['field'] }}" class="col-sm-2 control-label">{{ $choice['label'] }}</label>

		<div class="col-sm-6">
			<div class="controls">
				{{-- Unchecked boxes aren't sent; this says the form had it. --}}
				<input type="hidden" name="{{ $choice['field'] }}" value="0">
				<label for="{{ $choice['field'] }}" class="checkbox inline plain">
					<input type="checkbox" name="{{ $choice['field'] }}" value="1" id="{{ $choice['field'] }}" @if ( old( $choice['field'], $choice['enabled'] ) ) checked="checked" @endif> {{ $choice['text'] }}
				{{-- No space before the icon: the label's padding is the gap, as wide as core's before theirs. --}}
				</label><i class="glyphicon glyphicon-info-sign icon-info icon-info-inline wporgsite-choice-info" tabindex="0" role="img" aria-label="{{ $choice['help'] }}" data-toggle="popover" data-trigger="hover focus" data-html="true" data-placement="left" data-title="{{ $choice['label'] }}" data-content="{{ $choice['help'] }}"></i>
			</div>
		</div>
	</div>
@endforeach

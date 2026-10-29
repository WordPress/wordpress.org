{{-- A copy of the conversation's latest note, above its threads, for agents who pin it in their profile. --}}
<div class="wporgsite-pinned-note" id="wporgsite-pinned-note" role="note" aria-labelledby="wporgsite-pinned-note-title">
	<div class="wporgsite-pinned-note-header">
		<span class="wporgsite-pinned-note-title" id="wporgsite-pinned-note-title"><i class="glyphicon glyphicon-pushpin" aria-hidden="true"></i> {{ __( 'Latest note' ) }}</span>
		@if ( $author )
			<span class="wporgsite-pinned-note-author">{{ $author }}</span>
		@endif
		<span class="wporgsite-pinned-note-date" data-toggle="tooltip" title="{{ $date }}">{{ $ago }}</span>
		<a href="#thread-{{ $note->id }}" class="wporgsite-pinned-note-link">{{ __( 'Go to the note' ) }} <i class="glyphicon glyphicon-arrow-down" aria-hidden="true"></i></a>
	</div>
	<div class="wporgsite-pinned-note-body thread-content" dir="auto">{!! $body !!}</div>
	@if ( count( $attachments ) )
		<ul class="wporgsite-pinned-note-attachments">
			@foreach ( $attachments as $attachment )
				<li><i class="glyphicon glyphicon-paperclip" aria-hidden="true"></i> <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener">{{ $attachment['name'] }}</a></li>
			@endforeach
		</ul>
	@endif
</div>

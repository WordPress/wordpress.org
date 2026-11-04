{{-- What the page's script needs to mark the subjects of the conversation, its tab's title, and its previous conversations. --}}
<span class="wporg-subjects" hidden data-conversation-id="{{ $conversation_id }}" data-number="{{ $number }}" data-subject="{{ $subject }}" data-short-subject="{{ $short_subject }}" data-subjects="{{ json_encode( (object) $subjects ) }}"></span>

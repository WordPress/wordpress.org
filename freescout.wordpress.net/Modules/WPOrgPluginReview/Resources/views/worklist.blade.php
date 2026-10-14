@extends('layouts.app')

@php
	$name         = $user->getFullName();
	$headings     = $list->headings( $is_viewer ? null : $name );
	$descriptions = $list->descriptions( $name );
	$title        = $folder->getTypeName() . ' - ' . $mailbox->name;
@endphp

@section('title', $folder->active_count ? '(' . (int) $folder->active_count . ') ' . $title : $title)

@section('body_attrs')@parent data-mailbox_id="{{ $mailbox->id }}"@endsection

@section('sidebar')
	@include('partials/sidebar_menu_toggle')
	@include('mailboxes/sidebar_menu_view')
@endsection

@section('content')
<div class="wporg-worklist" data-worklist="{{ $list->key }}">
	<form class="wporg-worklist-toolbar" method="GET" action="{{ $list->url( (int) $mailbox->id ) }}">
		<label for="wporg-worklist-user">{{ $list->member_label() }}</label>
		<select id="wporg-worklist-user" name="user" class="form-control input-sm wporg-worklist-filter">
			@foreach ( $reviewers as $reviewer )
				<option value="{{ $reviewer->id }}" @if ( (int) $reviewer->id === (int) $user->id ) selected @endif>{{ $reviewer->getFullName() }}</option>
			@endforeach
		</select>
		@if ( $tags )
			<label for="wporg-worklist-tag">{{ __( 'Tag' ) }}</label>
			<select id="wporg-worklist-tag" name="tag" class="form-control input-sm wporg-worklist-filter">
				<option value="">{{ __( 'All tags' ) }}</option>
				@foreach ( $tags as $tag_id => $tag_option )
					<option value="{{ $tag_id }}" @if ( (int) $tag_id === (int) $tag ) selected @endif>{{ $tag_option['name'] }} ({{ $tag_option['count'] }})</option>
				@endforeach
			</select>
		@endif
		<noscript><button type="submit" class="btn btn-default btn-sm">{{ __( 'Show' ) }}</button></noscript>
		<span class="wporg-worklist-scope text-help">{{ __( 'Open and pending conversations assigned to :team.', array( 'team' => $list->team ) ) }}</span>
	</form>

	@foreach ( $sections as $section => $conversations )
		@php
			// Conversations someone is participating in can be changed together, with FreeScout's own bulk actions.
			$selectable = 'participating' === $section && $conversations;
		@endphp
		<section class="wporg-worklist-section" data-section="{{ $section }}" aria-labelledby="wporg-worklist-{{ $section }}">
			<h2 class="wporg-worklist-heading" id="wporg-worklist-{{ $section }}">
				{{ $headings[ $section ] }}
				<span class="wporg-worklist-count">{{ count( $conversations ) }}</span>
			</h2>
			<p class="wporg-worklist-description text-help">{{ $descriptions[ $section ] }}</p>

			@if ( $conversations )
				@if ( $selectable )
					@include('conversations/partials/bulk_actions', array( 'mailbox' => $mailbox ))
				@endif
				<table class="table wporg-worklist-table">
					<thead>
						<tr>
							@if ( $selectable )
								<th class="conv-cb wporg-worklist-cb"><input type="checkbox" class="toggle-all magic-checkbox" id="toggle-all"><label for="toggle-all"><span class="sr-only">{{ __( 'Select All Conversations' ) }}</span></label></th>
							@endif
							<th class="wporg-worklist-sender">{{ __( 'Sender' ) }}</th>
							<th class="wporg-worklist-subject">{{ __( 'Conversation' ) }}</th>
							<th class="wporg-worklist-threads">{{ __( 'Threads' ) }}</th>
							<th class="wporg-worklist-date">{{ __( 'Waiting Since' ) }}</th>
						</tr>
					</thead>
					<tbody>
						@foreach ( $conversations as $conversation )
							@php
								$url = $conversation->url();
							@endphp
							{{-- conv-row and conv-subject let the Tags module show tags added in bulk. --}}
							<tr class="@if ( $selectable ) conv-row @endif @if ( $conversation->isActive() ) is-active @endif" data-conversation_id="{{ $conversation->id }}">
								@if ( $selectable )
									<td class="conv-cb wporg-worklist-cb">
										<input type="checkbox" class="conv-checkbox magic-checkbox" id="cb-{{ $conversation->id }}" name="cb_{{ $conversation->id }}" value="{{ $conversation->id }}"><label for="cb-{{ $conversation->id }}"><span class="sr-only">{{ __( 'Select Conversation' ) }}: {{ $conversation->getSubject() }}</span></label>
									</td>
								@endif
								<td class="wporg-worklist-sender">
									<a href="{{ $url }}" title="{{ $conversation->customer_email }}">
										<span class="wporg-worklist-name">@if ( $conversation->customer ){{ $conversation->customer->getFullName( true ) }}@endif</span>
										<span class="wporg-worklist-email">{{ $conversation->customer_email }}</span>
									</a>
								</td>
								<td class="conv-subject wporg-worklist-subject">
									<a href="{{ $url }}">
										<p class="wporg-worklist-title">
											@if ( ! $conversation->isActive() )<span class="wporg-worklist-status">{{ $conversation->getStatusName() }}</span>@endif
											@action('conversations_table.before_subject', $conversation){{ $conversation->getSubject() }}@action('conversations_table.after_subject', $conversation)
										</p>
										<p class="wporg-worklist-preview">{{ $conversation->preview }}</p>
									</a>
								</td>
								<td class="wporg-worklist-threads">
									<a href="{{ $url }}"><span class="wporg-worklist-threads-count">{{ (int) $conversation->threads_count }}</span></a>
								</td>
								<td class="wporg-worklist-date">
									<a href="{{ $url }}" data-toggle="tooltip" data-html="true" data-placement="left" title="{{ $conversation->getDateTitle() }}">{{ $conversation->getWaitingSince( $folder ) }}</a>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			@else
				<p class="wporg-worklist-empty">{{ $list->empty_text() }}</p>
			@endif
		</section>
	@endforeach
</div>
@endsection

@extends('layouts.app')

@php
	$name     = $user->getFullName();
	$headings = $is_viewer
		? array(
			'participating'  => __( 'Reviews I’m participating in' ),
			'mentioned'      => __( 'Reviews I’m mentioned in' ),
			'needs_reviewer' => __( 'Reviews that need a reviewer' ),
		)
		: array(
			'participating'  => __( 'Reviews :name is participating in', array( 'name' => $name ) ),
			'mentioned'      => __( 'Reviews :name is mentioned in', array( 'name' => $name ) ),
			'needs_reviewer' => __( 'Reviews that need a reviewer' ),
		);
	$descriptions = array(
		'participating'  => __( 'With a reply from :name.', array( 'name' => $name ) ),
		'mentioned'      => __( 'With a note that mentions :name.', array( 'name' => $name ) ),
		'needs_reviewer' => __( 'With :marker in a note.', array( 'marker' => \Modules\WPOrgPluginReview\Services\MyReviews::NEEDS_REVIEWER ) ),
	);
	$title = $folder->getTypeName() . ' - ' . $mailbox->name;
@endphp

@section('title', $folder->active_count ? '(' . (int) $folder->active_count . ') ' . $title : $title)

@section('body_attrs')@parent data-mailbox_id="{{ $mailbox->id }}"@endsection

@section('sidebar')
	@include('partials/sidebar_menu_toggle')
	@include('mailboxes/sidebar_menu_view')
@endsection

@section('content')
<div class="wporg-my-reviews">
	<form class="wporg-my-reviews-toolbar" method="GET" action="{{ route( 'wporgpluginreview.my_reviews', array( 'mailbox_id' => $mailbox->id ) ) }}">
		<label for="wporg-my-reviews-user">{{ __( 'Reviewer' ) }}</label>
		<select id="wporg-my-reviews-user" name="user" class="form-control input-sm wporg-my-reviews-user">
			@foreach ( $reviewers as $reviewer )
				<option value="{{ $reviewer->id }}" @if ( (int) $reviewer->id === (int) $user->id ) selected @endif>{{ $reviewer->getFullName() }}</option>
			@endforeach
		</select>
		<noscript><button type="submit" class="btn btn-default btn-sm">{{ __( 'Show' ) }}</button></noscript>
		<span class="wporg-my-reviews-scope text-help">{{ __( 'Open and pending conversations assigned to :team.', array( 'team' => \Modules\WPOrgPluginReview\Services\Reviewers::REVIEW_TEAM ) ) }}</span>
	</form>

	@foreach ( $sections as $section => $conversations )
		@php
			// Reviews someone is participating in can be changed together, with FreeScout's own bulk actions.
			$selectable = 'participating' === $section && $conversations;
		@endphp
		<section class="wporg-my-reviews-section" data-section="{{ $section }}" aria-labelledby="wporg-my-reviews-{{ $section }}">
			<h2 class="wporg-my-reviews-heading" id="wporg-my-reviews-{{ $section }}">
				{{ $headings[ $section ] }}
				<span class="wporg-my-reviews-count">{{ count( $conversations ) }}</span>
			</h2>
			<p class="wporg-my-reviews-description text-help">{{ $descriptions[ $section ] }}</p>

			@if ( $conversations )
				@if ( $selectable )
					@include('conversations/partials/bulk_actions', array( 'mailbox' => $mailbox ))
				@endif
				<table class="table wporg-my-reviews-table">
					<thead>
						<tr>
							@if ( $selectable )
								<th class="conv-cb wporg-my-reviews-cb"><input type="checkbox" class="toggle-all magic-checkbox" id="toggle-all"><label for="toggle-all"><span class="sr-only">{{ __( 'Select All Conversations' ) }}</span></label></th>
							@endif
							<th class="wporg-my-reviews-sender">{{ __( 'Sender' ) }}</th>
							<th class="wporg-my-reviews-subject">{{ __( 'Conversation' ) }}</th>
							<th class="wporg-my-reviews-threads">{{ __( 'Threads' ) }}</th>
							<th class="wporg-my-reviews-date">{{ __( 'Waiting Since' ) }}</th>
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
									<td class="conv-cb wporg-my-reviews-cb">
										<input type="checkbox" class="conv-checkbox magic-checkbox" id="cb-{{ $conversation->id }}" name="cb_{{ $conversation->id }}" value="{{ $conversation->id }}"><label for="cb-{{ $conversation->id }}"><span class="sr-only">{{ __( 'Select Conversation' ) }}: {{ $conversation->getSubject() }}</span></label>
									</td>
								@endif
								<td class="wporg-my-reviews-sender">
									<a href="{{ $url }}" title="{{ $conversation->customer_email }}">
										<span class="wporg-my-reviews-name">@if ( $conversation->customer ){{ $conversation->customer->getFullName( true ) }}@endif</span>
										<span class="wporg-my-reviews-email">{{ $conversation->customer_email }}</span>
									</a>
								</td>
								<td class="conv-subject wporg-my-reviews-subject">
									<a href="{{ $url }}">
										<p class="wporg-my-reviews-title">
											@if ( ! $conversation->isActive() )<span class="wporg-my-reviews-status">{{ $conversation->getStatusName() }}</span>@endif
											@action('conversations_table.before_subject', $conversation){{ $conversation->getSubject() }}@action('conversations_table.after_subject', $conversation)
										</p>
										<p class="wporg-my-reviews-preview">{{ $conversation->preview }}</p>
									</a>
								</td>
								<td class="wporg-my-reviews-threads">
									<a href="{{ $url }}"><span class="wporg-my-reviews-threads-count">{{ (int) $conversation->threads_count }}</span></a>
								</td>
								<td class="wporg-my-reviews-date">
									<a href="{{ $url }}" data-toggle="tooltip" data-html="true" data-placement="left" title="{{ $conversation->getDateTitle() }}">{{ $conversation->getWaitingSince( $folder ) }}</a>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			@else
				<p class="wporg-my-reviews-empty">{{ __( 'No reviews.' ) }}</p>
			@endif
		</section>
	@endforeach
</div>
@endsection

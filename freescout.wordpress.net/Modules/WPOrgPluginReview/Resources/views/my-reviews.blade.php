@extends('layouts.app')

@php
	$name     = $user->getFullName();
	$headings = $is_viewer
		? array(
			'participating'  => __( 'Conversations I’m participating in' ),
			'mentioned'      => __( 'Conversations I’m mentioned in' ),
			'needs_reviewer' => __( 'Conversations that need a reviewer' ),
		)
		: array(
			'participating'  => __( 'Conversations :name is participating in', array( 'name' => $name ) ),
			'mentioned'      => __( 'Conversations :name is mentioned in', array( 'name' => $name ) ),
			'needs_reviewer' => __( 'Conversations that need a reviewer' ),
		);
	$descriptions = array(
		'participating'  => __( 'With a reply from :name.', array( 'name' => $name ) ),
		'mentioned'      => __( 'With a note that mentions :name.', array( 'name' => $name ) ),
		'needs_reviewer' => __( 'With :marker in a note.', array( 'marker' => \Modules\WPOrgPluginReview\Services\MyReviews::NEEDS_REVIEWER ) ),
	);
@endphp

@section('title', $folder->getTypeName() . ' - ' . $mailbox->name)

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
		<section class="wporg-my-reviews-section" data-section="{{ $section }}" aria-labelledby="wporg-my-reviews-{{ $section }}">
			<h2 class="wporg-my-reviews-heading" id="wporg-my-reviews-{{ $section }}">
				{{ $headings[ $section ] }}
				<span class="wporg-my-reviews-count">{{ count( $conversations ) }}</span>
			</h2>
			<p class="wporg-my-reviews-description text-help">{{ $descriptions[ $section ] }}</p>

			@if ( $conversations )
				<table class="table wporg-my-reviews-table">
					<thead>
						<tr>
							<th class="wporg-my-reviews-sender">{{ __( 'Sender' ) }}</th>
							<th class="wporg-my-reviews-subject">{{ __( 'Conversation' ) }}</th>
							<th class="wporg-my-reviews-number">{{ __( 'Number' ) }}</th>
							<th class="wporg-my-reviews-date">{{ __( 'Waiting Since' ) }}</th>
						</tr>
					</thead>
					<tbody>
						@foreach ( $conversations as $conversation )
							@php
								$url = $conversation->url();
							@endphp
							<tr class="@if ( $conversation->isActive() ) is-active @endif" data-conversation_id="{{ $conversation->id }}">
								<td class="wporg-my-reviews-sender">
									<a href="{{ $url }}" title="{{ $conversation->customer_email }}">
										<span class="wporg-my-reviews-name">@if ( $conversation->customer ){{ $conversation->customer->getFullName( true ) }}@endif</span>
										<span class="wporg-my-reviews-email">{{ $conversation->customer_email }}</span>
									</a>
								</td>
								<td class="wporg-my-reviews-subject">
									<a href="{{ $url }}">
										<span class="wporg-my-reviews-title">
											@if ( ! $conversation->isActive() )<span class="wporg-my-reviews-status">{{ $conversation->getStatusName() }}</span>@endif
											{{ $conversation->getSubject() }}
										</span>
										<span class="wporg-my-reviews-preview">{{ $conversation->preview }}</span>
									</a>
								</td>
								<td class="wporg-my-reviews-number">
									<a href="{{ $url }}"><i>#</i>{{ $conversation->number }}</a>
								</td>
								<td class="wporg-my-reviews-date">
									<a href="{{ $url }}" data-toggle="tooltip" data-html="true" data-placement="left" title="{{ $conversation->getDateTitle() }}">{{ $conversation->getWaitingSince( $folder ) }}</a>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			@else
				<p class="wporg-my-reviews-empty">{{ __( 'No conversations.' ) }}</p>
			@endif
		</section>
	@endforeach
</div>
@endsection

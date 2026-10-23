@extends('layouts.app')

@section('title', __('Connect to WordPress.org accounts'))

@section('content')
@php
	$actions = [
		'use'     => __('Credit to the user connected to it'),
		'connect' => __('Connect this user'),
		'create'  => __('Create a user, connected to it'),
		'done'    => __('Connected already'),
	];
	$ready      = count( array_filter( $plan, function ( $step ) { return $step['action'] && 'done' !== $step['action']; } ) );
	$mismatched = count( array_filter( $plan, function ( $step ) { return $step['action'] && 'done' !== $step['action'] && $step['mismatch']; } ) );
@endphp
<div class="container">
	<div class="flexy-container">
		<div class="flexy-item">
			<span class="heading">{{ __('Connect to WordPress.org accounts') }}</span>
		</div>
		<div class="flexy-block"></div>
		<div class="flexy-item">
			<a href="{{ route( 'wporgfreescoutimport.users' ) }}" class="btn btn-bordered">{{ __('Users on :name', [ 'name' => $name ]) }}</a>
		</div>
	</div>

	<div class="margin-top">
		<p>{{ __('Nothing is changed yet. Check that each WordPress.org account is the right person, then connect them. Rows with a problem are left out.') }}</p>
		<p>{{ __('Connecting lets the account’s owner log in as the user here. Rows whose account has neither the user’s name nor email there are highlighted, and only connected if you check their boxes.') }}</p>

		<table class="table">
			<thead>
				<tr>
					<th>{{ __('User on :name', [ 'name' => $name ]) }}</th>
					<th>{{ __('WordPress.org account') }}</th>
					<th>{{ __('User here') }}</th>
					<th>{{ __('What happens') }}</th>
				</tr>
			</thead>
			<tbody>
				@foreach ( $plan as $step )
					<tr @if ( $step['error'] ) class="danger" @elseif ( $step['mismatch'] ) class="warning" @endif>
						<td>
							@if ( $step['source_user'] )
								{{ trim( ( $step['source_user']['firstName'] ?? '' ) . ' ' . ( $step['source_user']['lastName'] ?? '' ) ) }}<br/>
								<small>{{ $step['source_user']['email'] ?? '' }}</small>
							@else
								{{ $step['row']['source_id'] ?: $step['row']['email'] }}
							@endif
						</td>
						<td>
							@if ( $step['wporg_user'] )
								<a href="{{ $step['wporg_user']->profile_url }}" target="_blank" rel="noopener noreferrer">{{ $step['wporg_user']->username }}</a><br/>
								<small>{{ trim( $step['wporg_user']->first_name . ' ' . $step['wporg_user']->last_name ) }} &lt;{{ $step['wporg_user']->email }}&gt;</small>
								@if ( $step['wporg_user']->blocked )
									<br/><small class="text-danger">{{ __('Blocked on WordPress.org: can’t log in') }}</small>
								@endif
							@else
								{{ $step['row']['username'] }}
							@endif
						</td>
						<td>
							@if ( $step['user'] )
								{{ $step['user']->getFullName() }}<br/><small>{{ $step['user']->email }}</small>
							@endif
						</td>
						<td>
							@if ( $step['error'] )
								<strong class="text-danger">{{ $step['error'] }}</strong>
							@else
								{{ $actions[ $step['action'] ] }}
								@if ( $step['mismatch'] )
									<br/><label class="checkbox-inline"><input type="checkbox" name="confirmed[]" value="{{ $step['source_user']['id'] }}" form="wporgfreescoutimport-connect"> <strong>{{ __('Not their name or email: connect anyway') }}</strong></label>
								@endif
							@endif
						</td>
					</tr>
				@endforeach
			</tbody>
		</table>

		@if ( $ready )
			<form method="POST" action="{{ route( 'wporgfreescoutimport.users.connect' ) }}" id="wporgfreescoutimport-connect">
				{{ csrf_field() }}
				<input type="hidden" name="csv" value="{{ $csv }}">
				<input type="hidden" name="apply" value="1">
				<button type="submit" class="btn btn-primary">{{ __('Connect :count users', [ 'count' => $ready ]) }}</button>
				@if ( $mismatched )
					<span class="text-help">{{ __(':count of them only if their boxes are checked.', [ 'count' => $mismatched ]) }}</span>
				@endif
				<a href="{{ route( 'wporgfreescoutimport.users' ) }}" class="btn btn-link">{{ __('Cancel') }}</a>
			</form>
		@else
			<p><strong>{{ __('Nothing to connect.') }}</strong></p>
		@endif
	</div>
</div>
@endsection

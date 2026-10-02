@extends('layouts.app')

@section('title', __('Connect to WordPress.org accounts'))

@section('content')
@php
	$actions = [
		'use'     => __('Credit to the FreeScout user connected to it'),
		'connect' => __('Connect this FreeScout user'),
		'create'  => __('Create a FreeScout user, connected to it'),
		'done'    => __('Connected already'),
	];
	$ready = count( array_filter( $plan, function ( $step ) { return $step['action'] && 'done' !== $step['action']; } ) );
@endphp
<div class="container">
	<div class="flexy-container">
		<div class="flexy-item">
			<span class="heading">{{ __('Connect to WordPress.org accounts') }}</span>
		</div>
		<div class="flexy-block"></div>
		<div class="flexy-item">
			<a href="{{ route( 'wporghelpscoutimport.agents' ) }}" class="btn btn-bordered">{{ __('HelpScout Users') }}</a>
		</div>
	</div>

	<div class="margin-top">
		<p>{{ __('Nothing is changed yet. Check that each WordPress.org account is the right person, then connect them. Rows with a problem are left out.') }}</p>

		<table class="table">
			<thead>
				<tr>
					<th>{{ __('HelpScout user') }}</th>
					<th>{{ __('WordPress.org account') }}</th>
					<th>{{ __('FreeScout user') }}</th>
					<th>{{ __('What happens') }}</th>
				</tr>
			</thead>
			<tbody>
				@foreach ( $plan as $step )
					<tr @if ( $step['error'] ) class="danger" @endif>
						<td>
							@if ( $step['helpscout_user'] )
								{{ trim( ( $step['helpscout_user']['firstName'] ?? '' ) . ' ' . ( $step['helpscout_user']['lastName'] ?? '' ) ) }}<br/>
								<small>{{ $step['helpscout_user']['email'] ?? '' }}</small>
							@else
								{{ $step['row']['helpscout_id'] ?: $step['row']['email'] }}
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
							@endif
						</td>
					</tr>
				@endforeach
			</tbody>
		</table>

		@if ( $ready )
			<form method="POST" action="{{ route( 'wporghelpscoutimport.agents.connect' ) }}">
				{{ csrf_field() }}
				<input type="hidden" name="csv" value="{{ $csv }}">
				<input type="hidden" name="apply" value="1">
				<button type="submit" class="btn btn-primary">{{ __('Connect :count users', [ 'count' => $ready ]) }}</button>
				<a href="{{ route( 'wporghelpscoutimport.agents' ) }}" class="btn btn-link">{{ __('Cancel') }}</a>
			</form>
		@else
			<p><strong>{{ __('Nothing to connect.') }}</strong></p>
		@endif
	</div>
</div>
@endsection

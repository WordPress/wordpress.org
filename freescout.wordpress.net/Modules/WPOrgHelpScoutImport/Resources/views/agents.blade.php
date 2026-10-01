@extends('layouts.app')

@section('title', __('HelpScout Agents'))

@section('content')
<div class="container">
	<div class="flexy-container">
		<div class="flexy-item">
			<span class="heading">{{ __('HelpScout Agents') }}</span>
		</div>
		<div class="flexy-block"></div>
		<div class="flexy-item">
			<a href="{{ route( 'wporghelpscoutimport.index' ) }}" class="btn btn-bordered">{{ __('HelpScout Import') }}</a>
		</div>
	</div>

	<div class="margin-top">
		@include('partials/flash_messages')

		<div class="alert alert-warning">
			<p><strong>{{ __('FreeScout users are never created from HelpScout.') }}</strong></p>
			<p>{{ __('Each HelpScout user’s replies and notes are credited to the FreeScout user chosen here, or else to the one with the same email. A HelpScout user without either gets no FreeScout user: their replies and notes are credited to “HelpScout Import”, and imported conversations keep that credit.') }}</p>
			@if ( $can_create )
				<p>{{ __('For someone with no FreeScout user yet, enter their WordPress.org username: that creates one, connected to their account. It’s disabled unless they’ll work in FreeScout, so former agents keep their credit without being able to log in.') }}</p>
			@else
				<p>{{ __('New FreeScout users can only be created from a WordPress.org username, which needs WP.org SSO to be on.') }}</p>
			@endif
		</div>

		@if ( $error )
			<div class="alert alert-danger">{{ __('HelpScout couldn’t be reached: :error', [ 'error' => $error ]) }}</div>
		@endif

		<div class="flexy-container margin-bottom">
			<form method="GET" action="{{ route( 'wporghelpscoutimport.agents' ) }}" class="form-inline flexy-item">
				<select name="mailbox" class="form-control" aria-label="{{ __('HelpScout mailbox') }}">
					<option value="">{{ __('Every mailbox') }}</option>
					@foreach ( $mailboxes as $id => $name )
						<option value="{{ $id }}" @if ( $mailbox_id === (int) $id ) selected @endif>{{ $name }}</option>
					@endforeach
				</select>
				<button type="submit" class="btn btn-default">{{ __('Show') }}</button>
			</form>
			<div class="flexy-block"></div>
			<form method="POST" action="{{ route( 'wporghelpscoutimport.agents.refresh' ) }}" class="flexy-item">
				{{ csrf_field() }}
				<input type="hidden" name="mailbox" value="{{ $mailbox_id ?: '' }}">
				<button type="submit" class="btn btn-default">{{ __('Ask HelpScout again') }}</button>
			</form>
		</div>

		@if ( $agents )
			<p>
				@if ( $unmatched )
					<strong class="text-danger">{{ __(':count of :total HelpScout users have no FreeScout user.', [ 'count' => $unmatched, 'total' => count( $agents ) ]) }}</strong>
				@else
					<strong class="text-success">{{ __('All :total HelpScout users have a FreeScout user.', [ 'total' => count( $agents ) ]) }}</strong>
				@endif
			</p>

			<form method="POST" action="{{ route( 'wporghelpscoutimport.agents.save' ) }}">
				{{ csrf_field() }}
				<input type="hidden" name="mailbox" value="{{ $mailbox_id ?: '' }}">

				<table class="table">
					<thead>
						<tr>
							<th>{{ __('HelpScout user') }}</th>
							<th>{{ __('Credited to') }}</th>
							<th>{{ __('Choose a FreeScout user') }}</th>
							@if ( $can_create )
								<th>{{ __('Or create one from WordPress.org') }}</th>
							@endif
						</tr>
					</thead>
					<tbody>
						@foreach ( $agents as $agent )
							@php
								$unmatched_row = ! $agent['chosen'] && ! $agent['by_email'];
								$selected      = $agent['chosen'] ?: $agent['suggested'];
							@endphp
							<tr id="agent-{{ $agent['id'] }}" @if ( $unmatched_row ) class="{{ $agent['suggested'] ? 'warning' : 'danger' }}" @endif>
								<td>
									{{ $agent['name'] }}<br/>
									<small>{{ $agent['email'] }}</small><br/>
									<small class="text-help">{{ implode( ', ', $agent['mailboxes'] ) }}</small>
								</td>
								<td>
									@if ( $agent['chosen'] )
										{{ $agent['chosen']->getFullName() }} <small class="text-help">({{ __('chosen') }})</small>
									@elseif ( $agent['by_email'] )
										{{ $agent['by_email']->getFullName() }} <small class="text-help">({{ __('same email') }})</small>
									@else
										<strong>{{ __('HelpScout Import') }}</strong> <small>({{ __('no FreeScout user') }})</small>
										@if ( $agent['suggested'] )
											<br/><small>{{ __('Suggested: :name, with the same name. Save to use them.', [ 'name' => $agent['suggested']->getFullName() ]) }}</small>
										@endif
									@endif
								</td>
								<td>
									<select name="agents[{{ $agent['id'] }}][user_id]" class="form-control input-sm wporghelpscoutimport-user" aria-label="{{ __('FreeScout user for :name', [ 'name' => $agent['name'] ]) }}">
										<option value="">{{ $agent['by_email'] ? __('Same email: :name', [ 'name' => $agent['by_email']->getFullName() ]) : __('None: HelpScout Import') }}</option>
										@foreach ( $users as $user )
											<option value="{{ $user->id }}" @if ( $selected && (int) $selected->id === (int) $user->id ) selected @endif>{{ $user->getFullName() }} &lt;{{ $user->email }}&gt;</option>
										@endforeach
									</select>
								</td>
								@if ( $can_create )
									<td>
										@if ( $unmatched_row )
											<input type="text" name="agents[{{ $agent['id'] }}][username]" class="form-control input-sm" placeholder="{{ __('WordPress.org username') }}" aria-label="{{ __('WordPress.org username of :name', [ 'name' => $agent['name'] ]) }}">
											<label class="checkbox-inline"><input type="checkbox" name="agents[{{ $agent['id'] }}][can_log_in]" value="1"> {{ __('Can log in') }}</label>
										@endif
									</td>
								@endif
							</tr>
						@endforeach
					</tbody>
				</table>

				<button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
			</form>
		@endif

		@if ( $teams )
			<h3 class="margin-top">{{ __('HelpScout teams') }}</h3>
			<p>{{ __('HelpScout lists its teams with its users, but they aren’t people and never write anything: conversations are assigned to them. Conversations assigned to a team are imported unassigned.') }}</p>
			<p class="text-help">{{ implode( ', ', $teams ) }}</p>
		@endif
	</div>
</div>
@endsection

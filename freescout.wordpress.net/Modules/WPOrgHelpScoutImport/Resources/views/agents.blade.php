@extends('layouts.app')

@section('title', __('HelpScout Users'))

@section('content')
@php
	$how = [
		'chosen' => __('chosen'),
		'email'  => __('same email'),
	];
@endphp
<div class="container">
	<div class="flexy-container">
		<div class="flexy-item">
			<span class="heading">{{ __('HelpScout Users') }}</span>
		</div>
		<div class="flexy-block"></div>
		<div class="flexy-item">
			<a href="{{ route( 'wporghelpscoutimport.index' ) }}" class="btn btn-bordered">{{ __('HelpScout Import') }}</a>
		</div>
	</div>

	<div class="margin-top">
		@include('partials/flash_messages')

		<p>{{ __('Each HelpScout user’s replies, notes, and assignments are credited to a FreeScout user: the one chosen here, or else the one with the same email. Importing a mailbox creates a FreeScout user for each of its HelpScout users without either, with access to the mailbox; users HelpScout no longer has get a disabled one.') }}</p>
		<p>{{ __('Choose a user here for someone who already has a FreeScout user under another email, before importing their mailboxes: FreeScout can’t merge users. Choosing someone after an import credits what’s imported to them too.') }}</p>

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

		<div class="panel panel-default">
			<div class="panel-body">
				<h4>{{ __('Connect to WordPress.org accounts') }}</h4>
				<p>{{ __('Download HelpScout’s users, fill in the wporg_username column, and check it here. Each one’s FreeScout user is connected to that account, created if they have none yet: their name, email, and avatar come from WordPress.org, and they can log in with it. Someone whose account is connected to a FreeScout user already is credited to that user.') }}</p>
				<p><a href="{{ route( 'wporghelpscoutimport.agents.export' ) }}" class="btn btn-default">{{ __('Download CSV') }}</a></p>
				@if ( $can_connect )
					<form method="POST" action="{{ route( 'wporghelpscoutimport.agents.connect' ) }}" enctype="multipart/form-data">
						{{ csrf_field() }}
						<div class="form-group">
							<label for="wporghelpscoutimport-csv-file">{{ __('Filled-in CSV') }}</label>
							<input type="file" id="wporghelpscoutimport-csv-file" name="csv_file" accept=".csv,text/csv">
						</div>
						<div class="form-group">
							<label for="wporghelpscoutimport-csv">{{ __('Or paste it') }}</label>
							<textarea id="wporghelpscoutimport-csv" name="csv" class="form-control" rows="4" placeholder="helpscout_id,…,wporg_username"></textarea>
						</div>
						<button type="submit" class="btn btn-default">{{ __('Check') }}</button>
					</form>
				@else
					<p class="text-help">{{ __('Connecting needs WP.org SSO to be on.') }}</p>
				@endif
			</div>
		</div>

		<form method="POST" action="{{ route( 'wporghelpscoutimport.agents.save' ) }}">
			{{ csrf_field() }}
			<input type="hidden" name="mailbox" value="{{ $mailbox_id ?: '' }}">

			@if ( $agents )
				<table class="table">
					<thead>
						<tr>
							<th>{{ __('HelpScout user') }}</th>
							<th>{{ __('FreeScout user') }}</th>
							<th>{{ __('Choose another') }}</th>
						</tr>
					</thead>
					<tbody>
						@foreach ( $agents as $agent )
							<tr id="agent-{{ $agent['id'] }}">
								<td>
									{{ $agent['name'] }}<br/>
									<small>{{ $agent['email'] }}</small><br/>
									@if ( $agent['former'] )
										<small class="text-help">{{ __('No longer in HelpScout') }}</small>
									@else
										<small class="text-help">{{ implode( ', ', $agent['mailboxes'] ) }}</small>
									@endif
								</td>
								<td>
									@if ( $agent['user'] )
										{{ $agent['user']->getFullName() }} <small class="text-help">&lt;{{ $agent['user']->email }}&gt; ({{ $how[ $agent['how'] ] }})</small>
										@if ( App\User::STATUS_ACTIVE !== (int) $agent['user']->status )
											<br/><small class="text-help">{{ __('Disabled') }}</small>
										@endif
									@else
										<em>{{ __('Created when their mailbox is imported') }}</em>
									@endif
								</td>
								<td>
									<select name="agents[{{ $agent['id'] }}]" class="form-control input-sm wporghelpscoutimport-user" aria-label="{{ __('FreeScout user for :name', [ 'name' => $agent['name'] ]) }}">
										<option value="">{{ __('Keep') }}</option>
										@foreach ( $users as $user )
											<option value="{{ $user->id }}">{{ $user->getFullName() }} &lt;{{ $user->email }}&gt;</option>
										@endforeach
									</select>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			@endif

			@if ( $teams )
				<h3 class="margin-top">{{ __('HelpScout teams') }}</h3>
				<p>{{ __('Conversations assigned to a HelpScout team are assigned to the FreeScout team chosen here, or else to the one with the same name; without either, they’re imported unassigned. FreeScout’s teams come from its Teams module: create them there first.') }}</p>
				@if ( ! $teams_module )
					<div class="alert alert-warning">{{ __('The Teams module is off, so there are no FreeScout teams: conversations assigned to teams are imported unassigned. Choosing teams once it’s on assigns them.') }}</div>
				@endif
				<table class="table">
					<thead>
						<tr>
							<th>{{ __('HelpScout team') }}</th>
							<th>{{ __('FreeScout team') }}</th>
							<th>{{ __('Choose another') }}</th>
						</tr>
					</thead>
					<tbody>
						@foreach ( $teams as $team )
							<tr id="team-{{ $team['id'] }}">
								<td>{{ $team['name'] }}</td>
								<td>
									@if ( $team['team'] )
										{{ $team['team']->getFullName() }} <small class="text-help">({{ $team['chosen'] ? __('chosen') : __('same name') }})</small>
									@else
										<em>{{ __('None: imported unassigned') }}</em>
									@endif
								</td>
								<td>
									@if ( count( $freescout_teams ) )
										<select name="teams[{{ $team['id'] }}]" class="form-control input-sm" aria-label="{{ __('FreeScout team for :name', [ 'name' => $team['name'] ]) }}">
											<option value="">{{ __('Keep') }}</option>
											@foreach ( $freescout_teams as $freescout_team )
												<option value="{{ $freescout_team->id }}">{{ $freescout_team->getFullName() }}</option>
											@endforeach
										</select>
									@endif
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			@endif

			@if ( $agents || $teams )
				<button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
			@endif
		</form>
	</div>
</div>
@endsection

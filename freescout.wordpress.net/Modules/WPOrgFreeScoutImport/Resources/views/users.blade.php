@extends('layouts.app')

@section('title', __('FreeScout Import Users'))

@section('content')
@php
	$how = [
		'chosen'  => __('chosen'),
		'created' => __('created by an import'),
		'email'   => __('same email'),
	];
@endphp
<div class="container">
	<div class="flexy-container">
		<div class="flexy-item">
			<span class="heading">{{ __('Users on :name', [ 'name' => $name ]) }}</span>
		</div>
		<div class="flexy-block"></div>
		<div class="flexy-item">
			<form method="POST" action="{{ route( 'wporgfreescoutimport.users.refresh' ) }}" class="pull-left">
				{{ csrf_field() }}
				<button type="submit" class="btn btn-bordered">{{ __('Ask :name again', [ 'name' => $name ]) }}</button>
			</form>
			<a href="{{ route( 'wporgfreescoutimport.index' ) }}" class="btn btn-bordered">{{ __('FreeScout Import') }}</a>
		</div>
	</div>

	<div class="margin-top">
		@include('partials/flash_messages')

		<p>{{ __('Each user’s replies, notes, and assignments are credited to a user here: the one chosen on this page, or else the one with the same email. An import creates a disabled user for anyone else, who keeps their name. Connect those who’ll work here to their WordPress.org accounts below: that switches them on, with access to the mailboxes their conversations are in.') }}</p>
		<p>{{ __('Choose a user here for someone who has one under another email, before importing their mailboxes: FreeScout can’t merge users. Choosing someone after an import credits what’s imported to them too.') }}</p>

		@if ( $error )
			<div class="alert alert-danger">{{ __(':name couldn’t be reached: :error', [ 'name' => $name, 'error' => $error ]) }}</div>
		@endif

		<div class="panel panel-default">
			<div class="panel-body">
				<h4>{{ __('Connect to WordPress.org accounts') }}</h4>
				<p>{{ __('Download :name’s users, fill in the wporg_username column for those who’ll work here, and check it here. Each one’s user here is connected to that account, created if they have none yet, and switched on if an import created them disabled: their name, email, and avatar come from WordPress.org, and they can log in with it. Someone whose account is connected to a user already is credited to that user.', [ 'name' => $name ]) }}</p>
				<p><a href="{{ route( 'wporgfreescoutimport.users.export' ) }}" class="btn btn-default">{{ __('Download CSV') }}</a></p>
				@if ( $can_connect )
					<form method="POST" action="{{ route( 'wporgfreescoutimport.users.connect' ) }}" enctype="multipart/form-data">
						{{ csrf_field() }}
						<div class="form-group">
							<label for="wporgfreescoutimport-csv-file">{{ __('Filled-in CSV') }}</label>
							<input type="file" id="wporgfreescoutimport-csv-file" name="csv_file" accept=".csv,text/csv">
						</div>
						<div class="form-group">
							<label for="wporgfreescoutimport-csv">{{ __('Or paste it') }}</label>
							<textarea id="wporgfreescoutimport-csv" name="csv" class="form-control" rows="4" placeholder="source_id,…,wporg_username"></textarea>
						</div>
						<button type="submit" class="btn btn-default">{{ __('Check') }}</button>
					</form>
				@else
					<p class="text-help">{{ __('Connecting needs WP.org SSO to be on.') }}</p>
				@endif
			</div>
		</div>

		@if ( $users )
			<form method="POST" action="{{ route( 'wporgfreescoutimport.users.save' ) }}">
				{{ csrf_field() }}
				<table class="table">
					<thead>
						<tr>
							<th>{{ __('User on :name', [ 'name' => $name ]) }}</th>
							<th>{{ __('User here') }}</th>
							<th>{{ __('Choose another') }}</th>
						</tr>
					</thead>
					<tbody>
						@foreach ( $users as $user )
							<tr id="user-{{ $user['id'] }}">
								<td>
									{{ $user['name'] }}<br/>
									<small>{{ $user['email'] }}</small>
								</td>
								<td>
									@if ( $user['existing'] )
										{{ $user['existing']['user']->getFullName() }} <small class="text-help">&lt;{{ $user['existing']['user']->email }}&gt; ({{ $how[ $user['existing']['how'] ] }})</small>
										@if ( App\User::STATUS_ACTIVE !== (int) $user['existing']['user']->status )
											<br/><small class="text-help">{{ __('Disabled') }}</small>
										@endif
									@else
										<em>{{ __('Created, disabled, when an import meets them') }}</em>
									@endif
								</td>
								<td>
									<select name="users[{{ $user['id'] }}]" class="form-control input-sm wporgfreescoutimport-user" aria-label="{{ __('User here for :name', [ 'name' => $user['name'] ]) }}">
										<option value="">{{ __('Keep') }}</option>
										@foreach ( $creditable as $option )
											<option value="{{ $option->id }}">{{ $option->getFullName() }} &lt;{{ $option->email }}&gt;</option>
										@endforeach
									</select>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
				<button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
			</form>
		@endif
	</div>
</div>
@endsection

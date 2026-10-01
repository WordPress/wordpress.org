@extends('layouts.app')

@section('title', __('HelpScout Import'))

@section('content')
<div class="container">
	<div class="flexy-container">
		<div class="flexy-item">
			<span class="heading">{{ __('HelpScout Import') }}</span>
		</div>
		<div class="flexy-block"></div>
		<div class="flexy-item">
			<a href="{{ route( 'wporghelpscoutimport.index' ) }}" class="btn btn-bordered">{{ __('Refresh') }}</a>
		</div>
	</div>

	<div class="margin-top">
		@include('partials/flash_messages')

		@if ( ! $configured )
			<div class="alert alert-warning">{{ __('Set WPORG_HELPSCOUT_APP_ID and WPORG_HELPSCOUT_APP_SECRET in FreeScout’s .env to import from HelpScout.') }}</div>
		@elseif ( $error )
			<div class="alert alert-danger">{{ __('HelpScout couldn’t be reached: :error', [ 'error' => $error ]) }}</div>
		@endif

		@if ( $sources )
			<p>{{ __('Imports copy a HelpScout mailbox’s conversations into a FreeScout mailbox, without sending anything. The first import copies everything; importing the same mailbox again copies only what changed since. Import before the mailbox’s email moves, and once more after.') }}</p>

			<form method="GET" action="{{ route( 'wporghelpscoutimport.index' ) }}" class="form-inline margin-bottom">
				<select name="agents" class="form-control">
					@foreach ( $sources as $source )
						<option value="{{ $source['id'] }}" @if ( $agents === (int) $source['id'] ) selected @endif>{{ $source['name'] }} &lt;{{ $source['email'] ?? '' }}&gt;</option>
					@endforeach
				</select>
				<button type="submit" class="btn btn-default">{{ __('Check agents') }}</button>
			</form>

			@if ( is_array( $people ) )
				<form method="POST" action="{{ route( 'wporghelpscoutimport.agents' ) }}" class="margin-bottom">
					{{ csrf_field() }}
					<input type="hidden" name="helpscout_mailbox_id" value="{{ $agents }}">
					<p>{{ __('Replies and notes are credited to the FreeScout user chosen here, or else to the one with the same email. Without either, they’re credited to “HelpScout Import”. Choose before importing: what’s imported keeps its credit.') }}</p>
					<table class="table table-condensed">
						<thead>
							<tr>
								<th>{{ __('HelpScout user') }}</th>
								<th>{{ __('FreeScout user') }}</th>
							</tr>
						</thead>
						<tbody>
							@foreach ( $people as $person )
								<tr @if ( ! $person['chosen'] && ! $person['by_email'] ) class="warning" @endif>
									<td><label for="wporghelpscoutimport-agent-{{ $person['id'] }}">{{ $person['name'] }} &lt;{{ $person['email'] }}&gt;</label></td>
									<td>
										<select name="agents[{{ $person['id'] }}]" id="wporghelpscoutimport-agent-{{ $person['id'] }}" class="form-control input-sm">
											<option value="">
												@if ( $person['by_email'] )
													{{ __('Same email: :name', [ 'name' => $person['by_email']->getFullName() ]) }}
												@else
													{{ __('No match: HelpScout Import') }}
												@endif
											</option>
											@foreach ( $users as $user )
												<option value="{{ $user->id }}" @if ( $person['chosen'] === (int) $user->id ) selected @endif>{{ $user->getFullName() }} &lt;{{ $user->email }}&gt;</option>
											@endforeach
										</select>
									</td>
								</tr>
							@endforeach
						</tbody>
					</table>
					<button type="submit" class="btn btn-default">{{ __('Save') }}</button>
				</form>
			@endif

			<form method="POST" action="{{ route( 'wporghelpscoutimport.start' ) }}" class="form-inline">
				{{ csrf_field() }}
				<select name="helpscout_mailbox_id" class="form-control" aria-label="{{ __('HelpScout mailbox') }}">
					@foreach ( $sources as $source )
						<option value="{{ $source['id'] }}" @if ( $agents === (int) $source['id'] ) selected @endif>{{ $source['name'] }}</option>
					@endforeach
				</select>
				&rarr;
				<select name="mailbox_id" class="form-control" aria-label="{{ __('FreeScout mailbox') }}">
					@foreach ( $mailboxes as $mailbox )
						<option value="{{ $mailbox->id }}">{{ $mailbox->name }}</option>
					@endforeach
				</select>
				<button type="submit" class="btn btn-primary">{{ __('Import') }}</button>
			</form>
		@endif

		@if ( count( $runs ) )
			<table class="table table-striped margin-top">
				<thead>
					<tr>
						<th>{{ __('From HelpScout') }}</th>
						<th>{{ __('Into') }}</th>
						<th>{{ __('What') }}</th>
						<th>{{ __('Status') }}</th>
						<th>{{ __('Progress') }}</th>
						<th>{{ __('Imported') }}</th>
						<th>{{ __('Updated') }}</th>
						<th>{{ __('Skipped') }}</th>
						<th>{{ __('Failed') }}</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					@foreach ( $runs as $run )
						<tr>
							<td>{{ $run->helpscout_mailbox_name }}</td>
							<td>{{ $run->mailbox ? $run->mailbox->name : __('(deleted)') }}</td>
							<td>
								@if ( $run->since )
									{{ __('Changes since :date', [ 'date' => App\User::dateFormat( $run->since, 'M j, Y H:i' ) ]) }}
								@else
									{{ __('Everything') }}
								@endif
							</td>
							<td>
								{{ ucfirst( $run->status ) }}
								@if ( $run->last_error )
									<br/><small class="text-danger">{{ $run->last_error }}</small>
								@endif
							</td>
							<td>
								@if ( $run->pages )
									{{ __('Page :page of :pages', [ 'page' => min( $run->page, $run->pages ), 'pages' => $run->pages ]) }}
									<br/><small>{{ __(':total conversations', [ 'total' => $run->total ]) }}</small>
								@endif
							</td>
							<td>{{ $run->imported }}</td>
							<td>{{ $run->updated }}</td>
							<td>{{ $run->skipped }}</td>
							<td>{{ $run->failed }}</td>
							<td class="text-right">
								@if ( 'running' === $run->status )
									<form method="POST" action="{{ route( 'wporghelpscoutimport.pause', [ 'id' => $run->id ] ) }}">
										{{ csrf_field() }}
										<button type="submit" class="btn btn-default btn-xs">{{ __('Pause') }}</button>
									</form>
								@elseif ( in_array( $run->status, [ 'paused', 'failed' ], true ) )
									<form method="POST" action="{{ route( 'wporghelpscoutimport.resume', [ 'id' => $run->id ] ) }}">
										{{ csrf_field() }}
										<button type="submit" class="btn btn-default btn-xs">{{ __('Resume') }}</button>
									</form>
								@endif
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		@endif
	</div>
</div>
@endsection

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

			<div class="alert alert-warning">
				<p><strong>{{ __('FreeScout users are never created from HelpScout.') }}</strong></p>
				<p>{{ __('Each HelpScout user’s replies and notes are credited to the FreeScout user chosen on the Agents page, or else to the one with the same email. A HelpScout user without either gets no FreeScout user: their replies and notes are credited to “HelpScout Import”. New FreeScout users can only be created from a WordPress.org username, on the Agents page.') }}</p>
				<p><a href="{{ route( 'wporghelpscoutimport.agents' ) }}" class="btn btn-default">{{ __('Agents') }}</a></p>
			</div>

			@if ( session( 'wporghelpscoutimport_unmatched' ) )
				@php $unmatched = session( 'wporghelpscoutimport_unmatched' ); @endphp
				<div class="alert alert-danger">
					<p><strong>{{ __(':count of :name’s HelpScout users have no FreeScout user.', [ 'count' => $unmatched['count'], 'name' => $unmatched['name'] ]) }}</strong></p>
					<p>{{ __('Their replies and notes would be credited to “HelpScout Import” until they’re matched on the Agents page.') }}</p>
					<form method="POST" action="{{ route( 'wporghelpscoutimport.start' ) }}" class="form-inline">
						{{ csrf_field() }}
						<input type="hidden" name="helpscout_mailbox_id" value="{{ $unmatched['mailbox'] }}">
						<input type="hidden" name="mailbox_id" value="{{ $unmatched['mailbox_id'] }}">
						<input type="hidden" name="unmatched_ok" value="1">
						<a href="{{ route( 'wporghelpscoutimport.agents', [ 'mailbox' => $unmatched['mailbox'] ] ) }}" class="btn btn-primary">{{ __('Match them first') }}</a>
						<button type="submit" class="btn btn-default">{{ __('Import anyway') }}</button>
					</form>
				</div>
			@endif

			<form method="POST" action="{{ route( 'wporghelpscoutimport.start' ) }}" class="form-inline">
				{{ csrf_field() }}
				<select name="helpscout_mailbox_id" class="form-control" aria-label="{{ __('HelpScout mailbox') }}">
					@foreach ( $sources as $source )
						<option value="{{ $source['id'] }}">{{ $source['name'] }}</option>
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

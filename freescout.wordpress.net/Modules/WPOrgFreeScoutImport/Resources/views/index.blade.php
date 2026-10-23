@extends('layouts.app')

@section('title', __('FreeScout Import'))

@section('content')
@php
	$statuses = [
		'running'   => __('Running'),
		'paused'    => __('Paused'),
		'done'      => __('Done'),
		'failed'    => __('Stopped'),
		'cancelled' => __('Cancelled'),
	];
	$skip_reasons = [
		'spam'        => __('spam'),
		'unpublished' => __('drafts or deleted'),
		'no_sender'   => __('no sender'),
		'deleted'     => __('deleted here'),
		'empty'       => __('nothing to import'),
		'gone'        => __('no longer there'),
		'moved'       => __('in another mailbox there now'),
	];
@endphp
<div class="container" @if ( $running ) data-wporgfreescoutimport-refresh="30" @endif>
	<div class="flexy-container">
		<div class="flexy-item">
			<span class="heading">{{ __('FreeScout Import') }}</span>
		</div>
		<div class="flexy-block"></div>
		<div class="flexy-item">
			<a href="{{ route( 'wporgfreescoutimport.users' ) }}" class="btn btn-bordered">{{ __('Users') }}</a>
			<a href="{{ route( 'wporgfreescoutimport.index' ) }}" class="btn btn-bordered">{{ __('Refresh') }}</a>
		</div>
	</div>

	<div class="margin-top">
		@include('partials/flash_messages')

		@if ( ! $configured )
			<p>{{ __('Enter the address of the FreeScout to import from, and its API key.') }}</p>
			@include('wporgfreescoutimport::settings')
		@elseif ( $error )
			<div class="alert alert-danger">{{ __(':name couldn’t be reached: :error', [ 'name' => $name, 'error' => $error ]) }}</div>
		@endif

		@if ( $sources )
			<p>{{ __('Imports copy a mailbox’s conversations from :name into a mailbox here, without sending anything. The first import copies everything; importing the same mailbox again copies only what changed since. Import before the mailbox’s email moves, and once more after.', [ 'name' => $name ]) }}</p>
			<p>{{ __('Every conversation gets a new number here; find one by the number it had there below. Each of :name’s users is credited to the user chosen on the Users page, or else to the one with the same email; an import creates a disabled user for anyone else.', [ 'name' => $name ]) }}</p>

			<form method="POST" action="{{ route( 'wporgfreescoutimport.start' ) }}" class="form-inline">
				{{ csrf_field() }}
				<select name="source_mailbox_id" class="form-control" aria-label="{{ __('Mailbox on :name', [ 'name' => $name ]) }}" required>
					<option value="" disabled selected>{{ __('Choose a mailbox on :name…', [ 'name' => $name ]) }}</option>
					@foreach ( $sources as $source )
						<option value="{{ $source['id'] }}">{{ $source['name'] }}</option>
					@endforeach
				</select>
				&rarr;
				<select name="mailbox_id" class="form-control" aria-label="{{ __('Mailbox here') }}" required>
					<option value="" disabled selected>{{ __('Choose a mailbox here…') }}</option>
					@foreach ( $mailboxes as $mailbox )
						<option value="{{ $mailbox->id }}">{{ $mailbox->name }}</option>
					@endforeach
				</select>
				<button type="submit" class="btn btn-primary">{{ __('Import') }}</button>
				<label class="checkbox-inline"><input type="checkbox" name="everything" value="1"> {{ __('Everything again, not only what changed') }}</label>
			</form>
		@endif

		@if ( count( $runs ) )
			<table class="table table-striped margin-top">
				<thead>
					<tr>
						<th>{{ __('From :name', [ 'name' => $name ]) }}</th>
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
							<td>{{ $run->source_mailbox_name }}</td>
							<td>{{ $run->mailbox ? $run->mailbox->name : __('(deleted)') }}</td>
							<td>
								@if ( $run->is_retry() )
									{{ __('Failed conversations again') }}
								@elseif ( $run->since )
									{{ __('Changes since :date', [ 'date' => App\User::dateFormat( $run->since, 'M j, Y H:i' ) ]) }}
								@else
									{{ __('Everything') }}
								@endif
							</td>
							<td>
								{{ $statuses[ $run->status ] ?? $run->status }}
								@if ( $run->is_stalled() )
									<br/><strong class="text-danger">{{ __('Stalled: no progress since :time. Resume it.', [ 'time' => App\User::dateFormat( $run->updated_at, 'M j, H:i' ) ]) }}</strong>
								@elseif ( 'running' === $run->status && $run->updated_at )
									<br/><small class="text-help">{{ __('Last progress :time', [ 'time' => App\User::dateFormat( $run->updated_at, 'H:i' ) ]) }}</small>
								@endif
								@if ( $run->last_error )
									<br/><small class="text-danger">{{ $run->last_error }}</small>
								@endif
							</td>
							<td>
								@if ( $run->is_retry() )
									{{ __(':done of :total', [ 'done' => count( (array) $run->page_done ), 'total' => $run->total ]) }}
								@elseif ( $run->pages )
									{{ __('Page :page of :pages', [ 'page' => min( $run->page, $run->pages ), 'pages' => $run->pages ]) }}
									<br/><small>{{ __(':total conversations', [ 'total' => $run->total ]) }}</small>
								@endif
							</td>
							<td>{{ $run->imported }}</td>
							<td>{{ $run->updated }}</td>
							<td>
								{{ $run->skipped }}
								@foreach ( (array) $run->skips as $reason => $count )
									<br/><small class="text-help">{{ $count }} {{ $skip_reasons[ $reason ] ?? $reason }}</small>
								@endforeach
							</td>
							<td>
								{{ $run->failed }}
								@if ( $run->failures )
									<details>
										<summary><small>{{ __('Which') }}</small></summary>
										<ul class="list-unstyled">
											@foreach ( (array) $run->failures as $source_id => $failure )
												<li><small>{{ $source_id }}: {{ $failure }}</small></li>
											@endforeach
										</ul>
									</details>
								@endif
							</td>
							<td class="text-right">
								@if ( 'running' === $run->status && ! $run->is_stalled() )
									<form method="POST" action="{{ route( 'wporgfreescoutimport.pause', [ 'id' => $run->id ] ) }}">
										{{ csrf_field() }}
										<button type="submit" class="btn btn-default btn-xs">{{ __('Pause') }}</button>
									</form>
								@elseif ( in_array( $run->status, [ 'paused', 'failed' ], true ) || $run->is_stalled() )
									<form method="POST" action="{{ route( 'wporgfreescoutimport.resume', [ 'id' => $run->id ] ) }}">
										{{ csrf_field() }}
										<button type="submit" class="btn btn-default btn-xs">{{ __('Resume') }}</button>
									</form>
								@endif
								@if ( in_array( $run->status, [ 'running', 'paused', 'failed' ], true ) )
									<form method="POST" action="{{ route( 'wporgfreescoutimport.cancel', [ 'id' => $run->id ] ) }}">
										{{ csrf_field() }}
										<button type="submit" class="btn btn-link btn-xs">{{ __('Cancel') }}</button>
									</form>
								@endif
								@if ( in_array( $run->status, [ 'done', 'cancelled' ], true ) && $run->failures )
									<form method="POST" action="{{ route( 'wporgfreescoutimport.retry', [ 'id' => $run->id ] ) }}">
										{{ csrf_field() }}
										<button type="submit" class="btn btn-default btn-xs">{{ __('Retry failed') }}</button>
									</form>
								@endif
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>

			<h3 class="margin-top">{{ __('Find a conversation') }}</h3>
			<form method="GET" action="{{ route( 'wporgfreescoutimport.find' ) }}" class="form-inline">
				<input type="text" name="number" class="form-control" inputmode="numeric" aria-label="{{ __('Number on :name', [ 'name' => $name ]) }}" placeholder="{{ __('Its number on :name', [ 'name' => $name ]) }}" required>
				<button type="submit" class="btn btn-default">{{ __('Open') }}</button>
			</form>
		@endif

		@if ( $configured )
			<h3 class="margin-top">{{ __('Message-IDs') }}</h3>
			<p class="text-help">{{ __(':name’s API doesn’t give its emails’ Message-IDs, which replies to old emails find their conversation by. Run the module’s bin/export-message-ids.php there, and upload what it writes, gzipped or not, before the last import. Threads imported already get theirs too. Uploading again replaces those it has.', [ 'name' => $name ]) }}</p>
			<p>{{ __(':count Message-IDs uploaded so far.', [ 'count' => number_format( $message_ids ) ]) }}</p>
			<form method="POST" action="{{ route( 'wporgfreescoutimport.message_ids' ) }}" enctype="multipart/form-data" class="form-inline">
				{{ csrf_field() }}
				<input type="file" name="message_ids" accept=".csv,.gz,text/csv,application/gzip" aria-label="{{ __('File of Message-IDs') }}" required>
				<button type="submit" class="btn btn-default">{{ __('Upload') }}</button>
			</form>
		@endif

		@if ( $configured )
			<h3 class="margin-top">{{ __('Where to import from') }}</h3>
			@include('wporgfreescoutimport::settings')
		@endif
	</div>
</div>
@endsection

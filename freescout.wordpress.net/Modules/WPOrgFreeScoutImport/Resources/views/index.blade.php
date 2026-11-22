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
			<a href="{{ route( 'wporgfreescoutimport.index' ) }}" class="btn btn-bordered">{{ __('Refresh') }}</a>
		</div>
	</div>

	<div class="margin-top">
		@include('partials/flash_messages')

		<p>{{ __('Imports copy a mailbox’s conversations from another FreeScout, a source, into a mailbox here, without sending anything. The first import copies everything; importing the same mailbox again copies only what changed since. Import before the mailbox’s email moves, and once more after.') }}</p>
		<p>{{ __('Every conversation gets a new number here; find one by the number it had there below. Each source’s users are credited to the users chosen on its Users page, or else to the ones with the same email; an import creates a disabled user for anyone else.') }}</p>

		<h3 class="margin-top">{{ __('Sources') }}</h3>
		@if ( $sources )
			<table class="table">
				<thead>
					<tr>
						<th>{{ __('Source') }}</th>
						<th>{{ __('Mailboxes') }}</th>
						<th>{{ __('Message-IDs') }}</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					@foreach ( $sources as $item )
						@php( $source = $item['source'] )
						<tr id="source-{{ $source->id }}">
							<td>
								<strong>{{ $source->name }}</strong><br/>
								<small>{{ $source->url }}</small>
								@if ( ! $item['configured'] )
									<br/><small class="text-warning">{{ __('No API key: enter one to import.') }}</small>
								@elseif ( $item['error'] )
									<br/><small class="text-danger">{{ __('Couldn’t be reached: :error', [ 'error' => $item['error'] ]) }}</small>
								@endif
							</td>
							<td>{{ implode( ', ', array_column( $item['mailboxes'], 'name' ) ) }}</td>
							<td>
								{{ number_format( $item['message_ids'] ) }}
								<form method="POST" action="{{ route( 'wporgfreescoutimport.message_ids', [ 'id' => $source->id ] ) }}" enctype="multipart/form-data">
									{{ csrf_field() }}
									<input type="file" name="message_ids" accept=".csv,.gz,text/csv,application/gzip" aria-label="{{ __('File of Message-IDs from :name', [ 'name' => $source->name ]) }}" required>
									<button type="submit" class="btn btn-default btn-xs">{{ __('Upload') }}</button>
								</form>
							</td>
							<td class="text-right">
								<a href="{{ route( 'wporgfreescoutimport.users', [ 'id' => $source->id ] ) }}" class="btn btn-default btn-xs">{{ __('Users') }}</a>
								<details>
									<summary><small>{{ __('Edit') }}</small></summary>
									@include( 'wporgfreescoutimport::source', [ 'source' => $source, 'action' => route( 'wporgfreescoutimport.sources.update', [ 'id' => $source->id ] ) ] )
									@if ( $source->has_key() )
										<form method="POST" action="{{ route( 'wporgfreescoutimport.sources.forget', [ 'id' => $source->id ] ) }}">
											{{ csrf_field() }}
											<button type="submit" class="btn btn-link btn-xs">{{ __('Forget the API key') }}</button>
										</form>
									@endif
									@if ( $item['deletable'] )
										<form method="POST" action="{{ route( 'wporgfreescoutimport.sources.delete', [ 'id' => $source->id ] ) }}">
											{{ csrf_field() }}
											<button type="submit" class="btn btn-link btn-xs text-danger">{{ __('Delete') }}</button>
										</form>
									@endif
								</details>
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
			<p class="text-help">{{ __('A source’s API doesn’t give its emails’ Message-IDs, which replies to old emails find their conversation by. Run the module’s bin/export-message-ids.php on its server, and upload what it writes, gzipped or not, before the last import. Threads imported already get theirs too. Uploading again replaces those it has.') }}</p>
		@endif

		<details @if ( ! $sources ) open @endif>
			<summary>{{ __('Add a source') }}</summary>
			@include( 'wporgfreescoutimport::source', [ 'source' => null, 'action' => route( 'wporgfreescoutimport.sources.store' ) ] )
		</details>

		@if ( array_filter( array_column( $sources, 'mailboxes' ) ) )
			<h3 class="margin-top">{{ __('Import') }}</h3>
			<form method="POST" action="{{ route( 'wporgfreescoutimport.start' ) }}" class="form-inline">
				{{ csrf_field() }}
				<select name="remote_mailbox" class="form-control" aria-label="{{ __('Mailbox to import') }}" required>
					<option value="" disabled selected>{{ __('Choose a mailbox to import…') }}</option>
					@foreach ( $sources as $item )
						@if ( $item['mailboxes'] )
							<optgroup label="{{ $item['source']->name }}">
								@foreach ( $item['mailboxes'] as $remote )
									<option value="{{ $item['source']->id }}:{{ $remote['id'] }}">{{ $remote['name'] }}</option>
								@endforeach
							</optgroup>
						@endif
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
						<th>{{ __('From') }}</th>
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
							<td>{{ $run->remote_mailbox_name }}<br/><small class="text-help">{{ $run->source ? $run->source->name : __('(deleted)') }}</small></td>
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
											@foreach ( (array) $run->failures as $remote_id => $failure )
												<li><small>{{ $remote_id }}: {{ $failure }}</small></li>
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
				<select name="source_id" class="form-control" aria-label="{{ __('Source') }}" required>
					@foreach ( $sources as $item )
						<option value="{{ $item['source']->id }}">{{ $item['source']->name }}</option>
					@endforeach
				</select>
				<input type="text" name="number" class="form-control" inputmode="numeric" aria-label="{{ __('Number there') }}" placeholder="{{ __('Its number there') }}" required>
				<button type="submit" class="btn btn-default">{{ __('Open') }}</button>
			</form>
		@endif
	</div>
</div>
@endsection

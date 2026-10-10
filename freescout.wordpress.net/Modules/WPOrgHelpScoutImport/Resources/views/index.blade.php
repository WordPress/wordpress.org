@extends('layouts.app')

@section('title', __('HelpScout Import'))

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
		'deleted'     => __('deleted in FreeScout'),
		'empty'       => __('nothing to import'),
		'gone'        => __('no longer in HelpScout'),
		'moved'       => __('in another HelpScout mailbox now'),
	];
	$reply_results = [
		'imported'  => __('imported'),
		'updated'   => __('updated'),
		'unchanged' => __('unchanged'),
		'kept'      => __('kept as FreeScout has them'),
		'failed'    => __('failed'),
	];
@endphp
{{-- Not while something on the page waits to be confirmed: refreshing would lose it. --}}
<div class="container" @if ( $running && ! session( 'wporghelpscoutimport_confirm' ) ) data-wporghelpscoutimport-refresh="30" @endif>
	<div class="flexy-container">
		<div class="flexy-item">
			<span class="heading">{{ __('HelpScout Import') }}</span>
		</div>
		<div class="flexy-block"></div>
		<div class="flexy-item">
			<a href="{{ route( 'wporghelpscoutimport.agents' ) }}" class="btn btn-bordered">{{ __('Users') }}</a>
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

		@if ( $numbering && ! $numbering['custom'] )
			<div class="alert alert-warning">{{ __('FreeScout shows its internal IDs instead of conversation numbers, so imported conversations won’t show HelpScout’s. Under Manage » Settings » General, set Conversation Number to Custom…') }}</div>
		@endif
		@if ( $numbering && $numbering['next'] <= $numbering['highest'] )
			<div class="alert alert-warning">{{ __('New FreeScout conversations would get numbers HelpScout already uses: FreeScout’s next is :next, and HelpScout’s highest is :highest. Before the first live email, set Next Conversation # well above it under Manage » Settings » General.', [ 'next' => number_format( $numbering['next'] ), 'highest' => number_format( $numbering['highest'] ) ]) }}</div>
		@endif

		@if ( $sources )
			<p>{{ __('Imports copy a HelpScout mailbox’s conversations into a FreeScout mailbox, without sending anything. The first import copies everything; importing the same mailbox again copies only what changed since. Import before the mailbox’s email moves, and once more after.') }}</p>
			<p>
				@if ( $replies )
					{{ __('Once its conversations are done, an import copies the mailbox’s saved replies too, and brings those it copied before up to date, unless they were changed in FreeScout.') }}
				@else
					{{ __('Switch on the Saved Replies module to import the mailboxes’ saved replies too.') }}
				@endif
			</p>
			<p>{{ __('Every HelpScout user who can see the mailbox gets a FreeScout user with access to it, unless they have one with the same email, or one is chosen on the Users page. Users HelpScout no longer has get a disabled one, when an import meets them. New users log in with WordPress.org once they’re connected to their account on their profile.') }}</p>

			@if ( session( 'wporghelpscoutimport_confirm' ) )
				@php $confirm = session( 'wporghelpscoutimport_confirm' ); @endphp
				<div class="alert alert-info">
					<p><strong>{{ __('Importing :name creates :count FreeScout users, with access to :mailbox:', [ 'name' => $confirm['name'], 'count' => count( $confirm['users'] ), 'mailbox' => $confirm['mailbox'] ]) }}</strong></p>
					<ul>
						@foreach ( $confirm['users'] as $user )
							<li>{{ $user }}</li>
						@endforeach
					</ul>
					<p>{{ __('If any of them already has a FreeScout user under another email, choose it on the Users page first: FreeScout can’t merge users later.') }}</p>
					<form method="POST" action="{{ route( 'wporghelpscoutimport.start' ) }}" class="form-inline">
						{{ csrf_field() }}
						<input type="hidden" name="helpscout_mailbox_id" value="{{ $confirm['helpscout_mailbox_id'] }}">
						<input type="hidden" name="mailbox_id" value="{{ $confirm['mailbox_id'] }}">
						<input type="hidden" name="everything" value="{{ $confirm['everything'] ? '1' : '' }}">
						<input type="hidden" name="confirmed" value="1">
						<button type="submit" class="btn btn-primary">{{ __('Create users and import') }}</button>
						<a href="{{ route( 'wporghelpscoutimport.agents', [ 'mailbox' => $confirm['helpscout_mailbox_id'] ] ) }}" class="btn btn-default">{{ __('Review users') }}</a>
					</form>
				</div>
			@endif

			<form method="POST" action="{{ route( 'wporghelpscoutimport.start' ) }}" class="form-inline">
				{{ csrf_field() }}
				<select name="helpscout_mailbox_id" class="form-control" aria-label="{{ __('HelpScout mailbox') }}" required>
					<option value="" disabled selected>{{ __('Choose a HelpScout mailbox…') }}</option>
					@foreach ( $sources as $source )
						<option value="{{ $source['id'] }}">{{ $source['name'] }}</option>
					@endforeach
				</select>
				&rarr;
				<select name="mailbox_id" class="form-control" aria-label="{{ __('FreeScout mailbox') }}" required>
					<option value="" disabled selected>{{ __('Choose a FreeScout mailbox…') }}</option>
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
								@if ( $run->saved_replies )
									<br/><small>{{ __('Saved replies:') }} {{ implode( ', ', array_map( function ( $result, $count ) use ( $reply_results ) { return ( is_array( $count ) ? count( $count ) : $count ) . ' ' . ( $reply_results[ $result ] ?? $result ); }, array_keys( $run->saved_replies ), $run->saved_replies ) ) }}</small>
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
											@foreach ( (array) $run->failures as $helpscout_id => $failure )
												<li><small>{{ $helpscout_id }}: {{ $failure }}</small></li>
											@endforeach
										</ul>
									</details>
								@endif
							</td>
							<td class="text-right">
								@if ( 'running' === $run->status && ! $run->is_stalled() )
									<form method="POST" action="{{ route( 'wporghelpscoutimport.pause', [ 'id' => $run->id ] ) }}">
										{{ csrf_field() }}
										<button type="submit" class="btn btn-default btn-xs">{{ __('Pause') }}</button>
									</form>
								@elseif ( in_array( $run->status, [ 'paused', 'failed' ], true ) || $run->is_stalled() )
									<form method="POST" action="{{ route( 'wporghelpscoutimport.resume', [ 'id' => $run->id ] ) }}">
										{{ csrf_field() }}
										<button type="submit" class="btn btn-default btn-xs">{{ __('Resume') }}</button>
									</form>
								@endif
								@if ( in_array( $run->status, [ 'running', 'paused', 'failed' ], true ) )
									<form method="POST" action="{{ route( 'wporghelpscoutimport.cancel', [ 'id' => $run->id ] ) }}">
										{{ csrf_field() }}
										<button type="submit" class="btn btn-link btn-xs">{{ __('Cancel') }}</button>
									</form>
								@endif
								@if ( in_array( $run->status, [ 'done', 'cancelled' ], true ) && $run->failures )
									<form method="POST" action="{{ route( 'wporghelpscoutimport.retry', [ 'id' => $run->id ] ) }}">
										{{ csrf_field() }}
										<button type="submit" class="btn btn-default btn-xs">{{ __('Retry failed') }}</button>
									</form>
								@endif
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		@endif

		@if ( count( $copies ) )
			<h3 class="margin-top">{{ __('WordPress.org') }}</h3>
			<p class="text-help">{{ __('WordPress.org keeps a copy of conversations, which the plugin directory links to, and replies to uploads in. It points at HelpScout until a mailbox switches to FreeScout: once HelpScout’s webhook leaves the mailbox alone, and after its last import, point WordPress.org at the conversations imported from HelpScout, together with moving the mailbox’s email. Until then, what happens to imported conversations in FreeScout isn’t sent to WordPress.org; after, it is, and later imports are pointed at as they finish. See the module’s README for every step.') }}</p>
			<table class="table table-striped">
				<thead>
					<tr>
						<th>{{ __('Mailbox') }}</th>
						<th>{{ __('Imported') }}</th>
						<th>{{ __('WordPress.org points at') }}</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					@foreach ( $copies as $copy )
						@php( $state = $copy['state'] )
						<tr>
							<td>{{ $copy['mailbox']->name }}</td>
							<td>{{ number_format( $copy['total'] ) }}</td>
							<td>
								@if ( ! $state )
									{{ __('HelpScout') }}
								@elseif ( Modules\WPOrgHelpScoutImport\Services\Copies::STATUS_RUNNING === $state['status'] )
									{{ __('FreeScout, :sent of :total so far', [ 'sent' => number_format( $state['sent'] ), 'total' => number_format( $copy['total'] ) ]) }}
								@elseif ( Modules\WPOrgHelpScoutImport\Services\Copies::STATUS_FAILED === $state['status'] )
									{{ __('FreeScout, :sent of :total so far', [ 'sent' => number_format( $state['sent'] ), 'total' => number_format( $copy['total'] ) ]) }}
									<br/><small class="text-danger">{{ $state['error'] }}</small>
								@else
									{{ __('FreeScout, since :date', [ 'date' => App\User::dateFormat( $state['started_at'], 'M j, Y H:i' ) ]) }}
								@endif
							</td>
							<td>
								@if ( ! $state || Modules\WPOrgHelpScoutImport\Services\Copies::STATUS_FAILED === $state['status'] )
									<form method="POST" action="{{ route( 'wporghelpscoutimport.copies' ) }}">
										{{ csrf_field() }}
										<input type="hidden" name="mailbox_id" value="{{ $copy['mailbox']->id }}">
										<button type="submit" class="btn btn-default btn-xs">{{ $state ? __('Carry on') : __('Point WordPress.org at FreeScout') }}</button>
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

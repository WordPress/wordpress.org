@extends('layouts.app')

@section('title_full', __('Teams') . ' - ' . $user->first_name . ' ' . $user->last_name)

@section('sidebar')
	@include('partials/sidebar_menu_toggle')
	@include('users/sidebar_menu')
@endsection

@section('content')
	<div class="section-heading">
		{{ __('Teams') }}
	</div>

	@include('partials/flash_messages')

	<div class="container form-container">
		<div class="row">
			<form method="POST" action="{{ route( 'wporgteams.user.save', array( 'id' => $user->id ) ) }}">
				{{ csrf_field() }}

				<div class="col-xs-12">
					<h3>{{ __(':first_name is a member of the selected teams:', array( 'first_name' => $user->first_name )) }}</h3>

					@if ( $user->isAdmin() )
						<p class="text-help">{{ __('Administrators have access to every mailbox and every permission; teams only make them members.') }}</p>
					@endif

					@forelse ( $teams as $team )
						<div class="control-group">
							<label class="checkbox" for="wporgteams-team-{{ $team->id }}">
								<input type="checkbox" name="{{ \Modules\WPOrgTeams\Http\Middleware\TeamAccess::TEAMS_FIELD }}[]" id="wporgteams-team-{{ $team->id }}" value="{{ $team->id }}" @if ( in_array( (int) $team->id, $member_of, true ) ) checked="checked" @endif @if ( ! in_array( (int) $team->id, $assignable, true ) ) disabled="disabled" @endif> {{ $team->first_name }}
							</label>
						</div>
					@empty
						<p class="text-help">{{ __('There are no teams yet.') }}</p>
					@endforelse

					@if ( count( $teams ) > count( $assignable ) )
						<p class="text-help">{{ __('You can only add people to, or take them out of, the teams you’re in.') }}</p>
					@endif

					@if ( count( $assignable ) )
						<div class="form-group margin-top">
							<button type="submit" class="btn btn-primary">{{ __('Save Teams') }}</button>
						</div>
					@endif
				</div>
			</form>

			@if ( ! $user->isAdmin() )
				<div class="col-xs-12 margin-top">
					<h3>{{ __('What their teams give them') }}</h3>

					<h4>{{ __('Mailboxes') }}</h4>
					@if ( count( $mailboxes ) )
						<ul>
							@foreach ( $mailboxes as $mailbox )
								<li>{{ $mailbox->name }}</li>
							@endforeach
						</ul>
					@else
						<p class="text-help">{{ __('None') }}</p>
					@endif

					<h4>{{ __('Permissions') }}</h4>
					@if ( count( $permissions ) )
						<ul>
							@foreach ( $permissions as $permission )
								<li>{{ App\User::getUserPermissionName( $permission ) }}</li>
							@endforeach
						</ul>
					@else
						<p class="text-help">{{ __('None') }}</p>
					@endif

					@if ( $defaults )
						<p class="text-help">{{ __('Everyone also has the permissions Manage » Settings gives all users: :permissions', array( 'permissions' => implode( ', ', array_map( array( App\User::class, 'getUserPermissionName' ), $defaults ) ) )) }}</p>
					@endif
				</div>
			@endif
		</div>
	</div>
@endsection

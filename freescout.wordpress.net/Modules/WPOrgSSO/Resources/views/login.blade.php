@extends('layouts.app')

@section('body_class', 'wporgsso-login')

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-8 col-md-offset-2">

			@include('auth/banner')

			<div class="wporgsso-login-body">
				@if ( $error )
					<div class="alert alert-danger">{{ $error }}</div>
				@endif

				<a class="btn btn-primary btn-lg wporgsso-login-button" href="{{ route( 'wporgsso.start' ) }}">
					<img src="{{ asset( \Module::getPublicPath( 'wporgsso' ) . '/img/wordpress-mark-white.svg' ) }}" alt="" width="22" height="22">
					<span>{{ __( 'Log in with WordPress.org' ) }}</span>
				</a>

				<p class="wporgsso-login-help">{!! __( 'Need access? Please reach out in :channel.', array( 'channel' => '<a href="https://wordpress.slack.com/archives/C02QB8GMM">#meta</a>' ) ) !!}</p>

				@if ( $password_login )
					<p class="margin-top">
						<a class="btn btn-link" href="{{ route( 'login', array( 'password' => 1 ) ) }}">{{ __( 'Administrator password login' ) }}</a>
					</p>
				@endif
			</div>
		</div>
	</div>
</div>
@endsection

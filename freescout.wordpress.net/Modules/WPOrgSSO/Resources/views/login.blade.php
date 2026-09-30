@extends('layouts.app')

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-8 col-md-offset-2">

			@include('auth/banner')

			<div class="panel panel-default panel-shaded">
				<div class="panel-body text-center">
					@if ( $error )
						<div class="alert alert-danger">{{ $error }}</div>
					@endif

					<p class="margin-top">
						<a class="btn btn-primary btn-lg" href="{{ route( 'wporgsso.start' ) }}">{{ __( 'Log in with WordPress.org' ) }}</a>
					</p>

					@if ( $password_login )
						<p class="margin-top">
							<a class="btn btn-link" href="{{ route( 'login', array( 'password' => 1 ) ) }}">{{ __( 'Administrator password login' ) }}</a>
						</p>
					@endif
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

{{-- The Teams module's team form, with the permissions the team grants; see Services/TeamForm.php. --}}
{!! \Modules\WPOrgTeams\Services\TeamForm::render( \Illuminate\Support\Arr::except( $__data, array( '__env', 'app' ) ) ) !!}

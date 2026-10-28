<?php
namespace WordPressdotorg\API\HelpScout;

// Add current DPO Export/Erasure status for the customer.
$wp_init_host = 'https://wordpress.org/'; // DPO site.
include __DIR__ . '/common.php';

// $request is the validated HelpScout request.
$request = get_request();

if ( empty( $request->customer->email ) ) {
	die( json_encode( [ 'html' => 'No email found' ] ) );
}

// This needs to run as a user.
wp_set_current_user( get_user_by( 'login', 'wordpressdotorg' )->ID );

// default empty output
$html  = '';
$email = get_user_email_for_email( $request );

$html .= sprintf(
	"<p>Search <a href='%s'>Erasures</a> | <a href='%s'>Exports</a></p>",
	add_query_arg( 's', urlencode( $email ), admin_url( 'erase-personal-data.php' ) ),
	add_query_arg( 's', urlencode( $email ), admin_url( 'export-personal-data.php' ) )
);

$requests = get_posts( [
	'post_type'      => 'user_request',
	's'              => $email,
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
] );

if ( ! $requests ) {
	$html .= '<p>No requests found.</p>';
} else {
	$html .= '<ul>';

	// Since PHP8 match() statements aren't yet here.
	$match_type = function( $type ) {
		switch ( $type ) {
			case 'export_personal_data': return 'Export';
			case 'remove_personal_data': return 'Erasure';
			default: return ucwords( str_replace( '_', ' ', $type ) );
		}
	};

	foreach ( $requests as $request_id ) {
		$request = wp_get_user_request( $request_id );

		$timestamps = [ 'created', 'modified', 'confirmed', 'completed' ];
		$dates      = [];
		foreach ( $timestamps as $field ) {
			$request_field = "{$field}_timestamp";
			if ( ! $request->$request_field ) {
				continue;
			}

			$dates[ $request->$request_field ] = sprintf(
				'%s: %s',
				ucwords( $field ),
				gmdate( 'Y-m-d H:i:s', $request->$request_field )
			);
		}
			

		$html .= sprintf(
			"<li title='%s'>%s: <strong>%s %s</strong></li>\n",
			implode( ', ', $dates ),
			gmdate( 'Y-m-d', min( array_keys( $dates ) ) ),
			$match_type( $request->action_name ),
			get_post_status_object( $request->status )->label,
		);
	}

	$html .= '</ul>';
}

// response to HS is just HTML to display in the sidebar
echo json_encode( [ 'html' => $html ] );

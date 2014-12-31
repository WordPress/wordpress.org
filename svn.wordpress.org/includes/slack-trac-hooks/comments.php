<?php

namespace Dotorg\SlackTracHooks\Comments;

function process_message( $lines ) {
	$lines = array_map( 'rtrim', $lines );
	
	// Trim off headers.
	while ( '' !== current( $lines ) ) {
		array_shift( $lines );
	}
	// Remove empty line between headers and body.
	array_shift( $lines );
	
	$title = '';
	while ( 0 !== strpos( current( $lines ), '------' ) ) {
		if ( '' !== $title ) {
			$last = substr( $title, -1 );
			if ( $last !== '-' && $last !== '_' ) {
				$title .= ' ';
			}
		}
		$title .= array_shift( $lines );
	}
	$title = substr( $title, strpos( $title, ': ' ) + 2 );
	
	// Remove up to top of ticket properties table.
	while ( 0 !== strpos( current( $lines ), '------' ) ) {
		array_shift( $lines );
	}
	// Remove top border of table.
	array_shift( $lines );
	// Remove ticket properties table.
	while ( 0 !== strpos( current( $lines ), '------' ) ) {
		array_shift( $lines );
	}
	// Remove bottom border of table.
	array_shift( $lines );
	
	// Remove empty line if present. (It is when it's a comment without changes.)
	if ( current( $lines ) === '' ) {
		array_shift( $lines );
	}
	
	// Remove Trac email footer.
	while ( end( $lines ) !== '--' ) {
		array_pop( $lines );
	}
	// Remove -- which starts footer.
	array_pop( $lines );
	// Remove empty line before footer.
	array_pop( $lines );
	
	preg_match( '/^(Comment|Changes) \(by (.*)\):$/', array_shift( $lines ), $matches );
	$has_changes = $matches[1] === 'Changes';
	$author = $matches[2];
	
	// Remove blank line after 'Comment|Changes (by author):'
	array_shift( $lines );
	
	$changes = $comment = array();
	if ( $has_changes ) {
		while ( '' !== current( $lines ) ) {
			$changes[] = preg_replace( '~^ \* (.*?):  ~', '_*$1:*_ ', array_shift( $lines ) );
		}
	}
	
	// Remove blank lines (should be two if it had changes).
	while ( '' === current( $lines ) ) {
		array_shift( $lines );
	}
	
	// Next line should start with 'Comment' if there is one.
	if ( $has_changes && 0 === strpos( current( $lines ), 'Comment' ) ) {
		array_shift( $lines ); // Remove 'Comment'
		array_shift( $lines ); // Remove blank line
	}
	
	// Everything left is the comment. Remove leading space.
	$comment = array_map( 'ltrim', $lines );
	
	$changes = implode( "\n", $changes );
	$comment = implode( "\n", $comment );

	return compact( 'author', 'title', 'changes', 'comment' );
}

function format_comment( $comment, $ticket_url ) {
	// Link 'Replying to [comment:1 user]:'
	$comment = preg_replace_callback( '/^Replying to \[comment:(\d+) (.*)\]/m',
		function ( $matches ) use ( $ticket_url ) {
			$comment_url = $ticket_url . '#comment:' . $matches[1];
			$text = 'Replying to ' . $matches[2];
			return "<$comment_url|$text>";
		}, $comment );

	// Replace {{{ and }}} with ``` or `
	$comment = trim( str_replace(
		array( "\n{{{\n", "\n}}}\n", '{{{', '}}}' ),
		array( "\n```\n", "\n```\n", '`',   '`' ),
		"\n$comment\n"
	), "\n" );

	return $comment;
}

function send( $slack_hook, $args ) {
	// Don't post auto-comments for commits.
	if ( false !== strpos( $args['comment'], '#!CommitTicketReference' ) ) {
		return;
	}

	$trac_class = '\\Dotorg\\SlackTracHooks\\' . $args['trac'] . '_Trac';
	$trac = new $trac_class;

		
	$args['comment'] = format_comment( $args['comment'], $args['ticket_url'] );
	$main_attachment = $args['changes'] ? $args['changes'] : $args['comment'];

	$pretext = sprintf( '*%s updated <%s|#%s %s>*', $args['author'], $args['comment_url'], $args['ticket'], $args['title'] );
	$fallback = trim( $pretext, '*' ) . "\n" . $main_attachment;

	$payload = array(
		'channel'     => $trac->get_firehose_channel(),
		'icon_emoji'  => $trac->get_emoji(),
		'username'    => $trac->get_ticket_username(),
		'attachments' => array(
			array(
				'pretext'   => $pretext,
				'fallback'  => $fallback,
				'text'      => $main_attachment,
				'mrkdwn_in' => array( 'pretext', 'fallback', 'text' ),
			),
		),
	);

	// If we have both changes and a comment, append the comment.
	// Ensure the comment uses a darker gray color, even when alone.	
	if ( $args['changes'] && $args['comment'] ) {
		$payload['attachments'][] = array(
			'fallback'  => $args['comment'],
			'text'      => $args['comment'],
			'mrkdwn_in' => array( 'fallback', 'text' ),
			'color'     => '#999',
		);
	} elseif ( ! $args['changes'] ) {
		$payload['attachments'][0]['color'] = '#999';
	}
	
	$payload = json_encode( $payload );
	
	$context = stream_context_create( array(
		'http' => array(
			'method'  => 'POST',
			'header'  => 'Content-Type: application/x-www-form-urlencoded' . PHP_EOL,
			'content' => http_build_query( compact( 'payload' ) ),
		),
	) );
	
	file_get_contents( $slack_hook, false, $context );
}


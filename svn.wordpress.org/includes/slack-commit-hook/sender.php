<?php

namespace SlackCommitHook;

class Sender {
	protected $trac;
	protected $repo;
	protected $rev;
	protected $slack_hook;

	protected $test_mode = false;
	protected $svnlook = '/usr/bin/svnlook';

	public function __construct( $trac, $repo, $rev, $slack_hook ) {
		$this->set_trac( $trac );
		$this->repo = $repo;
		$this->rev = $rev;
		$this->slack_hook = $slack_hook;
	}

	public function use_test_channel( $enabled ) {
		$this->test_mode = (bool) $enabled;
	}

	public function set_svnlook_executable( $path ) {
		$this->svnlook = $path;
	}

	public function run() {
		$payload = $this->generate_payload();
		foreach ( $this->trac->get_channels( $this->svnlook( 'changed' ) ) as $channel ) {
			$this->send( $channel, $payload );
		}
	}

	protected function set_trac( $trac ) {
		$class = __NAMESPACE__ . '\\' . $trac . '_Trac';
		$this->trac = new $class;
	}

	protected function generate_payload() {
		$author = $this->svnlook( 'author' );
		$log = $this->format_log_for_slack( $this->svnlook( 'log' ) );

		$url = $this->trac->get_commit_template( $this->rev );
		$revision = 'r' . $this->rev;

		$username = $this->trac->get_username();
		$emoji    = $this->trac->get_emoji();
		$color    = $this->trac->get_color();

		$pretext = "*$username <$url|$revision>* by $author:";
		$fallback = "$revision by $author: $log";

		return array(
			'channel'     => '#test',
			'username'    => $username,
			'icon_emoji'  => $emoji,
			'attachments' => array( array(
				'color'     => $color,
				'pretext'   => $pretext,
				'text'      => $log,
				'fallback'  => $fallback,
				'mrkdwn_in' => array( 'text', 'pretext' ),
			) ),
		);
	}

	protected function format_log_for_slack( $log ) {
		foreach ( $this->trac->get_log_replacements() as $find => $replace ) {
			$log = preg_replace( $find, '<' . $replace . '|$0>', $log );
		}
		return $log;
	}

	protected function svnlook( $subcommand ) {
		$args = array(
			$subcommand,
			'-r' . $this->rev,
			$this->repo,
		);
		$args = array_map( 'escapeshellarg', $args );
		$command = escapeshellcmd( $this->svnlook ) . ' ' . implode( ' ', $args );

		if ( $subcommand === 'changed' ) {
			$command .= ' | awk \'{print $2}\'';
		}

		exec( $command, $output, $return_var );
		if ( $return_var === 0 ) {
			return trim( implode( "\n", $output ) );
		}
		$this->error( "Error processing svnlook( $subcommand )" );
	}

	protected function error( $message ) {
		error_log( $message . ' for ' . $this->repo . ' -r ' . $this->rev );
		exit( 1 );
	}

	protected function send( $channel, $payload ) {
		if ( $this->test_mode ) {
			$payload['attachments'][0]['pretext']  = "[$channel] " . $payload['attachments'][0]['pretext'];
			$payload['attachments'][0]['fallback'] = "[$channel] " . $payload['attachments'][0]['fallback'];
		} else {
			$payload['channel'] = $channel;
		}

		$context = stream_context_create( array(
			'http' => array(
				'method'  => 'POST',
				'header'  => 'Content-Type: application/x-www-form-urlencoded' . PHP_EOL,
				'content' => http_build_query( array( 'payload' => json_encode( $payload ) ) ),
			),
		) );

		file_get_contents( $this->slack_hook, false, $context );
	}
}

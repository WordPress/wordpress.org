<?php

namespace Dotorg\Slack\Trac;
use Dotorg\Slack\Send;

class Commit_Handler {
	protected $trac;
	protected $repo;
	protected $rev;
	protected $send;

	protected $test_mode = false;
	protected $svnlook = '/usr/bin/svnlook';

	public function __construct( Send $send, $trac, $repo, $rev ) {
		$this->send = $send;
		$this->trac = Trac::get( $trac );
		$this->repo = $repo;
		$this->rev  = $rev;
	}

	public function run() {
		$this->generate_payload();
		foreach ( $this->trac->get_commit_channels( $this->svnlook( 'changed' ) ) as $channel ) {
			$this->send->send( $channel );
		}
	}

	public function testing( $enabled ) {
		$this->send->testing( (bool) $enabled );
	}

	public function set_svnlook_executable( $path ) {
		$this->svnlook = $path;
	}

	protected function generate_payload() {
		$author = $this->svnlook( 'author' );
		$log = Commit::format_commit_for_slack( $this->trac, $this->svnlook( 'log' ) );

		$url = $this->trac->get_commit_template( $this->rev );
		$revision = 'r' . $this->rev;

		$username = $this->trac->get_commit_username();
		$icon     = $this->trac->get_icon();
		$color    = $this->trac->get_color();

		$pretext = "*$username <$url|$revision>* by $author:";
		$fallback = "$revision by $author: $log";

		$this->send->set_username( $username );
		$this->send->set_icon( $icon );
		$this->send->add_attachment( array(
			'color'     => $color,
			'pretext'   => $pretext,
			'text'      => $log,
			'fallback'  => $fallback,
			'mrkdwn_in' => array( 'text', 'pretext', 'fallback' ),
		) );
	}

	protected function svnlook( $subcommand ) {
		$args = array(
			$subcommand,
			'-r' . $this->rev,
			$this->repo,
		);
		$args = array_map( 'escapeshellarg', $args );
		$command = escapeshellcmd( $this->svnlook . ' ' . implode( ' ', $args ) );

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
}

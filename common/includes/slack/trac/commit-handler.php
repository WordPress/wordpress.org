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
		foreach ( $this->trac->get_commit_channels( $this->svnlook( 'changed' ), $this->svnlook( 'log' ) ) as $channel ) {
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
		$author   = $this->svnlook( 'author' );
		$message  = Commit::format_commit_for_slack( $this->trac, $this->svnlook( 'log' ) );
		$date     = $this->svnlook( 'date' );
		$url      = $this->trac->get_commit_template( $this->rev );
		$revision = 'r' . $this->rev;
		$username = $this->trac->get_commit_username();
		$icon     = $this->trac->get_icon();
		$color    = $this->trac->get_color();
		$title    = "$username $revision";
		$fallback = "$username $revision by $author";

		$this->send->set_username( $username );
		$this->send->set_icon( $icon );
		$this->send->add_attachment( array(
			'title'       => $title,
			'title_link'  => $url,
			'author_name' => $author,
			'author_icon' => sprintf( 'https://wordpress.org/grav-redirect.php?user=%s&s=32', $author ),
			'color'       => $color,
			'text'        => $message,
			'fallback'    => $fallback,
			'mrkdwn_in'   => array( 'text' ),
			'ts'          => $date,
			'footer'      => sprintf( '<%s|%s>', $this->trac->get_url(), $this->trac->get_name() ),
			'footer_icon' => sprintf( '%s/chrome/common/trac.ico', $this->trac->get_url() ),
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

		if ( $subcommand === 'date' ) {
			// Convert output to timestamp.
			$command = 'date --date "$( ' . $command . ' )" +%s';
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

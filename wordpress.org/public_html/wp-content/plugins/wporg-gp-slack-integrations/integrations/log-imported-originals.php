<?php
/**
 * Logs originals imports of specific projects into a public Slack channel.
 */
class WPorg_GP_Slack_Log_Imported_Originals {

	/**
	 * Holds the channels name to send notifcations too.
	 */
	private const SLACK_CHANNEL = '#polyglots';

	/**
	 * Holds the list of allowed project IDs
	 */
	private const ALLOWED_PROJECTS = [
		1133, // wp-themes/twentyten
		1140, // wp-themes/twentyeleven
		1128, // wp-themes/twentytwelve
		1131, // wp-themes/twentythirteen
		1135, // wp-themes/twentyfourteen
		1138, // wp-themes/twentyfifteen
		2821, // wp-themes/twentysixteen
		361582, // wp-themes/twentyseventeen
		412870, // wp-themes/twentynineteen
		434201, // wp-themes/twentytwenty
		458013, // wp-themes/twentytwentyone
		483405, // wp-themes/twentytwentytwo
		380277, // wp-plugins/gutenberg/dev
		//377373, // wp-plugins/nothing-much/dev
		473698, // patterns/core
	];


	public function __construct() {
		if ( ! defined( 'GLOTPRESS_SLACK_WEBHOOK' ) ) {
			return;
		}

		add_action( 'gp_originals_imported', array( $this, 'process_import' ), 20, 5 );
	}


	/**
	 * Logs originals imports into Slack.
	 *
	 * @param string $project_id          Project ID the import was made to.
	 * @param int    $originals_added     Number or total originals added.
	 * @param int    $originals_existing  Number of existing originals updated.
	 * @param int    $originals_obsoleted Number of originals that were marked as obsolete.
	 * @param int    $originals_fuzzied   Number of originals that were close matches of old ones and thus marked as fuzzy.
	 */
	function process_import( $project_id, $originals_added, $originals_existing, $originals_obsoleted, $originals_fuzzied ) {
		if ( ! in_array( $project_id, self::ALLOWED_PROJECTS, true ) ) {
			return;
		}

		// Ignore import if no new strings have been added/fuzzied.
		if ( ! $originals_added && ! $originals_fuzzied ) {
			return;
		}

		$project = GP::$project->get( $project_id );
		if ( ! $project ) {
			return;
		}

		$attachment = [];

		$tmpl_status = [
			'added'     => [ '*%d* new string was added', '*%d* new strings were added' ],
			'updated'   => [ '*%d* was updated', '*%d* were updated' ],
			'fuzzied'   => [ '*%d* was fuzzied', '*%d* were fuzzied' ],
			'obsoleted' => [ '*%d* was obsoleted', '*%d* were obsoleted' ],
		];

		$updates   = [
			sprintf(
				$tmpl_status['added'][ ( 1 === $originals_added ) ? 0 : 1 ],
				$originals_added
			),
			sprintf(
				$tmpl_status['fuzzied'][ ( 1 === $originals_fuzzied ) ? 0 : 1 ],
				$originals_fuzzied
			),
			sprintf(
				$tmpl_status['obsoleted'][ ( 1 === $originals_obsoleted ) ? 0 : 1 ],
				$originals_obsoleted
			)
		];

		$attachment['text'] = wp_sprintf(
			'<https://translate.wordpress.org/projects/%1$s|`%1$s`>: %2$l',
			$project->path,
			$updates
		);

		$attachment['mrkdwn_in'] = [ 'text' ];
		$attachment['color']     = '#C32283';
		$attachment['fallback']  = 'New strings are available for translation';
		$attachment['pretext']   = 'New strings are available for translation:';

		$this->slack( $attachment );
	}

	/**
	 * Sends a notifcation to the the Slack channel.
	 *
	 * @param array $attachment Attachment of the notifcation.
	 */
	function slack( $attachment ) {
		require_once API_WPORGPATH . 'includes/slack-config.php';
		$send = new \Dotorg\Slack\Send( GLOTPRESS_SLACK_WEBHOOK );
		$send->set_username( 'WordPress Translate' );
		$send->add_attachment( $attachment );
		$send->send( self::SLACK_CHANNEL );
	}
}

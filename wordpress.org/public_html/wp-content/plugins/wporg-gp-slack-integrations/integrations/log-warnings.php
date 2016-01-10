<?php
/**
 * Logs translation warnings into a public slack channel for peer-review.
 *
 * @author @dd32
 */
class WPorg_GP_Slack_Log_Warnings {

	/**
	 * Holds the channels name to send notifcations too.
	 *
	 * @var string
	 */
	private $channel = '#polyglots-warnings';

	/**
	 * Holds the list of Translation ID's which have been
	 * notified about in this process.
	 *
	 * @var array
	 */
	private $warned = array();

	public function __construct() {
		if ( ! defined( 'GLOTPRESS_SLACK_WEBHOOK' ) ) {
			return;
		}

		add_action( 'warning_discarded', array( $this, 'warning_discarded' ), 10, 5 );
		add_action( 'translation_created', array( $this, 'translation_created' ) );
		add_action( 'translation_saved', array( $this, 'translation_saved' ) );
	}

	/**
	 * Logs discared warnings into Slack
	 *
	 * @param int    $project_id      ID of the project.
	 * @param int    $translation_set ID if the translation set.
	 * @param int    $translation     ID of the translation.
	 * @param string $warning         Key of the warning. (length, tags, placeholders, both_begin_end_on_newlines)
	 * @param int    $user            ID of the user.
	 */
	function warning_discarded( $project_id, $translation_set, $translation, $warning, $user ) {
		// TODO Log it.
	}

	/**
	 * Logs translation warnings into Slack for any newly created translations.
	 *
	 * @param GP_Translation $translatiom The just-created translation.
	 */
	function translation_created( $translation ) {
		if ( ! $translation->warnings ) {
			return;
		}

		// We only want to trigger for strings which are live, or are for consideration.
		if ( ! in_array( $translation->status, array( 'current', 'waiting' ) ) ) {
			return;
		}

		$this->process_translation_warning( $translation );
	}

	/**
	 * Logs translation warnings into Slack for any existing translations that are updated.
	 *
	 * @param GP_Translation $translatiom The just-created translation.
	 */
	function translation_saved( $translation ) {
		if ( ! $translation->warnings ) {
			return;
		}

		// We only want to trigger for strings which are live, or are for consideration.
		if ( ! in_array( $translation->status, array( 'current', 'waiting' ) ) ) {
			return;
		}

		$this->process_translation_warning( $translation );
	}

	/**
	 * Logs translation warnings into Slack.
	 *
	 * @param GP_Translation $translatiom The just-created translation.
	 */
	function process_translation_warning( $translation ) {
		// Avoid processing a specific translation twice
		if ( isset( $this->warned[ $translation->id ] ) ) {
			return;
		}

		$this->warned[ $translation->id ] = true;

		$original = GP::$original->get( $translation->original_id );
		$set = GP::$translation_set->get( $translation->translation_set_id );
		$project = GP::$project->get( $original->project_id );

		$project_name = $project->name;
		$parent_project_id = $project->parent_project_id;
		while ( $parent_project_id ) {
			$parent_project = GP::$project->get( $parent_project_id );
			$parent_project_id = $parent_project->parent_project_id;
			$project_name = "{$parent_project->name} - {$project_name}";
		}

		$project_url = gp_url_join( gp_url_public_root(), 'projects', $project->path, $set->locale, '/', $set->slug ) . '?filters[warnings]=yes&sort[by]=translation_date_added';
		$translation_url = gp_url_join( gp_url_public_root(), 'projects', $project->path, $set->locale, '/', $set->slug ) . '?filters[status]=either&filters[original_id]=' . $original->id . '&filters[translation_id]=' . $translation->id;

		$message = "New <{$translation_url}|translation warning> for <{$project_url}|{$project_name}> in {$set->name} #{$set->locale}:\n";

		// GlotPress stores warnings as an array of [ translation_plural => [ warning_code => warning_string ], ... ]
		foreach ( $translation->warnings as $i => $warn ) {
			$warnings = implode( ' ', $warn );
			$original_string = $i ? $original->plural : $original->singular;

			$translation_key = "translation_{$i}";
			$translation_string = $translation->$translation_key;

			$message .= "*Warning:* {$warnings}\n\t*Original:* {$original_string}\n\t*Translation:* {$translation_string}\n";
		}

		$this->slack( $message );
	}

	/**
	 * Sends a notifcation to the the Slack channel.
	 *
	 * @param string $message Text of the notifcation.
	 */
	function slack( $message ) {
		require_once API_WPORGPATH . 'includes/slack-config.php';
		$send = new \Dotorg\Slack\Send( GLOTPRESS_SLACK_WEBHOOK );
		$send->set_username( 'Translate Warning' );
		$send->set_text( $message );
		$send->send( $this->channel );
	}
}

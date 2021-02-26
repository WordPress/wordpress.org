<?php
namespace WordPressdotorg\Plugin_Directory\Email;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * A generic email to all committers of a plugin.
 * 
 * Example:
 * $email = new Email\Generic_To_Committers(
 *   $plugin_post,
 *   [
 *     'subject' => '###PLUGIN### is amazing!',
 *     'body' => "Howdy ###USER###,\n I just wanted to let you know that I think your plugin is fantastic!',
 *   ]
 * );
 * $email->send();
 * 
 * The subject will be prefiex with `[WordPress Plugin Directory]` and the body suffixed with the plugin directory signature.
 */
class Generic_To_Committers extends Base {
	protected $required_args = [
		'subject',
		'body',
	];

	/**
	 * @param $plugin The plugin this email relates to.
	 * @param $args[] A list of args that the email requires.
	 */
	public function __construct( $plugin, $args = [], $do_not_use = [] ) {
		$committers = array_unique( array_map(
			function( $user ) {
				return get_user_by( 'login', $user )->user_email;
			},
			Tools::get_plugin_committers( $plugin->post_name )
		) );

		return parent::__construct( $plugin, $committers, $args );
	}

	function subject() {
		return $this->replace_placeholders( $this->args['subject'] );
	}

	function body() {
		return $this->replace_placeholders( $this->args['body'] );
	}

	private function replace_placeholders( $text ) {
		$items = [
			'###PLUGIN###'            => $this->plugin->post_title,
			'###URL###'               => get_permalink( $this->plugin ),
			'###SLUG###'              => $this->plugin->post_name,
			'###USER###'              => $this->user_text( $this->user ),
			'###DATETIME###'          => gmdate( 'Y-m-d H:i:s \G\M\T' ),
			'###PLUGIN_TEAM_EMAIL###' => PLUGIN_TEAM_EMAIL,
		];

		return str_replace( array_keys( $items ), array_values( $items ), $text );
	}
}

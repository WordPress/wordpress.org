<?php
/**
 * GP_Translation_Helpers class
 *
 * @package gp-translation-helpers
 * @since 0.0.1
 */
class GP_Translation_Helpers {

	/**
	 * Class id.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	public $id = 'translation-helpers';

	/**
	 * Stores an instance of each helper.
	 *
	 * @since 0.0.1
	 * @var array
	 */
	private $helpers = array();

	/**
	 * Stores the self instance.
	 *
	 * @since 0.0.1
	 * @var object
	 */
	private static $instance = null;

	/**
	 * Inits the class.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public static function init() {
		self::get_instance();
	}

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * GP_Translation_Helpers constructor.
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'register_routes' ), 5 );
		add_action( 'gp_before_request', array( $this, 'before_request' ), 10, 2 );
		add_action( 'rest_after_insert_comment', array( 'GP_Notifications', 'init' ), 10, 3 );
		add_action( 'transition_comment_status', array( 'GP_Notifications', 'on_comment_status_change' ), 10, 3 );
		add_action( 'gp_pre_tmpl_load', array( $this, 'register_comment_feedback_js' ), 10, 2 );
		add_action( 'wp_ajax_comment_with_feedback', array( $this, 'comment_with_feedback' ) );
		add_action( 'wp_ajax_optout_discussion_notifications', array( $this, 'optout_discussion_notifications' ) );

		add_thickbox();
		gp_enqueue_style( 'thickbox' );

		wp_register_style(
			'gp-discussion-css',
			plugins_url( 'css/discussion.css', __DIR__ ),
			array(),
			filemtime( plugin_dir_path( __DIR__ ) . 'css/discussion.css' )
		);
		gp_enqueue_style( 'gp-discussion-css' );

		wp_register_style(  // todo: these CSS should be integrated in GlotPress.
			'gp-translation-helpers-editor',
			plugins_url( 'css/editor.css', __DIR__ ),
			array(),
			filemtime( plugin_dir_path( __DIR__ ) . 'css/editor.css' )
		);
		gp_enqueue_style( 'gp-translation-helpers-editor' );

		add_filter( 'gp_translation_row_template_more_links', array( $this, 'translation_row_template_more_links' ), 10, 5 );
		add_filter( 'preprocess_comment', array( $this, 'preprocess_comment' ) );
		add_filter(
			'gp_tmpl_load_locations',
			function( $locations, $template, $args, $template_path ) {
				if ( 'translation-row-editor-meta-status' === $template ) {
					array_unshift( $locations, dirname( dirname( __FILE__ ) ) . '/templates/gp-templates-overrides/' );
				} else {
					$locations[] = dirname( dirname( __FILE__ ) ) . '/templates/';
				}

				return $locations;
			},
			60,
			4
		);

		$this->helpers = self::load_helpers();
	}

	/**
	 * Adds the action to load the CSS and JavaScript required only in the original-permalink template.
	 *
	 * @since 0.0.1
	 *
	 * @param string $class_name    The class name of the route.
	 * @param string $last_method   The route method that will be called.
	 *
	 * @return void
	 */
	public function before_request( string $class_name, string $last_method ) {
		if (
			in_array(
				$class_name . '::' . $last_method,
				array(
					// 'GP_Route_Translation::translations_get',
					'GP_Route_Translation_Helpers::original_permalink',
				),
				true
			)
		) {
			add_action( 'gp_pre_tmpl_load', array( $this, 'pre_tmpl_load' ), 10, 2 );
		}
	}

	/**
	 * Adds the link for the discussion in the main screen.
	 *
	 * @since 0.0.2
	 *
	 * @todo Move the inline CSS style to a CSS file when this plugin be integrated into GlotPress.
	 *
	 * @param array              $more_links         The links to be output.
	 * @param GP_Project         $project            Project object.
	 * @param GP_Locale          $locale             Locale object.
	 * @param GP_Translation_Set $translation_set    Translation Set object.
	 * @param Translation_Entry  $translation        Translation object.
	 *
	 * @return array
	 */
	public function translation_row_template_more_links( array $more_links, GP_Project $project, GP_Locale $locale, GP_Translation_Set $translation_set, Translation_Entry $translation ): array {
		$permalink = GP_Route_Translation_Helpers::get_permalink( $project->path, $translation->original_id, $translation_set->slug, $translation_set->locale );

		$links                    = '<a href="' . esc_url( $permalink ) . '">Discussion</a>';
		$links                   .= '<a href="' . esc_url( $permalink ) . '" style="float:right" target="_blank"><span class="dashicons dashicons-external"></span></a>';
		$more_links['discussion'] = $links;

		return $more_links;
	}

	/**
	 * Prevents remote POST to comment forms.
	 *
	 * @since 0.0.2
	 *
	 * @param array $commentdata     Comment data.
	 *
	 * @return array|void
	 */
	public function preprocess_comment( array $commentdata ) {
		if ( ! $commentdata['user_ID'] ) {
			die( 'User not authorized!' );
		}
				return $commentdata;
	}

	/**
	 * Loads the CSS and JavaScript required only in the original-permalink template.
	 *
	 * @since 0.0.1
	 *
	 * @param string $template  The template. E.g. "original-permalink".
	 * @param array  $args      Arguments passed to the template. Passed by reference.
	 *
	 * @return void
	 */
	public function pre_tmpl_load( string $template, array $args ):void {
		$allowed_templates = apply_filters( 'gp_translations_helpers_templates', array( 'original-permalink' ) );

		if ( ! in_array( $template, $allowed_templates, true ) ) {
			return;
		}

		$translation_helpers_settings = array(
			'th_url'   => gp_url_project( $args['project'], gp_url_join( $args['locale_slug'], $args['translation_set_slug'], '-get-translation-helpers' ) ),
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'gp_optin_optout' ),
		);

		add_action( 'gp_head', array( $this, 'css_and_js' ), 10 );
		add_action( 'gp_translation_row_editor_columns', array( $this, 'translation_helpers' ), 10, 2 );

		add_filter(
			'gp_translation_row_editor_clospan',
			function( $colspan ) {
				return ( $colspan - 3 );
			}
		);

		wp_register_style(
			'gp-translation-helpers-css',
			plugins_url( 'css/translation-helpers.css', __DIR__ ),
			array(),
			filemtime( plugin_dir_path( __DIR__ ) . 'css/translation-helpers.css' )
		);
		gp_enqueue_style( 'gp-translation-helpers-css' );

		wp_register_script(
			'gp-translation-helpers',
			plugins_url( 'js/translation-helpers.js', __DIR__ ),
			array( 'gp-editor' ),
			filemtime( plugin_dir_path( __DIR__ ) . 'js/translation-helpers.js' ),
			true
		);
		gp_enqueue_scripts( array( 'gp-translation-helpers' ) );

		wp_localize_script( 'gp-translation-helpers', '$gp_translation_helpers_settings', $translation_helpers_settings );
		wp_localize_script(
			'gp-translation-helpers',
			'wpApiSettings',
			array(
				'root'           => esc_url_raw( rest_url() ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'admin_ajax_url' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Gets the translation helpers.
	 *
	 * The returned array has the title helper as key and object of
	 * this class as value.
	 *
	 * @since 0.0.1
	 *
	 * @return array
	 */
	public static function load_helpers(): array {
		$base_dir = dirname( dirname( __FILE__ ) ) . '/helpers/';
		require_once $base_dir . '/base-helper.php';

		$helpers_files = array(
			'helper-translation-discussion.php',
			'helper-other-locales.php',
			'helper-translation-history.php',
		);

		foreach ( $helpers_files as $helper ) {
			require_once $base_dir . $helper;
		}

		$helpers = array();

		$classes = get_declared_classes();
		foreach ( $classes as $declared_class ) {
			$reflect = new ReflectionClass( $declared_class );
			if ( $reflect->isSubclassOf( 'GP_Translation_Helper' ) ) {
				$helpers[ sanitize_title_with_dashes( $reflect->getDefaultProperties()['title'] ) ] = new $declared_class();
			}
		}

		return $helpers;
	}

	/**
	 * Loads the 'translation-helpers' template.
	 *
	 * @since 0.0.1
	 *
	 * @param GP_Translation     $translation The current translation.
	 * @param GP_Translation_Set $translation_set The current translation set.
	 *
	 * @return void
	 */
	public function translation_helpers( GP_Translation $translation, GP_Translation_Set $translation_set ) {
		$args = array(
			'project_id'           => $translation->project_id,
			'locale_slug'          => $translation_set->locale,
			'translation_set_slug' => $translation_set->slug,
			'original_id'          => $translation->original_id,
			'translation_id'       => $translation->id,
			'translation'          => $translation,
		);

		$sections = array();
		foreach ( $this->helpers as $translation_helper ) {
			$translation_helper->set_data( $args );

			if ( ! $translation_helper->activate() ) {
				continue;
			}

			$sections[] = array(
				'title'             => $translation_helper->get_title(),
				'content'           => $translation_helper->get_output(),
				'classname'         => $translation_helper->get_div_classname(),
				'id'                => $translation_helper->get_div_id(),
				'priority'          => $translation_helper->get_priority(),
				'has_async_content' => $translation_helper->has_async_content(),
				'load_inline'       => $translation_helper->load_inline(),
			);
		}
		usort(
			$sections,
			function( $s1, $s2 ) {
				return $s1['priority'] > $s2['priority'];
			}
		);

		gp_tmpl_load( 'translation-helpers', array( 'sections' => $sections ) );
	}

	/**
	 * Registers the routes and the methods that will respond to these routes.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function register_routes() {
		$dir      = '([^_/][^/]*)';
		$path     = '(.+?)';
		$projects = 'projects';
		$project  = $projects . '/' . $path;
		$locale   = '(' . implode( '|', wp_list_pluck( GP_Locales::locales(), 'slug' ) ) . ')';
		$set      = "$project/$locale/$dir";
		$id       = '(\d+)-?(\d+)?';

		GP::$router->prepend( "/$project/(\d+)(?:/$locale/$dir)?(/\d+)?", array( 'GP_Route_Translation_Helpers', 'original_permalink' ), 'get' );
		GP::$router->prepend( "/$project/-get-translation-helpers/$id", array( 'GP_Route_Translation_Helpers', 'ajax_translation_helpers' ), 'get' );
		GP::$router->prepend( "/$project/$locale/$dir/-get-translation-helpers/$id", array( 'GP_Route_Translation_Helpers', 'ajax_translation_helpers_locale' ), 'get' );
		GP::$router->prepend( "/locale/$locale/$dir/discussions/?", array( 'GP_Route_Translation_Helpers', 'discussions_dashboard' ), 'get' );
	}

	/**
	 * Prints inline CSS and JavaScript.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function css_and_js() {
		?>
		<style>
			<?php
			foreach ( $this->helpers as $translation_helper ) {
				$css = $translation_helper->get_css();
				if ( $css ) {
					echo '/* Translation Helper:  ' . esc_js( $translation_helper->get_title() ) . ' */' . "\n";
					echo esc_html( $css ) . "\n";
				}
			}
			?>
		</style>
		<script>
			<?php
			foreach ( $this->helpers as $translation_helper ) {
				$js = $translation_helper->get_js();
				if ( $js ) {
					echo '/* Translation Helper:  ' . esc_js( $translation_helper->get_title() ) . ' */' . "\n";
					echo $js . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
			?>
		</script>
		<?php
	}

	/**
	 * Registers and enqueues the reject-feedback.js script and also loads a few js variables on the page.
	 *
	 * @since 0.0.2
	 *
	 *  @param string $template Template of the current page.
	 *  @param array  $translation_set Current translation set.
	 *
	 * @return void
	 */
	public function register_comment_feedback_js( $template, $translation_set ) {

		if ( 'translations' !== $template ) {
			return;
		}

		wp_register_script(
			'gp-comment-feedback-js',
			plugins_url( 'js/reject-feedback.js', __DIR__ ),
			array( 'jquery', 'gp-common', 'gp-editor', 'thickbox' ),
			filemtime( plugin_dir_path( __DIR__ ) . 'js/reject-feedback.js' )
		);
		gp_enqueue_script( 'gp-comment-feedback-js' );

		$gp_locale = GP_Locales::by_field( 'slug', $translation_set['locale_slug'] );
		wp_localize_script(
			'gp-comment-feedback-js',
			'$gp_comment_feedback_settings',
			array(
				'url'                => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( 'gp_comment_feedback' ),
				'locale_slug'        => $translation_set['locale_slug'],
				'language'           => $gp_locale ? $gp_locale->english_name : 'Unknown',
				'openai_key'         => apply_filters( 'gp_get_openai_key', null ),
				'openai_temperature' => apply_filters( 'gp_get_openai_temperature', 0.8 ),
				'comment_reasons'    => Helper_Translation_Discussion::get_comment_reasons( $translation_set['locale_slug'] ),
			)
		);

		wp_register_script(
			'gp-translation-helpers-editor',
			plugins_url( 'js/editor.js', __DIR__ ),
			array( 'gp-editor' ),
			filemtime( plugin_dir_path( __DIR__ ) . 'js/editor.js' ),
			true
		);
		gp_enqueue_scripts( array( 'gp-translation-helpers-editor' ) );

		wp_localize_script(
			'gp-translation-helpers-editor',
			'$gp_translation_helpers_editor',
			array(
				'translation_helper_url' => gp_url_project( $translation_set['project']->path, gp_url_join( $translation_set['locale_slug'], $translation_set['translation_set']->slug, '-get-translation-helpers' ) ),
				'reply_text'             => esc_attr__( 'Reply' ),
				'cancel_reply_text'      => esc_html__( 'Cancel reply' ),
			)
		);
		wp_localize_script(
			'gp-translation-helpers-editor',
			'wpApiSettings',
			array(
				'root'           => esc_url_raw( rest_url() ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'admin_ajax_url' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Is called from the AJAX request in reject-feedback.js to use ChatGPT to review a translation.
	 *
	 * @since 0.0.2
	 *
	 * @return void
	 */
	public static function fetch_openai_review() {
		check_ajax_referer( 'gp_comment_feedback', 'nonce' );
		$original    = sanitize_text_field( $_POST['data']['original'] );
		$translation = sanitize_text_field( $_POST['data']['translation'] );
		$language    = sanitize_text_field( $_POST['data']['language'] );
		$glossary    = sanitize_text_field( $_POST['data']['glossary_query'] );
		$is_retry    = filter_var( $_POST['data']['is_retry'], FILTER_VALIDATE_BOOLEAN );

		$openai_response = GP_OpenAI_Review::get_openai_review( $original, $translation, $language, $glossary, $is_retry );
		if ( isset( $openai_response['error'] ) ) {
			wp_send_json_error( $openai_response );
		}
		wp_send_json_success( $openai_response );
	}

	/**
	 * Is called from the AJAX request in reject-feedback.js to submit an comment feedback.
	 *
	 * @since 0.0.2
	 *
	 * @return void
	 */
	public function comment_with_feedback() {
		check_ajax_referer( 'gp_comment_feedback', 'nonce' );

		$helper_discussion    = new Helper_Translation_Discussion();
		$locale_slug          = $helper_discussion->sanitize_comment_locale( sanitize_text_field( $_POST['data']['locale_slug'] ) );
		$translation_status   = ! empty( $_POST['data']['translation_status'] ) ? array_map( array( $helper_discussion, 'sanitize_translation_status' ), $_POST['data']['translation_status'] ) : null;
		$translation_id_array = ! empty( $_POST['data']['translation_id'] ) ? array_map( array( $helper_discussion, 'sanitize_translation_id' ), $_POST['data']['translation_id'] ) : null;
		$original_id_array    = ! empty( $_POST['data']['original_id'] ) ? array_map( array( $helper_discussion, 'sanitize_original_id' ), $_POST['data']['original_id'] ) : null;
		$comment_reason       = ! empty( $_POST['data']['reason'] ) ? $_POST['data']['reason'] : array( 'other' );
		$all_comment_reasons  = array_keys( Helper_Translation_Discussion::get_comment_reasons( $locale_slug ) );
		$comment_reason       = array_filter(
			$comment_reason,
			function( $reason ) use ( $all_comment_reasons ) {
				return in_array( $reason, $all_comment_reasons );
			}
		);
		$comment              = sanitize_text_field( $_POST['data']['comment'] );

		if ( ! $locale_slug ) {
			wp_send_json_error( 'Oops! Locale slug missing' );
		}
		if ( ! $translation_id_array ) {
			wp_send_json_error( 'Oops! Translation ID missing' );
		}
		if ( ! $original_id_array ) {
			wp_send_json_error( 'Oops! Original ID missing' );
		}
		if ( ! $comment_reason && ! $comment ) {
			wp_send_json_error( 'Oops! No comment and reason found' );
		}

		// Get original_id and translation_id of first string in the array
		$first_original_id    = array_shift( $original_id_array );
		$first_translation_id = array_shift( $translation_id_array );

		// Post comment on discussion page for the first string
		$first_comment_id = $this->insert_comment( $comment, $first_original_id, $comment_reason, $first_translation_id, $locale_slug, $_SERVER, $translation_status );

		if ( ! empty( $original_id_array ) && ! empty( $translation_id_array ) ) {
			// For other strings post link to the comment.
			$comment = get_comment_link( $first_comment_id );
			foreach ( $original_id_array as $index => $single_original_id ) {
				$comment_id = $this->insert_comment( $comment, $single_original_id, $comment_reason, $translation_id_array[ $index ], $locale_slug, $_SERVER, $translation_status );
				$_comment   = get_comment( $comment_id );
				GP_Notifications::add_related_comment( $_comment );
			}
		}

		if ( $first_comment_id ) {
			$comment = get_comment( $first_comment_id );
			GP_Notifications::init( $comment, null, null );
		}

		wp_send_json_success( 'success' );
	}

	/**
	 * Adds or removes metadata to a user, related with the opt-in/opt-out status in a discussion
	 *
	 * It receives thought Ajax this data:
	 * - nonce.
	 * - originalId. The id of the original string related with the discussion.
	 * - optType:
	 *   - optout. Add the metadata, to opt-out from the notifications.
	 *   - optin. Removes the metadata, to opt-in from the notifications. Default status.
	 *
	 * @since 0.0.2
	 *
	 * @return void
	 */
	public function optout_discussion_notifications() {
		$nonce = sanitize_text_field( $_POST['data']['nonce'] );
		if ( ! wp_verify_nonce( $nonce, 'gp_optin_optout' ) ) {
			wp_send_json_error( esc_html__( 'Invalid nonce.' ), 403 );
		} else {
			$user_id     = get_current_user_id();
			$original_id = sanitize_text_field( $_POST['data']['originalId'] );
			$opt_type    = sanitize_text_field( $_POST['data']['optType'] );
			if ( 'optout' === $opt_type ) {
				add_user_meta( $user_id, 'gp_opt_out', $original_id );
			} elseif ( 'optin' === $opt_type ) {
				delete_user_meta( $user_id, 'gp_opt_out', $original_id );
			}
			 wp_send_json_success();
		}
	}

	/**
	 * Inserts feedback as WordPress comment.
	 *
	 *  @param string $comment        Feedback entered by reviewer.
	 *  @param int    $original_id    ID of the original where the comment will be added.
	 *  @param array  $reason         Reason(s) for comment.
	 *  @param string $translation_id ID of the commented translation.
	 *  @param string $locale_slug    Locale of the commented translation.
	 *  @param array  $server         The $_SERVER array
	 *
	 * @return false|int
	 * @since 0.0.2
	 */
	private function insert_comment( $comment, $original_id, $reason, $translation_id, $locale_slug, $server, $translation_status ) {
		$post_id = Helper_Translation_Discussion::get_or_create_shadow_post_id( $original_id );
		$user    = wp_get_current_user();
		return wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_author_url'   => $user->user_url,
				'comment_author_IP'    => sanitize_text_field( $server['REMOTE_ADDR'] ),
				'comment_content'      => $comment,
				'comment_agent'        => sanitize_text_field( $server['HTTP_USER_AGENT'] ),
				'user_id'              => $user->ID,
				'comment_meta'         => array(
					'reject_reason'      => $reason,
					'translation_id'     => $translation_id,
					'locale'             => $locale_slug,
					'translation_status' => $translation_status,
				),
			)
		);
	}

}

<?php
/**
 * Tests for marking editor requests as done.
 *
 * @package wp-i18n-teams
 */

defined( 'ABSPATH' ) || die();

/**
 * Covers who may mark an editor request on the Polyglots site as done.
 */
class WPorg_I18n_Teams_Editor_Requests_Test extends WPorg_I18n_Teams_TestCase {

	/**
	 * Locale sites the lookup answers with. The IDs only key the roles in user meta.
	 *
	 * @var int[]
	 */
	const LOCALE_SITES = [
		'de_DE' => 9001,
		'fr_FR' => 9002,
		'de_CH' => 9003,
	];

	/**
	 * Answers the locale site lookup, which needs a table this suite does not have,
	 * and works on a site at the Polyglots address.
	 */
	public function setUp(): void {
		parent::setUp();

		add_filter( 'sites_pre_query', [ $this, 'find_locale_site' ], 10, 2 );

		$polyglots = $this->factory()->blog->create(
			[
				'domain' => 'make.wordpress.org',
				'path'   => '/polyglots/',
			]
		);
		switch_to_blog( $polyglots );
	}

	/**
	 * Clears the o2 request and leaves the Polyglots site before the test's changes are rolled back.
	 */
	public function tearDown(): void {
		unset( $_REQUEST['method'], $_REQUEST['message'] );

		while ( ms_is_switched() ) {
			restore_current_blog();
		}

		parent::tearDown();
	}

	/**
	 * Returns the locale site for a query by locale.
	 *
	 * @param array|null     $sites Sites found so far.
	 * @param \WP_Site_Query $query The site query.
	 * @return array|null
	 */
	public function find_locale_site( $sites, $query ) {
		if ( empty( $query->query_vars['locale'] ) ) {
			return $sites;
		}

		$locale = $query->query_vars['locale'];

		return isset( self::LOCALE_SITES[ $locale ] ) ? [ self::LOCALE_SITES[ $locale ] ] : [];
	}

	/**
	 * Creates a user with a role on a locale site, who is not a member of the Polyglots site.
	 *
	 * @param string $locale Locale whose site the role is on.
	 * @param string $role   Role on the locale site.
	 * @return int The user ID.
	 */
	protected function create_locale_user( string $locale, string $role = 'general_translation_editor' ): int {
		global $wpdb;

		$user_id = $this->create_non_member();
		update_user_meta( $user_id, $wpdb->get_blog_prefix( self::LOCALE_SITES[ $locale ] ) . 'capabilities', [ $role => true ] );

		return $user_id;
	}

	/**
	 * Creates a published post by a requester, with the given tags.
	 *
	 * @param string[] $tags Tag names.
	 * @return int The post ID.
	 */
	protected function create_post_with_tags( array $tags ): int {
		$post_id = $this->factory()->post->create( [ 'post_author' => $this->create_non_member() ] );
		wp_set_post_tags( $post_id, $tags );

		return $post_id;
	}

	/**
	 * Sends a request to o2's write API, up to the point where o2 acts on it.
	 *
	 * @param string $method  Request method.
	 * @param array  $message Message, as o2's JavaScript sends it.
	 */
	protected function start_o2_write_request( string $method, array $message ): void {
		$_REQUEST['method']  = $method;
		$_REQUEST['message'] = wp_slash( wp_json_encode( $message ) );

		do_action( 'o2_write_api' );
	}

	/**
	 * Builds the message o2 sends to change a post's resolution.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $callback o2 callback.
	 * @return array
	 */
	protected function resolution_message( int $post_id, string $callback = 'o2_resolved_posts' ): array {
		return [
			'type'       => 'post',
			'postID'     => $post_id,
			'pluginData' => [
				'callback' => $callback,
				'data'     => [
					'postID'    => $post_id,
					'nextState' => 'resolved',
				],
			],
		];
	}

	/**
	 * Whether o2 lets a user mark a post as done, at the three points it asks 'edit_post'.
	 *
	 * @param int $user_id User ID.
	 * @param int $post_id Post ID.
	 * @return bool[] When o2 adds the control, in its write API, and in the resolved posts callback.
	 */
	protected function o2_lets_user_resolve( int $user_id, int $post_id ): array {
		wp_set_current_user( $user_id );

		$shown = null;
		$saved = null;

		// The priority o2's resolved posts module adds its control at.
		add_filter(
			'o2_filter_post_actions',
			function ( $actions, $post_ID ) use ( &$shown ) {
				$shown = current_user_can( 'edit_post', $post_ID );
				return $actions;
			},
			15,
			2
		);
		add_action(
			'o2_callback_o2_resolved_posts',
			function ( $data ) use ( &$saved ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- o2's property.
				$saved = current_user_can( 'edit_post', $data->postID );
			}
		);

		apply_filters( 'o2_filter_post_actions', [], $post_id );

		$message = $this->resolution_message( $post_id );
		$this->start_o2_write_request( 'patch', $message );
		$checked = current_user_can( 'edit_post', $post_id );
		do_action( 'o2_callback_o2_resolved_posts', (object) $message['pluginData']['data'] );

		return [ $shown, $checked, $saved ];
	}

	/**
	 * Gets a post's content as o2 shows it, for a state and log as o2's to-do module adds them.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $state   o2 state of the post.
	 * @param array  $log     o2's log entries, oldest first.
	 * @param string $content Content before the line is added.
	 * @return string
	 */
	protected function o2_content( int $post_id, string $state, array $log, string $content = '<p>Request.</p>' ): string {
		$fragment = [
			'contentFiltered' => $content,
			'postMeta'        => [
				'resolvedPostsPostState' => $state,
				'resolvedPostsAuditLogs' => $log,
			],
		];

		return apply_filters( 'o2_post_fragment', $fragment, $post_id )['contentFiltered'];
	}

	/**
	 * A request that is done shows who marked it done, and when, from o2's latest log entry.
	 */
	public function test_done_request_shows_who_marked_it_done() {
		$request = $this->create_post_with_tags( [ 'editor-requests', 'de_DE' ] );
		$log     = [
			[
				'timestamp' => strtotime( '2026-11-01 09:00:00 UTC' ),
				'log'       => 'Jane marked this to-do',
			],
			[
				'timestamp' => strtotime( '2026-11-04 10:00:00 UTC' ),
				'log'       => 'Jane marked this done',
			],
		];

		$content = $this->o2_content( $request, 'resolved', $log );

		$this->assertStringStartsWith( '<p>Request.</p>', $content );
		$this->assertStringContainsString( 'Jane marked this done on November 4, 2026', $content );
		$this->assertStringNotContainsString( 'marked this to-do', $content );
	}

	/**
	 * The line is small and muted, and goes above o2's "Mark as done" control at the end of the content.
	 */
	public function test_resolution_line_is_above_the_mark_as_done_control() {
		$request = $this->create_post_with_tags( [ 'editor-requests', 'de_DE' ] );
		$log     = [
			[
				'timestamp' => strtotime( '2026-11-04 10:00:00 UTC' ),
				'log'       => 'Jane marked this done',
			],
		];

		$content = $this->o2_content( $request, 'resolved', $log, "<p>Request.</p><nav class='o2-post-footer-actions'>Mark as done</nav>" );

		$this->assertSame(
			'<p>Request.</p><p class="wporg-editor-request-resolution has-small-font-size has-charcoal-4-color"><span aria-hidden="true">&#10003;</span> Jane marked this done on November 4, 2026</p>' .
			"<nav class='o2-post-footer-actions'>Mark as done</nav>",
			$content
		);

		$content = $this->o2_content( $request, 'resolved', $log, '<nav class="o2-post-footer-actions">In the post.</nav>' . "<nav class='o2-post-footer-actions'>Mark as done</nav>" );

		$this->assertStringStartsWith( '<nav class="o2-post-footer-actions">In the post.</nav><p class="wporg-editor-request-resolution', $content, 'A matching element in the post itself.' );
	}

	/**
	 * Only a request that is done gets the line.
	 */
	public function test_resolution_line_is_only_on_done_requests() {
		$request = $this->create_post_with_tags( [ 'editor-requests', 'de_DE' ] );
		$post    = $this->create_post_with_tags( [ 'de_DE' ] );
		$log     = [
			[
				'timestamp' => strtotime( '2026-11-04 10:00:00 UTC' ),
				'log'       => 'Jane marked this done',
			],
		];

		$this->assertSame( '<p>Request.</p>', $this->o2_content( $request, 'unresolved', $log ), 'A request still to do.' );
		$this->assertSame( '<p>Request.</p>', $this->o2_content( $request, 'resolved', [] ), 'A request done without a log entry.' );
		$this->assertSame( '<p>Request.</p>', $this->o2_content( $request, 'resolved', [ [ 'log' => 'Jane marked this done' ] ] ), 'A log entry without a time.' );
		$this->assertSame( '<p>Request.</p>', $this->o2_content( $post, 'resolved', $log ), 'A post that is not a request.' );

		switch_to_blog( get_main_site_id() );
		$other_site = $this->create_post_with_tags( [ 'editor-requests', 'de_DE' ] );
		$this->assertSame( '<p>Request.</p>', $this->o2_content( $other_site, 'resolved', $log ), 'A request on another site.' );
	}

	/**
	 * A GTE of the locale a request is for can mark it as done.
	 */
	public function test_locale_editor_can_resolve_request_for_their_locale() {
		$request = $this->create_post_with_tags( [ 'editor-requests', 'de_DE' ] );

		$this->assertSame( [ true, true, true ], $this->o2_lets_user_resolve( $this->create_locale_user( 'de_DE' ), $request ) );
	}

	/**
	 * A locale manager of the locale a request is for can mark it as done.
	 */
	public function test_locale_manager_can_resolve_request_for_their_locale() {
		$request = $this->create_post_with_tags( [ 'editor-requests', 'de_DE' ] );

		$this->assertSame( [ true, true, true ], $this->o2_lets_user_resolve( $this->create_locale_user( 'de_DE', 'locale_manager' ), $request ) );
	}

	/**
	 * Editors are assigned to the parent locale, so they decide requests for its variants.
	 */
	public function test_variant_locale_request_is_decided_by_parent_locale_editors() {
		$request = $this->create_post_with_tags( [ 'editor-requests', 'de_DE_formal' ] );

		$this->assertSame( [ true, true, true ], $this->o2_lets_user_resolve( $this->create_locale_user( 'de_DE' ), $request ) );
	}

	/**
	 * A request tagged with a locale and its variant is for one locale.
	 */
	public function test_request_for_a_locale_and_its_variant_is_for_one_locale() {
		$request = $this->create_post_with_tags( [ 'editor-requests', 'de_DE', 'de_DE_formal' ] );

		$this->assertSame( [ true, true, true ], $this->o2_lets_user_resolve( $this->create_locale_user( 'de_DE' ), $request ) );
	}

	/**
	 * A variant of another locale still makes a request for several locales.
	 */
	public function test_request_with_a_variant_of_another_locale_stays_with_the_site_editors() {
		// de_CH_informal is a variant of de_CH, which has its own editors.
		$request = $this->create_post_with_tags( [ 'editor-requests', 'de_DE', 'de_CH_informal' ] );

		$this->assertSame( [ false, false, false ], $this->o2_lets_user_resolve( $this->create_locale_user( 'de_DE' ), $request ) );
		$this->assertSame( [ false, false, false ], $this->o2_lets_user_resolve( $this->create_locale_user( 'de_CH' ), $request ) );
	}

	/**
	 * A GTE of another locale cannot mark the request as done.
	 */
	public function test_editor_of_another_locale_cannot_resolve() {
		$request = $this->create_post_with_tags( [ 'editor-requests', 'de_DE' ] );

		$this->assertSame( [ false, false, false ], $this->o2_lets_user_resolve( $this->create_locale_user( 'fr_FR' ), $request ) );
	}

	/**
	 * A project translation editor of the locale cannot mark the request as done.
	 */
	public function test_project_translation_editor_cannot_resolve() {
		$request = $this->create_post_with_tags( [ 'editor-requests', 'de_DE' ] );

		$this->assertSame( [ false, false, false ], $this->o2_lets_user_resolve( $this->create_locale_user( 'de_DE', 'translation_editor' ), $request ) );
	}

	/**
	 * A request for several locales is left to the requester and the site's editors.
	 */
	public function test_request_for_several_locales_stays_with_the_site_editors() {
		$request = $this->create_post_with_tags( [ 'editor-requests', 'de_DE', 'fr_FR' ] );

		$this->assertSame( [ false, false, false ], $this->o2_lets_user_resolve( $this->create_locale_user( 'de_DE' ), $request ) );
	}

	/**
	 * A cross-locale PTE request is left to the requester and the site's editors.
	 */
	public function test_cross_locale_pte_request_stays_with_the_site_editors() {
		$request = $this->create_post_with_tags( [ 'editor-requests', 'clpte', 'de_DE' ] );

		$this->assertSame( [ false, false, false ], $this->o2_lets_user_resolve( $this->create_locale_user( 'de_DE' ), $request ) );
	}

	/**
	 * Posts that are not editor requests are not affected.
	 */
	public function test_other_posts_tagged_with_the_locale_are_not_affected() {
		$post = $this->create_post_with_tags( [ 'de_DE' ] );

		$this->assertSame( [ false, false, false ], $this->o2_lets_user_resolve( $this->create_locale_user( 'de_DE' ), $post ) );
	}

	/**
	 * Requests on any other site are not affected.
	 */
	public function test_requests_on_other_sites_are_not_affected() {
		switch_to_blog( get_main_site_id() );
		$request = $this->create_post_with_tags( [ 'editor-requests', 'de_DE' ] );

		$this->assertSame( [ false, false, false ], $this->o2_lets_user_resolve( $this->create_locale_user( 'de_DE' ), $request ) );
	}

	/**
	 * The requester can still mark their own request as done.
	 */
	public function test_requester_can_still_resolve_their_own_request() {
		$request = $this->create_post_with_tags( [ 'editor-requests', 'de_DE', 'fr_FR' ] );

		$this->assertSame( [ true, true, true ], $this->o2_lets_user_resolve( (int) get_post_field( 'post_author', $request ), $request ) );
	}

	/**
	 * The permission covers o2's resolve control and nothing else on the page.
	 */
	public function test_permission_does_not_reach_other_checks_on_the_page() {
		$request = $this->create_post_with_tags( [ 'editor-requests', 'de_DE' ] );
		wp_set_current_user( $this->create_locale_user( 'de_DE' ) );

		$other_action = null;
		add_filter(
			'o2_filter_post_actions',
			function ( $actions, $post_ID ) use ( &$other_action ) {
				$other_action = current_user_can( 'edit_post', $post_ID );
				return $actions;
			},
			10,
			2
		);

		$before = current_user_can( 'edit_post', $request );
		apply_filters( 'o2_filter_post_actions', [], $request );
		$after = current_user_can( 'edit_post', $request );

		$this->assertFalse( $before, 'Outside o2.' );
		$this->assertFalse( $other_action, 'In another o2 post action.' );
		$this->assertFalse( $after, 'After o2 has added its actions.' );
	}

	/**
	 * A request to o2's write API gets the permission only to change that post's resolution.
	 */
	public function test_permission_covers_only_resolution_requests() {
		$request = $this->create_post_with_tags( [ 'editor-requests', 'de_DE' ] );
		$other   = $this->create_post_with_tags( [ 'editor-requests', 'de_DE' ] );
		wp_set_current_user( $this->create_locale_user( 'de_DE' ) );

		$this->start_o2_write_request( 'update', $this->resolution_message( $request ) );
		$this->assertFalse( current_user_can( 'edit_post', $request ), 'Updating the post.' );

		$this->start_o2_write_request( 'patch', $this->resolution_message( $request, 'o2_sticky_posts' ) );
		$this->assertFalse( current_user_can( 'edit_post', $request ), 'Another callback.' );

		$this->start_o2_write_request( 'patch', [ 'type' => 'comment' ] + $this->resolution_message( $request ) );
		$this->assertFalse( current_user_can( 'edit_post', $request ), 'A comment message.' );

		$this->start_o2_write_request( 'patch', $this->resolution_message( $other ) );
		$this->assertFalse( current_user_can( 'edit_post', $request ), 'Resolving another post.' );
		$this->assertTrue( current_user_can( 'edit_post', $other ), 'The post being resolved.' );
		$this->assertTrue( current_user_can( 'edit_post', get_post( $other ) ), 'The post being resolved, passed as an object.' );
		$this->assertFalse( current_user_can( 'delete_post', $other ), 'Deleting the post being resolved.' );
	}
}

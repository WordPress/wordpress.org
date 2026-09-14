<?php
/**
 * Tests for the o2 Posting Access plugin.
 *
 * @package wporg-o2-posting-access
 */

defined( 'ABSPATH' ) || die();

use WordPressdotorg\o2\Posting_Access\Plugin;

/**
 * Covers who may post on an o2 site, and what happens to what they post.
 */
class WPorg_O2_Posting_Access_Test extends WPorg_O2_Posting_Access_TestCase {

	/**
	 * A user who is not a member of the blog and has never published.
	 *
	 * @var int
	 */
	protected $non_member;

	/**
	 * The plugin instance under test.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Sets up a non-member acting as the current user.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->plugin     = new Plugin();
		$this->non_member = $this->create_non_member();

		wp_set_current_user( $this->non_member );
	}

	/*
	 * Capabilities: the grant that makes the o2 front end usable at all.
	 */

	/**
	 * Non-members get posting capabilities so the o2 editor renders for them.
	 * o2 gates both its post form and its create endpoint on 'publish_posts'.
	 */
	public function test_non_member_receives_posting_capabilities() {
		$this->assertTrue( user_can( $this->non_member, 'edit_posts' ) );
		$this->assertTrue( user_can( $this->non_member, 'publish_posts' ) );
		$this->assertTrue( user_can( $this->non_member, 'edit_published_posts' ) );
	}

	/**
	 * The grant must not extend to capabilities the plugin never intended.
	 */
	public function test_non_member_receives_no_other_capabilities() {
		$this->assertFalse( user_can( $this->non_member, 'edit_others_posts' ) );
		$this->assertFalse( user_can( $this->non_member, 'delete_posts' ) );
		$this->assertFalse( user_can( $this->non_member, 'unfiltered_html' ) );
		$this->assertFalse( user_can( $this->non_member, 'manage_options' ) );
	}

	/**
	 * Logged out visitors must never be granted anything.
	 */
	public function test_logged_out_user_receives_no_capabilities() {
		wp_set_current_user( 0 );

		$this->assertFalse( current_user_can( 'edit_posts' ) );
		$this->assertFalse( current_user_can( 'publish_posts' ) );
	}

	/**
	 * The guard reads the filter's $user argument rather than the current user,
	 * so a capability check made *about* another user while someone is logged
	 * in must not grant that user anything.
	 */
	public function test_grant_does_not_leak_to_other_users() {
		$this->assertFalse( user_can( 0, 'publish_posts' ) );
	}

	/*
	 * REST reads: 'edit_posts' is granted for writing, but core also reads it as
	 * the gate for querying non-public statuses. Rows stay hidden either way --
	 * what leaks without the restriction is 'X-WP-Total', which is taken from the
	 * query before the per-item permission filter runs.
	 */

	/**
	 * Seeds three drafts belonging to somebody else.
	 *
	 * @return int The other author's user ID.
	 */
	protected function seed_others_drafts() {
		$author = $this->factory()->user->create( array( 'role' => 'author' ) );

		$this->factory()->post->create_many(
			3,
			array(
				'post_status' => 'draft',
				'post_author' => $author,
				'post_title'  => 'Unannounced release',
			)
		);

		return $author;
	}

	/**
	 * Runs a posts collection request and returns its total.
	 *
	 * @param array  $params Query parameters to set on the request.
	 * @param string $route  Optional. Collection route to request.
	 * @return array The response status, total, and row count.
	 */
	protected function query_posts( $params, $route = '/wp/v2/posts' ) {
		$request = new WP_REST_Request( 'GET', $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_do_request( $request );
		$headers  = $response->get_headers();

		return array(
			'status' => $response->get_status(),
			'total'  => isset( $headers['X-WP-Total'] ) ? (int) $headers['X-WP-Total'] : null,
			'rows'   => count( (array) $response->get_data() ),
		);
	}

	/**
	 * The count of other people's drafts must not be reported to a non-member.
	 */
	public function test_non_member_cannot_count_others_unpublished_posts() {
		$this->seed_others_drafts();

		$result = $this->query_posts(
			array(
				'context' => 'edit',
				'status'  => array( 'draft', 'pending', 'private' ),
			)
		);

		$this->assertLessThan( 400, $result['status'], 'Core still permits the request; the restriction is on what it counts.' );
		$this->assertSame( 0, $result['total'] );
		$this->assertSame( 0, $result['rows'] );
	}

	/**
	 * 'search' matches title and body, so an unrestricted count answers "does a
	 * hidden draft contain this string" one character at a time.
	 */
	public function test_non_member_cannot_search_others_unpublished_posts() {
		$this->seed_others_drafts();

		$result = $this->query_posts(
			array(
				'context' => 'edit',
				'status'  => array( 'draft' ),
				'search'  => 'Unannounced',
			)
		);

		$this->assertSame( 0, $result['total'] );
	}

	/**
	 * The 'author' parameter must not survive the restriction, or the count
	 * attributes hidden posts to the person who wrote them.
	 */
	public function test_non_member_cannot_attribute_others_unpublished_posts() {
		$author = $this->seed_others_drafts();

		$result = $this->query_posts(
			array(
				'context' => 'edit',
				'status'  => array( 'draft' ),
				'author'  => array( $author ),
			)
		);

		$this->assertSame( 0, $result['total'] );
	}

	/**
	 * The restriction scopes to the caller rather than refusing outright, so a
	 * non-member can still see the submission they are waiting on.
	 */
	public function test_non_member_still_sees_their_own_pending_post() {
		$this->seed_others_drafts();
		wp_insert_post(
			array(
				'post_title'  => 'My submission',
				'post_status' => 'pending',
				'post_author' => $this->non_member,
			)
		);

		$result = $this->query_posts(
			array(
				'context' => 'edit',
				'status'  => array( 'pending' ),
			)
		);

		$this->assertSame( 1, $result['total'] );
		$this->assertSame( 1, $result['rows'] );
	}

	/**
	 * Published posts are public, so the ordinary collection is left alone.
	 */
	public function test_non_member_published_queries_are_untouched() {
		wp_set_current_user( 0 );
		$this->factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		wp_set_current_user( $this->non_member );

		$result = $this->query_posts( array() );

		$this->assertSame( 2, $result['total'] );
		$this->assertSame( 2, $result['rows'] );
	}

	/**
	 * Members are outside the grant, so nothing about their queries changes.
	 */
	public function test_member_queries_are_untouched() {
		$this->seed_others_drafts();
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'editor' ) ) );

		$result = $this->query_posts(
			array(
				'context' => 'edit',
				'status'  => array( 'draft' ),
			)
		);

		$this->assertSame( 3, $result['total'] );
		$this->assertSame( 3, $result['rows'] );
	}

	/**
	 * Core's 'wp_block' maps both 'read' and 'edit_posts' onto 'edit_posts', so
	 * the grant reaches the patterns route as well and unpublished pattern
	 * content is searchable there unless every post type is covered.
	 */
	public function test_non_member_cannot_count_others_unpublished_patterns() {
		$author = $this->factory()->user->create( array( 'role' => 'author' ) );

		$this->factory()->post->create_many(
			2,
			array(
				'post_type'   => 'wp_block',
				'post_status' => 'draft',
				'post_author' => $author,
				'post_title'  => 'Unannounced pattern',
			)
		);

		$result = $this->query_posts(
			array(
				'context' => 'edit',
				'status'  => array( 'draft' ),
				'search'  => 'Unannounced',
			),
			'/wp/v2/blocks'
		);

		$this->assertLessThan( 400, $result['status'], 'Core still permits the request; the restriction is on what it counts.' );
		$this->assertSame( 0, $result['total'] );
		$this->assertSame( 0, $result['rows'] );
	}

	/**
	 * Post types are registered on 'init', long after this plugin loads, so the
	 * filter has to attach as they arrive rather than from a fixed list.
	 *
	 * The filter is asserted directly rather than over a request, because REST
	 * routes are registered on 'rest_api_init' and a post type registered mid-test
	 * has none.
	 */
	public function test_post_type_registered_after_load_is_restricted() {
		register_post_type(
			'wporg_test_rest_cpt',
			array(
				'public'          => true,
				'show_in_rest'    => true,
				'capability_type' => 'post',
			)
		);

		$args = apply_filters( 'rest_wporg_test_rest_cpt_query', array( 'post_status' => array( 'draft' ) ) );

		unregister_post_type( 'wporg_test_rest_cpt' );

		$this->assertSame( $this->non_member, $args['author'] );
	}

	/**
	 * 'inherit' is the default status of the attachments route and defers to the
	 * parent post, so restricting it would break media listings for no gain.
	 */
	public function test_inherit_status_is_not_restricted() {
		$args = array(
			'post_status' => array( 'inherit' ),
			'author__in'  => array( 12 ),
		);

		$this->assertSame( $args, $this->plugin->restrict_non_public_queries( $args ) );
	}

	/*
	 * Publishing policy: user_can_publish() decides who skips review.
	 */

	/**
	 * A non-member with no published post has not earned publishing rights.
	 */
	public function test_user_can_publish_is_false_for_fresh_non_member() {
		$this->assertFalse( $this->plugin->user_can_publish( $this->non_member ) );
	}

	/**
	 * Blog members always publish directly.
	 */
	public function test_user_can_publish_is_true_for_member() {
		$member = $this->factory()->user->create( array( 'role' => 'contributor' ) );

		$this->assertTrue( $this->plugin->user_can_publish( $member ) );
	}

	/**
	 * A user who does not exist can never publish.
	 */
	public function test_user_can_publish_is_false_for_unknown_user() {
		$this->assertFalse( $this->plugin->user_can_publish( PHP_INT_MAX ) );
	}

	/**
	 * The 'moderation_keys' option blocks a user whose email address matches,
	 * and it is checked before the published-post allowance.
	 */
	public function test_user_can_publish_respects_moderation_keys() {
		$spammer = $this->create_non_member( array( 'user_email' => 'nuisance@blocked.example' ) );

		wp_set_current_user( 0 );
		$this->factory()->post->create(
			array(
				'post_author' => $spammer,
				'post_status' => 'publish',
			)
		);

		update_option( 'moderation_keys', "blocked.example\nsomething-else" );

		$this->assertFalse( $this->plugin->user_can_publish( $spammer ) );
	}

	/**
	 * The 'wporg_o2_user_can_publish' filter has the final say.
	 */
	public function test_user_can_publish_is_filterable() {
		wp_set_current_user( 0 );
		$this->factory()->post->create(
			array(
				'post_author' => $this->non_member,
				'post_status' => 'publish',
			)
		);

		$deny = '__return_false';
		add_filter( 'wporg_o2_user_can_publish', $deny );
		$result = $this->plugin->user_can_publish( $this->non_member );
		remove_filter( 'wporg_o2_user_can_publish', $deny );

		$this->assertFalse( $result );
	}

	/*
	 * Submissions are held for review, whichever write path they arrive on.
	 */

	/**
	 * The REST controller does not pass 'post_author'; wp_insert_post() resolves
	 * it from the current user into $data. Reading the author from $postarr
	 * instead of $data would miss this case entirely, so it is pinned explicitly.
	 */
	public function test_publish_without_explicit_author_is_downgraded() {
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'No explicit author',
				'post_content' => 'Body.',
				'post_status'  => 'publish',
			)
		);

		$this->assertNotInstanceOf( WP_Error::class, $post_id );
		$this->assertSame( 'pending', get_post_status( $post_id ) );
	}

	/**
	 * The straightforward case: an explicit self-authored publish.
	 */
	public function test_publish_with_explicit_author_is_downgraded() {
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Explicit author',
				'post_content' => 'Body.',
				'post_status'  => 'publish',
				'post_author'  => $this->non_member,
			)
		);

		$this->assertSame( 'pending', get_post_status( $post_id ) );
	}

	/**
	 * The REST controller is one of the paths that does not run o2's own filter.
	 */
	public function test_rest_create_is_downgraded() {
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$request->set_body_params(
			array(
				'title'   => 'Created over REST',
				'content' => 'Body.',
				'status'  => 'publish',
			)
		);

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertLessThan( 400, $response->get_status(), 'Creation itself should still be permitted.' );
		$this->assertSame( 'pending', $data['status'] );
		$this->assertSame( 'pending', get_post_status( $data['id'] ) );
	}

	/**
	 * Scheduling is publishing with a delay, so 'future' has to be caught too.
	 */
	public function test_future_status_is_downgraded() {
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Scheduled',
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
				'post_author' => $this->non_member,
			)
		);

		$this->assertSame( 'pending', get_post_status( $post_id ) );
	}

	/**
	 * 'private' bypasses review just as 'publish' does.
	 */
	public function test_private_status_is_downgraded() {
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Private',
				'post_status' => 'private',
				'post_author' => $this->non_member,
			)
		);

		$this->assertSame( 'pending', get_post_status( $post_id ) );
	}

	/**
	 * Publishing must not be reachable by updating an existing pending post.
	 */
	public function test_updating_own_pending_post_to_publish_is_downgraded() {
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Two step',
				'post_status' => 'pending',
				'post_author' => $this->non_member,
			)
		);

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$this->assertSame( 'pending', get_post_status( $post_id ) );
	}

	/**
	 * The capabilities granted are the defaults for every post type registered
	 * with capability_type 'post', so the downgrade has to cover those too.
	 */
	public function test_custom_post_type_publish_is_downgraded() {
		register_post_type(
			'wporg_test_cpt',
			array(
				'public'          => true,
				'capability_type' => 'post',
			)
		);

		$post_id = wp_insert_post(
			array(
				'post_title'  => 'CPT publish',
				'post_type'   => 'wporg_test_cpt',
				'post_status' => 'publish',
				'post_author' => $this->non_member,
			)
		);

		unregister_post_type( 'wporg_test_cpt' );

		$this->assertSame( 'pending', get_post_status( $post_id ) );
	}

	/**
	 * The o2 front end path downgrades through the 'o2_create_post' filter,
	 * before the post ever reaches wp_insert_post().
	 */
	public function test_o2_create_post_is_downgraded() {
		$post              = new stdClass();
		$post->post_author = $this->non_member;
		$post->post_status = 'publish';

		$filtered = $this->plugin->save_new_post_as_pending( $post );

		$this->assertSame( 'pending', $filtered->post_status );
	}

	/**
	 * The same filter leaves a member's post alone.
	 */
	public function test_o2_create_post_is_not_downgraded_for_member() {
		$member = $this->factory()->user->create( array( 'role' => 'author' ) );

		$post              = new stdClass();
		$post->post_author = $member;
		$post->post_status = 'publish';

		$filtered = $this->plugin->save_new_post_as_pending( $post );

		$this->assertSame( 'publish', $filtered->post_status );
	}

	/*
	 * The downgrade runs on every insert site-wide, so it must be narrow.
	 */

	/**
	 * A blog member publishing normally must be untouched.
	 */
	public function test_member_publish_is_not_downgraded() {
		$member = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $member );

		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Member post',
				'post_status' => 'publish',
				'post_author' => $member,
			)
		);

		$this->assertSame( 'publish', get_post_status( $post_id ) );
	}

	/**
	 * Programmatic inserts -- cron, WP-CLI, importers -- run with no current
	 * user. Downgrading those would silently unpublish content site-wide.
	 */
	public function test_insert_with_no_current_user_is_not_downgraded() {
		wp_set_current_user( 0 );

		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Cron post',
				'post_status' => 'publish',
				'post_author' => $this->non_member,
			)
		);

		$this->assertSame( 'publish', get_post_status( $post_id ) );
	}

	/**
	 * An editor publishing on behalf of a non-member is a deliberate editorial
	 * act and must not be downgraded.
	 */
	public function test_editorial_publish_on_behalf_of_non_member_is_not_downgraded() {
		$editor = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );

		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Published for someone else',
				'post_status' => 'publish',
				'post_author' => $this->non_member,
			)
		);

		$this->assertSame( 'publish', get_post_status( $post_id ) );
	}

	/**
	 * The o2 create path calls get_default_post_to_edit( 'post', true ), which
	 * inserts an auto-draft before the real save. Clamping that would break the
	 * front end editor for everyone.
	 */
	public function test_auto_draft_is_not_downgraded() {
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Auto draft',
				'post_status' => 'auto-draft',
				'post_author' => $this->non_member,
			)
		);

		$this->assertSame( 'auto-draft', get_post_status( $post_id ) );
	}

	/**
	 * Drafts are already unpublished and must be left alone.
	 */
	public function test_draft_is_not_downgraded() {
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Draft',
				'post_status' => 'draft',
				'post_author' => $this->non_member,
			)
		);

		$this->assertSame( 'draft', get_post_status( $post_id ) );
	}

	/**
	 * Once a non-member has a published post they are trusted to publish
	 * directly -- the plugin's trust-on-first-approval design.
	 */
	public function test_non_member_with_published_post_may_publish() {
		/*
		 * Seeded with no current user, because creating it as the non-member
		 * would send the fixture itself to 'pending' -- which is exactly what
		 * the rest of this suite asserts.
		 */
		wp_set_current_user( 0 );
		$this->factory()->post->create(
			array(
				'post_author' => $this->non_member,
				'post_status' => 'publish',
			)
		);
		wp_set_current_user( $this->non_member );

		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Second post',
				'post_status' => 'publish',
				'post_author' => $this->non_member,
			)
		);

		$this->assertSame( 'publish', get_post_status( $post_id ) );
	}

	/**
	 * The moderation workflow the downgrade exists to feed: a moderator
	 * approving a non-member's pending post must actually publish it.
	 */
	public function test_moderator_can_approve_a_pending_post() {
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Awaiting review',
				'post_status' => 'publish',
				'post_author' => $this->non_member,
			)
		);

		$this->assertSame( 'pending', get_post_status( $post_id ), 'Precondition: the post starts out pending.' );

		$editor = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$this->assertSame( 'publish', get_post_status( $post_id ) );
	}

	/*
	 * How a post awaiting review is presented.
	 */

	/**
	 * Pending posts are labelled so nobody mistakes one for published content.
	 */
	public function test_pending_post_title_is_prefixed() {
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Awaiting review',
				'post_status' => 'pending',
				'post_author' => $this->non_member,
			)
		);

		$this->assertSame( 'Pending Review: Awaiting review', $this->plugin->prepend_pending_notice( 'Awaiting review', $post_id ) );
	}

	/**
	 * Published posts keep their title untouched.
	 */
	public function test_published_post_title_is_not_prefixed() {
		wp_set_current_user( 0 );
		$post_id = $this->factory()->post->create(
			array(
				'post_title'  => 'Live post',
				'post_status' => 'publish',
			)
		);

		$this->assertSame( 'Live post', $this->plugin->prepend_pending_notice( 'Live post', $post_id ) );
	}

	/**
	 * Comments stay shut on a post that is not publicly visible yet.
	 */
	public function test_comments_are_closed_for_pending_posts() {
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Awaiting review',
				'post_status' => 'pending',
				'post_author' => $this->non_member,
			)
		);

		$this->assertFalse( $this->plugin->close_comments_for_pending_posts( true, $post_id ) );
	}

	/**
	 * Published posts keep whatever comment status they already had.
	 */
	public function test_comments_are_left_open_for_published_posts() {
		wp_set_current_user( 0 );
		$post_id = $this->factory()->post->create(
			array(
				'post_title'  => 'Live post',
				'post_status' => 'publish',
			)
		);

		$this->assertTrue( $this->plugin->close_comments_for_pending_posts( true, $post_id ) );
	}

	/**
	 * The submit button tells the user their post will be reviewed.
	 */
	public function test_post_button_is_relabelled_for_non_members() {
		$this->assertSame( 'Submit for review', $this->plugin->replace_post_button_label( 'Post', 'Post', 'Verb, to post', 'o2' ) );
	}

	/**
	 * Members keep the plain "Post" button.
	 */
	public function test_post_button_is_not_relabelled_for_members() {
		$member = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $member );

		$this->assertSame( 'Post', $this->plugin->replace_post_button_label( 'Post', 'Post', 'Verb, to post', 'o2' ) );
	}

	/**
	 * Unrelated strings pass through untouched.
	 */
	public function test_unrelated_translations_are_untouched() {
		$this->assertSame( 'Post', $this->plugin->replace_post_button_label( 'Post', 'Post', 'Noun, a post', 'o2' ) );
		$this->assertSame( 'Post', $this->plugin->replace_post_button_label( 'Post', 'Post', 'Verb, to post', 'default' ) );
	}

	/*
	 * Writes: the granted capabilities are primitive, so every write path that
	 * takes a post ID has to be held to 'edit_post' on that specific ID.
	 */

	/**
	 * Seeds a post belonging to somebody else, plus a revision of it.
	 *
	 * Seeded with no current user so the pending downgrade does not apply and
	 * the fixture really is another author's private, published content.
	 *
	 * @param string $status Optional. Status for the seeded post.
	 * @return array{0:int,1:WP_Post} The post ID and its latest revision.
	 */
	protected function seed_others_post_with_revision( $status = 'private' ) {
		$current = get_current_user_id();
		wp_set_current_user( 0 );

		$author  = $this->factory()->user->create( array( 'role' => 'author' ) );
		$post_id = $this->factory()->post->create(
			array(
				'post_author'  => $author,
				'post_status'  => $status,
				'post_title'   => 'Embargoed plan',
				'post_content' => 'First draft, with the part that was later removed.',
			)
		);

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Second draft.',
			)
		);

		$revisions = wp_get_post_revisions( $post_id );
		$revision  = array_shift( $revisions );

		wp_set_current_user( $current );

		$this->assertInstanceOf( WP_Post::class, $revision, 'The fixture needs a revision to be meaningful.' );

		return array( $post_id, $revision );
	}

	/**
	 * Creates a post owned by the non-member to hang foreign objects off.
	 *
	 * @return int The new post's ID.
	 */
	protected function create_own_post() {
		return wp_insert_post(
			array(
				'post_title'   => 'Carrier',
				'post_content' => 'Body.',
				'post_status'  => 'publish',
				'post_author'  => $this->non_member,
			)
		);
	}

	/**
	 * The reparent itself: a non-member must not be able to take ownership of
	 * another author's revision by pointing it at a post of their own.
	 */
	public function test_non_member_cannot_reparent_another_authors_revision() {
		list( $post_id, $revision ) = $this->seed_others_post_with_revision();
		$carrier                    = $this->create_own_post();

		$result = wp_update_post(
			array(
				'ID'          => $revision->ID,
				'post_parent' => $carrier,
			)
		);

		$this->assertSame( 0, $result );
		$this->assertSame( $post_id, (int) get_post( $revision->ID )->post_parent );
	}

	/**
	 * What the reparent would buy: core authorizes a revision read against its
	 * parent, so a revision that still names its real parent stays unreadable.
	 */
	public function test_non_member_cannot_read_another_authors_revision() {
		list( $post_id, $revision ) = $this->seed_others_post_with_revision();
		$carrier                    = $this->create_own_post();

		wp_update_post(
			array(
				'ID'          => $revision->ID,
				'post_parent' => $carrier,
			)
		);

		foreach ( array( $post_id, $carrier ) as $parent ) {
			$request = new WP_REST_Request( 'GET', "/wp/v2/posts/{$parent}/revisions/{$revision->ID}" );
			$request->set_param( 'context', 'edit' );

			$response = rest_do_request( $request );

			$this->assertGreaterThanOrEqual( 400, $response->get_status(), "Revision was readable under parent {$parent}." );
			$this->assertStringNotContainsString( 'later removed', wp_json_encode( $response->get_data() ) );
		}
	}

	/**
	 * Hierarchical content: reparenting somebody else's page also rewrites its
	 * permalink, so the same check has to cover pages.
	 */
	public function test_non_member_cannot_reparent_another_authors_page() {
		wp_set_current_user( 0 );
		$author  = $this->factory()->user->create( array( 'role' => 'editor' ) );
		$section = $this->factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_author' => $author,
			)
		);
		$page_id = $this->factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_author' => $author,
				'post_parent' => $section,
			)
		);
		wp_set_current_user( $this->non_member );

		$carrier = $this->create_own_post();

		$result = wp_update_post(
			array(
				'ID'          => $page_id,
				'post_parent' => $carrier,
			)
		);

		$this->assertSame( 0, $result );
		$this->assertSame( $section, (int) get_post( $page_id )->post_parent );
	}

	/**
	 * The check is on the object rather than on one field, so a caller supplying
	 * content for somebody else's post is refused the same way a caller supplying
	 * a parent is.
	 */
	public function test_non_member_cannot_overwrite_another_authors_post() {
		list( $post_id ) = $this->seed_others_post_with_revision( 'publish' );

		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Overwritten.',
				'post_title'   => 'Overwritten.',
			)
		);

		$this->assertSame( 0, $result );
		$this->assertSame( 'Second draft.', get_post( $post_id )->post_content );
	}

	/**
	 * Attachments are routed through wp_insert_attachment(), a separate entry
	 * point into the same insert, so they are pinned too.
	 */
	public function test_non_member_cannot_reparent_another_authors_attachment() {
		wp_set_current_user( 0 );
		$author        = $this->factory()->user->create( array( 'role' => 'editor' ) );
		$attachment_id = $this->factory()->attachment->create( array( 'post_author' => $author ) );
		wp_set_current_user( $this->non_member );

		$carrier = $this->create_own_post();

		wp_update_post(
			array(
				'ID'          => $attachment_id,
				'post_parent' => $carrier,
			)
		);

		$this->assertNotSame( $carrier, (int) get_post( $attachment_id )->post_parent );
	}

	/**
	 * The legitimate case the caller exists for: attaching an upload of your own
	 * to a post of your own has to keep working.
	 */
	public function test_non_member_can_attach_their_own_attachment_to_their_own_post() {
		$carrier       = $this->create_own_post();
		$attachment_id = $this->factory()->attachment->create( array( 'post_author' => $this->non_member ) );

		wp_update_post(
			array(
				'ID'          => $attachment_id,
				'post_parent' => $carrier,
			)
		);

		$this->assertSame( $carrier, (int) get_post( $attachment_id )->post_parent );
	}

	/**
	 * Editing your own submission is the whole point of the grant.
	 */
	public function test_non_member_can_still_update_their_own_post() {
		$post_id = $this->create_own_post();

		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Edited by the author.',
			)
		);

		$this->assertSame( $post_id, $result );
		$this->assertSame( 'Edited by the author.', get_post( $post_id )->post_content );
	}

	/**
	 * Membership is not the boundary: a role that carries no claim over other
	 * people's posts gets the same treatment as a non-member. o2's update path
	 * runs the same attachment cleanup as its create path, so an author editing
	 * a post of their own reaches it too.
	 */
	public function test_author_member_cannot_reparent_another_authors_revision() {
		list( $post_id, $revision ) = $this->seed_others_post_with_revision();

		$member = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $member );

		$carrier = wp_insert_post(
			array(
				'post_title'   => 'Member carrier',
				'post_content' => 'Body.',
				'post_status'  => 'publish',
				'post_author'  => $member,
			)
		);

		$result = wp_update_post(
			array(
				'ID'          => $revision->ID,
				'post_parent' => $carrier,
			)
		);

		$this->assertSame( 0, $result );
		$this->assertSame( $post_id, (int) get_post( $revision->ID )->post_parent );
	}

	/**
	 * Members are unaffected: an editor edits other people's posts by design.
	 */
	public function test_member_can_still_update_another_authors_post() {
		list( $post_id ) = $this->seed_others_post_with_revision( 'publish' );

		$editor = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );

		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Edited by an editor.',
			)
		);

		$this->assertSame( $post_id, $result );
		$this->assertSame( 'Edited by an editor.', get_post( $post_id )->post_content );
	}

	/**
	 * 'edit_others_posts' is only the generic name for the capability, and a post
	 * type can name its own. Somebody who may edit other people's posts is still
	 * refused on a type whose capability they were never given, because the check
	 * asks about the object rather than about a capability name.
	 */
	public function test_generic_others_capability_does_not_exempt_a_custom_post_type() {
		register_post_type(
			'wporg_capped_cpt',
			array(
				'public'          => true,
				'map_meta_cap'    => true,
				'capability_type' => array( 'wporg_test_capped', 'wporg_test_cappeds' ),
			)
		);

		wp_set_current_user( 0 );
		$author  = $this->factory()->user->create( array( 'role' => 'author' ) );
		$post_id = $this->factory()->post->create(
			array(
				'post_type'    => 'wporg_capped_cpt',
				'post_author'  => $author,
				'post_content' => 'Original.',
			)
		);

		$editor = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );

		$has_generic_cap = current_user_can( 'edit_others_posts' );
		$can_edit_object = current_user_can( 'edit_post', $post_id );

		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Overwritten.',
			)
		);

		unregister_post_type( 'wporg_capped_cpt' );

		$this->assertTrue( $has_generic_cap, 'The fixture needs the generic capability for this test to mean anything.' );
		$this->assertFalse( $can_edit_object, 'The fixture needs the object check to fail for this test to mean anything.' );
		$this->assertSame( 0, $result );
		$this->assertSame( 'Original.', get_post( $post_id )->post_content );
	}

	/**
	 * The other half of that: holding the post type's own capabilities satisfies
	 * the object check, so a handbook editor keeps working on handbook pages.
	 */
	public function test_post_type_capability_permits_the_update() {
		register_post_type(
			'wporg_capped_cpt',
			array(
				'public'          => true,
				'map_meta_cap'    => true,
				'capability_type' => array( 'wporg_test_capped', 'wporg_test_cappeds' ),
			)
		);

		wp_set_current_user( 0 );
		$author  = $this->factory()->user->create( array( 'role' => 'author' ) );
		$post_id = $this->factory()->post->create(
			array(
				'post_type'    => 'wporg_capped_cpt',
				'post_author'  => $author,
				'post_content' => 'Original.',
			)
		);

		$editor = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );

		$grant = function ( $caps ) {
			$caps['edit_others_wporg_test_cappeds']    = true;
			$caps['edit_published_wporg_test_cappeds'] = true;

			return $caps;
		};
		add_filter( 'user_has_cap', $grant );

		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Edited by a handbook editor.',
			)
		);

		remove_filter( 'user_has_cap', $grant );
		unregister_post_type( 'wporg_capped_cpt' );

		$this->assertSame( $post_id, $result );
		$this->assertSame( 'Edited by a handbook editor.', get_post( $post_id )->post_content );
	}

	/**
	 * The status half of the same point: another author's private post needs
	 * 'edit_private_posts' on top of 'edit_others_posts', and the object check
	 * asks for both because map_meta_cap() does.
	 */
	public function test_others_capability_alone_does_not_reach_a_private_post() {
		wp_set_current_user( 0 );
		$author  = $this->factory()->user->create( array( 'role' => 'author' ) );
		$post_id = $this->factory()->post->create(
			array(
				'post_author'  => $author,
				'post_status'  => 'private',
				'post_content' => 'Original.',
			)
		);

		$user = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user );

		// A role holding 'edit_others_posts' without 'edit_private_posts'.
		$grant = function ( $caps ) {
			$caps['edit_others_posts']    = true;
			$caps['edit_published_posts'] = true;

			return $caps;
		};
		add_filter( 'user_has_cap', $grant );

		$has_others_cap  = current_user_can( 'edit_others_posts' );
		$can_edit_object = current_user_can( 'edit_post', $post_id );

		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Overwritten.',
			)
		);

		remove_filter( 'user_has_cap', $grant );

		$this->assertTrue( $has_others_cap, 'The fixture needs the others capability for this test to mean anything.' );
		$this->assertFalse( $can_edit_object, 'The fixture needs the object check to fail for this test to mean anything.' );
		$this->assertSame( 0, $result );
		$this->assertSame( 'Original.', get_post( $post_id )->post_content );
	}

	/**
	 * Core registers custom_css without map_meta_cap, which makes 'edit_post'
	 * resolve to the type's own 'edit_css' without recursing, and that is not a
	 * capability any role is granted. Ownership has nothing to do with it: the
	 * check fails for the administrator who saved the CSS in the first place just
	 * as readily. The Customizer writes Additional CSS through wp_update_post(),
	 * so a type answering that way has to be left to its own authorization.
	 */
	public function test_post_type_without_meta_capability_mapping_is_untouched() {
		wp_set_current_user( 0 );
		$first_admin = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$css_id      = $this->factory()->post->create(
			array(
				'post_type'    => 'custom_css',
				'post_status'  => 'publish',
				'post_title'   => 'wporg-test-theme',
				'post_author'  => $first_admin,
				'post_content' => 'body { color: #000; }',
			)
		);

		$admin = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$can_edit_object = current_user_can( 'edit_post', $css_id );

		$result = wp_update_post(
			array(
				'ID'           => $css_id,
				'post_content' => 'body { color: #fff; }',
			)
		);

		$this->assertFalse( $can_edit_object, 'This test is about a type whose edit_post check denies even an administrator.' );
		$this->assertSame( $css_id, $result );
		$this->assertSame( 'body { color: #fff; }', get_post( $css_id )->post_content );
	}

	/**
	 * A revision whose parent row is gone resolves to no post type at all. Core
	 * denies 'edit_post' in that case, so the guard has to ask rather than treat
	 * an unresolved type as permission.
	 */
	public function test_orphaned_revision_is_still_refused() {
		global $wpdb;

		list( $post_id, $revision ) = $this->seed_others_post_with_revision();
		$carrier                    = $this->create_own_post();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Orphaning a revision has no API equivalent.
		$wpdb->delete( $wpdb->posts, array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );
		clean_post_cache( $revision->ID );

		$can_edit_object = current_user_can( 'edit_post', $revision->ID );

		$result = wp_update_post(
			array(
				'ID'          => $revision->ID,
				'post_parent' => $carrier,
			)
		);

		$this->assertFalse( $can_edit_object, 'Core denies the orphan, so the guard has something to agree with.' );
		$this->assertSame( 0, $result );
		$this->assertNotSame( $carrier, (int) get_post( $revision->ID )->post_parent );
	}

	/**
	 * Resolving a revision with no parent must not fall through to the global
	 * $post: get_post( 0 ) returns it, so the capability model would be read off
	 * whatever the request happens to be rendering.
	 */
	public function test_revision_with_no_parent_does_not_consult_the_global_post() {
		global $wpdb, $post;

		list( , $revision ) = $this->seed_others_post_with_revision();
		$carrier            = $this->create_own_post();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Detaching a revision has no API equivalent.
		$wpdb->update( $wpdb->posts, array( 'post_parent' => 0 ), array( 'ID' => $revision->ID ) );
		clean_post_cache( $revision->ID );

		$previous_global = $post;

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Standing in for a request that is rendering a post.
		$post = get_post(
			$this->factory()->post->create(
				array(
					'post_type'   => 'custom_css',
					'post_status' => 'publish',
					'post_title'  => 'wporg-test-theme',
				)
			)
		);

		$result = wp_update_post(
			array(
				'ID'          => $revision->ID,
				'post_parent' => $carrier,
			)
		);

		$post = $previous_global;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertSame( 0, $result );
		$this->assertNotSame( $carrier, (int) get_post( $revision->ID )->post_parent );
	}

	/**
	 * A refused update reports itself as empty content, which says nothing about
	 * why, so the action is the only thing an operator has to go on.
	 */
	public function test_refusal_fires_an_action() {
		list( $post_id ) = $this->seed_others_post_with_revision( 'publish' );

		$refused = array();
		$spy     = function ( $refused_post_id, $user_id ) use ( &$refused ) {
			$refused[] = array( $refused_post_id, $user_id );
		};
		add_action( 'wporg_o2_posting_access_update_refused', $spy, 10, 2 );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Overwritten.',
			)
		);

		remove_action( 'wporg_o2_posting_access_update_refused', $spy, 10 );

		$this->assertSame( array( array( $post_id, $this->non_member ) ), $refused );
	}

	/**
	 * Changing one field re-saves the whole row, and the row goes through the
	 * current user's content filters on the way. A caller without 'unfiltered_html'
	 * therefore rewrites the stored content of a post written by somebody who had
	 * it, quite apart from whatever the caller was trying to change. The refusal
	 * has to land before any of that: no rewritten content, no revision on the
	 * victim's post, no save_post.
	 */
	public function test_refused_update_leaves_the_stored_row_alone() {
		wp_set_current_user( 0 );
		$author = $this->factory()->user->create( array( 'role' => 'editor' ) );
		grant_super_admin( $author );
		wp_set_current_user( $author );

		$raw     = '<p>Keep</p><script>alert(1)</script><div onclick="x()">Hi</div>';
		$post_id = wp_insert_post(
			array(
				'post_author'  => $author,
				'post_status'  => 'publish',
				'post_title'   => 'Written with unfiltered_html',
				'post_content' => $raw,
			)
		);

		$this->assertSame( $raw, get_post( $post_id )->post_content, 'The fixture needs markup the attacker could not have written.' );

		wp_set_current_user( $this->non_member );
		$carrier = $this->create_own_post();

		$saved = 0;
		$spy   = function ( $saved_post_id ) use ( &$saved, $post_id ) {
			if ( $saved_post_id === $post_id ) {
				++$saved;
			}
		};
		add_action( 'save_post', $spy );

		$result = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_parent' => $carrier,
			)
		);

		remove_action( 'save_post', $spy );
		clean_post_cache( $post_id );

		$this->assertSame( 0, $result );
		$this->assertSame( $raw, get_post( $post_id )->post_content );
		$this->assertSame( 0, (int) get_post( $post_id )->post_parent );
		$this->assertCount( 0, wp_get_post_revisions( $post_id ) );
		$this->assertSame( 0, $saved );
	}

	/**
	 * Cron, WP-CLI and importers run with no current user and must not be caught.
	 */
	public function test_update_with_no_current_user_is_untouched() {
		list( $post_id ) = $this->seed_others_post_with_revision( 'publish' );

		wp_set_current_user( 0 );

		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Edited by a script.',
			)
		);

		$this->assertSame( $post_id, $result );
	}

	/**
	 * A genuinely empty post still reports itself as empty, so the filter's
	 * original meaning is preserved for everyone else.
	 */
	public function test_empty_content_is_still_reported_empty() {
		$post_id = $this->create_own_post();

		$this->assertTrue( $this->plugin->restrict_updates_to_editable_posts( true, array( 'ID' => $post_id ) ) );
		$this->assertTrue( $this->plugin->restrict_updates_to_editable_posts( true, array() ) );
	}

	/**
	 * Creating a post supplies no ID, so nothing about creation changes.
	 */
	public function test_insert_without_an_id_is_untouched() {
		$this->assertFalse( $this->plugin->restrict_updates_to_editable_posts( false, array() ) );
		$this->assertFalse( $this->plugin->restrict_updates_to_editable_posts( false, array( 'ID' => 0 ) ) );
	}
}

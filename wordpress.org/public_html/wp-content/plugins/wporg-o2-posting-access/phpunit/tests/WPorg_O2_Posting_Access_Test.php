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
	 * @param array $params Query parameters to set on the request.
	 * @return array The response status, total, and row count.
	 */
	protected function query_posts( $params ) {
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );

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
}

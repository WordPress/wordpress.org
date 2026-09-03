<?php
/**
 * Tests for the component pages on make.wordpress.org/core.
 *
 * @package trac-notifications
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

// phpcs:disable WordPress.Security.NonceVerification, WordPress.NamingConventions.ValidHookName -- The tests build the requests wp-admin/post.php receives and fire its load hook.

/**
 * Covers who can create and edit component pages, and who can say who maintains them.
 */
class WPorg_Trac_Components_Test extends WPorg_Trac_Components_TestCase {

	/**
	 * An editor, who authors the component page.
	 *
	 * @var int
	 */
	protected int $editor;

	/**
	 * A contributor listed as an active maintainer of the component page.
	 *
	 * @var int
	 */
	protected int $maintainer;

	/**
	 * A contributor who is not listed on any component page.
	 *
	 * @var int
	 */
	protected int $bystander;

	/**
	 * The component page.
	 *
	 * @var int
	 */
	protected int $component;

	/**
	 * Creates a published component page with one contributor maintaining it.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->editor     = $this->factory->user->create( array( 'role' => 'editor' ) );
		$this->maintainer = $this->factory->user->create( array( 'role' => 'contributor' ) );
		$this->bystander  = $this->factory->user->create( array( 'role' => 'contributor' ) );

		$this->component = $this->factory->post->create(
			array(
				'post_type'   => Make_Core_Trac_Components::POST_TYPE_NAME,
				'post_status' => 'publish',
				'post_author' => $this->editor,
				'post_title'  => 'Media',
			)
		);

		update_post_meta( $this->component, '_active_maintainers', $this->login( $this->maintainer ) . ', someone-else' );
	}

	/**
	 * Returns a user's login.
	 *
	 * @param int $user_id The user ID.
	 * @return string
	 */
	protected function login( int $user_id ): string {
		return get_userdata( $user_id )->user_login;
	}

	/**
	 * Builds the request the classic editor submits when the current user saves a post.
	 *
	 * @param int   $post_id The post being saved.
	 * @param array $fields  Fields merged over the defaults. A null value leaves the field out.
	 * @return void
	 */
	protected function submit_edit( int $post_id, array $fields = array() ): void {
		$post = get_post( $post_id );

		$_POST    = array_merge(
			array(
				'action'      => 'editpost',
				'post_ID'     => $post_id,
				'post_type'   => $post->post_type,
				'post_author' => $post->post_author,
				'post_title'  => $post->post_title,
				'content'     => $post->post_content,
				'_wpnonce'    => wp_create_nonce( 'update-post_' . $post_id ),
			),
			$fields
		);
		$_POST    = array_filter(
			$_POST,
			function ( $value ) {
				return null !== $value;
			}
		);
		$_REQUEST = $_POST;

		do_action( 'load-post.php' );
	}

	/*
	 * Creating component pages.
	 */

	/**
	 * Component pages are set up by the people who run the site, not by contributors.
	 */
	public function test_a_contributor_cannot_create_a_component_page(): void {
		$create = get_post_type_object( Make_Core_Trac_Components::POST_TYPE_NAME )->cap->create_posts;

		$this->assertFalse( user_can( $this->bystander, $create ) );
		$this->assertFalse( user_can( $this->maintainer, $create ) );
		$this->assertTrue( user_can( $this->editor, $create ) );
	}

	/*
	 * Editing component pages.
	 */

	/**
	 * A maintainer can edit and publish the page they are listed on, even though an editor authored it.
	 */
	public function test_a_maintainer_can_edit_their_component_page(): void {
		$this->assertTrue( user_can( $this->maintainer, 'edit_post', $this->component ) );
		$this->assertTrue( user_can( $this->maintainer, 'publish_post', $this->component ) );
		$this->assertTrue( user_can( $this->maintainer, 'edit_post', get_post( $this->component ) ), 'The check is also made with a post object.' );
	}

	/**
	 * The list is typed by hand, and logins resolve regardless of case elsewhere.
	 */
	public function test_a_maintainer_listed_in_a_different_case_can_edit_their_component_page(): void {
		update_post_meta( $this->component, '_active_maintainers', strtoupper( $this->login( $this->maintainer ) ) );

		$this->assertTrue( user_can( $this->maintainer, 'edit_post', $this->component ) );
		$this->assertFalse( user_can( $this->bystander, 'edit_post', $this->component ) );
	}

	/**
	 * Being listed on one page says nothing about any other page.
	 */
	public function test_a_maintainer_cannot_edit_other_pages(): void {
		$other = $this->factory->post->create(
			array(
				'post_type'   => Make_Core_Trac_Components::POST_TYPE_NAME,
				'post_status' => 'publish',
				'post_author' => $this->editor,
			)
		);
		$post  = $this->factory->post->create( array( 'post_author' => $this->editor ) );

		$this->assertFalse( user_can( $this->maintainer, 'edit_post', $other ) );
		$this->assertFalse( user_can( $this->maintainer, 'edit_post', $post ) );
		$this->assertFalse( user_can( $this->bystander, 'edit_post', $this->component ) );
	}

	/**
	 * The maintainer list only means something on a component page.
	 */
	public function test_the_maintainer_list_is_ignored_on_other_post_types(): void {
		$post = $this->factory->post->create( array( 'post_author' => $this->editor ) );
		update_post_meta( $post, '_active_maintainers', $this->login( $this->maintainer ) );

		$this->assertFalse( user_can( $this->maintainer, 'edit_post', $post ) );
	}

	/**
	 * Maintaining a page never amounts to the plural capability, whatever the request carries.
	 */
	public function test_a_component_id_in_the_request_does_not_grant_edit_others_posts(): void {
		wp_set_current_user( $this->maintainer );

		$_POST    = array(
			'post_ID'  => $this->component,
			'action'   => 'editpost',
			'_wpnonce' => wp_create_nonce( 'update-post_' . $this->component ),
		);
		$_REQUEST = $_POST;

		$this->assertFalse( current_user_can( 'edit_others_posts' ) );
		$this->assertFalse( user_can( $this->maintainer, 'edit_others_posts' ) );
	}

	/*
	 * Saving component pages through wp-admin/post.php.
	 */

	/**
	 * The edit form submits the page's author; for a maintainer's save that field is dropped.
	 */
	public function test_saving_their_page_drops_the_submitted_author_for_a_maintainer(): void {
		wp_set_current_user( $this->maintainer );
		$this->submit_edit( $this->component );

		$this->assertArrayNotHasKey( 'post_author', $_POST );
		$this->assertFalse( current_user_can( 'edit_others_posts' ), 'No capability is granted for the save.' );
	}

	/**
	 * A contributor who does not maintain the page has their request left alone.
	 */
	public function test_saving_a_page_is_left_alone_for_a_bystander(): void {
		wp_set_current_user( $this->bystander );
		$this->submit_edit( $this->component );

		$this->assertArrayHasKey( 'post_author', $_POST );
	}

	/**
	 * The request has to be the real save, not something merely naming the page.
	 */
	public function test_saving_with_an_invalid_nonce_is_left_alone(): void {
		wp_set_current_user( $this->maintainer );
		$this->submit_edit( $this->component, array( '_wpnonce' => 'nope' ) );

		$this->assertArrayHasKey( 'post_author', $_POST );
	}

	/**
	 * Reassigning the page to someone else is an editor's decision, so core gets to see it.
	 */
	public function test_saving_with_a_changed_author_is_left_alone(): void {
		wp_set_current_user( $this->maintainer );
		$this->submit_edit( $this->component, array( 'post_author' => $this->maintainer ) );

		$this->assertArrayHasKey( 'post_author', $_POST );

		$this->submit_edit( $this->component, array( 'post_author_override' => $this->maintainer ) );

		$this->assertArrayHasKey( 'post_author', $_POST );
	}

	/**
	 * A maintainer's save goes through core's own save path and keeps the author.
	 */
	public function test_a_maintainer_can_save_a_page_they_did_not_author(): void {
		wp_set_current_user( $this->maintainer );
		$this->submit_edit( $this->component, array( 'post_title' => 'Media and Uploads' ) );

		edit_post();

		$post = get_post( $this->component );
		$this->assertSame( 'Media and Uploads', $post->post_title );
		$this->assertSame( $this->editor, (int) $post->post_author );
		$this->assertSame( 'publish', $post->post_status );
	}

	/**
	 * Core fills in the current user as author when the field is missing; the page keeps its author anyway.
	 */
	public function test_a_save_without_an_author_keeps_the_page_author(): void {
		wp_set_current_user( $this->maintainer );
		$this->submit_edit(
			$this->component,
			array(
				'post_author' => null,
				'post_title'  => 'Media and Uploads',
			)
		);

		edit_post();

		$post = get_post( $this->component );
		$this->assertSame( 'Media and Uploads', $post->post_title );
		$this->assertSame( $this->editor, (int) $post->post_author );
	}

	/**
	 * The same holds for any update a maintainer makes, Quick Edit included, while an editor can reassign.
	 */
	public function test_only_an_editor_can_change_the_page_author(): void {
		wp_set_current_user( $this->maintainer );
		wp_update_post(
			array(
				'ID'          => $this->component,
				'post_author' => $this->maintainer,
			)
		);
		$this->assertSame( $this->editor, (int) get_post( $this->component )->post_author );

		wp_set_current_user( $this->editor );
		wp_update_post(
			array(
				'ID'          => $this->component,
				'post_author' => $this->maintainer,
			)
		);
		$this->assertSame( $this->maintainer, (int) get_post( $this->component )->post_author );

		wp_set_current_user( 0 );
		wp_update_post(
			array(
				'ID'          => $this->component,
				'post_author' => $this->editor,
			)
		);
		$this->assertSame( $this->editor, (int) get_post( $this->component )->post_author, 'Updates with no user signed in, like WP-CLI, are left alone.' );
	}

	/**
	 * Core refuses the same save from a contributor who is not a maintainer.
	 *
	 * The suite's wp_die() prints and returns, so the refusal is turned into an
	 * exception here to stop edit_post() where a request would have stopped.
	 */
	public function test_a_bystander_cannot_save_a_component_page(): void {
		wp_set_current_user( $this->bystander );
		$this->submit_edit( $this->component, array( 'post_title' => 'Media and Uploads' ) );

		add_filter(
			'wp_die_handler',
			function () {
				return function ( $message ) {
					throw new RuntimeException( esc_html( (string) $message ) );
				};
			}
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'not allowed to edit this post' );

		edit_post();
	}

	/*
	 * The maintainer list.
	 */

	/**
	 * A maintainer sees who maintains the page but gets no field to change it.
	 */
	public function test_the_settings_box_gives_a_maintainer_no_field_for_the_list(): void {
		wp_set_current_user( $this->maintainer );

		ob_start();
		$this->components->meta_box_cb( get_post( $this->component ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( $this->login( $this->maintainer ), $output );
		$this->assertStringNotContainsString( 'name="active-maintainers"', $output );
	}

	/**
	 * An editor gets the field.
	 */
	public function test_the_settings_box_gives_an_editor_the_field_for_the_list(): void {
		wp_set_current_user( $this->editor );

		ob_start();
		$this->components->meta_box_cb( get_post( $this->component ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="active-maintainers"', $output );
	}

	/**
	 * A maintainer submitting the field anyway changes nothing.
	 */
	public function test_a_maintainer_cannot_change_the_maintainer_list(): void {
		wp_set_current_user( $this->maintainer );
		$this->submit_edit(
			$this->component,
			array(
				'component-settings-nonce' => wp_create_nonce( 'component-settings_' . $this->component ),
				'active-maintainers'       => $this->login( $this->maintainer ) . ', ' . $this->login( $this->bystander ),
			)
		);

		edit_post();

		$this->assertSame( $this->login( $this->maintainer ) . ', someone-else', get_post_meta( $this->component, '_active_maintainers', true ) );
	}

	/**
	 * An editor can change the list.
	 */
	public function test_an_editor_can_change_the_maintainer_list(): void {
		wp_set_current_user( $this->editor );
		$this->submit_edit(
			$this->component,
			array(
				'component-settings-nonce' => wp_create_nonce( 'component-settings_' . $this->component ),
				'active-maintainers'       => $this->login( $this->bystander ),
			)
		);

		edit_post();

		$this->assertSame( $this->login( $this->bystander ), get_post_meta( $this->component, '_active_maintainers', true ) );
		$this->assertTrue( user_can( $this->bystander, 'edit_post', $this->component ) );
		$this->assertFalse( user_can( $this->maintainer, 'edit_post', $this->component ) );
	}
}

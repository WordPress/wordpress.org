<?php
declare( strict_types=1 );

// phpcs:ignoreFile -- Isolated tests intentionally define stub WordPress and bbPress functions across namespaces.

namespace {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
	}
}

namespace WordPressdotorg\Forums {
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		Tests\Badges_Test::$hooks[] = compact( 'hook_name', 'callback', 'priority', 'accepted_args' );
	}

	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		add_filter( $hook_name, $callback, $priority, $accepted_args );
	}

	function apply_filters( $hook_name, $value ) {
		return Tests\Badges_Test::$filters[ $hook_name ] ?? $value;
	}

	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}

	function bbp_get_moderator_role() {
		return 'bbp_moderator';
	}

	function bbp_get_reply_author_id( $reply_id ) {
		return Tests\Badges_Test::$reply_authors[ $reply_id ] ?? 0;
	}

	function bbp_is_reply_published( $reply_id ) {
		return Tests\Badges_Test::$published_replies[ $reply_id ] ?? false;
	}

	function bbp_get_reply_post_type() {
		return 'reply';
	}

	function bbp_get_public_status_id() {
		return 'publish';
	}
}

namespace WordPressdotorg\Profiles {
	function has_badge( $badge, $user_id ) {
		return \WordPressdotorg\Forums\Tests\Badges_Test::$existing_badges[ "{$badge}:{$user_id}" ] ?? false;
	}

	function assign_badge( $badge, $user_id ) {
		\WordPressdotorg\Forums\Tests\Badges_Test::$assigned_badges[] = compact( 'badge', 'user_id' );

		return true;
	}
}

namespace WordPressdotorg\Forums\Tests {
	use PHPUnit\Framework\TestCase;
	use WordPressdotorg\Forums\Badges;

	require_once dirname( __DIR__ ) . '/inc/class-badges.php';

	class Fake_WPDB {
		public $posts = 'wp_posts';
		public $reply_counts = array();
		public $queries = array();

		public function prepare( $query, ...$args ) {
			if ( 1 === count( $args ) && is_array( $args[0] ) ) {
				$args = $args[0];
			}

			$this->queries[] = compact( 'query', 'args' );

			return array( $query, $args );
		}

		public function get_var( $prepared ) {
			$user_id = $prepared[1][0] ?? 0;
			$limit   = end( $prepared[1] );

			return min( $this->reply_counts[ $user_id ] ?? 0, $limit );
		}

	}

	class Badges_Test extends TestCase {
		public static $assigned_badges = array();
		public static $existing_badges = array();
		public static $filters = array();
		public static $hooks = array();
		public static $published_replies = array();
		public static $reply_authors = array();

		protected function setUp(): void {
			parent::setUp();

			global $wpdb;

			$wpdb                   = new Fake_WPDB();
			self::$assigned_badges  = array();
			self::$existing_badges  = array();
			self::$filters          = array();
			self::$hooks            = array();
			self::$published_replies = array();
			self::$reply_authors    = array();
		}

		public function test_moderator_role_assignment_awards_support_team_badge() {
			$badges = new Badges();

			$badges->maybe_award_team_badge_for_role( 123, 'bbp_moderator' );

			$this->assertSame(
				array(
					array(
						'badge'   => 'support-team',
						'user_id' => 123,
					),
				),
				self::$assigned_badges
			);
		}

		public function test_non_moderator_role_assignment_does_not_award_support_team_badge() {
			$badges = new Badges();

			$badges->maybe_award_team_badge_for_role( 123, 'bbp_participant' );

			$this->assertSame( array(), self::$assigned_badges );
		}

		public function test_published_reply_at_threshold_awards_support_contributor_badge() {
			global $wpdb;

			$wpdb->reply_counts[123] = 50;
			self::$published_replies[456] = true;
			self::$reply_authors[456] = 123;

			$badges = new Badges();
			$badges->maybe_award_contributor_badge_for_reply( 456 );

			$this->assertSame(
				array(
					array(
						'badge'   => 'support-contributor',
						'user_id' => 123,
					),
				),
				self::$assigned_badges
			);
		}

		public function test_unpublished_reply_does_not_award_support_contributor_badge() {
			global $wpdb;

			$wpdb->reply_counts[123] = 50;
			self::$published_replies[456] = false;
			self::$reply_authors[456] = 123;

			$badges = new Badges();
			$badges->maybe_award_contributor_badge_for_reply( 456 );

			$this->assertSame( array(), self::$assigned_badges );
		}

		public function test_existing_support_contributor_badge_skips_reply_count() {
			global $wpdb;

			$wpdb->reply_counts[123] = 50;
			self::$existing_badges['support-contributor:123'] = true;
			self::$published_replies[456] = true;
			self::$reply_authors[456] = 123;

			$badges = new Badges();
			$badges->maybe_award_contributor_badge_for_reply( 456 );

			$this->assertSame( array(), self::$assigned_badges );
			$this->assertSame( array(), $wpdb->queries );
		}
	}
}

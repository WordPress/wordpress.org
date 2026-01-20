<?php
/**
 * Tag handling customizations.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

class Tags {

	/** @var string The name of the option that stores the tag remaps. */
	const REMAP_OPTION_NAME = 'photo_directory_tag_remaps';

	/** @var bool Indicates if merge processing has started, to prevent re-entrancy. */
	private static $processing_merge = false;

	/** @var string[] Memoized array of taxonomies that have merge functionality enabled, as determined by `get_mergeable_taxonomies()`. */
	protected static $mergeable_taxonomies = [];

	/**
	 * Initializer.
	 */
	public static function init() {
		// Redirect archives for merged and renamed tags.
		add_action( 'template_redirect', [ __CLASS__, 'redirect_tags' ] );

		// Filter AI tag assignment to prevent assigning tags that match existing photo colors or categories.
		add_filter( 'wporg_photos_pre_tags_assign', [ __CLASS__, 'filter_out_existing_taxonomy_terms' ], 5,2 );

		// Filter AI tag assignment to remap tags to their final destinations.
		add_filter( 'wporg_photos_pre_tags_assign', [ __CLASS__, 'remap_tags_during_assignment' ], 10, 3 );

		// Prevent creation of terms that remap to existing terms.
		add_filter( 'pre_insert_term', [ __CLASS__, 'prevent_remapped_term_creation' ], 10, 3 );

		// Handle quick edit merge.
		add_action( 'wp_ajax_inline-save-tax',      [ __CLASS__, 'maybe_handle_quick_edit_merge' ], 1 );

		if ( is_admin() ) {
			self::init_admin();
		}
	}

	/**
	 * Initializes admin area related functionality and changes.
	 */
	public static function init_admin() {
		foreach ( self::get_mergeable_taxonomies() as $taxonomy ) {
			add_action( "{$taxonomy}_edit_form_fields", [ __CLASS__, 'inject_custom_slug_description' ], 20, 2 );
			add_action( "edited_{$taxonomy}",           [ __CLASS__, 'process_rename' ], 10, 2 );
		}
		add_action( 'admin_notices',              [ __CLASS__, 'admin_notices' ] );
		add_action( 'load-edit-tags.php',         [ __CLASS__, 'maybe_handle_term_merge_submit' ] );
		add_filter( 'redirect_term_location',     [ __CLASS__, 'maybe_redirect_merged_term' ], 10, 2 );

		// Hide the new tag form and prevent direct tag creation.
		add_action( "{$taxonomy}_add_form",       [ __CLASS__, 'hide_add_tag_form' ], 1 );
		add_filter( 'pre_insert_term',            [ __CLASS__, 'prevent_direct_tag_creation' ], 10, 3 );
	}

	/**
	 * Returns the taxonomies which have the merge functionality enabled.
	 *
	 * @return string[]
	 */
	public static function get_mergeable_taxonomies() {
		return self::$mergeable_taxonomies = self::$mergeable_taxonomies
			?: [ Registrations::get_taxonomy( 'tags' ) ];
	}

	/**
	 * Determines if a taxonomy is a mergeable taxonomy.
	 *
	 * @param string $taxonomy The taxonomy name.
	 * @return bool True if the taxonomy is mergeable, else false.
	 */
	public static function is_mergeable_taxonomy( $taxonomy ) {
		return in_array( $taxonomy, self::get_mergeable_taxonomies(), true );
	}

	/**
	 * Outputs a hidden custom description paragraph for the slug field.
	 *
	 * @param WP_Term $term     The current term object.
	 * @param string  $taxonomy The taxonomy slug.
	 */
	public static function inject_custom_slug_description( $term, $taxonomy ) {
		?>
		<p class="description" id="custom-merge-slug-description" style="display:none;">
			<?php esc_html_e( 'The &#8220;slug&#8221; is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.', 'wporg-photos' ); ?>
			<br><br>
			<?php echo wp_kses(
				__( '<strong>Note:</strong> If you change this to the slug of an existing tag, all posts for this tag will be merged into the existing tag and this tag will be deleted. Archive redirects will be added accordingly.', 'wporg-photos' ),
				[ 'strong' => [] ]
			); ?>
		</p>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const customDesc = document.getElementById('custom-merge-slug-description');
			const slugRow = document.querySelector('.form-field.term-slug-wrap');
			if (customDesc && slugRow) {
				const defaultDesc = slugRow.querySelector('p.description');
				if (defaultDesc) {
					defaultDesc.innerHTML = customDesc.innerHTML;
				}
				customDesc.style.display = 'none'; // Remain hidden, just in case.
			}
		});
		</script>
		<?php
	}

	/**
	 * Hides the new tag form for mergeable taxonomies.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 */
	public static function hide_add_tag_form( $taxonomy ) {
		if (
			self::is_mergeable_taxonomy( $taxonomy )
			&& isset( $_GET['post_type'] )
			&& Registrations::get_post_type() === $_GET['post_type']
		) {
			echo '<style>#col-left { display: none !important; } #col-right { float: none !important; width: 100% !important; }</style>';
		}
	}

	/**
	 * Prevents direct tag creation for mergeable taxonomies.
	 *
	 * @param mixed  $term     The term data.
	 * @param string $taxonomy The taxonomy.
	 * @param array  $args     Additional arguments.
	 * @return mixed|WP_Error The term data or WP_Error to prevent creation.
	 */
	public static function prevent_direct_tag_creation( $term, $taxonomy, $args ) {
		if ( self::is_mergeable_taxonomy( $taxonomy ) ) {
			$post_type = $_POST['post_type'] ?? '';
			$photo_post_type = Registrations::get_post_type();
			// Check if this is coming from the add tag form (and not programmatic).
			if (
				wp_doing_ajax()
				&& isset( $_POST['action'] )
				&& 'add-tag' === $_POST['action']
				&& $photo_post_type === $post_type
			) {
				return new \WP_Error( 'tag_creation_disabled',
					__( 'Direct tag creation is disabled. Please use the merge functionality instead.', 'wporg-photos' )
				);
			}
		}

		return $term;
	}

	/**
	 * Handles tag rename.
	 *
	 * @param int $term_id The ID of the term just edited.
	 * @param int $tt_id   The term taxonomy ID.
	 */
	public static function process_rename( $term_id, $tt_id ) {
		// Get taxonomy from the term.
		$term = get_term( $term_id );
		if ( ! $term instanceof \WP_Term ) {
			return;
		}
		$taxonomy = $term->taxonomy;

		// Bail if not a mergeable taxonomy.
		if ( ! self::is_mergeable_taxonomy( $taxonomy ) ) {
			return;
		}

		// Get the old slug and count from the transient.
		$key  = self::pre_rename_key( $taxonomy, $term_id );
		$data = get_transient( $key );

		// Bail if there was no pre-rename note.
		if ( false === $data || ! is_array( $data ) ) {
			return;
		}

		// Remove the transient.
		delete_transient( $key );

		// Old term data was captured earlier.
		$old_slug  = isset( $data['old_slug'] ) ? sanitize_title( $data['old_slug'] ) : '';
		$old_count = isset( $data['count'] ) ? (int) $data['count'] : 0;
		$new_slug  = $term->slug;

		// Only act when slug actually changed and the old term had posts.
		if ( $old_slug && $new_slug && $old_slug !== $new_slug && $old_count > 0 ) {
			self::add_slug_redirect( $old_slug, $new_slug, $taxonomy );
		}
	}

	/**
	 * Handles merging tags and setting a redirect when the user attempts to rename
	 * a term to a slug that already exists.
	 */
	public static function maybe_handle_term_merge_submit() {
		// Bail if merge is already in progress.
		if ( self::$processing_merge ) {
			return;
		}

		// Bail if not a POST request.
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		// Bail if not an edited tag request.
		if ( empty( $_POST['action'] ) || 'editedtag' !== $_POST['action'] ) {
			return;
		}

		// Bail if not a mergeable taxonomy.
		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( $_POST['taxonomy'] ) : '';
		if ( ! self::is_mergeable_taxonomy( $taxonomy ) ) {
			return;
		}

		// Bail if no from term ID.
		$from_term_id = isset( $_POST['tag_ID'] ) ? (int) $_POST['tag_ID'] : 0;
		if ( $from_term_id <= 0 ) {
			return;
		}

		// Bail if user doesn't have the capability.
		if ( ! current_user_can( 'edit_term', $from_term_id ) ) {
			return;
		}
		check_admin_referer( 'update-tag_' . $from_term_id );

		$new_slug_raw = isset( $_POST['slug'] ) ? wp_unslash( $_POST['slug'] ) : '';
		$new_slug     = sanitize_title( $new_slug_raw );

		// Bail if the slug field was not changed or is empty.
		if ( '' === $new_slug ) {
			return;
		}

		// Bail if from term doesn't exist.
		$from = get_term( $from_term_id, $taxonomy );
		if ( ! $from || is_wp_error( $from ) ) {
			return;
		}

		// Bail if user didn't actually change the slug.
		if ( $new_slug === $from->slug ) {
			return;
		}

		// Bail if the term doesn't exist.
		$exists = term_exists( $new_slug, $taxonomy );
		if ( ! $exists || is_wp_error( $exists ) ) {
			// Queue up a transient to store the old slug and count for later use by `process_rename()`.
			set_transient(
				self::pre_rename_key( $taxonomy, $from_term_id ),
				[
					'old_slug' => $from->slug,
					'count'    => (int) $from->count,
				],
				5 * MINUTE_IN_SECONDS
			);

			// Let core handle the new, unique slug.
			return;
		}

		// Bail if the term ID is invalid.
		$to_term_id = is_array( $exists ) ? (int) $exists['term_id'] : (int) $exists;
		if ( $to_term_id <= 0 || $to_term_id === $from_term_id ) {
			return;
		}

		// Keep the old before we mutate/delete it.
		$old_slug   = $from->slug;
		$old_count  = (int) $from->count;
		$to         = get_term( $to_term_id, $taxonomy );
		$to_name    = $to && ! is_wp_error( $to ) ? $to->name : '';

		// Merge terms.
		self::$processing_merge = true;
		self::merge_terms( $from_term_id, $to_term_id, $taxonomy );

		// Add redirect only if the old term actually had posts assigned.
		if ( $old_count > 0 ) {
			self::add_slug_redirect( $old_slug, $to->slug, $taxonomy );
		}

		self::queue_admin_notice( [
			'type' => 'success',
			'message' => sprintf(
				/* translators: 1: Old term name, 2: New/existing term name */
				__( 'Merged term "%1$s" into "%2$s".', 'wporg-photos' ),
				$from->name,
				$to_name
			)
		] );

		// Redirect to surviving term’s edit screen, skipping core's updater.
		$location = add_query_arg(
			[
				'action'   => 'edit',
				'post_type' => Registrations::get_post_type(),
				'taxonomy' => $taxonomy,
				'tag_ID'   => $to_term_id,
			],
			admin_url( 'term.php' )
		);
		wp_safe_redirect( $location );
		exit;
	}

	/**
	 * Handles quick edit merge.
	 */
	public static function maybe_handle_quick_edit_merge() {
		// Security and context checks mirror core.
		check_ajax_referer( 'taxinlineeditnonce', '_inline_edit' );

		// Bail if merge is already in progress.
		if ( self::$processing_merge ) {
			return;
		}

		// Bail if not a mergeable taxonomy.
		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( $_POST['taxonomy'] ) : '';
		if ( ! self::is_mergeable_taxonomy( $taxonomy ) ) {
			return; // Let core proceed.
		}

		// Bail if no from term ID.
		$from_id = isset( $_POST['tax_ID'] ) ? (int) $_POST['tax_ID'] : 0;
		if ( $from_id <= 0 || ! current_user_can( 'edit_term', $from_id ) ) {
			return;
		}

		// Bail if from term doesn't exist.
		$from = get_term( $from_id, $taxonomy );
		if ( ! $from || is_wp_error( $from ) ) {
			return;
		}

		$new_slug_raw = isset( $_POST['slug'] ) ? wp_unslash( $_POST['slug'] ) : '';
		$new_slug     = sanitize_title( $new_slug_raw );

		// Bail if the slug field was not changed or is empty.
		if ( '' === $new_slug || $new_slug === $from->slug ) {
			return;
		}

		$exists = term_exists( $new_slug, $taxonomy );

		// Bail if no existing term, but stash old slug/count so process_rename() can add a redirect.
		if ( ! $exists || is_wp_error( $exists ) ) {
			set_transient(
				self::pre_rename_key( $taxonomy, $from_id ),
				[
					'old_slug' => $from->slug,
					'count'    => (int) $from->count,
				],
				5 * MINUTE_IN_SECONDS
			);
			// Core's wp_ajax_inline_save_tax() will perform wp_update_term().
			return;
		}

		// Bail if the to term ID is invalid or the same as the from term ID.
		$to_id = is_array( $exists ) ? (int) $exists['term_id'] : (int) $exists;
		if ( $to_id <= 0 || $to_id === $from_id ) {
			return;
		}

		// Perform merge and produce the same kind of response core would.
		$old_count = (int) $from->count;
		self::$processing_merge = true;
		self::merge_terms( $from_id, $to_id, $taxonomy );

		if ( $old_count > 0 ) {
			$to_slug = get_term_field( 'slug', $to_id, $taxonomy );
			if ( ! is_wp_error( $to_slug ) && $to_slug ) {
				self::add_slug_redirect( $from->slug, $to_slug, $taxonomy );
			}
		}

		// Render the updated row HTML for the surviving term, just like core does.
		$tag           = get_term( $to_id, $taxonomy );
		$wp_list_table = _get_list_table( 'WP_Terms_List_Table', [ 'screen' => 'edit-' . $taxonomy ] );

		// Compute level for hierarchical taxonomies (tags will just be level 0).
		$level  = 0;
		$parent = $tag && ! is_wp_error( $tag ) ? (int) $tag->parent : 0;
		while ( $parent > 0 ) {
			$parent_tag = get_term( $parent, $taxonomy );
			$parent     = $parent_tag ? (int) $parent_tag->parent : 0;
			++$level;
		}

		$wp_list_table->single_row( $tag, $level );
		// Stop core's default AJAX handler from running.
		wp_die();
	}

	/**
	 * Generates a transient key for pre-rename data.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @param int    $term_id  The term ID.
	 * @return string The transient key.
	 */
	protected static function pre_rename_key( $taxonomy, $term_id ) {
		return "photos_tags_pre_rename_{$taxonomy}_{$term_id}";
	}

	/**
	 * Merges terms by moving all objects from one term to another, then deleting
	 * the source term.
	 *
	 * @param int    $from_term_id Source term ID.
	 * @param int    $to_term_id   Destination term ID.
	 * @param string $taxonomy     Taxonomy slug.
	 */
	protected static function merge_terms( $from_term_id, $to_term_id, $taxonomy ) {
		// Bail if merging with itself.
		if ( $from_term_id === $to_term_id ) {
			return;
		}

		$object_ids = get_objects_in_term( $from_term_id, $taxonomy );
		if ( is_wp_error( $object_ids ) ) {
			return;
		}

		foreach ( $object_ids as $object_id ) {
			wp_add_object_terms( $object_id, [ $to_term_id ], $taxonomy );
			wp_remove_object_terms( $object_id, [ $from_term_id ], $taxonomy );
		}

		// Now that no objects use it, delete the old term.
		wp_delete_term( $from_term_id, $taxonomy );
	}

	/**
	 * Queues an admin notice.
	 *
	 * @param string $message The message to queue.
	 */
	public static function queue_admin_notice( $message ) {
		set_transient( 'photo_directory_tag_merge_admin_notice', $message, 30 );
	}

	/**
	 * Outputs admin notice if one is queued.
	 */
	public static function admin_notices() {
		$data = get_transient( 'photo_directory_tag_merge_admin_notice' );
		if ( ! $data ) {
			return;
		}
		delete_transient( 'photo_directory_tag_merge_admin_notice' );

		$type    = in_array( $data['type'], [ 'success', 'warning', 'error', 'info' ], true ) ? $data['type'] : 'success';
		$message = $data['message'];

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			wp_kses_post( $message )
		);
	}

	/**
	 * Changes the redirect URL if a term being edited has been merged.
	 *
	 * @param string      $location The original redirect URL.
	 * @param WP_Taxonomy $tax      The taxonomy object.
	 * @return string Modified URL.
	 */
	public static function maybe_redirect_merged_term( $location, $tax ) {
		if ( ! self::is_mergeable_taxonomy( $tax->name ) ) {
			return $location;
		}

		$parts = wp_parse_url( $location );
		if ( empty( $parts['query'] ) ) {
			return $location;
		}

		parse_str( $parts['query'], $qs );
		if ( empty( $qs['tag_ID'] ) ) {
			return $location;
		}

		$old_id = (int) $qs['tag_ID'];
		$transient_name = "photo_directory_tag_merge_redirect_{$old_id}";
		$new_id = get_transient( $transient_name );
		if ( ! $new_id ) {
			return $location;
		}

		delete_transient( $transient_name );
		$location = remove_query_arg( 'tag_ID', $location );

		return add_query_arg( 'tag_ID', $new_id, $location );
	}

	/**
	 * Records any tag redirects that need to happen for merged or renamed tags.
	 *
	 * @param string $old_slug The tag slug to redirect.
	 * @param string $new_slug The tag slug to redirect to.
	 * @param string $taxonomy The taxonomy.
	 */
	protected static function add_slug_redirect( $old_slug, $new_slug, $taxonomy ) {
		// Structure: [ 'taxonomy' => [ 'old-slug' => 'new-slug', ... ] ]
		if ( ! $old_slug || ! $new_slug ) {
			return;
		}

		$all = get_option( self::REMAP_OPTION_NAME, [] );
		if ( ! isset( $all[ $taxonomy ] ) ) {
			$all[ $taxonomy ] = [];
		}
		$all[ $taxonomy ][ $old_slug ] = $new_slug;
		update_option( self::REMAP_OPTION_NAME, $all );
	}

	/**
	 * Redirects URL for tags that have been merged or renamed.
	 */
	public static function redirect_tags() {
		// Bail if in the admin.
		if ( is_admin() ) {
			return;
		}

		// Bail if not a request for mergeable tag.
		$taxonomy = get_query_var( 'taxonomy' );
		if ( ! $taxonomy || ! self::is_mergeable_taxonomy( $taxonomy ) ) {
			return;
		}

		// Bail if no slug.
		$slug = get_query_var( $taxonomy );
		if ( ! $slug ) {
			return;
		}

		$all = get_option( self::REMAP_OPTION_NAME, [] );
		if ( empty( $all[ $taxonomy ][ $slug ] ) ) {
			return;
		}

		// Resolve the slug to its final destination.
		$final_slug = self::resolve_tag_remap( $slug, $taxonomy );

		$new_link = get_term_link( $final_slug, $taxonomy );
		if ( is_wp_error( $new_link ) ) {
			return;
		}

		wp_redirect( $new_link, 301 );
		exit;
	}

	/**
	 * Resolves a tag slug to its final destination by following remaps.
	 *
	 * @param string $slug     The tag slug to resolve.
	 * @param string $taxonomy The taxonomy.
	 * @return string The final slug after following all remaps.
	 */
	public static function resolve_tag_remap( $slug, $taxonomy ) {
		// Bail if no slug or not a mergeable taxonomy.
		if ( ! $slug || ! self::is_mergeable_taxonomy( $taxonomy ) ) {
			return $slug;
		}

		// Get all remaps for the taxonomy.
		$all = get_option( self::REMAP_OPTION_NAME, [] );

		// Bail if no remap for the slug.
		if ( empty( $all[ $taxonomy ][ $slug ] ) ) {
			return $slug;
		}

		// Follow remaps until we find a final slug (since a remap might be to another remap).
		$final_slug = $slug;
		$remap_count = 0;
		while (
			! empty( $all[ $taxonomy ][ $final_slug ] )
			&& $all[ $taxonomy ][ $final_slug ] !== $final_slug
			&& $remap_count < 10
		) {
			/*
			 * @todo Update remaps option to remap remaps to remove intermediate remaps? Would
			 * lose the record of some tag remaps.
			 * Note: There is only one resultant remap for the visitor, so these intermediate remaps
			 * don't result in multiple remaps.
			 */
			$final_slug = $all[ $taxonomy ][ $final_slug ];
			$remap_count++;
		}

		return $final_slug;
	}

	/**
	 * Resolves an array of tag slugs to their final destinations by following remaps.
	 *
	 * @param array  $slugs    Array of tag slugs to resolve.
	 * @param string $taxonomy The taxonomy.
	 * @return array Array of final slugs after following all remaps, with duplicates removed.
	 */
	public static function resolve_tag_remaps( $slugs, $taxonomy ) {
		// Bail if no slugs or not a mergeable taxonomy.
		if ( ! is_array( $slugs ) || ! self::is_mergeable_taxonomy( $taxonomy ) ) {
			return $slugs;
		}

		// Resolve each slug to its final destination.
		$resolved_slugs = [];
		foreach ( $slugs as $slug ) {
			$resolved_slug = self::resolve_tag_remap( $slug, $taxonomy );
			$resolved_slugs[] = $resolved_slug;
		}

		// Remove duplicates while preserving order.
		return array_unique( $resolved_slugs );
	}

	/**
	 * Filters out tags that match existing photo colors or categories.
	 *
	 * @param array $tags_to_assign Array of tag slugs to filter.
	 * @param int   $post_id        The post ID.
	 * @return array Filtered array of tag slugs.
	 */
	public static function filter_out_existing_taxonomy_terms( $tags_to_assign, $post_id ) {
		// Bail if no tags to assign.
		if ( ! is_array( $tags_to_assign ) || empty( $tags_to_assign ) ) {
			return $tags_to_assign;
		}

		// Get existing terms for colors and categories.
		$existing_terms = [];

		// Get existing color terms.
		$color_terms = get_terms( [
			'taxonomy'   => Registrations::get_taxonomy( 'colors' ),
			'hide_empty' => false,
			'fields'     => 'slugs',
		] );
		if ( ! is_wp_error( $color_terms ) && is_array( $color_terms ) ) {
			$existing_terms = array_merge( $existing_terms, $color_terms );
		}

		// Get existing category terms.
		$category_terms = get_terms( [
			'taxonomy'   => Registrations::get_taxonomy( 'categories' ),
			'hide_empty' => false,
			'fields'     => 'slugs',
		] );
		if ( ! is_wp_error( $category_terms ) && is_array( $category_terms ) ) {
			$existing_terms = array_merge( $existing_terms, $category_terms );
		}

		// Convert to lowercase for case-insensitive comparison.
		$existing_terms = array_map( 'strtolower', $existing_terms );

		// Filter out tags that match existing terms.
		return array_filter( $tags_to_assign, function( $tag ) use ( $existing_terms ) {
			return ! in_array( strtolower( $tag ), $existing_terms, true );
		} );
	}

	/**
	 * Filters tags during assignment to remap them to their final destinations.
	 *
	 * @param array $tags_to_assign Array of tag slugs to assign.
	 * @param int   $post_id        The post ID.
	 * @param int   $image_id       The image attachment ID.
	 * @return array Array of remapped tag slugs with duplicates removed.
	 */
	public static function remap_tags_during_assignment( $tags_to_assign, $post_id, $image_id ) {
		$taxonomy = Registrations::get_taxonomy( 'tags' );

		// Bail if no tags or not a mergeable taxonomy.
		if ( ! is_array( $tags_to_assign ) || ! self::is_mergeable_taxonomy( $taxonomy ) ) {
			return $tags_to_assign;
		}

		// Resolve all tags to their final destinations.
		$resolved_tags = self::resolve_tag_remaps( $tags_to_assign, $taxonomy );

		// Remove duplicates that may have been created by remaps.
		return array_unique( $resolved_tags );
	}

	/**
	 * Prevents creation of terms that are remapped to existing terms.
	 *
	 * @param mixed  $term     The term data.
	 * @param string $taxonomy The taxonomy.
	 * @param array  $args     Additional arguments.
	 * @return mixed|WP_Error The term data or WP_Error to prevent creation.
	 */
	public static function prevent_remapped_term_creation( $term, $taxonomy, $args ) {
		// Bail if not a mergeable taxonomy.
		if ( ! self::is_mergeable_taxonomy( $taxonomy ) ) {
			return $term;
		}

		// Extract term name/slug from the term data.
		$term_slug = '';
		if ( is_string( $term ) ) {
			$term_slug = $term;
		} elseif ( is_array( $term ) && isset( $term['slug'] ) ) {
			$term_slug = $term['slug'];
		} elseif ( is_array( $term ) && isset( $term['name'] ) ) {
			$term_slug = sanitize_title( $term['name'] );
		}

		// Bail if no slug.
		if ( ! $term_slug ) {
			return $term;
		}

		// Resolve the slug to its final destination.
		$remapped_slug = self::resolve_tag_remap( $term_slug, $taxonomy );

		// Bail if the term remaps to itself.
		if ( $remapped_slug === $term_slug ) {
			return $term;
		}

		// Check if the remapped term already exists.
		$existing_term = get_term_by( 'slug', $remapped_slug, $taxonomy );
		if ( $existing_term && ! is_wp_error( $existing_term ) ) {
			// Return the existing term data instead of creating a new one.
			if ( is_array( $term ) ) {
				$term['slug'] = $existing_term->slug;
				$term['name'] = $existing_term->name;
			} else {
				$term = $existing_term->slug;
			}
			return $term;
		}

		// If the remapped term doesn't exist, allow creation but modify the term data.
		if ( is_array( $term ) ) {
			$term['slug'] = $remapped_slug;
			if ( isset( $term['name'] ) ) {
				$term['name'] = $remapped_slug;
			}
		} else {
			$term = $remapped_slug;
		}

		return $term;
	}

	/**
	 * Determines if the current screen is for a mergeable taxonomy tag being edited.
	 *
	 * @return True if the current screen is for editing a tag in a mergeable taxonomy,
	 *         else false.
	 */
	public static function is_editing_mergeable_tag() {
		$screen = get_current_screen();

		// Bail if not a mergeable taxonomy.
		if ( ! $screen || ! self::is_mergeable_taxonomy( $screen->taxonomy ) ) {
			return false;
		}

		// Bail if not editing a tag.
		if ( 'edit-' !== substr( $screen->id, 0, 5 ) ) {
			return false;
		}

		return true;
	}

	/**
	 * API function to rename or merge tags.
	 *
	 * Use this instead of `wp_update_term()` to "rename to existing slug = merge".
	 *
	 * @return array|WP_Error Result of `wp_update_term()` on simple rename, or ['merged' => true, 'to_term_id' => X] on merge.
	 */
	public static function rename_or_merge( int $term_id, string $taxonomy, string $new_slug ) {
		// Bail if not a mergeable taxonomy.
		if ( ! self::is_mergeable_taxonomy( $taxonomy ) ) {
			return new WP_Error( 'invalid_taxonomy', __( 'Taxonomy is not merge-enabled.', 'your-textdomain' ) );
		}

		$new_slug = sanitize_title( $new_slug );
		$from     = get_term( $term_id, $taxonomy );

		// Bail if the term doesn't exist.
		if ( ! $from || is_wp_error( $from ) ) {
			return new WP_Error( 'invalid_term', __( 'Term not found.', 'wporg-photos' ) );
		}

		// Bail if the new slug is the same as the old slug.
		if ( '' === $new_slug || $new_slug === $from->slug ) {
			// Nothing to do.
			return [ 'term_id' => $term_id ];
		}

		// Handle a merge.
		$exists = term_exists( $new_slug, $taxonomy );
		if ( $exists && ! is_wp_error( $exists ) ) {
			$to_term_id = is_array( $exists ) ? (int) $exists['term_id'] : (int) $exists;
			if ( $to_term_id && $to_term_id !== $term_id ) {
				if ( self::$processing_merge ) {
					return new WP_Error( 'reentrancy', __( 'Merge already in progress.', 'wporg-photos' ) );
				}
				self::$processing_merge = true;

				$old_count = (int) $from->count;
				$old_slug  = $from->slug;
				self::merge_terms( $term_id, $to_term_id, $taxonomy );

				if ( $old_count > 0 ) {
					$to = get_term( $to_term_id, $taxonomy );
					if ( $to && ! is_wp_error( $to ) ) {
						self::add_slug_redirect( $old_slug, $to->slug, $taxonomy );
					}
				}

				self::$processing_merge = false;
				return [ 'merged' => true, 'to_term_id' => $to_term_id ];
			}
		}

		// Handle a simple rename.
		$result = wp_update_term( $term_id, $taxonomy, [ 'slug' => $new_slug ] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Optional: add redirect if old term had posts.
		if ( (int) $from->count > 0 ) {
			self::add_slug_redirect( $from->slug, $new_slug, $taxonomy );
		}

		return $result;
	}

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Tags', 'init' ] );

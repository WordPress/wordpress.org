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

	/** @var array The old terms. */
	protected static $old_terms = [];

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
			add_action( "edit_{$taxonomy}",             [ __CLASS__, 'capture_old_slug' ], 1, 2 );
			add_action( "edited_{$taxonomy}",           [ __CLASS__, 'process_merge_rename' ], 10, 2 );
		}
		add_action( 'admin_notices',              [ __CLASS__, 'admin_notices' ] );
		add_filter( 'redirect_term_location',     [ __CLASS__, 'maybe_redirect_merged_term' ], 10, 2 );
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
	 * Stores the name and slug of term being edited before changes are saved.
	 *
	 * @param int $term_id The ID of the term just edited.
	 * @param int $tt_id   The term taxonomy ID.
	 */
	public static function capture_old_slug( $term_id, $tt_id ) {
		// Get taxonomy from the term.
		$term = get_term( $term_id );
		if ( ! $term instanceof \WP_Term ) {
			return;
		}
		$taxonomy = $term->taxonomy;

		if ( ! self::is_mergeable_taxonomy( $taxonomy ) ) {
			return;
		}

		self::$old_terms[ $taxonomy ][ $term_id ] = [
			'name' => $term->name,
			'slug' => $term->slug,
		];
	}

	/**
	 * Handles tag merge/rename.
	 *
	 * @param int $term_id The ID of the term just edited.
	 * @param int $tt_id   The term taxonomy ID.
	 */
	public static function process_merge_rename( $term_id, $tt_id ) {
		// Bail if function has already been invoked (prevents recursion).
		if ( self::$processing_merge ) {
			return;
		}

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

		// Old term data was captured earlier.
		$old_data = self::$old_terms[ $taxonomy ][ $term_id ] ?? [ 'name' => '', 'slug' => '' ];
		$old_name = $old_data['name'];
		$old_slug = $old_data['slug'];

		// Get new term data.
		$new_slug = ( $term instanceof \WP_Term ) ? $term->slug : '';
		$new_name = ( $term instanceof \WP_Term ) ? $term->name : '';

		// Mimic core and auto-generate slug from name if no slug was provided.
		if ( '' === $new_slug ) {
			$new_slug = sanitize_title( $new_name );
		}

		// Bail if no change in slug.
		if ( ! $new_slug || $old_slug === $new_slug ) {
			return;
		}

		// Check if target tag exists.
		$exists = term_exists( $new_slug, $taxonomy );
		$to_term_id = is_array( $exists ) ? (int) $exists['term_id'] : (int) $exists;

		self::$processing_merge = true;

		// Merge terms if target term exists.
		if ( $to_term_id && $to_term_id !== $term_id ) {
			self::merge_terms( $term_id, $to_term_id, $taxonomy );
			$new_term = get_term( $to_term_id, $taxonomy );

			self::add_slug_redirect( $old_slug, $new_term->slug, $taxonomy );

			set_transient( "photo_directory_tag_merge_redirect_{$term_id}", $to_term_id, 60 );

			$message = sprintf(
				/* translators: 1: Old term, 2: Existing term */
				__( 'Merged term <strong>%1$s</strong> into <strong>%2$s</strong>.', 'wporg-photos' ),
				esc_html( $old_name ),
				esc_html( $new_term->name )
			);

		// Else just rename existing term.
		} else {
			$updated = wp_update_term( $term_id, $taxonomy, [
				'name' => $new_name,
				'slug' => $new_slug,
			] );

			if ( is_wp_error( $updated ) ) {
				$message = $updated->get_error_message();
			} else {
				self::add_slug_redirect( $old_slug, $new_slug, $taxonomy );

				$message = sprintf(
					/* translators: %s: New term name */
					__( 'Change term slug to <strong>%s</strong>.', 'wporg-photos' ),
					esc_html( $new_name )
				);
			}
		}

		// Queue up an admin notice.
		set_transient( 'photo_directory_tag_merge_admin_notice', $message, 30 );

		self::$processing_merge = false;

		return;
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
			wp_remove_object_terms( $object_id, $from_term_id, $taxonomy );
			wp_add_object_terms( $object_id, $to_term_id, $taxonomy );
		}

		// Now that no objects use it, delete the old term.
		wp_delete_term( $from_term_id, $taxonomy );
	}

	/**
	 * Outputs admin notice if one is queued.
	 */
	public static function admin_notices() {
		if ( $msg = get_transient( 'photo_directory_tag_merge_admin_notice' ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				wp_kses_post( $msg )
			);
			delete_transient( 'photo_directory_tag_merge_admin_notice' );
		}
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

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Tags', 'init' ] );

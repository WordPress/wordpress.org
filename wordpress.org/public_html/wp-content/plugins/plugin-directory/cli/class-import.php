<?php
namespace WordPressdotorg\Plugin_Directory\CLI;

use Exception;
use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;
use WordPressdotorg\Plugin_Directory\Block_JSON;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Email\Release_Confirmation as Release_Confirmation_Email;
use WordPressdotorg\Plugin_Directory\Readme\{ Parser as Readme_Parser, Validator as Readme_Validator };
use WordPressdotorg\Plugin_Directory\Standalone\Plugins_Info_API;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;
use WordPressdotorg\Plugin_Directory\Tools\SVN;
use WordPressdotorg\Plugin_Directory\Tools\Tokenisation_Helpers;
use WordPressdotorg\Plugin_Directory\Zip\Builder;

/**
 * The functionality required to process a plugin import into the Directory.
 *
 * This will normally be called on the CLI in response to a plugin commit.
 *
 * @package WordPressdotorg\Plugin_Directory\CLI
 */
class Import {
	const PLUGIN_SVN_BASE = 'https://plugins.svn.wordpress.org';

	// Readme fields which get stored in plugin meta
	public $readme_fields = array(
		'donate_link',
		'license',
		'license_uri',
		'upgrade_notice',
		'screenshots',

		// These headers are stored as post meta, but are handled separately.
		// 'tested',
		// 'requires',
		// 'requires_php',
	);

	// Plugin headers that are stored in plugin meta
	public $plugin_headers = array(
		// Header    => meta_key
		'Name'       => 'header_name',
		'PluginURI'  => 'header_plugin_uri',
		'Author'     => 'header_author',
		'AuthorURI'  => 'header_author_uri',
		'TextDomain' => 'header_textdomain',

		// These headers are stored in these fields, but are handled separately.
		// 'Version'         => 'version',
		// 'RequiresWP'      => 'requires',
		// 'RequiresPHP'     => 'requires_php',
		// 'RequiresPlugins' => 'requires_plugins'
	);

	/**
	 * List of warnings generated during the import process.
	 *
	 * @var array
	 */
	public $warnings = array();

	/**
	 * The last plugin imported.
	 *
	 * @var \WP_Post
	 */
	public $plugin;

	/**
	 * Whether a tag's code changed since its release's confirmation state was established.
	 *
	 * Compares the tag's current "Last Changed Rev" against the recorded source_revision. Records
	 * predating that field fall back to the served ZIP's export revision, and fail safe (modified)
	 * when even that is unknown; import_from_svn() backfills source_revision so the fallback runs once.
	 *
	 * @param array|false $release      Stored release record, per Plugin_Directory::get_release().
	 * @param int         $tag_revision The tag path's current "Last Changed Rev".
	 * @return bool Whether the tag changed after the recorded source revision.
	 */
	public static function tag_modified_after_release( $release, $tag_revision ) {
		if ( ! $release ) {
			return false;
		}

		if ( isset( $release['source_revision'] ) ) {
			return (int) $tag_revision > (int) $release['source_revision'];
		}

		return (int) $tag_revision > (int) ( $release['zips_built_from_revision'] ?? 0 );
	}

	/**
	 * Process an import for a Plugin into the Plugin Directory.
	 *
	 * @throws \Exception
	 *
	 * @param string $plugin_slug            The slug of the plugin to import.
	 * @param array  $svn_changed_tags       A list of tags/trunk which the SVN change touched. Optional.
	 * @param array  $svn_tags_deleted       A list of tags/trunk which were deleted in the SVN change. Optional.
	 * @param array  $svn_revision_triggered The SVN revision which this import has been triggered by. Optional.
	 */
	public function import_from_svn( $plugin_slug, $svn_changed_tags = array( 'trunk' ), $svn_tags_deleted = array(), $svn_revision_triggered = 0 ) {
		// Reset properties.
		$this->warnings = [];

		$plugin = $this->plugin = Plugin_Directory::get_plugin_post( $plugin_slug );
		if ( ! $plugin ) {
			throw new Exception( 'Unknown Plugin' );
		}

		$data = $this->export_and_parse_plugin( $plugin_slug );

		$readme             = $data['readme'];
		$assets             = $data['assets'];
		$headers            = $data['plugin_headers'];
		$version            = $headers->Version ?? '';
		$stable_tag         = $data['stable_tag'];
		$last_committer     = $data['last_committer'];
		$last_revision      = $data['last_revision'];
		$tagged_versions    = $data['tagged_versions'];
		$last_modified      = $data['last_modified'];
		$blocks             = $data['blocks'];
		$block_files        = $data['block_files'];
		$dashboard_widgets  = $data['dashboard_widgets'] ?? array();
		$current_stable_tag = get_post_meta( $plugin->ID, 'stable_tag', true ) ?: 'trunk';
		$touches_stable_tag = (bool) array_intersect( [ $stable_tag, $current_stable_tag ], $svn_changed_tags );

		// If the readme generated any warnings, raise it to self::$import_warnings;
		if ( $readme->warnings ) {
			$this->warnings = array_merge( $this->warnings, $readme->warnings );
		}

		/**
		 * Fire an import action, now that we've exported most of the plugin data.
		 *
		 * NOTE: This is prior to any validation checks.
		 *
		 * @param Import  $this                   The Plugin Importer object.
		 * @param WP_Post $plugin                 The plugin being imported.
		 * @param array   $data                   The data from the import process.
		 * @param array   $svn_changed_tags       The list of SVN tags/trunk affected to trigger the import.
		 * @param array   $svn_tags_deleted       The list of SVN tags/trunk deleted in the import.
		 * @param int     $svn_revision_triggered The SVN revision that triggered the import.
		 */
		do_action( 'wporg_plugins_import', $this, $plugin, $data, $svn_changed_tags, $svn_tags_deleted, $svn_revision_triggered );

		// Validate various headers:

		/*
		 * Warn when the plugin's Version header has anything other than digits, dots, and an
		 * optional `-rc` / `-beta` / `-alpha` pre-release suffix (with optional digits).
		 *
		 * Catches headers that include an accidental duplicate `Version:` prefix, stray
		 * letters or punctuation, or other free-form text mixed in with the version number.
		 *
		 * The strict format matters because WordPress core uses `version_compare()` to decide
		 * whether to offer an update — a malformed header can silently give the wrong answer
		 * for users running an older release.
		 */
		if ( $version && ! preg_match( '/^\d+(?:\.\d+)*(?:-(?:rc|beta|alpha)(?:\.?\d+)?)?$/i', $version ) ) {
			$this->warnings['version_header_unexpected_chars'] = $version;
		}

		// Stored as post meta, served by the API, and used as a path component downstream.
		if ( $version && ! self::version_is_path_safe( $version ) ) {
			$this->warnings['invalid_version_header'] = $version;

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI context, callers write the message to STDERR.
			throw new Exception( Readme_Validator::instance()->translate_code_to_message( 'invalid_version_header', $version ) );
		}

		/*
		 * Warn when the plugin's Version header doesn't appear to match the tag it was released from.
		 *
		 * Trunk releases skip this check — there's no tag folder to compare against.
		 */
		if ( 'trunk' !== $stable_tag && $version && ! self::version_matches_tag( $version, $stable_tag ) ) {
			$this->warnings['version_tag_mismatch'] = [
				'version' => $version,
				'tag'     => $stable_tag,
			];
		}

		/*
		 * Check to see if the plugin is using the `Update URI` header.
		 *
		 * Plugins on WordPress.org should NOT use this header, but we do accept some URI formats for it in the API,
		 * so those are allowed to pass here.
		 * Any documentation suggesting that a WordPress.org hosted plugin should use this header is incorrect.
		 */
		if ( $headers->UpdateURI ) {
			$update_uri_valid = preg_match( '!^(https?://)?(wordpress.org|w.org)/plugins?/(?P<slug>[^/]+)/?$!i', $headers->UpdateURI, $update_uri_matches );
			if ( ! $update_uri_valid || $update_uri_matches['slug'] !== $plugin_slug ) {
				$this->warnings['invalid_update_uri'] = $headers->UpdateURI;

				throw new Exception( Readme_Validator::instance()->translate_code_to_message( 'invalid_update_uri' ) );
			}
		}

		$_requires_plugins = array_filter( array_map( 'trim', explode( ',', $headers->RequiresPlugins ) ) );
		$requires_plugins     = [];
		$unmet_dependencies   = [];
		foreach ( $_requires_plugins as $requires_plugin_slug ) {
			$requires_plugin_post = Plugin_Directory::get_plugin_post( $requires_plugin_slug );

			// get_plugin_post() will resolve some edge-cases, but we only want exact slug-matches, anything else is wrong.
			if (
				$requires_plugin_post &&
				$requires_plugin_slug === $requires_plugin_post->post_name &&
				'publish' === $requires_plugin_post->post_status
			) {
				$requires_plugins[] = $requires_plugin_post->post_name;
			} else {
				$unmet_dependencies[] = $requires_plugin_slug;
			}
		}

		if ( $unmet_dependencies ) {
			$this->warnings['unmet_dependencies'] = $unmet_dependencies;

			throw new Exception( Readme_Validator::instance()->translate_code_to_message( 'unmet_dependencies', $unmet_dependencies ) );
		}
		unset( $_requires_plugins, $unmet_dependencies );

		/*
		 * If a tag has been deleted, we should also remove any unconfirmed releases.
		 * NOTE: remove_release() will not remove a confirmed release, but will remove a discarded release.
		 *
		 * Additionally; this must occur before the below release confirmation checks,
		 * if the trunk readme has it's stable_tag set to one of these deleted (now non-existent) tags,
		 * then $stable_tag will be set to the fallback 'trunk', causing the RC checks to fail.
		 */
		foreach ( $svn_tags_deleted as $svn_deleted_tag ) {
			if ( Plugin_Directory::remove_release( $plugin, $svn_deleted_tag ) ) {
				echo "Plugin tag {$svn_deleted_tag} deleted; release removed.\n";
			}
		}

		// Release confirmation
		if ( $plugin->release_confirmation ) {
			// If the stable tag is trunk, we shouldn't continue, as we don't support that for RC.
			if ( 'trunk' === $stable_tag ) {
				throw new Exception( 'Plugin cannot be released from trunk due to release confirmation being enabled.' );
			}

			// Per-tag last-changed rev/author, from the listing export_and_parse_plugin() already fetched.
			$tag_last_changed = [];
			foreach ( $tagged_versions as $tag_meta ) {
				if ( isset( $tag_meta['tag'] ) ) {
					$tag_last_changed[ $tag_meta['tag'] ] = [
						'revision' => (int) ( $tag_meta['revision'] ?? 0 ),
						'author'   => (string) ( $tag_meta['author'] ?? '' ),
					];
				}
			}

			// Check to see if the commit has touched tags that don't have known confirmed releases.
			foreach ( $svn_changed_tags as $svn_changed_tag ) {
				if ( 'trunk' === $svn_changed_tag ) {
					continue;
				}

				$release = Plugin_Directory::get_release( $plugin, $svn_changed_tag );

				// get_release() matches loosely ('1.4' == '1.40', or a trunk@ fallback); only act on an exact-tag record.
				if ( $release && (string) ( $release['tag'] ?? '' ) !== (string) $svn_changed_tag ) {
					$release = false;
				}

				// $last_revision/$last_committer describe the stable path; other tags need their own.
				if ( isset( $tag_last_changed[ $svn_changed_tag ] ) ) {
					$tag_revision  = $tag_last_changed[ $svn_changed_tag ]['revision'];
					$tag_committer = $tag_last_changed[ $svn_changed_tag ]['author'] ?: $last_committer;
				} elseif ( $svn_changed_tag === $stable_tag ) {
					$tag_revision  = (int) $last_revision;
					$tag_committer = $last_committer;
				} else {
					// Unknown revision (tag deleted mid-import, or listing failure): don't guess and risk a false reset.
					$this->warnings['tag_revision_unresolved'][] = $svn_changed_tag;
					continue;
				}

				// Re-committed code must re-confirm, not inherit the tag's old approval; an unchanged re-import is a no-op.
				$modified_after_release = self::tag_modified_after_release( $release, $tag_revision );

				if ( ! $release || $modified_after_release ) {
					if ( $svn_changed_tag === $stable_tag ) {
						// Stable release, described by the parsed plugin headers.
						$release_version = $version;
					} elseif ( $release ) {
						// Keep the stored version; there's no header data for non-stable tags.
						$release_version = $release['version'] ?: $svn_changed_tag;
					} else {
						// New non-stable release; fallback to the tag name.
						$release_version = $svn_changed_tag;
					}

					$release_data = [
						'tag'             => $svn_changed_tag,
						'version'         => $release_version,
						'committer'       => [ $tag_committer ],
						'revision'        => [ $tag_revision ],
						// Baseline for later modification checks.
						'source_revision' => $tag_revision,
					];

					// Discard the prior approval when re-opening a modified release.
					if ( $modified_after_release ) {
						$release_data['reset_confirmation'] = true;
					}

					Plugin_Directory::add_release( $plugin, $release_data );

					/*
					 * Trigger the release confirmation email.
					 *
					 * This goes to ALL committers, including who commited the change.
					 * "bot" accounts are NOT emailed, nor are accounts that have web login disabled.
					 */
					$who_to_email = array_diff(
						Tools::get_plugin_committers( $plugin_slug ),
						$GLOBALS['bot_accounts'] ?? [],
						$GLOBALS['nologin_accounts'] ?? []
					);

					$email = new Release_Confirmation_Email(
						$plugin,
						$who_to_email,
						[
							'who'     => $tag_committer,
							'readme'  => $readme,
							'headers' => $headers,
							'version' => $release_version,
						]
					);
					$email->send();

					if ( $modified_after_release ) {
						echo "Plugin release {$svn_changed_tag} modified after release; confirmation reset.\n";
					} else {
						echo "Plugin release {$svn_changed_tag} not confirmed; email triggered.\n";
					}
				} elseif ( ! isset( $release['source_revision'] ) ) {
					// Legacy record, unchanged: record its baseline so later checks are tag-specific.
					Plugin_Directory::add_release(
						$plugin,
						[
							'tag'             => $svn_changed_tag,
							'source_revision' => $tag_revision,
						]
					);
				}
			}

			// Now check to see if the stable has been confirmed.
			$release = Plugin_Directory::get_release( $plugin, $stable_tag );
			if ( ! $release ) {
				throw new Exception( "Plugin release {$stable_tag} not found." );
			}

			/*
			 * If the stable release isn't confirmed, the next section will abort processing,
			 * but if this commit didn't touch a stable tag, but rather a confirmed release tag,
			 * then we need to build a new zip for that tag.
			 *
			 * This is required as ZIP building occurs at the end of the import process, yet with
			 * release confirmations that will not be reached when the release isn't yet confirmed.
			 */
			if ( ! $release['confirmed'] && ! $touches_stable_tag ) {
				$zips_to_build = [];
				foreach ( $svn_changed_tags as $svn_changed_tag ) {
					// Never build the stable tag zips here.
					if ( $svn_changed_tag === $stable_tag ) {
						continue;
					}

					// Always allow trunk to be rebuilt.
					if ( 'trunk' === $svn_changed_tag ) {
						$zips_to_build[] = 'trunk';
						continue;
					}

					/*
					 * If the tag is confirmed, but the zips haven't been built, then build them.
					 * This can be a confirmed release, but one which isn't set as stable.
					 */
					$this_release = Plugin_Directory::get_release( $plugin, $svn_changed_tag );
					if ( $this_release['confirmed'] && ! $this_release['zips_built'] ) {
						$zips_to_build[] = $this_release['tag'];
					}
				}

				if ( $zips_to_build ) {
					// NOTE: $stable_tag not passed, as it's not yet stable and won't be.
					$this->rebuild_affected_zips( $plugin_slug, $current_stable_tag, $current_stable_tag, $zips_to_build, $svn_revision_triggered );
				}
			}

			// Check that the tag is approved (If the release needed to be confirmed).
			if ( ! $release['confirmed'] && $release['confirmations_required'] ) {

				if ( ! in_array( $last_committer, $release['committer'], true ) ) {
					$release['committer'][] = $last_committer;
				}
				if ( ! in_array( $last_revision, $release['revision'], true ) ) {
					$release['revision'][] = $last_revision;
				}

				// Update with ^
				Plugin_Directory::add_release( $plugin, $release );

				/**
				 * Fire an action to let other code know this plugin has a pending release.
				 *
				 * @param WP_Post $plugin  The plugin being imported.
				 * @param array   $release The release data.
				 * @param array   $data    The data from the import process.
				 */
				do_action( 'wporg_plugins_import_release_pending', $plugin, $release, $data );

				throw new Exception( "Plugin release {$stable_tag} not confirmed." );
			}

			// At this point we can assume that the release was confirmed, and should be imported.
		}

		/**
		 * Fire an import action, now that we've exported the plugin data, and validates that it's ready for release.
		 *
		 * NOTE: This fires after Release Confirmation, such that the plugin is 100% ready to be released.
		 *
		 * @param Import  $this                   The Plugin Importer object.
		 * @param WP_Post $plugin                 The plugin being imported.
		 * @param array   $release                The release data. Only present if the plugin uses Release Confirmation.
		 * @param array   $data                   The data from the import process.
		 * @param array   $svn_changed_tags       The list of SVN tags/trunk affected to trigger the import.
		 * @param array   $svn_tags_deleted       The list of SVN tags/trunk deleted in the import.
		 * @param int     $svn_revision_triggered The SVN revision that triggered the import.
		 */
		do_action( 'wporg_plugins_import_process', $this, $plugin, $release ?? false, $data, $svn_changed_tags, $svn_tags_deleted, $svn_revision_triggered );

		$content = '';
		if ( $readme->sections ) {
			foreach ( $readme->sections as $section => $section_content ) {
				$content .= "\n\n<!--section={$section}-->\n{$section_content}";
			}
		} elseif ( ! empty( $headers->Description ) ) {
			$content = "<!--section=description-->\n{$headers->Description}";
		}

		// Use the Readme name, as long as it's not the plugin slug.
		if (
			$readme->name &&
			$readme->name !== $plugin->post_name
		) {
			$plugin->post_title = $readme->name;
		} elseif ( $headers->Name ) {
			$plugin->post_title = strip_tags( $headers->Name );
		}

		$plugin->post_content = trim( $content ) ?: $plugin->post_content;
		$plugin->post_excerpt = trim( $readme->short_description ) ?: $headers->Description ?: $plugin->post_excerpt;

		/*
		 * Bump last updated if:
		 * - The version has changed.
		 * - The post_modified is empty, which is the case for many initial checkins.
		 * - A tag (or trunk) commit is made to the current stable. The build has changed, even if not new version.
		 */
		if (
			( ! $version || $version != get_post_meta( $plugin->ID, 'version', true ) ) ||
			$plugin->post_modified == '0000-00-00 00:00:00' ||
			( $svn_changed_tags && in_array( ( $stable_tag ?: 'trunk' ), $svn_changed_tags, true ) )
		) {
			if ( $last_modified ) {
				$plugin->post_modified = $plugin->post_modified_gmt = $last_modified;
			} else {
				$plugin->post_modified = $plugin->post_modified_gmt = current_time( 'mysql' );
			}
		}

		// Plugins should move from 'approved' to 'publish' on first parse
		// `export_and_parse_plugin()` will throw an exception in the case where plugin files cannot be found,
		// so by this time the plugin should be live.
		if ( 'approved' === $plugin->post_status ) {
			$plugin->post_status = 'publish';

			// The post date should be set to when the plugin is first set live.
			$plugin->post_date = $plugin->post_date_gmt = current_time( 'mysql' );
		}

		wp_update_post( $plugin );

		// Set categories if there aren't any yet. wp-admin takes precedent.
		if ( ! wp_get_object_terms( $plugin->ID, 'plugin_category', array( 'fields' => 'ids' ) ) ) {
			wp_set_object_terms( $plugin->ID, Tag_To_Category::map( $readme->tags ), 'plugin_category' );
		}

		// Set tags from the readme
		wp_set_object_terms( $plugin->ID, $readme->tags, 'plugin_tags' );

		// Update the contributors list
		wp_set_object_terms( $plugin->ID, $readme->contributors, 'plugin_contributors' );

		// Update the committers list
		Tools::sync_plugin_committers_with_taxonomy( $plugin->post_name );

		if ( in_array( 'adopt-me', $readme->tags ) ) {
			wp_set_object_terms( $plugin->ID, 'adopt-me', 'plugin_section' );
		} else {
			wp_remove_object_terms( $plugin->ID, 'adopt-me', 'plugin_section' );
		}

		// Update all readme meta
		foreach ( $this->readme_fields as $readme_field ) {
			update_post_meta( $plugin->ID, $readme_field, wp_slash( $readme->$readme_field ) );
		}

		// Store the plugin headers we need. Note that 'Version', 'RequiresWP', and 'RequiresPHP' are handled below.
		foreach ( $this->plugin_headers as $plugin_header => $meta_field ) {
			update_post_meta( $plugin->ID, $meta_field, ( isset( $headers->$plugin_header ) ? wp_slash( $headers->$plugin_header ) : '' ) );
		}

		// Update the Requires, Requires PHP, and Tested up to fields, prefering those from the Plugin Headers.
		// Unfortunately the value within $headers is not always a well-formed value.
		$requires     = $readme->requires;
		$requires_php = $readme->requires_php;
		$tested       = $readme->tested;
		if ( $headers->RequiresWP && preg_match( '!^[\d.]{3,}$!', $headers->RequiresWP ) ) {
			$requires = $headers->RequiresWP;
		}
		if ( $headers->RequiresPHP && preg_match( '!^[\d.]{3,}$!', $headers->RequiresPHP ) ) {
			$requires_php = $headers->RequiresPHP;
		}
		if ( $headers->TestedUpTo && preg_match( '!^[\d.]{3,}$!', $headers->TestedUpTo ) ) {
			$tested = $headers->TestedUpTo;
		}

		// Sanitize the tested version.
		if ( function_exists( 'wporg_get_version_equivalents' ) ) {
			foreach ( wporg_get_version_equivalents() as $latest_compatible_version => $compatible_with ) {
				if ( in_array( $tested, $compatible_with, true ) ) {
					$tested = $latest_compatible_version;
					break;
				}
			}
		}

		// Keep a log of all plugin names used by the plugin over time.
		$plugin_names = get_post_meta( $plugin->ID, 'plugin_name_history', true ) ?: [];
		if ( ! isset( $plugin_names[ $headers->Name ] ) ) {
			// [ 'Plugin Name' => '1.2.3', 'Plugin New Name' => '4.5.6' ]
			$plugin_names[ $headers->Name ] = $headers->Version;
			update_post_meta( $plugin->ID, 'plugin_name_history', wp_slash( $plugin_names ) );
		}

		update_post_meta( $plugin->ID, 'requires_plugins',   wp_slash( $requires_plugins ) );
		update_post_meta( $plugin->ID, 'requires',           wp_slash( $requires ) );
		update_post_meta( $plugin->ID, 'requires_php',       wp_slash( $requires_php ) );
		update_post_meta( $plugin->ID, 'tested',             wp_slash( $tested ) );
		update_post_meta( $plugin->ID, 'tagged_versions',    wp_slash( array_keys( $tagged_versions ) ) );
		update_post_meta( $plugin->ID, 'sections',           wp_slash( array_keys( $readme->sections ) ) );
		update_post_meta( $plugin->ID, 'assets_screenshots', wp_slash( $assets['screenshot'] ) );
		update_post_meta( $plugin->ID, 'assets_icons',       wp_slash( $assets['icon'] ) );
		update_post_meta( $plugin->ID, 'assets_banners',     wp_slash( $assets['banner'] ) );
		update_post_meta( $plugin->ID, 'last_updated',       wp_slash( $plugin->post_modified_gmt ) );

		// Calculate the 'plugin color' from the average color of the banner if provided. This is used for fallback icons.
		$banner_average_color = '';
		if ( $first_banner = reset( $assets['banner'] ) ) {
			// The Banners are not stored locally, which is why a URL is used here
			$banner_average_color = Tools::get_image_average_color( Template::get_asset_url( $plugin, $first_banner, false /* no CDN */ ) );
		}
		update_post_meta( $plugin->ID, 'assets_banners_color', wp_slash( $banner_average_color ) );

		// Store the content of blueprint files, if they're available and valid.
		if ( isset( $assets['blueprint'] ) && count( $assets['blueprint'] ) > 0 ) {
			update_post_meta( $plugin->ID, 'assets_blueprints', wp_slash( $assets['blueprint'] ) );
		} else {
			delete_post_meta( $plugin->ID, 'assets_blueprints' );
			// TODO: maybe if ( $touches_stable_tag )?
			add_post_meta( $plugin->ID, '_missing_blueprint_notice', 1, true );
		}

		// Store the block data, if known
		if ( count( $blocks ) ) {
			$changed = update_post_meta( $plugin->ID, 'all_blocks', $blocks );
			if ( $changed || count ( get_post_meta( $plugin->ID, 'block_name' ) ) !== count ( $blocks ) ) {
				delete_post_meta( $plugin->ID, 'block_name' );
				delete_post_meta( $plugin->ID, 'block_title' );

				foreach ( $blocks as $block ) {
					add_post_meta( $plugin->ID, 'block_name', $block->name, false );
					add_post_meta( $plugin->ID, 'block_title', $block->title, false );
				}
			}
		} else {
			delete_post_meta( $plugin->ID, 'all_blocks' );
			delete_post_meta( $plugin->ID, 'block_name' );
			delete_post_meta( $plugin->ID, 'block_title' );
		}

		// Only store block_files for plugins in the block directory
		if ( count( $block_files ) && has_term( 'block', 'plugin_section', $plugin->ID ) ) {
			update_post_meta( $plugin->ID, 'block_files', $block_files );
		} else {
			delete_post_meta( $plugin->ID, 'block_files' );
		}

		// Dashboard widgets: assign the section term and store widget names.
		if ( $dashboard_widgets ) {
			wp_add_object_terms( $plugin->ID, 'dashboard-widgets', 'plugin_section' );

			delete_post_meta( $plugin->ID, 'dashboard_widget_name' );
			foreach ( $dashboard_widgets as $widget_name ) {
				if ( '' === $widget_name ) {
					continue;
				}
				add_post_meta( $plugin->ID, 'dashboard_widget_name', $widget_name, false );
			}
		} else {
			wp_remove_object_terms( $plugin->ID, 'dashboard-widgets', 'plugin_section' );
			delete_post_meta( $plugin->ID, 'dashboard_widget_name' );
		}

		// Add the release to storage.
		if ( 'trunk' != $stable_tag ) {
			Plugin_Directory::add_release(
				$plugin,
				[
					'tag'       => $stable_tag,
					'version'   => $version,
					'committer' => [ $last_committer ],
					'revision'  => [ $last_revision ]
				]
			);
		} elseif ( 'trunk' === $stable_tag && version_compare( $version, $plugin->version, '>' ) ) {
			// This is a new version, released from trunk.
			Plugin_Directory::add_release(
				$plugin,
				[
					'tag'       => "trunk@{$version}",
					'version'   => $version,
					'committer' => [ $last_committer ],
					'revision'  => [ $last_revision ]
				]
			);
		}

		$this->rebuild_affected_zips( $plugin_slug, $stable_tag, $current_stable_tag, $svn_changed_tags, $svn_revision_triggered );

		// If we've got a new version, store the last version in the plugin meta.
		if ( $version && $version !== $plugin->version ) {
			update_post_meta( $plugin->ID, 'last_version', wp_slash( $plugin->version ) );
			update_post_meta( $plugin->ID, 'last_stable_tag', wp_slash( $current_stable_tag ) );
			update_post_meta( $plugin->ID, 'last_version_date', wp_slash( $plugin->version_date ) );

			// Keep the date of the last version change, this often differs from the last_updated/post_modified dates.
			update_post_meta( $plugin->ID, 'version_date', wp_slash( current_time( 'mysql' ) ) );
		}

		// Finally, set the new version live.
		update_post_meta( $plugin->ID, 'stable_tag', wp_slash( $stable_tag ) );
		update_post_meta( $plugin->ID, 'version',    wp_slash( $version ) );
		// Update the list of tags last, as it controls which ZIPs are present in the 'Previous versions' section and info API.
		update_post_meta( $plugin->ID, 'tags',       wp_slash( $tagged_versions ) );

		// Ensure that the API gets the updated data
		API_Update_Updater::update_single_plugin( $plugin->post_name );
		Plugins_Info_API::flush_plugin_information_cache( $plugin->post_name );

		/**
		 * Action that fires after a plugin is imported.
		 *
		 * @param WP_Post $plugin         The plugin updated.
		 * @param string  $stable_tag     The new stable tag for the plugin.
		 * @param string  $old_stable_tag The previous stable tag for the plugin.
		 * @param array   $changed_tags   The list of SVN tags/trunk affected to trigger the import.
		 * @param int     $svn_revision   The SVN revision that triggered the import.
		 * @param array   $warnings       The list of warnings generated during the import process.
		 */
		do_action( 'wporg_plugins_imported', $plugin, $stable_tag, $current_stable_tag, $svn_changed_tags, $svn_revision_triggered, $this->warnings );

		return true;
	}

	/**
	 * (Re)build plugin ZIPs affected by this commit.
	 *
	 * @param string $plugin_slug            The plugin slug.
	 * @param string $stable_tag             The new stable tag.
	 * @param string $current_stable_tag     The current stable tag.
	 * @param array  $svn_changed_tags       The list of SVN tags modified since last import.
	 * @param string $svn_revision_triggered The SVN revision which triggered the rebuild.
	 *
	 * @return bool
	 */
	protected function rebuild_affected_zips( $plugin_slug, $stable_tag, $current_stable_tag, $svn_changed_tags, $svn_revision_triggered = 0 ) {
		$versions_to_build = $svn_changed_tags;

		// Ensure that the stable zip is built/rebuilt if need be.
		if ( $stable_tag != $current_stable_tag && ! in_array( $stable_tag, $versions_to_build ) ) {
			$versions_to_build[] = $stable_tag;
		}

		$plugin = Plugin_Directory::get_plugin_post( $plugin_slug );

		// Don't rebuild release-confirmation-required tags.
		if ( $plugin->release_confirmation ) {
			foreach ( $versions_to_build as $i => $tag ) {
				// Trunk should always be built, and will never be set as the stable tag when confirmations are enabled.
				if ( 'trunk' === $tag ) {
					continue;
				}

				$release = Plugin_Directory::get_release( $plugin, $tag );

				if (
					// If the release isn't known, skip.
					! $release ||
					// If the release isn't confirmed AND confirmations were required, skip.
					( ! $release['confirmed'] && $release['confirmations_required'] ) ||
					// If the release has had its ZIPs built, skip if it required confirmations.
					( $release['zips_built'] && $release['confirmations_required'] )
				) {
					unset( $versions_to_build[ $i ] );
				}
			}

			if ( $versions_to_build ) {
				echo "Building ZIPs for {$plugin_slug}: " . implode( ', ', $versions_to_build ) . "\n";
			}
		}

		if ( ! $versions_to_build ) {
			return false;
		}

		// Rebuild/Build $build_zips
		try {
			// This will rebuild the ZIP.
			$zip_builder    = new Builder();
			$built_versions = $zip_builder->build(
				$plugin_slug,
				array_unique( $versions_to_build ),
				$svn_revision_triggered ?
					"{$plugin_slug}: ZIP build triggered by https://plugins.trac.wordpress.org/changeset/{$svn_revision_triggered}" :
					"{$plugin_slug}: ZIP build triggered by " . php_uname( 'n' ),
				$stable_tag
			);
		} catch ( Exception $e ) {
			$failed_versions = array_unique( $versions_to_build );
			$error           = preg_replace( '/[\r\n\t]+/', ' ', $e->getMessage() );

			$this->warnings['zip_build_failed'] = [
				'versions' => $failed_versions,
				'message'  => $error,
			];

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Routed to the error log via E_USER_WARNING; raw is fine.
			trigger_error( sprintf( '%s: ZIP build failed for %s: %s', $plugin_slug, implode( ', ', $failed_versions ), $error ), E_USER_WARNING );

			return false;
		}

		// Mark only the ZIPs that actually built, each with its export revision.
		Plugin_Directory::mark_zips_built( $plugin, $built_versions );

		return true;
	}

	/**
	 * Export a plugin and determine all the information about the current state of the plugin.
	 *
	 * - Creates a /trunk/ export of the plugin.
	 * - Creates a /stable/ export of the stable_tag if specified, falling back to /trunk/.
	 * - Handles readme.md & readme.txt prefering the latter.
	 * - Searches for Screenshots in /$stable/ and in /assets/ (listed remotely).
	 *
	 * @throws \Exception
	 *
	 * @param string $plugin_slug The slug of the plugin to parse.
	 *
	 * @return array {
	 *   'readme', 'stable_tag', 'plugin_headers', 'assets', 'tagged_versions'
	 * }
	 */
	protected function export_and_parse_plugin( $plugin_slug ) {
		$tmp_dir = Filesystem::temp_directory( "process-{$plugin_slug}" );

		// We assume the stable tag is trunk to start with.
		$stable_tag = 'trunk';

		// Find the trunk readme file, list remotely to avoid checking out the entire directory.
		$trunk_files = SVN::ls( self::PLUGIN_SVN_BASE . "/{$plugin_slug}/trunk" ) ?: array();

		// Find the list of tagged versions of the plugin.
		$tagged_versions    = [];
		$tagged_versions_raw = SVN::ls( "https://plugins.svn.wordpress.org/{$plugin_slug}/tags/", true ) ?: [];
		foreach ( $tagged_versions_raw as $entry ) {
			// Discard files
			if ( 'dir' !== $entry['kind'] ) {
				continue;
			}

			$tag = $entry['filename'];

			// Prefix the 0 for plugin versions like 0.1
			if ( '.' == substr( $tag, 0, 1 ) ) {
				$tag = "0{$tag}";
			}

			$tagged_versions[ $tag ] = [
				'tag'      => $entry['filename'],
				'author'   => $entry['author'],
				'date'     => $entry['date'],
				'revision' => (int) ( $entry['revision'] ?? 0 ),
			];
		}

		// Not all plugins utilise `trunk`, some just tag versions.
		if ( ! $trunk_files ) {
			if ( ! $tagged_versions ) {
				throw new Exception( 'Plugin has no files in trunk, nor tags.' );
			}

			$stable_tag = array_reduce( array_keys( $tagged_versions ), function( $a, $b ) {
				return version_compare( $a, $b, '>' ) ? $a : $b;
			} );
		}

		// A plugin historically doesn't have to have a readme.
		$trunk_readme_files = preg_grep( '!^readme.(txt|md)$!i', $trunk_files );
		if ( $trunk_readme_files ) {
			$trunk_readme_file = reset( $trunk_readme_files );
			// Preference readme.txt over readme.md if both exist.
			foreach ( $trunk_readme_files as $f ) {
				if ( '.txt' == strtolower( substr( $f, -4 ) ) ) {
					$trunk_readme_file = $f;
					break;
				}
			}

			$trunk_readme_file = self::PLUGIN_SVN_BASE . "/{$plugin_slug}/trunk/{$trunk_readme_file}";
			$trunk_readme      = new Readme_Parser( $trunk_readme_file );

			$stable_tag = $trunk_readme->stable_tag;
		}

		$svn_info = false;
		if ( $stable_tag && 'trunk' != $stable_tag ) {
			$stable_url = self::PLUGIN_SVN_BASE . "/{$plugin_slug}/tags/{$stable_tag}";
			$svn_info = SVN::info( $stable_url );

			if ( ! $svn_info['result'] && '0.' === substr( $stable_tag, 0, 2 ) ) {
				// Handle tags which we store as 0.blah but are in /tags/.blah
				$_stable_tag = substr( $stable_tag, 1 );
				$stable_url  = self::PLUGIN_SVN_BASE . "/{$plugin_slug}/tags/{$_stable_tag}";
				$svn_info    = SVN::info( $stable_url );
			}

			// Verify that the tag has files, falling back to trunk if not.
			if ( ! SVN::ls( $stable_url ) ) {
				$svn_info = false;
			}
		}

		// Fall back to using `trunk` as stable, if the tag doesn't exist.
		if ( ! $svn_info || ! $svn_info['result'] ) {
			if ( 'trunk' !== $stable_tag ) {
				$this->warnings['stable_tag_invalid_trunk_fallback'] = $stable_tag;
				$this->warnings['stable_tag_invalid']                = true;
			}

			$stable_tag = 'trunk';
			$stable_url = self::PLUGIN_SVN_BASE . "/{$plugin_slug}/trunk";
			$svn_info   = SVN::info( $stable_url );
		}

		if ( ! $svn_info['result'] ) {
			throw new Exception( 'Could not find stable SVN URL: ' . ( $svn_info['errors'] ? implode( ' ', reset( $svn_info['errors'] ) ) : 'Unknown error' ) );
		}

		$last_modified = false;
		if ( preg_match( '/^([0-9]{4}\-[0-9]{2}\-[0-9]{2} [0-9]{1,2}:[0-9]{2}:[0-9]{2})/', $svn_info['result']['Last Changed Date'] ?? '', $m ) ) {
			$last_modified = $m[0];
		}

		$last_committer = $svn_info['result']['Last Changed Author'] ?? '';
		$last_revision  = $svn_info['result']['Last Changed Rev'] ?? 0;

		/*
		 * Before we check out the plugin, ensure that it has *files* in the folder.
		 *
		 * Some plugins accidentally copy their entire SVN repo into the tagged folder, which
		 * causes a recursive checkout many multiple gigabytes in size, causing issues for WordPress.org.
		 */
		if ( ! wp_list_filter( SVN::ls( $stable_url, true ), [ 'kind' => 'file' ] ) ) {
			throw new Exception( "Could not create SVN export of {$stable_url}: Path appears not to have any files." );
		}

		$svn_export = SVN::export(
			$stable_url,
			$tmp_dir . '/export',
			array(
				'ignore-externals',
			)
		);

		if ( ! $svn_export['result'] || empty( $svn_export['revision'] ) ) {
			// Catch the case where exporting a tag finds nothing, but there was nothing in trunk either.
			if ( ! $trunk_files ) {
				throw new Exception( 'Plugin has no files in trunk, nor tags.' );
			}

			throw new Exception( 'Could not create SVN export: ' . ( $svn_export['errors'] ? implode( ' ', reset( $svn_export['errors'] ) ) : 'Unknown error' ) );
		}

		// The readme may not actually exist, but that's okay.
		$readme = $this->find_readme_file( $tmp_dir . '/export' );
		$readme = new Readme_Parser( $readme );

		// There must be valid plugin headers though.
		$plugin_headers = $this->find_plugin_headers( "$tmp_dir/export" );
		if ( ! $plugin_headers ) {
			throw new Exception( 'Could not find the plugin headers.' );
		}

		// Now we look in the /assets/ folder for banners, screenshots, and icons.
		$assets = array(
			'screenshot' => array(),
			'banner'     => array(),
			'icon'       => array(),
			'blueprint'  => array(),
		);

		$asset_limits = array(
			'screenshot' => 10 * MB_IN_BYTES,
			'banner'     => 4 * MB_IN_BYTES,
			'icon'       => 1 * MB_IN_BYTES,
			'blueprint'  => 100 * KB_IN_BYTES,
		);

		// Previously-imported asset metadata, used to skip re-reading any file whose
		// SVN revision hasn't changed.
		$prior_assets = array(
			'screenshot' => get_post_meta( $this->plugin->ID, 'assets_screenshots', true ) ?: array(),
			'banner'     => get_post_meta( $this->plugin->ID, 'assets_banners',     true ) ?: array(),
			'icon'       => get_post_meta( $this->plugin->ID, 'assets_icons',       true ) ?: array(),
		);

		$svn_blueprints_folder = null;
		$svn_assets_folder = SVN::ls( self::PLUGIN_SVN_BASE . "/{$plugin_slug}/assets/", true /* verbose */ );
		if ( $svn_assets_folder ) { // /assets/ may not exist.
			foreach ( $svn_assets_folder as $asset ) {
				if ( 'blueprints' === $asset['filename'] ) {
					$svn_blueprints_folder = self::PLUGIN_SVN_BASE . "/{$plugin_slug}/assets/blueprints/";
					continue;
				}

				// screenshot-0(-rtl)(-de_DE).(png|jpg|jpeg|gif) || banner-772x250.PNG || icon.svg
				if ( ! preg_match( '!^(?P<type>screenshot|banner|icon)(?:-(?P<resolution>\d+(?:\D\d+)?)(-rtl)?(?:-(?P<locale>[a-z]{2,3}(?:_[A-Z]{2})?(?:_[a-z0-9]+)?))?\.(png|jpg|jpeg|gif)|\.svg)$!iu', $asset['filename'], $m ) ) {
					continue;
				}

				$type = strtolower( $m['type'] );

				// Don't import zero-byte or oversize assets.
				if ( ! $asset['filesize'] || $asset['filesize'] > $asset_limits[ $type ] ) {
					continue;
				}

				$filename   = $asset['filename'];
				$revision   = $asset['revision'];
				$location   = 'assets';
				$resolution = isset( $m['resolution'] ) ? $m['resolution'] : false;
				$locale     = isset( $m['locale'] )     ? $m['locale']     : false;

				// Ensure the resolution key is in the expected 123x123 format.
				// Resolution is also the screenshot number, in which case it's stringy numeric only.
				if ( $resolution && 'screenshot' === $type ) {
					$resolution = (string)( (int) $resolution );
				} else if ( $resolution ) {
					$resolution = preg_replace( '/[^0-9]/u', 'x', $resolution );
				}

				$record = compact( 'filename', 'revision', 'resolution', 'location', 'locale' );

				$record = self::enrich_asset_dimensions(
					$record,
					$prior_assets[ $type ][ $filename ] ?? null,
					$this->plugin
				);

				$assets[ $type ][ $asset['filename'] ] = $record;
			}
		}

		if ( $svn_blueprints_folder ) {
			$svn_export = SVN::export(
				$svn_blueprints_folder,
				$tmp_dir . '/blueprints',
				array(
					'ignore-externals',
				)
			);

			foreach ( Filesystem::list_files( "$tmp_dir/blueprints/", false /* non-recursive */, '!^blueprint[-\w]*\.json$!' ) as $plugin_blueprint ) {
				$filename = basename( $plugin_blueprint );

				// Don't import oversize blueprints
				if ( filesize( $plugin_blueprint ) > $asset_limits['blueprint'] ) {
					continue;
				}

				// Make sure the blueprint file is valid json and contains the essentials; also minimize whitespace etc.
				$contents = self::normalize_blueprint_json( file_get_contents( $plugin_blueprint ), $plugin_slug );
				if ( !$contents ) {
					continue;
				}

				$assets['blueprint'][ $filename ] = array(
					'filename'   => $filename,
					'revision'   => $svn_export['revision'],
					'resolution' => false,
					'location'   => 'assets',
					'locale'     => '',
					'contents'   => $contents
				);
			}

			// For the time being, limit the number of blueprints. Revise this when the case for multiple blueprints is more clear.
			if ( isset( $assets['blueprint'] ) && count ( $assets['blueprint'] ) > 10 ) {
				$assets['blueprint'] = array_slice( $assets['blueprint'], 0, 10, true );
			}
		}

		// Find screenshots in the stable plugin folder (but don't overwrite /assets/)
		foreach ( Filesystem::list_files( "$tmp_dir/export/", false /* non-recursive */, '!^screenshot-\d+\.(jpeg|jpg|png|gif)$!' ) as $plugin_screenshot ) {
			$filename      = basename( $plugin_screenshot );
			$screenshot_id = substr( $filename, strpos( $filename, '-' ) + 1 );
			$screenshot_id = substr( $screenshot_id, 0, strpos( $screenshot_id, '.' ) );

			if ( isset( $assets['screenshot'][ $filename ] ) ) {
				// Skip it, it exists within /assets/ already
				continue;
			}

			// Don't import zero-byte or oversize assets.
			$screenshot_size = filesize( $plugin_screenshot );
			if ( ! $screenshot_size || $screenshot_size > $asset_limits['screenshot'] ) {
				continue;
			}

			$record = array(
				'filename'   => $filename,
				'revision'   => $svn_export['revision'],
				'resolution' => $screenshot_id,
				'location'   => 'plugin',
			);

			$record = self::enrich_asset_dimensions(
				$record,
				$prior_assets['screenshot'][ $filename ] ?? null,
				$this->plugin,
				$plugin_screenshot
			);

			$assets['screenshot'][ $filename ] = $record;
		}

		if ( 'trunk' === $stable_tag ) {
			$stable_path = $stable_tag;
		} else {
			$stable_path  = 'tags/';
			$stable_path .= $_stable_tag ?? $stable_tag;
		}

		// Find registered blocks and their files.
		$blocks = array();
		$block_files = array();
		$potential_block_directories = array( '.' );
		$base_dir = "$tmp_dir/export";

		$block_json_files = Filesystem::list_files( $base_dir, true, '!(?:^|/)block\.json$!i' );
		if ( ! empty( $block_json_files ) ) {
			foreach ( $block_json_files as $filename ) {
				$blocks_in_file = $this->find_blocks_in_file( $filename );
				$relative_filename = str_replace( "$base_dir/", '', $filename );
				$potential_block_directories[] = dirname( $relative_filename );
				foreach ( $blocks_in_file as $block ) {
					if ( ! empty( $block->name ) ) {
						$blocks[ $block->name ] = $block;
					}

					$extracted_files = $this->extract_file_paths_from_block_json( $block, dirname( $relative_filename ) );
					if ( ! empty( $extracted_files ) ) {
						$block_files = array_merge(
							$block_files,
							array_map(
								function( $file ) use ( $stable_path ) {
									return "/$stable_path/" . ltrim( $file, '\\' );
								},
								$extracted_files
							)
						);
					}
				}
			}
		} else {
			foreach ( Filesystem::list_files( $base_dir, true, '!\.(?:php|js|jsx)$!i' ) as $filename ) {
				$blocks_in_file = $this->find_blocks_in_file( $filename );
				if ( ! empty( $blocks_in_file ) ) {
					$relative_filename = str_replace( "$base_dir/", '', $filename );
					$potential_block_directories[] = dirname( $relative_filename );
					foreach ( $blocks_in_file as $block ) {
						if ( isset( $blocks[ $block->name ] ) ) {
							$blocks[ $block->name ] = (object) array_merge( (array) $blocks[ $block->name ], array_filter( (array) $block ) );
						} else {
							$blocks[ $block->name ] = $block;
						}
					}
				}
			}
		}

		// Set the fallback name for the blocks.
		foreach ( $blocks as $block_name => &$block ) {
			if ( empty( $block->title ) ) {
				$block->title = $block_name;
				// If the block duplicates the namespace, remove it. 'plugin-slug/plugin-slug-block-name'
				$block->title = preg_replace( '#^([^/]+)/\\1-?#i', '$1/', $block->title );
				// If the namespace is the slug (w/ or w/o dashes..), remove it.
				if (
					str_starts_with( $block->title, $plugin_slug . '/' ) ||
					str_starts_with( $block->title, str_replace( '-', '', $plugin_slug ) . '/' )
				) {
					$block->title = explode( '/', $block->title, 2 )[1];
				}
				// Treat any non-wordy characters as spaces.
				$block->title = preg_replace( '/[^a-z]+/', ' ', $block->title );
				// Capitalise all words.
				$block->title = ucwords( $block->title );
			}
		}

		// Remove any core blocks from the block list.
		$blocks = array_filter(
			$blocks,
			function( $block_name ) {
				return 0 !== strpos( $block_name, 'core/' );
			},
			ARRAY_FILTER_USE_KEY
		);

		// Filter the blocks list so that the parent block is first.
		if ( count( $blocks ) > 1 ) {
			$children = array_filter(
				$blocks,
				function( $block ) {
					return isset( $block->parent ) && is_array( $block->parent ) && count( $block->parent );
				}
			);

			$parent = array_filter(
				$blocks,
				function( $block ) {
					return ! isset( $block->parent ) || ! is_array( $block->parent ) || ! count( $block->parent );
				}
			);

			$blocks = array_merge( $parent, $children );
		}

		// Only search for block files if none were found in a block.json.
		if ( empty( $block_files ) ) {
			$build_files = array();

			$build_files = self::find_possible_block_assets( $base_dir, $potential_block_directories );

			foreach ( $build_files as $file ) {
				$block_files[] = "/$stable_path/" . ltrim( str_replace( "$base_dir/", '', $file ), '/' );
			}
		}

		// Only allow js or css files
		$block_files = array_unique( array_filter( $block_files, function( $filename ) {
			return preg_match( '!\.(?:js|jsx|css)$!i', $filename );
		} ) );

		// Find dashboard widget registrations (wp_add_dashboard_widget calls).
		$dashboard_widgets = array();
		foreach ( Filesystem::list_files( $base_dir, true, '!\.php$!i' ) as $filename ) {
			// Skip third-party dependencies — they are not the plugin itself.
			if ( str_contains( $filename, '/vendor/' ) ) {
				continue;
			}
			foreach ( self::find_dashboard_widgets_in_file( $filename ) as $widget ) {
				$dashboard_widgets[] = $widget;
			}
		}

		return apply_filters(
			'wporg_plugins_export_and_parse_plugin',
			compact( 'readme', 'stable_tag', 'last_modified', 'last_committer', 'last_revision', 'tmp_dir', 'plugin_headers', 'assets', 'tagged_versions', 'blocks', 'block_files', 'dashboard_widgets' ),
			$plugin_slug,
			$this,
		);
	}

	/**
	 * Populate `width` and `height` on an asset record, reusing the prior
	 * import's values when the SVN revision hasn't changed.
	 *
	 * @param array       $record The asset record.
	 * @param array|null  $prior  Matching record from the prior import.
	 * @param \WP_Post    $post   The plugin post.
	 * @param string|null $local  Optional local path to read instead of fetching from SVN.
	 * @return array
	 */
	public static function enrich_asset_dimensions( $record, $prior, $post, $local = null ) {
		if (
			is_array( $prior ) &&
			isset( $prior['revision'], $prior['width'], $prior['height'] ) &&
			(string) $prior['revision'] === (string) $record['revision'] &&
			$prior['width'] > 0 && $prior['height'] > 0
		) {
			$record['width']  = (int) $prior['width'];
			$record['height'] = (int) $prior['height'];

			return $record;
		}

		$size = false;

		if ( $local && file_exists( $local ) ) {
			$size = wp_getimagesize( $local );
		}

		if ( ! $size ) {
			// `wp_tempnam()` lives in wp-admin and isn't loaded by default in CLI/cron contexts.
			require_once ABSPATH . 'wp-admin/includes/file.php';

			$url       = Template::get_asset_url( $post, $record, false /* no CDN */ );
			$temp_file = wp_tempnam( $record['filename'] );

			// Range the first read to 128 KB — enough for the headers of
			// most images. Fall back to a full read only when the prefix
			// isn't enough to decode the header — the falsy `$size` at
			// the bottom of the loop is the implicit retry. Transport
			// errors / non-2xx intentionally bail out via `break`: those
			// failure modes won't be helped by re-requesting the same
			// URL without Range.
			foreach ( array( 128 * KB_IN_BYTES, 0 ) as $limit ) {
				$args = array(
					'timeout'  => 15,
					'stream'   => true,
					'filename' => $temp_file,
				);
				if ( $limit > 0 ) {
					$args['headers']             = array( 'Range' => 'bytes=0-' . ( $limit - 1 ) );
					$args['limit_response_size'] = $limit;
				}

				$response = wp_safe_remote_get( $url, $args );
				$code     = wp_remote_retrieve_response_code( $response );
				if ( is_wp_error( $response ) || ( 200 !== $code && 206 !== $code ) ) {
					break;
				}

				if ( ! file_exists( $temp_file ) || 0 === filesize( $temp_file ) ) {
					break;
				}

				$size = wp_getimagesize( $temp_file );
				if ( $size ) {
					break;
				}
			}

			unlink( $temp_file );
		}

		if ( $size && ! empty( $size[0] ) && ! empty( $size[1] ) ) {
			$record['width']  = (int) $size[0];
			$record['height'] = (int) $size[1];
		}

		return $record;
	}

	/**
	 * Find the plugin readme file.
	 *
	 * Looks for either a readme.txt or readme.md file, prioritizing readme.txt.
	 *
	 * @param string $directory The Directory to search for the readme in.
	 *
	 * @return string The plugin readme.txt or readme.md filename.
	 */
	public static function find_readme_file( $directory ) {
		$files = Filesystem::list_files( $directory, false /* non-recursive */, '!(?:^|/)readme\.(txt|md)$!i' );

		// prioritize readme.txt
		foreach ( $files as $f ) {
			if ( '.txt' == strtolower( substr( $f, -4 ) ) ) {
				return $f;
			}
		}

		return reset( $files );
	}

	/**
	 * Find the plugin headers for the given directory.
	 *
	 * @param string $directory The directory of the plugin.
	 * @param int    $max_depth The maximum depth to search for files. Default: current directory only.
	 *
	 * @return object The plugin headers.
	 */
	public static function find_plugin_headers( $directory, $max_depth = -1 ) {
		$files = Filesystem::list_files( $directory, ( $max_depth > 0 ), '!\.php$!i', $max_depth );

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Add any additional headers required.
		add_filter( 'extra_plugin_headers', array( __CLASS__, 'add_extra_plugin_headers' ) );

		/*
		 * Sometimes plugins have multiple files which we detect as a plugin based on the headers.
		 * We'll break immediately if the file has a `Plugin Name:` header, otherwise
		 * simply return the last set of headers we come across.
		 */
		$headers = false;
		foreach ( $files as $file ) {
			$data = get_plugin_data( $file, false, false );
			if ( array_filter( $data ) ) {
				$data['PluginFile'] = $file;
				$headers            = $data;

				if ( $headers['Name'] ) {
					break;
				}
			}
		}

		remove_filter( 'extra_plugin_headers', array( __CLASS__, 'add_extra_plugin_headers' ) );

		if ( ! $headers ) {
			return false;
		}

		// The extra_plugin_headers filter doesn't let you set the key.
		foreach ( self::add_extra_plugin_headers( [] ) as $key => $header ) {
			if (
				$key != $header &&
				! isset( $headers[ $key ] ) &&
				isset( $headers[ $header ] )
			) {
				$headers[ $key ] = $headers[ $header ];
				unset( $headers[ $header ] );
			}
		}

		return (object) $headers;
	}

	/**
	 * Determine whether a plugin's Version header can be used as a path component.
	 *
	 * @param mixed $version The plugin's Version header value.
	 * @return bool True when the value is safe to use in a path, false otherwise.
	 */
	public static function version_is_path_safe( $version ) {
		if ( ! is_string( $version ) || '' === $version ) {
			return false;
		}

		if ( preg_match( '#[[:cntrl:]]#', $version ) ) {
			return false;
		}

		$segments = preg_split( '#[/\\\\]#', $version );

		return '' !== $segments[0] && ! array_intersect( array( '.', '..' ), $segments );
	}

	/**
	 * Determine whether a plugin's Version header looks like a match for the SVN tag it was released from.
	 *
	 * Both sides are reduced to the leading dotted-numeric portion (e.g. `release-1.4.0` → `1.4.0`,
	 * `1.4.0-beta` → `1.4.0`, `1.0 & beta` → `1.0`), then compared with `version_compare()`. Any
	 * inequality is treated as a mismatch — including the unusual case where the Version header is
	 * ahead of the tag, which is allowable but almost always unintended. `1.0` vs `1.0.0` is treated
	 * as equal after trailing `.0` segments are stripped.
	 *
	 * @param string $version The plugin's Version header value.
	 * @param string $tag     The SVN tag folder name (e.g. `1.4.1`, `v2.0`).
	 * @return bool True when the values appear to match, false when they look mismatched.
	 */
	public static function version_matches_tag( $version, $tag ) {
		$normalize = static function ( $v ) {
			// Capture the leading dotted-numeric portion (plus an optional `-rc` / `-beta` /
			// `-alpha` pre-release suffix, case-insensitive, with optional `.`/no-separator digits)
			// after any non-digit prefix such as `v`, `Version: `, `release-`, `tag-`, or `hover-`.
			if ( ! preg_match( '/^[^0-9]*(\d+(?:\.\d+)*(?:-(?:rc|beta|alpha)(?:\.?\d+)?)?)/i', (string) $v, $m ) ) {
				return '';
			}
			// Lowercase the suffix — version_compare() is not consistently case-insensitive
			// (e.g. `1.0-Beta` < `1.0-beta`), so normalize before comparing.
			$captured = strtolower( $m[1] );
			// Strip trailing `.0` segments so version_compare() treats `1.0` and `1.0.0` as equal.
			// Only applies to the dotted-numeric portion; a pre-release suffix is left alone.
			return preg_replace( '/(\.0+)+(?=(?:-(?:rc|beta|alpha)(?:\.?\d+)?)?$)/', '', $captured );
		};

		$normalized_version = $normalize( $version );
		$normalized_tag     = $normalize( $tag );

		if ( '' === $normalized_version || '' === $normalized_tag ) {
			return true;
		}

		// Flag any inequality. The common case is "forgot to bump the header" (tag ahead of
		// version), but the inverse is also worth flagging — it's allowable yet usually unintended.
		return version_compare( $normalized_tag, $normalized_version, '==' );
	}

	/**
	 * Add support for additional plugin headers prior to WordPress supporting it.
	 *
	 * @param array $headers The headers to look for in plugins.
	 * @return array
	 */
	public static function add_extra_plugin_headers( $headers ) {
		// WordPress Plugin Dependencies - See https://meta.trac.wordpress.org/ticket/6921
		if ( ! isset( $headers['RequiresPlugins'] ) ) {
			$headers['RequiresPlugins'] = 'Requires Plugins';
		}
		// https://meta.trac.wordpress.org/ticket/4621
		if ( ! isset( $headers['TestedUpTo'] ) ) {
			$headers['TestedUpTo'] = 'Tested up to';
		}

		return $headers;
	}

	/**
	 * Look for Gutenberg blocks registered within a single file.
	 *
	 * @param string $filename Pathname of the file.
	 *
	 * @return array An array of objects representing blocks, corresponding to the block.json format where possible.
	 */
	static function find_blocks_in_file( $filename ) {

		$ext = strtolower( pathinfo($filename, PATHINFO_EXTENSION) );

		$blocks = array();

		if ( 'js' === $ext || 'jsx' === $ext ) {
			// Parse a js-style registerBlockType() call.
			// Note that this only works with literal strings for the block name and title, and assumes that order.
			$contents = file_get_contents( $filename );
			if ( $contents && preg_match_all( "#registerBlockType[^{}]{0,500}[(]\s*[\"']([-\w]+/[-\w]+)[\"']\s*,\s*[{][^;]{0,500}?\s*title\s*:[\s\w(.]*[\"']([^\"']*)[\"'](?!\s*[+])#ms", $contents, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					$blocks[] = (object) [
						'name'  => $match[1],
						'title' => $match[2],
					];
				}
			}
		}

		if ( 'php' === $ext ) {
			// Parse register_block_type() and `new WP_Block_Type()` calls.
			// Block names must be literal strings of the form "namespace/name"; the optional
			// 'title' entry inside the second-arg options array is captured when present.
			$contents = file_get_contents( $filename );
			if ( $contents ) {
				foreach ( array( 'register_block_type', 'new WP_Block_Type' ) as $needle ) {
					foreach ( Tokenisation_Helpers::find_function_calls( $contents, $needle ) as $args ) {
						$name = $args[0] ?? null;
						if ( ! is_string( $name ) || ! preg_match( '#^[-\w]+/[-\w]+$#', $name ) ) {
							continue;
						}
						$options = $args[1] ?? null;
						$title   = is_array( $options ) && is_string( $options['title'] ?? null )
							? $options['title']
							: null;
						$blocks[] = (object) array(
							'name'  => $name,
							'title' => $title,
						);
					}
				}
			}
		}

		if ( 'block.json' === basename( $filename ) ) {
			// A block.json file should have everything we want.
			$validator = new Block_JSON\Validator();
			$block     = Block_JSON\Parser::parse( array( 'file' => $filename ) );
			$result    = $validator->validate( $block );
			if ( ! is_wp_error( $block ) && is_wp_error( $result ) ) {
				// Only certain properties must be valid for our purposes here.
				$required_valid_props = array(
					'block.json[editorScript]',
					'block.json[editorStyle]',
					'block.json[name]',
					'block.json[script]',
					'block.json[style]',
				);
				$error = $result->get_error_message();
				$is_json_valid = array_reduce(
					$required_valid_props,
					function( $is_valid, $prop ) use ( $error ) {
						$prop_field = substr( $prop, 11, -1 ); // 'name' in 'block.json[name]'
						return (
							$is_valid &&
							( false === strpos( $error, $prop ) ) &&
							// String in rest_validate_object_value_from_schema()
							( false === strpos( $error, "{$prop_field} is a required property of block.json." ) )
						);
					},
					true
				);
				if ( $is_json_valid ) {
					$blocks[] = $block;
				}
			} elseif ( true === $result ) {
				$blocks[] = $block;
			}
		}

		return $blocks;
	}

	/**
	 * Look for wp_add_dashboard_widget() calls within a single PHP file.
	 *
	 * The second argument is the widget label. When wrapped in a recognised
	 * i18n function (__, _e, _x, _ex, _n, _nx, esc_html__, esc_html_e,
	 * esc_html_x, esc_attr__, esc_attr_e, esc_attr_x, translate,
	 * translate_with_gettext_context), the inner literal is extracted; other
	 * wrappers (e.g. sprintf, esc_html, custom helpers) or non-literal
	 * expressions resolve to an empty string. Each call is still reported so
	 * the section term can be applied even when the label is not parseable.
	 *
	 * @param string $filename Pathname of the file.
	 * @return string[] List of widget label strings (empty string for non-literal labels).
	 */
	public static function find_dashboard_widgets_in_file( $filename ) {
		if ( 'php' !== strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) ) {
			return array();
		}

		$contents = file_get_contents( $filename );
		if ( ! $contents ) {
			return array();
		}

		$widgets = array();
		foreach ( Tokenisation_Helpers::find_function_calls( $contents, 'wp_add_dashboard_widget' ) as $args ) {
			$label     = $args[1] ?? null;
			$widgets[] = is_string( $label ) ? $label : '';
		}
		return array_unique( $widgets );
	}

	/**
	 * Get script and style file paths from an imported block.json.
	 *
	 * @param object $parsed_json
	 * @param string $block_json_path
	 *
	 * @return array
	 */
	static function extract_file_paths_from_block_json( $parsed_json, $block_json_path = '' ) {
		$files = array();

		$props = array( 'editorScript', 'script', 'editorStyle', 'style' );

		foreach ( $props as $prop ) {
			if ( isset( $parsed_json->$prop ) ) {
				foreach ( (array) $parsed_json->$prop as $file ) {
					if ( str_starts_with( $file, 'file:' ) || str_contains( $file, '.' ) ) {
						$files[] = trailingslashit( $block_json_path ) . remove_block_asset_path_prefix( $file );
					} else {
						// script handle.. not handled.
					}
				}
			}
		}

		return $files;
	}

	/**
	 * Find likely JS and CSS block asset files in a given directory.
	 *
	 * @param string $base_dir Base path in which to search.
	 * @param array $potential_block_directories Subdirectories likely to contain block assets, if known. Optional.
	 *
	 * @return array
	 */
	static function find_possible_block_assets( $base_dir, $potential_block_directories = null ) {
		if ( empty( $potential_block_directories ) || !is_array( $potential_block_directories ) ) {
			$potential_block_directories = array( '.' );
		}

		$build_files = array();

		foreach ( $potential_block_directories as $block_dir ) {
			// dirname() returns . when there is no directory separator present.
			if ( '.' === $block_dir ) {
				$block_dir = '';
			}

			// First look for a dedicated "build" or "dist" directory.
			foreach ( array( 'build', 'dist' ) as $dirname ) {
				if ( is_dir( "$base_dir/$block_dir/$dirname" ) ) {
					$build_files += Filesystem::list_files( "$base_dir/$block_dir/$dirname", true, '!\.(?:js|jsx|css)$!i' );
				}
			}

			// There must be at least on JS file, so if only css was found, keep looking.
			if ( empty( preg_grep( '!\.(?:js|jsx)$!i', $build_files ) ) ) {
				// Then check for files in the current directory with "build" or "min" in the filename.
				$build_files += Filesystem::list_files( "$base_dir/$block_dir", false, '![_\-\.]+(?:build|dist|min)[_\-\.]+!i' );
			}

			if ( empty( preg_grep( '!\.(?:js|jsx)$!i', $build_files ) ) ) {
				// Finally, just grab whatever js/css files there are in the current directory.
				$build_files += Filesystem::list_files( "$base_dir/$block_dir", false, '#(?<!webpack\.config)\.(?:js|jsx|css)$#i' );
			}
		}

		if ( empty( preg_grep( '!\.(?:js|jsx)$!i', $build_files ) ) ) {
			// Nothing in the potential block directories. Check if we somehow missed build/dist directories in the root.
			foreach ( array( 'build', 'dist' ) as $dirname ) {
				if ( is_dir( "$base_dir/$dirname" ) ) {
					$build_files += Filesystem::list_files( "$base_dir/$dirname", true, '!\.(?:js|jsx|css)$!i' );
				}
			}
		}

		if ( empty( preg_grep( '!\.(?:js|jsx)$!i', $build_files ) ) ) {
			// Still nothing. Take on last wild swing.
			$build_files += Filesystem::list_files( $base_dir, false, '!\.(?:js|jsx|css)$!i' );
		}

		return array_unique( $build_files );
	}

	static function normalize_blueprint_json( $blueprint_file_contents, $plugin_slug ) {
		$decoded_file = json_decode( $blueprint_file_contents, true );

		$contents = false;
		if ( is_array( $decoded_file ) && JSON_ERROR_NONE === json_last_error() ) {

			$has_self_install_step = false;
			if ( isset( $decoded_file[ 'steps' ] ) ) {
				// Null & falsey items are often present in auto-generated blueprints, reindex to avoid serialising to an object.
				$decoded_file[ 'steps' ] = array_values( array_filter( $decoded_file[ 'steps' ] ) );

				foreach ( $decoded_file[ 'steps' ] as &$step ) {
					// Normalize a "install (plugin|theme) from url" to a install-by-slug.
					if (
						'installPlugin' === $step['step'] ||
						'installTheme' === $step['step']
					) {
						$keys = [
							'pluginZipFile',
							'pluginData',
							'themeZipFile',
							'themeData'
						];
						foreach ( $keys as $key ) {
							if ( preg_match( '!^https?://downloads\.wordpress\.org/[^/]+/(?P<slug>[a-z0-9-_]+)(\.(?P<version>.+?))?\.zip($|[?])!i', $step[ $key ]['url'] ?? '', $m ) ) {
								unset( $step[ $key ] );

								if ( 'installPlugin' === $step['step'] ) {
									$step[ 'pluginData' ] = [
										'resource' => 'wordpress.org/plugins',
										'slug'     => $m['slug']
									];
								} else {
									$step[ 'themeData' ] = [
										'resource' => 'wordpress.org/themes',
										'slug'     => $m['slug']
									];
								}
							}
						}
					}

					// Upgrade from pluginZipFile to pluginData by slug where possible.
					if ( isset( $step['pluginZipFile']['slug'] ) ) {
						$step['pluginData'] = array(
							'resource' => 'wordpress.org/plugins',
							'slug'     => $step['pluginZipFile']['slug'],
						);
						unset( $step['pluginZipFile'] );
					}

					// Check if this is a "install this plugin" step.
					if (
						'installPlugin' === $step['step'] &&
						isset( $step['pluginData']['slug'] ) &&
						$plugin_slug === $step['pluginData']['slug']
					) {
						$has_self_install_step = true;

						// Ensure the step activates the plugin.
						$step['options'] ??= [];
						$step['options']['activate'] = true;
					}
				}
			}

			// Akismet is a special case because the plugin is bundled with WordPress.
			if ( ! $has_self_install_step && 'akismet' !== $plugin_slug ) {
				$decoded_file['steps'][] = array(
					'step' => 'installPlugin',
					'pluginData' => array(
						'resource' => 'wordpress.org/plugins',
						'slug'     => $plugin_slug,
					),
					'options' => array(
						'activate' => true,
					)
				);
			}

			$contents = json_encode( $decoded_file ); // Re-encode to minimize whitespace
		}

		return $contents;
	}
}

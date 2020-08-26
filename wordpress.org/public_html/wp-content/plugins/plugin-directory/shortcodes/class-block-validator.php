<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;

use WordPressdotorg\Plugin_Directory\CLI\Block_Plugin_Checker;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Import;

class Block_Validator {

	/**
	 * Displays a form to validate block plugins.
	 */
	public static function display() {
		ob_start();
		$plugin_url = $_REQUEST['plugin_url'] ?? '';

		if ( is_user_logged_in() ) :
			?>

		<div class="wrap block-validator">
			<form method="post" action="." class="block-validator__plugin-form">
				<label for="plugin_url"><?php _e( 'Plugin repo URL', 'wporg-plugins' ); ?></label>
				<div class="block-validator__plugin-input-container">
					<input type="text" class="block-validator__plugin-input" id="plugin_url" name="plugin_url" placeholder="https://plugins.svn.wordpress.org/" value="<?php echo esc_attr( $plugin_url ); ?>" />
					<input type="submit" class="button button-secondary block-validator__plugin-submit" value="<?php esc_attr_e( 'Check Plugin!', 'wporg-plugins' ); ?>" />
					<?php wp_nonce_field( 'validate-block-plugin', 'block-nonce' ); ?>
				</div>
			</form>


			<details>
			<summary>Or upload a plugin ZIP file.</summary>
			<form id="upload_form" class="plugin-upload-form" enctype="multipart/form-data" method="POST" action="">
				<?php wp_nonce_field( 'wporg-block-upload', 'block-upload-nonce' ); ?>
				<input type="hidden" name="action" value="upload"/>

				<input type="file" id="zip_file" class="plugin-file" name="zip_file" size="25" accept=".zip"/>
				<label class="button button-secondary" for="zip_file"><?php _e( 'Select File', 'wporg-plugins' ); ?></label>

				<input id="upload_button" name="block-directory-upload" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Upload', 'wporg-plugins' ); ?>"/>

				<p>
					<small>
						<?php
						printf(
							/* translators: Maximum allowed file size. */
							esc_html__( 'Maximum allowed file size: %s', 'wporg-plugins' ),
							esc_html( size_format( wp_max_upload_size() ) )
						);
						?>
					</small>
					</p>
				</form>

				<?php
				$upload_script = '
					( function ( $ ) {
						var $label = $( "label.button" ),
							labelText = $label.text();
						$( "#zip_file" )
							.on( "change", function( event ) {
								var fileName = event.target.value.split( "\\\\" ).pop();
								fileName ? $label.text( fileName ) : $label.text( labelText );
							} )
							.on( "focus", function() { $label.addClass( "focus" ); } )
							.on( "blur", function() { $label.removeClass( "focus" ); } );
					} ( window.jQuery ) );';

				if ( ! wp_script_is( 'jquery', 'done' ) ) {
					wp_enqueue_script( 'jquery' );
					wp_add_inline_script( 'jquery-migrate', $upload_script );
				} else {
					printf( '<script>%s</script>', $upload_script );
				}
			?>
			</details>
			<?php

			if ( $_POST && ! empty( $_POST['plugin_url'] ) && wp_verify_nonce( $_POST['block-nonce'], 'validate-block-plugin' ) ) {
				self::validate_block( $_POST['plugin_url'] );
			} elseif ( $_POST && ! empty( $_POST['block-directory-upload'] ) ) {
				self::handle_file_upload();
			} elseif ( $_POST && ! empty( $_POST['block-directory-edit'] ) ) {
				self::handle_edit_form();
			} elseif ( $_POST && ! empty( $_POST['block-directory-test'] ) ) {
				self::handle_test();
			}
			?>
		</div>
		<?php else : ?>
		<div class="wrap block-validator">
			<p><?php _e( 'Please log in to use the block plugin checker.', 'wporg-plugins' ); ?></p>
		</div>
		<?php endif;
		return ob_get_clean();
	}

	protected static function handle_file_upload() {
		if (
			! empty( $_POST['block-upload-nonce'] )
			&& wp_verify_nonce( $_POST['block-upload-nonce'], 'wporg-block-upload' )
			&& 'upload' === $_POST['action']
			) {
			if ( UPLOAD_ERR_OK === $_FILES['zip_file']['error'] ) {
				self::validate_block_from_zip( $_FILES['zip_file']['tmp_name'] );
			} else {
				$message = __( 'Error in file upload.', 'wporg-plugins' );
			}

			if ( ! empty( $message ) ) {
				echo "<div class='notice notice-warning notice-alt'><p>{$message}</p></div>\n";
			}
		}

	}

	protected static function handle_edit_form() {
		$post = get_post( intval( $_POST['plugin-id'] ) );
		if ( $post && wp_verify_nonce( $_POST['block-directory-nonce'], 'block-directory-edit-' . $post->ID ) ) {
			if ( current_user_can( 'edit_post', $post->ID ) || current_user_can( 'plugin_admin_edit', $post->ID ) ) {
				$terms = wp_list_pluck( get_the_terms( $post->ID, 'plugin_section' ), 'slug' );
				if ( 'add' === $_POST['block-directory-edit'] ) {
					$terms[] = 'block';
				} elseif ( 'remove' === $_POST['block-directory-edit'] ) {
					$terms = array_diff( $terms, array( 'block' ) );
				}
				$result = wp_set_object_terms( $post->ID, $terms, 'plugin_section' );
				if ( !is_wp_error( $result ) && !empty( $result ) ) {
					if ( 'add' === $_POST['block-directory-edit'] ) {
						Tools::audit_log( 'Plugin added to block directory.', $post->ID );
						self::maybe_send_email_plugin_added( $post );
						Plugin_Import::queue( $post->post_name, array( 'tags_touched' => array( $post->stable_tag ) ) );
						echo '<div class="notice notice-success notice-alt"><p>' . __( 'Plugin added to the block directory.', 'wporg-plugins' ) . '</p></div>';
					} elseif ( 'remove' === $_POST['block-directory-edit'] ) {
						Tools::audit_log( 'Plugin removed from block directory.', $post->ID );
						echo '<div class="notice notice-info notice-alt"><p>' . __( 'Plugin removed from the block directory.', 'wporg-plugins' ) . '</p></div>';
					}
				}
			}

			return self::validate_block( $post->post_name );
		}
	}

	protected static function handle_test() {
		$post = get_post( intval( $_POST['plugin-id'] ) );
		if ( $post && 'test' === $_POST['block-directory-test'] && wp_verify_nonce( $_POST['block-directory-test-nonce'], 'block-directory-test-' . $post->ID ) ) {
			if ( wp_cache_get( "plugin-e2e-test-{$post->ID}", 'plugin-test' ) ) {
				echo '<div class="notice notice-warning notice-alt"><p>' . __( 'Test already in progress.', 'wporg-plugins' ) . '</p></div>';
			} elseif ( current_user_can( 'edit_post', $post->ID ) || current_user_can( 'plugin_admin_edit', $post->ID ) ) {
				$result = Tools\Block_e2e::run( $post );
				if ( $result ) {
					echo '<div class="notice notice-success notice-alt"><p>' . __( 'Test run started. Please check back in 10 minutes.', 'wporg-plugins' ) . '</p></div>';
					wp_cache_add( "plugin-e2e-test-{$post->ID}", '1', 'plugin-test', 10 * MINUTE_IN_SECONDS );
				} else {
					echo '<div class="notice notice-error notice-alt"><p>' . __( 'Unable to start a test run.', 'wporg-plugins' ) . '</p></div>';
				}
			}
		}

		return self::validate_block( $post->post_name );
	}

	protected static function plugin_is_in_block_directory( $slug ) {
		$plugin = Plugin_Directory::get_plugin_post( $slug );

		return (
			$plugin &&
			$plugin->post_name === $slug &&
			has_term( 'block', 'plugin_section', $plugin )
		);
	}

	/**
	 * Render the form to add/remove the block from the directory.
	 *
	 * @param WP_Post $plugin     The post object representing this plugin.
	 * @param bool    $has_errors Whether this plugin has errors preventing it from inclusion in the directory.
	 */
	protected static function render_plugin_actions( $plugin, $has_errors ) {
		echo '<form method="post">';
		echo '<input type="hidden" name="plugin-id" value="' . esc_attr( $plugin->ID ) . '" />';

		echo '<p>';
		if ( self::plugin_is_in_block_directory( $plugin->post_name ) ) {
			echo wp_nonce_field( 'block-directory-edit-' . $plugin->ID, 'block-directory-nonce' );
			// translators: %s plugin title.
			echo '<button class="button button-secondary button-large" type="submit" name="block-directory-edit" value="remove">' . sprintf( __( 'Remove %s from Block Directory', 'wporg-plugins' ), $plugin->post_title ) . '</button>';
		} else if ( ! $has_errors ) {
			echo wp_nonce_field( 'block-directory-edit-' . $plugin->ID, 'block-directory-nonce' );
			// translators: %s plugin title.
			echo '<button class="button button-primary button-large" type="submit" name="block-directory-edit" value="add">' . sprintf( __( 'Add %s to Block Directory', 'wporg-plugins' ), $plugin->post_title ) . '</button>';
		}

		if ( current_user_can( 'edit_post', $post->ID ) ) {
			echo wp_nonce_field( 'block-directory-test-' . $plugin->ID, 'block-directory-test-nonce' );
			// translators: %s plugin title.
			$disabled = ( wp_cache_get( "plugin-e2e-test-{$plugin->ID}", 'plugin-test' ) ? ' disabled="disabled"' : '' );
			echo '<button class="button button-secondary button-large" type="submit" name="block-directory-test" value="test"' . $disabled . '>' . sprintf( __( 'Test %s', 'wporg-plugins' ), $plugin->post_title ) . '</button>';
		}

		echo '</p>';
		echo '</form>';
	}

	/**
	 * Validates a block plugin to check that blocks are correctly registered and detectable.
	 *
	 * @param string $plugin_url The URL of a Subversion or GitHub repository.
	 */
	protected static function validate_block( $plugin_url ) {
		$checker = new Block_Plugin_Checker();
		$results = $checker->run_check_plugin_repo( $plugin_url );
		self::display_results( $checker );
	}

	/**
	 * Validates a block plugin to check that blocks are correctly registered and detectable.
	 *
	 * @param string $plugin_url The URL of a Subversion or GitHub repository.
	 */
	protected static function validate_block_from_zip( $zip_file ) {
		$path = Tools\Filesystem::unzip( $zip_file );
		$checker = new Block_Plugin_Checker();
		$results = $checker->run_check_plugin_files( $path );
		self::display_results( $checker );
	}

	/**
	 * Display the results of a Block_Plugin_Checker run.
	 *
	 * @param array $results The Block_Plugin_Checker output.
	 */
	protected static function display_results( $checker ) {

		echo '<h2>' . __( 'Results', 'wporg-plugins' ) . '</h2>';

		$results = $checker->get_results();

		if ( $checker->repo_url && $checker->repo_revision ) {
			echo '<p>';
			printf(
				// translators: %1$s is the repo URL, %2$s is a version number.
				__( 'Results for %1$s revision %2$s', 'wporg-plugins' ),
				'<code>' . esc_url( $checker->repo_url ) . '</code>',
				esc_html( $checker->repo_revision )
			);
			echo '</p>';
		}

		$results_by_type = array();
		$block_json_issues = array();
		foreach ( $results as $item ) {
			if ( 'info' !== $item->type && 'check_block_json_is_valid' === $item->check_name ) {
				$block_json_issues[] = $item;
			} else {
				$results_by_type[ $item->type ][] = $item;
			}
		}

		$has_errors = ! empty( $results_by_type['error'] );
		$has_warnings = ! empty( $results_by_type['warning'] ) || ! empty( $block_json_issues );

		if ( $has_errors ) :
			?>
			<div class="notice notice-error notice-alt">
				<p><?php _e( 'Some problems were found. They need to be addressed for your plugin to be included in the Block Directory.', 'wporg-plugins' ); ?></p>
			</div>
		<?php elseif ( $checker->slug ) : ?>
			<?php if ( self::plugin_is_in_block_directory( $checker->slug ) ) : ?>
				<div class="notice notice-info notice-alt">
					<p><?php _e( 'This plugin is already in the Block Directory.', 'wporg-plugins' ); ?></p>
				</div>
			<?php elseif ( $has_warnings ) : ?>
				<div class="notice notice-info notice-alt">
					<p><?php _e( 'You can add your plugin to the Block Directory.', 'wporg-plugins' ); ?></p>
				</div>
			<?php else : ?>
				<div class="notice notice-success notice-alt">
					<p><?php _e( 'No issues were found. You can add your plugin to the Block Directory.', 'wporg-plugins' ); ?></p>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<div class="notice notice-info notice-alt">
				<p>
					<?php
					printf(
						__( 'Your plugin passed the checks, but only plugins hosted on WordPress.org can be added to the Block Directory. <a href="%s">Upload your plugin to the WordPress.org repo,</a> then come back here to add it to the Block Directory.', 'wporg-plugins' ),
						esc_url( home_url( 'developers' ) )
					);
					?>
				</p>
			</div>
			<?php
		endif;

		if ( $checker->slug ) {
			$plugin = Plugin_Directory::get_plugin_post( $checker->slug );
			if ( current_user_can( 'edit_post', $plugin->ID ) ) {
				// Plugin reviewers etc
				echo '<h3>' . __( 'Plugin Review Tools', 'wporg-plugins' ) . '</h3>';

				$e2e_result = get_post_meta( $plugin->ID, 'e2e_success', true );
				if ( !empty( $e2e_result ) ) {
					echo '<h4>' . __( 'Test Results', 'wporg-plugins' ) . '</h4>';
					if ( 'true' === $e2e_result ) {
						echo "<div class='notice notice-info notice-alt'><p>\n";
						echo __( 'Test passed.', 'wporg-plugins' );
						echo "</p></div>\n";
					} else {
						echo "<div class='notice notice-error notice-alt'><p>\n";
						echo sprintf( esc_html__( 'Test failed: %s', wporg-plugins ), '<code>' . esc_html( get_post_meta( $plugin->ID, 'e2e_error', true ) ) . '</code>' );
						echo "</p></div>\n";
					}
				}

				if ( $image = get_post_meta( $plugin->ID, 'e2e_screenshotBlock', true ) ) {
					echo '<div class="test-screenshot"><figure>';
					echo '<img src="data:image/png;base64, ' . esc_attr( $image ) . '" />';
					echo '<figcaption>Screenshot from last test run</figcaption>';
					echo '</figure></div>';
				}

				echo '<ul>';
				echo '<li><a href="' . get_edit_post_link( $plugin->ID ) . '">' . __( 'Edit plugin', 'wporg-plugins' ) . '</a></li>';
				echo '<li><a href="' . esc_url( 'https://plugins.trac.wordpress.org/browser/' . $checker->slug . '/trunk' ) . '">' . __( 'Trac browser', 'wporg-plugins' ) . '</a></li>';
				echo '</ul>';

				self::render_plugin_actions( $plugin, $has_errors );

			} elseif ( current_user_can( 'plugin_admin_edit', $plugin->ID ) ) {
				// Plugin committers
				echo '<h3>' . __( 'Committer Tools', 'wporg-plugins' ) . '</h3>';
				echo '<ul>';
				echo '<li><a href="' . esc_url( 'https://plugins.trac.wordpress.org/browser/' . $checker->slug . '/trunk' ) . '">' . __( 'Browse code on trac', 'wporg-plugins' ) . '</a></li>';
				echo '</ul>';

				self::render_plugin_actions( $plugin, $has_errors );
			}
		}

		$output = '';

		$error_types = array(
			'error'   => array(
				'title' => __( 'Fatal Errors', 'wporg-plugins' ),
				'description' => __( 'These issues must be fixed before this block can appear in the block directory.', 'wporg-plugis' ),
			),
			'warning' => array(
				'title' => __( 'Recommendations', 'wporg-plugins' ),
				'description' => __( 'These are suggestions to improve your block. While they are not required for your block plugin to be added to the Block Directory, addressing them will help people discover and use your block.', 'wporg-plugins' ),
			),
			'info'    => array(
				'title' => __( 'Notes', 'wporg-plugins' ),
				'description' => false,
			),
		);
		foreach ( $error_types as $type => $labels ) {
			if ( empty( $results_by_type[ $type ] ) ) {
				// Print out the warning wrapper if we have block.json issues.
				if ( 'warning' !== $type || empty( $block_json_issues ) ) {
					continue;
				}
			}

			$output .= "<h3>{$labels['title']}</h3>\n";
			if ( $labels['description'] ) {
				$output .= "<p class='small'>{$labels['description']}</p>\n";
			}
			$output .= "<div class='notice notice-{$type} notice-alt'>\n";
			foreach ( (array) $results_by_type[ $type ] as $item ) {
				// Only get details if this is a warning or error.
				$details = ( 'info' === $type ) ? false : self::get_detailed_help( $item->check_name, $item );
				if ( $details ) {
					$details = '<p>' . implode( '</p><p>', (array) $details ) . '</p>';
					$output .= "<details class='{$item->check_name}'><summary>{$item->message}</summary>{$details}</details>";
				} else {
					$output .= "<p>{$item->message}</p>";
				}
			}
			// Collapse block.json warnings into one details at the end of warnings list.
			if ( 'warning' === $type && ! empty( $block_json_issues ) ) {
				$messages = wp_list_pluck( $block_json_issues, 'message' );
				$details = '<p>' . implode( '</p><p>', (array) $messages ) . '</p>';
				$output .= sprintf(
					'<details class="check_block_json_is_valid"><summary>%1$s</summary>%2$s</details>',
					__( 'Issues found in block.json file.', 'wporg-plugins' ),
					$details
				);
			}
			$output .= "</div>\n";
		}

		if ( empty( $output ) ) {
			$output .= '<div class="notice notice-success notice-alt">';
			$output .= '<p>' . __( 'Congratulations! No errors found.', 'wporg-plugins' ) . '</p>';
			$output .= '</div>';
		}

		echo $output;
	}

	/**
	 * Get a more detailed help message for a given check.
	 *
	 * @param string $method The name of the check method.
	 * @param array  $result The full result data.
	 *
	 * @return string|array More details for a given block issue. Array of strings if there should be a linebreak.
	 */
	public static function get_detailed_help( $method, $result ) {
		switch ( $method ) {
			case 'check_readme_exists':
				return [
					__( 'All plugins need a readme.txt file.', 'wporg-plugins' ),
					sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( home_url( 'developers/#readme' ) ),
						__( 'Learn more about readmes.', 'wporg-plugins' )
					),
				];
			case 'check_license':
				return [
					__( 'Plugins should include a GPL-compatible license in either readme.txt or the plugin headers.', 'wporg-plugins' ),
					sprintf( '<a href="https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#1-plugins-must-be-compatible-with-the-gnu-general-public-license">%s</a>', __( 'Learn more about licenses.', 'wporg-plugins' ) ),
				];
			case 'check_plugin_headers':
				return sprintf( '<a href="https://developer.wordpress.org/plugins/plugin-basics/header-requirements/">%s</a>', __( 'Learn more about plugin headers.', 'wporg-plugins' ) );
			case 'check_block_tag':
				return __( 'The readme.txt file must contain the tag "block" (singular) for this to be added to the block directory.', 'wporg-plugins' );
			case 'check_for_duplicate_block_name':
				$details = [
					__( "Block names must be unique, otherwise it can cause problems when using the block. It is recommended to use your plugin's name as the namespace.", 'wporg-plugins' ),
				];
				if ( 'warning' === $result->type ) {
					$details[] = '<em>' . __( 'If this is a different version of your plugin, you can ignore this warning.', 'wporg-plugins' ) . '</em>';
				}
				return $details;
			case 'check_for_blocks':
				return [
					__( 'To work in the Block Directory, a plugin must register a block. Generally one per plugin (multiple blocks may be permitted if those blocks are interdependent, such as a list block that contains list item blocks).', 'wporg-plugins' ),
					__( 'If your plugin doesn’t register a block, it probably belongs in the main Plugin Directory rather than the Block Directory.', 'wporg-plugins' ),
					sprintf( '<a href="https://developer.wordpress.org/block-editor/tutorials/create-block/">%s</a>', __( 'Learn how to create a block.' ) ),
				];
			case 'check_for_block_json':
				return __( 'Your plugin should contain at least one <code>block.json</code> file. This file contains metadata describing the block and its JavaScript and CSS assets. Make sure you include at least one <code>script</code> or <code>editorScript</code> item.', 'wporg-plugins' );
			case 'check_for_block_script_files':
				return [
					__( 'The value of <code>script</code>, <code>style</code>, <code>editorScript</code>, <code>editorStyle</code> must be a valid file path. This value was detected, but there is no file at this location in your plugin.', 'wporg-plugins' ),
					__( 'Unlike regular blocks, plugins in the block directory cannot use a script handle for these values.', 'wporg-plugins' ),
				];
			case 'check_for_register_block_type':
				return __( 'At least one of your JavaScript files must explicitly call registerBlockType(). Without that call, your block will not work in the editor.', 'wporg-plugins' );
			case 'check_block_json_is_valid_json':
				return __( 'This block.json file is invalid. The Block Directory needs to be able to read this file.', 'wporg-plugins' );
			case 'check_php_size':
				return __( 'Block plugins should keep the PHP code to a minimum. If you need a lot of PHP code, your plugin probably belongs in the main Plugin Directory rather than the Block Directory.', 'wporg-plugins' );
			case 'check_for_standard_block_name':
				return [
					__( 'Block names must contain a namespace prefix, include only lowercase alphanumeric characters or dashes, and start with a letter. The namespace should be unique to your block plugin, make sure to change any defaults from block templates like "create-block/" or "cgb/".', 'wporg-plugins' ),
					__( 'Example: <code>my-plugin/my-custom-block</code>', 'wporg-plugins' ),
				];
			case 'check_for_single_parent':
				return __( 'Block plugins should contain a single main block, which is added to the editor when the block is installed. If multiple blocks are used (ex: list items in a list block), the list items should set the `parent` property in their `block.json` file.', 'wporg-plugins' );
			case 'check_php_function_calls':
				return __( 'Block plugins should contain minimal PHP with no UI outside the editor. JavaScript should be used instead of PHP where possible.', 'wporg-plugins' );
			case 'check_for_translation_function':
				return sprintf(
					// translators: %s is the link to the internationalization docs.
					__( 'Block plugins should use <code>wp_set_script_translations</code> to load translations for each script file. <a href="%s">Learn more about internationalization.</a>', 'wporg-plugins' ),
					'https://developer.wordpress.org/block-editor/developers/internationalization/'
				);
			case 'check_total_size':
				return __( 'Larger plugins will take longer to install. This is more noticeable in the Block Directory, where the user expects blocks to be added immediately. Try reducing your file size by optimizing images & SVGs, only including the assets you need (images, fonts, etc), and using core-provided JavaScript libraries.', 'wporg-plugins' );
			case 'check_for_multiple_namespaces':
				return __( 'Block plugins should contain a single main block. Any children blocks should use the same namespace prefix as the main block. Please ensure there are no extraneous blocks included by mistake.', 'wporg-plugins' );
			case 'check_for_unique_namespace':
				return __( 'Blocks should use a namespace that is unique to the plugin or its author. It appears this namespace is already in use by another author. If that&#8217;s not you then please ensure you choose a unique namespace for your blocks. The plugin slug is a good choice.' );
			// This is a special case, since multiple values may be collapsed.
			case 'check_block_json_is_valid':
				return false;
		}
	}

	/**
	 * Sends an email confirmation to the plugin's author the first time a plugin is added to the directory.
	 */
	protected static function maybe_send_email_plugin_added( $post ) {

		$plugin_author = get_user_by( 'id', $post->post_author );
		if ( empty( $plugin_author ) )
			return false;

		// Only send the email the first time it's added.
		if ( !add_post_meta( $post->ID, 'added_to_block_directory', time(), true ) )
			return false;


		/* translators: %s: plugin name */
		$email_subject = sprintf(
			__( '[WordPress Plugin Directory] Added to Block Directory - %s', 'wporg-plugins' ),
			$post->post_name
		);

		/*
			Please leave the blank lines in place.
		*/
		$email_content = sprintf(
			// translators: 1: plugin name, 2: plugin slug.
			__(
'This email is to let you know that your plugin %1$s has been added to the Block Directory here: https://wordpress.org/plugins/browse/block/.

We\'re still working on improving Block Directory search and automated detection of blocks, so don\'t be alarmed if your block isn\'t immediately visible there. We\'ve built a new tool to help developers identify problems and potential improvements to block plugins, which you\'ll find here: https://wordpress.org/plugins/developers/block-plugin-validator/.

In case you missed it, the Block Directory is a new feature coming to WordPress 5.5 in August. By having your plugin automatically added here, WordPress users will be able to discover your block plugin and install it directly from the editor when searching for blocks. If you have any feedback (bug, enhancement, etc) about this new feature, please open an issue here: https://github.com/wordpress/gutenberg/.

If you would like your plugin removed from the Block Directory, you can do so here: https://wordpress.org/plugins/developers/block-plugin-validator/?plugin_url=%2$s

Otherwise, you\'re all set!

--
The WordPress Plugin Directory Team
https://make.wordpress.org/plugins', 'wporg-plugins'
			),
			$post->post_title,
			$post->post_name
		);

		$user_email = $plugin_author->user_email;

		return wp_mail( $user_email, $email_subject, $email_content, 'From: plugins@wordpress.org' );
	}
}

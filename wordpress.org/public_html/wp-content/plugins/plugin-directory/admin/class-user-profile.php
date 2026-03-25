<?php

namespace WordPressdotorg\Plugin_Directory\Admin;

use WordPressdotorg\Plugin_Directory\API\Scoped_API_Key;

/**
 * All functionality related to the User Profile Scoped API Keys.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin
 */
class User_Profile {

    /**
     * Fetch the instance of the User_Profile class.
     */
    public static function instance() {
        static $instance = null;

        return ! is_null( $instance ) ? $instance : $instance = new self();
    }

    /**
     * Constructor.
     */
    private function __construct() {
        add_action( 'show_user_profile', [ $this, 'render_scoped_api_keys' ] );
        add_action( 'edit_user_profile', [ $this, 'render_scoped_api_keys' ] );

        add_action( 'personal_options_update', [ $this, 'save_scoped_api_keys' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_scoped_api_keys' ] );
    }

    /**
     * Render the Scoped API Keys section on the user profile page.
     *
     * @param \WP_User $user The current user object.
     */
    public function render_scoped_api_keys( $user ) {
        if ( ! current_user_can( 'edit_user', $user->ID ) ) {
            return;
        }

        $keys = get_user_meta( $user->ID, Scoped_API_Key::META_KEY, true ) ?: [];

        // Display the raw key if just generated.
        if ( ! empty( $_GET['wporg_plugin_scoped_key_generated'] ) ) {
            $raw_key = get_transient( 'wporg_plugin_scoped_key_' . $user->ID );
            if ( $raw_key ) {
                delete_transient( 'wporg_plugin_scoped_key_' . $user->ID );
                printf(
                        '<div class="notice notice-success is-dismissible"><p>%s <code>%s</code></p><p><small>%s</small></p></div>',
                        esc_html__( 'New Scoped API Key generated:', 'wporg-plugins' ),
                        esc_html( $raw_key ),
                        esc_html__( 'Make sure to copy this key now. You won\'t be able to see it again!', 'wporg-plugins' )
                );
            }
        }

        ?>
        <div class="scoped-api-keys">
            <h2><?php _e( 'Plugin Scoped API Keys', 'wporg-plugins' ); ?></h2>
            <p><?php _e( 'These keys allow access to specific Plugin Directory API endpoints.', 'wporg-plugins' ); ?></p>

            <?php if ( $keys ) : ?>
                <table class="wp-list-table widefat fixed striped table-view-list">
                    <thead>
                    <tr>
                        <th><?php _e( 'Scope', 'wporg-plugins' ); ?></th>
                        <th><?php _e( 'Created', 'wporg-plugins' ); ?></th>
                        <th><?php _e( 'Last used', 'wporg-plugins' ); ?></th>
                        <th><?php _e( 'Last IP', 'wporg-plugins' ); ?></th>
                        <th><?php _e( 'Usage this week', 'wporg-plugins' ); ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $keys as $entry ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $entry['scope'] ); ?></code></td>
                            <td><?php echo esc_html( wp_date( get_option( 'date_format' ), $entry['created_at'] ) ); ?></td>
                            <td><?php echo esc_html( wp_date( get_option( 'date_format' ), $entry['last_used'] ) ); ?></td>
                            <td><?php echo esc_html( $entry['last_ip'] ); ?></td>
                            <td><?php echo intval( $entry['usage_count'] ); ?></td>
                            <td class="column-actions" style="text-align: right;">
                                <button type="submit" name="wporg_plugin_scoped_key_revoke"
                                        value="<?php echo esc_attr( $entry['hash'] ); ?>"
                                        class="button-link button-link-delete"
                                        onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to revoke this key?', 'wporg-plugins' ); ?>');"><?php _e( 'Revoke', 'wporg-plugins' ); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php _e( 'No scoped API keys found.', 'wporg-plugins' ); ?></p>
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="wporg_plugin_scoped_key_scope"><?php _e( 'Generate New Key', 'wporg-plugins' ); ?></label>
                    </th>
                    <td>
                        <select name="wporg_plugin_scoped_key_scope" id="wporg_plugin_scoped_key_scope">
                            <?php foreach ( Scoped_API_Key::SCOPES as $scope ) : ?>
                                <option value="<?php echo esc_attr( $scope ); ?>"><?php echo esc_html( $scope ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="submit" name="wporg_plugin_scoped_key_generate"
                               value="<?php esc_attr_e( 'Generate', 'wporg-plugins' ); ?>" class="button">
                        <?php wp_nonce_field( 'wporg_plugin_scoped_key_manage', 'wporg_plugin_scoped_key_nonce' ); ?>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Save/Handle the Scoped API Keys generation and revocation.
     *
     * @param int $user_id The user ID.
     */
    public function save_scoped_api_keys( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        if ( ! isset( $_POST['wporg_plugin_scoped_key_nonce'] ) || ! wp_verify_nonce( $_POST['wporg_plugin_scoped_key_nonce'], 'wporg_plugin_scoped_key_manage' ) ) {
            return;
        }

        if ( ! empty( $_POST['wporg_plugin_scoped_key_generate'] ) ) {
            $scope = $_POST['wporg_plugin_scoped_key_scope'] ?? '';
            if ( in_array( $scope, Scoped_API_Key::SCOPES, true ) ) {
                $raw_key = Scoped_API_Key::generate( $user_id, $scope );
                set_transient( 'wporg_plugin_scoped_key_' . $user_id, $raw_key, 30 );

                add_filter( 'wp_redirect', function ( $location ) {
                    return add_query_arg( 'wporg_plugin_scoped_key_generated', 1, $location );
                } );
            }
        } elseif ( ! empty( $_POST['wporg_plugin_scoped_key_revoke'] ) ) {
            $hash = $_POST['wporg_plugin_scoped_key_revoke'];
            $keys = get_user_meta( $user_id, Scoped_API_Key::META_KEY, true ) ?: [];

            $new_keys = array_filter( $keys, fn( $entry ) => $entry['hash'] !== $hash );

            if ( count( $new_keys ) !== count( $keys ) ) {
                update_user_meta( $user_id, Scoped_API_Key::META_KEY, array_values( $new_keys ) );
            }
        }
    }
}

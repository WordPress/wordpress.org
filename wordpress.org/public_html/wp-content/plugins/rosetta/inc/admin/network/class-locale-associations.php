<?php

namespace WordPressdotorg\Rosetta\Admin\Network;

use GP_Locales;
use WordPressdotorg\Rosetta\Admin\Admin_Page;
use WordPressdotorg\Rosetta\Admin\Admin_Page_View;
use WordPressdotorg\Rosetta\Database\Tables;
use WP_Error;

class Locale_Associations implements Admin_Page {

	/**
	 * Holds the slug of the page.
	 *
	 * @var string
	 */
	protected $page_slug = 'locale-associations';

	/**
	 * Holds the hook suffix of the page.
	 *
	 * @var string
	 */
	protected $page_hook;

	/**
	 * Holds the associated view.
	 *
	 * @var \WordPressdotorg\Rosetta\Admin\Admin_Page_View
	 */
	protected $view;

	/**
	 * Holds the admin URL of the page.
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * Constructor.
	 *
	 * @param \WordPressdotorg\Rosetta\Admin\Admin_Page_View $view The view.
	 */
	public function __construct( Admin_Page_View $view ) {
		$this->view = $view;
		$this->view->set_page( $this );
	}

	/**
	 * Registers a new admin page.
	 */
	public function register() {
		$this->page_hook = add_submenu_page(
			'sites.php',
			$this->view->get_title(),
			$this->view->get_title(),
			'manage_sites',
			$this->page_slug,
			[ $this->view, 'render' ]
		);

		add_action( 'load-' . $this->page_hook, [ $this, 'action' ] );
	}

	/**
	 * Handles actions like adding/deleting locale associations.
	 */
	public function action() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! isset( $_POST['action'] ) || ! in_array( $_POST['action'], [ 'add-association', 'delete-association' ], true ) ) {
			return;
		}

		$current_action = $_POST['action'];
		$result = null;

		switch ( $current_action ) {
			case 'add-association' :
				$result = $this->action_add_association();
				break;
			case 'delete-association' :
				$result = $this->action_delete_association();
				break;
			default :
				return;
		}

		$query_args = [
			'performed_action' => $current_action,
		];

		if ( is_wp_error( $result ) ) {
			$query_args['error'] = $result->get_error_code();
		} else {
			$query_args['updated'] = 'success';
		}

		wp_safe_redirect( add_query_arg( $query_args, $this->url() ) );
		exit;
	}

	/**
	 * Handles adding a new locale association.
	 *
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	private function action_add_association() {
		global $wpdb;

		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'add-association' ) ) {
			return new WP_Error( 'nonce_failure' );
		}

		if ( empty( $_POST['locale'] ) || empty( $_POST['locale'] ) ) {
			return new WP_Error( 'missing_data' );
		}

		$locale = sanitize_text_field( $_POST['locale'] );
		$subdomain = sanitize_text_field( $_POST['subdomain'] );

		if ( 0 !== strpos( $locale, 'test' ) ) {
			$locales = get_available_languages();
			if ( ! in_array( $locale, $locales, true ) ) {
				return new WP_Error( 'locale_does_not_exist' );
			}
		}

		$result = $wpdb->insert( Tables::LOCALES, [
			'locale'    => $locale,
			'subdomain' => $subdomain,
		] );

		if ( ! $result ) {
			return new WP_Error( 'insert_failure' );
		}

		$lang_id = $wpdb->insert_id;

		$site_ids = get_sites( [
			'domain'     => "{$subdomain}.wordpress.org",
			'network_id' => get_current_network_id(),
			'fields'     => 'ids',
		] );

		foreach ( $site_ids as $site_id ) {
			update_blog_details( $site_id, [ 'lang_id' => $lang_id ] );
		}

		$this->delete_cache();

		return true;
	}

	/**
	 * Handles deleting a locale association.
	 *
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public function action_delete_association() {
		global $wpdb;

		if ( empty( $_POST['id'] ) ) {
			return new WP_Error( 'missing_data' );
		}

		$id = (int) $_POST['id'];

		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'delete-association-' . $id ) ) {
			return new WP_Error( 'nonce_failure' );
		}

		// Reset 'lang_id' of associated sites.
		$site_ids = get_sites( [
			'lang_id'    => $id,
			'network_id' => get_current_network_id(),
			'fields'     => 'ids',
		] );

		foreach ( $site_ids as $site_id ) {
			update_blog_details( $site_id, [ 'lang_id' => '0' ] );
		}

		// Remove locale from global table.
		$result = $wpdb->delete( Tables::LOCALES, [
			'locale_id' => $id,
		], [ '%d' ] );

		if ( ! $result ) {
			return new WP_Error( 'delete_failure' );
		}

		$this->delete_cache();

		return true;
	}

	/**
	 * Deletes the cache for locale associations.
	 */
	private function delete_cache() {
		wp_cache_add_global_groups( [ 'locale-associations' ] );

		wp_cache_delete( 'subdomains', 'locale-associations' );
		wp_cache_delete( 'locale-list', 'locale-associations' );
		wp_cache_delete( 'local-sites', 'locale-associations' );
		wp_cache_delete( 'id-locale', 'locale-associations' );
	}

	/**
	 * Retrieves all associations.
	 *
	 * @return array List of associations.
	 */
	public function get_associations() {
		global $wpdb;

		$associations = $wpdb->get_results( 'SELECT * FROM ' . Tables::LOCALES . ' ORDER BY locale' );
		if ( ! is_array( $associations ) ) {
			$associations = [];
		}

		return $associations;
	}

	/**
	 * Retrieves all available WP locales which are not assigned to a site yet.
	 *
	 * @return array List of locales.
	 */
	public function get_available_wp_locales() {
		global $wpdb;

		$locales           = GP_Locales::locales();
		$wp_locales        = array_filter( wp_list_pluck( $locales, 'wp_locale' ) );
		$wp_locales_in_use = $wpdb->get_col( 'SELECT locale FROM ' . Tables::LOCALES );

		$wp_locales_in_use[] = 'en_US';

		return array_diff( $wp_locales, $wp_locales_in_use );
	}

	/**
	 * Gets the admin URL of the page.
	 *
	 * @return string Admin URL of the page.
	 */
	public function url() {
		if ( ! isset( $this->url ) ) {
			$url = add_query_arg( 'page', $this->page_slug, 'sites.php' );
			$this->url = network_admin_url( $url );
		}

		return $this->url;
	}
}

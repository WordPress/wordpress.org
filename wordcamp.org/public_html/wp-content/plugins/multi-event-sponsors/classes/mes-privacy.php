<?php

/**
 * Class MES_Privacy
 *
 * Hook into WP's privacy features for personal data export and erasure.
 */
class MES_Privacy {
	/**
	 * MES_Privacy constructor.
	 */
	public function __construct() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_personal_data_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_personal_data_erasers' ) );
	}

	/**
	 * Registers the personal data exporter callbacks.
	 *
	 * @param array $exporters
	 *
	 * @return array
	 */
	public function register_personal_data_exporters( $exporters ) {
		$exporters['multi-event-sponsors'] = array(
			'exporter_friendly_name' => __( 'WordCamp Multi-Event Sponsors', 'wordcamporg' ),
			'callback'               => array( $this, 'personal_data_exporter' ),
		);

		return $exporters;
	}

	/**
	 * Callback to find and export personal data.
	 *
	 * @param string $email_address
	 * @param int    $page
	 *
	 * @return array
	 */
	public function personal_data_exporter( $email_address, $page ) {
		$results = array(
			'data' => array(),
			'done' => true,
		);

		$query = $this->get_query( $email_address, $page );

		if ( is_wp_error( $query ) ) {
			return $results;
		}

		$props_to_export = array(
			'mes_company_name'    => __( 'Company Name', 'wordcamporg' ),
			'mes_first_name'      => __( 'First Name', 'wordcamporg' ),
			'mes_last_name'       => __( 'Last Name', 'wordcamporg' ),
			'mes_email_address'   => __( 'Email Address', 'wordcamporg' ),
			'mes_phone_number'    => __( 'Phone Number', 'wordcamporg' ),
		);

		$data_to_export = array();

		foreach ( $query->posts as $post ) {
			$post_data_to_export = array();

			foreach ( $props_to_export as $key => $label ) {
				$value = get_post_meta( $post->ID, $key, true );

				if ( ! empty( $value ) ) {
					$post_data_to_export[] = array(
						'name'  => $label,
						'value' => $value,
					);
				}
			}

			if ( ! empty( $post_data_to_export ) ) {
				$data_to_export[] = array(
					'group_id'    => 'multi-event-sponsors',
					'group_label' => __( 'WordCamp Multi-Event Sponsors', 'wordcamporg' ),
					'item_id'     => "mes-{$post->ID}",
					'data'        => $post_data_to_export,
				);
			}
		}

		$results['data'] = $data_to_export;
		$results['done'] = $query->max_num_pages <= $page;

		return $results;
	}

	/**
	 * Registers the personal data eraser callbacks. Currently just a stub.
	 *
	 * @param array $erasers
	 *
	 * @return array
	 */
	public function register_personal_data_erasers( $erasers ) {
		return $erasers;
	}

	/**
	 * Creates a query object containing results of a search for a particular email address.
	 *
	 * @param string $email_address
	 * @param int    $page
	 *
	 * @return WP_Query
	 */
	protected function get_query( $email_address, $page ) {
		$query_args = array(
			'posts_per_page' => 20,
			'paged'          => $page,
			'post_type'      => 'mes',
			'post_status'    => 'any',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'   => 'mes_email_address',
					'value' => $email_address,
				),
			),
		);

		return new WP_Query( $query_args );
	}
}

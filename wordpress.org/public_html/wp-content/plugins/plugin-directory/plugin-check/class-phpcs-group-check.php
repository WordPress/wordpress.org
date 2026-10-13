<?php
/**
 * Plugin Check: PHPCS group check.
 *
 * @package WordPressdotorg\Plugin_Directory
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Plugin_Directory\Plugin_Check;

use WordPress\Plugin_Check\Checker\Check_Result;
use WordPress\Plugin_Check\Checker\Checks\Abstract_PHP_CodeSniffer_Check;
use WordPress\Plugin_Check\Traits\Stable_Check;

/**
 * Runs the sniffs of several PHPCS-based checks that use the same standard in a single PHPCS pass.
 *
 * Every finding is handed to the check that owns its sniff, so check-specific message changes still apply.
 */
class PHPCS_Group_Check extends Abstract_PHP_CodeSniffer_Check {

	use Stable_Check;

	/**
	 * PHPCS arguments that checks in a group may use.
	 *
	 * @var string[]
	 */
	const GROUPABLE_ARGS = array( 'standard', 'extensions', 'sniffs', 'runtime-set' );

	/**
	 * The grouped checks, keyed by slug.
	 *
	 * @var Abstract_PHP_CodeSniffer_Check[]
	 */
	protected array $checks;

	/**
	 * The combined PHPCS arguments of the grouped checks.
	 *
	 * @var array
	 */
	protected array $args;

	/**
	 * Lowercase sniff codes mapped to the slugs of the checks that select them.
	 *
	 * @var array
	 */
	protected array $sniff_owners = array();

	/**
	 * Constructor.
	 *
	 * @param Abstract_PHP_CodeSniffer_Check[] $checks The checks to group, keyed by slug.
	 * @param array                            $args   The PHPCS arguments of each check, keyed by slug.
	 */
	public function __construct( array $checks, array $args ) {
		$this->checks = $checks;

		$first_args = reset( $args );
		$sniffs     = array();
		$runtime    = array();
		foreach ( $args as $slug => $check_args ) {
			foreach ( explode( ',', $check_args['sniffs'] ) as $sniff ) {
				$sniff = trim( $sniff );

				$sniffs[ strtolower( $sniff ) ]               = $sniff;
				$this->sniff_owners[ strtolower( $sniff ) ][] = $slug;
			}

			$runtime += $check_args['runtime-set'] ?? array();
		}

		$this->args = array(
			'standard'    => $first_args['standard'],
			'sniffs'      => implode( ',', $sniffs ),
			'runtime-set' => $runtime,
		);
		if ( isset( $first_args['extensions'] ) ) {
			$this->args['extensions'] = $first_args['extensions'];
		}
	}

	/**
	 * Groups the checks that can share a PHPCS run.
	 *
	 * Checks can share a run when they use the same standard and file extensions, select their sniffs, and set no
	 * conflicting runtime values. Checks that can't, are left out to run on their own.
	 *
	 * @param Abstract_PHP_CodeSniffer_Check[] $checks The checks, keyed by slug.
	 * @param Check_Result                     $result The check result, including the plugin context to check.
	 * @return PHPCS_Group_Check[] A group check for every group of two or more checks.
	 */
	public static function from_checks( array $checks, Check_Result $result ): array {
		$groups = array();
		foreach ( $checks as $slug => $check ) {
			$args = $check->get_args( $result );
			if ( empty( $args['standard'] ) || empty( $args['sniffs'] ) || array_diff_key( $args, array_flip( self::GROUPABLE_ARGS ) ) ) {
				continue;
			}

			$key     = $args['standard'] . '|' . ( $args['extensions'] ?? '' );
			$runtime = $args['runtime-set'] ?? array();

			$groups[ $key ] ??= array(
				'checks'  => array(),
				'args'    => array(),
				'runtime' => array(),
			);

			if ( array_diff_assoc( array_intersect_key( $runtime, $groups[ $key ]['runtime'] ), $groups[ $key ]['runtime'] ) ) {
				continue;
			}

			$groups[ $key ]['checks'][ $slug ] = $check;
			$groups[ $key ]['args'][ $slug ]   = $args;
			$groups[ $key ]['runtime']        += $runtime;
		}

		$group_checks = array();
		foreach ( $groups as $group ) {
			if ( count( $group['checks'] ) > 1 ) {
				$group_checks[] = new self( $group['checks'], $group['args'] );
			}
		}

		return $group_checks;
	}

	/**
	 * Gets the grouped checks.
	 *
	 * @return Abstract_PHP_CodeSniffer_Check[] The checks, keyed by slug.
	 */
	public function get_checks(): array {
		return $this->checks;
	}

	/**
	 * Returns the combined PHPCS arguments of the grouped checks.
	 *
	 * @param Check_Result $result The check result to amend, including the plugin context to check.
	 * @return array An associative array of PHPCS CLI arguments.
	 */
	protected function get_args( Check_Result $result ): array {
		return $this->args;
	}

	/**
	 * Hands a finding to the checks that select its sniff.
	 *
	 * Findings that no check selects, such as most PHPCS internal messages, go to the first check.
	 *
	 * @param Check_Result $result   The check result to amend, including the plugin context to check.
	 * @param bool         $error    Whether it is an error or notice.
	 * @param string       $message  Error message.
	 * @param string       $code     Error code.
	 * @param string       $file     Absolute path to the file where the issue was found.
	 * @param int          $line     The line on which the message occurred. Default is 0 (unknown line).
	 * @param int          $column   The column on which the message occurred. Default is 0 (unknown column).
	 * @param string       $docs     URL for further information about the message.
	 * @param int          $severity Severity level. Default is 5.
	 */
	protected function add_result_message_for_file( Check_Result $result, $error, $message, $code, $file, $line = 0, $column = 0, string $docs = '', $severity = 5 ): void {
		$sniff  = strtolower( implode( '.', array_slice( explode( '.', $code ), 0, 3 ) ) );
		$owners = $this->sniff_owners[ $sniff ] ?? array( array_key_first( $this->checks ) );

		foreach ( $owners as $slug ) {
			$this->checks[ $slug ]->add_result_message_for_file( $result, $error, $message, $code, $file, $line, $column, $docs, $severity );
		}
	}

	/**
	 * Gets the categories for the check.
	 *
	 * Group checks aren't registered, so they have none.
	 *
	 * @return array The categories for the check.
	 */
	public function get_categories(): array {
		return array();
	}

	/**
	 * Gets the description for the check.
	 *
	 * @return string Description.
	 */
	public function get_description(): string {
		return 'Runs the sniffs of several PHPCS-based checks in one pass.';
	}

	/**
	 * Gets the documentation URL for the check.
	 *
	 * @return string The documentation URL.
	 */
	public function get_documentation_url(): string {
		return '';
	}
}

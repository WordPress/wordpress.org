<?php
/**
 * Plugin Check: Batched PHPCS check.
 *
 * @package WordPressdotorg\Plugin_Directory
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Plugin_Directory\Plugin_Check;

use WordPress\Plugin_Check\Checker\Check_Result;
use WordPress\Plugin_Check\Checker\Checks\Abstract_PHP_CodeSniffer_Check;
use WordPress\Plugin_Check\Checker\CLI_Runner;
use WordPress\Plugin_Check\Checker\Preparation;
use WordPress\Plugin_Check\Checker\Static_Check;
use WordPress\Plugin_Check\Utilities\Plugin_Request_Utility;

/**
 * Stands in for a PHPCS-based check, so that the selected PHPCS checks share PHPCS runs.
 *
 * Each PHPCS check otherwise tokenizes the whole plugin on its own. The first of these checks to run, runs every
 * selected check that can share a run with others, see PHPCS_Group_Check. The rest run on their own as before.
 */
class Batched_Check implements Static_Check {

	/**
	 * The slug of the check this stands in for.
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * The check this stands in for.
	 *
	 * @var Abstract_PHP_CodeSniffer_Check
	 */
	protected $check;

	/**
	 * The check result the grouped checks last ran for.
	 *
	 * @var Check_Result|null
	 */
	private static $result;

	/**
	 * The slugs of the checks that ran in a group for that result.
	 *
	 * @var string[]
	 */
	private static $grouped = array();

	/**
	 * Constructor.
	 *
	 * @param string                         $slug  The slug of the check to stand in for.
	 * @param Abstract_PHP_CodeSniffer_Check $check The check to stand in for.
	 */
	public function __construct( string $slug, Abstract_PHP_CodeSniffer_Check $check ) {
		$this->slug  = $slug;
		$this->check = $check;
	}

	/**
	 * Replaces the PHPCS-based checks with batched ones.
	 *
	 * @param array $checks An array map of check slugs to Check instances.
	 * @return array The filtered checks.
	 */
	public static function wrap_checks( $checks ) {
		foreach ( $checks as $slug => $check ) {
			if ( $check instanceof Abstract_PHP_CodeSniffer_Check && ! $check instanceof Preparation ) {
				$checks[ $slug ] = new self( (string) $slug, $check );
			}
		}

		return $checks;
	}

	/**
	 * Amends the given result by running the check, or by running all groupable selected checks on first use.
	 *
	 * @param Check_Result $result The check result to amend, including the plugin context to check.
	 */
	public function run( Check_Result $result ) {
		if ( self::$result !== $result ) {
			self::$result  = $result;
			self::$grouped = self::run_groups( $result );
		}

		if ( ! in_array( $this->slug, self::$grouped, true ) ) {
			$this->check->run( $result );
		}
	}

	/**
	 * Runs the selected checks that can share PHPCS runs.
	 *
	 * Plugin Check only keeps track of its runner when it initializes early, otherwise an identically configured runner
	 * is used to find out which checks are selected.
	 *
	 * @param Check_Result $result The check result to amend, including the plugin context to check.
	 * @return string[] The slugs of the checks that ran.
	 */
	private static function run_groups( Check_Result $result ): array {
		$runner = Plugin_Request_Utility::get_runner();
		if ( ! $runner && array( 'plugin', 'check' ) === array_slice( \WP_CLI::get_runner()->arguments, 0, 2 ) ) {
			$runner = new CLI_Runner();
		}

		if ( ! $runner ) {
			return array();
		}

		$checks = array();
		foreach ( $runner->get_checks_to_run() as $slug => $check ) {
			if ( $check instanceof self ) {
				$checks[ $slug ] = $check->check;
			}
		}

		$grouped = array();
		foreach ( PHPCS_Group_Check::from_checks( $checks, $result ) as $group ) {
			$group->run( $result );
			array_push( $grouped, ...array_map( 'strval', array_keys( $group->get_checks() ) ) );
		}

		return $grouped;
	}

	/**
	 * Gets the stability of the check.
	 *
	 * @return string The stability of the check this stands in for.
	 */
	public function get_stability() {
		return $this->check->get_stability();
	}

	/**
	 * Gets the categories for the check.
	 *
	 * @return array The categories of the check this stands in for.
	 */
	public function get_categories() {
		return $this->check->get_categories();
	}

	/**
	 * Gets the description for the check.
	 *
	 * @return string The description of the check this stands in for.
	 */
	public function get_description(): string {
		return $this->check->get_description();
	}

	/**
	 * Gets the documentation URL for the check.
	 *
	 * @return string The documentation URL of the check this stands in for.
	 */
	public function get_documentation_url(): string {
		return $this->check->get_documentation_url();
	}
}

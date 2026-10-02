<?php
/**
 * Gives imported conversations HelpScout's custom field values, in the Custom Fields module.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Services;

use App\Mailbox;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Modules\WPOrgHelpScoutImport\Exceptions\ApiError;
use Modules\WPOrgHelpScoutImport\Exceptions\RateLimited;

/**
 * Creates a mailbox's custom fields as HelpScout has them, and sets conversations' values.
 *
 * The Custom Fields module is a paid one, so its tables are written directly, without its classes.
 */
final class CustomFields {

	/**
	 * Alias of the Custom Fields module.
	 *
	 * @var string
	 */
	public const MODULE = 'customfields';

	/**
	 * The module's field types, by HelpScout's, written without punctuation; others are single lines.
	 *
	 * @var int[]
	 */
	private const TYPES = array(
		'dropdown'   => 1,
		'singleline' => 2,
		'multiline'  => 3,
		'number'     => 4,
		'date'       => 5,
	);

	/**
	 * The module's dropdown type.
	 *
	 * @var int
	 */
	private const TYPE_DROPDOWN = 1;

	/**
	 * The module's multiselect dropdown type, which keeps option numbers like a dropdown.
	 *
	 * @var int
	 */
	private const TYPE_DROPDOWN_MULTISELECT = 8;

	/**
	 * The module's date type.
	 *
	 * @var int
	 */
	private const TYPE_DATE = 5;

	/**
	 * Longest field name the module keeps.
	 *
	 * @var int
	 */
	private const NAME_LENGTH = 75;

	/**
	 * HelpScout API client.
	 *
	 * @var HelpScout
	 */
	private $helpscout;

	/**
	 * HelpScout's fields, by HelpScout mailbox ID, then field ID.
	 *
	 * @var array[]
	 */
	private $definitions = array();

	/**
	 * Constructor.
	 *
	 * @param HelpScout $helpscout HelpScout API client.
	 */
	public function __construct( HelpScout $helpscout ) {
		$this->helpscout = $helpscout;
	}

	/**
	 * Whether the Custom Fields module is on, so there's somewhere to keep the values.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		try {
			return (bool) \App\Module::isActive( self::MODULE ) && Schema::hasTable( 'custom_fields' ) && Schema::hasTable( 'conversation_custom_field' );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * A conversation's values, by the mailbox's custom fields, which are created as HelpScout has them if need be.
	 *
	 * Read and created before the conversation's transaction: fields stay, even if the conversation fails.
	 *
	 * @param array   $source  HelpScout conversation.
	 * @param Mailbox $mailbox FreeScout mailbox it's imported into.
	 * @return string[] Values, by FreeScout custom field ID.
	 *
	 * @throws ApiError If HelpScout is unavailable, refuses the app, or is rate limited.
	 */
	public function values( array $source, Mailbox $mailbox ): array {
		$values = array();

		foreach ( (array) ( $source['customFields'] ?? array() ) as $value ) {
			$field_id = is_array( $value ) ? (int) ( $value['id'] ?? 0 ) : 0;
			$text     = trim( (string) ( $value['text'] ?? $value['value'] ?? '' ) );
			if ( ! $field_id || '' === $text ) {
				continue;
			}

			$definition = $this->definition( (int) ( $source['mailboxId'] ?? 0 ), $field_id ) ?? array(
				'id'   => $field_id,
				'name' => (string) ( $value['name'] ?? '' ),
				'type' => 'singleline',
			);

			$field = $this->field( $definition, $mailbox );
			if ( ! $field ) {
				continue;
			}

			$fs_value = self::value( $field, $text );
			if ( null !== $fs_value ) {
				$values[ (int) $field->id ] = $fs_value;
			}
		}

		return $values;
	}

	/**
	 * Sets a conversation's values; those it has already stay, so agents' changes are kept.
	 *
	 * @param int      $conversation_id FreeScout conversation ID.
	 * @param string[] $values          Values, by FreeScout custom field ID, from values().
	 * @return void
	 */
	public static function write( int $conversation_id, array $values ): void {
		foreach ( $values as $field_id => $value ) {
			$set = \DB::table( 'conversation_custom_field' )->where( 'conversation_id', $conversation_id )->where( 'custom_field_id', $field_id )->exists();
			if ( ! $set ) {
				\DB::table( 'conversation_custom_field' )->insert(
					array(
						'conversation_id' => $conversation_id,
						'custom_field_id' => (int) $field_id,
						'value'           => $value,
					)
				);
			}
		}
	}

	/**
	 * HelpScout's definition of a field, read once per mailbox.
	 *
	 * @param int $mailbox_id HelpScout mailbox ID.
	 * @param int $field_id   HelpScout field ID.
	 * @return array|null Null if HelpScout doesn't list it, or won't list the mailbox's fields.
	 *
	 * @throws ApiError If HelpScout is unavailable, refuses the app, or is rate limited.
	 */
	private function definition( int $mailbox_id, int $field_id ): ?array {
		if ( ! $mailbox_id ) {
			return null;
		}

		if ( ! isset( $this->definitions[ $mailbox_id ] ) ) {
			try {
				$fields = $this->helpscout->fields( $mailbox_id );
			} catch ( ApiError $e ) {
				if ( $e instanceof RateLimited || 401 === $e->status || 0 === $e->status || $e->status >= 500 ) {
					throw $e;
				}

				// Without the definitions, values are kept as text.
				$fields = array();
			}

			$this->definitions[ $mailbox_id ] = array_column( array_filter( $fields, 'is_array' ), null, 'id' );
		}

		return $this->definitions[ $mailbox_id ][ $field_id ] ?? null;
	}

	/**
	 * The mailbox's custom field for a HelpScout field: the one an import created, or one with its name, or a new one.
	 *
	 * @param array   $definition HelpScout's field.
	 * @param Mailbox $mailbox    FreeScout mailbox.
	 * @return object|null The custom field's row; null if it has no name to create it with.
	 */
	private function field( array $definition, Mailbox $mailbox ): ?object {
		$helpscout_id = (int) $definition['id'];
		$mapped       = \DB::table( 'wporghelpscoutimport_fields' )->where( 'helpscout_field_id', $helpscout_id )->where( 'mailbox_id', $mailbox->id )->value( 'custom_field_id' );
		$field        = $mapped ? \DB::table( 'custom_fields' )->where( 'id', $mapped )->first() : null;
		if ( $field ) {
			return $field;
		}

		$name = mb_substr( trim( (string) ( $definition['name'] ?? '' ) ), 0, self::NAME_LENGTH );
		if ( '' === $name ) {
			return null;
		}

		$field = \DB::table( 'custom_fields' )->where( 'mailbox_id', $mailbox->id )->where( 'name', $name )->first();
		if ( ! $field ) {
			$type    = self::TYPES[ preg_replace( '/[^a-z]/', '', strtolower( (string) ( $definition['type'] ?? '' ) ) ) ] ?? self::TYPES['singleline'];
			$options = (array) ( $definition['options'] ?? array() );
			usort(
				$options,
				static function ( $a, $b ): int {
					return (int) ( $a['order'] ?? 0 ) <=> (int) ( $b['order'] ?? 0 );
				}
			);

			$labels = array();
			foreach ( $options as $option ) {
				$label = trim( (string) ( $option['label'] ?? '' ) );
				if ( '' !== $label && ! in_array( $label, $labels, true ) ) {
					$labels[ count( $labels ) + 1 ] = $label;
				}
			}

			$now   = Carbon::now();
			$id    = \DB::table( 'custom_fields' )->insertGetId(
				array(
					'mailbox_id' => $mailbox->id,
					'name'       => $name,
					'type'       => $type,
					// Like the module keeps a dropdown's options: by their number, from 1.
					'options'    => self::TYPE_DROPDOWN === $type ? json_encode( $labels ? $labels : array( 1 => '' ) ) : null,
					'required'   => false,
					'sort_order' => (int) \DB::table( 'custom_fields' )->where( 'mailbox_id', $mailbox->id )->max( 'sort_order' ) + 1,
					'created_at' => $now,
					'updated_at' => $now,
				)
			);
			$field = \DB::table( 'custom_fields' )->where( 'id', $id )->first();
		}

		\DB::table( 'wporghelpscoutimport_fields' )->updateOrInsert(
			array(
				'helpscout_field_id' => $helpscout_id,
				'mailbox_id'         => $mailbox->id,
			),
			array(
				'custom_field_id' => $field->id,
				'updated_at'      => Carbon::now(),
			)
		);

		return $field;
	}

	/**
	 * A value as the module keeps it in a field: a dropdown's option number, a date as `Y-m-d`, or the text.
	 *
	 * A dropdown option the field doesn't have yet is added to it.
	 *
	 * @param object $field Custom field's row.
	 * @param string $text  HelpScout's value, as text.
	 * @return string|null Null if it can't be kept in the field, like a date that isn't one.
	 */
	private static function value( object $field, string $text ): ?string {
		if ( self::TYPE_DATE === (int) $field->type ) {
			try {
				return Carbon::parse( $text )->format( 'Y-m-d' );
			} catch ( \Throwable $e ) {
				return null;
			}
		}

		// A field FreeScout had by the name may be a multiselect dropdown: HelpScout's value is one of its options.
		if ( ! in_array( (int) $field->type, array( self::TYPE_DROPDOWN, self::TYPE_DROPDOWN_MULTISELECT ), true ) ) {
			return $text;
		}

		// Without the empty option the module gives a dropdown that has none.
		$options = array_filter(
			(array) json_decode( (string) $field->options, true ),
			static function ( $label ): bool {
				return '' !== (string) $label;
			}
		);
		$number  = array_search( $text, $options, true );
		if ( false === $number ) {
			$number             = $options ? max( array_map( 'intval', array_keys( $options ) ) ) + 1 : 1;
			$options[ $number ] = $text;
			$field->options     = json_encode( $options );
			\DB::table( 'custom_fields' )->where( 'id', $field->id )->update( array( 'options' => $field->options ) );
		}

		return (string) $number;
	}
}

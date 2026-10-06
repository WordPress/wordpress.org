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
	 * The module's number type.
	 *
	 * @var int
	 */
	private const TYPE_NUMBER = 4;

	/**
	 * The module's date type.
	 *
	 * @var int
	 */
	private const TYPE_DATE = 5;

	/**
	 * The module's tags type, which keeps a line's words split at commas.
	 *
	 * @var int
	 */
	private const TYPE_MULTISELECT = 7;

	/**
	 * The module's multiselect dropdown type, which keeps the options' labels, split at commas.
	 *
	 * @var int
	 */
	private const TYPE_DROPDOWN_MULTISELECT = 8;

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
	 * The mailboxes whose fields were created or found already, as `HelpScout mailbox ID:FreeScout mailbox ID`.
	 *
	 * @var bool[]
	 */
	private $mapped = array();

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
	 * Read and created before the conversation's transaction: fields stay, even if the conversation fails. The first
	 * time, all of the HelpScout mailbox's fields are created, in HelpScout's order, whether conversations have values
	 * for them or not; but only in the mailbox it's imported into. A conversation that stays in another mailbox, as
	 * agents worked on it there, only brings the fields it has values for.
	 *
	 * @param array   $source  HelpScout conversation.
	 * @param Mailbox $mailbox FreeScout mailbox its fields are in.
	 * @param bool    $map_all Whether that's the mailbox it's imported into, which gets all of HelpScout's fields.
	 * @return string[] Values, by FreeScout custom field ID.
	 *
	 * @throws ApiError If HelpScout is unavailable, refuses the app, or is rate limited.
	 */
	public function values( array $source, Mailbox $mailbox, bool $map_all = true ): array {
		$helpscout_mailbox_id = (int) ( $source['mailboxId'] ?? 0 );
		$values               = array();

		if ( $map_all ) {
			$this->map( $helpscout_mailbox_id, $mailbox );
		}

		foreach ( (array) ( $source['customFields'] ?? array() ) as $value ) {
			$field_id = is_array( $value ) ? (int) ( $value['id'] ?? 0 ) : 0;
			$text     = trim( (string) ( $value['text'] ?? $value['value'] ?? '' ) );
			if ( ! $field_id || '' === $text ) {
				continue;
			}

			$definition = $this->definitions( $helpscout_mailbox_id )[ $field_id ] ?? array(
				'id'   => $field_id,
				'name' => (string) ( $value['name'] ?? '' ),
				'type' => 'singleline',
			);

			$field = $this->field( $definition, $mailbox );
			if ( ! $field ) {
				continue;
			}

			$fs_value = self::value( $field, $text, self::mapping( $field_id, (int) $mailbox->id ) );
			if ( null !== $fs_value ) {
				$values[ (int) $field->id ] = $fs_value;
			}
		}

		return $values;
	}

	/**
	 * Brings a conversation's values up to date with HelpScout's, keeping agents' changes.
	 *
	 * A value is set where the conversation has none yet, and changed or cleared where it's still what the last import
	 * gave it.
	 *
	 * @param int           $conversation_id FreeScout conversation ID.
	 * @param string[]      $values          Values, by FreeScout custom field ID, from values().
	 * @param string[]|null $written         The values the last import gave it; null if none gave it values.
	 * @return void
	 */
	public static function write( int $conversation_id, array $values, ?array $written ): void {
		$written = (array) $written;
		$set     = \DB::table( 'conversation_custom_field' )
			->where( 'conversation_id', $conversation_id )
			->whereIn( 'custom_field_id', array_merge( array_keys( $values ), array_keys( $written ) ) )
			->pluck( 'value', 'custom_field_id' )
			->all();

		foreach ( $values as $field_id => $value ) {
			$last = array_key_exists( $field_id, $written ) ? (string) $written[ $field_id ] : null;

			if ( ! array_key_exists( $field_id, $set ) ) {
				// Removed in FreeScout since the last import, like with its dropdown option: it stays removed until HelpScout's changes.
				if ( $value !== $last ) {
					\DB::table( 'conversation_custom_field' )->insert(
						array(
							'conversation_id' => $conversation_id,
							'custom_field_id' => (int) $field_id,
							'value'           => $value,
						)
					);
				}
			} elseif ( $last === (string) $set[ $field_id ] && $value !== $last ) {
				\DB::table( 'conversation_custom_field' )->where( 'conversation_id', $conversation_id )->where( 'custom_field_id', $field_id )->update( array( 'value' => $value ) );
			}
		}

		// Cleared in HelpScout since.
		foreach ( $written as $field_id => $last ) {
			if ( ! array_key_exists( $field_id, $values ) && array_key_exists( $field_id, $set ) && (string) $last === (string) $set[ $field_id ] ) {
				\DB::table( 'conversation_custom_field' )->where( 'conversation_id', $conversation_id )->where( 'custom_field_id', $field_id )->delete();
			}
		}
	}

	/**
	 * Removes a conversation's values in a mailbox's fields, like once it's moved out of that mailbox.
	 *
	 * @param int $conversation_id FreeScout conversation ID.
	 * @param int $mailbox_id      FreeScout mailbox ID.
	 * @return void
	 */
	public static function forget( int $conversation_id, int $mailbox_id ): void {
		$fields = \DB::table( 'custom_fields' )->where( 'mailbox_id', $mailbox_id )->pluck( 'id' )->all();
		if ( $fields ) {
			\DB::table( 'conversation_custom_field' )->where( 'conversation_id', $conversation_id )->whereIn( 'custom_field_id', $fields )->delete();
		}
	}

	/**
	 * Creates or finds the mailbox's custom fields for all of the HelpScout mailbox's, in HelpScout's order, once.
	 *
	 * @param int     $helpscout_mailbox_id HelpScout mailbox ID.
	 * @param Mailbox $mailbox              FreeScout mailbox.
	 * @return void
	 *
	 * @throws ApiError If HelpScout is unavailable, refuses the app, or is rate limited.
	 */
	private function map( int $helpscout_mailbox_id, Mailbox $mailbox ): void {
		$key = $helpscout_mailbox_id . ':' . $mailbox->id;
		if ( ! $helpscout_mailbox_id || isset( $this->mapped[ $key ] ) ) {
			return;
		}

		$definitions = $this->definitions( $helpscout_mailbox_id );
		usort(
			$definitions,
			static function ( array $a, array $b ): int {
				return (int) ( $a['order'] ?? 0 ) <=> (int) ( $b['order'] ?? 0 );
			}
		);

		foreach ( $definitions as $definition ) {
			$this->field( $definition, $mailbox );
		}

		$this->mapped[ $key ] = true;
	}

	/**
	 * HelpScout's definitions of a mailbox's fields, read once per mailbox.
	 *
	 * @param int $mailbox_id HelpScout mailbox ID.
	 * @return array[] Fields, by their ID; none if HelpScout won't list them.
	 *
	 * @throws ApiError If HelpScout is unavailable, refuses the app, or is rate limited.
	 */
	private function definitions( int $mailbox_id ): array {
		if ( ! $mailbox_id ) {
			return array();
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

		return $this->definitions[ $mailbox_id ];
	}

	/**
	 * The mailbox's custom field for a HelpScout field: the one an import created, or one with its name, or a new one.
	 *
	 * A field with HelpScout's name gets the dropdown options of HelpScout's it doesn't have. A new one gets HelpScout's
	 * type, options, and whether it's required, and goes after the mailbox's fields.
	 *
	 * @param array   $definition HelpScout's field.
	 * @param Mailbox $mailbox    FreeScout mailbox.
	 * @return object|null The custom field's row; null if it has no name to create it with, or it was deleted since an
	 *                     import created or found it.
	 */
	private function field( array $definition, Mailbox $mailbox ): ?object {
		$helpscout_id = (int) $definition['id'];
		$mapping      = self::mapping( $helpscout_id, (int) $mailbox->id );
		if ( $mapping ) {
			// Deleted in FreeScout since: it stays deleted.
			return \DB::table( 'custom_fields' )->where( 'id', $mapping->custom_field_id )->first();
		}

		$name = mb_substr( trim( (string) ( $definition['name'] ?? '' ) ), 0, self::NAME_LENGTH );
		if ( '' === $name ) {
			return null;
		}

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
				$labels[] = $label;
			}
		}

		$field = \DB::table( 'custom_fields' )->where( 'mailbox_id', $mailbox->id )->where( 'name', $name )->first();
		if ( $field ) {
			foreach ( $labels as $label ) {
				self::option( $field, $label );
			}
		} else {
			$type = self::TYPES[ preg_replace( '/[^a-z]/', '', strtolower( (string) ( $definition['type'] ?? '' ) ) ) ] ?? self::TYPES['singleline'];
			$now  = Carbon::now();
			$id   = \DB::table( 'custom_fields' )->insertGetId(
				array(
					'mailbox_id' => $mailbox->id,
					'name'       => $name,
					'type'       => $type,
					// Like the module keeps a dropdown's options: by their number, from 1.
					'options'    => self::TYPE_DROPDOWN === $type ? json_encode( $labels ? array_combine( range( 1, count( $labels ) ), $labels ) : array( 1 => '' ) ) : null,
					'required'   => ! empty( $definition['required'] ),
					// Last, after the mailbox's own: map() creates them in HelpScout's order.
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
				'options'         => $labels ? json_encode( $labels, JSON_UNESCAPED_UNICODE ) : null,
				'updated_at'      => Carbon::now(),
			)
		);

		return $field;
	}

	/**
	 * Where an import created or found a HelpScout field in a mailbox.
	 *
	 * @param int $helpscout_id HelpScout field ID.
	 * @param int $mailbox_id   FreeScout mailbox ID.
	 * @return object|null Its row, with `custom_field_id`, and `options`, HelpScout's dropdown options as JSON.
	 */
	private static function mapping( int $helpscout_id, int $mailbox_id ): ?object {
		return \DB::table( 'wporghelpscoutimport_fields' )->where( 'helpscout_field_id', $helpscout_id )->where( 'mailbox_id', $mailbox_id )->first();
	}

	/**
	 * A value as the module keeps it in a field: a dropdown's option number, a multiselect dropdown's option label, a
	 * date as `Y-m-d`, a number, or the text.
	 *
	 * A dropdown option the field doesn't have is added to it, unless HelpScout had it when an import created or found
	 * the field: then it was deleted in FreeScout since.
	 *
	 * @param object      $field   Custom field's row.
	 * @param string      $text    HelpScout's value, as text.
	 * @param object|null $mapping Where an import created or found the field, from mapping().
	 * @return string|null Null if it can't be kept in the field, like a date that isn't one.
	 */
	private static function value( object $field, string $text, ?object $mapping ): ?string {
		$type = (int) $field->type;

		if ( self::TYPE_DATE === $type ) {
			return self::date( $text );
		}

		if ( self::TYPE_NUMBER === $type ) {
			return is_numeric( $text ) ? $text : null;
		}

		// A field FreeScout had by the name may split its value at commas.
		if ( self::TYPE_MULTISELECT === $type ) {
			$text = self::without_commas( $text );

			return '' !== $text ? $text : null;
		}

		if ( ! in_array( $type, array( self::TYPE_DROPDOWN, self::TYPE_DROPDOWN_MULTISELECT ), true ) ) {
			return $text;
		}

		$known  = $mapping ? (array) json_decode( (string) $mapping->options, true ) : array();
		$number = self::option( $field, $text, $known );
		if ( null === $number ) {
			return null;
		}

		// An option HelpScout added since: it's HelpScout's now, so it isn't added again once it's deleted in FreeScout.
		if ( $mapping && ! in_array( $text, $known, true ) ) {
			$mapping->options = json_encode( array_merge( $known, array( $text ) ), JSON_UNESCAPED_UNICODE );
			\DB::table( 'wporghelpscoutimport_fields' )->where( 'id', $mapping->id )->update( array( 'options' => $mapping->options ) );
		}

		// A multiselect dropdown keeps the labels of the options chosen.
		return self::TYPE_DROPDOWN_MULTISELECT === $type ? (string) json_decode( (string) $field->options, true )[ $number ] : (string) $number;
	}

	/**
	 * A dropdown option's number in a field, which is added to its options if need be.
	 *
	 * @param object   $field   Custom field's row; its options are updated.
	 * @param string   $label   Option's label, as HelpScout has it.
	 * @param string[] $deleted HelpScout's labels not to add, since they were deleted in FreeScout.
	 * @return int|null Null if it's not a dropdown, or the option is one not to add.
	 */
	private static function option( object $field, string $label, array $deleted = array() ): ?int {
		$type = (int) $field->type;
		if ( ! in_array( $type, array( self::TYPE_DROPDOWN, self::TYPE_DROPDOWN_MULTISELECT ), true ) ) {
			return null;
		}

		$original = $label;
		if ( self::TYPE_DROPDOWN_MULTISELECT === $type ) {
			$label = self::without_commas( $label );
		}

		// Without the empty option the module gives a dropdown that has none.
		$options = array_filter(
			(array) json_decode( (string) $field->options, true ),
			static function ( $option ): bool {
				return '' !== (string) $option;
			}
		);
		$number  = array_search( $label, $options, true );
		if ( false !== $number ) {
			return (int) $number;
		}

		if ( '' === $label || in_array( $original, $deleted, true ) ) {
			return null;
		}

		$number             = $options ? max( array_map( 'intval', array_keys( $options ) ) ) + 1 : 1;
		$options[ $number ] = $label;
		$field->options     = json_encode( $options );
		\DB::table( 'custom_fields' )->where( 'id', $field->id )->update( array( 'options' => $field->options ) );

		return $number;
	}

	/**
	 * A value without commas, which the module would split it at, for fields that keep several.
	 *
	 * @param string $text Value.
	 * @return string
	 */
	private static function without_commas( string $text ): string {
		return trim( (string) preg_replace( '/[\s,]+/u', ' ', $text ) );
	}

	/**
	 * A date as `Y-m-d`, from one HelpScout gives as `Y-m-d`, or as an ISO 8601 date and time.
	 *
	 * @param string $text HelpScout's value.
	 * @return string|null Null if it isn't such a date, like a relative one ("next friday").
	 */
	private static function date( string $text ): ?string {
		$time = '(?:T\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}(?::?\d{2})?)?)?';
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})' . $time . '$/', $text, $parts ) || ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
			return null;
		}

		return $parts[1] . '-' . $parts[2] . '-' . $parts[3];
	}
}

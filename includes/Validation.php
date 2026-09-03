<?php
namespace FormPipe;

defined( 'ABSPATH' ) || exit;

/**
 * One validation result per field.
 *
 * The container pattern is preserved for back-compat with custom module
 * code that reads $validation['field'] (CF7 did the same trick); new code
 * should use the explicit methods.
 */
final class Validation implements \ArrayAccess {

	/** @var array<string,array{reason:string,idref:string}> */
	private array $invalid_fields = [];

	/** @var array<string,mixed> */
	private array $values = [];

	public function add_error( string $field, string $reason ): void {
		$idref = '';

		// Look up the id for this field among scanned tags.
		foreach ( FormTagsManager::last_scanned() as $tag ) {
			if ( $tag->name === $field ) {
				$id = $tag->get_id_option();
				if ( $id !== '' && ! str_starts_with( $id, 'formpipe' ) ) {
					$idref = $id;
				}
				break;
			}
		}

		$this->invalid_fields[ $field ] = [
			'field'  => $field,
			'reason' => $reason,
			'idref'  => $idref,
		];
	}

	public function is_valid(): bool {
		return $this->invalid_fields === [];
	}

	public function is_field_valid( string $field ): bool {
		return ! isset( $this->invalid_fields[ $field ] );
	}

	public function get_errors(): array {
		return $this->invalid_fields;
	}

	public function get_error( string $field ) {
		return $this->invalid_fields[ $field ] ?? null;
	}

	/** @param array<string,mixed> $values */
	public function set_values( array $values ): void {
		$this->values = $values;
	}

	/** @return array<string,mixed> */
	public function get_values(): array {
		return $this->values;
	}

	// ArrayAccess for back-compat with custom validation filters.

	#[\ReturnTypeWillChange]
	public function offsetExists( $offset ): bool {
		return isset( $this->invalid_fields[ $offset ] );
	}

	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		return $this->invalid_fields[ $offset ] ?? null;
	}

	#[\ReturnTypeWillChange]
	public function offsetSet( $offset, $value ): void {
		// Accept legacy "reason" key as a per-field map.
		if ( 'reason' === $offset && is_array( $value ) ) {
			foreach ( $value as $field => $reason ) {
				$this->add_error( (string) $field, (string) $reason );
			}
		}
	}

	#[\ReturnTypeWillChange]
	public function offsetUnset( $offset ): void {
		unset( $this->invalid_fields[ $offset ] );
	}
}

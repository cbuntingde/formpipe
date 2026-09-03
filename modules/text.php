<?php
namespace FormPipe;
/**
 * text / email / url / tel field module.
 *
 * Registers a single handler that produces an <input type="…"> for each
 * of the four basetypes. Validation filters are registered per type.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'formpipe_init', static function (): void {
	\FormPipe\FormTagsManager::register(
		[ 'text', 'text*', 'email', 'email*', 'url', 'url*', 'tel', 'tel*' ],
		static function ( \FormPipe\FormTag $tag ): string {
			$type = 'text';
			foreach ( [ 'email' => 'email', 'url' => 'url', 'tel' => 'tel' ] as $prefix => $value ) {
				if ( str_starts_with( $tag->type, $prefix ) ) {
					$type = $value;
					break;
				}
			}

			$default = $tag->get_default_option();

			$atts = [
				'type'     => $type,
				'name'     => $tag->name,
				'id'       => $tag->get_id_option() ?: null,
				'class'    => $tag->get_class_option( 'formpipe-field' ) ?: null,
				'value'    => $default,
				'size'     => (int) ( $tag->get_option( 'size', '\d+', true ) ?: 40 ),
				'maxlength'=> (int) ( $tag->get_option( 'maxlength', '\d+', true ) ?: 0 ) ?: null,
				'minlength'=> (int) ( $tag->get_option( 'minlength', '\d+', true ) ?: 0 ) ?: null,
				'tabindex' => (int) ( $tag->get_option( 'tabindex', '-?\d+', true ) ?: 0 ) ?: null,
				'placeholder'=> $tag->get_option( 'placeholder', '.+', true ) ?: null,
				'autocomplete'=> $tag->get_option( 'autocomplete', '[-0-9a-zA-Z|_]+', true ) ?: null,
			];

			if ( $tag->is_required() ) {
				$atts['required']      = true;
				$atts['aria-required'] = 'true';
			}

			$atts = array_filter( $atts, static fn( $v ) => $v !== null && $v !== '' && $v !== 0 );

			return sprintf(
				'<span class="formpipe-control" data-name="%1$s"><input %2$s /></span>',
				esc_attr( $tag->name ),
				\FormPipe\format_atts( $atts )
			);
		},
		[ 'name-attr' => true ]
	);
} );

/**
 * Posted-data filter: trim whitespace.
 */
add_filter( 'formpipe_posted_text',  static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, 10, 1 );
add_filter( 'formpipe_posted_text*', static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, 10, 1 );
add_filter( 'formpipe_posted_email', static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, 10, 1 );
add_filter( 'formpipe_posted_email*', static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, 10, 1 );
add_filter( 'formpipe_posted_url',   static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, 10, 1 );
add_filter( 'formpipe_posted_url*',  static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, 10, 1 );
add_filter( 'formpipe_posted_tel',   static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, 10, 1 );
add_filter( 'formpipe_posted_tel*',  static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, 10, 1 );

/**
 * Per-type validation filters.
 */
$text_validate_required = static function ( \FormPipe\Validation $v, \FormPipe\FormTag $t, $value ): \FormPipe\Validation {
	$min = (int) ( $t->get_option( 'minlength', '\d+', true ) ?: 0 );
	$max = (int) ( $t->get_option( 'maxlength', '\d+', true ) ?: 0 );

	if ( $value === '' || $value === null ) {
		return $v;
	}
	if ( $min > 0 && mb_strlen( (string) $value ) < $min ) {
		$v->add_error( $t->name, __( 'This field has too short input.', 'formpipe' ) );
	}
	if ( $max > 0 && mb_strlen( (string) $value ) > $max ) {
		$v->add_error( $t->name, __( 'This field has too long input.', 'formpipe' ) );
	}
	return $v;
};

add_filter( 'formpipe_validate_text',  $text_validate_required, 10, 3 );
add_filter( 'formpipe_validate_text*', $text_validate_required, 10, 3 );

add_filter( 'formpipe_validate_email', static function ( \FormPipe\Validation $v, \FormPipe\FormTag $t, $value ): \FormPipe\Validation {
	if ( $value === '' || $value === null ) {
		return $v;
	}
	if ( ! formpipe_is_email( (string) $value ) ) {
		$v->add_error( $t->name, __( 'Please enter a valid email address.', 'formpipe' ) );
	}
	return $v;
}, 10, 3 );

add_filter( 'formpipe_validate_email*', static function ( \FormPipe\Validation $v, \FormPipe\FormTag $t, $value ): \FormPipe\Validation {
	if ( $value === '' || $value === null ) {
		return $v;
	}
	if ( ! formpipe_is_email( (string) $value ) ) {
		$v->add_error( $t->name, __( 'Please enter a valid email address.', 'formpipe' ) );
	}
	return $v;
}, 10, 3 );

add_filter( 'formpipe_validate_url', static function ( \FormPipe\Validation $v, \FormPipe\FormTag $t, $value ): \FormPipe\Validation {
	if ( $value === '' || $value === null ) {
		return $v;
	}
	if ( ! formpipe_is_url( (string) $value ) ) {
		$v->add_error( $t->name, __( 'Please enter a valid URL.', 'formpipe' ) );
	}
	return $v;
}, 10, 3 );

add_filter( 'formpipe_validate_url*', static function ( \FormPipe\Validation $v, \FormPipe\FormTag $t, $value ): \FormPipe\Validation {
	if ( $value === '' || $value === null ) {
		return $v;
	}
	if ( ! formpipe_is_url( (string) $value ) ) {
		$v->add_error( $t->name, __( 'Please enter a valid URL.', 'formpipe' ) );
	}
	return $v;
}, 10, 3 );

add_filter( 'formpipe_validate_tel', static function ( \FormPipe\Validation $v, \FormPipe\FormTag $t, $value ): \FormPipe\Validation {
	if ( $value === '' || $value === null ) {
		return $v;
	}
	if ( ! formpipe_is_tel( (string) $value ) ) {
		$v->add_error( $t->name, __( 'Please enter a valid phone number.', 'formpipe' ) );
	}
	return $v;
}, 10, 3 );

add_filter( 'formpipe_validate_tel*', static function ( \FormPipe\Validation $v, \FormPipe\FormTag $t, $value ): \FormPipe\Validation {
	if ( $value === '' || $value === null ) {
		return $v;
	}
	if ( ! formpipe_is_tel( (string) $value ) ) {
		$v->add_error( $t->name, __( 'Please enter a valid phone number.', 'formpipe' ) );
	}
	return $v;
}, 10, 3 );

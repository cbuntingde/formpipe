<?php
namespace FormPipe;
/**
 * number / number* / range / range* module.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'formpipe_init', static function (): void {
	\FormPipe\FormTagsManager::register(
		[ 'number', 'number*', 'range', 'range*' ],
		static function ( \FormPipe\FormTag $tag ): string {
			$type = $tag->basetype === 'range' ? 'range' : 'number';

			$atts = [
				'type'  => $type,
				'name'  => $tag->name,
				'id'    => $tag->get_id_option() ?: null,
				'class' => $tag->get_class_option( 'formpipe-field' ) ?: null,
			];

			if ( $tag->is_required() ) {
				$atts['required']      = true;
				$atts['aria-required'] = 'true';
			}

			foreach ( [
				'min'  => '-?\d+(?:\.\d+)?',
				'max'  => '-?\d+(?:\.\d+)?',
				'step' => '\d+(?:\.\d+)?',
			] as $opt => $pattern ) {
				$v = $tag->get_option( $opt, $pattern, true );
				if ( $v !== false ) {
					$atts[ $opt ] = $v;
				}
			}

			$default = $tag->get_default_option();
			if ( $default !== '' ) {
				$atts['value'] = (string) $default;
			}

			return sprintf(
				'<span class="formpipe-control" data-name="%1$s"><input %2$s /></span>',
				esc_attr( $tag->name ),
				\FormPipe\format_atts( $atts )
			);
		},
		[ 'name-attr' => true ]
	);
} );

add_filter( 'formpipe_posted_number',  static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, 10, 1 );
add_filter( 'formpipe_posted_number*', static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, 10, 1 );
add_filter( 'formpipe_posted_range',   static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, 10, 1 );
add_filter( 'formpipe_posted_range*',  static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, 10, 1 );

$number_validate = static function ( \FormPipe\Validation $v, \FormPipe\FormTag $t, $value ): \FormPipe\Validation {
	if ( $value === '' || $value === null ) {
		return $v;
	}
	if ( ! is_numeric( $value ) ) {
		$v->add_error( $t->name, __( 'Please enter a number.', 'formpipe' ) );
		return $v;
	}
	$num = (float) $value;
	foreach ( [ 'min' => '>=', 'max' => '<=' ] as $opt => $op ) {
		$bound = $t->get_option( $opt, '-?\d+(?:\.\d+)?', true );
		if ( $bound === false ) {
			continue;
		}
		if ( '>=' === $op && $num < (float) $bound ) {
			$v->add_error( $t->name, sprintf( __( 'Please enter a value of %s or higher.', 'formpipe' ), $bound ) );
		}
		if ( '<=' === $op && $num > (float) $bound ) {
			$v->add_error( $t->name, sprintf( __( 'Please enter a value of %s or lower.', 'formpipe' ), $bound ) );
		}
	}
	return $v;
};

add_filter( 'formpipe_validate_number',  $number_validate, 10, 3 );
add_filter( 'formpipe_validate_number*', $number_validate, 10, 3 );
add_filter( 'formpipe_validate_range',   $number_validate, 10, 3 );
add_filter( 'formpipe_validate_range*',  $number_validate, 10, 3 );

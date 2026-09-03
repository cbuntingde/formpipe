<?php
namespace FormPipe;
/**
 * select / select* module. Pipe-encoded values via FormPipe_Pipes.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'formpipe_init', static function (): void {
	\FormPipe\FormTagsManager::register(
		[ 'select', 'select*' ],
		static function ( \FormPipe\FormTag $tag ): string {
			$is_multiple = $tag->has_option( 'multiple' ) || $tag->has_option( 'include_blank' );
			$name        = $tag->name . ( $is_multiple ? '[]' : '' );

			$default = (array) $tag->get_default_option( '', false );

			$atts = [
				'name'     => $name,
				'id'       => $tag->get_id_option() ?: null,
				'class'    => $tag->get_class_option( 'formpipe-field' ) ?: null,
				'tabindex' => (int) ( $tag->get_option( 'tabindex', '-?\d+', true ) ?: 0 ) ?: null,
			];

			if ( $tag->is_required() ) {
				$atts['required']      = true;
				$atts['aria-required'] = 'true';
			}

			if ( $is_multiple ) {
				$atts['multiple'] = true;
			}

			$options = '';
			foreach ( $tag->values as $i => $value ) {
				$opt_atts = 'value="' . esc_attr( $value ) . '"';

				if ( in_array( $value, $default, true ) || (string) $i === (string) $default ) {
					$opt_atts .= ' selected';
				}

				$options .= '<option ' . $opt_atts . '>' . esc_html( $tag->labels[ $i ] ?? $value ) . '</option>';
			}

			if ( $tag->has_option( 'include_blank' ) ) {
				$options = '<option value=""></option>' . $options;
			}

			$atts = array_filter( $atts, static fn( $v ) => $v !== null && $v !== '' && $v !== 0 );

			return sprintf(
				'<span class="formpipe-control" data-name="%1$s"><select %2$s>%3$s</select></span>',
				esc_attr( $tag->name ),
				\FormPipe\format_atts( $atts ),
				$options
			);
		},
		[ 'name-attr' => true, 'selectable' => true ]
	);
} );

add_filter( 'formpipe_posted_select',  static function ( $value, $orig, $tag ) {
	if ( ! ( $tag instanceof \FormPipe\FormTag ) ) {
		return $value;
	}
	return is_array( $orig ) ? array_map( 'sanitize_text_field', $orig ) : sanitize_text_field( (string) $orig );
}, 10, 3 );

add_filter( 'formpipe_posted_select*', static function ( $value, $orig, $tag ) {
	if ( ! ( $tag instanceof \FormPipe\FormTag ) ) {
		return $value;
	}
	return is_array( $orig ) ? array_map( 'sanitize_text_field', $orig ) : sanitize_text_field( (string) $orig );
}, 10, 3 );

$validate_select = static function ( \FormPipe\Validation $v, \FormPipe\FormTag $t, $value ): \FormPipe\Validation {
	if ( $value === '' || $value === null || $value === [] ) {
		return $v;
	}
	$allowed = $t->values;
	$check   = static function ( $v ) use ( $allowed ) {
		return in_array( (string) $v, $allowed, true );
	};
	$bad = is_array( $value ) ? array_filter( $value, static fn( $x ) => ! $check( $x ) ) : ( $check( $value ) ? [] : [ $value ] );
	if ( $bad !== [] ) {
		$v->add_error( $t->name, __( 'Please select a valid choice.', 'formpipe' ) );
	}
	return $v;
};

add_filter( 'formpipe_validate_select',  $validate_select, 10, 3 );
add_filter( 'formpipe_validate_select*', $validate_select, 10, 3 );

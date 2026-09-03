<?php
namespace FormPipe;
/**
 * checkbox / checkbox* module.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'formpipe_init', static function (): void {
	\FormPipe\FormTagsManager::register(
		[ 'checkbox', 'checkbox*' ],
		static function ( \FormPipe\FormTag $tag ): string {
			$default   = (array) $tag->get_default_option( '', true );
			$exclusive = $tag->has_option( 'exclusive' );

			$wrap_atts = [
				'class' => $tag->get_class_option( 'formpipe-field formpipe-checkbox' ) ?: null,
			];

			$inner = '';
			foreach ( $tag->values as $i => $value ) {
				$opt_atts = [
					'type'  => 'checkbox',
					'name'  => $tag->name . '[]',
					'value' => $value,
				];

				if ( $exclusive ) {
					$opt_atts['name'] = $tag->name;
				}

				if ( $tag->is_required() ) {
					$opt_atts['required'] = true;
				}

				if ( in_array( $value, $default, true ) ) {
					$opt_atts['checked'] = true;
				}

				$inner .= sprintf(
					'<label><input %1$s /> %2$s</label>',
					\FormPipe\format_atts( $opt_atts ),
					esc_html( $tag->labels[ $i ] ?? $value )
				);
			}

			return sprintf(
				'<span %1$s data-name="%2$s">%3$s</span>',
				\FormPipe\format_atts( $wrap_atts ),
				esc_attr( $tag->name ),
				$inner
			);
		},
		[ 'name-attr' => true, 'selectable' => true ]
	);
} );

add_filter( 'formpipe_posted_checkbox', static function ( $value, $orig, $tag ) {
	return is_array( $orig ) ? array_map( 'sanitize_text_field', $orig ) : [];
}, 10, 3 );

add_filter( 'formpipe_posted_checkbox*', static function ( $value, $orig, $tag ) {
	return is_array( $orig ) ? array_map( 'sanitize_text_field', $orig ) : [];
}, 10, 3 );

$validate_checkbox = static function ( \FormPipe\Validation $v, \FormPipe\FormTag $t, $value ): \FormPipe\Validation {
	if ( ! is_array( $value ) || $value === [] ) {
		return $v;
	}
	$allowed = $t->values;
	foreach ( $value as $v ) {
		if ( ! in_array( (string) $v, $allowed, true ) ) {
			$v->add_error( $t->name, __( 'Please choose a valid option.', 'formpipe' ) );
			return $v;
		}
	}
	return $v;
};

add_filter( 'formpipe_validate_checkbox',  $validate_checkbox, 10, 3 );
add_filter( 'formpipe_validate_checkbox*', $validate_checkbox, 10, 3 );

<?php
namespace FormPipe;
/**
 * radio / radio* module.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'formpipe_init', static function (): void {
	\FormPipe\FormTagsManager::register(
		[ 'radio', 'radio*' ],
		static function ( \FormPipe\FormTag $tag ): string {
			$default = (string) $tag->get_default_option();

			$wrap_atts = [
				'class' => $tag->get_class_option( 'formpipe-field formpipe-radio' ) ?: null,
			];

			$inner = '';
			foreach ( $tag->values as $i => $value ) {
				$opt_atts = [
					'type'  => 'radio',
					'name'  => $tag->name,
					'value' => $value,
				];

				if ( $tag->is_required() ) {
					$opt_atts['required'] = true;
				}

				if ( $value === $default ) {
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

add_filter( 'formpipe_posted_radio',  static function ( $value, $orig, $tag ) {
	return is_array( $orig ) ? sanitize_text_field( (string) reset( $orig ) ) : sanitize_text_field( (string) $orig );
}, 10, 3 );

add_filter( 'formpipe_posted_radio*', static function ( $value, $orig, $tag ) {
	return is_array( $orig ) ? sanitize_text_field( (string) reset( $orig ) ) : sanitize_text_field( (string) $orig );
}, 10, 3 );

$validate_radio = static function ( \FormPipe\Validation $v, \FormPipe\FormTag $t, $value ): \FormPipe\Validation {
	if ( $value === '' || $value === null ) {
		return $v;
	}
	if ( ! in_array( (string) $value, $t->values, true ) ) {
		$v->add_error( $t->name, __( 'Please choose a valid option.', 'formpipe' ) );
	}
	return $v;
};

add_filter( 'formpipe_validate_radio',  $validate_radio, 10, 3 );
add_filter( 'formpipe_validate_radio*', $validate_radio, 10, 3 );

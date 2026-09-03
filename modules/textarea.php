<?php
namespace FormPipe;
/**
 * textarea / textarea* module.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'formpipe_init', static function (): void {
	\FormPipe\FormTagsManager::register(
		[ 'textarea', 'textarea*' ],
		static function ( \FormPipe\FormTag $tag ): string {
			$default = $tag->get_default_option();

			$atts = [
				'name'        => $tag->name,
				'id'          => $tag->get_id_option() ?: null,
				'class'       => $tag->get_class_option( 'formpipe-field' ) ?: null,
				'rows'        => (int) ( $tag->get_option( 'rows', '\d+', true ) ?: 5 ),
				'cols'        => (int) ( $tag->get_option( 'cols', '\d+', true ) ?: 40 ),
				'maxlength'   => (int) ( $tag->get_option( 'maxlength', '\d+', true ) ?: 0 ) ?: null,
				'minlength'   => (int) ( $tag->get_option( 'minlength', '\d+', true ) ?: 0 ) ?: null,
				'placeholder' => $tag->get_option( 'placeholder', '.+', true ) ?: null,
				'tabindex'    => (int) ( $tag->get_option( 'tabindex', '-?\d+', true ) ?: 0 ) ?: null,
				'autocomplete'=> $tag->get_option( 'autocomplete', '[-0-9a-zA-Z|_]+', true ) ?: null,
			];

			if ( $tag->is_required() ) {
				$atts['required']      = true;
				$atts['aria-required'] = 'true';
			}

			$atts = array_filter( $atts, static fn( $v ) => $v !== null && $v !== '' && $v !== 0 );

			return sprintf(
				'<span class="formpipe-control" data-name="%1$s"><textarea %2$s>%3$s</textarea></span>',
				esc_attr( $tag->name ),
				\FormPipe\format_atts( $atts ),
				esc_textarea( (string) $default )
			);
		},
		[ 'name-attr' => true ]
	);
} );

add_filter( 'formpipe_posted_textarea',  static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, 10, 1 );
add_filter( 'formpipe_posted_textarea*', static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, 10, 1 );

$textarea_validate = static function ( \FormPipe\Validation $v, \FormPipe\FormTag $t, $value ): \FormPipe\Validation {
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

add_filter( 'formpipe_validate_textarea',  $textarea_validate, 10, 3 );
add_filter( 'formpipe_validate_textarea*', $textarea_validate, 10, 3 );

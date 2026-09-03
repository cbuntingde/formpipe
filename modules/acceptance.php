<?php
namespace FormPipe;
/**
 * acceptance / acceptance* module.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'formpipe_init', static function (): void {
	\FormPipe\FormTagsManager::register(
		[ 'acceptance', 'acceptance*' ],
		static function ( \FormPipe\FormTag $tag ): string {
			$atts = [
				'type'  => 'checkbox',
				'name'  => $tag->name,
				'value' => '1',
				'class' => $tag->get_class_option( 'formpipe-field formpipe-acceptance' ) ?: null,
				'id'    => $tag->get_id_option() ?: null,
				'tabindex'=> (int) ( $tag->get_option( 'tabindex', '-?\d+', true ) ?: 0 ) ?: null,
			];

			if ( $tag->is_required() ) {
				$atts['required']      = true;
				$atts['aria-required'] = 'true';
			}

			$atts = array_filter( $atts, static fn( $v ) => $v !== null && $v !== '' && $v !== 0 );

			$label = $tag->values[0] !== ''
				? $tag->values[0]
				: ( $tag->content !== '' ? $tag->content : __( 'I accept.', 'formpipe' ) );

			return sprintf(
				'<span class="formpipe-control" data-name="%1$s"><label><input %2$s /> %3$s</label></span>',
				esc_attr( $tag->name ),
				\FormPipe\format_atts( $atts ),
				esc_html( $label )
			);
		},
		[ 'name-attr' => true ]
	);
} );

add_filter( 'formpipe_posted_acceptance',  static fn( $v ) => $v ? '1' : '', 10, 1 );
add_filter( 'formpipe_posted_acceptance*', static fn( $v ) => $v ? '1' : '', 10, 1 );

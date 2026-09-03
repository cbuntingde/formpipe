<?php
namespace FormPipe;
/**
 * submit module.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'formpipe_init', static function (): void {
	\FormPipe\FormTagsManager::register(
		'submit',
		static function ( \FormPipe\FormTag $tag ): string {
			$label = $tag->values[0] ?? __( 'Send', 'formpipe' );

			$atts = [
				'type'  => 'submit',
				'name'  => $tag->name,
				'id'    => $tag->get_id_option() ?: null,
				'class' => $tag->get_class_option( 'formpipe-submit' ) ?: null,
				'value' => $label,
			];

			return sprintf(
				'<button %1$s>%2$s</button>',
				\FormPipe\format_atts( $atts ),
				esc_html( $label )
			);
		},
		[]
	);
} );

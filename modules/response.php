<?php
namespace FormPipe;
/**
 * response module: explicit response-output placeholder.
 *
 * Tag syntax: [response "your-name"]
 *
 * If the named field has a posted value, it's echoed in the response area.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'formpipe_init', static function (): void {
	\FormPipe\FormTagsManager::register(
		'response',
		static function ( \FormPipe\FormTag $tag ): string {
			$field = $tag->values[0] ?? '';
			$label = $tag->content !== '' ? $tag->content : $field;

			return sprintf(
				'<output class="formpipe-response-field" data-field="%1$s">'
					. '<span class="formpipe-response-label">%2$s</span>'
					. '<span class="formpipe-response-value"></span>'
				. '</output>',
				esc_attr( $field ),
				esc_html( $label )
			);
		},
		[]
	);
} );

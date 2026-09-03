<?php
namespace FormPipe;
/**
 * count module: live character/word counter.
 *
 * Tag syntax: [count your-message mode:chars]
 */

defined( 'ABSPATH' ) || exit;

add_action( 'formpipe_init', static function (): void {
	\FormPipe\FormTagsManager::register(
		'count',
		static function ( \FormPipe\FormTag $tag ): string {
			$target = $tag->name;
			$mode   = $tag->get_option( 'mode', 'chars|words', true ) ?: 'chars';

			return sprintf(
				'<output class="formpipe-count" for="%1$s" data-mode="%2$s">0</output>',
				esc_attr( $target ),
				esc_attr( $mode )
			);
		},
		[]
	);
} );

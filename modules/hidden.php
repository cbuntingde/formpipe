<?php
namespace FormPipe;
/**
 * hidden module.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'formpipe_init', static function (): void {
	\FormPipe\FormTagsManager::register(
		'hidden',
		static function ( \FormPipe\FormTag $tag ): string {
			$default = $tag->get_default_option();
			return sprintf(
				'<input type="hidden" name="%1$s" value="%2$s" class="formpipe-hidden" />',
				esc_attr( $tag->name ),
				esc_attr( (string) $default )
			);
		},
		[ 'name-attr' => true ]
	);
} );

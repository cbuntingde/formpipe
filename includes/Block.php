<?php
namespace FormPipe;

defined( 'ABSPATH' ) || exit;

/**
 * Gutenberg block: server-rendered form embed.
 */
final class Block {

	public function register(): void {
		add_action( 'init', [ $this, 'register_block' ], 10, 0 );
	}

	public function register_block(): void {
		register_block_type( FORMPIPE_DIR . '/assets/block', [
			'render_callback' => [ $this, 'render' ],
		] );
	}

	public function render( array $attrs, string $content = '' ): string {
		$id    = (int) ( $attrs['formId'] ?? 0 );
		$title = (string) ( $attrs['title'] ?? '' );

		if ( $id <= 0 && $title !== '' ) {
			$posts = get_posts( [
				'post_type'      => Form::POST_TYPE,
				'title'          => $title,
				'posts_per_page' => 1,
			] );
			$id = $posts ? (int) $posts[0]->ID : 0;
		}

		if ( $id <= 0 ) {
			return '';
		}

		$form = Form::from_post( $id );
		if ( $form === null ) {
			return '';
		}

		return $form->render();
	}
}

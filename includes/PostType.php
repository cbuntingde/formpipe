<?php
namespace FormPipe;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `formpipe_form` custom post type and maps our capability
 * aliases onto core page capabilities.
 */
final class PostType {

	public const POST_TYPE = 'formpipe_form';

	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ], 5, 0 );
		add_filter( 'map_meta_cap', [ $this, 'map_meta_cap' ], 10, 4 );
	}

	public function register_post_type(): void {
		register_post_type( self::POST_TYPE, [
			'labels'          => [
				'name'          => __( 'Forms', 'formpipe' ),
				'singular_name' => __( 'Form', 'formpipe' ),
				'add_new_item'  => __( 'Add Form', 'formpipe' ),
				'edit_item'     => __( 'Edit Form', 'formpipe' ),
				'new_item'      => __( 'New Form', 'formpipe' ),
				'view_item'     => __( 'View Form', 'formpipe' ),
				'search_items'  => __( 'Search Forms', 'formpipe' ),
				'not_found'     => __( 'No forms found.', 'formpipe' ),
				'menu_name'     => __( 'Forms', 'formpipe' ),
			],
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => false,
			'rewrite'         => false,
			'query_var'       => false,
			'capability_type' => 'page',
			'supports'        => [ 'title' ],
			'capabilities'    => [
				'edit_post'          => 'formpipe_edit_form',
				'read_post'          => 'formpipe_read_form',
				'delete_post'        => 'formpipe_delete_form',
				'edit_posts'         => 'formpipe_edit_forms',
				'edit_others_posts'  => 'formpipe_edit_forms',
				'publish_posts'      => 'formpipe_edit_forms',
				'read_private_posts' => 'formpipe_edit_forms',
			],
		] );
	}

	/**
	 * Translate formpipe_* capabilities onto core page capabilities.
	 */
	public function map_meta_cap( $caps, $cap, $user_id, $args ): array {
		$caps = (array) $caps;

		switch ( $cap ) {
			case 'formpipe_edit_form':
			case 'formpipe_edit_forms':
				$caps = [ 'edit_pages' ];
				break;

			case 'formpipe_read_form':
			case 'formpipe_read_forms':
				$caps = [ 'edit_posts' ];
				break;

			case 'formpipe_delete_form':
			case 'formpipe_delete_forms':
				$caps = [ 'delete_pages' ];
				break;

			case 'formpipe_submit':
				$caps = [ 'read' ];
				break;
		}

		return $caps;
	}
}

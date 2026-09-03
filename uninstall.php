<?php
/**
 * Uninstall: remove all forms and our post meta.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$posts = get_posts( [
	'post_type'      => 'formpipe_form',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
] );

foreach ( $posts as $id ) {
	wp_delete_post( (int) $id, true );
}

delete_option( 'formpipe_options' );

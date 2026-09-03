<?php
/**
 * @var \FormPipe\FormListTable $table
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Forms', 'formpipe' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=formpipe-new' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New', 'formpipe' ); ?>
	</a>
	<hr class="wp-header-end" />

	<?php if ( ! empty( $_GET['message'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
			<?php
			switch ( $_GET['message'] ) {
				case 'deleted': printf( esc_html( _n( '%d form deleted.', '%d forms deleted.', (int) ( $_GET['count'] ?? 0 ), 'formpipe' ) ), (int) ( $_GET['count'] ?? 0 ) ); break;
				case 'created': esc_html_e( 'Form created.', 'formpipe' ); break;
				case 'saved':   esc_html_e( 'Form saved.', 'formpipe' ); break;
			}
			?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="formpipe_delete" />
		<?php wp_nonce_field( 'bulk-formpipe-forms' ); ?>
		<?php $table->display(); ?>
	</form>
</div>

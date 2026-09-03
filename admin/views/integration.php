<?php
defined( 'ABSPATH' ) || exit;

$integration = \FormPipe\Integration::get_instance();
$integrations = $integration->all();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Integration', 'formpipe' ); ?></h1>

	<?php if ( ! empty( $_GET['message'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved.', 'formpipe' ); ?></p>
		</div>
	<?php endif; ?>

	<p><?php esc_html_e( 'Connect this site to external services for spam protection, email marketing, payments, and CRM.', 'formpipe' ); ?></p>

	<?php if ( $integrations === [] ) : ?>
		<p><?php esc_html_e( 'No integrations are currently registered. Install an integration module to enable connections.', 'formpipe' ); ?></p>
	<?php else : ?>
		<div class="formpipe-integrations">
			<?php foreach ( $integrations as $name => $cfg ) : ?>
				<div class="formpipe-integration-card card <?php echo ! empty( $cfg['is_active'] ) && ( $cfg['is_active'] )() ? 'active' : ''; ?>" id="<?php echo esc_attr( $name ); ?>">
					<h2 class="title"><?php echo esc_html( $cfg['title'] ?? $name ); ?></h2>
					<div class="formpipe-integration-body">
						<?php echo (string) ( $cfg['render'] )( $cfg ); ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

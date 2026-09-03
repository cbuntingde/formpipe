<?php
/**
 * @var \FormPipe\Form $form
 * @var string        $active_tab
 */
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/helpers.php';

$tabs = [
	'form'      => __( 'Form', 'formpipe' ),
	'mail'      => __( 'Mail', 'formpipe' ),
	'mail-2'    => __( 'Mail (2)', 'formpipe' ),
	'messages'  => __( 'Messages', 'formpipe' ),
	'settings'  => __( 'Additional Settings', 'formpipe' ),
];

if ( ! array_key_exists( $active_tab, $tabs ) ) {
	$active_tab = 'form';
}

$post_id = $form->initial() ? -1 : $form->id();

$scanned = \FormPipe\FormTagsManager::last_scanned();
?>
<div class="wrap formpipe-editor">
	<h1 class="wp-heading-inline">
		<?php echo $form->initial() ? esc_html__( 'Add Form', 'formpipe' ) : esc_html__( 'Edit Form', 'formpipe' ); ?>
	</h1>

	<?php if ( ! $form->initial() && current_user_can( 'formpipe_edit_forms' ) ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=formpipe-new' ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add New', 'formpipe' ); ?>
		</a>
	<?php endif; ?>

	<hr class="wp-header-end" />

	<?php if ( ! empty( $_GET['message'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
			<?php
			switch ( $_GET['message'] ) {
				case 'saved':   esc_html_e( 'Form saved.', 'formpipe' ); break;
				case 'created': esc_html_e( 'Form created.', 'formpipe' ); break;
				default:         echo esc_html( (string) $_GET['message'] );
			}
			?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="formpipe-admin-form">
		<input type="hidden" name="action" value="formpipe_save" />
		<input type="hidden" name="post_ID" value="<?php echo esc_attr( (string) $post_id ); ?>" />
		<input type="hidden" name="formpipe-locale" value="<?php echo esc_attr( $form->locale() ); ?>" />
		<?php wp_nonce_field( 'formpipe_save_' . $post_id ); ?>

		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-2">

				<div id="post-body-content">
					<div id="titlediv">
						<div id="titlewrap">
							<input type="text" name="post_title" id="title"
								value="<?php echo esc_attr( $form->title() ); ?>"
								placeholder="<?php esc_attr_e( 'Enter title here', 'formpipe' ); ?>"
								spellcheck="true" autocomplete="off"
								<?php disabled( ! current_user_can( 'formpipe_edit_form', $post_id ) ); ?>
							/>
						</div>
					</div>

					<nav class="nav-tab-wrapper formpipe-tabs" role="tablist">
						<?php foreach ( $tabs as $slug => $label ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'active-tab', $slug ) ); ?>"
								class="nav-tab <?php echo $slug === $active_tab ? 'nav-tab-active' : ''; ?>"
								role="tab" aria-selected="<?php echo $slug === $active_tab ? 'true' : 'false'; ?>"
								data-tab="<?php echo esc_attr( $slug ); ?>">
								<?php echo esc_html( $label ); ?>
							</a>
						<?php endforeach; ?>
					</nav>

					<div class="formpipe-tab formpipe-tab-form" data-tab="form">
						<p>
							<?php esc_html_e( 'Use the tag-generator below to insert form fields. You can also write the form-tag syntax by hand.', 'formpipe' ); ?>
						</p>
						<div id="formpipe-tag-generator-buttons">
							<?php
							$gen = \FormPipe\TagGenerator::get_instance();
							foreach ( $gen->panels() as $id => $panel ) {
								printf(
									'<button type="button" class="button" data-taggen="open-dialog" data-target="%1$s">%2$s</button> ',
									esc_attr( $panel['content'] ),
									esc_html( $panel['title'] )
								);
							}
							?>
						</div>
						<textarea name="wpcf7-form" id="wpcf7-form" rows="20" class="large-text code"
							data-config-field="form.body"
							<?php disabled( ! current_user_can( 'formpipe_edit_form', $post_id ) ); ?>
						><?php echo esc_textarea( $form->template ); ?></textarea>
					</div>

					<div class="formpipe-tab formpipe-tab-mail" data-tab="mail" hidden>
						<h2><?php esc_html_e( 'Mail (1)', 'formpipe' ); ?></h2>
						<?php formpipe_admin_render_mail_fields( $form->mail, 'mail', 1 ); ?>
					</div>

					<div class="formpipe-tab formpipe-tab-mail-2" data-tab="mail-2" hidden>
						<h2>
							<?php esc_html_e( 'Mail (2)', 'formpipe' ); ?>
						</h2>
						<?php formpipe_admin_render_mail_fields( $form->mail_2, 'mail-2', 2 ); ?>
					</div>

					<div class="formpipe-tab formpipe-tab-messages" data-tab="messages" hidden>
						<h2><?php esc_html_e( 'Messages', 'formpipe' ); ?></h2>
						<table class="form-table">
							<tbody>
							<?php foreach ( $form->messages as $key => $value ) : ?>
								<tr>
									<th scope="row"><code><?php echo esc_html( $key ); ?></code></th>
									<td>
										<input type="text" name="messages[<?php echo esc_attr( $key ); ?>]"
											class="regular-text"
											value="<?php echo esc_attr( $value ); ?>"
											<?php disabled( ! current_user_can( 'formpipe_edit_form', $post_id ) ); ?>
										/>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<div class="formpipe-tab formpipe-tab-settings" data-tab="settings" hidden>
						<h2><?php esc_html_e( 'Additional Settings', 'formpipe' ); ?></h2>
						<p class="description">
							<?php esc_html_e( 'One per line: name:value. Example: demo_mode:on', 'formpipe' ); ?>
						</p>
						<?php
						$settings_text = '';
						foreach ( $form->settings as $k => $v ) {
							$settings_text .= $k . ':' . $v . "\n";
						}
						?>
						<textarea name="additional-settings" rows="6" class="large-text code"
							<?php disabled( ! current_user_can( 'formpipe_edit_form', $post_id ) ); ?>
						><?php echo esc_textarea( $settings_text ); ?></textarea>
					</div>
				</div>

				<div id="postbox-container-1" class="postbox-container">
					<div id="submitdiv" class="postbox">
						<h2><?php esc_html_e( 'Publish', 'formpipe' ); ?></h2>
						<div class="inside">
							<?php submit_button( $form->initial() ? __( 'Create', 'formpipe' ) : __( 'Update', 'formpipe' ), 'primary', 'wpcf7-save', false ); ?>
						</div>
					</div>

					<div class="postbox">
						<h2><?php esc_html_e( 'Shortcode', 'formpipe' ); ?></h2>
						<div class="inside">
							<code>[formpipe id="<?php echo esc_attr( (string) $post_id ); ?>"]</code>
						</div>
					</div>

					<div class="postbox">
						<h2><?php esc_html_e( 'Scanned fields', 'formpipe' ); ?></h2>
						<div class="inside">
							<?php if ( $scanned === [] ) : ?>
								<p><?php esc_html_e( 'No fields detected yet.', 'formpipe' ); ?></p>
							<?php else : ?>
								<ul>
									<?php foreach ( $scanned as $tag ) : ?>
										<li><code>[<?php echo esc_html( $tag->type ); ?><?php echo $tag->name !== '' ? ' ' . esc_html( $tag->name ) : ''; ?>]</code></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</form>

	<?php
	$gen = \FormPipe\TagGenerator::get_instance();
	foreach ( $gen->panels() as $id => $panel ) {
		$gen->print_panel( $form, $panel );
	}
	?>
</div>

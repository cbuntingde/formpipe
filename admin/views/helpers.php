<?php
/**
 * Helper functions for admin view templates.
 *
 * Loaded by admin view files; provides render_mail_fields() and friends
 * without forcing the views themselves to be methods of a class.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render the Mail section fields (recipient, sender, subject, body, headers, attachments, use_html, exclude_blank).
 *
 * @param array  $mail   The mail template array.
 * @param string $prefix Field-name prefix (e.g. 'mail' or 'mail-2').
 * @param int    $index  Visual index (1 or 2).
 */
function formpipe_admin_render_mail_fields( array $mail, string $prefix, int $index ): void {
	?>
	<table class="form-table">
		<tbody>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $prefix ); ?>-recipient"><?php esc_html_e( 'To', 'formpipe' ); ?></label></th>
			<td><input type="email" name="<?php echo esc_attr( $prefix ); ?>[recipient]" id="<?php echo esc_attr( $prefix ); ?>-recipient" class="regular-text" value="<?php echo esc_attr( $mail['recipient'] ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $prefix ); ?>-sender"><?php esc_html_e( 'From', 'formpipe' ); ?></label></th>
			<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[sender]" id="<?php echo esc_attr( $prefix ); ?>-sender" class="regular-text" value="<?php echo esc_attr( $mail['sender'] ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $prefix ); ?>-subject"><?php esc_html_e( 'Subject', 'formpipe' ); ?></label></th>
			<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[subject]" id="<?php echo esc_attr( $prefix ); ?>-subject" class="regular-text" value="<?php echo esc_attr( $mail['subject'] ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $prefix ); ?>-body"><?php esc_html_e( 'Message body', 'formpipe' ); ?></label></th>
			<td>
				<textarea name="<?php echo esc_attr( $prefix ); ?>[body]" id="<?php echo esc_attr( $prefix ); ?>-body" rows="14" class="large-text code"><?php echo esc_textarea( $mail['body'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Use mail-tags like [your-name] or [your-email] to insert submitted values.', 'formpipe' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $prefix ); ?>-headers"><?php esc_html_e( 'Additional headers', 'formpipe' ); ?></label></th>
			<td><textarea name="<?php echo esc_attr( $prefix ); ?>[additional_headers]" id="<?php echo esc_attr( $prefix ); ?>-headers" rows="3" class="large-text"><?php echo esc_textarea( $mail['additional_headers'] ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $prefix ); ?>-attachments"><?php esc_html_e( 'Attachments', 'formpipe' ); ?></label></th>
			<td><textarea name="<?php echo esc_attr( $prefix ); ?>[attachments]" id="<?php echo esc_attr( $prefix ); ?>-attachments" rows="3" class="large-text"><?php echo esc_textarea( $mail['attachments'] ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'HTML mail', 'formpipe' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[use_html]" value="1" <?php checked( ! empty( $mail['use_html'] ) ); ?> />
					<?php esc_html_e( 'Send as HTML', 'formpipe' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Exclude blank lines', 'formpipe' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[exclude_blank]" value="1" <?php checked( ! empty( $mail['exclude_blank'] ) ); ?> />
					<?php esc_html_e( 'Skip lines whose mail-tags are all empty', 'formpipe' ); ?>
				</label>
			</td>
		</tr>
		</tbody>
	</table>
	<?php
}

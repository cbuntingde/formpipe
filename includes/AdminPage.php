<?php
namespace FormPipe;

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI: list table + editor + integration page + welcome panel + help tabs.
 *
 * The list table extends WP_List_Table. The editor uses tabs (Form / Mail /
 * Mail-2 / Messages / Additional settings). The integration page renders
 * registered integrations.
 */
final class AdminPage {

	public const SCREEN_ID     = 'toplevel_page_formpipe';
	public const EDIT_SCREEN   = 'formpipe_page_formpipe-new';
	public const INTEG_SCREEN  = 'formpipe_page_formpipe-integration';

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'menu' ], 9, 0 );
		add_action( 'admin_post_formpipe_save',    [ $this, 'handle_save' ] );
		add_action( 'admin_post_formpipe_copy',    [ $this, 'handle_copy' ] );
		add_action( 'admin_post_formpipe_delete',  [ $this, 'handle_delete' ] );
		add_action( 'admin_post_formpipe_settings',[ $this, 'handle_settings' ] );
		add_action( 'admin_enqueue_scripts',       [ $this, 'enqueue' ], 10, 1 );
		add_filter( 'set_screen_option_' . FormListTable::PER_PAGE, [ $this, 'save_screen_option' ], 10, 3 );
	}

	public function menu(): void {
		add_menu_page(
			__( 'Forms', 'formpipe' ),
			__( 'Forms', 'formpipe' ),
			'formpipe_read_forms',
			'formpipe',
			[ $this, 'render' ],
			'dashicons-email-alt',
			26
		);

		add_submenu_page(
			'formpipe',
			__( 'Forms', 'formpipe' ),
			__( 'All Forms', 'formpipe' ),
			'formpipe_read_forms',
			'formpipe',
			[ $this, 'render' ]
		);

		$addnew = add_submenu_page(
			'formpipe',
			__( 'Add Form', 'formpipe' ),
			__( 'Add New', 'formpipe' ),
			'formpipe_edit_forms',
			'formpipe-new',
			[ $this, 'render_editor' ]
		);
		add_action( 'load-' . $addnew, [ $this, 'load_editor_screen' ] );

		$integ = add_submenu_page(
			'formpipe',
			__( 'Integration', 'formpipe' ),
			__( 'Integration', 'formpipe' ),
			'formpipe_manage_integration',
			'formpipe-integration',
			[ $this, 'render_integration' ]
		);
		add_action( 'load-' . $integ, [ $this, 'load_integration_screen' ] );

		do_action( 'formpipe_admin_menu' );
	}

	public function enqueue( string $hook ): void {
		$ours = [ self::SCREEN_ID, self::EDIT_SCREEN, self::INTEG_SCREEN ];
		if ( ! in_array( $hook, $ours, true ) ) {
			return;
		}

		wp_enqueue_style(
			'formpipe-admin',
			plugins_url( 'assets/admin.css', FORMPIPE_FILE ),
			[],
			FORMPIPE_VERSION
		);

		$asset = include FORMPIPE_DIR . '/assets/admin/index.asset.php';

		wp_enqueue_script(
			'formpipe-admin',
			plugins_url( 'assets/admin/index.js', FORMPIPE_FILE ),
			$asset['dependencies'] ?? [],
			$asset['version'] ?? FORMPIPE_VERSION,
			true
		);

		wp_set_script_translations( 'formpipe-admin', 'formpipe' );

		wp_add_inline_script(
			'formpipe-admin',
			'var formpipe = ' . wp_json_encode( [
				'api'      => rest_url( 'formpipe/v1/' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'restBase' => esc_url_raw( rest_url() ),
			] ) . ';',
			'before'
		);
	}

	public function load_editor_screen(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_editor_tag_generator' ] );
	}

	public function enqueue_editor_tag_generator(): void {
		wp_enqueue_script( 'formpipe-tag-generator' );
	}

	public function load_integration_screen(): void {
		// Future: context help.
	}

	public function render(): void {
		$table = new FormListTable();
		$table->prepare_items();
		require FORMPIPE_DIR . '/admin/views/list.php';
	}

	public function render_editor(): void {
		$form = $this->resolve_form();
		if ( $form === null ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Form not found.', 'formpipe' ) . '</p></div>';
			return;
		}

		Form::set_current( $form );
		FormTagsManager::reset();
		FormTagsManager::scan( $form->template );

		$active_tab = isset( $_GET['active-tab'] ) ? sanitize_key( wp_unslash( $_GET['active-tab'] ) ) : 'form';

		require FORMPIPE_DIR . '/admin/views/editor.php';
	}

	public function render_integration(): void {
		if ( ! Integration::get_instance()->exists() ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Integration', 'formpipe' ) . '</h1>';
			echo '<p>' . esc_html__( 'No integrations are currently available.', 'formpipe' ) . '</p></div>';
			return;
		}

		require FORMPIPE_DIR . '/admin/views/integration.php';
	}

	public function handle_save(): void {
		$id   = (int) ( $_POST['post_ID'] ?? 0 );
		$form = $this->resolve_form_for_save( $id );

		if ( $form === null ) {
			wp_die( esc_html__( 'Invalid form.', 'formpipe' ) );
		}

		check_admin_referer( 'formpipe_save_' . $form->id );

		if ( ! current_user_can( 'formpipe_edit_form', $form->id ) ) {
			wp_die( esc_html__( 'You are not allowed to edit this form.', 'formpipe' ) );
		}

		$form->set_title( (string) ( $_POST['post_title'] ?? '' ) );
		$form->set_locale( (string) ( $_POST['formpipe-locale'] ?? '' ) );
		$form->template = (string) ( $_POST['wpcf7-form'] ?? '' );

		$mail = (array) ( $_POST['mail'] ?? [] );
		$mail_2 = (array) ( $_POST['mail-2'] ?? [] );

		$form->mail   = $this->sanitize_mail( $mail, $form->mail );
		$form->mail_2 = $this->sanitize_mail( $mail_2, $form->mail_2 );

		$form->messages = $this->sanitize_messages( (array) ( $_POST['messages'] ?? [] ) );
		$form->settings = $this->parse_settings( (string) ( $_POST['additional-settings'] ?? '' ) );

		$form->save();

		wp_safe_redirect( admin_url( 'admin.php?page=formpipe&post=' . $form->id . '&message=saved' ) );
		exit;
	}

	public function handle_copy(): void {
		$id = (int) ( $_POST['post_ID'] ?? 0 );
		check_admin_referer( 'formpipe_copy_' . $id );

		if ( ! current_user_can( 'formpipe_edit_form', $id ) ) {
			wp_die( esc_html__( 'You are not allowed to copy this form.', 'formpipe' ) );
		}

		$form = Form::from_post( $id );
		if ( $form === null ) {
			wp_safe_redirect( admin_url( 'admin.php?page=formpipe' ) );
			exit;
		}

		$copy   = $form->copy();
		$new_id = $copy->save();

		wp_safe_redirect( admin_url( 'admin.php?page=formpipe&post=' . $new_id . '&message=created' ) );
		exit;
	}

	public function handle_delete(): void {
		$ids = (array) ( $_POST['post_ID'] ?? $_REQUEST['post'] ?? [] );
		check_admin_referer( 'bulk-formpipe-forms' );

		$deleted = 0;
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( ! current_user_can( 'formpipe_delete_form', $id ) ) {
				continue;
			}
			$form = Form::from_post( $id );
			if ( $form !== null && $form->delete() ) {
				$deleted++;
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=formpipe&message=deleted&count=' . $deleted ) );
		exit;
	}

	public function handle_settings(): void {
		check_admin_referer( 'formpipe_settings' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'formpipe' ) );
		}

		$options = (array) ( $_POST['formpipe_options'] ?? [] );

		$clean = [
			'load_js'  => ! empty( $options['load_js'] ),
			'load_css' => ! empty( $options['load_css'] ),
		];

		update_option( 'formpipe_options', $clean );

		wp_safe_redirect( admin_url( 'admin.php?page=formpipe-integration&message=saved' ) );
		exit;
	}

	public function save_screen_option( $result, $option, $value ): bool {
		return (string) $value;
	}

	private function resolve_form(): ?Form {
		$id = (int) ( $_GET['post'] ?? 0 );

		if ( $id > 0 ) {
			return Form::from_post( $id );
		}

		return Form::blank();
	}

	private function resolve_form_for_save( int $id ): ?Form {
		if ( $id > 0 ) {
			return Form::from_post( $id );
		}
		return Form::blank();
	}

	private function sanitize_mail( array $input, array $existing ): array {
		$out = $existing;

		$out['active']     = ! empty( $input['active'] );
		$out['recipient']  = sanitize_email( (string) ( $input['recipient'] ?? '' ) );
		$out['sender']     = sanitize_text_field( (string) ( $input['sender'] ?? '' ) );
		$out['subject']    = sanitize_text_field( (string) ( $input['subject'] ?? '' ) );
		$out['body']       = wp_kses_post( (string) ( $input['body'] ?? '' ) );
		$out['additional_headers'] = sanitize_textarea_field( (string) ( $input['additional_headers'] ?? '' ) );
		$out['attachments'] = sanitize_textarea_field( (string) ( $input['attachments'] ?? '' ) );
		$out['use_html']   = ! empty( $input['use_html'] );
		$out['exclude_blank'] = ! empty( $input['exclude_blank'] );

		return $out;
	}

	private function sanitize_messages( array $input ): array {
		$out = [];
		foreach ( $input as $key => $value ) {
			$out[ sanitize_key( (string) $key ) ] = sanitize_text_field( (string) $value );
		}
		return $out;
	}

	private function parse_settings( string $raw ): array {
		$out = [];
		foreach ( preg_split( '/\r?\n/', $raw ) ?: [] as $line ) {
			if ( preg_match( '/^([a-zA-Z0-9_\-]+):(.*)$/', trim( $line ), $m ) ) {
				$out[ $m[1] ] = trim( $m[2] );
			}
		}
		return $out;
	}
}

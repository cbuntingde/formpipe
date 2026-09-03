<?php
namespace FormPipe;

defined( 'ABSPATH' ) || exit;

/**
 * The Form value object.
 *
 * Storage:
 *   - title, locale     -> post_title, post_meta
 *   - template          -> post_content (searchable, used by shortcode resolution)
 *   - mail / mail_2     -> post_meta `_<name>`
 *   - messages          -> post_meta `_messages`
 *   - settings          -> post_meta `_settings`
 *
 * One instance per request is cached statically as "current" so that
 * shortcodes / blocks / mail-tag replacement can find it.
 */
final class Form {

	public const POST_TYPE = PostType::POST_TYPE;

	private static ?Form $current = null;

	/** @var int|null */
	public $id = null;
	public string $title = '';
	public string $locale = '';
	public string $hash = '';
	public string $template = '';

	/** @var array<string,mixed> */
	public array $mail = [];

	/** @var array<string,mixed> */
	public array $mail_2 = [];

	/** @var array<string,string> */
	public array $messages = [];

	/** @var array<string,string> */
	public array $settings = [];

	/** @var array<string,mixed> */
	private array $shortcode_atts = [];

	private static int $unit_tag_counter = 0;

	public static function get_current(): ?Form {
		return self::$current;
	}

	public static function set_current( ?Form $form ): void {
		self::$current = $form;
	}

	public static function from_post( $post ): ?Form {
		if ( $post instanceof self ) {
			return $post;
		}
		if ( ! $post ) {
			return null;
		}

		$post = get_post( $post );
		if ( ! $post || $post->post_type !== self::POST_TYPE ) {
			return null;
		}

		$form              = new self();
		$form->id          = (int) $post->ID;
		$form->title       = (string) $post->post_title;
		$form->hash        = (string) get_post_meta( $post->ID, '_hash', true );
		$form->locale      = (string) get_post_meta( $post->ID, '_locale', true );
		$form->template    = (string) $post->post_content;
		$form->mail        = self::normalize_mail( (array) get_post_meta( $post->ID, '_mail', true ), false );
		$form->mail_2      = self::normalize_mail( (array) get_post_meta( $post->ID, '_mail_2', true ), true );
		$form->messages    = self::normalize_messages( (array) get_post_meta( $post->ID, '_messages', true ) );
		$form->settings    = self::parse_settings( (string) get_post_meta( $post->ID, '_settings', true ) );

		return $form;
	}

	public static function blank(): self {
		$form          = new self();
		$form->title   = __( 'Untitled', 'formpipe' );
		$form->locale  = '';
		$form->mail    = self::normalize_mail( [], false );
		$form->mail_2  = self::normalize_mail( [], true );

		return $form;
	}

	public function initial(): bool {
		return empty( $this->id );
	}

	public function id(): int {
		return (int) $this->id;
	}

	public function title(): string {
		return $this->title;
	}

	public function set_title( string $title ): void {
		$title = wp_strip_all_tags( $title, true );
		$title = formpipe_strip_whitespaces( $title );
		if ( $title === '' ) {
			$title = __( 'Untitled', 'formpipe' );
		}
		$this->title = $title;
	}

	public function locale(): string {
		if ( formpipe_is_rtl( $this->locale ) ) {
			return $this->locale;
		}
		return formpipe_is_rtl_locale( $this->locale ) ? $this->locale : '';
	}

	public function set_locale( string $locale ): void {
		$locale = trim( $locale );
		$this->locale = $locale === '' ? 'en_US' : $locale;
	}

	public function hash( int $length = 7 ): string {
		return substr( $this->hash, 0, max( 1, $length ) );
	}

	public function prop( string $name ) {
		$props = [
			'form'      => $this->template,
			'mail'      => $this->mail,
			'mail_2'    => $this->mail_2,
			'messages'  => $this->messages,
			'settings'  => $this->settings,
		];

		if ( array_key_exists( $name, $props ) ) {
			return $props[ $name ];
		}

		return null;
	}

	public function get_properties(): array {
		return [
			'form'      => $this->template,
			'mail'      => $this->mail,
			'mail_2'    => $this->mail_2,
			'messages'  => $this->messages,
			'settings'  => $this->settings,
		];
	}

	public function set_properties( array $properties ): void {
		if ( array_key_exists( 'form', $properties ) ) {
			$this->template = (string) $properties['form'];
		}
		foreach ( [ 'mail', 'mail_2' ] as $k ) {
			if ( isset( $properties[ $k ] ) && is_array( $properties[ $k ] ) ) {
				$this->{$k} = array_merge( $this->{$k}, $properties[ $k ] );
			}
		}
		foreach ( [ 'messages', 'settings' ] as $k ) {
			if ( isset( $properties[ $k ] ) && is_array( $properties[ $k ] ) ) {
				$this->{$k} = $properties[ $k ];
			}
		}
	}

	public function set_shortcode_atts( array $atts ): void {
		$this->shortcode_atts = $atts;
	}

	public function shortcode_attr( string $name ): string {
		return isset( $this->shortcode_atts[ $name ] ) ? (string) $this->shortcode_atts[ $name ] : '';
	}

	public function has_setting( string $name ): bool {
		return array_key_exists( $name, $this->settings );
	}

	public function setting( string $name, ?string $default = null ): ?string {
		return $this->settings[ $name ] ?? $default;
	}

	public function setting_bool( string $name ): bool {
		return in_array( $this->settings[ $name ] ?? '', [ 'on', 'true', '1' ], true );
	}

	public function is_demo_mode(): bool {
		return $this->setting_bool( 'demo_mode' );
	}

	public function is_true( string $name ): bool {
		return in_array(
			$this->setting( $name ),
			[ 'on', 'true', '1' ],
			true
		);
	}

	public function is_false( string $name ): bool {
		return in_array(
			$this->setting( $name ),
			[ 'off', 'false', '0' ],
			true
		);
	}

	public function nonce_is_active(): bool {
		$active = (bool) apply_filters( 'formpipe_verify_nonce', false, $this );
		if ( $this->is_true( 'subscribers_only' ) ) {
			$active = true;
		}
		return $active;
	}

	public function save(): int {
		$postarr = [
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $this->title,
			'post_content' => $this->template,
			'post_name'    => sanitize_title( $this->title ),
		];

		if ( $this->initial() ) {
			$id = wp_insert_post( wp_slash( $postarr ), true );
		} else {
			$postarr['ID'] = (int) $this->id;
			$id = wp_update_post( wp_slash( $postarr ), true );
		}

		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}

		$this->id = (int) $id;

		update_post_meta( $id, '_mail',     $this->mail );
		update_post_meta( $id, '_mail_2',   $this->mail_2 );
		update_post_meta( $id, '_messages', $this->messages );
		update_post_meta( $id, '_settings', $this->dump_settings() );
		update_post_meta( $id, '_locale',   $this->locale );

		if ( ! $this->hash ) {
			$this->hash = formpipe_form_hash( $this->id );
			update_post_meta( $id, '_hash', $this->hash );
		}

		do_action( 'formpipe_after_save', $this );
		return $this->id;
	}

	public function copy(): self {
		$copy          = new self();
		$copy->title   = $this->title . ' ' . __( '(copy)', 'formpipe' );
		$copy->locale  = $this->locale;
		$copy->template= $this->template;
		$copy->mail    = $this->mail;
		$copy->mail_2  = $this->mail_2;
		$copy->messages= $this->messages;
		$copy->settings= $this->settings;
		return $copy;
	}

	public function delete(): bool {
		if ( $this->initial() ) {
			return false;
		}
		$ok = wp_delete_post( $this->id, true );
		if ( $ok ) {
			do_action( 'formpipe_after_delete', $this );
			$this->id = null;
		}
		return (bool) $ok;
	}

	public function shortcode( array $options = [] ): string {
		$options = wp_parse_args( $options, [ 'use_old_format' => false ] );
		$title   = str_replace( [ '"', '[', ']' ], '', $this->title );

		if ( $options['use_old_format'] ) {
			$old = (int) get_post_meta( $this->id, '_old_cf7_unit_id', true );
			if ( $old ) {
				return sprintf( '[contact-form %1$d "%2$s"]', $old, $title );
			}
			return '';
		}

		return sprintf( '[formpipe id="%1$s" title="%2$s"]', $this->hash(), $title );
	}

	public function render(): string {
		FormTagsManager::reset();
		self::set_current( $this );

		// Run the scanner once for tags; the renderer will use the cached
		// result when calling replace_all.
		FormTagsManager::scan( $this->template );

		$body = FormTagsManager::replace_all( $this->template );
		$body = (string) apply_filters( 'formpipe_form_body', $body, $this );

		$unit_tag = $this->unit_tag();

		$hidden = '';
		$hids   = [
			'_formpipe'             => (string) $this->id,
			'_formpipe_version'     => FORMPIPE_VERSION,
			'_formpipe_locale'      => $this->locale(),
			'_formpipe_unit_tag'    => $unit_tag,
			'_formpipe_container'   => (string) ( get_the_ID() ?: 0 ),
			'_formpipe_posted_hash' => '',
			'_formpipe_rendered_at' => (string) time(),
			'_hp_' . $unit_tag      => '',
		];

		if ( $this->nonce_is_active() && is_user_logged_in() ) {
			$hids['_wpnonce'] = wp_create_nonce( 'formpipe_submit' );
		}

		foreach ( $hids as $name => $value ) {
			$hidden .= sprintf(
				'<input type="hidden" name="%1$s" value="%2$s" />',
				esc_attr( $name ),
				esc_attr( $value )
			);
		}

		$hidden = (string) apply_filters( 'formpipe_form_hidden_fields', $hidden, $this );

		$status = 'init';
		$message = '';
		$result = Submission::last_for( $this->id );

		if ( $result !== null && $result['unit_tag'] === $unit_tag ) {
			$status  = $result['status'];
			$message = $result['message'];
		}

		$atts = [
			'action'      => esc_url( formpipe_get_request_uri() . '#' . $unit_tag ),
			'method'      => 'post',
			'class'       => 'formpipe-form',
			'id'          => $unit_tag,
			'data-id'     => (string) $this->id,
			'data-status' => $status,
			'data-unit-tag'=> $unit_tag,
			'novalidate'  => true,
			'enctype'     => $this->has_file_field() ? 'multipart/form-data' : null,
		];

		$atts = (array) apply_filters( 'formpipe_form_atts', $atts, $this );

		$response = sprintf(
			'<div class="formpipe-response-output" role="status" aria-live="polite" aria-atomic="true">%s</div>',
			esc_html( $message )
		);
		$response = (string) apply_filters( 'formpipe_form_response_output', $response, '', $message, $this, $status );

		$screen_reader = $this->screen_reader_response( $status, $message );

		return sprintf(
			'<div class="formpipe" data-id="%1$d" data-status="%2$s">'
				. '%3$s'
				. '<form %4$s>%5$s%6$s</form>'
			. '</div>',
			$this->id,
			esc_attr( $status ),
			$screen_reader,
			format_atts( $atts ),
			$hidden,
			$body
		);
	}

	public function unit_tag(): string {
		$id = $this->initial() ? 0 : (int) $this->id;
		self::$unit_tag_counter++;
		return sprintf( 'fp%d-f%d-o%d', $id, $id, self::$unit_tag_counter );
	}

	public function has_file_field(): bool {
		return FormTagsManager::supports_feature( 'file', 'file' )
			|| FormTagsManager::supports_feature( 'file*', 'file' );
	}

	public function submit( array $posted ): array {
		$submission = new Submission( $this, $posted );
		$result     = $submission->run();

		Submission::store_last( $this->id, $result + [ 'unit_tag' => formpipe_superglobal_post( '_formpipe_unit_tag' ) ] );

		return $result;
	}

	private function screen_reader_response( string $status, string $message ): string {
		return sprintf(
			'<div class="formpipe-screen-reader-response" aria-live="assertive">'
				. '<p role="status">%s</p>'
			. '</div>',
			esc_html( $message )
		);
	}

	private static function normalize_mail( array $mail, bool $inactive ): array {
		$defaults = [
			'active'            => ! $inactive,
			'recipient'         => (string) get_option( 'admin_email' ),
			'sender'            => sprintf( '%s <%s>', wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ), (string) get_option( 'admin_email' ) ),
			'subject'           => sprintf( '[%s] %s', wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ), __( 'New submission', 'formpipe' ) ),
			'body'              => '',
			'additional_headers'=> 'Reply-To: [your-email]',
			'attachments'       => '',
			'use_html'          => false,
			'exclude_blank'     => false,
		];

		return array_merge( $defaults, $mail );
	}

	private static function normalize_messages( array $messages ): array {
		$defaults = [
			'mail_sent_ok'        => __( 'Thank you, your message has been sent.', 'formpipe' ),
			'mail_sent_ng'        => __( 'Failed to send your message. Please try later or contact the administrator.', 'formpipe' ),
			'validation_error'    => __( 'One or more fields have an error. Please check and try again.', 'formpipe' ),
			'spam'                => __( 'Failed to send your message. Please try later or contact the administrator.', 'formpipe' ),
			'accept_terms'        => __( 'Please accept the terms to proceed.', 'formpipe' ),
			'invalid_required'    => __( 'Please fill in the required field.', 'formpipe' ),
			'invalid_too_long'    => __( 'This field has too long input.', 'formpipe' ),
			'invalid_too_short'   => __( 'This field has too short input.', 'formpipe' ),
		];

		$out = [];
		foreach ( $defaults as $key => $default ) {
			$out[ $key ] = isset( $messages[ $key ] ) && $messages[ $key ] !== ''
				? (string) $messages[ $key ]
				: $default;
		}

		return $out + $messages;
	}

	private static function parse_settings( string $raw ): array {
		$out = [];
		foreach ( preg_split( '/\r?\n/', $raw ) ?: [] as $line ) {
			if ( preg_match( '/^([a-zA-Z0-9_\-]+):(.*)$/', trim( $line ), $m ) ) {
				$out[ $m[1] ] = trim( $m[2] );
			}
		}
		return $out;
	}

	private function dump_settings(): string {
		$lines = [];
		foreach ( $this->settings as $k => $v ) {
			$lines[] = $k . ':' . $v;
		}
		return implode( "\n", $lines );
	}
}

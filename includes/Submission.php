<?php
namespace FormPipe;

defined( 'ABSPATH' ) || exit;

/**
 * Submission lifecycle. One instance per POST.
 *
 * Stages:
 *   1. setup_data      sanitize posted values.
 *   2. handle_uploads  move uploaded files to a tmp dir.
 *   3. validate        run formpipe_validate_<type> filters per tag.
 *   4. check_accepted  if any acceptance* tag is required.
 *   5. check_spam      honeypot + posted-data hash + filter.
 *   6. mail            send primary mail, then mail_2 on success.
 *
 * On __destruct, owned uploaded files are removed.
 */
final class Submission {

	private static ?Submission $current = null;
	/** @var array<int,array{unit_tag:string,status:string,message:string,errors:array}> */
	private static array $last_results = [];

	public static function get_instance(): ?self {
		return self::$current;
	}

	public static function store_last( int $form_id, array $result ): void {
		self::$last_results[ $form_id ] = $result;
	}

	public static function last_for( int $form_id ): ?array {
		return self::$last_results[ $form_id ] ?? null;
	}

	private Form $form;
	/** @var array<string,mixed> */
	private array $posted;
	/** @var array<string,string> */
	private array $specials = [];
	/** @var array<string,string[]> field name => saved file paths */
	private array $uploads = [];

	public function __construct( Form $form, array $posted ) {
		$this->form   = $form;
		$this->posted = $this->sanitize( $posted );
		self::$current = $this;
	}

	public function __destruct() {
		$this->cleanup_uploads();
	}

	public function get_form(): Form {
		return $this->form;
	}

	public function get_posted_data( string $name = '' ) {
		if ( $name === '' ) {
			return $this->posted;
		}
		return $this->posted[ $name ] ?? null;
	}

	public function get_special( string $name ): ?string {
		return $this->specials[ $name ] ?? null;
	}

	public function get_uploads(): array {
		return $this->uploads;
	}

	public function add_consent( string $name, string $conditions ): void {
		$this->consent[ $name ] = $conditions;
	}

	public function get_consent(): array {
		return $this->consent;
	}

	/** @var array<string,string> */
	private array $consent = [];

	/** @return array{status:string,message:string,errors:array} */
	public function run(): array {
		$upload_errors = $this->handle_uploads();
		$validation    = $this->validate( $upload_errors );

		if ( ! $validation->is_valid() ) {
			$msg = $this->message_for_status( 'validation_error', $validation );
			return [
				'status'  => 'validation_failed',
				'message' => $msg,
				'errors'  => $validation->get_errors(),
			];
		}

		if ( ! $this->check_accepted() ) {
			return [
				'status'  => 'acceptance_missing',
				'message' => $this->message( 'accept_terms' ),
				'errors'  => [],
			];
		}

		if ( ! empty( $upload_errors ) ) {
			$validation->add_error( key( $upload_errors ), reset( $upload_errors ) );
			return [
				'status'  => 'validation_failed',
				'message' => $this->message( 'validation_error' ),
				'errors'  => $validation->get_errors(),
			];
		}

		if ( $this->is_spam() ) {
			return [
				'status'  => 'spam',
				'message' => $this->message( 'spam' ),
				'errors'  => [],
			];
		}

		$sent = Mail::send(
			$this->form->mail,
			'mail',
			$validation->get_values(),
			$this->specials,
			$this->uploads
		);

		if ( ! $sent ) {
			return [
				'status'  => 'mail_failed',
				'message' => $this->message( 'mail_sent_ng' ),
				'errors'  => [],
			];
		}

		if ( ! empty( $this->form->mail_2['active'] ) ) {
			Mail::send(
				$this->form->mail_2,
				'mail_2',
				$validation->get_values(),
				$this->specials,
				$this->uploads
			);
		}

		do_action( 'formpipe_mail_sent', $this->form, $this );

		return [
			'status'  => 'mail_sent',
			'message' => $this->message( 'mail_sent_ok' ),
			'errors'  => [],
		];
	}

	/** @return array<string,string> field => reason */
	private function handle_uploads(): array {
		$errors = [];

		if ( empty( $_FILES ) || ! is_array( $_FILES ) ) {
			return $errors;
		}

		foreach ( FormTagsManager::last_scanned() as $tag ) {
			if ( empty( $tag->name ) || empty( $tag->features['file'] ) ) {
				continue;
			}

			if ( empty( $_FILES[ $tag->name ]['name'] ) ) {
				continue;
			}

			$limit_raw = (string) $tag->get_option( 'limit', '\d+[kKmM]?[bB]?', true );
			$limit     = $limit_raw !== '' ? formpipe_parse_limit( $limit_raw, (int) wp_max_upload_size() ) : (int) wp_max_upload_size();

			$options = [
				'tag'       => $tag,
				'limit'     => $limit,
				'filetypes' => (string) $tag->get_option( 'filetypes', '[A-Za-z0-9_,.\-/* ]+', true ),
			];

			$paths = formpipe_handle_upload( $_FILES[ $tag->name ], $options );

			if ( is_wp_error( $paths ) ) {
				$errors[ $tag->name ] = $paths->get_error_message();
				continue;
			}

			if ( $paths !== [] ) {
				$this->uploads[ $tag->name ] = $paths;
				$this->posted[ $tag->name ]  = implode( ', ', array_map( 'basename', $paths ) );
			}
		}

		return $errors;
	}

	private function validate( array $upload_errors ): Validation {
		$validation = new Validation();
		$values     = [];

		foreach ( FormTagsManager::last_scanned() as $tag ) {
			if ( $tag->name === '' ) {
				continue;
			}

			if ( isset( $upload_errors[ $tag->name ] ) ) {
				$validation->add_error( $tag->name, $upload_errors[ $tag->name ] );
				continue;
			}

			$raw   = $this->posted[ $tag->name ] ?? '';
			$value = $raw;

			/**
			 * Filter the raw value for a tag type before validation.
			 */
			$value = apply_filters( "formpipe_posted_{$tag->type}", $value, $raw, $tag );

			if ( $tag->is_required() && $this->is_empty( $value ) ) {
				$validation->add_error( $tag->name, $this->form->messages['invalid_required'] ?? __( 'Please fill in the required field.', 'formpipe' ) );
				continue;
			}

			$validation = apply_filters( "formpipe_validate_{$tag->type}", $validation, $tag, $value );

			$values[ $tag->name ] = $value;
		}

		$validation = apply_filters( 'formpipe_validate', $validation, FormTagsManager::last_scanned() );

		$validation->set_values( $values );

		$this->specials = (array) apply_filters( 'formpipe_special_values', [
			'_remote_ip'      => formpipe_anonymize_ip( $this->remote_ip() ),
			'_user_agent'     => (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
			'_url'            => $this->referer(),
			'_date'           => wp_date( 'Y-m-d' ),
			'_time'           => wp_date( 'H:i' ),
			'_invalid_fields' => (string) count( $validation->get_errors() ),
			'_post_id'        => (string) ( get_the_ID() ?: 0 ),
			'_post_title'     => get_the_title() ?: '',
			'_post_url'       => get_permalink() ?: '',
			'_form_title'     => $this->form->title(),
			'_form_id'        => (string) $this->form->id(),
		], $this );

		return $validation;
	}

	private function check_accepted(): bool {
		$has_required = false;

		foreach ( FormTagsManager::last_scanned() as $tag ) {
			if ( $tag->basetype !== 'acceptance' ) {
				continue;
			}
			if ( $tag->is_required() ) {
				$has_required = true;
			}
			if ( empty( $this->posted[ $tag->name ] ) ) {
				return false;
			}
		}

		if ( ! $has_required && ! $this->form->setting_bool( 'acceptance' ) ) {
			return true;
		}

		return (bool) apply_filters( 'formpipe_check_accepted', true, $this );
	}

	private function is_spam(): bool {
		$skip = (bool) apply_filters( 'formpipe_skip_spam_check', false, $this );
		if ( $skip ) {
			return false;
		}

		// Honeypot.
		$unit_tag = (string) ( $_POST['_formpipe_unit_tag'] ?? '' );
		if ( $unit_tag !== '' && (string) ( $_POST[ '_hp_' . $unit_tag ] ?? '' ) !== '' ) {
			return true;
		}

		// Min 1s render time.
		$rendered = (int) ( $_POST['_formpipe_rendered_at'] ?? 0 );
		if ( $rendered > 0 && ( time() - $rendered ) < 1 ) {
			return true;
		}

		// Posted-data hash (replay window).
		$posted_hash = (string) ( $_POST['_formpipe_posted_hash'] ?? '' );
		if ( $posted_hash !== '' ) {
			$tick = (int) ceil( time() / ( MINUTE_IN_SECONDS / 2 ) );
			$expected = formpipe_posted_data_hash( $this->posted, (string) $tick, $unit_tag, $this->remote_ip() );
			$prev_tick = (string) ( $tick - 1 );
			$prev_expected = formpipe_posted_data_hash( $this->posted, $prev_tick, $unit_tag, $this->remote_ip() );

			if ( ! hash_equals( $expected, $posted_hash ) && ! hash_equals( $prev_expected, $posted_hash ) ) {
				return true;
			}
		}

		return (bool) apply_filters( 'formpipe_is_spam', false, $this );
	}

	private function cleanup_uploads(): void {
		foreach ( $this->uploads as $paths ) {
			foreach ( (array) $paths as $path ) {
				if ( is_string( $path ) && is_file( $path ) && formpipe_is_file_path_in_content_dir( $path ) ) {
					@unlink( $path );
				}
			}
		}
	}

	private function uploads_dir(): string {
		return wp_normalize_path( wp_upload_dir()['basedir'] . '/formpipe-tmp' );
	}

	/** @param array<string,mixed> $in */
	private function sanitize( array $in ): array {
		$out = [];

		foreach ( $in as $k => $v ) {
			$k = (string) $k;

			if ( str_starts_with( $k, '_' ) && ! str_starts_with( $k, '_hp_' ) && $k !== '_formpipe_rendered_at' ) {
				continue;
			}

			if ( is_array( $v ) ) {
				$v = array_map( static function ( $i ) {
					return is_string( $i ) ? formpipe_sanitize_value( $i ) : $i;
				}, $v );
			} elseif ( is_string( $v ) ) {
				$v = formpipe_sanitize_value( $v );
			}

			$out[ $k ] = $v;
		}

		return $out;
	}

	private function message( string $key ): string {
		return (string) ( $this->form->messages[ $key ] ?? '' );
	}

	private function message_for_status( string $key, Validation $validation ): string {
		$msg = (string) ( $this->form->messages[ $key ] ?? __( 'Please correct the highlighted fields.', 'formpipe' ) );
		return (string) apply_filters( 'formpipe_validation_message', $msg, $validation );
	}

	private function is_empty( $value ): bool {
		if ( is_string( $value ) ) {
			return trim( $value ) === '';
		}
		if ( is_array( $value ) ) {
			return $value === [];
		}
		return $value === null;
	}

	private function remote_ip(): string {
		$ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	private function referer(): string {
		$home = home_url();
		$ref  = (string) ( $_SERVER['HTTP_REFERER'] ?? '' );
		if ( $ref !== '' && str_starts_with( $ref, $home ) ) {
			return esc_url_raw( $ref );
		}
		return esc_url_raw( $home . ( $_SERVER['REQUEST_URI'] ?? '/' ) );
	}
}

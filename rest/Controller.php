<?php
namespace FormPipe;

defined( 'ABSPATH' ) || exit;

/**
 * REST API for FormPipe.
 *
 * Routes:
 *   GET    /forms                     list
 *   POST   /forms                     create
 *   GET    /forms/{id}                get
 *   PUT    /forms/{id}                update
 *   DELETE /forms/{id}                delete
 *   POST   /forms/{id}/feedback       submit (form front-end ajax)
 *   GET    /forms/{id}/schema         SWV-style schema (placeholder)
 *   GET    /forms/{id}/refill         refill response (placeholder)
 */
final class Controller {

	public const NS = 'formpipe/v1';

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'routes' ] );
	}

	public function routes(): void {
		register_rest_route( self::NS, '/forms', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'list_forms' ],
				'permission_callback' => static fn() => current_user_can( 'formpipe_read_forms' ),
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_form' ],
				'permission_callback' => static fn() => current_user_can( 'formpipe_edit_forms' ),
			],
		] );

		register_rest_route( self::NS, '/forms/(?P<id>\d+)', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_form' ],
				'permission_callback' => static function ( $req ) {
					return current_user_can( 'formpipe_read_form', (int) $req['id'] );
				},
			],
			[
				'methods'             => 'PUT,PATCH',
				'callback'            => [ $this, 'update_form' ],
				'permission_callback' => static function ( $req ) {
					return current_user_can( 'formpipe_edit_form', (int) $req['id'] );
				},
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'delete_form' ],
				'permission_callback' => static function ( $req ) {
					return current_user_can( 'formpipe_delete_form', (int) $req['id'] );
				},
			],
		] );

		register_rest_route( self::NS, '/forms/(?P<id>\d+)/feedback', [
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'submit_feedback' ],
				'permission_callback' => [ $this, 'permission_feedback' ],
				'args'                => [
					'_formpipe_unit_tag' => [
						'required' => true,
						'type'     => 'string',
					],
				],
			],
		] );

		register_rest_route( self::NS, '/forms/(?P<id>\d+)/schema', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_schema' ],
			'permission_callback' => [ $this, 'permission_schema_refill' ],
		] );

		register_rest_route( self::NS, '/forms/(?P<id>\d+)/refill', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_refill' ],
			'permission_callback' => [ $this, 'permission_schema_refill' ],
		] );
	}

	public function list_forms( $req ) {
		$q = new \WP_Query( [
			'post_type'      => Form::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => (int) ( $req['per_page'] ?? 100 ),
		] );

		$out = [];
		foreach ( $q->posts as $p ) {
			$out[] = $this->serialize( $p );
		}
		return rest_ensure_response( $out );
	}

	public function get_form( $req ) {
		$form = Form::from_post( (int) $req['id'] );
		if ( $form === null ) {
			return new \WP_Error( 'formpipe_not_found', __( 'Form not found.', 'formpipe' ), [ 'status' => 404 ] );
		}
		return rest_ensure_response( $this->serialize_form( $form ) );
	}

	public function create_form( $req ) {
		if ( ! current_user_can( 'formpipe_edit_forms' ) ) {
			return new \WP_Error( 'formpipe_forbidden', __( 'You are not allowed to create forms.', 'formpipe' ), [ 'status' => 403 ] );
		}

		$form = Form::blank();
		$this->apply_payload( $form, (array) $req->get_params() );
		$form->save();

		return rest_ensure_response( $this->serialize_form( $form ) );
	}

	public function update_form( $req ) {
		$form = Form::from_post( (int) $req['id'] );
		if ( $form === null ) {
			return new \WP_Error( 'formpipe_not_found', __( 'Form not found.', 'formpipe' ), [ 'status' => 404 ] );
		}

		$this->apply_payload( $form, (array) $req->get_params() );
		$form->save();

		return rest_ensure_response( $this->serialize_form( $form ) );
	}

	public function delete_form( $req ) {
		$form = Form::from_post( (int) $req['id'] );
		if ( $form === null ) {
			return new \WP_Error( 'formpipe_not_found', __( 'Form not found.', 'formpipe' ), [ 'status' => 404 ] );
		}
		if ( ! $form->delete() ) {
			return new \WP_Error( 'formpipe_cannot_delete', __( 'Could not delete form.', 'formpipe' ), [ 'status' => 500 ] );
		}
		return rest_ensure_response( [ 'deleted' => true ] );
	}

	public function submit_feedback( $req ) {
		$form = Form::from_post( (int) $req['id'] );
		if ( $form === null ) {
			return new \WP_Error( 'formpipe_not_found', __( 'Form not found.', 'formpipe' ), [ 'status' => 404 ] );
		}

		$unit_tag = (string) ( $req['_formpipe_unit_tag'] ?? '' );
		if ( $unit_tag === '' || $unit_tag !== $form->unit_tag() ) {
			return new \WP_Error( 'formpipe_unit_tag', __( 'Invalid unit tag.', 'formpipe' ), [ 'status' => 400 ] );
		}

		$params = $req->get_params();
		$files  = $req->get_file_params();
		$merged = array_merge( $params, $files );

		$result = $form->submit( $merged );
		Submission::store_last( $form->id, $result + [ 'unit_tag' => $unit_tag ] );

		if ( ! empty( $result['errors'] ) ) {
			$result['invalid_fields'] = array_values( $result['errors'] );
		}

		return rest_ensure_response( $result );
	}

	public function permission_feedback(): bool|WP_Error {
		$ip = formpipe_remote_ip();

		// Per-IP rate limit. Default: 10 submissions / minute. Override via
		// the formpipe_feedback_rate_limit filter.
		$limit = (int) apply_filters( 'formpipe_feedback_rate_limit', 10, $ip );
		if ( formpipe_ip_rate_limited( $ip, $limit ) ) {
			return new \WP_Error(
				'formpipe_rate_limited',
				__( 'Too many submissions; please slow down.', 'formpipe' ),
				[ 'status' => 429 ]
			);
		}

		// Optional captcha / bot challenge: a companion module can hook
		// formpipe_captcha_verified and return true when its token validated.
		// Set formpipe_require_captcha true to fail closed when no module
		// is active (production hardening).
		if ( apply_filters( 'formpipe_require_captcha', false ) && ! formpipe_captcha_verified() ) {
			return new \WP_Error(
				'formpipe_captcha_required',
				__( 'Captcha verification required.', 'formpipe' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	public function permission_schema_refill(): bool {
		// Schema + refill are read-only and unit-tag-bound; allow public.
		// Per-IP rate limit still applies.
		$ip  = formpipe_remote_ip();
		$lim = (int) apply_filters( 'formpipe_schema_rate_limit', 60, $ip );
		return ! formpipe_ip_rate_limited( $ip, $lim );
	}

	public function get_schema( $req ) {
		$form = Form::from_post( (int) $req['id'] );
		if ( $form === null ) {
			return new \WP_Error( 'formpipe_not_found', __( 'Form not found.', 'formpipe' ), [ 'status' => 404 ] );
		}

		// Re-scan and emit a simple JSON-Schema-ish payload so the editor UI
		// has something to render against. Modules hook into this via the
		// `formpipe_form_schema` filter to add their own rules.
		FormTagsManager::reset();
		$tags = FormTagsManager::scan( $form->template );

		$properties = [];
		$required   = [];
		foreach ( $tags as $tag ) {
			if ( $tag->name === '' ) {
				continue;
			}

			$entry = [
				'type'       => $this->schema_type_for( $tag->basetype ),
				'title'      => $tag->name,
				'field_type' => $tag->basetype,
			];

			if ( $tag->basetype === 'select' || $tag->basetype === 'radio' || $tag->basetype === 'checkbox' ) {
				$entry['enum'] = $tag->values;
			}

			$properties[ $tag->name ] = $entry;

			if ( $tag->is_required() ) {
				$required[] = $tag->name;
			}
		}

		return rest_ensure_response( [
			'type'       => 'object',
			'properties' => $properties,
			'required'   => $required,
		] );
	}

	public function get_refill( $req ) {
		$out = (array) apply_filters( 'formpipe_refill_response', [], Form::from_post( (int) $req['id'] ) );
		return rest_ensure_response( $out );
	}

	private function schema_type_for( string $basetype ): string {
		return match ( $basetype ) {
			'email', 'url', 'tel', 'text'      => 'string',
			'number', 'range'                  => 'number',
			'date', 'time'                     => 'string',
			'checkbox'                         => 'array',
			'textarea', 'select', 'radio',
			'acceptance', 'file', 'quiz',
			'hidden', 'submit', 'captcha',
			'response', 'count'                => 'string',
			default                            => 'string',
		};
	}

	private function apply_payload( Form $form, array $payload ): void {
		if ( isset( $payload['title'] ) ) {
			$form->set_title( (string) $payload['title'] );
		}
		if ( isset( $payload['locale'] ) ) {
			$form->set_locale( (string) $payload['locale'] );
		}
		if ( isset( $payload['template'] ) ) {
			$form->template = (string) $payload['template'];
		}
		if ( isset( $payload['mail'] ) && is_array( $payload['mail'] ) ) {
			$form->mail = array_merge( $form->mail, $payload['mail'] );
		}
		if ( isset( $payload['mail_2'] ) && is_array( $payload['mail_2'] ) ) {
			$form->mail_2 = array_merge( $form->mail_2, $payload['mail_2'] );
		}
		if ( isset( $payload['messages'] ) && is_array( $payload['messages'] ) ) {
			$form->messages = $payload['messages'];
		}
		if ( isset( $payload['settings'] ) && is_array( $payload['settings'] ) ) {
			$form->settings = $payload['settings'];
		}
	}

	private function serialize( \WP_Post $post ): array {
		return [
			'id'    => (int) $post->ID,
			'title' => (string) $post->post_title,
			'hash'  => (string) get_post_meta( $post->ID, '_hash', true ),
		];
	}

	private function serialize_form( Form $form ): array {
		return [
			'id'        => $form->id(),
			'title'     => $form->title(),
			'locale'    => $form->locale(),
			'template'  => $form->template,
			'mail'      => $form->mail,
			'mail_2'    => $form->mail_2,
			'messages'  => $form->messages,
			'settings'  => $form->settings,
		];
	}
}

<?php
namespace FormPipe;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend wiring:
 *   - [formpipe id="123"] shortcode (with hash/title attributes)
 *   - parse_request handler that runs submit() for the matching form when
 *     the form posts to itself (nonce-checked when the form requires it)
 *   - asset enqueue (frontend JS + CSS)
 */
final class Frontend {

	public function register(): void {
		add_action( 'parse_request', [ $this, 'maybe_submit' ], 20, 0 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 10, 0 );
		add_shortcode( 'formpipe', [ $this, 'shortcode' ] );
		add_shortcode( 'contact-form', [ $this, 'shortcode_legacy' ] );
		add_filter( 'widget_text', 'do_shortcode' );
	}

	public function maybe_submit(): void {
		if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'POST' ) {
			return;
		}

		$id = (int) ( $_POST['_formpipe'] ?? 0 );
		if ( $id <= 0 ) {
			return;
		}

		$form = Form::from_post( $id );
		if ( $form === null ) {
			return;
		}

		$unit_tag = (string) ( $_POST['_formpipe_unit_tag'] ?? '' );
		if ( $unit_tag === '' ) {
			return;
		}

		// Nonce check when the form requires one (e.g. subscribers-only).
		if ( $form->nonce_is_active() && is_user_logged_in() ) {
			check_admin_referer( 'formpipe_submit' );
		}

		$result = $form->submit( (array) $_POST );
		Submission::store_last( $form->id, $result + [ 'unit_tag' => $unit_tag ] );

		$redirect = formpipe_get_request_uri() . '#' . $unit_tag;
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_safe_redirect
		wp_safe_redirect( $redirect );
		exit;
	}

	public function enqueue(): void {
		if ( ! is_singular() && ! is_front_page() && ! is_archive() ) {
			return;
		}

		wp_enqueue_style(
			'formpipe',
			plugins_url( 'assets/form.css', FORMPIPE_FILE ),
			[],
			FORMPIPE_VERSION
		);

		wp_enqueue_script(
			'formpipe',
			plugins_url( 'assets/form.js', FORMPIPE_FILE ),
			[],
			FORMPIPE_VERSION,
			true
		);
	}

	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			[
				'id'    => 0,
				'hash'  => '',
				'title' => '',
			],
			(array) $atts,
			'formpipe'
		);

		$form = $this->resolve_form( $atts );
		if ( $form === null ) {
			return '';
		}

		$form->set_shortcode_atts( $atts );

		return $form->render();
	}

	public function shortcode_legacy( $atts ): string {
		return $this->shortcode( $atts );
	}

	private function resolve_form( array $atts ): ?Form {
		if ( ! empty( $atts['id'] ) ) {
			return Form::from_post( (int) $atts['id'] );
		}

		if ( ! empty( $atts['hash'] ) ) {
			$posts = get_posts( [
				'post_type'      => Form::POST_TYPE,
				'posts_per_page' => 1,
				'meta_query'     => [
					[
						'key'     => '_hash',
						'value'   => '^' . preg_quote( (string) $atts['hash'], '' ),
						'compare' => 'REGEXP',
					],
				],
			] );
			return $posts ? Form::from_post( (int) $posts[0]->ID ) : null;
		}

		if ( ! empty( $atts['title'] ) ) {
			$posts = get_posts( [
				'post_type'      => Form::POST_TYPE,
				'title'          => (string) $atts['title'],
				'posts_per_page' => 1,
			] );
			return $posts ? Form::from_post( (int) $posts[0]->ID ) : null;
		}

		return null;
	}
}

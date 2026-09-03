<?php
namespace FormPipe;
/**
 * captcha module: integration-driven captcha slot.
 *
 * Integration modules (reCAPTCHA, Turnstile, hCaptcha) hook into
 * `formpipe_captcha_html` to render their widget here. The Submission's
 * `is_spam()` filter chain checks via the integrations' verify callbacks.
 *
 * Tag syntax: [captcha your-captcha]
 */

defined( 'ABSPATH' ) || exit;

add_action( 'formpipe_init', static function (): void {
	\FormPipe\FormTagsManager::register(
		'captcha',
		static function ( \FormPipe\FormTag $tag ): string {
			$html = (string) apply_filters( 'formpipe_captcha_html', '', $tag );
			return sprintf(
				'<span class="formpipe-control formpipe-captcha" data-name="%1$s">%2$s</span>',
				esc_attr( $tag->name ),
				$html
			);
		},
		[ 'name-attr' => true ]
	);
} );

add_filter( 'formpipe_posted_captcha', static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, 10, 1 );
add_filter( 'formpipe_posted_captcha*', static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, 10, 1 );

add_filter( 'formpipe_validate_captcha', static function ( \FormPipe\Validation $v, \FormPipe\FormTag $t, $value ): \FormPipe\Validation {
	if ( $value === '' || $value === null ) {
		$v->add_error( $t->name, __( 'Please complete the captcha.', 'formpipe' ) );
	}
	return $v;
}, 10, 3 );

add_filter( 'formpipe_validate_captcha*', static function ( \FormPipe\Validation $v, \FormPipe\FormTag $t, $value ): \FormPipe\Validation {
	if ( $value === '' || $value === null ) {
		$v->add_error( $t->name, __( 'Please complete the captcha.', 'formpipe' ) );
	}
	return $v;
}, 10, 3 );

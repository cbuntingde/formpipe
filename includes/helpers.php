<?php
namespace FormPipe;

 /**
  * Render an associative array of attributes into `name="value"` pairs.
 * Booleans render as the bare name; null/false are dropped.
 */
function format_atts( array $atts ): string {
	$booleans = [
		'checked', 'disabled', 'inert', 'multiple', 'readonly',
		'required', 'selected', 'novalidate', 'autofocus', 'autoplay',
		'controls', 'default', 'defer', 'hidden', 'loop', 'open',
	];

	$out = '';
	foreach ( $atts as $name => $value ) {
		$name = strtolower( (string) $name );

		if ( ! preg_match( '/^[a-z_:][a-z_:.0-9\-]*$/', $name ) ) {
			continue;
		}

		if ( $value === null || $value === false ) {
			continue;
		}

		if ( $value === true || ( in_array( $name, $booleans, true ) && $value === '' ) ) {
			$out .= ' ' . $name;
			continue;
		}

		$out .= ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
	}

	return trim( $out );
}

/**
 * Sanitized superglobal read.
 */
function formpipe_superglobal( string $which, string $key ) {
	$g = match ( $which ) {
		'get'     => $_GET,
		'post'    => $_POST,
		'request' => $_REQUEST,
		'server'  => $_SERVER,
		default   => null,
	};

	if ( ! is_array( $g ) || ! isset( $g[ $key ] ) ) {
		return null;
	}

	$value = $g[ $key ];

	if ( is_array( $value ) ) {
		return array_map( static function ( $v ) {
			return is_string( $v ) ? formpipe_sanitize_value( $v ) : $v;
		}, $value );
	}

	return formpipe_sanitize_value( $value );
}

function formpipe_superglobal_get( string $key, $default = '' ) {
	$v = formpipe_superglobal( 'get', $key );
	return $v ?? $default;
}

function formpipe_superglobal_post( string $key, $default = '' ) {
	$v = formpipe_superglobal( 'post', $key );
	return $v ?? $default;
}

function formpipe_superglobal_request( string $key, $default = '' ) {
	$v = formpipe_superglobal( 'request', $key );
	return $v ?? $default;
}

function formpipe_superglobal_server( string $key, $default = '' ) {
	$v = formpipe_superglobal( 'server', $key );
	return $v ?? $default;
}

function formpipe_sanitize_value( $value ) {
	if ( ! is_string( $value ) ) {
		return $value;
	}
	$value = wp_unslash( $value );
	$value = wp_check_invalid_utf8( $value );
	$value = wp_kses_no_null( $value );
	$value = formpipe_strip_whitespaces( $value );
	return $value;
}

/**
 * Strip Unicode whitespace from the start/end of a string.
 */
function formpipe_strip_whitespaces( string $value, string $side = 'both' ): string {
	$pattern = '\x09-\x0D\x20\x85\xA0\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}';

	if ( $side !== 'end' ) {
		$value = preg_replace( "/^[{$pattern}]+/u", '', $value );
	}
	if ( $side !== 'start' ) {
		$value = preg_replace( "/[{$pattern}]+$/u", '', $value );
	}

	return $value;
}

/**
 * Canonicalize for case/whitespace-insensitive comparison.
 */
function formpipe_canonicalize( string $value, $options = 'lower' ): string {
	if ( is_string( $options ) ) {
		$options = [ 'strto' => $options ];
	}

	$options = wp_parse_args( $options, [
		'strto'            => 'lower',
		'strip_separators' => false,
	] );

	$charset = 'UTF-8';
	$value   = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, $charset );

	if ( function_exists( 'mb_convert_kana' ) ) {
		$value = mb_convert_kana( $value, 'asKV', $charset );
	}

	if ( $options['strip_separators'] ) {
		$value = preg_replace( '/[\r\n\t ]+/', '', $value );
	} else {
		$value = preg_replace( '/[\r\n\t ]+/', ' ', $value );
	}

	if ( 'lower' === $options['strto'] ) {
		$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, $charset ) : strtolower( $value );
	} elseif ( 'upper' === $options['strto'] ) {
		$value = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $value, $charset ) : strtoupper( $value );
	}

	return formpipe_strip_whitespaces( $value );
}

/**
 * Sanitize a unit-tag string.
 */
function formpipe_sanitize_unit_tag( string $tag ): string {
	return preg_replace( '/[^A-Za-z0-9_\-]/', '', $tag );
}

/**
 * Build an action URL suitable for the form's POST target.
 */
function formpipe_get_request_uri(): string {
	static $uri = null;
	if ( $uri !== null ) {
		return $uri;
	}
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
	return $uri;
}

/**
 * Flatten an array to a comma-separated string.
 */
function formpipe_flatten( $value ): string {
	if ( is_array( $value ) ) {
		return implode( ', ', array_map( static function ( $v ) {
			return is_array( $v ) ? '' : (string) $v;
		}, $value ) );
	}
	return (string) $value;
}

/**
 * Parse a "limit:N[k|m]" string into bytes.
 */
function formpipe_parse_limit( string $raw, int $default = 1048576 ): int {
	if ( ! preg_match( '/^(\d+)([kKmM])?[bB]?$/', trim( $raw ), $m ) ) {
		return $default;
	}
	$value = (int) $m[1];
	return match ( strtolower( $m[2] ?? '' ) ) {
		'k'      => $value * 1024,
		'm'      => $value * 1024 * 1024,
		default  => $value,
	};
}

function formpipe_max_upload_size(): int {
	return (int) wp_max_upload_size();
}

/**
 * Sanitize a filename so it's not executable as a script.
 */
function formpipe_antiscript_file_name( string $name ): string {
	$name = wp_basename( $name );
	$name = preg_replace( '/[\r\n\t \-]+/', '-', $name );
	$name = preg_replace( '/[\pC\pZ]+/iu', '', $name );

	$parts = explode( '.', $name );
	if ( count( $parts ) < 2 ) {
		return $name;
	}

	$script_re = '/^(php|phtml|pl|py|rb|cgi|asp|aspx)\d?$/i';
	$ext       = array_pop( $parts );
	$stem      = array_shift( $parts );

	foreach ( $parts as $part ) {
		$stem .= '.' . ( preg_match( $script_re, $part ) ? $part . '_' : $part );
	}

	if ( preg_match( $script_re, $ext ) ) {
		return $stem . '.' . $ext . '_.txt';
	}

	return $stem . '.' . $ext;
}

/**
 * True if $path is under WP_CONTENT_DIR / UPLOADS / WP_TEMP_DIR.
 */
function formpipe_is_file_path_in_content_dir( string $path ): bool {
	if ( $path === '' ) {
		return false;
	}

	$real_path = realpath( $path );
	if ( $real_path === false ) {
		return false;
	}

	$candidates = [ WP_CONTENT_DIR ];
	if ( defined( 'UPLOADS' ) ) {
		$candidates[] = ABSPATH . UPLOADS;
	}
	if ( defined( 'WP_TEMP_DIR' ) ) {
		$candidates[] = WP_TEMP_DIR;
	}

	foreach ( $candidates as $dir ) {
		$real_dir = realpath( $dir );
		if ( $real_dir === false ) {
			continue;
		}
		if ( str_starts_with(
			wp_normalize_path( $real_path ),
			wp_normalize_path( trailingslashit( $real_dir ) )
		) ) {
			return true;
		}
	}

	return false;
}

function formpipe_is_email( string $value ): bool {
	$result = (bool) is_email( $value );
	return (bool) apply_filters( 'formpipe_is_email', $result, $value );
}

function formpipe_is_url( string $value ): bool {
	if ( ! preg_match( '#^https?://[^\s]+$#i', $value ) ) {
		return false;
	}
	$scheme = strtolower( (string) wp_parse_url( $value, PHP_URL_SCHEME ) );
	return in_array( $scheme, [ 'http', 'https' ], true );
}

function formpipe_is_tel( string $value ): bool {
	$digits = preg_replace( '/\D+/', '', $value );
	if ( strlen( $digits ) < 5 || strlen( $digits ) > 16 ) {
		return false;
	}
	return (bool) apply_filters( 'formpipe_is_tel', true, $value );
}

function formpipe_is_date( string $value ): bool {
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
		return false;
	}
	return (bool) checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
}

function formpipe_is_time( string $value ): bool {
	if ( ! preg_match( '/^(\d{2}):(\d{2})(?::(\d{2}))?$/', $value, $m ) ) {
		return false;
	}
	$h = (int) $m[1]; $mi = (int) $m[2]; $s = (int) ( $m[3] ?? 0 );
	return $h >= 0 && $h <= 23 && $mi >= 0 && $mi <= 59 && $s >= 0 && $s <= 59;
}

function formpipe_is_name( string $value ): bool {
	return (bool) preg_match( '/^[A-Za-z][-A-Za-z0-9_:.]*$/', $value );
}

function formpipe_is_rtl( string $locale ): bool {
	$rtl = [ 'ar', 'he', 'fa', 'ur', 'ps', 'dv', 'ha', 'ks', 'yi', 'ckb' ];
	return in_array( strtolower( substr( $locale, 0, 2 ) ), $rtl, true );
}

function formpipe_is_rtl_locale( string $locale ): bool {
	if ( $locale === '' ) {
		return false;
	}
	return formpipe_is_rtl( $locale ) || in_array( strtolower( $locale ), [ 'he_il', 'ar_ae', 'ar_sa' ], true );
}

/**
 * Build a posted-data hash for replay protection.
 *
 * Mirrors `buildPostedHash()` in assets/form.js exactly. The client sends
 * the result of a djb2 over `tick + '|' + unit_tag + '|' + sorted_kv`,
 * where each `kv` is `key + '=' + String(value).slice(0,256) + '\n'`.
 * The server must reproduce that byte stream so `hash_equals()` agrees.
 *
 * Iteration is over the un-sanitized POST: the JS side includes the
 * internal `_formpipe_*` keys (which `Submission::sanitize()` strips), so
 * hashing the sanitized array would never match.
 *
 * @param array<string,mixed> $posted Raw posted values (un-sanitized).
 * @param string             $tick   Server-side tick, in 30s units.
 * @param string             $unit_tag  Form unit-tag from the request.
 */
function formpipe_posted_data_hash( array $posted, string $tick, string $unit_tag ): string {
	$buf = $tick . '|' . $unit_tag . '|';

	$keys = array_map( 'strval', array_keys( $posted ) );
	// The hash field itself isn't part of the input — the client computes
	// the hash while the field is still empty in the DOM, then drops the
	// result into the field. Excluding the key on both sides keeps the
	// two iterations byte-identical.
	$keys = array_values( array_filter( $keys, static fn( $k ) => $k !== '_formpipe_posted_hash' ) );
	sort( $keys, SORT_STRING );

	foreach ( $keys as $k ) {
		$val = formpipe_flatten( $posted[ $k ] ?? '' );
		if ( strlen( $val ) > 256 ) {
			$val = substr( $val, 0, 256 );
		}
		$buf .= $k . '=' . $val . "\n";
	}

	return formpipe_djb2_hex( $buf );
}

/**
 * djb2 hash, hex-encoded (last 8 chars, matching the JS implementation).
 *
 * Client and server must produce byte-for-byte identical strings so
 * `hash_equals()` succeeds. Mirrors `simpleHash()` in assets/form.js.
 */
function formpipe_djb2_hex( string $s ): string {
	$h = 5381;
	$len = strlen( $s );
	for ( $i = 0; $i < $len; $i++ ) {
		$h = ( ( ( $h << 5 ) + $h ) ^ ord( $s[ $i ] ) ) & 0xFFFFFFFF;
	}
	return substr( sprintf( '%08x', $h ), -8 );
}

function formpipe_form_hash( int $form_id ): string {
	return substr( wp_hash( 'formpipe:' . $form_id ), 0, 7 );
}

function formpipe_debug(): bool {
	return defined( 'WP_DEBUG' ) && WP_DEBUG;
}

function formpipe_kses_allowed_html(): array {
	static $tags = null;

	if ( $tags === null ) {
		$tags = wp_kses_allowed_html( 'post' );

		$extras = [
			'button'   => [ 'disabled' => true, 'name' => true, 'type' => true, 'value' => true, 'class' => true, 'id' => true ],
			'fieldset' => [ 'disabled' => true, 'name' => true, 'class' => true ],
			'input'    => [ 'accept' => true, 'alt' => true, 'autocomplete' => true, 'capture' => true, 'checked' => true, 'disabled' => true, 'list' => true, 'max' => true, 'maxlength' => true, 'min' => true, 'minlength' => true, 'multiple' => true, 'name' => true, 'pattern' => true, 'placeholder' => true, 'readonly' => true, 'required' => true, 'size' => true, 'step' => true, 'type' => true, 'value' => true, 'class' => true, 'id' => true ],
			'label'    => [ 'for' => true, 'class' => true ],
			'legend'   => [ 'class' => true ],
			'option'   => [ 'disabled' => true, 'label' => true, 'selected' => true, 'value' => true ],
			'select'   => [ 'autocomplete' => true, 'disabled' => true, 'multiple' => true, 'name' => true, 'required' => true, 'size' => true, 'class' => true, 'id' => true ],
			'textarea' => [ 'autocomplete' => true, 'cols' => true, 'disabled' => true, 'maxlength' => true, 'minlength' => true, 'name' => true, 'placeholder' => true, 'readonly' => true, 'required' => true, 'rows' => true, 'wrap' => true, 'class' => true, 'id' => true ],
			'optgroup' => [ 'disabled' => true, 'label' => true ],
		];

		foreach ( $extras as $tag => $attrs ) {
			$tags[ $tag ] = array_merge( (array) ( $tags[ $tag ] ?? [] ), $attrs );
		}

		foreach ( $tags as $name => $attrs ) {
			$tags[ $name ]['class']    = true;
			$tags[ $name ]['id']       = true;
			$tags[ $name ]['data-*']   = true;
			$tags[ $name ]['aria-*']   = true;
			$tags[ $name ]['role']     = true;
			$tags[ $name ]['hidden']   = true;
			$tags[ $name ]['tabindex'] = true;
		}
	}

	return (array) apply_filters( 'formpipe_kses_allowed_html', $tags );
}

function formpipe_anonymize_ip( string $ip ): string {
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return $ip;
	}
	$packed = inet_pton( $ip );
	if ( strlen( $packed ) === 4 ) {
		return inet_ntop( $packed & inet_pton( '255.255.255.0' ) );
	}
	return inet_ntop( $packed & inet_pton( 'ffff:ffff:ffff:0000:0000:0000:0000:0000' ) );
}

/**
 * IP-keyed transient bucket for per-IP feedback rate-limiting.
 *
 * Returns true when the IP is over the per-minute budget. State persists
 * in a WP transient so the bucket survives across requests for ~60 s.
 * Bucket key is salted with a per-installation secret so an attacker
 * can't see / reset neighboring sites' buckets.
 */
function formpipe_ip_rate_limited( string $ip, int $limit, int $window = 60 ): bool {
	if ( $ip === '' ) {
		return false;
	}

	$salt    = (string) wp_hash( 'formpipe_rate' );
	$bucket  = 'formpipe_rl_' . substr( wp_hash( $salt . '|' . $ip ), 0, 16 );
	$current = (int) ( get_transient( $bucket ) ?: 0 );

	if ( $current >= $limit ) {
		return true;
	}

	set_transient( $bucket, $current + 1, $window );
	return false;
}

/**
 * Whether a captcha / bot challenge has been passed for this request.
 *
 * Companion modules (reCAPTCHA, Turnstile, hCaptcha) hook the
 * `formpipe_captcha_verified` filter and return true when their token
 * validated. Default is false — without a captcha module installed,
 * ajax submissions are still gated by IP rate-limit + honeypot.
 */
function formpipe_captcha_verified(): bool {
	return (bool) apply_filters( 'formpipe_captcha_verified', false );
}

/**
 * IP from the current request. Tries REMOTE_ADDR first, then a CDN
 * header (configurable via FORMPIPE_TRUSTED_PROXY_HEADER). Empty when
 * neither is available (CLI). Use formpipe_anonymize_ip() for log output.
 */
function formpipe_remote_ip(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] )
		? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] )
		: '';

	if ( defined( 'FORMPIPE_TRUSTED_PROXY_HEADER' ) && FORMPIPE_TRUSTED_PROXY_HEADER ) {
		$hdr = (string) FORMPIPE_TRUSTED_PROXY_HEADER;
		if ( ! empty( $_SERVER[ $hdr ] ) ) {
			$forwarded = (string) wp_unslash( $_SERVER[ $hdr ] );
			$first     = trim( explode( ',', $forwarded )[0] );
			if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
				$ip = $first;
			}
		}
	}

	return $ip;
}

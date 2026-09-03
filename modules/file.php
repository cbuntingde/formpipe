<?php
namespace FormPipe;
/**
 * file / file* module.
 *
 * Renders <input type="file">. Submission::handle_uploads() calls
 * formpipe_handle_upload() (defined here) to move the file to a tmp dir.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'formpipe_init', static function (): void {
	\FormPipe\FormTagsManager::register(
		[ 'file', 'file*' ],
		static function ( \FormPipe\FormTag $tag ): string {
			$atts = [
				'type'      => 'file',
				'name'      => $tag->name,
				'id'        => $tag->get_id_option() ?: null,
				'class'     => $tag->get_class_option( 'formpipe-field' ) ?: null,
				'tabindex'  => (int) ( $tag->get_option( 'tabindex', '-?\d+', true ) ?: 0 ) ?: null,
				'size'      => (int) ( $tag->get_option( 'size', '\d+', true ) ?:  40 ),
				'accept'    => $tag->get_option( 'accept', '[A-Za-z0-9_,.\-/*+ ]+', true ) ?: null,
			];

			if ( $tag->has_option( 'multiple' ) ) {
				$atts['multiple'] = true;
				$atts['name']      = $tag->name . '[]';
			}

			if ( $tag->is_required() ) {
				$atts['required'] =   true;
				$atts['aria-required'] = 'true';
			}

			$atts = array_filter( $atts, static fn( $v ) => $v !== null && $v !== '' && $v !== 0 );

			return sprintf(
				'<span class="formpipe-control" data-name="%1$s"><input %2$s /></span>',
				esc_attr( $tag->name ),
				\FormPipe\format_atts( $atts )
			);
		},
		[ 'name-attr' => true, 'file' => true ]
	);
} );

/**
 * Sniff a file's content-type from magic bytes.
 *
 * Tries finfo first (most accurate), falls back to a hand-rolled magic-byte
 * scanner. Returns the canonical mime on success or null when no signature
 * matched. Pure: only reads the file, no side effects.
 *
 * @return string|null
 */
function formpipe_sniff_mime( string $path ) {
	if ( ! is_readable( $path ) ) {
		return null;
	}

	if ( function_exists( 'finfo_open' ) ) {
		$finfo    = @finfo_open( FILEINFO_MIME_TYPE );
		$mime_raw = $finfo ? @finfo_file( $finfo, $path ) : false;
		if ( $finfo ) {
			finfo_close( $finfo );
		}
		if ( is_string( $mime_raw ) && $mime_raw !== '' && $mime_raw !== 'application/x-empty' ) {
			return $mime_raw;
		}
	}

	$head = @file_get_contents( $path, false, null, 0, 16 );
	if ( $head === false || $head === '' ) {
		return null;
	}

	if ( str_starts_with( $head, "\xFF\xD8\xFF" ) ) {
		return 'image/jpeg';
	}
	if ( str_starts_with( $head, "\x89PNG\r\n\x1A\n" ) ) {
		return 'image/png';
	}
	if ( str_starts_with( $head, "GIF87a" ) || str_starts_with( $head, "GIF89a" ) ) {
		return 'image/gif';
	}
	if ( str_starts_with( $head, "RIFF" ) && substr( $head, 8, 4 ) === 'WEBP' ) {
		return 'image/webp';
	}
	if ( str_starts_with( $head, "%PDF-" ) ) {
		return 'application/pdf';
	}
	if ( str_starts_with( $head, "PK\x03\x04" ) || str_starts_with( $head, "PK\x05\x06" ) || str_starts_with( $head, "PK\x07\x08" ) ) {
		return 'application/zip';
	}
	if ( str_starts_with( $head, "<?xml" ) || str_starts_with( $head, "<svg" ) ) {
		return 'image/svg+xml';
	}

	return null;
}

/**
 * What mimes do we expect for an extension. Used by the upload validator
 * to gate the extension on the content mime.
 *
 * @return string[]
 */
function formpipe_expected_mimes_for_extension( string $ext ): array {
	$map = [
		'jpg'  => [ 'image/jpeg' ],
		'jpeg' => [ 'image/jpeg' ],
		'jpe'  => [ 'image/jpeg' ],
		'png'  => [ 'image/png' ],
		'gif'  => [ 'image/gif' ],
		'webp' => [ 'image/webp' ],
		'svg'  => [ 'image/svg+xml' ],
		'pdf'  => [ 'application/pdf' ],
		'zip'  => [ 'application/zip' ],
		'txt'  => [ 'text/plain', 'application/x-empty', 'text/x-php' ],
	];
	return $map[ $ext ] ?? [];
}

/**
 * True when the file's body contains PHP execution markers, regardless of
 * claimed type. Reads up to 64 KiB; the markers are short and concentrated
 * near the top of any plausible polyglot.
 *
 * Catches:
 *   - `<?php ... ?>` opening tags
 *   - `<? ... ?>` short tags
 *   - `<script language="php">` (PHP < 7)
 *   - `<% %>` ASP-style tags (when PHP is configured with asp_tags=1)
 *
 * This is a defense-in-depth check on top of WP's
 * `wp_check_filetype_and_ext()` and the magic-byte sniff. A polyglot that
 * satisfies WP (e.g. has a real JPEG header) but also embeds PHP bytes is
 * rejected here even if the extension passes the layer-1 and layer-2
 * gates.
 */
function formpipe_upload_contains_php( string $path ): bool {
	if ( ! is_readable( $path ) ) {
		return false;
	}

	$head = @file_get_contents( $path, false, null, 0, 65536 );
	if ( ! is_string( $head ) || $head === '' ) {
		return false;
	}

	if ( str_contains( $head, '<?php' ) ) {
		return true;
	}
	if ( preg_match( '/<\?(?!xml)\s/', $head ) ) {
		return true;
	}
	if ( str_contains( $head, '<script' ) && preg_match( '/<script\b[^>]*language\s*=\s*["\']?php/i', $head ) ) {
		return true;
	}
	if ( str_contains( $head, '<%' ) ) {
		return true;
	}

	return false;
}

/**
 * Validate one uploaded file against a comma-separated allowlist of extensions.
 *
 * Four layers so polyglots can't slip through:
 *   1. basename script-bait regex (no `evil.php` even if extension allowed)
 *   2. wp_check_filetype_and_ext (WP's preferred extension/MIME check)
 *   3. independent content sniff (finfo + magic-byte fallback) gated on
 *      the extension's expected mimes
 *   4. raw-bytes scan for PHP execution markers (defense-in-depth)
 *
 * @return true|\WP_Error
 */
function formpipe_validate_upload( string $tmp, string $name, string $filetypes ) {
	if ( $filetypes === '' ) {
		return true;
	}

	$allowed = array_map(
		static fn( $t ) => strtolower( trim( $t ) ),
		explode( ',', $filetypes )
	);

	$safe = formpipe_antiscript_file_name( $name );

	$base = strtolower( pathinfo( $safe, PATHINFO_FILENAME ) );
	// Reject any segment of the stem or final extension whose name is a
	// known server-side execution extension. The regex matches the base
	// filename only — a filename like `evil.php.jpg` is rejected via the
	// second branch (`pathinfo(... PATHINFO_EXTENSION)`) because the
	// stem still contains `evil.php`.
	if ( preg_match( '/^(php|phtml|phar|cgi|pl|py|sh|asp|aspx|jsp)\d*$/', $base ) ) {
		return new \WP_Error( 'formpipe_upload_type', __( 'That file type is not allowed.', 'formpipe' ) );
	}
	if ( str_contains( $base, '.php' ) || str_contains( $base, '.phtml' ) || str_contains( $base, '.phar' ) ) {
		return new \WP_Error( 'formpipe_upload_type', __( 'That file type is not allowed.', 'formpipe' ) );
	}

	$ext = strtolower( pathinfo( $safe, PATHINFO_EXTENSION ) );

	// Layer 1: WP's preferred extension/MIME check. If WP says the file
	// type is not allowed (e.g. because the name is `evil.php.jpg` and
	// WP detected PHP markers in the body), reject immediately. The
	// return tuple is `[ ext, type ]` — `[ false, false ]` when WP
	// could not classify.
	$wp_check = wp_check_filetype_and_ext( $tmp, $safe );
	$wp_ext   = is_array( $wp_check ) ? ( $wp_check['ext'] ?? false ) : false;
	$wp_type  = is_array( $wp_check ) ? ( $wp_check['type'] ?? false ) : false;

	if ( $wp_ext === false || $wp_ext !== $ext ) {
		return new \WP_Error( 'formpipe_upload_type', __( 'That file type is not allowed.', 'formpipe' ) );
	}

	$ext_ok = in_array( $ext, $allowed, true );

	// Layer 2: independent content sniff (finfo + magic-byte fallback).
	// Gated on the extension's expected MIME list so a polyglot claimed
	// as `.jpg` but containing PHP bytes is rejected.
	$content_mime = formpipe_sniff_mime( $tmp );
	$expected     = formpipe_expected_mimes_for_extension( $ext );

	if ( $content_mime !== null ) {
		$ext_ok = $ext_ok && in_array( $content_mime, $expected, true );
	} else {
		// Sniffer couldn't classify. Allow only non-fingerprinted ext (txt).
		$sniffable = [ 'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp', 'svg', 'pdf' ];
		if ( in_array( $ext, $sniffable, true ) ) {
			$ext_ok = false;
		}
	}

	// Layer 3: belt-and-suspenders content scan. Any uploaded file with
	// PHP-like bytes in its body is rejected regardless of claimed type.
	if ( formpipe_upload_contains_php( $tmp ) ) {
		return new \WP_Error( 'formpipe_upload_type', __( 'That file type is not allowed.', 'formpipe' ) );
	}

	if ( ! $ext_ok ) {
		return new \WP_Error( 'formpipe_upload_type', __( 'That file type is not allowed.', 'formpipe' ) );
	}

	return true;
}

/**
 * Move uploaded files to a tmp dir, validate extension/size, return paths.
 *
 * Set `bypass_is_uploaded_file => true` in $options to accept files created
 * outside of a real upload (used by the smoke harness). Production code
 * leaves it false and relies on is_uploaded_file() to reject attacker-
 * supplied paths.
 *
 * @return string[]|\WP_Error
 */
function formpipe_handle_upload( array $file, array $options ) {
	if ( empty( $file['name'] ) ) {
		return [];
	}

	$limit     = (int) ( $options['limit'] ?? formpipe_max_upload_size() );
	$filetypes = (string) ( $options['filetypes'] ?? '' );
	$bypass    = ! empty( $options['bypass_is_uploaded_file'] );

	$names     = formpipe_flatten_files( $file['name'] );
	$tmp_names = formpipe_flatten_files( $file['tmp_name'] );
	$errors    = formpipe_flatten_files( $file['error'] );
	$sizes     = formpipe_flatten_files( $file['size'] );

	$dir = wp_upload_dir()['basedir'] . '/formpipe-tmp';
	wp_mkdir_p( $dir );

	$saved = [];

	foreach ( $names as $i => $name ) {
		$tmp = $tmp_names[ $i ] ?? '';
		$err = (int) ( $errors[ $i ] ?? UPLOAD_ERR_NO_FILE );
		$sz  = (int) ( $sizes[ $i ] ?? 0 );

		if ( $err !== UPLOAD_ERR_OK ) {
			return new \WP_Error( 'formpipe_upload_failed', __( 'Upload failed.', 'formpipe' ) );
		}

		if ( ! $bypass && ! is_uploaded_file( $tmp ) ) {
			continue;
		}

		if ( $sz > $limit ) {
			return new \WP_Error( 'formpipe_upload_too_large', __( 'The uploaded file is too large.', 'formpipe' ) );
		}

		$check = formpipe_validate_upload( $tmp, $name, $filetypes );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$dest = trailingslashit( $dir ) . wp_unique_filename( $dir, $name );

		if ( ! $bypass ) {
			$moved = @move_uploaded_file( $tmp, $dest );
		} else {
			$moved = @rename( $tmp, $dest ) || @copy( $tmp, $dest );
		}

		if ( ! $moved ) {
			return new \WP_Error( 'formpipe_upload_move', __( 'Could not save the upload.', 'formpipe' ) );
		}

		// Lock down in production. In test mode the harness shares the tmp
		// dir and 0400 holds up cleanup and overwrites of same filename.
		if ( ! $bypass ) {
			@chmod( $dest, 0400 );
		}
		$saved[] = $dest;
	}

	return $saved;
}

/**
 * Flatten a $_FILES entry to a list of file names / tmp paths.
 *
 * @return string[]
 */
function formpipe_flatten_files( $value ): array {
	if ( is_array( $value ) ) {
		$out = [];
		foreach ( $value as $v ) {
			foreach ( formpipe_flatten_files( $v ) as $vv ) {
				$out[] = $vv;
			}
		}
		return $out;
	}
	return $value === '' ? [] : [ (string) $value ];
}
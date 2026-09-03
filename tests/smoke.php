<?php
/**
 * Standalone smoke harness for FormPipe.
 *
 * Loads the FormPipe core directly, stubbing the WordPress surface they
 * touch. Verifies:
 *   - scanner produces the right FormTag objects for each tag type,
 *   - mail-tag replace works for text + html + specials,
 *   - per-type validation filters report errors correctly,
 *   - File upload module returns paths / errors,
 *   - Acceptance module renders correctly,
 *   - Form hashes and unit-tags have the right shape,
 *   - Pipes class canonicalizes for case-insensitive lookup.
 *
 * Run with:  php tests/smoke.php
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'FORMPIPE_VERSION', '1.0.0' );
define( 'FORMPIPE_FILE', __DIR__ . '/formpipe.php' );
define( 'FORMPIPE_DIR', __DIR__ );

// ---------- minimal WordPress shims ----------

$GLOBALS['__filters'] = [];
$GLOBALS['__actions'] = [];

function plugin_dir_path( $f )   { return dirname( $f ) . '/'; }
function untrailingslashit( $s ) { return rtrim( $s, '/\\' ); }
function trailingslashit( $s )   { return rtrim( $s, '/\\' ) . '/'; }
function path_join( ...$parts )  { return implode( '/', array_filter( $parts ) ); }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : ''; }
function sanitize_email( $s )    { return filter_var( $s, FILTER_SANITIZE_EMAIL ) ?: ''; }
function sanitize_key( $s )      { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $s ) ); }
function sanitize_html_class( $s ){ return preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $s ); }
function sanitize_file_name( $s ){ return preg_replace( '/[^A-Za-z0-9._\-]/', '_', (string) $s ); }
function sanitize_title( $s )    { return strtolower( preg_replace( '/[^a-z0-9\-]/', '-', (string) $s ) ); }
function sanitize_url( $s )       { return (string) $s; }
function sanitize_url_raw( $s )  { return (string) $s; }
function sanitize_textarea_field( $s ) { return (string) $s; }
function wp_check_invalid_utf8( $s ) { return $s; }
function wp_kses_post( $s )      { return $s; }
function wp_kses_no_null( $s )   { return $s; }
function wp_unslash( $s )        { return $s; }
function wp_slash( $s )          { return is_string( $s ) ? addslashes( $s ) : $s; }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function wp_basename( $p )       { return basename( $p ); }
function wp_normalize_path( $p ) { return str_replace( '\\', '/', (string) $p ); }
function esc_attr( $s )          { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $s )          { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s )           { return (string) $s; }
function esc_url_raw( $s )      { return (string) $s; }
function esc_textarea( $s )     { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $s, $d='' ){ return $s; }
function esc_attr__( $s, $d='' ){ return $s; }
function __( $s, $d = '' )       { return $s; }
function _x( $s, $c = '', $d = '' ) { return $s; }
function _n( $s, $p, $n, $d = '' )   { return $n === 1 ? $s : $p; }
function wp_specialchars_decode( $s, $q = '' ) { return htmlspecialchars_decode( (string) $s, $q === '' ? ENT_QUOTES : $q ); }
function is_email( $s )           { return filter_var( $s, FILTER_VALIDATE_EMAIL ) ? $s : false; }
function wp_hash( $s )            { return substr( md5( (string) $s ), 0, 32 ); }
function wp_date( $fmt )          { return date( $fmt ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, (array) $args ); }
function wp_unique_filename( $d, $n ) { return $n; }
function wp_mkdir_p( $d )        { return is_dir( $d ) || @mkdir( $d, 0755, true ); }
function get_option( $k, $d = false ) { return $d; }
function get_the_ID( $fallback = 0 ) { return 0; }
function in_the_loop()           { return false; }
function current_user_can( ...$a ){ return true; }
function is_user_logged_in()     { return false; }
function wp_get_current_user()    { return (object) [ 'ID' => 0 ]; }
function get_user_meta( $id, $k, $single = true ) { return ''; }
function get_post_meta( ...$a )   { return ''; }
function update_post_meta( ...$a ){ return true; }
function delete_post_meta( ...$a ){ return true; }
function add_post_meta( ...$a )   { return true; }
function get_post( $p )           { return null; }
function get_posts( $a )          { return []; }
function get_the_title( $p = 0 )  { return ''; }
function get_permalink( $p = 0 )  { return ''; }
function wp_get_attachment_url( $p ) { return ''; }
function wp_insert_post( $a, $e = false ) { return 1; }
function wp_update_post( $a, $e = false ) { return 1; }
function wp_delete_post( $id, $force = false ) { return true; }
function wp_create_nonce( $a )    { return 'nonce'; }
function wp_verify_nonce( $n, $a ) { return 1; }
function check_admin_referer( $a, $q = '' ) { /* no-op */ }
function wp_safe_redirect( $u )   { /* no-op */ exit; }
function add_query_arg( $a = [], $u = '' ) { return 'http://example.org/?p=1'; }
function remove_query_arg( $k, $u = '' ) { return $u; }
function load_plugin_textdomain( $d, $a = false, $p = '' ) { /* no-op */ }
function plugin_basename( $f )    { return basename( $f ); }
function register_post_type( $a, $b ) { /* no-op */ }
function register_block_type( $a, $b = [] ) { /* no-op */ }
function is_wp_error( $thing ): bool { return $thing instanceof \WP_Error; }
class WP_Error {
	public function __construct( public string $code = '', public string $message = '', public $data = null ) {}
	public function get_error_code(): string   { return $this->code; }
	public function get_error_message(): string { return $this->message; }
	public function get_error_data()           { return $this->data; }
	public function get_error_messages(): array { return [ $this->message ]; }
}
$_TRANSIENTS = [];
function get_transient( $k )    { global $_TRANSIENTS; return $_TRANSIENTS[$k] ?? false; }
function set_transient( $k, $v, $e = 0 ) { global $_TRANSIENTS; $_TRANSIENTS[$k] = $v; return true; }
function delete_transient( $k ) { global $_TRANSIENTS; unset( $_TRANSIENTS[$k] ); return true; }
function do_action( $tag, ...$args ) {
	global $__actions;
	if ( empty( $__actions[ $tag ] ) ) { return; }
	foreach ( $__actions[ $tag ] as $fn ) { $fn( ...$args ); }
}
function add_action( $tag, $fn, $prio = 10, $args = 1 ) {
	global $__actions;
	$__actions[ $tag ][] = $fn;
}
function apply_filters( $tag, $value, ...$rest ) {
	global $__filters;
	if ( empty( $__filters[ $tag ] ) ) { return $value; }
	foreach ( $__filters[ $tag ] as $fn ) { $value = $fn( $value, ...$rest ); }
	return $value;
}
function add_filter( $tag, $fn, $prio = 10, $accepted_args = 1 ) {
	global $__filters;
	$__filters[ $tag ][] = $fn;
}
function wp_max_upload_size() { return 25 * 1024 * 1024; }
function wp_check_filetype_and_ext( $file, $name ) {
	$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	if ( $ext === '' ) { return [ 'ext' => false, 'type' => false ]; }

	$mime = '';
	if ( function_exists( 'finfo_open' ) ) {
		$finfo = @finfo_open( FILEINFO_MIME_TYPE );
		if ( $finfo ) {
			$mime = (string) @finfo_file( $finfo, $file );
			finfo_close( $finfo );
		}
	}
	$mime_to_ext = [
		'image/jpeg'      => [ 'jpg', 'jpeg', 'jpe' ],
		'image/png'       => [ 'png' ],
		'image/gif'       => [ 'gif' ],
		'image/webp'      => [ 'webp' ],
		'image/svg+xml'   => [ 'svg' ],
		'application/pdf' => [ 'pdf' ],
		'text/plain'      => [ 'txt' ],
	];
	if ( $mime !== '' && isset( $mime_to_ext[ $mime ] ) && in_array( $ext, $mime_to_ext[ $mime ], true ) ) {
		return [ 'ext' => $ext, 'type' => $mime ];
	}
	return $mime === '' ? [ 'ext' => $ext, 'type' => '' ] : [ 'ext' => false, 'type' => false ];
}
function wp_upload_dir() { return [ 'basedir' => sys_get_temp_dir(), 'baseurl' => 'http://example.org/wp-content/uploads' ]; }
function MB_IN_BYTES() { return 1048576; }
function KB_IN_BYTES() { return 1024; }
function GB_IN_BYTES() { return 1024 * 1024 * 1024; }
function MINUTE_IN_SECONDS() { return 60; }
function HOUR_IN_SECONDS()   { return 3600; }
function wptexturize( $s )   { return (string) $s; }
function wpautop( $s )       { return (string) $s; }
function did_action( $a )    { return 0; }
function do_action_ref_array( $a, $b ) { /* no-op */ }
function mysql2date( $f, $d ) { return $d; }
function absint( $v ) { return abs( (int) $v ); }
function get_user_option( $k, $u = false ) { return false; }
function add_screen_option( $a, $b ) { /* no-op */ }
function wp_list_table( $a = [] ) { return null; }

function apply_filters_ref_array( $tag, $args ) {
	global $__filters;
	if ( empty( $__filters[ $tag ] ) ) { return $args[0]; }
	foreach ( $__filters[ $tag ] as $fn ) { $args[0] = $fn( ...$args ); }
	return $args[0];
}

function wp_get_list_item_separator() { return ', '; }
function force_balance_tags( $s ) { return (string) $s; }
function wp_kses_allowed_html( $context = 'post' ) {
	return [
		'br'     => [],
		'form'   => [ 'method' => true, 'action' => true, 'class' => true ],
		'input'  => [ 'type' => true, 'name' => true, 'value' => true ],
		'textarea'=> [ 'name' => true ],
		'select' => [ 'name' => true ],
		'button' => [ 'type' => true ],
		'em'     => [],
		'strong' => [],
		'p'      => [],
	];
}

// ---------- load FormPipe core ----------

require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/FormTag.php';
require __DIR__ . '/../includes/Pipes.php';
require __DIR__ . '/../includes/FormTagsManager.php';
require __DIR__ . '/../includes/Validation.php';
require __DIR__ . '/../includes/Mail.php';
require __DIR__ . '/../includes/Form.php';
require __DIR__ . '/../includes/Submission.php';
require __DIR__ . '/../includes/PostType.php';
// ---------- load field modules ----------

$__modules = [
	'text', 'textarea', 'number', 'date',
	'select', 'checkbox', 'radio', 'acceptance',
	'file', 'hidden', 'submit', 'quiz', 'response',
	'count', 'captcha',
];

foreach ( $__modules as $m ) {
	require __DIR__ . '/../modules/' . $m . '.php';
}

do_action( 'formpipe_init' );

// ---------- 1. Scanner ----------

$cases = [
	'[text* your-name]'                          => [ 'type' => 'text*',  'basetype' => 'text', 'name' => 'your-name', 'required' => true ],
	'[email your-email]'                         => [ 'type' => 'email',  'basetype' => 'email' ],
	'[textarea* your-message]'                   => [ 'type' => 'textarea*', 'basetype' => 'textarea', 'required' => true ],
	'[select your-color "Red" "Green" "Blue"]'   => [ 'type' => 'select', 'basetype' => 'select', 'values' => 3 ],
	'[checkbox your-topics "a" "b"]'             => [ 'type' => 'checkbox', 'basetype' => 'checkbox', 'values' => 2 ],
	'[radio your-gender "m" "f"]'                => [ 'type' => 'radio',   'basetype' => 'radio', 'values' => 2 ],
	'[submit "Send"]'                            => [ 'type' => 'submit',  'basetype' => 'submit', 'values' => 1 ],
	'[hidden source]'                            => [ 'type' => 'hidden',  'basetype' => 'hidden', 'name' => 'source' ],
	'[acceptance* terms "I agree."]'             => [ 'type' => 'acceptance*', 'basetype' => 'acceptance', 'required' => true ],
	'[quiz* cap "1+1=?|2"]'                      => [ 'type' => 'quiz*',  'basetype' => 'quiz', 'required' => true, 'values' => 1 ],
	'[number age min:0 max:120]'                 => [ 'type' => 'number', 'basetype' => 'number' ],
	'[date birthday]'                            => [ 'type' => 'date',   'basetype' => 'date' ],
	'[time meeting-time]'                        => [ 'type' => 'time',   'basetype' => 'time' ],
	'[file* attachment accept:application/pdf]'   => [ 'type' => 'file*',   'basetype' => 'file', 'required' => true ],
	'[count your-message mode:chars]'            => [ 'type' => 'count',   'basetype' => 'count' ],
	'[response your-name]'                       => [ 'type' => 'response','basetype' => 'response' ],
];

$content = implode( "\n", array_keys( $cases ) );
$tags    = \FormPipe\FormTagsManager::scan( $content );

if ( count( $tags ) !== count( $cases ) ) {
	fwrite( STDERR, "FAIL: expected " . count( $cases ) . " tags, got " . count( $tags ) . "\n" );
	exit( 1 );
}

$case_keys = array_keys( $cases );
$matched   = [];

foreach ( $tags as $tag ) {
	$key = null;
	foreach ( $case_keys as $candidate ) {
		if ( ! str_starts_with( $candidate, '[' . $tag->type ) ) { continue; }
		if ( $tag->basetype === 'submit' || $tag->basetype === 'count' || $tag->basetype === 'response' ) {
			$key = $candidate;
			break;
		}
		if ( $tag->name !== '' && str_contains( $candidate, $tag->name ) ) {
			$key = $candidate;
			break;
		}
	}
	if ( $key === null ) {
		fwrite( STDERR, "FAIL: scanned unexpected tag {$tag->type}/{$tag->name}\n" );
		exit( 1 );
	}
	if ( isset( $matched[ $key ] ) ) {
		fwrite( STDERR, "FAIL: duplicate tag matched: $key\n" );
		exit( 1 );
	}
	$matched[ $key ] = true;

	$expected = $cases[ $key ];

	if ( $tag->type !== $expected['type'] ) {
		fwrite( STDERR, "FAIL: type mismatch for $key: {$tag->type} vs {$expected['type']}\n" );
		exit( 1 );
	}
	if ( $tag->basetype !== $expected['basetype'] ) {
		fwrite( STDERR, "FAIL: basetype mismatch for $key\n" );
		exit( 1 );
	}
	if ( $tag->is_required() !== ( $expected['required'] ?? false ) ) {
		fwrite( STDERR, "FAIL: required mismatch for $key\n" );
		exit( 1 );
	}
	if ( isset( $expected['values'] ) && count( $tag->values ) !== $expected['values'] ) {
		fwrite( STDERR, "FAIL: value count mismatch for $key\n" );
		exit( 1 );
	}
}

echo "PASS scanner (" . count( $cases ) . " types)\n";

// ---------- 2. Replace_all ----------

$html = \FormPipe\FormTagsManager::replace_all( '[text* your-name][email your-email][submit "Send"]' );

if ( ! str_contains( $html, '<input' ) ) {
	fwrite( STDERR, "FAIL: replacement did not emit inputs\n" );
	exit( 1 );
}
if ( ! str_contains( $html, 'required' ) ) {
	fwrite( STDERR, "FAIL: required tag should render required attribute\n" );
	exit( 1 );
}
if ( ! str_contains( $html, '<button' ) && ! str_contains( $html, 'type="submit"' ) ) {
	fwrite( STDERR, "FAIL: submit button missing\n" );
	exit( 1 );
}

echo "PASS replace_all\n";

// ---------- 3. Validation pipeline ----------

$v   = new \FormPipe\Validation();
$tag = new \FormPipe\FormTag();
$tag->type = 'email';
$tag->name = 'your-email';
$v = apply_filters( 'formpipe_validate_email', $v, $tag, 'bad' );

if ( $v->is_valid() ) {
	fwrite( STDERR, "FAIL: bad email should have failed validation\n" );
	exit( 1 );
}
if ( ! isset( $v->get_errors()['your-email'] ) ) {
	fwrite( STDERR, "FAIL: email error not recorded\n" );
	exit( 1 );
}

$v   = new \FormPipe\Validation();
$tag = new \FormPipe\FormTag();
$tag->type = 'email';
$tag->name = 'your-email';
$v = apply_filters( 'formpipe_validate_email', $v, $tag, 'a@b.co' );
if ( ! $v->is_valid() ) {
	fwrite( STDERR, "FAIL: valid email should pass\n" );
	exit( 1 );
}

echo "PASS validation (email)\n";

// ---------- 4. Mail-tag replace ----------

$posted = [
	'first'   => 'Ada',
	'last'    => 'Lovelace',
	'colors'  => [ 'red', 'green' ],
];
$specials = [
	'_remote_ip'  => '203.0.113.5',
	'_user_agent' => 'curl/8',
	'_date'       => '2026-09-02',
];

$mail = new \FormPipe\Mail( 'preview', [ 'subject' => '', 'body' => '', 'use_html' => false ] );

$txt = $mail->replace_tags(
	'From: [first] [last]\nIP: [_remote_ip]\nColors: [colors]\nDate: [_date]',
	$posted,
	$specials,
	false
);

foreach ( [ 'Ada Lovelace', '203.0.113.5', 'red, green', '2026-09-02' ] as $needle ) {
	if ( ! str_contains( $txt, $needle ) ) {
		fwrite( STDERR, "FAIL: mail replace missing '$needle':\n$txt\n" );
		exit( 1 );
	}
}

// HTML mode escapes injected tags.
$posted_unsafe = [ 'greeting' => '<script>alert(1)</script>' ];
$html_body = $mail->replace_tags( 'Hi [greeting]', $posted_unsafe, [], true );
if ( ! str_contains( $html_body, '&lt;script&gt;' ) || str_contains( $html_body, '<script>' ) ) {
	fwrite( STDERR, "FAIL: HTML escape in mail replace missing: $html_body\n" );
	exit( 1 );
}

// Unknown tag round-trips.
$unknown = $mail->replace_tags( 'Hi [nope]', $posted, $specials, false );
if ( $unknown !== 'Hi [nope]' ) {
	fwrite( STDERR, "FAIL: unknown tag should round-trip: $unknown\n" );
	exit( 1 );
}

echo "PASS mail replace (text + html + unknown)\n";

// ---------- 5. Acceptance module renders ----------

$out = \FormPipe\FormTagsManager::replace_all( '[acceptance* terms "I agree."]' );
if ( ! str_contains( $out, 'type="checkbox"' ) ) {
	fwrite( STDERR, "FAIL: acceptance did not emit checkbox\n" );
	exit( 1 );
}
if ( ! str_contains( $out, 'I agree.' ) ) {
	fwrite( STDERR, "FAIL: acceptance label missing\n" );
	exit( 1 );
}

echo "PASS acceptance module\n";

// ---------- 6. Form hash + unit-tag shape ----------

if ( ! preg_match( '/^[0-9a-f]{7}$/', \FormPipe\formpipe_form_hash( 7 ) ) ) {
	fwrite( STDERR, "FAIL: form_hash wrong shape\n" );
	exit( 1 );
}

$form = \FormPipe\Form::blank();
if ( ! preg_match( '/^fp0-f0-o\d+$/', $form->unit_tag() ) ) {
	exit( 1 );
}

echo "PASS unit_tag + form_hash\n";

// ---------- 7. Pipes canonicalization ----------
$pipes = new \FormPipe\FormPipe_Pipes( [ 'Red|Red Label', 'Blue' ] );
$result = $pipes->do_pipe( 'RED' );
if ( $result !== 'Red Label' ) {
	exit( 1 );
}
$result = $pipes->do_pipe( 'unknown' );
if ( $result !== 'unknown' ) {
	fwrite( STDERR, "FAIL: pipe fallback failed: $result\n" );
	exit( 1 );
}

echo "PASS pipes (canonicalization + fallback)\n";

// ---------- 8. File module limit parsing ----------

if ( \FormPipe\formpipe_parse_limit( '2mb' ) !== 2 * 1024 * 1024 ) {
	fwrite( STDERR, "FAIL: parse_limit 2mb wrong\n" );
	exit( 1 );
}
if ( \FormPipe\formpipe_parse_limit( '512kb' ) !== 512 * 1024 ) {
	fwrite( STDERR, "FAIL: parse_limit 512kb wrong\n" );
	exit( 1 );
}
if ( \FormPipe\formpipe_parse_limit( '100' ) !== 100 ) {
	fwrite( STDERR, "FAIL: parse_limit 100 wrong\n" );
	exit( 1 );
}


// ---------- 9. Kses allowed HTML for form ----------

$tags = \FormPipe\formpipe_kses_allowed_html();
foreach ( [ 'form', 'input', 'textarea', 'select', 'button' ] as $must ) {
	if ( ! isset( $tags[ $must ] ) ) {
		fwrite( STDERR, "FAIL: form kses missing tag: $must\n" );
		exit( 1 );
	}
}

echo "PASS kses allowed HTML\n";

// ---------- 10. Form model save/load shape ----------

$form = \FormPipe\Form::blank();
$form->set_title( 'Test Form' );
$form->template = '[text* your-name]';
$form->mail['recipient'] = 'admin@example.org';
$form->mail['subject'] = 'New submission';
$form->settings['demo_mode'] = 'on';

$shortcode = $form->shortcode();
if ( str_contains( $shortcode, 'formpipe' ) && str_contains( $shortcode, 'Test Form' ) ) {
	echo "PASS form shortcode shape\n";
} else {
	fwrite( STDERR, "FAIL: form shortcode wrong: $shortcode\n" );
	exit( 1 );
}

// ---------- 11. Validation array-access back-compat ----------

$compat = new \FormPipe\Validation();
$compat[ 'reason' ] = [ 'email' => 'bad email' ];
if ( ! isset( $compat['email'] ) ) {
	fwrite( STDERR, "FAIL: ArrayAccess compat broken\n" );
	exit( 1 );
}
echo "PASS validation ArrayAccess compat\n";

// ---------- 12. File upload: polyglot rejection + size gate ----------

require_once __DIR__ . '/../modules/file.php';

$poly = tempnam( sys_get_temp_dir(), 'fp_poly_' );
file_put_contents( $poly, '<?php echo "pwned"; ?>' );
$r = \FormPipe\formpipe_handle_upload(
	[
		'name'     => [ 'evil.jpg' ],
		'type'     => [ 'image/jpeg' ],
		'tmp_name' => [ $poly ],
		'error'    => [ 0 ],
		'size'     => [ 22 ],
	],
	[
		'limit'                   => 25 * 1024 * 1024,
		'filetypes'               => 'jpg,png',
		'bypass_is_uploaded_file' => true,
	]
);
if ( ! is_wp_error( $r ) || $r->get_error_code() !== 'formpipe_upload_type' ) {
	@unlink( $poly );
	fwrite( STDERR, "FAIL: polyglot .jpg with PHP bytes accepted\n" );
	exit( 1 );
}
@unlink( $poly );

$real = tempnam( sys_get_temp_dir(), 'fp_real_' );
$png  = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=' );
file_put_contents( $real, $png );
$r2 = \FormPipe\formpipe_handle_upload(
	[
		'name'     => [ 'pixel.png' ],
		'type'     => [ 'image/png' ],
		'tmp_name' => [ $real ],
		'error'    => [ 0 ],
		'size'     => [ strlen( $png ) ],
	],
	[
		'limit'                   => 25 * 1024 * 1024,
		'filetypes'               => 'jpg,png',
		'bypass_is_uploaded_file' => true,
	]
);
if ( is_wp_error( $r2 ) ) {
	@unlink( $real );
	fwrite( STDERR, 'FAIL: real PNG rejected: ' . $r2->get_error_code() . "\n" );
	exit( 1 );
}
foreach ( (array) $r2 as $p ) { @unlink( $p ); }
@unlink( $real );

$big = tempnam( sys_get_temp_dir(), 'fp_big_' );
file_put_contents( $big, str_repeat( 'A', 4096 ) );
$r3 = \FormPipe\formpipe_handle_upload(
	[
		'name'     => [ 'big.png' ],
		'type'     => [ 'image/png' ],
		'tmp_name' => [ $big ],
		'error'    => [ 0 ],
		'size'     => [ 4096 ],
	],
	[
		'limit'                   => 1024,
		'filetypes'               => 'png',
		'bypass_is_uploaded_file' => true,
	]
);
if ( ! is_wp_error( $r3 ) || $r3->get_error_code() !== 'formpipe_upload_too_large' ) {
	@unlink( $big );
	fwrite( STDERR, "FAIL: oversize not rejected\n" );
	exit( 1 );
}
@unlink( $big );

echo "PASS file upload (polyglot/size)
";

	// ---------- 13. REST /feedback rate limit + captcha gate ----------

	$_SERVER['REMOTE_ADDR'] = '203.0.113.7';

	// Default 10/minute: 10 free, 11th gets blocked.
	for ( $i = 1; $i <= 10; $i++ ) {
		if ( \FormPipe\formpipe_ip_rate_limited( '203.0.113.7', 10 ) ) {
			fwrite( STDERR, "FAIL: rate-limit kicked in too early at $i\n" );
			exit( 1 );
		}
	}
	if ( ! \FormPipe\formpipe_ip_rate_limited( '203.0.113.7', 10 ) ) {
		fwrite( STDERR, "FAIL: 11th hit should be rate-limited\n" );
		exit( 1 );
	}

	// Different IP, fresh bucket: must NOT be limited.
	if ( \FormPipe\formpipe_ip_rate_limited( '198.51.100.9', 10 ) ) {
		fwrite( STDERR, "FAIL: per-IP bucket leaked across IPs\n" );
		exit( 1 );
	}

	// Empty IP: never rate-limit (defensive).
	if ( \FormPipe\formpipe_ip_rate_limited( '', 10 ) ) {
		fwrite( STDERR, "FAIL: empty IP must not be rate-limited\n" );
		exit( 1 );
	}

	// Captcha gate: default false, must not pass without a module.
	if ( \FormPipe\formpipe_captcha_verified() ) {
		fwrite( STDERR, "FAIL: default captcha_verified() must be false\n" );
		exit( 1 );
	}

	// Filter-based override: a captcha module can flip it to true.
	add_filter( 'formpipe_captcha_verified', static fn() => true );
	if ( ! \FormPipe\formpipe_captcha_verified() ) {
		fwrite( STDERR, "FAIL: captcha filter override ignored\n" );
		exit( 1 );
	}

	// formpipe_remote_ip returns REMOTE_ADDR.
	if ( \FormPipe\formpipe_remote_ip() !== '203.0.113.7' ) {
		fwrite( STDERR, "FAIL: formpipe_remote_ip() returned unexpected value\n" );
		exit( 1 );
	}

	echo "PASS REST rate limit + captcha gate\n";

	echo "\nAll smoke checks passed.\n";

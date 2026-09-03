<?php
namespace FormPipe;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap. The only place that wires hooks to the rest of the code.
 */
final class Plugin {

	/**
	 * Field modules to load by default. Third-party code can extend via the
	 * `formpipe_modules` filter.
	 *
	 * @var string[]
	 */
	private const CORE_MODULES = [
		'text',          // covers text/email/url/tel
		'textarea',
		'number',
		'date',          // covers date/time
		'select',
		'checkbox',
		'radio',
		'acceptance',
		'quiz',
		'file',
		'hidden',
		'submit',
		'response',
		'count',
		'captcha',       // captcha slot — integration modules register via filter
	];

	public static function boot(): void {
		load_plugin_textdomain( 'formpipe', false, dirname( plugin_basename( FORMPIPE_FILE ) ) . '/languages' );

		( new PostType() )->register();
		( new AdminPage() )->register();
		( new Frontend() )->register();
		( new Integration() )->register();
		( new Block() )->register();
		( new TagGenerator() )->register();
		( new Rest\Controller() )->register();

		add_action( 'formpipe_init', [ __NAMESPACE__ . '\\FormTagsManager', 'register_hooks' ], 10, 0 );
		add_action( 'init', static function (): void {
			do_action( 'formpipe_init' );
		}, 10, 0 );
	}

	public static function load_modules(): void {
		$modules = (array) apply_filters( 'formpipe_modules', self::CORE_MODULES );

		foreach ( $modules as $module ) {
			$module = (string) $module;
			$file   = FORMPIPE_MODULES_DIR . '/' . $module . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	}
}

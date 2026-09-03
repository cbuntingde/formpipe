<?php
namespace FormPipe;

defined( 'ABSPATH' ) || exit;

/**
 * Integration registry. Modules register themselves via formpipe_register_integration().
 *
 * Each integration provides:
 *   - title, categories (spam_protection | email_marketing | payments | crm)
 *   - is_active()           whether the integration has credentials configured
 *   - get_form_handler()    returns a callable that returns captcha HTML
 *   - get_spam_filter()     returns a callable added to formpipe_is_spam
 *   - admin_ui( $action )   renders the settings UI in the integration page
 *
 * This is a single small class so add-ons don't need to know about each other.
 */
final class Integration {

	private static ?Integration $instance = null;

	/** @var array<string,array> */
	private array $integrations = [];

	/** @var string[] */
	private array $categories = [
		'spam_protection' => 'Spam protection',
		'email_marketing' => 'Email marketing',
		'payments'        => 'Payments',
		'crm'             => 'CRM',
	];

	public static function get_instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register(): void {
		self::$instance = $this;
		add_action( 'formpipe_init', [ $this, 'load_integrations' ], 20, 0 );
	}

	public function load_integrations(): void {
		do_action( 'formpipe_register_integration', $this );
	}

	public function add( string $name, array $args ): bool {
		$name = sanitize_key( $name );
		if ( $name === '' || isset( $this->integrations[ $name ] ) ) {
			return false;
		}

		$this->integrations[ $name ] = wp_parse_args( $args, [
			'title'     => '',
			'icon'      => '',
			'categories'=> [],
			'is_active' => static fn(): bool => false,
			'render'    => static fn(): string => '',
			'settings'  => [],
		] );

		return true;
	}

	public function remove( string $name ): void {
		unset( $this->integrations[ $name ] );
	}

	public function exists( string $name = '' ): bool {
		if ( $name === '' ) {
			return $this->integrations !== [];
		}
		return isset( $this->integrations[ $name ] );
	}

	public function get( string $name ): ?array {
		return $this->integrations[ $name ] ?? null;
	}

	/** @return string[] */
	public function categories(): array {
		return $this->categories;
	}

	/** @return array<string,array> */
	public function all(): array {
		return $this->integrations;
	}
}

/**
 * Helper for integration modules to register themselves.
 */
function formpipe_register_integration( string $name, array $args ): bool {
	return Integration::get_instance()->add( $name, $args );
}

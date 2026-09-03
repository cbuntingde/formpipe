<?php
namespace FormPipe;

defined( 'ABSPATH' ) || exit;

/**
 * The single form-tag scanner.
 *
 * Modules register their tag types via FormTagsManager::register().
 * The manager builds a regex from the registered type list (longest types
 * first so `textarea*` beats `textarea`), and uses preg_replace_callback
 * to either scan or replace all tags in one pass.
 *
 * Feature flags recognized on registered types:
 *   - 'name-attr'   the tag has a name (first whitespace token).
 *   - 'selectable'  the tag has pipe-encoded values.
 *   - 'multiple'    the tag accepts multiple values.
 *   - 'file'        the tag is a file upload.
 *   - 'singular'    only one of this basetype may appear.
 */
final class FormTagsManager {

	/** @var array<string,array{callback:callable,features:array<string,bool>}> */
	private static array $types = [];

	/** @var FormTag[] */
	private static array $scanned = [];

	public static function register_hooks(): void {
		// Future: scan-on-demand, caching, etc.
	}

	public static function reset(): void {
		self::$types   = [];
		self::$scanned = [];
	}

	/**
	 * Register a form-tag type.
	 *
	 * @param string|string[] $type
	 * @param callable        $callback
	 * @param array<string,bool> $features
	 */
	public static function register( $type, callable $callback, array $features = [] ): void {
		foreach ( (array) $type as $raw ) {
			$t = strtolower( preg_replace( '/[^a-z0-9_*]+/', '_', (string) $raw ) );
			$t = rtrim( $t, '_' );

			if ( $t === '' || isset( self::$types[ $t ] ) ) {
				continue;
			}

			self::$types[ $t ] = [
				'callback' => $callback,
				'features' => $features,
			];
		}
	}

	/** @return string[] */
	public static function registered_types(): array {
		return array_keys( self::$types );
	}

	public static function supports_feature( string $type, string $feature ): bool {
		return ! empty( self::$types[ $type ]['features'][ $feature ] );
	}

	public static function collect_tag_types( array $features = [], bool $invert = false ): array {
		$out = [];

		foreach ( array_keys( self::$types ) as $type ) {
			$match = false;

			foreach ( $features as $feature ) {
				$neg   = str_starts_with( $feature, '!' );
				$check = ltrim( $feature, '! ' );

				if ( $neg ) {
					if ( ! self::supports_feature( $type, $check ) ) {
						$match = true;
						break;
					}
				} else {
					if ( self::supports_feature( $type, $check ) ) {
						$match = true;
						break;
					}
				}
			}

			if ( ( $match && ! $invert ) || ( ! $match && $invert ) ) {
				$out[] = $type;
			}
		}

		return $out;
	}

	/**
	 * Replace every registered form-tag in $content with HTML returned by
	 * the corresponding handler. Side-effect: caches the scanned tags.
	 */
	public static function replace_all( string $content ): string {
		self::$scanned = [];

		if ( self::$types === [] ) {
			return $content;
		}

		return preg_replace_callback(
			self::regex(),
			static function ( array $m ): string {
				$tag = self::build_tag( $m );

				if ( $tag === null ) {
					return $m[0];
				}

				/**
				 * Filter the parsed tag before its handler runs.
				 */
				$tag = apply_filters( 'formpipe_form_tag', $tag, $m[0] );

				self::$scanned[] = $tag;

				return (string) call_user_func( self::$types[ $tag->type ]['callback'], $tag );
			},
			$content
		);
	}

	/** @return FormTag[] */
	public static function last_scanned(): array {
		return self::$scanned;
	}

	/** @return FormTag[] */
	public static function scan( string $content ): array {
		$copy          = self::$scanned;
		self::$scanned = [];

		preg_replace_callback(
			self::regex(),
			static function ( array $m ): string {
				$tag = self::build_tag( $m );
				if ( $tag !== null ) {
					$tag = apply_filters( 'formpipe_form_tag', $tag, $m[0] );
					self::$scanned[] = $tag;
				}
				return '';
			},
			$content
		);

		$out          = self::$scanned;
		self::$scanned = $copy;

		return $out;
	}

	public static function normalize( string $content ): string {
		if ( self::$types === [] ) {
			return $content;
		}

		return preg_replace_callback(
			self::regex(),
			static function ( array $m ): string {
				if ( '[' === $m[1] && ']' === $m[6] ) {
					return $m[0]; // escaped
				}

				$attr = trim( preg_replace( '/[\r\n\t ]+/', ' ', (string) $m[3] ) );
				$attr = strtr( $attr, [ '<' => '&lt;', '>' => '&gt;' ] );
				$body = trim( (string) $m[5] );

				return $m[1] . '[' . $m[2]
					. ( $attr !== '' ? ' ' . $attr : '' )
					. ( ! empty( $m[4] ) ? ' ' . $m[4] : '' )
					. ']'
					. ( $body !== '' ? $body . '[/' . $m[2] . ']' : '' )
					. $m[6];
			},
			$content
		);
	}

	public static function replace_with_placeholders( string $content, string $placeholder_inline, string $placeholder_block ): string {
		// Implemented as a no-op replacement here; the renderer doesn't use it.
		return $content;
	}

	private static function regex(): string {
		$types = array_keys( self::$types );
		usort( $types, static fn( $a, $b ) => strlen( $b ) <=> strlen( $a ) );
		$types = implode( '|', array_map( 'preg_quote', $types ) );

		return '/(\[?)\[(' . $types . ')(?:[ \t]+(.*?))?(?:[ \t]+\/)?\]'
			. '(?:([^[]*?)\[\/\2\])?(\]?)/su';
	}

	private static function build_tag( array $m ): ?FormTag {
		[ , $open, $type, $attr, $content, $close ] = $m;
		$type = strtolower( (string) $type );

		// [[foo]] is escaped literal.
		if ( $open === '[' && $close === ']' ) {
			return null;
		}

		$features = self::$types[ $type ]['features'] ?? [];
		$tag      = new FormTag();
		$tag->type     = $type;
		$tag->basetype = rtrim( $type, '*' );
		$tag->features = $features;
		$tag->content  = trim( (string) $content );

		if ( (string) $attr === '' ) {
			return $tag;
		}

		$parts = self::parse_attr( (string) $attr );
		if ( $parts === null ) {
			return null;
		}

		[ $name, $options, $raw_values ] = $parts;

		if ( ! empty( $features['name-attr'] ) ) {
			if ( ! preg_match( '/^[A-Za-z][-A-Za-z0-9_:.]*$/', (string) $name ) ) {
				return null;
			}
			$tag->raw_name = (string) $name;
			$tag->name     = strtr( $tag->raw_name, '.', '_' );
		}

		$tag->options    = $options;
		$tag->raw_values = $raw_values;
		$tag->values     = $raw_values;
		$tag->labels     = $raw_values;

		if ( ! empty( $features['selectable'] ) && class_exists( __NAMESPACE__ . '\\FormPipe_Pipes' ) ) {
			$tag->pipes = new FormPipe_Pipes( $raw_values );
			if ( $tag->pipes->zero() ) {
				$tag->pipes = null;
			} else {
				$tag->values = $tag->pipes->collect_befores();
				$tag->labels = $tag->pipes->collect_afters() ?: $tag->values;
			}
		}

		return $tag;
	}

	/**
	 * Parse `[tag name opt:val "quoted value"]`.
	 *
	 * @return array{0:string,1:string[],2:string[]}|null
	 */
	private static function parse_attr( string $attr ): ?array {
		$attr = trim( $attr );

		if ( $attr === '' ) {
			return [ '', [], [] ];
		}

		$tokens = preg_match_all(
			'/[A-Za-z0-9_:.?#&@=|*\/%\-\+]+|"[^"]*"|\'[^\']*\'/',
			$attr,
			$m
		) ? $m[0] : [];

		if ( ! $tokens ) {
			return null;
		}

		$name       = '';
		$options    = [];
		$raw_values = [];

		$first = $tokens[0] ?? '';
		if ( $first !== '' && $first[0] !== '"' && $first[0] !== "'" ) {
			$name = (string) array_shift( $tokens );
		}

		foreach ( $tokens as $tok ) {
			if ( $tok[0] === '"' || $tok[0] === "'" ) {
				$raw_values[] = trim( $tok, "\"' " );
			} else {
				$options[] = $tok;
			}
		}

		return [ $name, $options, $raw_values ];
	}
}

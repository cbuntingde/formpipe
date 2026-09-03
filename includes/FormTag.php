<?php
namespace FormPipe;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable value object representing a scanned form-tag.
 *
 * Built by FormTagsManager::scan(), consumed by:
 *   - field modules (handler receives a FormTag),
 *   - the form renderer,
 *   - the submission validator.
 *
 * No ArrayAccess, no __get magic. Properties are read by name.
 */
final class FormTag {

	public string $type = '';
	public string $basetype = '';
	public string $raw_name = '';
	public string $name = '';
	/** @var string[] */
	public array $options = [];
	/** @var string[] */
	public array $raw_values = [];
	/** @var string[] */
	public array $values = [];
	/** @var string[] */
	public array $labels = [];
	public string $content = '';
	/** @var array<string,bool> */
	public array $features = [];
	public ?FormPipe_Pipes $pipes = null;

	public function is_required(): bool {
		return str_ends_with( $this->type, '*' );
	}

	public function has_option( string $name ): bool {
		$pattern = '/^' . preg_quote( $name, '/' ) . '(?::.+)?$/i';

		foreach ( $this->options as $option ) {
			if ( preg_match( $pattern, $option ) ) {
				return true;
			}
		}

		return false;
	}

	public function get_option( string $name, string $pattern = '.+', bool $single = false ) {
		$regex = '/^' . preg_quote( $name, '/' ) . ':(' . $pattern . ')$/i';
		$hits  = [];

		foreach ( $this->options as $option ) {
			if ( preg_match( $regex, $option, $m ) ) {
				if ( $single ) {
					return $m[1];
				}
				$hits[] = $m[1];
			}
		}

		return $single ? false : ( $hits ?: false );
	}

	public function get_class_option( string $default = '' ): string {
		$defaults = $default !== '' ? preg_split( '/\s+/', $default ) ?: [] : [];
		$classes  = array_merge( $defaults, (array) $this->get_option( 'class', '.+', false ) );

		$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );

		return implode( ' ', array_unique( $classes ) );
	}

	public function get_id_option(): string {
		$option = (string) ( $this->get_option( 'id', '[A-Za-z0-9_\-]+', true ) ?: '' );

		if ( $option === '' || str_starts_with( $option, 'formpipe' ) ) {
			return '';
		}

		return $option;
	}

	public function get_data_option( array $allowed ): array {
		$raw   = (array) $this->get_option( 'data', '.+', false );
		$valid = [];

		foreach ( $raw as $item ) {
			[ $key, $val ] = array_pad( explode( ':', (string) $item, 2 ), 2, '' );
			$key = trim( $key );
			$val = trim( $val );

			if ( $key !== '' && in_array( $key, $allowed, true ) ) {
				$valid[ sanitize_key( $key ) ] = $val;
			}
		}

		return $valid;
	}

	/**
	 * Default value lookup. Supports:
	 *   - "user_<key>"     -> wp_get_current_user()-><key>
	 *   - "user_meta_<key>" -> get_user_meta( get_current_user_id(), $key, true )
	 *   - "get"            -> $_GET value
	 *   - "post"           -> $_POST value
	 *   - "post_meta"      -> get_post_meta( get_the_ID(), $name, true )
	 *   - "shortcode_attr" -> $form->shortcode_attr( $name )
	 *   - numeric          -> $tag->values[$n-1]
	 */
	public function get_default_option( string $default_value = '', bool $multiple = false, bool $shifted = false ) {
		$opts = (array) $this->get_option( 'default', '.+', false );
		if ( $opts === [] ) {
			return $multiple ? [] : $default_value;
		}

		$values = [];

		foreach ( $opts as $opt ) {
			$opt = (string) $opt;

			if ( str_starts_with( $opt, 'user_meta_' ) && is_user_logged_in() ) {
				$meta_key = substr( $opt, 10 );
				$val      = (string) get_user_meta( get_current_user_id(), $meta_key, true );
				if ( $val !== '' ) {
					if ( $multiple ) {
						$values[] = $val;
					} else {
						return $val;
					}
				}
				continue;
			}

			if ( str_starts_with( $opt, 'user_' ) && is_user_logged_in() ) {
				$user = wp_get_current_user();
				$prop = substr( $opt, 5 );
				$val  = (string) $user->get( $prop );
				if ( $val !== '' ) {
					if ( $multiple ) {
						$values[] = $val;
					} else {
						return $val;
					}
				}
				continue;
			}

			if ( $opt === 'post_meta' && in_the_loop() ) {
				$val = (string) get_post_meta( (int) get_the_ID(), $this->name, true );
				if ( $val !== '' ) {
					if ( $multiple ) {
						$values[] = $val;
					} else {
						return $val;
					}
				}
				continue;
			}

			if ( $opt === 'get' ) {
				$val = formpipe_superglobal_get( $this->name );
				if ( $val !== '' ) {
					if ( $multiple ) {
						$values[] = $val;
					} else {
						return $val;
					}
				}
				continue;
			}

			if ( $opt === 'post' ) {
				$val = formpipe_superglobal_post( $this->name );
				if ( $val !== '' ) {
					if ( $multiple ) {
						$values[] = $val;
					} else {
						return $val;
					}
				}
				continue;
			}

			if ( $opt === 'shortcode_attr' ) {
				$form = Form::get_current();
				if ( $form !== null ) {
					$val = $form->shortcode_attr( $this->name );
					if ( $val !== '' ) {
						if ( $multiple ) {
							$values[] = $val;
						} else {
							return $val;
						}
					}
				}
				continue;
			}

			if ( preg_match( '/^\d+(?:_\d+)*$/', $opt ) ) {
				foreach ( explode( '_', $opt ) as $num ) {
					$idx = (int) $num - ( $shifted ? 0 : 1 );
					if ( isset( $this->values[ $idx ] ) ) {
						if ( $multiple ) {
							$values[] = $this->values[ $idx ];
						} else {
							return $this->values[ $idx ];
						}
					}
				}
			}
		}

		if ( $multiple ) {
			return array_values( array_unique( $values ) );
		}

		return $default_value;
	}

	public function to_array(): array {
		return [
			'type'       => $this->type,
			'basetype'   => $this->basetype,
			'raw_name'   => $this->raw_name,
			'name'       => $this->name,
			'options'    => $this->options,
			'raw_values' => $this->raw_values,
			'values'     => $this->values,
			'labels'     => $this->labels,
			'content'    => $this->content,
		];
	}
}

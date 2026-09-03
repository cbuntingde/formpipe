<?php
namespace FormPipe;
/**
 * date / date* / time / time* module.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'formpipe_init', static function (): void {
	\FormPipe\FormTagsManager::register(
		[ 'date', 'date*', 'time', 'time*' ],
		static function ( \FormPipe\FormTag $tag ): string {
			$type = $tag->basetype;

			$atts = [
				'type'  => $type,
				'name'  => $tag->name,
				'id'    => $tag->get_id_option() ?: null,
				'class' => $tag->get_class_option( 'formpipe-field' ) ?: null,
			];

			if ( $tag->is_required() ) {
				$atts['required']      = true;
				$atts['aria-required'] = 'true';
			}

			if ( $type === 'date' ) {
				foreach ( [
					'min' => '\d{4}-\d{2}-\d{2}',
					'max' => '\d{4}-\d{2}-\d{2}',
				] as $opt => $pattern ) {
					$v = $tag->get_option( $opt, $pattern, true );
					if ( $v !== false ) {
						$atts[ $opt ] = $v;
					}
				}
				$step = $tag->get_option( 'step', '\d+', true );
				if ( $step !== false ) {
					$atts['step'] = $step;
				}
			}

			$default = $tag->get_default_option();
			if ( $default !== '' ) {
				$atts['value'] = (string) $default;
			}

			return sprintf(
				'<span class="formpipe-control" data-name="%1$s"><input %2$s /></span>',
				esc_attr( $tag->name ),
				\FormPipe\format_atts( $atts )
			);
		},
		[ 'name-attr' => true ]
	);
} );

add_filter( 'formpipe_posted_date',  static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, 10, 1 );
add_filter( 'formpipe_posted_date*', static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, 10, 1 );
add_filter( 'formpipe_posted_time',  static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, 10, 1 );
add_filter( 'formpipe_posted_time*', static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, 10, 1 );

$validate_date = static function ( \FormPipe\Validation $v, \FormPipe\FormTag $t, $value ): \FormPipe\Validation {
	if ( $value === '' || $value === null ) {
		return $v;
	}
	if ( ! formpipe_is_date( (string) $value ) ) {
		$v->add_error( $t->name, __( 'Please enter a valid date.', 'formpipe' ) );
	}
	return $v;
};

add_filter( 'formpipe_validate_date',  $validate_date, 10, 3 );
add_filter( 'formpipe_validate_date*', $validate_date, 10, 3 );

add_filter( 'formpipe_validate_time', static function ( \FormPipe\Validation $v, \FormPipe\FormTag $t, $value ): \FormPipe\Validation {
	if ( $value === '' || $value === null ) {
		return $v;
	}
	if ( ! formpipe_is_time( (string) $value ) ) {
		$v->add_error( $t->name, __( 'Please enter a valid time.', 'formpipe' ) );
	}
	return $v;
}, 10, 3 );

add_filter( 'formpipe_validate_time*', static function ( \FormPipe\Validation $v, \FormPipe\FormTag $t, $value ): \FormPipe\Validation {
	if ( $value === '' || $value === null ) {
		return $v;
	}
	if ( ! formpipe_is_time( (string) $value ) ) {
		$v->add_error( $t->name, __( 'Please enter a valid time.', 'formpipe' ) );
	}
	return $v;
}, 10, 3 );

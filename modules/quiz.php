<?php
namespace FormPipe;
/**
 * quiz / quiz* module.
 *
 * Tag syntax: [quiz your-q "Question|answer"]
 */

defined( 'ABSPATH' ) || exit;

add_action( 'formpipe_init', static function (): void {
	\FormPipe\FormTagsManager::register(
		[ 'quiz', 'quiz*' ],
		static function ( \FormPipe\FormTag $tag ): string {
			$question = $tag->values[0] ?? '';
			$answer   = $tag->labels[0] ?? '';

			$atts = [
				'type'        => 'text',
				'name'        => $tag->name,
				'id'          => $tag->get_id_option() ?: null,
				'class'       => $tag->get_class_option( 'formpipe-field' ) ?: null,
				'size'        => (int) ( $tag->get_option( 'size', '\d+', true ) ?: 40 ),
				'maxlength'   => (int) ( $tag->get_option( 'maxlength', '\d+', true ) ?: 0 ) ?: null,
				'tabindex'    => (int) ( $tag->get_option( 'tabindex', '-?\d+', true ) ?: 0 ) ?: null,
			];

			if ( $tag->is_required() ) {
				$atts['required']      = true;
				$atts['aria-required'] = 'true';
			}

			$atts = array_filter( $atts, static fn( $v ) => $v !== null && $v !== '' && $v !== 0 );

			// Stash the answer in a data attr so JS can prefill for spam tests.
			return sprintf(
				'<span class="formpipe-control formpipe-quiz" data-name="%1$s">'
					. '<span class="formpipe-quiz-question">%2$s</span>'
					. '<input %3$s data-quiz-answer="%4$s" />'
				. '</span>',
				esc_attr( $tag->name ),
				esc_html( $question ),
				\FormPipe\format_atts( $atts ),
				esc_attr( $answer )
			);
		},
		[ 'name-attr' => true, 'selectable' => true ]
	);
} );

$validate_quiz = static function ( \FormPipe\Validation $v, \FormPipe\FormTag $t, $value ): \FormPipe\Validation {
	$answer = $t->labels[0] ?? '';
	if ( $answer === '' ) {
		return $v;
	}
	if ( strcasecmp( trim( (string) $value ), trim( $answer ) ) !== 0 ) {
		$v->add_error( $t->name, __( 'Your answer is incorrect.', 'formpipe' ) );
	}
	return $v;
};

add_filter( 'formpipe_validate_quiz',  $validate_quiz, 10, 3 );
add_filter( 'formpipe_validate_quiz*', $validate_quiz, 10, 3 );

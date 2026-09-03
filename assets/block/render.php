<?php
/**
 * Server render for the FormPipe block. Delegates to Form::render.
 *
 * @param array $attrs  Block attributes.
 */
$form_id = (int) ( $attrs['formId'] ?? 0 );

if ( $form_id <= 0 ) {
	return '';
}

$form = \FormPipe\Form::from_post( $form_id );

if ( $form === null ) {
	return '';
}

echo $form->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

<?php
namespace FormPipe;

defined( 'ABSPATH' ) || exit;

/**
 * A single pipe: before|after.
 */
final class FormPipe_Pipe {

	public string $before;
	public string $after;

	public function __construct( string $text ) {
		$text    = (string) $text;
		$pipe_at = strpos( $text, '|' );

		if ( $pipe_at === false ) {
			$this->before = $this->after = formpipe_strip_whitespaces( $text );
		} else {
			$this->before = formpipe_strip_whitespaces( substr( $text, 0, $pipe_at ) );
			$this->after  = formpipe_strip_whitespaces( substr( $text, $pipe_at + 1 ) );
		}
	}
}

/**
 * A list of pipes; supports lookups and canonicalization.
 */
final class FormPipe_Pipes {

	/** @var FormPipe_Pipe[] */
	private array $pipes = [];

	public function __construct( array $texts = [] ) {
		foreach ( $texts as $text ) {
			$this->pipes[] = new FormPipe_Pipe( (string) $text );
		}
	}

	public function do_pipe( string $input ): string {
		$canonical_input = strtolower( formpipe_canonicalize( $input, 'as-is' ) );

		foreach ( $this->pipes as $pipe ) {
			$canonical_before = strtolower( formpipe_canonicalize( $pipe->before, 'as-is' ) );

			if ( $canonical_input === $canonical_before ) {
				return $pipe->after;
			}
		}

		return $input;
	}

	public function collect_befores(): array {
		return array_map( static fn( FormPipe_Pipe $p ) => $p->before, $this->pipes );
	}

	public function collect_afters(): array {
		return array_map( static fn( FormPipe_Pipe $p ) => $p->after, $this->pipes );
	}

	public function zero(): bool {
		return $this->pipes === [];
	}

	public function random_pipe(): ?FormPipe_Pipe {
		if ( $this->zero() ) {
			return null;
		}
		return $this->pipes[ array_rand( $this->pipes ) ];
	}

	public function to_array(): array {
		return array_map(
			static fn( FormPipe_Pipe $p ) => [ $p->before, $p->after ],
			$this->pipes
		);
	}
}

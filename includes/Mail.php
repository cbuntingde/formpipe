<?php
namespace FormPipe;

defined( 'ABSPATH' ) || exit;

/**
 * Mail composer. Replaces `[tag-name]` with posted values and special
 * values, then calls wp_mail().
 *
 * Templates:
 *   - subject, sender, recipient, body, headers, use_html, exclude_blank
 *   - optional attachments (one filename per line; uploaded files appear
 *     automatically when their field name appears in [attachment] tags
 *     in the body or attachments block).
 *
 * Special mail-tags (resolved before regular tags):
 *   [_remote_ip], [_user_agent], [_url], [_date], [_time],
 *   [_post_title], [_post_url], [_invalid_fields], [_serial_number]
 */
final class Mail {

	private static ?Mail $current = null;

	public string $name = '';
	public string $locale = '';
	/** @var array<string,mixed> */
	public array $template;
	public string $current_component = '';
	public bool $use_html = false;
	public bool $exclude_blank = false;

	public function __construct( string $name, array $template ) {
		$this->name              = trim( $name );
		$this->template          = wp_parse_args( $template, [
			'subject'           => '',
			'sender'            => '',
			'body'              => '',
			'recipient'         => '',
			'additional_headers'=> '',
			'attachments'       => '',
			'use_html'          => false,
			'exclude_blank'     => false,
		] );

		$this->use_html      = (bool) $this->template['use_html'];
		$this->exclude_blank = (bool) $this->template['exclude_blank'];
	}

	public static function send( array $template, string $name, array $values, array $specials = [], array $uploads = [] ): bool {
		if ( empty( $template['active'] ) ) {
			return true;
		}

		self::$current = new self( $name, $template );

		if ( $submission = Submission::get_instance() ) {
			$cf            = $submission->get_form();
			self::$current->locale = $cf->locale();
		}

		$components = self::$current->compose( $values, $specials, $uploads );

		if ( $components === null ) {
			return false;
		}

		return (bool) wp_mail(
			$components['recipient'],
			$components['subject'],
			$components['body'],
			$components['headers'],
			$components['attachments']
		);
	}

	public static function get_current(): ?self {
		return self::$current;
	}

	/**
	 * @return array{subject:string,sender:string,recipient:string,body:string,headers:string[],attachments:string[]}
	 */
	public function compose( array $values, array $specials, array $uploads ): array {
		$subject = $this->replace_tags( (string) $this->template['subject'], $values, $specials, false );
		$sender  = $this->replace_tags( (string) $this->template['sender'],  $values, $specials, false );
		$body    = $this->replace_tags( (string) $this->template['body'],    $values, $specials, $this->use_html );
		$rcpt    = $this->replace_tags( (string) $this->template['recipient'],$values, $specials, false );

		$recipient     = $this->extract_email( $rcpt );
		$headers       = $this->build_headers( (string) $this->template['additional_headers'], $values, $specials, $sender );
		$attachments   = $this->resolve_attachments( (string) $this->template['attachments'], $values, $uploads );

		if ( $this->use_html ) {
			$body = $this->wrap_html( $body, $subject );
		}

		return [
			'subject'    => $subject,
			'sender'     => $sender,
			'recipient'  => $recipient,
			'body'       => $body,
			'headers'    => $headers,
			'attachments'=> $attachments,
		];
	}

	public function replace_tags( string $content, array $values, array $specials, bool $html ): string {
		// Split by line so exclude_blank can drop empty lines.
		$out = '';
		foreach ( preg_split( '/\r?\n/', $content ) ?: [ $content ] as $line ) {
			$replaced = $this->replace_in_line( $line, $values, $specials, $html );

			if ( $this->exclude_blank && $this->line_has_only_blank_tags( $replaced ) ) {
				continue;
			}

			$out .= $replaced . "\n";
		}

		return rtrim( $out, "\n" );
	}

	private function replace_in_line( string $line, array $values, array $specials, bool $html ): string {
		return preg_replace_callback(
			'/(\[?)\[([a-zA-Z_][a-zA-Z0-9_:\-.]*)(?:[ \t]+((?:"[^"]*"|\'[^\']*\'|[^]]*?)*))?\](?:(\]?)?)/',
			function ( array $m ) use ( $values, $specials, $html ): string {
				[ , $open, $name, $args, $close ] = $m;
				$tag = $m[0];

				// [[foo]] escape.
				if ( $open === '[' && ( $close === ']' || ( isset( $m[4] ) && $m[4] === ']' ) ) ) {
					return (string) substr( $tag, 1, -1 );
				}

				// Special mail-tags.
				if ( array_key_exists( $name, $specials ) ) {
					return (string) $specials[ $name ];
				}

				if ( ! array_key_exists( $name, $values ) ) {
					return $tag;
				}

				$value = $values[ $name ];
				$value = formpipe_flatten( $value );

				// Per-tag type formatting hook.
				$value = apply_filters( 'formpipe_mail_tag_replaced', $value, $name, $html );

				if ( $html ) {
					$value = wptexturize( $value );
				}
				$value = esc_html( $value );

				return (string) $value;
			},
			$line
		);
	}

	private function line_has_only_blank_tags( string $line ): bool {
		// After replacement, any non-whitespace chars left means it has content.
		return trim( preg_replace( '/\s+/', '', $line ) ?? '' ) === '';
	}

	private function extract_email( string $line ): string {
		// Prefer the part inside <…>.
		if ( preg_match( '/<([^>]+)>/', $line, $m ) ) {
			$candidate = trim( $m[1] );
			if ( is_email( $candidate ) ) {
				return $candidate;
			}
		}

		if ( preg_match( '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $line, $m ) ) {
			return $m[0];
		}

		return '';
	}

	private function build_headers( string $template, array $values, array $specials, string $sender ): array {
		$headers = [];

		if ( $sender !== '' ) {
			$headers[] = 'From: ' . str_replace( [ "\r", "\n" ], '', $sender );
		}

		if ( $this->use_html ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		}

		foreach ( preg_split( '/\r?\n/', $template ) ?: [] as $line ) {
			$line = trim( $this->replace_tags( $line, $values, $specials, false ) );
			if ( $line === '' || ! str_contains( $line, ':' ) ) {
				continue;
			}
			$headers[] = $line;
		}

		return $headers;
	}

	private function resolve_attachments( string $template, array $values, array $uploads ): array {
		$list = [];

		foreach ( preg_split( '/\r?\n/', $template ) ?: [] as $line ) {
			$line = trim( $line );
			if ( $line === '' || str_starts_with( $line, '[' ) ) {
				continue;
			}
			$path = path_join( WP_CONTENT_DIR, $line );
			if ( formpipe_is_file_path_in_content_dir( $path ) && is_readable( $path ) ) {
				$list[] = $path;
			}
		}

		// Uploaded files: include any whose field name appears in the body
		// or attachments block.
		foreach ( $uploads as $name => $paths ) {
			foreach ( (array) $paths as $path ) {
				if ( str_contains( $template, '[' . $name . ']' ) || str_contains( $this->template['body'], '[' . $name . ']' ) ) {
					$list[] = $path;
				}
			}
		}

		return $list;
	}

	private function wrap_html( string $body, string $subject ): string {
		$lang_atts = '';
		if ( $this->locale !== '' ) {
			$lang_atts = ' ' . format_atts( [
				'dir' => formpipe_is_rtl( $this->locale ) ? 'rtl' : 'ltr',
				'lang'=> str_replace( '_', '-', $this->locale ),
			] );
		}

		$header = (string) apply_filters( 'formpipe_mail_html_header',
			"<!doctype html>\n<html{$lang_atts}>\n<head>\n<meta charset=\"utf-8\">\n<title>"
			. esc_html( $subject )
			. "</title>\n</head>\n<body>\n",
			$this
		);

		$footer = (string) apply_filters( 'formpipe_mail_html_footer',
			"\n</body>\n</html>\n",
			$this
		);

		return $header . wpautop( wp_kses( $body, formpipe_kses_allowed_html() ) ) . $footer;
	}
}

/**
 * Mail-tagged-text helper used when rendering templates outside of a
 * direct Submission context.
 */
final class MailTaggedText {

	private string $content;
	private bool $html;
	private array $values;
	private array $specials;

	public function __construct( string $content, array $values = [], array $specials = [], bool $html = false ) {
		$this->content  = $content;
		$this->html     = $html;
		$this->values   = $values;
		$this->specials = $specials;
	}

	public function replace(): string {
		$mail = new Mail( 'preview', [
			'subject' => '',
			'body'    => '',
			'use_html'=> $this->html,
		] );
		return $mail->replace_tags( $this->content, $this->values, $this->specials, $this->html );
	}
}

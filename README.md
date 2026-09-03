# FormPipe

A small contact-form WordPress plugin. CPT storage, one scanner, one admin page, one block, REST.

> **Status:** Experimental / pre-release. Modernized rewrite of Contact Form 7. Has **not** undergone rigorous testing or a security audit. Do not deploy on production sites without independent review.

- **Requires PHP:** 8.0+
- **Requires WordPress:** 6.5+
- **License:** GPL-2.0-or-later

## Development

```bash
composer install
composer lint       # phpcs (WordPress coding standards)
composer lint:fix   # auto-fix what phpcbf can
composer test       # phpunit (WP test suite)
composer test:smoke # standalone smoke harness (no WP needed)
composer check      # lint + test
```

CI runs on every push and PR via `.github/workflows/ci.yml`, across PHP 8.0 – 8.3.

## Build / release

The `.distignore` keeps dev tooling out of the WordPress.org release zip.

## Plugin directory

- `formpipe.php` — plugin header + bootstrap
- `includes/` — core classes (`Plugin`, `Form`, `FormTag`, `Pipes`, `Mail`, `Validation`, …)
- `modules/` — field-type modules (`text`, `email`, `select`, `file`, `captcha`, …)
- `admin/` — admin UI (list table, editor view)
- `rest/` — REST API controller (`/formpipe/v1/feedback`)
- `assets/` — frontend CSS/JS, block editor assets
- `tests/` — PHPUnit + standalone smoke harness

## License

GPL-2.0-or-later. See `LICENSE`.

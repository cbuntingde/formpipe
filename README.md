# FormPipe

FormPipe is a small WordPress plugin that lets site owners build and manage contact forms. Forms are stored as a custom post type, rendered through a single block, submitted via a REST endpoint, and managed from a dedicated admin page.

> **Status:** Experimental / pre-release. This plugin is a modernized rewrite of an older contact-form plugin and has not yet undergone rigorous testing or a security audit. Do not deploy on production sites without independent review.

- Requires WordPress 6.5 or newer
- Requires PHP 8.0 or newer
- Released under the GNU General Public License, version 2 or later

## What the plugin does

- Provides a contact-form custom post type with title, template, mail settings, and form settings.
- Scans a form template for tags like `[text* your-name]` or `[email your-email]` and replaces them with the corresponding HTML inputs.
- Ships a set of field types: text, email, textarea, number, date, select, radio, checkbox, hidden, file, count, quiz, captcha, acceptance, submit, and response.
- Validates submitted values (required fields, email format, file size/type, anti-spam rate limits, optional captcha).
- Sends a notification email with mail-tag replacement for posted values and special tokens (`[_remote_ip]`, `[_date]`, etc.).
- Escapes untrusted content in both plain-text and HTML email bodies.
- Exposes a REST endpoint at `/formpipe/v1/feedback` for front-end submissions.
- Provides a block (`formpipe/form`) and a shortcode (`[contact-form ...]`) for embedding forms in posts and pages.

## Repository layout

- `formpipe.php` — plugin header and bootstrap.
- `includes/` — core classes for forms, validation, mail, the custom post type, the block, the REST controller, and helpers.
- `modules/` — one file per field type, each implementing its own render and validation hooks.
- `admin/` — admin list table, editor view, and integration helpers.
- `rest/` — REST API controller for form submissions.
- `assets/` — front-end and admin CSS, JavaScript, and block-editor assets.
- `tests/` — test harness. Includes a standalone smoke test that exercises the scanner, validation, mail-tag replacement, file upload, and rate limiting without requiring a full WordPress install.
- `uninstall.php` — cleanup routine run when the plugin is removed.
- `readme.txt` — WordPress.org-style readme used by the plugin directory.

## Recent changes

- Added a proper `.gitignore` covering OS files, editor folders, build artifacts, dependencies, and local environment files.
- Added a `.distignore` so that development tooling, test files, and configuration files are excluded from the plugin zip distributed through WordPress.org.
- Added `LICENSE`, matching the GPL-2.0-or-later license declared in the plugin header.
- Added a CI workflow on GitHub Actions that runs on every push and pull request against the `main` branch, across multiple versions of PHP and WordPress.
- Added a `composer.json` describing the project and its tooling.
- Added `phpcs.xml.dist` configuring the project's coding-standard checks.
- Added `phpunit.xml.dist` configuring the test runner, including a dedicated suite for the standalone smoke test.
- Added a PHPUnit bootstrap that loads the WordPress test suite when available.

## Credits

Maintained by the FormPipe contributors. Author of record on the plugin: FormPipe.

## License

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any later version. See `LICENSE` for the full text.

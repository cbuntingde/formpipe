=== FormPipe ===
Contributors: formpipe
Tags: contact form, form, mail, captcha
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A small, complete contact-form plugin for WordPress. CPT storage, one scanner, one admin page, one block, REST.

== Description ==

> **Notice — experimental build.** FormPipe is a modernized rewrite of *Contact Form 7*. It has **not** been rigorously tested and **has not** undergone a security audit. The upload handler, REST endpoints, and submission pipeline should be treated as pre-release. Do not deploy on a production site without independent review. The maintainers make no warranty as to fitness for any particular purpose.

FormPipe is a contact-form plugin that ships with:

- A single scanner for form-tags (text, email, url, tel, textarea, number, date, time, select, checkbox, acceptance, quiz, file, hidden, submit, response, count, captcha).
- A submission pipeline with: server-side validation, spam filters (honeypot, posted-data hash, render-time floor), acceptance gate, file-upload handler.
- REST CRUD + a `/feedback` endpoint for ajax submission.
- A Gutenberg block for embedding forms.
- A single admin page with tabs (Form, Mail, Mail 2, Messages, Additional settings) and a tag-generator dialog.
- Pipe-encoded values for select/checkbox/radio/quiz.

= Privacy =

FormPipe itself does not track users. Integrations (when installed) may contact external services; check the integration's documentation.

= Hardening =

* Set the `formpipe_require_captcha` filter to `true` to require a verified captcha on every ajax submission. Without it the public `/feedback` endpoint is gated only by the per-IP rate limit (default 10 submissions per minute; override via `formpipe_feedback_rate_limit`), the honeypot field, and the render-time floor.
* For deployments behind a CDN that sets `CF-Connecting-IP` or `X-Forwarded-For`, define `FORMPIPE_TRUSTED_PROXY_HEADER` to the trusted header name so the rate limiter sees the real client IP.

== Installation ==

1. Upload the `formpipe` directory to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen.
3. Open **Forms** in the admin sidebar and add a form.
4. Embed forms via the `[formpipe id="…"]` shortcode or the **FormPipe Form** block.

== Frequently Asked Questions ==

= Does it support spam protection? =

Yes.

* Built-in: honeypot field, posted-data hash, minimum render-time floor.
* REST `/feedback` is rate-limited per IP (default 10 submissions / minute; override via the `formpipe_feedback_rate_limit` filter). The `/schema` and `/refill` endpoints share a separate (looser) bucket so they can't be weaponized for triggering.
* Captcha is optional and opt-in. Hook `formpipe_captcha_verified` from a companion module (reCAPTCHA, Turnstile, hCaptcha); the core default is to fail-open without a module. Set the `formpipe_require_captcha` filter to `true` to fail-closed in production.
* For deployments behind a CDN that sets `CF-Connecting-IP` or `X-Forwarded-For`, define `FORMPIPE_TRUSTED_PROXY_HEADER` to the trusted header name.

= Can I send mail as HTML? =

Yes. Tick "Send as HTML" in the Mail editor. Tags are escaped automatically; the body is wrapped in a basic HTML shell.

= How do I add new fields? =

Use the **Generate Tag** buttons in the Form editor, or write the syntax by hand, e.g. `[text* your-name placeholder "Your name"]`.

== Changelog ==

= 1.0.0 =
* Initial release.
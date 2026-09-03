# FormPipe

FormPipe is a WordPress plugin for building and managing contact forms. A site owner creates a form, writes a short template that lists the fields they want, and embeds the form in any page or post. Visitors fill the form in their browser; the plugin sends a notification email and stores each submission.

> **Status:** Experimental / pre-release. FormPipe is a modernized rewrite of an older contact-form plugin and has not yet undergone rigorous testing or a security audit. Do not deploy on production sites without independent review.

- WordPress 6.5 or newer
- PHP 8.0 or newer
- Released under the GNU General Public License, version 2 or later

## What the plugin does

- Lets a site owner define one or more contact forms, each with a title, a template of fields, an email recipient, and form-level settings.
- Recognizes short tags inside a form template, such as `[text* your-name]` for a required text input or `[email your-email]` for an email input, and replaces them with the corresponding form fields.
- Includes the following field types: text, email, textarea, number, date, select, radio, checkbox, hidden, file, count, quiz, captcha, acceptance, submit, and response.
- Validates each submission. It checks required fields, email format, file size and type, and applies a per-visitor submission rate limit. An optional captcha field is supported for sites that need it.
- Sends a notification email when a form is submitted. The email body can include the values a visitor typed and a small set of automatic tokens such as the visitor's IP address and the submission date.
- Renders submitted values safely in both plain-text and HTML email bodies, so a visitor cannot inject scripts or extra markup into an outgoing message.
- Accepts submissions through a WordPress REST endpoint, which is the URL path on the site that handles form submissions sent from the browser.
- Provides a block (a content element added through the WordPress block editor, named `formpipe/form`) and a shortcode (a small text tag such as `[contact-form id="1"]`) for placing a form inside a page or post.

## How the plugin is organized

The plugin is split into a few working parts so each concern is in one place:

- The main plugin file, which carries the plugin header WordPress reads on activation and loads the rest of the plugin.
- A folder of core classes that handle forms, validation, email delivery, the storage type used for forms and submissions, the block, and the REST endpoint.
- A folder of field-type modules, one file per field type, that describe how each field renders and how its value is validated.
- A folder of admin pages that list, edit, and integrate forms from the WordPress dashboard.
- A folder of front-end and editor assets: the stylesheets and scripts the form and admin screens load in the browser.
- A uninstall routine that cleans up the plugin's stored options when a site owner removes the plugin from WordPress.
- A readme in the WordPress.org format, used by the plugin directory listing.

## Credits

Maintained by the FormPipe contributors. Author of record on the plugin: FormPipe.

## License


Created by Chris Bunting <cbuntingde@gmail.com>
